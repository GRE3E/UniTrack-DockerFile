<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/vendor/autoload.php'; // Dotenv
include_once 'config.php'; // Configuración de base de datos

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
require 'PHPMailer-master/src/Exception.php';

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

// Verificar conexión
if (!$conn) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "No hay conexión a la base de datos"]);
    exit();
}

// Verificar variables SMTP
if (empty($_ENV['SMTP_HOST']) || empty($_ENV['SMTP_USER']) || empty($_ENV['SMTP_PASS'])) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Variables SMTP no cargadas"]);
    exit();
}

// Obtener ID del usuario por correo
function getUserIdByEmail($email) {
    global $conn;
    $email = trim(strtolower($email));
    $sql = "SELECT idusuario FROM usuario WHERE LOWER(correo) = $1";
    $result = pg_query_params($conn, $sql, array($email));
    
    if ($result && pg_num_rows($result) > 0) {
        $row = pg_fetch_assoc($result);
        return $row['idusuario'];
    }
    return null;
}

// Enviar código
function sendUserCode($email) {
    global $conn;

    $userId = getUserIdByEmail($email);
    if (!$userId) {
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "Correo no registrado"]);
        return;
    }

    $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

    $sql = "INSERT INTO verificacion_codigo (id_usuario, codigo, intentos, usado) VALUES ($1, $2, $3, $4)";
    $result = pg_query_params($conn, $sql, array($userId, $code, 0, 'false'));

    if ($result) {
        // Enviar correo
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

            $mail->isHTML(true);
            $mail->Subject = 'Código de verificación';
            $mail->Body = "<p>Tu código de verificación es: <strong>$code</strong></p><p>Este código es válido por 15 minutos.</p>";

            $mail->send();
            echo json_encode(["success" => true, "message" => "Código enviado al correo"]);
        } catch (Exception $e) {
            error_log("Error al enviar correo: " . $mail->ErrorInfo);
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Error al enviar el correo"]);
        }
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Error al guardar el código"]);
        return;
    }
}

// Verificar código
function verifyUserCode($code) {
    global $conn;
    $sql = "SELECT * FROM verificacion_codigo WHERE codigo = $1 AND usado = false AND fecha_creacion >= (NOW() - INTERVAL '15 minutes')";
    $result = pg_query_params($conn, $sql, array($code));

    if ($result && pg_num_rows($result) > 0) {
        $row = pg_fetch_assoc($result);
        
        if ($row['intentos'] >= 5) {
            http_response_code(429);
            echo json_encode(["success" => false, "error" => "Demasiados intentos. Solicita un nuevo código."]);
            return;
        }

        // Resetear intentos
        $sqlReset = "UPDATE verificacion_codigo SET intentos = 0 WHERE codigo = $1";
        $resultReset = pg_query_params($conn, $sqlReset, array($code));

        if ($resultReset) {
            echo json_encode(["success" => true, "id_user" => $row['id_usuario']]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Error al resetear intentos"]);
        }
        return;
    } else {
        // Incrementar intentos
        $sqlUpdate = "UPDATE verificacion_codigo SET intentos = intentos + 1 WHERE codigo = $1";
        $resultUpdate = pg_query_params($conn, $sqlUpdate, array($code));

        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Código inválido o expirado"]);
        return;
    }
}

// Restablecer contraseña
function resetUserPassword($password, $code) {
    global $conn;
    $sql = "SELECT id_usuario FROM verificacion_codigo WHERE codigo = $1 AND usado = false AND fecha_creacion >= (NOW() - INTERVAL '15 minutes')";
    $result = pg_query_params($conn, $sql, array($code));

    if ($result && pg_num_rows($result) > 0) {
        $row = pg_fetch_assoc($result);
        $userId = $row['id_usuario'];
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $sqlUpdate = "UPDATE usuario SET contrasena = $1 WHERE idusuario = $2";
        $resultUpdate = pg_query_params($conn, $sqlUpdate, array($hash, $userId));

        if ($resultUpdate && pg_affected_rows($resultUpdate) > 0) {
            $sqlMark = "UPDATE verificacion_codigo SET usado = true WHERE codigo = $1";
            $resultMark = pg_query_params($conn, $sqlMark, array($code));

            if ($resultMark) {
                echo json_encode(["success" => true, "message" => "Contraseña actualizada"]);
            } else {
                http_response_code(500);
                echo json_encode(["success" => false, "error" => "Error al marcar código como usado"]);
            }
            return;
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "No se pudo actualizar la contraseña"]);
            return;
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Código inválido o expirado"]);
        return;
    }
}

// Routing
$data = json_decode(file_get_contents("php://input"), true);
$action = isset($data['action']) ? $data['action'] : '';

if ($action === 'send-code' && !empty($data['email'])) {
    sendUserCode($data['email']);
} elseif ($action === 'verify-code' && !empty($data['code'])) {
    verifyUserCode($data['code']);
} elseif ($action === 'reset-password' && !empty($data['password']) && !empty($data['code'])) {
    resetUserPassword($data['password'], $data['code']);
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Acción o datos inválidos"]);
}
?>