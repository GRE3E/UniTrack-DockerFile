<?php
// Cabeceras CORS para TODAS las respuestas
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Manejo de solicitud OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir conexión a la base de datos
include_once 'config.php';

// Incluir PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// Función para enviar correo con PHPMailer
function enviarCorreo($correoDestino, $asunto, $cuerpo)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'mixie.brighit01@gmail.com';
        $mail->Password = 'rnfi ybfp dzou xsgb'; // Asegúrate de usar una contraseña de aplicación, no la de tu cuenta
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('mixie.brighit01@gmail.com', 'Somos X');
        $mail->addAddress($correoDestino);

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body = $cuerpo;

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error for debugging
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}

// Ruta para subir imágenes
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['action']) && $_GET['action'] == 'upload') {
    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $uploadDir = '/app/uploads/'; // Directorio temporal dentro del contenedor
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $uploadedFile = $uploadDir . basename($_FILES['image']['name']);

        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadedFile)) {
            // Llamar al script Python
            $pythonScriptPath = '/app/cripto_seguridad/main.py'; // Ruta al script Python dentro del contenedor
            $command = 'python3 ' . escapeshellarg($pythonScriptPath) . ' ' . escapeshellarg($uploadedFile);
            $output = shell_exec($command);

            // Decodificar la salida JSON del script Python
            $result = json_decode($output, true);

            if ($result) {
                echo json_encode(['success' => true, 'analysis_result' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al procesar la salida del script Python.', 'python_output' => $output]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al mover el archivo subido.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No se subió ninguna imagen o hubo un error en la subida.']);
    }
    exit();
}

// Función para obtener historial
function historial($idUsuario)
{
    global $conn;

    $sql = "SELECT u.idUsuario, r.fecha, r.hora, r.nombre, r.email, r.modo
            FROM reportes r
            JOIN usuario u ON r.user_id = u.idUsuario 
            WHERE u.idUsuario = ?";

    $result = pg_query_params($conn, $sql, array($idUsuario));

    $data = array();
    while ($row = pg_fetch_assoc($result)) {
        $data[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode($data);
}

// Obtener todos los usuarios
function getAllUsers()
{
    global $conn;

    $sql = "SELECT idusuario, nombres, apellidos, correo, codigo_estudiante FROM usuario";
    $result = pg_query($conn, $sql);

    if (pg_num_rows($result) > 0) {
        return json_encode(pg_fetch_all($result));
    } else {
        return json_encode(["error" => "No se encontraron usuarios"]);
    }
}

// Obtener usuario por correo
function CurrentUser($correo)
{
    global $conn;

    $sql = "SELECT idUsuario, nombres, apellidos, correo, codigo_estudiante, correoA, carrera, ciclo, edad, sexo 
            FROM usuario WHERE correo = ?";
    $result = pg_query_params($conn, $sql, array($correo));

    if (pg_num_rows($result) > 0) {
        return json_encode(pg_fetch_assoc($result));
    } else {
        return json_encode(["error" => "Usuario no encontrado"]);
    }
}

// Crear usuario
function createUser($nombres, $apellidos, $correo, $codigo_estudiante, $contrasena, $correoA, $carrera, $ciclo, $edad, $sexo)
{
    global $conn;

    $hashedPassword = password_hash($contrasena, PASSWORD_BCRYPT);

    $sql = "INSERT INTO usuario (nombres, apellidos, correo, codigo_estudiante, contrasena, correoA, carrera, ciclo, edad, sexo)
            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)";
    $result = pg_query_params($conn, $sql, array($nombres, $apellidos, $correo, $codigo_estudiante, $hashedPassword, $correoA, $carrera, $ciclo, $edad, $sexo));

    if ($result) {
        return json_encode(["message" => "Usuario creado correctamente"]);
    } else {
        return json_encode(["error" => "Error al crear usuario"]);
    }
}

// Login usuario
function loginUser($correo, $contrasena)
{
    global $conn;

    $sql = "SELECT idUsuario, nombres, apellidos, correo, codigo_estudiante, contrasena, correoA, carrera, ciclo, edad, sexo
            FROM usuario WHERE correo = $1";
    $result = pg_query_params($conn, $sql, array($correo));

    if (pg_num_rows($result) > 0) {
        $user = pg_fetch_assoc($result);
        if (password_verify($contrasena, $user['contrasena'])) {
            unset($user['contrasena']);
            return json_encode(["success" => true, "user" => $user]);
        } else {
            return json_encode(["error" => "Contraseña incorrecta"]);
        }
    } else {
        return json_encode(["error" => "Usuario no encontrado"]);
    }
}

// Actualizar usuario
function updateUser($id, $nombres, $apellidos, $correo, $codigo_estudiante)
{
    global $conn;

    $sql = "UPDATE usuario SET nombres = $1, apellidos = $2, correo = $3, codigo_estudiante = $4 WHERE idusuario = $5";
    $result = pg_query_params($conn, $sql, array($nombres, $apellidos, $correo, $codigo_estudiante, $id));

    if ($result) {
        return json_encode(["message" => "Usuario actualizado correctamente"]);
    } else {
        return json_encode(["error" => "Error al actualizar usuario"]);
    }
}

// Eliminar usuario
function deleteUser($id)
{
    global $conn;

    $sql = "DELETE FROM usuario WHERE idusuario = $1";
    $result = pg_query_params($conn, $sql, array($id));

    if ($result) {
        return json_encode(["message" => "Usuario eliminado correctamente"]);
    } else {
        return json_encode(["error" => "Error al eliminar usuario"]);
    }
}

// Enviar token de recuperación
function sendToken($correo)
{
    global $conn;

    $token = bin2hex(random_bytes(16)); // genera token de 32 caracteres

    $result = pg_query_params($conn, "UPDATE usuario SET token_recuperacion = $1 WHERE correo = $2", array($token, $correo));

    if (pg_affected_rows($result) > 0) {
        $asunto = "Recuperación de contraseña";
        $cuerpo = "<p>Hola,</p><p>Tu token de recuperación es: <strong>$token</strong></p>";

        if (enviarCorreo($correo, $asunto, $cuerpo)) {
            echo json_encode(["message" => "Token enviado correctamente"]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "No se pudo enviar el correo"]);
        }
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Correo no encontrado"]);
    }
}

