<?php
// Include configuration and database
require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Coupon code to check
$couponCode = "BLACK-1";

// Query to get coupon details
$query = "SELECT c.*, ct.name as coupon_type_name, ct.value as coupon_type_value,
          b.full_name as buyer_name, b.civil_id as buyer_civil_id, 
          b.mobile_number as buyer_mobile, b.file_number as buyer_file_number
          FROM coupons c
          LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
          LEFT JOIN users b ON c.buyer_id = b.id
          WHERE c.code = ?";

$stmt = $db->prepare($query);
$stmt->bindParam(1, $couponCode);
$stmt->execute();
$coupon = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h1>Coupon Check Results</h1>";

if ($coupon) {
    echo "<h2>Coupon Found in Database!</h2>";
    echo "<pre>";
    echo "ID: " . $coupon['id'] . "\n";
    echo "Code: " . $coupon['code'] . "\n";
    echo "Type: " . $coupon['coupon_type_name'] . "\n";
    echo "Value: " . $coupon['coupon_type_value'] . "\n";
    echo "Status: " . $coupon['status'] . " (exact case as stored in database)\n";
    echo "Initial Balance: " . $coupon['initial_balance'] . "\n";
    echo "Current Balance: " . $coupon['current_balance'] . "\n";
    echo "Buyer: " . $coupon['buyer_name'] . "\n";
    echo "Buyer Civil ID: " . $coupon['buyer_civil_id'] . "\n";
    echo "Buyer Mobile: " . $coupon['buyer_mobile'] . "\n";
    echo "</pre>";
    
    echo "<h3>Redemption Eligibility Check:</h3>";
    echo "<pre>";
    echo "Status check (must be 'assigned'): " . (strtolower($coupon['status']) == 'assigned' ? 'PASS' : 'FAIL') . "\n";
    echo "Balance check (must be > 0): " . ($coupon['current_balance'] > 0 ? 'PASS' : 'FAIL') . "\n";
    echo "</pre>";
    
    if (strtolower($coupon['status']) == 'assigned' && $coupon['current_balance'] > 0) {
        echo "<div style='color: green; font-weight: bold;'>This coupon SHOULD be valid for redemption!</div>";
    } else {
        echo "<div style='color: red; font-weight: bold;'>This coupon is NOT valid for redemption.</div>";
        
        if (strtolower($coupon['status']) != 'assigned') {
            echo "<p>The status is not 'assigned'. Current status: '" . $coupon['status'] . "'</p>";
        }
        
        if ($coupon['current_balance'] <= 0) {
            echo "<p>The current balance is zero or negative: " . $coupon['current_balance'] . "</p>";
        }
    }
    
    // Check if the coupon is actually in the database with the exact case
    $exactQuery = "SELECT COUNT(*) as count FROM coupons WHERE code = ? AND status = 'assigned'";
    $exactStmt = $db->prepare($exactQuery);
    $exactStmt->bindParam(1, $couponCode);
    $exactStmt->execute();
    $exactResult = $exactStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>Exact Case Query Check:</h3>";
    echo "<pre>";
    echo "Exact match count (code='$couponCode' AND status='assigned'): " . $exactResult['count'] . "\n";
    echo "</pre>";
    
} else {
    echo "<h2>Coupon NOT Found in Database!</h2>";
    echo "<p>The coupon with code '$couponCode' does not exist in the database.</p>";
    
    // Check if any similar coupons exist
    $similarQuery = "SELECT code FROM coupons WHERE code LIKE ?";
    $similarStmt = $db->prepare($similarQuery);
    $similarParam = "%BLACK%";
    $similarStmt->bindParam(1, $similarParam);
    $similarStmt->execute();
    
    echo "<h3>Similar Coupons in Database:</h3>";
    echo "<ul>";
    $found = false;
    while ($row = $similarStmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<li>" . $row['code'] . "</li>";
        $found = true;
    }
    if (!$found) {
        echo "<li>No similar coupons found</li>";
    }
    echo "</ul>";
}
?>
