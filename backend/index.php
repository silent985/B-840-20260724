<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

$host = getenv('DB_HOST') ?: 'db';
$dbname = getenv('DB_NAME') ?: 'labelease';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: 'rootpassword';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = array_filter(explode('/', trim($path, '/')));
$pathParts = array_values($pathParts);
$endpoint = $pathParts[0] ?? '';

switch ($endpoint) {
    case 'words':
        require_once __DIR__ . '/routes/words.php';
        break;

    case 'progress':
        require_once __DIR__ . '/routes/progress.php';
        break;

    case 'study':
        require_once __DIR__ . '/routes/study.php';
        break;

    case 'wrong-words':
        require_once __DIR__ . '/routes/wrong-words.php';
        break;

    default:
        http_response_code(404);
        echo json_encode(["error" => "Endpoint not found"]);
        break;
}
