<?php
// Cambia la URL por el dominio en despliegue
header("Access-Control-Allow-Origin: http://localhost:4200");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configura la cookie de sesión para desarrollo local
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);
// Iniciar sesión en cada petición
session_start();

// Incluir el archivo de configuración de la conexión a la base de datos PostgreSQL
include_once 'config.php';

function reportes()
{
    global $pdo;

    try {
        $sql = "SELECT r.idreporte, r.fecha, r.hora, r.nombre, r.email, r.modo
                FROM reportes r
                JOIN usuario u ON r.user_id::int = u.idusuario 
                WHERE modo = 'entrada'
                ORDER BY r.fecha DESC, r.hora DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($result);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener reportes: ' . $e->getMessage()]);
    }
}

function reportesSalida()
{
    global $pdo;

    try {
        $sql = "SELECT r.idreporte, r.fecha, r.hora, r.nombre, r.email, r.modo
                FROM reportes r
                JOIN usuario u ON r.user_id::int = u.idusuario 
                WHERE modo = 'salida'
                ORDER BY r.fecha DESC, r.hora DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($result);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener reportes de salida: ' . $e->getMessage()]);
    }
}

// Función para obtener todos los usuarios
function getAllUsers()
{
    global $pdo;

    try {
        $sql = "SELECT idusuario, nombres, apellidos, correo, codigo_estudiante, carrera, ciclo, edad, sexo 
                FROM usuario 
                ORDER BY nombres, apellidos";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($result) > 0) {
            return json_encode($result);
        } else {
            return json_encode(['message' => 'No se encontraron usuarios']);
        }
    } catch (PDOException $e) {
        return json_encode(['error' => 'Error al obtener usuarios: ' . $e->getMessage()]);
    }
}

