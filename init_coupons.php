<?php
// Include configuration file
require_once 'config/config.php';
require_once 'config/database.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check if coupons already exist
$query = "SELECT COUNT(*) as count FROM coupons";
$stmt = $db->prepare($query);
$stmt->execute();
$couponCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($couponCount > 0) {
    echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>";
    echo "<h2>Coupons Already Exist</h2>";
    echo "<p>There are already {$couponCount} coupons in the database. Do you want to reinitialize them?</p>";
    echo "<p><strong>Warning:</strong> This will reset all coupons to 'available' status and remove any buyer/recipient assignments.</p>";
    
    if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
        // Delete existing coupons
        $query = "DELETE FROM redemption_logs";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $query = "DELETE FROM coupons";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        // Continue with initialization
        initializeCoupons($db);
        
        echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
        echo "<h3>Success!</h3>";
        echo "<p>All coupons have been reinitialized successfully.</p>";
        echo "</div>";
        echo "<p><a href='admin/manage_coupons.php' style='display: inline-block; background-color: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>Go to Manage Coupons</a></p>";
    } else {
        echo "<p><a href='init_coupons.php?confirm=yes' style='display: inline-block; background-color: #dc3545; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Yes, Reinitialize All Coupons</a>";
        echo "<a href='check_coupons.php' style='display: inline-block; background-color: #6c757d; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>Cancel</a></p>";
    }
    echo "</div>";
} else {
    // Initialize coupons
    initializeCoupons($db);
    
    echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>";
    echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px;'>";
    echo "<h2>Success!</h2>";
    echo "<p>Coupons have been initialized successfully.</p>";
    echo "</div>";
    echo "<p><a href='admin/manage_coupons.php' style='display: inline-block; background-color: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; margin-top: 20px;'>Go to Manage Coupons</a></p>";
    echo "</div>";
}

// Function to initialize coupons
function initializeCoupons($db) {
    // Get coupon types
    $query = "SELECT id, name, value FROM coupon_types";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $couponTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create batch coupons for each type
    foreach ($couponTypes as $type) {
        $typeId = $type['id'];
        $typeName = strtoupper(substr($type['name'], 0, 1)); // Get first letter of type (B, G, S)
        $value = $type['value'];
        
        // Create 10 coupons for each type with new naming format (101-110)
        for ($i = 1; $i <= 10; $i++) {
            $couponNumber = 100 + $i; // Start from 101
            $code = "{$typeName}{$couponNumber}";
            
            $query = "INSERT INTO coupons (code, coupon_type_id, initial_balance, current_balance, status) 
                      VALUES (?, ?, ?, ?, 'available')";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $code);
            $stmt->bindParam(2, $typeId);
            $stmt->bindParam(3, $value);
            $stmt->bindParam(4, $value);
            $stmt->execute();
        }
    }
}
?>