// =========================
//    ENRUTADOR PRINCIPAL
// =========================

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($_GET['action'] === 'currentUser' && isset($_GET['correo'])) {
            echo CurrentUser($_GET['correo']);
        } elseif ($_GET['action'] === 'historial' && isset($_GET['idUsuario'])) {
            historial($_GET['idUsuario']);
        } else {
            echo getAllUsers();
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        $action = $data['action'] ?? '';

        if ($action === 'login') {
            echo loginUser($data['correo'], $data['contrasena']);
        } elseif ($action === 'sendVerificationCode') {
            sendToken($data['correo']);
        } else {
            // Registro
            if (
                empty($data['nombres']) || empty($data['apellidos']) || empty($data['correo']) ||
                empty($data['codigo_estudiante']) || empty($data['contrasena']) ||
                empty($data['correoA']) || empty($data['carrera']) ||
                empty($data['ciclo']) || empty($data['edad']) || empty($data['sexo'])
            ) {
                http_response_code(400);
                echo json_encode(['error' => 'Todos los campos son obligatorios']);
                exit();
            }

            if (!preg_match('/@ucvvirtual\.edu\.pe$/', $data['correo'])) {
                http_response_code(400);
                echo json_encode(['error' => 'El correo debe ser de la universidad']);
                exit();
            }

            if (strlen($data['contrasena']) < 6) {
                http_response_code(400);
                echo json_encode(['error' => 'La contraseña debe tener al menos 6 caracteres']);
                exit();
            }

            echo createUser(
                $data['nombres'], $data['apellidos'], $data['correo'],
                $data['codigo_estudiante'], $data['contrasena'], $data['correoA'],
                $data['carrera'], $data['ciclo'], $data['edad'], $data['sexo']
            );
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = json_decode(file_get_contents("php://input"), true);
        echo updateUser($data['id'], $data['nombres'], $data['apellidos'], $data['correo'], $data['codigo_estudiante']);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $data = json_decode(file_get_contents("php://input"), true);
        echo deleteUser($data['id']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
