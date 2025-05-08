<?php
// Include database connection
require_once 'config/database.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Start transaction
$db->beginTransaction();

try {
    // Check for duplicate service names
    $query = "SELECT name, COUNT(*) as count FROM services GROUP BY name HAVING count > 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $duplicate_found = false;
    if (count($duplicates) > 0) {
        echo "<h2>Found Duplicate Services</h2>";
        echo "<p>The following service names have duplicates:</p>";
        echo "<ul>";
        foreach ($duplicates as $dup) {
            echo "<li>" . htmlspecialchars($dup['name']) . " (appears " . $dup['count'] . " times)</li>";
            $duplicate_found = true;
        }
        echo "</ul>";
        
        // Remove duplicates
        echo "<h3>Removing Duplicates...</h3>";
        foreach ($duplicates as $dup) {
            $name = $dup['name'];
            // Get all services with this name
            $query = "SELECT id FROM services WHERE name = ? ORDER BY id ASC";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $name);
            $stmt->execute();
            $duplicate_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Keep the first one, delete the rest
            $first_id = array_shift($duplicate_ids);
            echo "<p>Keeping service ID $first_id for '" . htmlspecialchars($name) . "' and removing " . count($duplicate_ids) . " duplicates.</p>";
            
            if (count($duplicate_ids) > 0) {
                $placeholders = implode(',', array_fill(0, count($duplicate_ids), '?'));
                $query = "DELETE FROM services WHERE id IN ($placeholders)";
                $stmt = $db->prepare($query);
                foreach ($duplicate_ids as $index => $id) {
                    $stmt->bindValue($index + 1, $id);
                }
                $stmt->execute();
            }
        }
    } else {
        echo "<h2>No Duplicate Services Found</h2>";
        echo "<p>All service names are unique.</p>";
    }
    
    // Get all services ordered by name
    $query = "SELECT * FROM services ORDER BY name ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a temporary table
    $db->exec("CREATE TEMPORARY TABLE temp_services LIKE services");
    
    // Reset auto increment
    $db->exec("ALTER TABLE services AUTO_INCREMENT = 1");
    
    // Clear the services table
    $db->exec("DELETE FROM services");
    
    // Insert services with new IDs
    $new_id = 1;
    echo "<h2>Reassigning Service IDs</h2>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Old ID</th><th>New ID</th><th>Service Name</th></tr>";
    
    foreach ($services as $service) {
        $old_id = $service['id'];
        
        $query = "INSERT INTO services (name, description, default_price) VALUES (?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $service['name']);
        $stmt->bindParam(2, $service['description']);
        $stmt->bindParam(3, $service['default_price']);
        $stmt->execute();
        
        echo "<tr>";
        echo "<td>" . $old_id . "</td>";
        echo "<td>" . $new_id . "</td>";
        echo "<td>" . htmlspecialchars($service['name']) . "</td>";
        echo "</tr>";
        
        $new_id++;
    }
    
    echo "</table>";
    
    // Commit transaction
    $db->commit();
    
    echo "<h2>Success!</h2>";
    echo "<p>Service IDs have been reset to start from 1.</p>";
    if ($duplicate_found) {
        echo "<p>Duplicate services have been removed.</p>";
    }
    echo "<p><a href='admin/manage_services.php' class='btn btn-primary'>Go to Manage Services</a></p>";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollBack();
    echo "<h2>Error</h2>";
    echo "<p>An error occurred: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
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
    </style>
</head>
<body>
    <!-- Content is generated by PHP above -->
</body>
</html>
