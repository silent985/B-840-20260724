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

    // 明确类型契约：PDO 默认将整数列返回为字符串，这里将 id 转为整数，
    // 保证前端拿到的 word.id 始终是数字类型。
    $words = array_map(function ($word) {
        $word['id'] = (int)$word['id'];
        return $word;
    }, $words);

    echo json_encode($words);
}
