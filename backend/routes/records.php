<?php
require_once __DIR__ . '/../index.php';

// 仅支持 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError("Method not allowed", 405);
}

// POST /api/records —— 记录一次答题（单词、答题结果和学习时间）
$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    jsonError("Invalid JSON body", 400);
}
if (!isset($data['word_id']) || !isset($data['is_correct']) || !isset($data['client_token'])) {
    jsonError("word_id, is_correct and client_token are required", 400);
}

// word_id：必须为正整数
$wordId = filter_var($data['word_id'], FILTER_VALIDATE_INT);
if ($wordId === false || $wordId < 1) {
    jsonError("word_id must be a positive integer", 400);
}

// is_correct：严格只接受布尔或整数 0/1，杜绝 "yes"/"on" 等歧义值
$rawCorrect = $data['is_correct'];
if (is_bool($rawCorrect)) {
    $isCorrect = $rawCorrect ? 1 : 0;
} elseif (is_int($rawCorrect) && ($rawCorrect === 0 || $rawCorrect === 1)) {
    $isCorrect = $rawCorrect;
} else {
    jsonError("is_correct must be a boolean or 0/1", 400);
}

// client_token：必填，非空字符串且不超过 64 字符
if (!is_string($data['client_token'])) {
    jsonError("client_token must be a string", 400);
}
$clientToken = trim($data['client_token']);
if ($clientToken === '' || strlen($clientToken) > 64) {
    jsonError("client_token must be a non-empty string up to 64 chars", 400);
}

/**
 * 根据已存在的记录与本次请求负载进行核对：
 *   - 负载一致（word_id 与 is_correct 相同）：幂等重放，返回既有结果（200）
 *   - 负载不同：令牌被复用于不同答案，返回 409 并附带服务端已存结果
 * 该函数会输出响应并终止请求。
 */
function reconcileToken(array $existing, int $reqWordId, int $reqCorrect): void {
    $storedWordId = $existing['word_id'] !== null ? (int)$existing['word_id'] : null;
    $storedCorrect = (int)$existing['is_correct'];

    $result = [
        "id" => (int)$existing['id'],
        "word_id" => $storedWordId,
        "is_correct" => $storedCorrect,
        "added_to_wrong_words" => $storedCorrect === 0
    ];

    if ($storedWordId === $reqWordId && $storedCorrect === $reqCorrect) {
        $result["replayed"] = true;
        echo json_encode($result);
        exit;
    }

    // 同一令牌携带不同负载：拒绝并回传权威结果，供前端对齐
    http_response_code(409);
    echo json_encode([
        "error" => "client_token already used with a different payload",
        "stored" => $result
    ]);
    exit;
}

try {
    // 幂等：令牌已存在则按负载核对（重放或 409）
    $stmt = $pdo->prepare("SELECT id, word_id, is_correct FROM study_records WHERE client_token = ?");
    $stmt->execute([$clientToken]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        reconcileToken($existing, $wordId, $isCorrect);
    }

    // 校验单词存在并取出文本用于快照
    $stmt = $pdo->prepare("SELECT word FROM words WHERE id = ?");
    $stmt->execute([$wordId]);
    $word = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$word) {
        jsonError("Word not found", 404);
    }
    $wordSnapshot = $word['word'];

    // 原子事务：保存答题记录 -> 更新掌握状态 -> 维护错词集
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO study_records (word_id, word_snapshot, is_correct, client_token)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$wordId, $wordSnapshot, $isCorrect, $clientToken]);
    // 插入后立即保存 ID，确保后续操作不覆盖返回值
    $recordId = (int)$pdo->lastInsertId();

    if ($isCorrect === 1) {
        // 答对：标记为已掌握
        $stmt = $pdo->prepare("UPDATE words SET mastered = 1 WHERE id = ?");
        $stmt->execute([$wordId]);
    } else {
        // 答错：自动进入错词集，已存在则累计次数并刷新时间
        $stmt = $pdo->prepare(
            "INSERT INTO wrong_words (word_id, wrong_count, last_wrong_at)
             VALUES (?, 1, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE wrong_count = wrong_count + 1, last_wrong_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$wordId]);
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // 并发下同一令牌可能同时插入，命中唯一键冲突时按负载核对
    if ($e->getCode() === '23000') {
        $stmt = $pdo->prepare("SELECT id, word_id, is_correct FROM study_records WHERE client_token = ?");
        $stmt->execute([$clientToken]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            reconcileToken($existing, $wordId, $isCorrect);
        }
    }

    jsonError("Failed to save study record", 500);
}

http_response_code(201);
echo json_encode([
    "id" => $recordId,
    "word_id" => $wordId,
    "is_correct" => $isCorrect,
    "added_to_wrong_words" => $isCorrect === 0,
    "replayed" => false
]);
