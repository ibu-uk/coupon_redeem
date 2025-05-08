<?php
// Include database connection
require_once 'config/database.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Delete All Services</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        h2 {
            color: #333;
            margin-top: 30px;
        }
        .btn {
            display: inline-block;
            padding: 10px 15px;
            background-color: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .btn:hover {
            background-color: #c82333;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-warning {
            color: #856404;
            background-color: #fff3cd;
            border-color: #ffeeba;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
    </style>
</head>
<body>
    <h1>Delete All Services</h1>";

// Check if confirmation is given
if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    try {
        // Check if services are referenced in redemption_logs
        $query = "SELECT COUNT(*) as count FROM redemption_logs";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            echo "<div class='alert alert-danger'>";
            echo "<h3>Cannot Delete Services</h3>";
            echo "<p>There are " . $result['count'] . " redemption logs that reference services.</p>";
            echo "<p>You must first delete all redemption logs before deleting services.</p>";
            echo "</div>";
            
            echo "<h3>Options:</h3>";
            echo "<a href='delete_all_services.php?confirm=yes&delete_logs=yes' class='btn' onclick=\"return confirm('WARNING: This will delete ALL redemption logs and ALL services. This action cannot be undone. Are you sure?');\">Delete All Redemption Logs and Services</a>";
            echo " <a href='admin/manage_services.php' class='btn btn-secondary'>Cancel</a>";
        } else {
            // No redemption logs, safe to delete services
            
            // Disable foreign key checks
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Truncate services table
            $db->exec("TRUNCATE TABLE services");
            
            // Reset auto increment
            $db->exec("ALTER TABLE services AUTO_INCREMENT = 1");
            
            // Re-enable foreign key checks
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            echo "<div class='alert alert-success'>";
            echo "<h3>Success!</h3>";
            echo "<p>All services have been deleted.</p>";
            echo "<p>The auto-increment counter has been reset to 1.</p>";
            echo "<p>You can now add new services starting with ID 1.</p>";
            echo "</div>";
            
            echo "<a href='admin/manage_services.php' class='btn btn-secondary'>Go to Manage Services</a>";
        }
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>";
        echo "<h3>Error</h3>";
        echo "<p>An error occurred: " . $e->getMessage() . "</p>";
        echo "</div>";
        
        echo "<a href='admin/manage_services.php' class='btn btn-secondary'>Go to Manage Services</a>";
    }
} else if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes' && isset($_GET['delete_logs']) && $_GET['delete_logs'] == 'yes') {
    try {
        // Disable foreign key checks
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // Truncate redemption_logs table
        $db->exec("TRUNCATE TABLE redemption_logs");
        
        // Truncate services table
        $db->exec("TRUNCATE TABLE services");
        
        // Reset auto increment
        $db->exec("ALTER TABLE services AUTO_INCREMENT = 1");
        
        // Re-enable foreign key checks
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        echo "<div class='alert alert-success'>";
        echo "<h3>Success!</h3>";
        echo "<p>All redemption logs and services have been deleted.</p>";
        echo "<p>The auto-increment counter has been reset to 1.</p>";
        echo "<p>You can now add new services starting with ID 1.</p>";
        echo "</div>";
        
        echo "<a href='admin/manage_services.php' class='btn btn-secondary'>Go to Manage Services</a>";
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>";
        echo "<h3>Error</h3>";
        echo "<p>An error occurred: " . $e->getMessage() . "</p>";
        echo "</div>";
        
        echo "<a href='admin/manage_services.php' class='btn btn-secondary'>Go to Manage Services</a>";
    }
} else {
    // Show warning and confirmation button
    echo "<div class='alert alert-warning'>";
    echo "<h3>Warning!</h3>";
    echo "<p>You are about to delete ALL services from the database.</p>";
    echo "<p>This action cannot be undone.</p>";
    echo "<p>If services are referenced in redemption logs, you will be given additional options.</p>";
    echo "</div>";
    
    echo "<a href='delete_all_services.php?confirm=yes' class='btn' onclick=\"return confirm('Are you sure you want to delete all services? This action cannot be undone.');\">Delete All Services</a>";
    echo " <a href='admin/manage_services.php' class='btn btn-secondary'>Cancel</a>";
}

echo "</body>
</html>";
?>
