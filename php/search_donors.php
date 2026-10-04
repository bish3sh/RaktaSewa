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
    
    // Get search parameters
    $query = $_GET['query'] ?? '';
    $bloodType = $_GET['blood_type'] ?? '';
    $limit = (int)($_GET['limit'] ?? 10);
    $limit = max(1, min(50, $limit)); // Ensure limit is between 1 and 50
    
    $donors = [];
    
    if ($pdo) {
        try {
            // Build the search query
            $sql = "SELECT 
                        u.id,
                        u.full_name,
                        u.email,
                        u.phone,
                        u.blood_type,
                        u.city,
                        u.state,
                        u.is_eligible,
                        u.created_at,
                        COUNT(d.id) as total_donations,
                        MAX(d.donation_date) as last_donation
                    FROM users u
                    LEFT JOIN donations d ON u.id = d.donor_id AND d.status = 'completed'
                    WHERE u.role = 'donor' 
                    AND u.is_active = 1";
            
            $params = [];
            
            // Add search query filter
            if (!empty($query)) {
                $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
                $searchParam = '%' . $query . '%';
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }
            
            // Add blood type filter
            if (!empty($bloodType)) {
                $sql .= " AND u.blood_type = ?";
                $params[] = $bloodType;
            }
            
            $sql .= " GROUP BY u.id ORDER BY u.full_name LIMIT ?";
            $params[] = $limit;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $realDonors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($realDonors)) {
                $donors = $realDonors;
            }
            
        } catch (PDOException $e) {
            error_log("Database error in search_donors.php: " . $e->getMessage());
            $donors = []; // Return empty array if database error
        }
    }
    
    // Format the data for frontend
    foreach ($donors as &$donor) {
        // Calculate donation eligibility
        $daysSinceLastDonation = 0;
        if ($donor['last_donation']) {
            $daysSinceLastDonation = floor((time() - strtotime($donor['last_donation'])) / 86400);
        }
        
        $donor['days_since_last_donation'] = $daysSinceLastDonation;
        $donor['eligible_to_donate'] = $donor['is_eligible'] && ($daysSinceLastDonation >= 56 || !$donor['last_donation']);
        
        // Format dates
        if ($donor['last_donation']) {
            $donor['last_donation_formatted'] = date('M j, Y', strtotime($donor['last_donation']));
        } else {
            $donor['last_donation_formatted'] = 'Never';
        }
        
        // Add contact status
        $donor['contact_status'] = $donor['phone'] ? 'available' : 'no_phone';
        
        // Add eligibility status with color
        if ($donor['eligible_to_donate']) {
            $donor['eligibility_status'] = [
                'text' => 'Eligible',
                'color' => 'success',
                'icon' => 'fa-check-circle'
            ];
        } else if ($daysSinceLastDonation < 56 && $donor['last_donation']) {
            $daysRemaining = 56 - $daysSinceLastDonation;
            $donor['eligibility_status'] = [
                'text' => "Eligible in $daysRemaining days",
                'color' => 'warning',
                'icon' => 'fa-clock'
            ];
        } else {
            $donor['eligibility_status'] = [
                'text' => 'Not Eligible',
                'color' => 'danger',
                'icon' => 'fa-times-circle'
            ];
        }
        
        // Privacy protection for emails and phones
        if ($donor['email']) {
            $emailParts = explode('@', $donor['email']);
            $donor['email_masked'] = substr($emailParts[0], 0, 3) . '***@' . $emailParts[1];
        }
        
        if ($donor['phone']) {
            $donor['phone_masked'] = substr($donor['phone'], 0, 3) . '***' . substr($donor['phone'], -3);
        }
        
        // Format join date
        if ($donor['created_at']) {
            $donor['member_since'] = date('M Y', strtotime($donor['created_at']));
        }
    }
    
    // Calculate summary statistics
    $stats = [
        'total_found' => count($donors),
        'eligible_count' => count(array_filter($donors, function($d) { return $d['eligible_to_donate']; })),
        'blood_types' => array_count_values(array_column($donors, 'blood_type')),
        'search_query' => $query,
        'blood_type_filter' => $bloodType,
        'limit' => $limit
    ];
    
    // Clean output buffer and send JSON
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => $donors,
        'stats' => $stats,
        'message' => 'Donor search completed successfully'
    ]);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Service temporarily unavailable',
        'error' => 'Unable to search donors',
        'data' => []
    ]);
    exit;
}
?>