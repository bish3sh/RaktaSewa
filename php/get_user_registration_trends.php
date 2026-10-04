<?php
// Complete error suppression and JSON-only output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('html_errors', 0);
error_reporting(0);

// Start output buffering immediately
ob_start();

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

try {
    require_once 'db_connect.php';

    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    $daysParam = isset($_GET['days']) ? (int)$_GET['days'] : 14;
    $days = max(7, min(60, $daysParam));

    $startDate = new DateTime();
    $startDate->modify('-' . ($days - 1) . ' days');
    $startDateString = $startDate->format('Y-m-d');

    $stmt = $pdo->prepare(
        "SELECT DATE(created_at) as day, COUNT(*) as count
         FROM users
         WHERE created_at >= ?
         GROUP BY DATE(created_at)
         ORDER BY DATE(created_at) ASC"
    );
    $stmt->execute([$startDateString]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countsByDay = [];
    foreach ($rows as $row) {
        $countsByDay[$row['day']] = (int)$row['count'];
    }

    $series = [];
    $cursor = clone $startDate;
    $today = new DateTime();

    while ($cursor <= $today) {
        $dayKey = $cursor->format('Y-m-d');
        $series[] = [
            'date' => $dayKey,
            'label' => $cursor->format('D'),
            'value' => $countsByDay[$dayKey] ?? 0
        ];
        $cursor->modify('+1 day');
    }

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => [
            'days' => count($series),
            'series' => $series
        ]
    ]);
    exit;
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load registration trends',
        'data' => [
            'days' => 0,
            'series' => []
        ]
    ]);
    exit;
}
?>