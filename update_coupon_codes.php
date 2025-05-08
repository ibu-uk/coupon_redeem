<?php
// Include configuration file
require_once 'config/config.php';
require_once 'config/database.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check if user is logged in and has admin role
if(!isLoggedIn() || !hasRole('admin')) {
    echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>";
    echo "<h2>Access Denied</h2>";
    echo "<p>You must be logged in as an administrator to access this page.</p>";
    echo "<p><a href='login.php' style='display: inline-block; background-color: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>Go to Login</a></p>";
    echo "</div>";
    exit;
}

// Function to update coupon codes
function updateCouponCodes($db) {
    $results = [];
    $errors = [];
    
    // Get all coupon types
    $query = "SELECT id, name FROM coupon_types";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $couponTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($couponTypes as $type) {
        $typeId = $type['id'];
        $typeName = strtoupper(substr($type['name'], 0, 1)); // Get first letter of type (B, G, S)
        
        // Get all coupons for this type
        $query = "SELECT id, code FROM coupons WHERE coupon_type_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $typeId);
        $stmt->execute();
        $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($coupons as $coupon) {
            $oldCode = $coupon['code'];
            $couponId = $coupon['id'];
            
            // Extract the number from the old code (e.g., "BLACK-3" -> "3")
            if (preg_match('/[0-9]+$/', $oldCode, $matches) || preg_match('/\-([0-9]+)$/', $oldCode, $matches)) {
                $number = intval($matches[0]);
                $newNumber = 100 + $number; // Convert to new format (e.g., 3 -> 103)
                $newCode = "{$typeName}{$newNumber}";
                
                // Update the coupon code
                $updateQuery = "UPDATE coupons SET code = ? WHERE id = ?";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(1, $newCode);
                $updateStmt->bindParam(2, $couponId);
                
                if ($updateStmt->execute()) {
                    $results[] = "Updated coupon ID {$couponId} from '{$oldCode}' to '{$newCode}'";
                } else {
                    $errors[] = "Failed to update coupon ID {$couponId} from '{$oldCode}' to '{$newCode}'";
                }
            } else {
                $errors[] = "Could not extract number from coupon code '{$oldCode}' (ID: {$couponId})";
            }
        }
    }
    
    return ['results' => $results, 'errors' => $errors];
}

// Process the update
if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    $updateResults = updateCouponCodes($db);
    $results = $updateResults['results'];
    $errors = $updateResults['errors'];
    
    echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>";
    echo "<h2>Coupon Code Update Results</h2>";
    
    if (!empty($results)) {
        echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
        echo "<h3>Success!</h3>";
        echo "<p>" . count($results) . " coupons updated successfully.</p>";
        echo "<ul>";
        foreach ($results as $result) {
            echo "<li>{$result}</li>";
        }
        echo "</ul>";
        echo "</div>";
    }
    
    if (!empty($errors)) {
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
        echo "<h3>Errors</h3>";
        echo "<p>" . count($errors) . " errors occurred during the update.</p>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li>{$error}</li>";
        }
        echo "</ul>";
        echo "</div>";
    }
    
    echo "<p><a href='admin/manage_coupons.php' style='display: inline-block; background-color: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; margin-top: 20px;'>Go to Manage Coupons</a></p>";
    echo "</div>";
} else {
    // Show confirmation page
    echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>";
    echo "<h2>Update Coupon Codes</h2>";
    echo "<p>This will update all coupon codes to the new format:</p>";
    echo "<ul>";
    echo "<li>BLACK-1 through BLACK-10 will become B101 through B110</li>";
    echo "<li>GOLD-1 through GOLD-10 will become G101 through G110</li>";
    echo "<li>SILVER-1 through SILVER-10 will become S101 through S110</li>";
    echo "</ul>";
    echo "<p><strong>Warning:</strong> This operation cannot be undone. Make sure you have a backup of your database before proceeding.</p>";
    echo "<p><a href='update_coupon_codes.php?confirm=yes' style='display: inline-block; background-color: #dc3545; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Yes, Update Coupon Codes</a>";
    echo "<a href='admin/manage_coupons.php' style='display: inline-block; background-color: #6c757d; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>Cancel</a></p>";
    echo "</div>";
}
?>
