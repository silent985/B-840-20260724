<?php
require_once __DIR__ . '/../index.php';

$method = $_SERVER['REQUEST_METHOD'];

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

if ($method !== 'GET') wrongMethod('GET');
if (count($pathParts) > 1) reject(404, "Endpoint not found");

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM words");
    $total = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as mastered FROM words WHERE mastered = 1");
    $mastered = (int)$stmt->fetch(PDO::FETCH_ASSOC)['mastered'];

    $stmt = $pdo->query("SELECT COUNT(*) as remaining FROM words WHERE mastered = 0");
    $remaining = (int)$stmt->fetch(PDO::FETCH_ASSOC)['remaining'];

    $stmt = $pdo->query("SELECT COUNT(*) as total_attempts FROM study_records");
    $totalAttempts = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total_attempts'];

    $stmt = $pdo->query("SELECT COUNT(*) as correct_attempts FROM study_records WHERE is_correct = 1");
    $correctAttempts = (int)$stmt->fetch(PDO::FETCH_ASSOC)['correct_attempts'];

    $accuracy = $totalAttempts > 0 ? round(($correctAttempts / $totalAttempts) * 100, 1) : 0;

    $stmt = $pdo->query("SELECT COUNT(*) as wrong_count FROM wrong_words");
    $wrongCount = (int)$stmt->fetch(PDO::FETCH_ASSOC)['wrong_count'];

    $trend = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) as cnt, COALESCE(SUM(is_correct), 0) as correct
             FROM study_records
             WHERE DATE(studied_at) = ?"
        );
        $stmt->execute([$date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $trend[] = [
            'date' => $date,
            'label' => date('m/d', strtotime($date)),
            'count' => (int)$row['cnt'],
            'correct' => (int)$row['correct']
        ];
    }

    echo json_encode([
        "total" => $total,
        "mastered" => $mastered,
        "remaining" => $remaining,
        "progress_percentage" => $total > 0 ? round(($mastered / $total) * 100, 1) : 0,
        "total_attempts" => $totalAttempts,
        "correct_attempts" => $correctAttempts,
        "accuracy" => $accuracy,
        "wrong_count" => $wrongCount,
        "trend" => $trend
    ]);
} catch (PDOException $e) {
    reject(500, "Failed to fetch progress");
}
