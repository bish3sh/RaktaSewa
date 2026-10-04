<?php
/**
 * Get Admin Dashboard Statistics
 * Returns key metrics for the admin dashboard
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db_connect.php';

try {
    // Debug session information
    error_log("Session debug - ID: " . session_id() . ", Status: " . session_status() . ", Data: " . print_r($_SESSION, true));
    
    // Check if user is authenticated and is admin
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. Admin privileges required.',
            'debug' => [
                'session_id' => session_id(),
                'session_status' => session_status(),
                'has_user_id' => isset($_SESSION['user_id']),
                'user_role' => $_SESSION['role'] ?? 'not_set',
                'session_data' => $_SESSION ?? []
            ]
        ]);
        exit;
    }
    
    $db = getDBConnection();
    
    // Get total statistics
    $stats = [];
    
    // Total users by role
    $userStatsStmt = $db->query("
        SELECT role, COUNT(*) as count 
        FROM users 
        GROUP BY role
    ");
    $userStats = $userStatsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stats['total_users'] = array_sum($userStats);
    $stats['total_donors'] = $userStats['donor'] ?? 0;
    $stats['total_hospitals'] = $userStats['hospital'] ?? 0;
    $stats['total_admins'] = $userStats['admin'] ?? 0;
    
    // Dashboard expects these specific field names
    $stats['new_users_today'] = 0; // Would need separate query for today's registrations
    
    // Hospital statistics
    $hospitalStatsStmt = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending
        FROM hospitals
    ");
    $hospitalStats = $hospitalStatsStmt->fetch();
    
    $stats['hospitals_approved'] = (int)$hospitalStats['approved'];
    $stats['hospitals_pending'] = (int)$hospitalStats['pending'];
    
    // Dashboard expected field names
    $stats['active_hospitals'] = (int)$hospitalStats['approved'];
    $stats['pending_hospitals'] = (int)$hospitalStats['pending'];
    
    // Blood inventory statistics
    $bloodStatsStmt = $db->query("
        SELECT 
            SUM(units_available) as total_available,
            SUM(units_required) as total_required,
            COUNT(DISTINCT hospital_id) as hospitals_with_inventory
        FROM blood_inventory
    ");
    $bloodStats = $bloodStatsStmt->fetch();
    
    $stats['total_blood_units'] = (int)($bloodStats['total_available'] ?? 0);
    $stats['blood_requests'] = (int)($bloodStats['total_required'] ?? 0);
    $stats['active_blood_banks'] = (int)($bloodStats['hospitals_with_inventory'] ?? 0);
    
    // Donation statistics (check if donations table exists)
    try {
        $donationStatsStmt = $db->query("
            SELECT 
                COUNT(*) as total_donations,
                COUNT(CASE WHEN MONTH(donation_date) = MONTH(NOW()) AND YEAR(donation_date) = YEAR(NOW()) THEN 1 END) as donations_this_month
            FROM donations 
            WHERE status = 'completed'
        ");
        $donationStats = $donationStatsStmt->fetch();
        
        $stats['total_donations'] = (int)($donationStats['total_donations'] ?? 0);
        $stats['donations_this_month'] = (int)($donationStats['donations_this_month'] ?? 0);
    } catch (Exception $e) {
        // If donations table doesn't exist or has issues
        $stats['total_donations'] = 0;
        $stats['donations_this_month'] = 0;
    }
    
    // Emergency and critical requests (placeholder values)
    $stats['emergency_requests'] = 0;
    $stats['critical_requests'] = 0;
    
    // Recent registration statistics (last 30 days)
    $recentStatsStmt = $db->query("
        SELECT 
            COUNT(*) as recent_registrations,
            SUM(CASE WHEN role = 'donor' THEN 1 ELSE 0 END) as recent_donors,
            SUM(CASE WHEN role = 'hospital' THEN 1 ELSE 0 END) as recent_hospitals
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $recentStats = $recentStatsStmt->fetch();
    
    $stats['recent_registrations'] = (int)($recentStats['recent_registrations'] ?? 0);
    $stats['recent_donors'] = (int)($recentStats['recent_donors'] ?? 0);
    $stats['recent_hospitals'] = (int)($recentStats['recent_hospitals'] ?? 0);
    
    // Blood type distribution - ensure consistent order and exactly 8 types
    $bloodTypeStmt = $db->query("
        SELECT 
            blood_type,
            SUM(units_available) as total_units
        FROM blood_inventory 
        WHERE blood_type IN ('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-')
        GROUP BY blood_type
        ORDER BY FIELD(blood_type, 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-')
    ");
    $bloodTypesData = $bloodTypeStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Ensure all 8 blood types are present with 0 if not found
    $allBloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    $bloodTypes = [];
    foreach ($allBloodTypes as $type) {
        $bloodTypes[] = [
            'blood_type' => $type,
            'total_units' => (int)($bloodTypesData[$type] ?? 0)
        ];
    }
    
    $stats['blood_type_distribution'] = $bloodTypes;
    
    // Donation trends (last 7 days for cleaner display)
    try {
        // Get donation trends from donations table for the last 7 days
        $trendsStmt = $db->query("
            SELECT 
                DATE_FORMAT(donation_date, '%b %d') as period,
                DATE(donation_date) as date_key,
                COUNT(*) as donation_count
            FROM donations
            WHERE donation_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            AND donation_date <= CURDATE()
            GROUP BY DATE(donation_date), DATE_FORMAT(donation_date, '%b %d')
            ORDER BY DATE(donation_date) ASC
        ");
        $trendData = $trendsStmt->fetchAll();
        
        // Create array with last 7 days (fill in missing days with 0)
        $labels = [];
        $data = [];
        $dataMap = [];
        
        // Map existing data
        foreach ($trendData as $row) {
            $dataMap[$row['date_key']] = (int)$row['donation_count'];
        }
        
        // Generate last 7 days
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $label = date('M j', strtotime("-$i days"));
            $labels[] = $label;
            $data[] = isset($dataMap[$date]) ? $dataMap[$date] : 0;
        }
        
        $stats['donation_trends'] = [
            'labels' => $labels,
            'data' => $data
        ];
        
    } catch (Exception $e) {
        error_log("Donation trends error: " . $e->getMessage());
        // Fallback to empty 7-day trend
        $labels = [];
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('M j', strtotime("-$i days"));
            $labels[] = $date;
            $data[] = 0;
        }
        $stats['donation_trends'] = [
            'labels' => $labels,
            'data' => $data
        ];
    }
    
    // Geographic distribution (donors by city from users table)
    try {
        $geoStmt = $db->query("
            SELECT 
                city,
                COUNT(*) as donor_count
            FROM users
            WHERE role = 'donor' AND city IS NOT NULL AND city != ''
            GROUP BY city
            ORDER BY donor_count DESC
            LIMIT 10
        ");
        $geoData = $geoStmt->fetchAll();
        $stats['geographic_distribution'] = $geoData;
    } catch (Exception $e) {
        error_log("Geographic distribution error: " . $e->getMessage());
        $stats['geographic_distribution'] = [];
    }
    
    // System health indicators
    $stats['system_health'] = [
        'database_status' => 'online',
        'total_tables' => 5, // users, hospitals, blood_inventory, notifications, activity_logs
        'last_backup' => null, // Would need backup system
        'uptime' => '99.9%' // Placeholder
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Admin stats error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load dashboard statistics',
        'data' => []
    ]);
}
?>