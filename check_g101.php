<?php
// Include database connection
require_once 'config/database.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Check G101 coupon status
echo "<h2>G101 Coupon Status Check</h2>";

// Check coupon table
$query = "SELECT * FROM coupons WHERE code = 'G101'";
$stmt = $db->prepare($query);
$stmt->execute();
$coupon = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Coupon Table Data:</h3>";
echo "<pre>";
print_r($coupon);
echo "</pre>";

// Check redemption logs
$query = "SELECT * FROM redemption_logs WHERE coupon_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $coupon['id']);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Redemption Logs:</h3>";
echo "<pre>";
print_r($logs);
echo "</pre>";

// Check if there's a recipient assigned
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $coupon['recipient_id']);
$stmt->execute();
$recipient = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Assigned Recipient:</h3>";
echo "<pre>";
print_r($recipient);
echo "</pre>";
?>
