<?php
// Include database connection
require_once 'config/database.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Service IDs</title>
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        .btn {
            display: inline-block;
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-info {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
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
    </style>
</head>
<body>";

// Check if we're in analysis mode or execution mode
$analysis_mode = !isset($_GET['execute']);

if ($analysis_mode) {
    echo "<div class='alert alert-info'>
        <h3>Analysis Mode</h3>
        <p>This page is currently in analysis mode. It will show you what changes would be made without actually making them.</p>
        <p>After reviewing the analysis, click the button at the bottom of the page to execute the changes.</p>
    </div>";
}

// Check for duplicate service names
echo "<h2>Checking for Duplicate Services</h2>";
$query = "SELECT name, COUNT(*) as count FROM services GROUP BY name HAVING count > 1";
$stmt = $db->prepare($query);
$stmt->execute();
$duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$duplicate_found = false;
if (count($duplicates) > 0) {
    echo "<div class='alert alert-warning'>";
    echo "<h3>Found Duplicate Services</h3>";
    echo "<p>The following service names have duplicates:</p>";
    echo "<ul>";
    foreach ($duplicates as $dup) {
        echo "<li>" . htmlspecialchars($dup['name']) . " (appears " . $dup['count'] . " times)</li>";
        $duplicate_found = true;
    }
    echo "</ul>";
    echo "</div>";
    
    // Get details about duplicates
    echo "<h3>Duplicate Services Details</h3>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Description</th><th>Price</th><th>Used in Redemptions</th></tr>";
    
    foreach ($duplicates as $dup) {
        $name = $dup['name'];
        // Get all services with this name
        $query = "SELECT s.*, (SELECT COUNT(*) FROM redemption_logs WHERE service_id = s.id) as usage_count 
                 FROM services s WHERE s.name = ? ORDER BY s.id ASC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $name);
        $stmt->execute();
        $duplicate_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($duplicate_services as $service) {
            echo "<tr>";
            echo "<td>" . $service['id'] . "</td>";
            echo "<td>" . htmlspecialchars($service['name']) . "</td>";
            echo "<td>" . htmlspecialchars($service['description']) . "</td>";
            echo "<td>" . $service['default_price'] . "</td>";
            echo "<td>" . $service['usage_count'] . "</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
} else {
    echo "<div class='alert alert-success'>";
    echo "<p>No duplicate service names found. All service names are unique.</p>";
    echo "</div>";
}

// Get all services ordered by name for ID reassignment
$query = "SELECT s.*, (SELECT COUNT(*) FROM redemption_logs WHERE service_id = s.id) as usage_count 
         FROM services s ORDER BY s.name ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Show current ID mapping and what will be changed
echo "<h2>Service ID Reassignment Plan</h2>";
echo "<p>The following table shows the current service IDs and what they will be changed to:</p>";
echo "<table>";
echo "<tr><th>Current ID</th><th>New ID</th><th>Service Name</th><th>Used in Redemptions</th></tr>";

$new_id = 1;
$id_mapping = [];

foreach ($services as $service) {
    $old_id = $service['id'];
    $id_mapping[$old_id] = $new_id;
    
    echo "<tr>";
    echo "<td>" . $old_id . "</td>";
    echo "<td>" . $new_id . "</td>";
    echo "<td>" . htmlspecialchars($service['name']) . "</td>";
    echo "<td>" . $service['usage_count'] . "</td>";
    echo "</tr>";
    
    $new_id++;
}

echo "</table>";

// If we're in execution mode, perform the changes
if (!$analysis_mode) {
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Create a temporary table to store the ID mapping
        $db->exec("CREATE TEMPORARY TABLE id_mapping (old_id INT, new_id INT)");
        
        // Insert the ID mapping
        $query = "INSERT INTO id_mapping (old_id, new_id) VALUES (?, ?)";
        $stmt = $db->prepare($query);
        
        foreach ($id_mapping as $old_id => $new_id) {
            $stmt->bindParam(1, $old_id);
            $stmt->bindParam(2, $new_id);
            $stmt->execute();
        }
        
        // Update redemption_logs to use temporary IDs (to avoid constraint violations)
        $db->exec("UPDATE redemption_logs SET service_id = service_id + 10000 WHERE service_id IN (SELECT old_id FROM id_mapping)");
        
        // Create a new services table
        $db->exec("CREATE TABLE new_services LIKE services");
        
        // Insert services with new IDs
        foreach ($services as $service) {
            $old_id = $service['id'];
            $new_id = $id_mapping[$old_id];
            
            $query = "INSERT INTO new_services (id, name, description, default_price, created_at, updated_at) 
                     VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $new_id);
            $stmt->bindParam(2, $service['name']);
            $stmt->bindParam(3, $service['description']);
            $stmt->bindParam(4, $service['default_price']);
            $stmt->bindParam(5, $service['created_at']);
            $stmt->bindParam(6, $service['updated_at']);
            $stmt->execute();
        }
        
        // Drop the old services table
        $db->exec("DROP TABLE services");
        
        // Rename the new table
        $db->exec("RENAME TABLE new_services TO services");
        
        // Update the auto_increment
        $db->exec("ALTER TABLE services AUTO_INCREMENT = " . $new_id);
        
        // Update redemption_logs to use new IDs
        $db->exec("UPDATE redemption_logs rl 
                  JOIN id_mapping im ON rl.service_id - 10000 = im.old_id 
                  SET rl.service_id = im.new_id 
                  WHERE rl.service_id > 10000");
        
        // Commit transaction
        $db->commit();
        
        echo "<div class='alert alert-success'>";
        echo "<h3>Success!</h3>";
        echo "<p>Service IDs have been successfully reset to start from 1.</p>";
        echo "<p>All references in redemption_logs have been updated to use the new IDs.</p>";
        echo "</div>";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        echo "<div class='alert alert-danger'>";
        echo "<h3>Error</h3>";
        echo "<p>An error occurred: " . $e->getMessage() . "</p>";
        echo "</div>";
    }
    
    echo "<p><a href='admin/manage_services.php' class='btn btn-primary'>Go to Manage Services</a></p>";
} else {
    // Show execution button
    echo "<form method='get' action='fix_services_ids_safe.php'>";
    echo "<input type='hidden' name='execute' value='1'>";
    echo "<button type='submit' class='btn btn-primary'>Execute ID Reset</button>";
    echo " <a href='admin/manage_services.php' class='btn' style='background-color: #6c757d; color: white;'>Cancel</a>";
    echo "</form>";
}

echo "</body></html>";
?>
