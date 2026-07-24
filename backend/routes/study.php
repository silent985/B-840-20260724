<?php
require_once __DIR__ . '/../index.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = (int)($_GET['limit'] ?? 10);
    if ($limit < 1) $limit = 10;
    if ($limit > 100) $limit = 100;

    $stmt = $pdo->query("SELECT id, word, definition, example FROM words WHERE mastered = 0 ORDER BY RAND() LIMIT $limit");
    $words = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($words)) {
        $stmt = $pdo->query("SELECT id, word, definition, example FROM words ORDER BY RAND() LIMIT $limit");
        $words = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode($words);
}
