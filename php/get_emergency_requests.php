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
    
    $pdo = null;
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        $pdo = null; // Database unavailable
    }

    // Get parameters
    $status = $_GET['status'] ?? null;
    $limit = $_GET['limit'] ?? 20;
    $limit = max(1, min(100, (int)$limit));
    
    $emergencyRequests = [];
    
    if ($pdo) {
        try {
            // Try to get real emergency requests from database
            $sql = "SELECT 
                        r.id,
                        r.blood_type,
                        r.units_needed,
                        r.urgency_level,
                        r.status,
                        r.notes,
                        r.location,
                        r.contact_person,
                        r.contact_phone,
                        r.created_at,
                        r.updated_at,
                        h.hospital_name,
                        h.address as hospital_address,
                        h.contact_email as hospital_email
                    FROM emergency_requests r
                    LEFT JOIN hospitals h ON r.hospital_id = h.id
                    WHERE 1=1";
            
            $params = [];
            
            if ($status) {
                $sql .= " AND r.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY 
                        CASE r.urgency_level 
                            WHEN 'emergency' THEN 1
                            WHEN 'critical' THEN 2  
                            WHEN 'high' THEN 3
                            WHEN 'medium' THEN 4
                            ELSE 5
                        END,
                        r.created_at DESC
                      LIMIT " . (int)$limit;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $emergencyRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Query executed. Results count: " . count($emergencyRequests));
            error_log("SQL: " . $sql);
            error_log("Params: " . json_encode($params));
            
            // Add urgency field as alias for urgency_level for frontend compatibility
            foreach ($emergencyRequests as &$request) {
                $request['urgency'] = $request['urgency_level'];
                $request['phone'] = $request['contact_phone'] ?? '';
                $request['contact_email'] = $request['hospital_email'] ?? '';
                
                // Extract patient_name from notes if it exists, otherwise create a placeholder
                if (!empty($request['notes']) && preg_match('/Patient Name:\s*(.+?)(\n|$)/i', $request['notes'], $matches)) {
                    $request['patient_name'] = trim($matches[1]);
                } else {
                    $request['patient_name'] = 'Emergency Case #' . $request['id'];
                }
            }
            
            // Log the query results
            error_log("Emergency requests query returned: " . count($emergencyRequests) . " results");
            
        } catch (PDOException $e) {
            error_log("Database error in get_emergency_requests.php: " . $e->getMessage());
            $emergencyRequests = []; // Return empty array instead of sample data
        }
    } else {
        error_log("Database connection not available in get_emergency_requests.php");
        $emergencyRequests = [];
    }
    
    // Don't use sample data fallback - always return real data or empty array
    // This ensures we only show actual emergency requests from the database
    
    // Format the data for frontend
    foreach ($emergencyRequests as &$request) {
        // Normalize urgency_level to urgency for frontend compatibility
        $request['urgency'] = $request['urgency_level'] ?? 'normal';
        
        // Add urgency colors and priorities
        $urgencyInfo = [
            'emergency' => ['color' => 'danger', 'priority' => 1, 'text' => 'EMERGENCY'],
            'critical' => ['color' => 'warning', 'priority' => 2, 'text' => 'CRITICAL'],
            'high' => ['color' => 'info', 'priority' => 3, 'text' => 'HIGH'],
            'medium' => ['color' => 'primary', 'priority' => 4, 'text' => 'MEDIUM'],
            'normal' => ['color' => 'secondary', 'priority' => 5, 'text' => 'NORMAL']
        ];
        
        $urgency = $request['urgency_level'] ?? 'normal';
        $request['urgency_info'] = $urgencyInfo[$urgency] ?? $urgencyInfo['normal'];
        
        // Format timestamps
        $request['created_formatted'] = date('M j, Y g:i A', strtotime($request['created_at']));
        
        // Calculate time ago
        $time = time() - strtotime($request['created_at']);
        if ($time < 60) {
            $request['time_ago'] = 'just now';
        } elseif ($time < 3600) {
            $request['time_ago'] = floor($time/60) . ' minutes ago';
        } elseif ($time < 86400) {
            $request['time_ago'] = floor($time/3600) . ' hours ago';
        } else {
            $request['time_ago'] = floor($time/86400) . ' days ago';
        }
        
        // Add status styling
        $statusInfo = [
            'pending' => ['color' => 'warning', 'text' => 'Pending'],
            'accepted' => ['color' => 'info', 'text' => 'Accepted'],
            'fulfilled' => ['color' => 'success', 'text' => 'Fulfilled'],
            'cancelled' => ['color' => 'secondary', 'text' => 'Cancelled']
        ];
        
        $status = $request['status'] ?? 'pending';
        $request['status_info'] = $statusInfo[$status] ?? $statusInfo['pending'];
    }
    
    // Calculate statistics
    $stats = [
        'total_requests' => count($emergencyRequests),
        'critical_count' => count(array_filter($emergencyRequests, function($r) { 
            return $r['urgency_level'] === 'critical'; 
        })),
        'high_count' => count(array_filter($emergencyRequests, function($r) { 
            return $r['urgency_level'] === 'high'; 
        })),
        'medium_count' => count(array_filter($emergencyRequests, function($r) { 
            return $r['urgency_level'] === 'medium'; 
        })),
        'pending_count' => count(array_filter($emergencyRequests, function($r) { 
            return $r['status'] === 'pending'; 
        })),
        'blood_types' => array_count_values(array_column($emergencyRequests, 'blood_type')),
        'total_units_needed' => array_sum(array_column($emergencyRequests, 'units_needed')),
        'last_updated' => date('Y-m-d H:i:s')
    ];
    
    // Clean output buffer and send JSON
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => $emergencyRequests,
        'stats' => $stats,
        'filters' => [
            'status' => $status,
            'limit' => $limit
        ],
        'message' => 'Emergency requests retrieved successfully'
    ]);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Service temporarily unavailable',
        'data' => [],
        'error' => 'Unable to process request'
    ]);
    exit;
}
?>