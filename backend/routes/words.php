<?php
require_once __DIR__ . '/../index.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("SELECT id, word, definition, example, mastered FROM words ORDER BY id DESC");
    $words = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($words);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['word']) || empty($data['definition'])) {
        http_response_code(400);
        echo json_encode(["error" => "Word and definition are required"]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO words (word, definition, example) VALUES (?, ?, ?)");
    $stmt->execute([
        trim($data['word']),
        trim($data['definition']),
        $data['example'] ?? ''
    ]);

    http_response_code(201);
    echo json_encode([
        "id" => $pdo->lastInsertId(),
        "word" => trim($data['word']),
        "definition" => trim($data['definition']),
        "example" => $data['example'] ?? '',
        "mastered" => 0
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = $pathParts[1] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Word ID is required"]);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM words WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(["success" => true]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT' && isset($pathParts[1])) {
    $id = $pathParts[1];
    $data = json_decode(file_get_contents('php://input'), true);

    $stmt = $pdo->prepare("UPDATE words SET mastered = ? WHERE id = ?");
    $stmt->execute([$data['mastered'] ?? 0, $id]);

    echo json_encode(["success" => true]);
}
