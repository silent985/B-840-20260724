<?php
require_once __DIR__ . '/../index.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM words");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as mastered FROM words WHERE mastered = 1");
    $mastered = $stmt->fetch(PDO::FETCH_ASSOC)['mastered'];

    $stmt = $pdo->query("SELECT COUNT(*) as remaining FROM words WHERE mastered = 0");
    $remaining = $stmt->fetch(PDO::FETCH_ASSOC)['remaining'];

    $stmt = $pdo->query("SELECT COUNT(*) as total_sessions FROM study_sessions");
    $totalSessions = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total_sessions'];

    $stmt = $pdo->query("SELECT COUNT(*) as total_answers FROM study_records");
    $totalAnswers = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total_answers'];

    $stmt = $pdo->query("SELECT COUNT(*) as correct_answers FROM study_records WHERE is_correct = 1");
    $correctAnswers = (int)$stmt->fetch(PDO::FETCH_ASSOC)['correct_answers'];

    $accuracy = $totalAnswers > 0 ? round(($correctAnswers / $totalAnswers) * 100, 1) : 0;

    $stmt = $pdo->query("SELECT COUNT(*) as wrong_count FROM wrong_words");
    $wrongCount = (int)$stmt->fetch(PDO::FETCH_ASSOC)['wrong_count'];

    $trend = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayStmt = $pdo->prepare("SELECT COUNT(*) as count FROM study_records WHERE DATE(study_time) = ?");
        $dayStmt->execute([$date]);
        $dayCount = (int)$dayStmt->fetch(PDO::FETCH_ASSOC)['count'];

        $correctStmt = $pdo->prepare("SELECT COUNT(*) as correct FROM study_records WHERE DATE(study_time) = ? AND is_correct = 1");
        $correctStmt->execute([$date]);
        $dayCorrect = (int)$correctStmt->fetch(PDO::FETCH_ASSOC)['correct'];

        $sessionStmt = $pdo->prepare("SELECT COUNT(*) as sessions FROM study_sessions WHERE DATE(started_at) = ?");
        $sessionStmt->execute([$date]);
        $daySessions = (int)$sessionStmt->fetch(PDO::FETCH_ASSOC)['sessions'];

        $trend[] = [
            'date' => $date,
            'label' => date('m/d', strtotime($date)),
            'total' => $dayCount,
            'correct' => $dayCorrect,
            'sessions' => $daySessions
        ];
    }

    echo json_encode([
        "total" => (int)$total,
        "mastered" => (int)$mastered,
        "remaining" => (int)$remaining,
        "progress_percentage" => $total > 0 ? round(($mastered / $total) * 100, 1) : 0,
        "total_sessions" => $totalSessions,
        "total_answers" => $totalAnswers,
        "correct_answers" => $correctAnswers,
        "accuracy" => $accuracy,
        "wrong_count" => $wrongCount,
        "trend" => $trend
    ]);
}
