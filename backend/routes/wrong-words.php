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

$allowedMethods = ['GET', 'DELETE'];
if (!in_array($method, $allowedMethods, true)) wrongMethod('GET, DELETE');

if ($method === 'GET') {
    if ($id !== null) reject(404, "Endpoint not found");

    $searchRaw = $_GET['search'] ?? '';
    if (is_array($searchRaw) || is_object($searchRaw) || is_bool($searchRaw)) {
        reject(400, "search must be a string");
    }
    $search = trim((string)$searchRaw);

    if (mb_strlen($search) > 255) {
        reject(400, "Search term must not exceed 255 characters");
    }

    $sql = "SELECT ww.id, ww.word_id, ww.review_count, ww.added_at, ww.last_reviewed_at,
                   w.word, w.definition, w.example
            FROM wrong_words ww
            INNER JOIN words w ON w.id = ww.word_id";

    $params = [];
    if ($search !== '') {
        $sql .= " WHERE w.word LIKE ? OR w.definition LIKE ?";
        $like = "%{$search}%";
        $params = [$like, $like];
    }

    $sql .= " ORDER BY ww.added_at DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $wrongWords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($wrongWords);
    } catch (PDOException $e) {
        reject(500, "Failed to fetch wrong words");
    }
}

if ($method === 'DELETE') {
    if (!$id || !ctype_digit((string)$id) || (int)$id <= 0) {
        reject(400, "Wrong word ID is required and must be a positive integer");
    }
    if (count($pathParts) > 2) reject(404, "Endpoint not found");

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id FROM wrong_words WHERE id = ?");
        $stmt->execute([(int)$id]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            reject(404, "Wrong word not found");
        }

        $stmt = $pdo->prepare("DELETE FROM wrong_words WHERE id = ?");
        $stmt->execute([(int)$id]);

        $pdo->commit();
        echo json_encode(["success" => true]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        reject(500, "Failed to remove wrong word");
    }
}
