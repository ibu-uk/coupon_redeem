<?php
// Include configuration files
require_once 'config/config.php';
require_once 'config/database.php';

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Start transaction
$conn->beginTransaction();

try {
    // Disable foreign key checks temporarily
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // 1. Reset redemption_logs table - TRUNCATE is faster and resets auto-increment
    $conn->exec("TRUNCATE TABLE redemption_logs");
    echo "✓ Redemption logs reset successfully<br>";
    
    // 2. Reset coupons to initial state
    $conn->exec("UPDATE coupons SET 
                 status = 'available', 
                 buyer_id = NULL,
                 recipient_id = NULL,
                 current_balance = initial_balance,
                 assigned_date = NULL");
    echo "✓ Coupons reset successfully<br>";
    
    // 3. Delete all users except admin
    $conn->exec("DELETE FROM users WHERE role != 'admin'");
    echo "✓ Users reset successfully (admin preserved)<br>";
    
    // 4. Reset recipients
    $conn->exec("TRUNCATE TABLE recipients");
    echo "✓ Recipients reset successfully<br>";
    
    // 5. Reset services
    $conn->exec("TRUNCATE TABLE services");
    echo "✓ Services reset successfully<br>";
    
    // 6. Re-add default services
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
    echo "✓ Default services added successfully<br>";
    
    // Re-enable foreign key checks
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Commit transaction
    $conn->commit();
    
    echo "<div style='margin-top: 20px; padding: 15px; background-color: #d4edda; color: #155724; border-radius: 5px;'>";
    echo "<h3>✅ System Reset Complete</h3>";
    echo "<p>All data has been reset. The system is now ready for fresh use.</p>";
    echo "<p>The admin account has been preserved.</p>";
    echo "<a href='index.php' style='display: inline-block; margin-top: 15px; padding: 8px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Return to Login</a>";
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
