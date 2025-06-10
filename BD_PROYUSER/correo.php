<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/vendor/autoload.php'; // Para dotenv

// Cargar variables de entorno desde .env
$env_path = dirname(__DIR__) . '/.env';
if (file_exists($env_path)) {
  $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    list($name, $value) = explode('=', $line, 2);
    $_ENV[trim($name)] = trim($value);
  }
}

include_once 'config.php';

// PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
require 'PHPMailer-master/src/Exception.php';



// DEPURACIÓN: Verifica conexión a la base de datos
if (!$conn) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "No hay conexión a la base de datos"]);
    exit();
}

// DEPURACIÓN: Verifica variables de entorno SMTP
if (empty($_ENV['MAIL_USERNAME']) || empty($_ENV['MAIL_PASSWORD'])) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Variables SMTP no cargadas"]);
    exit();
}

// Obtener el idAdmin a partir del correo
function getAdminIdByEmail($email)
{
    global $conn;
    $sql = "SELECT idAdmin FROM administrador WHERE correo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['idAdmin'];
    }
    return null;
}

// Enviar código de verificación
function sendCode($email)
{
    global $conn;
    $adminId = getAdminIdByEmail($email);
    if (!$adminId) {
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "Correo no registrado"]);
        return;
    }
    $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

    // Guardar el código en la tabla verificacion_codigo_admin
    $sql = "INSERT INTO verificacion_codigo_admin (id_admin, codigo, intentos) VALUES (?, ?, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $adminId, $code);
    if ($stmt->execute()) {
        // Envío real de correo con PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USERNAME'];
            $mail->Password = $_ENV['MAIL_PASSWORD'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom($_ENV['MAIL_USERNAME'], 'Somos X');
            $mail->addAddress($email);
            $mail->Subject = 'Código de verificación';
            $mail->Body = "Tu código de verificación es: $code";

            $mail->send();
            echo json_encode(["success" => true, "message" => "Código enviado"]);
        } catch (Exception $e) {
            error_log("Error PHPMailer: " . $mail->ErrorInfo, 3, __DIR__ . '/error.log');
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "error" => "No se pudo enviar el correo"
            ]);
        }
    } else {
        http_response_code(500);
        error_log("Error al guardar código: " . $conn->error, 3, __DIR__ . '/error.log');
        echo json_encode(["success" => false, "error" => "No se pudo enviar el código"]);
    }
}

// Verificar el código de verificación (con límite de intentos)
function verifyCode($code)
{
    global $conn;
    $sql = "SELECT * FROM verificacion_codigo_admin WHERE codigo = ? AND usado = 0 AND fecha_creacion >= (NOW() - INTERVAL 15 MINUTE)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (isset($row['intentos']) && $row['intentos'] >= 5) {
            http_response_code(429);
            echo json_encode(["success" => false, "error" => "Demasiados intentos. Solicita un nuevo código."]);
            return;
        }
        $sqlReset = "UPDATE verificacion_codigo_admin SET intentos = 0 WHERE codigo = ?";
        $stmtReset = $conn->prepare($sqlReset);
        $stmtReset->bind_param("s", $code);
        $stmtReset->execute();

        echo json_encode(["success" => true, "id_admin" => $row['id_admin']]);
    } else {
        $sqlUpdate = "UPDATE verificacion_codigo_admin SET intentos = intentos + 1 WHERE codigo = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bind_param("s", $code);
        $stmtUpdate->execute();

        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Código inválido o expirado"]);
    }
}

// Restablecer la contraseña usando el código
function resetPassword($password, $code)
{
    global $conn;
    try {
        $sql = "SELECT id_admin FROM verificacion_codigo_admin WHERE codigo = ? AND usado = 0 AND fecha_creacion >= (NOW() - INTERVAL 15 MINUTE)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $adminId = $row['id_admin'];
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            // (Opcional) Log extra: correo del admin
            $stmtCorreo = $conn->prepare("SELECT correo FROM administrador WHERE idAdmin = ?");
            $stmtCorreo->bind_param("i", $adminId);
            $stmtCorreo->execute();
            $resultCorreo = $stmtCorreo->get_result();
            $correoAdmin = $resultCorreo->fetch_assoc()['correo'] ?? 'NO ENCONTRADO';
            
            $sqlUpdate = "UPDATE administrador SET contrasena = ? WHERE idAdmin = ?";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->bind_param("si", $passwordHash, $adminId);
            $stmtUpdate->execute(); // <-- Ejecuta el UPDATE primero

            // Verifica si realmente se actualizó la contraseña
            if ($stmtUpdate->affected_rows > 0) {
                $sqlMark = "UPDATE verificacion_codigo_admin SET usado = 1 WHERE codigo = ?";
                $stmtMark = $conn->prepare($sqlMark);
                $stmtMark->bind_param("s", $code);
                $stmtMark->execute();

                echo json_encode(["success" => true, "message" => "Contraseña actualizada"]);
            } else {
                http_response_code(500);
                error_log("Error al actualizar contraseña: " . $conn->error, 3, __DIR__ . '/error.log');
                echo json_encode(["success" => false, "error" => "No se pudo actualizar la contraseña"]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Código inválido o expirado"]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        error_log($e->getMessage(), 3, __DIR__ . '/error.log');
        echo json_encode(["success" => false, "error" => "Error interno"]);
    }
}

// Manejo de la petición
$data = json_decode(file_get_contents("php://input"), true);
$action = isset($data['action']) ? $data['action'] : '';

if ($action === 'send-code' && !empty($data['email'])) {
    sendCode($data['email']);
} elseif ($action === 'verify-code' && !empty($data['code'])) {
    verifyCode($data['code']);
} elseif ($action === 'reset-password' && !empty($data['password']) && !empty($data['code'])) {
    resetPassword($data['password'], $data['code']);
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Acción o datos inválidos"]);
}
