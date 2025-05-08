<?php
// This script ensures the admin user exists with the correct password
// Include database configuration
require_once 'config/database.php';

// Function to ensure admin exists with correct password
function ensureAdminUser() {
    // Get database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if admin user exists
    $query = "SELECT id, password FROM users WHERE username = 'admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    // Default admin credentials
    $adminUsername = 'admin';
    $adminPassword = 'admin123';
    $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
    
    if($stmt->rowCount() > 0) {
        // Admin exists, verify password is correct
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $adminId = $row['id'];
        $storedPassword = $row['password'];
        
        // Test if the stored password is correct
        if(!password_verify($adminPassword, $storedPassword)) {
            // Password is incorrect or hash is invalid, update it
            $updateQuery = "UPDATE users SET password = :password WHERE id = :id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':password', $hashedPassword);
            $updateStmt->bindParam(':id', $adminId);
            $updateStmt->execute();
            
            // Log the password update
            error_log("Admin password was reset to default at " . date('Y-m-d H:i:s'));
        }
    } else {
        // Admin doesn't exist, create it
        $createQuery = "INSERT INTO users (username, password, email, full_name, role) 
                        VALUES ('admin', :password, 'admin@example.com', 'System Administrator', 'admin')";
        $createStmt = $db->prepare($createQuery);
        $createStmt->bindParam(':password', $hashedPassword);
        $createStmt->execute();
        
        // Log the admin creation
        error_log("Admin user was created at " . date('Y-m-d H:i:s'));
    }
    
    return true;
}

// Run the function to ensure admin exists
ensureAdminUser();

// If this script is accessed directly, show a message
if(basename($_SERVER['PHP_SELF']) === 'admin_login_check.php') {
    echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px;'>";
    echo "<h3>Admin Account Verified</h3>";
    echo "<p>The admin account has been verified and is ready to use.</p>";
    echo "<p>You can now <a href='login.php'>login here</a> with:</p>";
    echo "<ul>";
    echo "<li>Username: <strong>admin</strong></li>";
    echo "<li>Password: <strong>admin123</strong></li>";
    echo "</ul>";
    echo "</div>";
}
?>