// Función para crear un nuevo admin
function createAdmin($nombres, $apellidos, $correo, $codigo_admin, $contrasena, $edad, $sexo)
{
    global $pdo;

    try {
        // Validar y limpiar los datos de entrada
        $nombres = trim(filter_var($nombres, FILTER_SANITIZE_STRING));
        $apellidos = trim(filter_var($apellidos, FILTER_SANITIZE_STRING));
        $correo = trim(filter_var($correo, FILTER_SANITIZE_EMAIL));
        $codigo_admin = trim(filter_var($codigo_admin, FILTER_SANITIZE_STRING));
        $edad = trim(filter_var($edad, FILTER_SANITIZE_STRING));
        $sexo = trim(filter_var($sexo, FILTER_SANITIZE_STRING));

        // Validaciones
        if (empty($nombres) || empty($apellidos) || empty($correo) || empty($codigo_admin) || empty($contrasena) || empty($edad) || empty($sexo)) {
            http_response_code(400);
            return json_encode(["error" => "Todos los campos son obligatorios"]);
        }

        if (strlen($nombres) > 50 || strlen($apellidos) > 50) {
            http_response_code(400);
            return json_encode(["error" => "Nombre y apellido no deben superar 50 caracteres"]);
        }

        if (strlen($codigo_admin) > 20) {
            http_response_code(400);
            return json_encode(["error" => "El código de administrador no debe superar 20 caracteres"]);
        }

        if (strlen($correo) > 100) {
            http_response_code(400);
            return json_encode(["error" => "El correo no debe superar 100 caracteres"]);
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            return json_encode(["error" => "Formato de correo no válido"]);
        }

        if (strlen($contrasena) < 6 || strlen($contrasena) > 255) {
            http_response_code(400);
            return json_encode(["error" => "La contraseña debe tener entre 6 y 255 caracteres"]);
        }

        // Validar duplicados
        $sqlCheck = "SELECT idadmin FROM administrador WHERE correo = $1 OR codigo_admin = $2";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([$correo, $codigo_admin]);
        
        if ($stmtCheck->rowCount() > 0) {
            http_response_code(409);
            return json_encode(["error" => "El correo o código de administrador ya existe"]);
        }

        // Hash de la contraseña
        $hashedPassword = password_hash($contrasena, PASSWORD_BCRYPT);

        // Insertar nuevo administrador
        $sql = "INSERT INTO administrador (nombres, apellidos, correo, codigo_admin, contrasena, edad, sexo) 
                VALUES ($1, $2, $3, $4, $5, $6, $7)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$nombres, $apellidos, $correo, $codigo_admin, $hashedPassword, $edad, $sexo])) {
            return json_encode(["message" => "Administrador creado correctamente"]);
        } else {
            return json_encode(["error" => "Error al crear administrador"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        return json_encode(["error" => "Error de base de datos: " . $e->getMessage()]);
    } catch (Exception $e) {
        http_response_code(500);
        return json_encode(["error" => "Error interno: " . $e->getMessage()]);
    }
}

function updateUser($id, $nombres, $apellidos, $correo, $codigo_estudiante)
{
    global $pdo;

    try {
        // Validar y limpiar los datos de entrada
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            http_response_code(400);
            return json_encode(["error" => "ID de usuario no válido"]);
        }

        $nombres = trim(filter_var($nombres, FILTER_SANITIZE_STRING));
        $apellidos = trim(filter_var($apellidos, FILTER_SANITIZE_STRING));
        $correo = trim(filter_var($correo, FILTER_SANITIZE_EMAIL));
        $codigo_estudiante = trim(filter_var($codigo_estudiante, FILTER_SANITIZE_STRING));

        // Validaciones
        if (empty($nombres) || empty($apellidos) || empty($correo) || empty($codigo_estudiante)) {
            http_response_code(400);
            return json_encode(["error" => "Todos los campos son obligatorios"]);
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            return json_encode(["error" => "Formato de correo no válido"]);
        }

        // Verificar si el usuario existe
        $sqlExists = "SELECT idusuario FROM usuario WHERE idusuario = $1";
        $stmtExists = $pdo->prepare($sqlExists);
        $stmtExists->execute([$id]);
        
        if ($stmtExists->rowCount() === 0) {
            http_response_code(404);
            return json_encode(["error" => "Usuario no encontrado"]);
        }

        // Verificar duplicados (excluyendo el usuario actual)
        $sqlCheck = "SELECT idusuario FROM usuario WHERE (correo = $1 OR codigo_estudiante = $2) AND idusuario != $3";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([$correo, $codigo_estudiante, $id]);
        
        if ($stmtCheck->rowCount() > 0) {
            http_response_code(409);
            return json_encode(["error" => "El correo o código de estudiante ya existe"]);
        }

        // Actualizar usuario
        $sql = "UPDATE usuario SET nombres = $1, apellidos = $2, correo = $3, codigo_estudiante = $4 WHERE idusuario = $5";
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute([$nombres, $apellidos, $correo, $codigo_estudiante, $id])) {
            return json_encode(["message" => "Usuario actualizado correctamente"]);
        } else {
            return json_encode(["error" => "Error al actualizar usuario"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        return json_encode(["error" => "Error de base de datos: " . $e->getMessage()]);
    } catch (Exception $e) {
        http_response_code(500);
        return json_encode(["error" => "Error interno: " . $e->getMessage()]);
    }
}

// Función para eliminar un usuario por ID
function deleteUser($id)
{
    global $pdo;

    try {
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            http_response_code(400);
            return json_encode(["error" => "ID de usuario no válido"]);
        }

        // Verificar si el usuario existe
        $sqlExists = "SELECT idusuario FROM usuario WHERE idusuario = $1";
        $stmtExists = $pdo->prepare($sqlExists);
        $stmtExists->execute([$id]);
        
        if ($stmtExists->rowCount() === 0) {
            http_response_code(404);
            return json_encode(["error" => "Usuario no encontrado"]);
        }

        // Iniciar transacción para eliminar datos relacionados
        $pdo->beginTransaction();

        try {
            // Eliminar reportes relacionados
            $sqlReportes = "DELETE FROM reportes WHERE user_id = $1";
            $stmtReportes = $pdo->prepare($sqlReportes);
            $stmtReportes->execute([$id]);

            // Eliminar alertas relacionadas
            $sqlAlertas = "DELETE FROM alertas WHERE user_id = $1";
            $stmtAlertas = $pdo->prepare($sqlAlertas);
            $stmtAlertas->execute([$id]);

            // Eliminar códigos de verificación relacionados
            $sqlVerificacion = "DELETE FROM verificacion_codigo WHERE id_usuario = $1";
            $stmtVerificacion = $pdo->prepare($sqlVerificacion);
            $stmtVerificacion->execute([$id]);

            // Eliminar usuario
            $sql = "DELETE FROM usuario WHERE idusuario = $1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            $pdo->commit();
            return json_encode(["message" => "Usuario y datos relacionados eliminados correctamente"]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        return json_encode(["error" => "Error de base de datos: " . $e->getMessage()]);
    } catch (Exception $e) {
        http_response_code(500);
        return json_encode(["error" => "Error interno: " . $e->getMessage()]);
    }
}

// Función para obtener un usuario por ID
function getUserById($id)
{
    global $pdo;

    try {
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            http_response_code(400);
            return json_encode(["error" => "ID de usuario no válido"]);
        }

        $sql = "SELECT idusuario, nombres, apellidos, correo, codigo_estudiante, carrera, ciclo, edad, sexo 
                FROM usuario WHERE idusuario = $1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return json_encode($result);
        } else {
            http_response_code(404);
            return json_encode(["error" => "Usuario no encontrado"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        return json_encode(["error" => "Error de base de datos: " . $e->getMessage()]);
    } catch (Exception $e) {
        http_response_code(500);
        return json_encode(["error" => "Error interno: " . $e->getMessage()]);
    }
}

// Función para verificar usuario y contraseña admin
function loginUser($correo, $contrasena)
{
    global $pdo;

    try {
        if (empty($correo) || empty($contrasena)) {
            http_response_code(400);
            return json_encode(["error" => "Correo y contraseña son obligatorios"]);
        }

        $sql = "SELECT idadmin, nombres, apellidos, correo, codigo_admin, contrasena, edad, sexo 
                FROM administrador WHERE correo = $1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$correo]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if (password_verify($contrasena, $user['contrasena'])) {
                $_SESSION['idAdmin'] = $user['idadmin'];
                unset($user['contrasena']);
                return json_encode($user);
            } else {
                http_response_code(401);
                return json_encode(["error" => "Contraseña incorrecta"]);
            }
        } else {
            http_response_code(404);
            return json_encode(["error" => "Usuario no encontrado"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        return json_encode(["error" => "Error de base de datos: " . $e->getMessage()]);
    } catch (Exception $e) {
        http_response_code(500);
        return json_encode(["error" => "Error interno: " . $e->getMessage()]);
    }
}

// Endpoint para cerrar sesión
function logoutUser()
{
    session_unset();
    session_destroy();
    echo json_encode(['message' => 'Sesión cerrada correctamente']);
    exit();
}

// Verificar si la solicitud es un método GET
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        // --- VERIFICACIÓN DE SESIÓN PARA EL AUTHGUARD ---
        if (isset($_GET['checkSession'])) {
            if (isset($_SESSION['idAdmin'])) {
                echo json_encode(['active' => true]);
            } else {
                http_response_code(401);
                echo json_encode(['error' => 'Sesión expirada']);
            }
            exit();
        }

        // PROTECCIÓN: Solo permite acceso si hay sesión, excepto para endpoints públicos
        if (
            !(
                isset($_GET['action']) && ($_GET['action'] === 'login' || $_GET['action'] === 'registro')
            )
        ) {
            if (!isset($_SESSION['idAdmin'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Sesión expirada']);
                exit();
            }
        }

        if (isset($_GET['id'])) {
            echo getUserById($_GET['id']);
        } elseif (isset($_GET['action']) && $_GET['action'] === 'reportes') {
            reportes();
        } elseif (isset($_GET['action']) && $_GET['action'] === 'salidas') {
            reportesSalida();
        } else {
            echo getAllUsers();
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents("php://input");
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'JSON no válido']);
            exit();
        }

        // LOGIN (público)
        if (isset($data['action']) && $data['action'] === 'login') {
            echo loginUser($data['correo'] ?? '', $data['contrasena'] ?? '');
            exit();
        }

        // LOGOUT (público)
        if (isset($data['action']) && $data['action'] === 'logout') {
            logoutUser();
            exit();
        }

        // REGISTRO ADMIN (público)
        if (
            isset($data['nombres']) &&
            isset($data['apellidos']) &&
            isset($data['correo']) &&
            isset($data['codigo_admin']) &&
            isset($data['contrasena']) &&
            isset($data['edad']) &&
            isset($data['sexo'])
        ) {
            // Validación del dominio de correo
            if (!preg_match('/@ucvvirtual\.edu\.pe$/', $data['correo'])) {
                http_response_code(400);
                echo json_encode(['error' => 'El correo debe ser de la universidad (@ucvvirtual.edu.pe)']);
                exit();
            }

            echo createAdmin(
                $data['nombres'],
                $data['apellidos'],
                $data['correo'],
                $data['codigo_admin'],
                $data['contrasena'],
                $data['edad'],
                $data['sexo']
            );
            exit();
        }

        // PROTECCIÓN: Todo lo demás requiere sesión
        if (!isset($_SESSION['idAdmin'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Sesión expirada']);
            exit();
        }

        // Aquí van las acciones protegidas por POST (crear usuarios, etc.)
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // PROTECCIÓN: Solo permite acceso si hay sesión
        if (!isset($_SESSION['idAdmin'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Sesión expirada']);
            exit();
        }

        $input = file_get_contents("php://input");
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'JSON no válido']);
            exit();
        }

        if (isset($data['id'])) {
            echo updateUser(
                $data['id'],
                $data['nombres'] ?? '',
                $data['apellidos'] ?? '',
                $data['correo'] ?? '',
                $data['codigo_estudiante'] ?? ''
            );
        } else {
            http_response_code(400);
            echo json_encode(["error" => "ID de usuario no especificado para actualizar"]);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // PROTECCIÓN: Solo permite acceso si hay sesión
        if (!isset($_SESSION['idAdmin'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Sesión expirada']);
            exit();
        }

        $input = file_get_contents("php://input");
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'JSON no válido']);
            exit();
        }

        if (isset($data['id'])) {
            echo deleteUser($data['id']);
        } else {
            http_response_code(400);
            echo json_encode(["error" => "ID de usuario no especificado para eliminar"]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error interno del servidor: " . $e->getMessage()]);
}

// No cerrar la conexión PDO explícitamente - se cierra automáticamente
?>