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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $subEndpoint = $pathParts[1] ?? '';

    if ($subEndpoint === '') {
        $limit = validateInteger($_GET['limit'] ?? 10, 'limit', 1, 100);

        $stmt = $pdo->query("SELECT id, word, definition, example FROM words WHERE mastered = 0 ORDER BY RAND() LIMIT $limit");
        $words = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($words)) {
            $stmt = $pdo->query("SELECT id, word, definition, example FROM words ORDER BY RAND() LIMIT $limit");
            $words = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode($words);
    } elseif ($subEndpoint === 'session') {
        $sessionId = $pathParts[2] ?? '';
        if ($sessionId === '') {
            http_response_code(400);
            echo json_encode(["error" => "Session ID is required"]);
            exit;
        }

        $sessionId = validateString($sessionId, 'sessionId', 64);

        $stmt = $pdo->prepare("SELECT session_id, total_words, correct_count, started_at, completed_at FROM study_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            http_response_code(404);
            echo json_encode(["error" => "Session not found"]);
            exit;
        }

        $recordsStmt = $pdo->prepare("SELECT word_id, is_correct, study_time FROM study_records WHERE session_id = ?");
        $recordsStmt->execute([$sessionId]);
        $records = $recordsStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "session" => $session,
            "records" => $records
        ]);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Endpoint not found"]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subEndpoint = $pathParts[1] ?? '';

    if ($subEndpoint === 'session') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid JSON"]);
            exit;
        }

        $sessionId = validateString($data['session_id'] ?? '', 'session_id', 64);
        $totalWords = validateInteger($data['total_words'] ?? 0, 'total_words', 1, 200);

        $startedAt = date('Y-m-d H:i:s');

        try {
            $stmt = $pdo->prepare("SELECT session_id, total_words, started_at FROM study_sessions WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                http_response_code(200);
                echo json_encode([
                    "success" => true,
                    "idempotent" => true,
                    "session_id" => $existing['session_id'],
                    "total_words" => (int)$existing['total_words'],
                    "started_at" => $existing['started_at']
                ]);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO study_sessions (session_id, total_words, correct_count, started_at) VALUES (?, ?, 0, ?)");
            $stmt->execute([$sessionId, $totalWords, $startedAt]);

            http_response_code(201);
            echo json_encode([
                "success" => true,
                "idempotent" => false,
                "session_id" => $sessionId,
                "total_words" => $totalWords,
                "started_at" => $startedAt
            ]);
        } catch (Exception $e) {
            $stmt = $pdo->prepare("SELECT session_id, total_words, started_at FROM study_sessions WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                http_response_code(200);
                echo json_encode([
                    "success" => true,
                    "idempotent" => true,
                    "session_id" => $existing['session_id'],
                    "total_words" => (int)$existing['total_words'],
                    "started_at" => $existing['started_at']
                ]);
            } else {
                http_response_code(500);
                echo json_encode(["error" => "Failed to create session: " . $e->getMessage()]);
            }
        }
    } elseif ($subEndpoint === 'record') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid JSON"]);
            exit;
        }

        $sessionId = validateString($data['session_id'] ?? '', 'session_id', 64);
        $wordId = validateInteger($data['word_id'] ?? 0, 'word_id', 1);
        $isCorrect = validateBoolean($data['is_correct'] ?? null, 'is_correct');

        $sessionStmt = $pdo->prepare("SELECT id, completed_at FROM study_sessions WHERE session_id = ?");
        $sessionStmt->execute([$sessionId]);
        $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            http_response_code(404);
            echo json_encode(["error" => "Session not found"]);
            exit;
        }

        if ($session['completed_at'] !== null) {
            http_response_code(400);
            echo json_encode(["error" => "Session already completed"]);
            exit;
        }

        $wordStmt = $pdo->prepare("SELECT id, word, definition, example FROM words WHERE id = ?");
        $wordStmt->execute([$wordId]);
        $wordData = $wordStmt->fetch(PDO::FETCH_ASSOC);

        if (!$wordData) {
            http_response_code(404);
            echo json_encode(["error" => "Word not found"]);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $existingStmt = $pdo->prepare("SELECT id, is_correct, study_time FROM study_records WHERE session_id = ? AND word_id = ? FOR UPDATE");
            $existingStmt->execute([$sessionId, $wordId]);
            $existingRecord = $existingStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingRecord) {
                $pdo->commit();

                $existingIsCorrect = (bool)$existingRecord['is_correct'];
                if ($existingIsCorrect !== $isCorrect) {
                    http_response_code(409);
                    echo json_encode([
                        "error" => "Conflict: this word was already answered differently in this session",
                        "existing_is_correct" => $existingIsCorrect,
                        "submitted_is_correct" => $isCorrect
                    ]);
                    exit;
                }

                http_response_code(200);
                echo json_encode([
                    "success" => true,
                    "idempotent" => true,
                    "session_id" => $sessionId,
                    "word_id" => $wordId,
                    "is_correct" => $existingIsCorrect,
                    "study_time" => $existingRecord['study_time']
                ]);
                exit;
            }

            $studyTime = date('Y-m-d H:i:s');

            $stmt = $pdo->prepare("INSERT INTO study_records (session_id, word_id, word, is_correct, study_time) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$sessionId, $wordId, $wordData['word'], $isCorrect ? 1 : 0, $studyTime]);

            if ($isCorrect) {
                $updateSessionStmt = $pdo->prepare("UPDATE study_sessions SET correct_count = correct_count + 1 WHERE session_id = ?");
                $updateSessionStmt->execute([$sessionId]);

                $masterStmt = $pdo->prepare("UPDATE words SET mastered = 1 WHERE id = ?");
                $masterStmt->execute([$wordId]);
            } else {
                $checkWrongStmt = $pdo->prepare("SELECT id FROM wrong_words WHERE word_id = ?");
                $checkWrongStmt->execute([$wordId]);
                $existingWrong = $checkWrongStmt->fetch();

                if ($existingWrong) {
                    $updateWrongStmt = $pdo->prepare("UPDATE wrong_words SET wrong_count = wrong_count + 1, last_wrong_time = ? WHERE word_id = ?");
                    $updateWrongStmt->execute([$studyTime, $wordId]);
                } else {
                    $insertWrongStmt = $pdo->prepare("INSERT INTO wrong_words (word_id, word, definition, example, wrong_count, last_wrong_time) VALUES (?, ?, ?, ?, 1, ?)");
                    $insertWrongStmt->execute([$wordId, $wordData['word'], $wordData['definition'], $wordData['example'], $studyTime]);
                }
            }

            $pdo->commit();

            http_response_code(201);
            echo json_encode([
                "success" => true,
                "idempotent" => false,
                "session_id" => $sessionId,
                "word_id" => $wordId,
                "is_correct" => $isCorrect,
                "study_time" => $studyTime
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();

            $checkStmt = $pdo->prepare("SELECT id, is_correct, study_time FROM study_records WHERE session_id = ? AND word_id = ?");
            $checkStmt->execute([$sessionId, $wordId]);
            $raceRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($raceRecord) {
                $existingIsCorrect = (bool)$raceRecord['is_correct'];
                if ($existingIsCorrect !== $isCorrect) {
                    http_response_code(409);
                    echo json_encode([
                        "error" => "Conflict: this word was already answered differently in this session",
                        "existing_is_correct" => $existingIsCorrect,
                        "submitted_is_correct" => $isCorrect
                    ]);
                } else {
                    http_response_code(200);
                    echo json_encode([
                        "success" => true,
                        "idempotent" => true,
                        "session_id" => $sessionId,
                        "word_id" => $wordId,
                        "is_correct" => $existingIsCorrect,
                        "study_time" => $raceRecord['study_time']
                    ]);
                }
            } else {
                http_response_code(500);
                echo json_encode(["error" => "Failed to save record: " . $e->getMessage()]);
            }
        }
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Endpoint not found"]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $subEndpoint = $pathParts[1] ?? '';

    if ($subEndpoint === 'session') {
        $sessionId = $pathParts[2] ?? '';
        if ($sessionId === '') {
            http_response_code(400);
            echo json_encode(["error" => "Session ID is required"]);
            exit;
        }

        $sessionId = validateString($sessionId, 'sessionId', 64);
        $completedAt = date('Y-m-d H:i:s');

        $checkStmt = $pdo->prepare("SELECT completed_at FROM study_sessions WHERE session_id = ?");
        $checkStmt->execute([$sessionId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            http_response_code(404);
            echo json_encode(["error" => "Session not found"]);
            exit;
        }

        if ($existing['completed_at'] !== null) {
            http_response_code(200);
            echo json_encode(["success" => true, "idempotent" => true, "already_completed" => true, "completed_at" => $existing['completed_at']]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE study_sessions SET completed_at = ? WHERE session_id = ? AND completed_at IS NULL");
        $stmt->execute([$completedAt, $sessionId]);

        http_response_code(200);
        echo json_encode(["success" => true, "idempotent" => false, "completed_at" => $completedAt]);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Endpoint not found"]);
    }
}
