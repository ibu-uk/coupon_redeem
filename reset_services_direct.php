<?php
// Include database connection
require_once 'config/database.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

try {
    // Disable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Delete all from redemption_logs
    $db->exec("DELETE FROM redemption_logs");
    
    // Delete all from services
    $db->exec("DELETE FROM services");
    
    // Reset auto increment for services
    $db->exec("ALTER TABLE services AUTO_INCREMENT = 1");
    
    // Re-enable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; margin: 20px; border-radius: 5px;'>";
    echo "<h3>Success!</h3>";
    echo "<p>All redemption logs and services have been deleted.</p>";
    echo "<p>The service ID counter has been reset to 1.</p>";
    echo "<p>You can now add new services starting with ID 1.</p>";
    echo "<p><a href='admin/manage_services.php' style='color: #155724; font-weight: bold;'>Go to Manage Services</a></p>";
    echo "</div>";
} catch (Exception $e) {
    // Re-enable foreign key checks in case of error
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px;'>";
    echo "<h3>Error</h3>";
    echo "<p>An error occurred: " . $e->getMessage() . "</p>";
    echo "<p><a href='admin/manage_services.php' style='color: #721c24; font-weight: bold;'>Go to Manage Services</a></p>";
    echo "</div>";
}
?>
