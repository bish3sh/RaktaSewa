<?php
require_once 'php/db_connect.php';

try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT * FROM hospitals WHERE hospital_name LIKE '%Rai%' LIMIT 1");
    $hospital = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($hospital) {
        echo "Hospital Data:\n";
        echo "ID: " . $hospital['id'] . "\n";
        echo "Name: " . $hospital['hospital_name'] . "\n";
        echo "Address: " . $hospital['address'] . "\n";
        echo "City: " . $hospital['city'] . "\n";
        echo "Contact Phone: '" . $hospital['contact_phone'] . "'\n";
        echo "Contact Email: '" . $hospital['contact_email'] . "'\n";
        echo "Phone: '" . $hospital['phone'] . "'\n";
        echo "Email: '" . $hospital['email'] . "'\n";
        echo "\nAll fields:\n";
        print_r($hospital);
    } else {
        echo "Hospital not found";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
