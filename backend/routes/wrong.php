<?php
require_once __DIR__ . '/../index.php';

function validateInteger($value, string $fieldName, int $min = 1, ?int $max = null): int {
    if (!is_numeric($value)) {
        http_response_code(400);
        echo json_encode(["error" => "$fieldName must be a valid integer"]);
        exit;
    }
    $intValue = (int)$value;
    if ($intValue < $min) {
        http_response_code(400);
        echo json_encode(["error" => "$fieldName must be at least $min"]);
        exit;
    }
    if ($max !== null && $intValue > $max) {
        http_response_code(400);
        echo json_encode(["error" => "$fieldName must be at most $max"]);
        exit;
    }
    return $intValue;
}

function validateBoolean($value, string $fieldName): bool {
    if (!is_bool($value) && !in_array($value, [0, 1, '0', '1'], true)) {
        http_response_code(400);
        echo json_encode(["error" => "$fieldName must be a boolean"]);
        exit;
    }
    return (bool)$value;
}

function validateString($value, string $fieldName, int $maxLength = 255, bool $allowEmpty = false): string {
    if (!is_string($value)) {
        http_response_code(400);
        echo json_encode(["error" => "$fieldName must be a string"]);
        exit;
    }
    $trimmed = trim($value);
    if (!$allowEmpty && $trimmed === '') {
        http_response_code(400);
        echo json_encode(["error" => "$fieldName cannot be empty"]);
        exit;
    }
    if (strlen($trimmed) > $maxLength) {
        http_response_code(400);
        echo json_encode(["error" => "$fieldName must be at most $maxLength characters"]);
        exit;
    }
    return $trimmed;
}

