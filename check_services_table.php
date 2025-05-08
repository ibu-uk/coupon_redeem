<?php
// Include database connection
require_once 'config/database.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Check services table structure
echo "<h2>Services Table Structure</h2>";
$query = "DESCRIBE services";
$stmt = $db->prepare($query);
$stmt->execute();
echo "<pre>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
echo "</pre>";

// Check current services data
echo "<h2>Current Services Data</h2>";
$query = "SELECT * FROM services ORDER BY id ASC";
$stmt = $db->prepare($query);
$stmt->execute();
echo "<pre>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
echo "</pre>";

// Check for duplicate service names
echo "<h2>Duplicate Service Names Check</h2>";
$query = "SELECT name, COUNT(*) as count FROM services GROUP BY name HAVING count > 1";
$stmt = $db->prepare($query);
$stmt->execute();
$duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($duplicates) > 0) {
    echo "<p>Found duplicate service names:</p>";
    echo "<pre>";
    print_r($duplicates);
    echo "</pre>";
} else {
    echo "<p>No duplicate service names found.</p>";
}
?>
