<?php
require_once __DIR__ . '/../index.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM words");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as mastered FROM words WHERE mastered = 1");
    $mastered = $stmt->fetch(PDO::FETCH_ASSOC)['mastered'];

    $stmt = $pdo->query("SELECT COUNT(*) as remaining FROM words WHERE mastered = 0");
    $remaining = $stmt->fetch(PDO::FETCH_ASSOC)['remaining'];

    echo json_encode([
        "total" => (int)$total,
        "mastered" => (int)$mastered,
        "remaining" => (int)$remaining,
        "progress_percentage" => $total > 0 ? round(($mastered / $total) * 100, 1) : 0
    ]);
}
