<?php
// Include database configuration
require_once 'config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

try {
    // Start transaction
    $db->beginTransaction();
    
    // Check current role column definition
    $query = "SHOW COLUMNS FROM users LIKE 'role'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Current role column definition: " . $column['Type'] . "<br>";
    
    // Modify the role column to include 'staff'
    $query = "ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'buyer', 'recipient', 'staff')";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    echo "Role column updated successfully to include 'staff' role.<br>";
    
    // Commit transaction
    $db->commit();
    
    echo "Database update completed successfully.";
} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollBack();
    echo "Error: " . $e->getMessage();
}
?>
