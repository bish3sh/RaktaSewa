<?php
// setup_database.php - One-click database setup
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Connect to MySQL without specifying database
    $pdo = new PDO("mysql:host=localhost", "root", "");
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS bloodbank_db");
    
    // Now connect to bloodbank_db
    $pdo = new PDO("mysql:host=localhost;dbname=bloodbank_db", "root", "");
    
    // Check if appointments table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'appointments'");
    $appointmentsExists = $stmt->rowCount() > 0;
    
    if (!$appointmentsExists) {
        // Create appointments table
        $createAppointments = "
        CREATE TABLE IF NOT EXISTS appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            donor_id INT NOT NULL,
            hospital_id INT NOT NULL,
            appointment_date DATE NOT NULL,
            appointment_time TIME NOT NULL,
            blood_type VARCHAR(5) NOT NULL,
            status ENUM('scheduled', 'confirmed', 'completed', 'cancelled') DEFAULT 'scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
            INDEX idx_donor_id (donor_id),
            INDEX idx_hospital_id (hospital_id),
            INDEX idx_appointment_date (appointment_date),
            INDEX idx_status (status)
        )";
        
        $pdo->exec($createAppointments);
    }
    
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $usersExists = $stmt->rowCount() > 0;
    
    if (!$usersExists) {
        // Create basic users table
        $createUsers = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            blood_type VARCHAR(5),
            phone VARCHAR(15),
            role ENUM('donor', 'hospital', 'admin') DEFAULT 'donor',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE
        )";
        
        $pdo->exec($createUsers);
        
        // No default test data - users will register through the application
    }
    
    // Check if hospitals table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'hospitals'");
    $hospitalsExists = $stmt->rowCount() > 0;
    
    if (!$hospitalsExists) {
        // Create basic hospitals table
        $createHospitals = "
        CREATE TABLE IF NOT EXISTS hospitals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            hospital_name VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            contact_person VARCHAR(100),
            phone VARCHAR(15),
            address TEXT,
            city VARCHAR(100),
            state VARCHAR(100),
            pincode VARCHAR(10),
            license_number VARCHAR(100),
            is_approved BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $pdo->exec($createHospitals);
        
        // No default test data - hospitals will register through the application
    }
    
    // No sample appointments are created - they will be scheduled through the application
    
    // Final verification
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM appointments");
    $finalAppointmentCount = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'donor'");
    $donorCount = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM hospitals WHERE is_approved = 1");
    $hospitalCount = $stmt->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'message' => 'Database setup completed successfully!',
        'tables_created' => [
            'appointments' => !$appointmentsExists ? 'created' : 'already_exists',
            'users' => !$usersExists ? 'created' : 'already_exists',
            'hospitals' => !$hospitalsExists ? 'created' : 'already_exists'
        ],
        'data_summary' => [
            'appointments' => $finalAppointmentCount,
            'donors' => $donorCount,
            'hospitals' => $hospitalCount
        ],
        'ready_for_use' => true
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'recommendations' => [
            'Check if XAMPP MySQL service is running',
            'Verify MySQL port 3306 is available',
            'Ensure no other MySQL instances are running'
        ]
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Setup error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>