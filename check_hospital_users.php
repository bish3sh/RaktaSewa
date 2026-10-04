<?php
require_once 'php/db_connect.php';

header('Content-Type: text/plain');

try {
    $pdo = getDBConnection();
    
    echo "=== HOSPITAL USERS ===\n\n";
    $stmt = $pdo->query("SELECT id, username, full_name, email, role FROM users WHERE role = 'hospital'");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "No hospital users found\n";
    } else {
        foreach ($users as $u) {
            echo "User ID: {$u['id']}\n";
            echo "Username: {$u['username']}\n";
            echo "Full Name: {$u['full_name']}\n";
            echo "Email: {$u['email']}\n";
            echo "Role: {$u['role']}\n";
            echo "---\n";
        }
    }
    
    echo "\n=== HOSPITALS ===\n\n";
    $stmt = $pdo->query("SELECT id, hospital_name, user_id, is_approved, is_active FROM hospitals");
    $hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($hospitals as $h) {
        echo "Hospital ID: {$h['id']}\n";
        echo "Hospital Name: {$h['hospital_name']}\n";
        echo "User ID: {$h['user_id']}\n";
        echo "Approved: " . ($h['is_approved'] ? 'Yes' : 'No') . "\n";
        echo "Active: " . ($h['is_active'] ? 'Yes' : 'No') . "\n";
        echo "---\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
