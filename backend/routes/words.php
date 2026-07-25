<?php
require_once __DIR__ . '/../index.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = $pathParts[1] ?? null;

function reject($code, $message) {
    http_response_code($code);
    echo json_encode(["error" => $message]);
    exit;
}

function wrongMethod($allow) {
    http_response_code(405);
    header("Allow: $allow");
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

function validateStringField($data, $key, $required = true) {
    if (!array_key_exists($key, $data)) {
        if ($required) reject(400, "$key is required");
        return '';
    }
    $val = $data[$key];
    if (!is_string($val)) {
        reject(400, "$key must be a string");
    }
    return trim($val);
}

$allowedMethods = ['GET', 'POST', 'PUT', 'DELETE'];
if (!in_array($method, $allowedMethods, true)) wrongMethod('GET, POST, PUT, DELETE');

if ($method === 'GET') {
    if ($id !== null) reject(404, "Endpoint not found");

    try {
        $stmt = $pdo->query("SELECT id, word, definition, example, mastered FROM words ORDER BY id DESC");
        $words = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($words);
    } catch (PDOException $e) {
        reject(500, "Failed to fetch words");
    }
}

if ($method === 'POST') {
    if ($id !== null) reject(404, "Endpoint not found");

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) reject(400, "Request body must be a JSON object");

    $word = validateStringField($data, 'word');
    $definition = validateStringField($data, 'definition');
    $example = validateStringField($data, 'example', false);

    if ($word === '' || $definition === '') {
        reject(400, "Word and definition are required");
    }

    if (mb_strlen($word) > 255) {
        reject(400, "Word must not exceed 255 characters");
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO words (word, definition, example) VALUES (?, ?, ?)");
        $stmt->execute([$word, $definition, $example]);

        http_response_code(201);
        echo json_encode([
            "id" => (int)$pdo->lastInsertId(),
            "word" => $word,
            "definition" => $definition,
            "example" => $example,
            "mastered" => 0
        ]);
    } catch (PDOException $e) {
        reject(500, "Failed to add word");
    }
}

if ($method === 'DELETE') {
    if (!$id || !ctype_digit((string)$id) || (int)$id <= 0) {
        reject(400, "Word ID is required and must be a positive integer");
    }
    if (count($pathParts) > 2) reject(404, "Endpoint not found");

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id FROM words WHERE id = ?");
        $stmt->execute([(int)$id]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            reject(404, "Word not found");
        }

        $stmt = $pdo->prepare("DELETE FROM words WHERE id = ?");
        $stmt->execute([(int)$id]);

        $pdo->commit();
        echo json_encode(["success" => true]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        reject(500, "Failed to delete word");
    }
}

if ($method === 'PUT') {
    if (!$id || !ctype_digit((string)$id) || (int)$id <= 0) {
        reject(400, "Word ID is required and must be a positive integer");
    }
    if (count($pathParts) > 2) reject(404, "Endpoint not found");

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) reject(400, "Request body must be a JSON object");

    if (!array_key_exists('mastered', $data)) {
        reject(400, "mastered is required");
    }
    $masteredRaw = $data['mastered'];
    if ($masteredRaw === 0 || $masteredRaw === '0' || $masteredRaw === false) {
        $mastered = 0;
    } elseif ($masteredRaw === 1 || $masteredRaw === '1' || $masteredRaw === true) {
        $mastered = 1;
    } else {
        reject(400, "mastered must be 0 or 1");
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id FROM words WHERE id = ?");
        $stmt->execute([(int)$id]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            reject(404, "Word not found");
        }

        $stmt = $pdo->prepare("UPDATE words SET mastered = ? WHERE id = ?");
        $stmt->execute([$mastered, (int)$id]);

        $pdo->commit();
        echo json_encode(["success" => true]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        reject(500, "Failed to update word");
    }
}
