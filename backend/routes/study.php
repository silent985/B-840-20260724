<?php
require_once __DIR__ . '/../index.php';

$method = $_SERVER['REQUEST_METHOD'];
$subResource = $pathParts[1] ?? null;
$pathLength = count($pathParts);

function validateLimit($raw) {
    if (is_array($raw) || is_bool($raw) || is_object($raw)) {
        return null;
    }
    if (is_int($raw)) return $raw;
    if (is_string($raw) && ctype_digit($raw)) return (int)$raw;
    if (is_numeric($raw) && (float)$raw == (int)$raw && (int)$raw > 0) return (int)$raw;
    return null;
}

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

if ($subResource === null) {
    if ($method !== 'GET') wrongMethod('GET');

    $limit = validateLimit($_GET['limit'] ?? 10);
    if ($limit === null) reject(400, "limit must be a positive integer");
    if ($limit < 1) $limit = 10;
    if ($limit > 100) $limit = 100;

    try {
        $stmt = $pdo->query("SELECT id, word, definition, example FROM words WHERE mastered = 0 ORDER BY RAND() LIMIT $limit");
        $words = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($words)) {
            $stmt = $pdo->query("SELECT id, word, definition, example FROM words ORDER BY RAND() LIMIT $limit");
            $words = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode($words);
    } catch (PDOException $e) {
        reject(500, "Failed to fetch study words");
    }
} elseif ($subResource === 'wrong-words') {
    if ($pathLength > 2) reject(404, "Endpoint not found");
    if ($method !== 'GET') wrongMethod('GET');

    $limit = validateLimit($_GET['limit'] ?? 10);
    if ($limit === null) reject(400, "limit must be a positive integer");
    if ($limit < 1) $limit = 10;
    if ($limit > 100) $limit = 100;

    try {
        $stmt = $pdo->query(
            "SELECT w.id, w.word, w.definition, w.example
             FROM words w
             INNER JOIN wrong_words ww ON ww.word_id = w.id
             ORDER BY RAND()
             LIMIT $limit"
        );
        $words = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($words);
    } catch (PDOException $e) {
        reject(500, "Failed to fetch wrong words for review");
    }
} elseif ($subResource === 'records') {
    if ($pathLength > 2) reject(404, "Endpoint not found");
    if ($method !== 'POST') wrongMethod('POST');

    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);

    if (!is_array($data)) {
        reject(400, "Request body must be a JSON object");
    }

    if (!array_key_exists('word_id', $data)) {
        reject(400, "word_id is required");
    }
    $wordIdRaw = $data['word_id'];
    if (is_bool($wordIdRaw) || is_array($wordIdRaw) || is_object($wordIdRaw) || $wordIdRaw === null) {
        reject(400, "word_id must be a positive integer");
    }
    $wordId = filter_var($wordIdRaw, FILTER_VALIDATE_INT);
    if ($wordId === false || $wordId <= 0) {
        reject(400, "word_id must be a positive integer");
    }

    if (!array_key_exists('is_correct', $data)) {
        reject(400, "is_correct is required");
    }
    $isCorrectRaw = $data['is_correct'];
    if ($isCorrectRaw === 0 || $isCorrectRaw === '0' || $isCorrectRaw === false) {
        $isCorrect = 0;
    } elseif ($isCorrectRaw === 1 || $isCorrectRaw === '1' || $isCorrectRaw === true) {
        $isCorrect = 1;
    } else {
        reject(400, "is_correct must be 0 or 1");
    }

    if (!array_key_exists('request_id', $data)) {
        reject(400, "request_id is required");
    }
    $requestId = $data['request_id'];
    if (!is_string($requestId)) {
        reject(400, "request_id must be a string");
    }
    $requestId = trim($requestId);
    if ($requestId === '') {
        reject(400, "request_id must not be empty");
    }
    if (strlen($requestId) > 64) {
        reject(400, "request_id must not exceed 64 characters");
    }

    if (!array_key_exists('word_snapshot', $data)) {
        reject(400, "word_snapshot is required");
    }
    $wordSnapshot = $data['word_snapshot'];
    if (!is_string($wordSnapshot)) {
        reject(400, "word_snapshot must be a string");
    }
    $wordSnapshot = trim($wordSnapshot);
    if ($wordSnapshot === '') {
        reject(400, "word_snapshot must not be empty");
    }
    if (mb_strlen($wordSnapshot) > 255) {
        reject(400, "word_snapshot must not exceed 255 characters");
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id FROM words WHERE id = ?");
        $stmt->execute([$wordId]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            reject(404, "Word not found");
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO study_records (request_id, word_id, word_snapshot, is_correct)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$requestId, $wordId, $wordSnapshot, $isCorrect]);
            $recordId = (int)$pdo->lastInsertId();
        } catch (PDOException $insertErr) {
            $sqlState = $insertErr->errorInfo[0] ?? '';
            $driverCode = $insertErr->errorInfo[1] ?? 0;

            if ($sqlState === '23000' && $driverCode == 1062) {
                $stmt = $pdo->prepare(
                    "SELECT id, word_id, word_snapshot, is_correct, studied_at
                     FROM study_records WHERE request_id = ?"
                );
                $stmt->execute([$requestId]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                $pdo->rollBack();

                if ($existing) {
                    http_response_code(200);
                    echo json_encode([
                        "id" => (int)$existing['id'],
                        "word_id" => (int)$existing['word_id'],
                        "word_snapshot" => $existing['word_snapshot'],
                        "is_correct" => (int)$existing['is_correct'],
                        "studied_at" => $existing['studied_at'],
                        "idempotent" => true
                    ]);
                    exit;
                }
            }

            throw $insertErr;
        }

        if ($isCorrect === 0) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO wrong_words (word_id) VALUES (?)");
            $stmt->execute([$wordId]);

            if ($stmt->rowCount() === 0) {
                $stmt = $pdo->prepare(
                    "UPDATE wrong_words
                     SET review_count = review_count + 1, last_reviewed_at = NOW()
                     WHERE word_id = ?"
                );
                $stmt->execute([$wordId]);
            }
        } else {
            $stmt = $pdo->prepare(
                "UPDATE wrong_words
                 SET review_count = review_count + 1, last_reviewed_at = NOW()
                 WHERE word_id = ?"
            );
            $stmt->execute([$wordId]);
        }

        $pdo->commit();

        http_response_code(201);
        echo json_encode([
            "id" => $recordId,
            "word_id" => $wordId,
            "word_snapshot" => $wordSnapshot,
            "is_correct" => $isCorrect,
            "studied_at" => date('Y-m-d H:i:s'),
            "idempotent" => false
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        reject(500, "Failed to save study record");
    }
} else {
    reject(404, "Endpoint not found");
}
