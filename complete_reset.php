<?php
// Include configuration files
require_once 'config/config.php';
require_once 'config/database.php';

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Function to execute SQL with error handling
function executeSql($conn, $sql, $message) {
    try {
        $conn->exec($sql);
        echo "✓ $message<br>";
        return true;
    } catch (PDOException $e) {
        echo "✗ Error in $message: " . $e->getMessage() . "<br>";
        return false;
    }
}

// Start transaction
$conn->beginTransaction();

try {
    // Disable foreign key checks
    executeSql($conn, "SET FOREIGN_KEY_CHECKS = 0", "Disabled foreign key checks");
    
    // Get all tables in the database
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Truncate all tables except coupon_types
    foreach ($tables as $table) {
        if ($table != 'coupon_types') {
            executeSql($conn, "TRUNCATE TABLE $table", "Truncated table: $table");
        }
    }
    
    // Create admin user
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $adminSql = "INSERT INTO users (username, password, full_name, role, created_at) 
                VALUES ('admin', '$adminPassword', 'System Administrator', 'admin', NOW())";
    executeSql($conn, $adminSql, "Created admin user");
    
    // Create default services
    $services = [
        ['name' => 'Medical Consultation', 'description' => 'General medical consultation'],
        ['name' => 'Dental Service', 'description' => 'Basic dental checkup and cleaning'],
        ['name' => 'Laboratory Test', 'description' => 'Basic blood work and tests'],
        ['name' => 'Physiotherapy', 'description' => 'Physical therapy session'],
        ['name' => 'Vaccination', 'description' => 'Standard vaccination service']
    ];
    
    $serviceStmt = $conn->prepare("INSERT INTO services (name, description) VALUES (?, ?)");
    foreach ($services as $service) {
        $serviceStmt->execute([$service['name'], $service['description']]);
    }
    echo "✓ Added default services<br>";
    
    // Create pre-defined coupons (10 of each type)
    $couponTypes = [
        ['id' => 1, 'name' => 'Black', 'value' => 700],
        ['id' => 2, 'name' => 'Gold', 'value' => 500],
        ['id' => 3, 'name' => 'Silver', 'value' => 300]
    ];
    
    $couponStmt = $conn->prepare("INSERT INTO coupons (coupon_type_id, coupon_number, initial_balance, current_balance, status) VALUES (?, ?, ?, ?, 'available')");
    
    foreach ($couponTypes as $type) {
        for ($i = 1; $i <= 10; $i++) {
            $couponNumber = $type['name'] . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);
            $couponStmt->execute([$type['id'], $couponNumber, $type['value'], $type['value']]);
        }
        echo "✓ Created 10 {$type['name']} coupons<br>";
    }
    
    // Re-enable foreign key checks
    executeSql($conn, "SET FOREIGN_KEY_CHECKS = 1", "Re-enabled foreign key checks");
    
    // Commit transaction
    $conn->commit();
    
    echo "<div style='margin-top: 20px; padding: 15px; background-color: #d4edda; color: #155724; border-radius: 5px;'>";
    echo "<h3>✅ System Reset Complete</h3>";
    echo "<p>All data has been completely reset. The system is now ready for fresh use.</p>";
    echo "<p>Admin login: username: <strong>admin</strong>, password: <strong>admin123</strong></p>";
    echo "<a href='index.php' style='display: inline-block; margin-top: 15px; padding: 8px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Go to Login</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $conn->rollBack();
    echo "<div style='margin-top: 20px; padding: 15px; background-color: #f8d7da; color: #721c24; border-radius: 5px;'>";
    echo "<h3>❌ Error During Reset</h3>";
    echo "<p>An error occurred while resetting the system: " . $e->getMessage() . "</p>";
    echo "<a href='index.php' style='display: inline-block; margin-top: 15px; padding: 8px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Return to Login</a>";
    echo "</div>";
}
?>
