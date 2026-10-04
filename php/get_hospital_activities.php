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
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

try {
    // Database connection
    $host = 'localhost';
    $dbname = 'bloodbank_db';
    $username = 'root';
    $password = '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        $pdo = null; // Database unavailable
    }
    // Get parameters
    $limit = $_GET['limit'] ?? 10;
    $limit = max(1, min(50, (int)$limit)); // Ensure limit is between 1 and 50
    
    $hospital_id = $_GET['hospital_id'] ?? null;
    
    // Initialize activities array
    $activities = [];
    
    // Try to get real activities from various tables if they exist
    try {
        // Get blood donation activities
        $stmt = $pdo->prepare("
            SELECT 
                d.id,
                d.donation_date as activity_date,
                d.blood_type,
                d.quantity,
                d.status,
                u.username as donor_name,
                'donation' as activity_type
            FROM donations d
            LEFT JOIN users u ON d.user_id = u.id
            WHERE d.hospital_id = ?
            ORDER BY d.donation_date DESC
            LIMIT ?
        ");
        
        if ($hospital_id) {
            $stmt->execute([$hospital_id, $limit]);
        } else {
            // If no hospital_id, get from all hospitals
            $stmt = $pdo->prepare("
                SELECT 
                    d.id,
                    d.donation_date as activity_date,
                    d.blood_type,
                    d.quantity,
                    d.status,
                    u.username as donor_name,
                    h.hospital_name,
                    'donation' as activity_type
                FROM donations d
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN hospitals h ON d.hospital_id = h.id
                ORDER BY d.donation_date DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
        }
        
        $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert donations to activity format
        foreach ($donations as $donation) {
            $activities[] = [
                'id' => 'donation_' . $donation['id'],
                'type' => 'donation',
                'title' => 'Blood Donation',
                'description' => "{$donation['donor_name']} donated {$donation['blood_type']} blood",
                'details' => "Quantity: {$donation['quantity']} units",
                'blood_type' => $donation['blood_type'],
                'quantity' => $donation['quantity'],
                'status' => $donation['status'],
                'participant' => $donation['donor_name'],
                'hospital' => $donation['hospital_name'] ?? 'Current Hospital',
                'activity_date' => $donation['activity_date'],
                'icon' => 'fas fa-tint',
                'color' => 'success'
            ];
        }
        
    } catch (Exception $e) {
        // donations table doesn't exist or has different structure
    }
    
    // Try to get blood request activities
    try {
        $stmt = $pdo->prepare("
            SELECT 
                er.id,
                er.created_at as activity_date,
                er.blood_type,
                er.units_needed,
                er.status,
                er.urgency_level,
                er.notes,
                h.hospital_name,
                'emergency_request' as activity_type
            FROM emergency_requests er
            LEFT JOIN hospitals h ON er.hospital_id = h.id
            ORDER BY er.created_at DESC
            LIMIT " . (int)$limit . "
        ");
        
        $stmt->execute();
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert requests to activity format
        foreach ($requests as $request) {
            // Extract patient name from notes if available
            $patientName = 'Emergency Patient';
            if (preg_match('/Patient Name:\s*(.+?)(\n|$)/i', $request['notes'], $matches)) {
                $patientName = trim($matches[1]);
            }
            
            $activities[] = [
                'id' => 'request_' . $request['id'],
                'activity_type' => 'blood_request',
                'title' => 'Emergency Blood Request',
                'description' => "Emergency request for {$request['blood_type']} blood for {$patientName}",
                'details' => "Units needed: {$request['units_needed']}, Urgency: {$request['urgency_level']}",
                'blood_type' => $request['blood_type'],
                'quantity' => $request['units_needed'],
                'status' => $request['status'],
                'urgency' => $request['urgency_level'],
                'hospital' => $request['hospital_name'] ?? 'Unknown Hospital',
                'created_at' => $request['activity_date'],
                'icon' => 'fas fa-exclamation-triangle',
                'color' => $request['urgency_level'] === 'critical' ? 'danger' : ($request['urgency_level'] === 'high' ? 'warning' : 'info')
            ];
        }
        
    } catch (Exception $e) {
        error_log("Error fetching emergency requests: " . $e->getMessage());
    }
    
    // Try to get appointment activities
    try {
        $stmt = $pdo->prepare("
            SELECT 
                a.id,
                a.appointment_date as activity_date,
                a.status,
                a.created_at,
                u.full_name as donor_name,
                u.blood_type,
                h.hospital_name,
                'appointment' as activity_type
            FROM appointments a
            LEFT JOIN users u ON a.donor_id = u.user_id
            LEFT JOIN hospitals h ON a.hospital_id = h.id
            ORDER BY a.created_at DESC
            LIMIT " . (int)$limit . "
        ");
        
        $stmt->execute();
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert appointments to activity format
        foreach ($appointments as $appointment) {
            $statusText = ucfirst($appointment['status']);
            $activities[] = [
                'id' => 'appointment_' . $appointment['id'],
                'activity_type' => 'donation',
                'title' => 'Appointment ' . $statusText,
                'description' => "{$appointment['donor_name']} ({$appointment['blood_type']}) - Appointment {$statusText}",
                'details' => "Scheduled: " . date('M d, Y', strtotime($appointment['activity_date'])),
                'blood_type' => $appointment['blood_type'],
                'status' => $appointment['status'],
                'participant' => $appointment['donor_name'],
                'hospital' => $appointment['hospital_name'] ?? 'Current Hospital',
                'created_at' => $appointment['created_at'],
                'icon' => 'fas fa-calendar-check',
                'color' => $appointment['status'] === 'completed' ? 'success' : ($appointment['status'] === 'cancelled' ? 'danger' : 'info')
            ];
        }
        
    } catch (Exception $e) {
        error_log("Error fetching appointments: " . $e->getMessage());
    }
    
    // If no real activities found, provide a helpful message
    if (empty($activities)) {
        $activities = [[
            'id' => 'no_activity',
            'activity_type' => 'system',
            'title' => 'No Recent Activities',
            'description' => 'No recent activities to display',
            'details' => 'Activities will appear here as they occur',
            'created_at' => date('Y-m-d H:i:s'),
            'icon' => 'fas fa-info-circle',
            'color' => 'secondary'
        ]];
    }
    
    // Sort activities by date (most recent first)
    usort($activities, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Limit to requested number
    $activities = array_slice($activities, 0, $limit);
    
    // Format dates for display
    foreach ($activities as &$activity) {
        $activity['formatted_date'] = date('M d, Y g:i A', strtotime($activity['created_at']));
        $activity['time_ago'] = timeAgo($activity['created_at']);
    }
    
    // Activity statistics
    $stats = [
        'total_activities' => count($activities),
        'donations' => count(array_filter($activities, function($a) { return $a['activity_type'] === 'donation'; })),
        'requests' => count(array_filter($activities, function($a) { return $a['activity_type'] === 'blood_request' || $a['activity_type'] === 'emergency_request'; })),
        'appointments' => count(array_filter($activities, function($a) { return $a['activity_type'] === 'appointment'; })),
        'last_updated' => date('Y-m-d H:i:s')
    ];
    
    // Clean output buffer and send JSON
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => $activities,
        'stats' => $stats,
        'message' => 'Hospital activities retrieved successfully'
    ]);
    exit;
    
} catch (PDOException $e) {
    error_log("Database error in get_hospital_activities.php: " . $e->getMessage());
    
    // Return sample data instead of failing
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => [
            [
                'id' => 'sample_1',
                'type' => 'donation',
                'title' => 'Blood Donation',
                'description' => 'Sample donor donated O+ blood',
                'details' => 'Quantity: 1 unit, Status: Completed',
                'blood_type' => 'O+',
                'quantity' => 1,
                'status' => 'completed',
                'participant' => 'Sample Donor',
                'hospital' => 'Current Hospital',
                'activity_date' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'formatted_date' => date('M d, Y g:i A', strtotime('-2 hours')),
                'time_ago' => '2 hours ago',
                'icon' => 'fas fa-tint',
                'color' => 'success'
            ]
        ],
        'stats' => [
            'total_activities' => 1,
            'donations' => 1,
            'requests' => 0,
            'last_updated' => date('Y-m-d H:i:s')
        ],
        'message' => 'Sample hospital activities (database unavailable)'
    ]);
    exit;
} catch (Exception $e) {
    // Clear any output buffer and return clean JSON
    ob_clean();
    error_log("Error in get_hospital_activities.php: " . $e->getMessage());
    
    // Always return valid JSON
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load hospital activities',
        'data' => [],
        'error' => 'Service temporarily unavailable'
    ]);
}

// End output buffering
ob_end_flush();

// Helper function for time ago calculation
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M d, Y', strtotime($datetime));
}
?>