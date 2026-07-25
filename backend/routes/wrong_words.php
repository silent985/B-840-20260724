<?php
require_once __DIR__ . '/../index.php';

// GET /api/wrong-words —— 错词集列表（联表带出单词详情，供筛选与复习）
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query(
            "SELECT ww.word_id, w.word, w.definition, w.example,
                    ww.wrong_count, ww.last_wrong_at
             FROM wrong_words ww
             JOIN words w ON w.id = ww.word_id
             ORDER BY ww.last_wrong_at DESC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        jsonError("Failed to load wrong words", 500);
    }

    $wrongWords = array_map(function ($row) {
        return [
            "word_id" => (int)$row['word_id'],
            "word" => $row['word'],
            "definition" => $row['definition'],
            "example" => $row['example'] ?? '',
            "wrong_count" => (int)$row['wrong_count'],
            "last_wrong_at" => $row['last_wrong_at']
        ];
    }, $rows);

    echo json_encode($wrongWords);
    exit;
}

// DELETE /api/wrong-words/{word_id} —— 从错词集移除某个单词
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $rawId = $pathParts[1] ?? null;

    if ($rawId === null) {
        jsonError("word_id is required", 400);
    }

    $wordId = filter_var($rawId, FILTER_VALIDATE_INT);
    if ($wordId === false || $wordId < 1) {
        jsonError("word_id must be a positive integer", 400);
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM wrong_words WHERE word_id = ?");
        $stmt->execute([$wordId]);
    } catch (PDOException $e) {
        jsonError("Failed to remove wrong word", 500);
    }

    // 重复删除视为幂等成功：无论此前是否存在，最终状态都是「已不在错词集」。
    // removed 标识本次是否真正删除了一行，供前端参考。
    echo json_encode([
        "success" => true,
        "word_id" => $wordId,
        "removed" => $stmt->rowCount() > 0
    ]);
    exit;
}

jsonError("Method not allowed", 405);
