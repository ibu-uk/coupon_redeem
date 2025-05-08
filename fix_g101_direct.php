<?php
// Include database connection
require_once 'config/database.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Update G101 coupon status directly
$query = "UPDATE coupons SET status = 'assigned' WHERE code = 'G101'";
$stmt = $db->prepare($query);

if($stmt->execute()) {
    echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; margin: 20px; border-radius: 5px;'>";
    echo "<h3>Success!</h3>";
    echo "<p>G101 coupon status has been updated to 'assigned'.</p>";
    echo "<p><a href='admin/manage_coupons.php'>Go to Manage Coupons</a></p>";
    echo "</div>";
} else {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px;'>";
    echo "<h3>Error</h3>";
    echo "<p>Failed to update G101 coupon status.</p>";
    echo "</div>";
}
?>
