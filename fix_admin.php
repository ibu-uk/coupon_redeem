<?php
// Include database configuration
require_once 'config/database.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// New admin password
$password = 'admin123';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Update admin user directly
$query = "UPDATE users SET password = :password WHERE username = 'admin'";
$stmt = $db->prepare($query);
$stmt->bindParam(':password', $hashedPassword);

// Execute query and check result
if($stmt->execute()) {
    echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px;'>";
    echo "<h3>Admin Password Reset Successfully!</h3>";
    echo "<p>The admin password has been reset to: <strong>admin123</strong></p>";
    echo "<p>You can now <a href='login.php'>login here</a> with:</p>";
    echo "<ul>";
    echo "<li>Username: <strong>admin</strong></li>";
    echo "<li>Password: <strong>admin123</strong></li>";
    echo "</ul>";
    echo "</div>";
    
    // Also check if the admin user exists, if not create it
    $checkQuery = "SELECT id FROM users WHERE username = 'admin'";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute();
    
    if($checkStmt->rowCount() == 0) {
        // Admin user doesn't exist, create it
        $createQuery = "INSERT INTO users (username, password, email, full_name, role) 
                        VALUES ('admin', :password, 'admin@example.com', 'System Administrator', 'admin')";
        $createStmt = $db->prepare($createQuery);
        $createStmt->bindParam(':password', $hashedPassword);
        
        if($createStmt->execute()) {
            echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px;'>";
            echo "<h3>Admin User Created!</h3>";
            echo "<p>A new admin user has been created since one didn't exist.</p>";
            echo "</div>";
        }
    }
} else {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px;'>";
    echo "<h3>Error!</h3>";
    echo "<p>Failed to reset admin password. Please check if the database is properly set up.</p>";
    
    // Check if the users table exists
    try {
        $tableCheckQuery = "SHOW TABLES LIKE 'users'";
        $tableCheckStmt = $db->prepare($tableCheckQuery);
        $tableCheckStmt->execute();
        
        if($tableCheckStmt->rowCount() == 0) {
            echo "<p>The 'users' table doesn't exist. Please import the database schema first.</p>";
            echo "<p>You can import it from: <code>database/coupon_db.sql</code></p>";
        }
    } catch(PDOException $e) {
        echo "<p>Database error: " . $e->getMessage() . "</p>";
    }
    
    echo "</div>";
}
?>
