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
if (!$pdo) {
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
    global $pdo;
    
    try {
        $sql = "SELECT idadmin FROM administrador WHERE correo = $1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['idadmin'];
        }
        return null;
    } catch (PDOException $e) {
        error_log("Error en getAdminIdByEmail: " . $e->getMessage(), 3, __DIR__ . '/error.log');
        return null;
    }
}

// Enviar código de verificación
function sendCode($email)
{
    global $pdo;
    
    try {
        // Validar formato de email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Formato de correo inválido"]);
            return;
        }

        $adminId = getAdminIdByEmail($email);
        if (!$adminId) {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "Correo no registrado"]);
            return;
        }

        // Verificar si hay códigos recientes no usados (evitar spam)
        $sqlCheck = "SELECT COUNT(*) as count FROM verificacion_codigo_admin 
                     WHERE id_admin = $1 AND usado = false 
                     AND fecha_creacion >= (NOW() - INTERVAL '5 minutes')";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([$adminId]);
        $checkResult = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($checkResult['count'] > 0) {
            http_response_code(429);
            echo json_encode([
                "success" => false, 
                "error" => "Espera 5 minutos antes de solicitar otro código"
            ]);
            return;
        }

        // Invalidar códigos anteriores del mismo admin
        $sqlInvalidate = "UPDATE verificacion_codigo_admin SET usado = true WHERE id_admin = $1 AND usado = false";
        $stmtInvalidate = $pdo->prepare($sqlInvalidate);
        $stmtInvalidate->execute([$adminId]);

        // Generar nuevo código
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Guardar el código en la tabla verificacion_codigo_admin
        $sql = "INSERT INTO verificacion_codigo_admin (id_admin, codigo, intentos, usado, fecha_creacion) 
                VALUES ($1, $2, 0, false, NOW())";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$adminId, $code])) {
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
                $mail->CharSet = 'UTF-8';

                $mail->setFrom($_ENV['MAIL_USERNAME'], 'Sistema de Gestión - UCV');
                $mail->addAddress($email);
                $mail->Subject = 'Código de verificación - Recuperación de contraseña';
                
                $mail->isHTML(true);
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <h2 style='color: #333; text-align: center;'>Recuperación de Contraseña</h2>
                        <p>Hola,</p>
                        <p>Has solicitado restablecer tu contraseña. Tu código de verificación es:</p>
                        <div style='background-color: #f4f4f4; padding: 20px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 3px; margin: 20px 0;'>
                            $code
                        </div>
                        <p style='color: #666;'>Este código expira en 15 minutos.</p>
                        <p style='color: #666; font-size: 12px;'>Si no solicitaste este código, puedes ignorar este mensaje.</p>
                    </div>
                ";

                $mail->send();
                echo json_encode([
                    "success" => true, 
                    "message" => "Código enviado correctamente al correo electrónico"
                ]);
            } catch (Exception $e) {
                error_log("Error PHPMailer: " . $mail->ErrorInfo, 3, __DIR__ . '/error.log');
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "error" => "No se pudo enviar el correo electrónico"
                ]);
            }
        } else {
            http_response_code(500);
            error_log("Error al guardar código en BD", 3, __DIR__ . '/error.log');
            echo json_encode(["success" => false, "error" => "No se pudo generar el código"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Error en sendCode: " . $e->getMessage(), 3, __DIR__ . '/error.log');
        echo json_encode(["success" => false, "error" => "Error interno del servidor"]);
    }
}

// Verificar el código de verificación (con límite de intentos)
function verifyCode($code)
{
    global $pdo;
    
    try {
        // Validar formato del código
        if (!preg_match('/^\d{6}$/', $code)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Formato de código inválido"]);
            return;
        }

        $sql = "SELECT * FROM verificacion_codigo_admin 
                WHERE codigo = $1 AND usado = false 
                AND fecha_creacion >= (NOW() - INTERVAL '15 minutes')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$code]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Verificar límite de intentos
            if ($result['intentos'] >= 5) {
                // Marcar como usado para evitar más intentos
                $sqlMark = "UPDATE verificacion_codigo_admin SET usado = true WHERE codigo = $1";
                $stmtMark = $pdo->prepare($sqlMark);
                $stmtMark->execute([$code]);
                
                http_response_code(429);
                echo json_encode([
                    "success" => false, 
                    "error" => "Demasiados intentos fallidos. Solicita un nuevo código."
                ]);
                return;
            }

            // Código válido - resetear intentos
            $sqlReset = "UPDATE verificacion_codigo_admin SET intentos = 0 WHERE codigo = $1";
            $stmtReset = $pdo->prepare($sqlReset);
            $stmtReset->execute([$code]);

            echo json_encode([
                "success" => true, 
                "id_admin" => $result['id_admin'],
                "message" => "Código verificado correctamente"
            ]);
        } else {
            // Incrementar intentos si el código existe pero es inválido/expirado
            $sqlUpdate = "UPDATE verificacion_codigo_admin 
                         SET intentos = intentos + 1 
                         WHERE codigo = $1 AND usado = false";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([$code]);

            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Código inválido o expirado"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Error en verifyCode: " . $e->getMessage(), 3, __DIR__ . '/error.log');
        echo json_encode(["success" => false, "error" => "Error interno del servidor"]);
    }
}

