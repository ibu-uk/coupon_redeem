<?php
// Include configuration file
require_once 'config/config.php';
require_once 'config/database.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check coupon types
$query = "SELECT * FROM coupon_types";
$stmt = $db->prepare($query);
$stmt->execute();

echo "<h3>Coupon Types:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Name</th><th>Value</th></tr>";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['name']}</td>";
    echo "<td>{$row['value']} KD</td>";
    echo "</tr>";
}
echo "</table>";
?>