function validateFilter($filter): string {
    $allowedFilters = ['all', 'frequent', 'recent'];
    if (!in_array($filter, $allowedFilters, true)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid filter value. Allowed: " . implode(', ', $allowedFilters)]);
        exit;
    }
    return $filter;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $filter = validateFilter($_GET['filter'] ?? 'all');
    $search = validateString($_GET['search'] ?? '', 'search', 255, true);
    $limit = validateInteger($_GET['limit'] ?? 50, 'limit', 1, 200);

    $sql = "SELECT ww.id, ww.word_id, ww.word, ww.definition, ww.example, ww.wrong_count, ww.last_wrong_time, w.mastered
            FROM wrong_words ww
            LEFT JOIN words w ON ww.word_id = w.id
            WHERE 1=1";
    $params = [];

    if ($filter === 'frequent') {
        $sql .= " AND ww.wrong_count >= 3";
    } elseif ($filter === 'recent') {
        $sql .= " AND ww.last_wrong_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    }

    if ($search !== '') {
        $sql .= " AND (ww.word LIKE ? OR ww.definition LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql .= " ORDER BY ww.last_wrong_time DESC LIMIT $limit";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $wrongWords = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM wrong_words");
    $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo json_encode([
        "words" => $wrongWords,
        "total" => $total
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = $pathParts[1] ?? null;

    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode(["error" => "Valid wrong word ID is required"]);
        exit;
    }

    $id = (int)$id;

    $stmt = $pdo->prepare("DELETE FROM wrong_words WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(["error" => "Wrong word not found"]);
        exit;
    }

    echo json_encode(["success" => true]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pathParts[1]) && $pathParts[1] === 'review') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON"]);
        exit;
    }

    $wordId = validateInteger($data['word_id'] ?? 0, 'word_id', 1);
    $isCorrect = validateBoolean($data['is_correct'] ?? null, 'is_correct');
    $requestId = validateString($data['request_id'] ?? '', 'request_id', 64);

    $idempotentStmt = $pdo->prepare("SELECT word_id, is_correct, review_removed FROM study_records WHERE request_id = ?");
    $idempotentStmt->execute([$requestId]);
    $existingResult = $idempotentStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingResult) {
        $existingIsCorrect = (bool)$existingResult['is_correct'];
        if ($existingResult['word_id'] != $wordId || $existingIsCorrect !== $isCorrect) {
            http_response_code(409);
            echo json_encode([
                "error" => "Conflict: request_id was already used with different parameters",
                "existing_word_id" => (int)$existingResult['word_id'],
                "existing_is_correct" => $existingIsCorrect
            ]);
            exit;
        }

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "idempotent" => true,
            "removed" => (bool)$existingResult['review_removed'],
            "word_id" => $wordId,
            "is_correct" => $isCorrect
        ]);
        exit;
    }

    $studyTime = date('Y-m-d H:i:s');
    $removed = false;

    try {
        $pdo->beginTransaction();

        $getWordStmt = $pdo->prepare("SELECT word, definition, example FROM words WHERE id = ? FOR UPDATE");
        $getWordStmt->execute([$wordId]);
        $wordData = $getWordStmt->fetch(PDO::FETCH_ASSOC);

        if (!$wordData) {
            http_response_code(404);
            echo json_encode(["error" => "Word not found"]);
            $pdo->rollBack();
            exit;
        }

        $checkStmt = $pdo->prepare("SELECT wrong_count FROM wrong_words WHERE word_id = ? FOR UPDATE");
        $checkStmt->execute([$wordId]);
        $wrongWord = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($isCorrect) {
            if ($wrongWord) {
                if ($wrongWord['wrong_count'] <= 1) {
                    $deleteStmt = $pdo->prepare("DELETE FROM wrong_words WHERE word_id = ?");
                    $deleteStmt->execute([$wordId]);
                    $removed = true;
                } else {
                    $updateStmt = $pdo->prepare("UPDATE wrong_words SET wrong_count = wrong_count - 1 WHERE word_id = ?");
                    $updateStmt->execute([$wordId]);
                }
            }
        } else {
            if ($wrongWord) {
                $updateStmt = $pdo->prepare("UPDATE wrong_words SET wrong_count = wrong_count + 1, last_wrong_time = ? WHERE word_id = ?");
                $updateStmt->execute([$studyTime, $wordId]);
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO wrong_words (word_id, word, definition, example, wrong_count, last_wrong_time) VALUES (?, ?, ?, ?, 1, ?)");
                $insertStmt->execute([$wordId, $wordData['word'], $wordData['definition'], $wordData['example'], $studyTime]);
            }
        }

        $recordStmt = $pdo->prepare("INSERT INTO study_records (session_id, word_id, word, is_correct, study_time, request_id, review_removed) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $recordStmt->execute(['review_' . $requestId, $wordId, $wordData['word'], $isCorrect ? 1 : 0, $studyTime, $requestId, $removed ? 1 : 0]);

        $pdo->commit();

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "idempotent" => false,
            "removed" => $removed,
            "word_id" => $wordId,
            "is_correct" => $isCorrect
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();

        $recheckStmt = $pdo->prepare("SELECT word_id, is_correct, review_removed FROM study_records WHERE request_id = ?");
        $recheckStmt->execute([$requestId]);
        $raceResult = $recheckStmt->fetch(PDO::FETCH_ASSOC);

        if ($raceResult) {
            $raceIsCorrect = (bool)$raceResult['is_correct'];
            if ((int)$raceResult['word_id'] !== $wordId || $raceIsCorrect !== $isCorrect) {
                http_response_code(409);
                echo json_encode([
                    "error" => "Conflict: request_id was already used with different parameters",
                    "existing_word_id" => (int)$raceResult['word_id'],
                    "existing_is_correct" => $raceIsCorrect
                ]);
            } else {
                http_response_code(200);
                echo json_encode([
                    "success" => true,
                    "idempotent" => true,
                    "removed" => (bool)$raceResult['review_removed'],
                    "word_id" => $wordId,
                    "is_correct" => $isCorrect
                ]);
            }
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Failed to save review: " . $e->getMessage()]);
        }
    }
}
