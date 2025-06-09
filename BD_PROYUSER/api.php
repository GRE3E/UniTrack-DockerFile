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
        return false;
    }
}

// Función para obtener historial
function historial($idUsuario)
{
    global $conn;

    $sql = "SELECT u.idUsuario, r.fecha, r.hora, r.nombre, r.email, r.modo
            FROM reportes r
            JOIN usuario u ON r.user_id = u.idUsuario 
            WHERE u.idUsuario = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = array();
    while ($row = $result->fetch_assoc()) {
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
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        return json_encode($result->fetch_all(MYSQLI_ASSOC));
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
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return json_encode($result->fetch_assoc());
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
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssss", $nombres, $apellidos, $correo, $codigo_estudiante, $hashedPassword, $correoA, $carrera, $ciclo, $edad, $sexo);

    if ($stmt->execute()) {
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
            FROM usuario WHERE correo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
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

    $sql = "UPDATE usuario SET nombres = ?, apellidos = ?, correo = ?, codigo_estudiante = ? WHERE idusuario = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $nombres, $apellidos, $correo, $codigo_estudiante, $id);

    if ($stmt->execute()) {
        return json_encode(["message" => "Usuario actualizado correctamente"]);
    } else {
        return json_encode(["error" => "Error al actualizar usuario"]);
    }
}

// Eliminar usuario
function deleteUser($id)
{
    global $conn;

    $sql = "DELETE FROM usuario WHERE idusuario = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
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

    $stmt = $conn->prepare("UPDATE usuario SET token_recuperacion = ? WHERE correo = ?");
    $stmt->bind_param("ss", $token, $correo);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
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
