<?php
/**
 * Get Registered Campaigns for Current User
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';

try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'User not authenticated'
        ]);
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $db = getDBConnection();

    $stmt = $db->prepare("SELECT campaign_id FROM campaign_registrations WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $campaigns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'data' => $campaigns
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
