<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Start output buffering to catch any stray output
ob_start();

try {
    // Database connection
    $pdo = null;
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=bloodbank_db;charset=utf8",
            "root",
            "",
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    } catch (PDOException $e) {
        // Database connection failed - use fallback data
        $pdo = null;
    }
    
    // Get filter parameters
    $status = $_GET['status'] ?? 'all';
    $period = $_GET['period'] ?? '30'; // days
    
    $stats = [];
    
    if ($pdo) {
        try {
            // Get statistics based on actual campaign data
            
            // Total campaigns from hospital_activities
            $stmt = $pdo->query("
                SELECT COUNT(*) 
                FROM hospital_activities ha
                JOIN hospitals h ON ha.hospital_id = h.id
                WHERE ha.activity_type = 'campaign_created'
                AND h.is_approved = 1
            ");
            $totalCampaigns = $stmt->fetchColumn();
            
            // Active campaigns (campaigns with status = 'active' or current date is within campaign dates)
            $stmt = $pdo->query("
                SELECT COUNT(*) 
                FROM hospital_activities ha
                JOIN hospitals h ON ha.hospital_id = h.id
                WHERE ha.activity_type = 'campaign_created'
                AND h.is_approved = 1
                AND (
                    JSON_UNQUOTE(JSON_EXTRACT(ha.activity_data, '$.status')) = 'active'
                    OR (
                        DATE(JSON_UNQUOTE(JSON_EXTRACT(ha.activity_data, '$.start_date'))) <= CURDATE()
                        AND DATE(JSON_UNQUOTE(JSON_EXTRACT(ha.activity_data, '$.end_date'))) >= CURDATE()
                    )
                )
            ");
            $activeCampaigns = $stmt->fetchColumn();
            
            // Upcoming campaigns (campaigns with start_date in the future)
            $stmt = $pdo->query("
                SELECT COUNT(*) 
                FROM hospital_activities ha
                JOIN hospitals h ON ha.hospital_id = h.id
                WHERE ha.activity_type = 'campaign_created'
                AND h.is_approved = 1
                AND DATE(JSON_UNQUOTE(JSON_EXTRACT(ha.activity_data, '$.start_date'))) > CURDATE()
            ");
            $upcomingCampaigns = $stmt->fetchColumn();
            
            // Completed campaigns (campaigns with status = 'completed' or end_date has passed)
            $stmt = $pdo->query("
                SELECT COUNT(*) 
                FROM hospital_activities ha
                JOIN hospitals h ON ha.hospital_id = h.id
                WHERE ha.activity_type = 'campaign_created'
                AND h.is_approved = 1
                AND (
                    JSON_UNQUOTE(JSON_EXTRACT(ha.activity_data, '$.status')) = 'completed'
                    OR DATE(JSON_UNQUOTE(JSON_EXTRACT(ha.activity_data, '$.end_date'))) < CURDATE()
                )
            ");
            $completedCampaigns = $stmt->fetchColumn();
            
            // Pending campaigns is not really applicable - set to 0
            $pendingCampaigns = 0;
            
            // Total participants (prefer campaign_registrations if available)
            $totalParticipants = 0;
            try {
                $countStmt = $pdo->query(
                    "SELECT COUNT(*) FROM campaign_registrations WHERE status IN ('registered','attended')"
                );
                $totalParticipants = (int)$countStmt->fetchColumn();
            } catch (Exception $e) {
                $totalParticipants = 0;
            }

            if ($totalParticipants === 0) {
                $stmt = $pdo->query("
                    SELECT SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(ha.activity_data, '$.current_donors')) AS UNSIGNED))
                    FROM hospital_activities ha
                    JOIN hospitals h ON ha.hospital_id = h.id
                    WHERE ha.activity_type = 'campaign_created'
                    AND h.is_approved = 1
                ");
                $totalParticipants = $stmt->fetchColumn() ?: 0;
            }
            
            // Total donations (activities) in the last period
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM hospital_activities WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$period]);
            $recentDonations = $stmt->fetchColumn();
            
            // Total donors (users with donor role)
            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'donor'");
            $totalDonors = $stmt->fetchColumn();
            
            // Blood units collected (from inventory)
            $stmt = $pdo->query("SELECT SUM(units_available) FROM blood_inventory");
            $totalUnitsCollected = $stmt->fetchColumn() ?: 0;
            
            $stats = [
                'total_campaigns' => (int)$totalCampaigns,
                'active_campaigns' => (int)$activeCampaigns,
                'upcoming_campaigns' => (int)$upcomingCampaigns,
                'completed_campaigns' => (int)$completedCampaigns,
                'pending_campaigns' => (int)$pendingCampaigns,
                'total_participants' => (int)$totalParticipants,
                'recent_donations' => (int)$recentDonations,
                'total_donors' => (int)$totalDonors,
                'total_units_collected' => (int)$totalUnitsCollected,
                'success_rate' => $totalCampaigns > 0 ? round(($completedCampaigns / $totalCampaigns) * 100, 1) : 0,
                'average_donors_per_campaign' => $totalCampaigns > 0 ? round($totalParticipants / $totalCampaigns, 1) : 0,
                'period_days' => (int)$period
            ];
            
        } catch (PDOException $e) {
            error_log("Database error in get_campaign_stats.php: " . $e->getMessage());
            $pdo = null; // Use fallback data
        }
    }
    
    // Fallback data if database is unavailable
    if (empty($stats)) {
        $stats = [
            'total_campaigns' => 0,
            'active_campaigns' => 0,
            'upcoming_campaigns' => 0,
            'completed_campaigns' => 0,
            'pending_campaigns' => 0,
            'total_participants' => 0,
            'recent_donations' => 0,
            'total_donors' => 0,
            'total_units_collected' => 0,
            'success_rate' => 0,
            'average_donors_per_campaign' => 0,
            'period_days' => (int)$period
        ];
    }
    
    // Add additional calculated metrics
    $stats['campaigns_by_status'] = [
        'active' => $stats['active_campaigns'],
        'completed' => $stats['completed_campaigns'],
        'pending' => $stats['pending_campaigns']
    ];
    
    $stats['recent_activity'] = [
        'donations_last_' . $period . '_days' => $stats['recent_donations'],
        'average_daily_donations' => round($stats['recent_donations'] / $period, 1)
    ];
    
    $stats['performance_metrics'] = [
        'success_rate' => $stats['success_rate'],
        'average_donors_per_campaign' => $stats['average_donors_per_campaign'],
        'units_per_donor' => $stats['total_donors'] > 0 ? round($stats['total_units_collected'] / $stats['total_donors'], 1) : 0
    ];
    
    // Clean output buffer and send response
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Campaign statistics retrieved successfully',
        'data' => $stats
    ]);
    
} catch (Exception $e) {
    // Clean any output and return error
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve campaign statistics: ' . $e->getMessage()
    ]);
}
?>