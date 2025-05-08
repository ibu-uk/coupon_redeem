<?php
// Include configuration file
require_once 'config/config.php';
require_once 'config/database.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Check if G101 exists and update its status
$query = "SELECT id FROM coupons WHERE code = 'G101'";
$stmt = $db->prepare($query);
$stmt->execute();

if($stmt->rowCount() > 0) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $coupon_id = $row['id'];
    
    // Update the coupon status to 'assigned'
    $update_query = "UPDATE coupons SET status = 'assigned' WHERE id = ?";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(1, $coupon_id);
    
    if($update_stmt->execute()) {
        echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; margin: 20px; border-radius: 5px;'>";
        echo "<h3>G101 Status Update</h3>";
        echo "<p>G101 coupon has been successfully updated to 'assigned' status.</p>";
        echo "<p><a href='admin/manage_coupons.php'>Go to Manage Coupons</a></p>";
        echo "</div>";
    } else {
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px;'>";
        echo "<h3>Error</h3>";
        echo "<p>Unable to update G101 status.</p>";
        echo "</div>";
    }
} else {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px;'>";
    echo "<h3>Error</h3>";
    echo "<p>G101 coupon not found in the database.</p>";
    echo "</div>";
}
?>
