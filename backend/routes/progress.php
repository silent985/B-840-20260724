<?php
require_once __DIR__ . '/../index.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError("Method not allowed", 405);
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM words");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as mastered FROM words WHERE mastered = 1");
    $mastered = $stmt->fetch(PDO::FETCH_ASSOC)['mastered'];

    $stmt = $pdo->query("SELECT COUNT(*) as remaining FROM words WHERE mastered = 0");
    $remaining = $stmt->fetch(PDO::FETCH_ASSOC)['remaining'];

    // 累计学习次数与正确率（基于每次答题记录）
    $stmt = $pdo->query(
        "SELECT COUNT(*) as total_records,
                SUM(is_correct) as correct_records
         FROM study_records"
    );
    $recordStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalSessions = (int)$recordStats['total_records'];
    $correctSessions = (int)$recordStats['correct_records'];
    $accuracy = $totalSessions > 0
        ? round(($correctSessions / $totalSessions) * 100, 1)
        : 0;

    // 错词集数量
    $stmt = $pdo->query("SELECT COUNT(*) as wrong FROM wrong_words");
    $wrongWords = (int)$stmt->fetch(PDO::FETCH_ASSOC)['wrong'];

    // 最近 7 天学习趋势：按日聚合答题次数，缺失的日期补 0
    $stmt = $pdo->query(
        "SELECT DATE(studied_at) as study_date, COUNT(*) as count
         FROM study_records
         WHERE studied_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(studied_at)"
    );
    $countsByDate = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $countsByDate[$row['study_date']] = (int)$row['count'];
    }
} catch (PDOException $e) {
    jsonError("Failed to load progress", 500);
}

$trend = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i day"));
    $trend[] = [
        "date" => $date,
        "count" => $countsByDate[$date] ?? 0
    ];
}

echo json_encode([
    "total" => (int)$total,
    "mastered" => (int)$mastered,
    "remaining" => (int)$remaining,
    "progress_percentage" => $total > 0 ? round(($mastered / $total) * 100, 1) : 0,
    "total_sessions" => $totalSessions,
    "accuracy" => $accuracy,
    "wrong_words" => $wrongWords,
    "weekly_trend" => $trend
]);
