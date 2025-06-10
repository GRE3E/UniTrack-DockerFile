<?php
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

// Usar las variables de entorno para la conexión
$host = $_ENV['DB_HOST'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];
$dbname = $_ENV['DB_NAME'];
$port = isset($_ENV['DB_PORT']) ? $_ENV['DB_PORT'] : 5432; // usa 5432 por defecto para PostgreSQL

$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$pass");

if (!$conn) {
  header('Content-Type: application/json');
  http_response_code(500);
  echo json_encode([
    "success" => false,
    "error" => "Error de conexión a la base de datos"
    // "details" => $conn->connect_error // Solo para desarrollo, nunca en producción
  ]);
  exit();
}