// Restablecer la contraseña usando el código
function resetPassword($password, $code)
{
    global $pdo;
    
    try {
        // Validar contraseña
        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode([
                "success" => false, 
                "error" => "La contraseña debe tener al menos 6 caracteres"
            ]);
            return;
        }

        if (strlen($password) > 255) {
            http_response_code(400);
            echo json_encode([
                "success" => false, 
                "error" => "La contraseña es demasiado larga"
            ]);
            return;
        }

        // Validar formato del código
        if (!preg_match('/^\d{6}$/', $code)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Formato de código inválido"]);
            return;
        }

        // Iniciar transacción
        $pdo->beginTransaction();

        // Verificar código válido
        $sql = "SELECT id_admin FROM verificacion_codigo_admin 
                WHERE codigo = $1 AND usado = false 
                AND fecha_creacion >= (NOW() - INTERVAL '15 minutes')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$code]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $adminId = $result['id_admin'];
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            // Actualizar contraseña
            $sqlUpdate = "UPDATE administrador SET contrasena = $1 WHERE idadmin = $2";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([$passwordHash, $adminId]);

            // Verificar si se actualizó
            if ($stmtUpdate->rowCount() > 0) {
                // Marcar código como usado
                $sqlMark = "UPDATE verificacion_codigo_admin SET usado = true WHERE codigo = $1";
                $stmtMark = $pdo->prepare($sqlMark);
                $stmtMark->execute([$code]);

                // Obtener información del admin para log
                $sqlAdmin = "SELECT correo, nombres, apellidos FROM administrador WHERE idadmin = $1";
                $stmtAdmin = $pdo->prepare($sqlAdmin);
                $stmtAdmin->execute([$adminId]);
                $adminInfo = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
                
                // Log de seguridad
                error_log("Contraseña restablecida para admin: " . ($adminInfo['correo'] ?? 'ID: ' . $adminId), 3, __DIR__ . '/security.log');

                $pdo->commit();
                echo json_encode([
                    "success" => true, 
                    "message" => "Contraseña actualizada correctamente"
                ]);
            } else {
                $pdo->rollBack();
                http_response_code(500);
                error_log("Error: No se pudo actualizar la contraseña para admin ID: $adminId", 3, __DIR__ . '/error.log');
                echo json_encode([
                    "success" => false, 
                    "error" => "No se pudo actualizar la contraseña"
                ]);
            }
        } else {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Código inválido o expirado"]);
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        error_log("Error en resetPassword: " . $e->getMessage(), 3, __DIR__ . '/error.log');
        echo json_encode(["success" => false, "error" => "Error interno del servidor"]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        error_log("Error general en resetPassword: " . $e->getMessage(), 3, __DIR__ . '/error.log');
        echo json_encode(["success" => false, "error" => "Error interno del servidor"]);
    }
}

// Función para limpiar códigos expirados (opcional - llamar periódicamente)
function cleanExpiredCodes()
{
    global $pdo;
    
    try {
        $sql = "DELETE FROM verificacion_codigo_admin 
                WHERE fecha_creacion < (NOW() - INTERVAL '1 hour')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Error limpiando códigos expirados: " . $e->getMessage(), 3, __DIR__ . '/error.log');
        return false;
    }
}

// Manejo de la petición
try {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "JSON inválido"]);
        exit();
    }
    
    $action = isset($data['action']) ? trim($data['action']) : '';

    switch ($action) {
        case 'send-code':
            if (!empty($data['email'])) {
                sendCode(trim($data['email']));
            } else {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Email requerido"]);
            }
            break;
            
        case 'verify-code':
            if (!empty($data['code'])) {
                verifyCode(trim($data['code']));
            } else {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Código requerido"]);
            }
            break;
            
        case 'reset-password':
            if (!empty($data['password']) && !empty($data['code'])) {
                resetPassword($data['password'], trim($data['code']));
            } else {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Contraseña y código requeridos"]);
            }
            break;
            
        case 'clean-expired':
            // Endpoint opcional para limpiar códigos expirados
            $cleaned = cleanExpiredCodes();
            echo json_encode([
                "success" => true, 
                "message" => "Códigos limpiados", 
                "count" => $cleaned
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Acción no válida"]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error general: " . $e->getMessage(), 3, __DIR__ . '/error.log');
    echo json_encode(["success" => false, "error" => "Error interno del servidor"]);
}
?>