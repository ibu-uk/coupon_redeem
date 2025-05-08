<?php
// Include configuration and database files
require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Read the SQL file
$sql = file_get_contents('database/update_schema.sql');

// Execute the SQL statements
try {
    // Split the SQL file into individual statements
    $statements = explode(';', $sql);
    
    // Execute each statement
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $db->exec($statement);
        }
    }
    
    echo '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9;">';
    echo '<h2 style="color: #4CAF50;">Database Updated Successfully!</h2>';
    echo '<p>The database schema has been updated with the following changes:</p>';
    echo '<ul>';
    echo '<li>Added <code>created_by_admin_id</code> column to the users table</li>';
    echo '<li>Added <code>created_by_admin_id</code> column to the redemption_logs table</li>';
    echo '<li>Set the admin user as self-created</li>';
    echo '</ul>';
    echo '<p>These changes will allow the system to track which users were manually created by an admin versus automatically generated during coupon assignment.</p>';
    echo '<p><a href="admin/manage_users.php" style="display: inline-block; background-color: #4CAF50; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;">Go to User Management</a></p>';
    echo '</div>';
} catch (PDOException $e) {
    echo '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #f44336; border-radius: 5px; background-color: #ffebee;">';
    echo '<h2 style="color: #f44336;">Database Update Failed</h2>';
    echo '<p>Error: ' . $e->getMessage() . '</p>';
    echo '<p>Please contact your system administrator for assistance.</p>';
    echo '</div>';
}
?>
