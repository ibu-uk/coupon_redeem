<?php
// Include configuration and database
require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Check if Black coupon type exists and get its value
$query = "SELECT id, value FROM coupon_types WHERE name = 'Black'";
$stmt = $db->prepare($query);
$stmt->execute();
$couponType = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$couponType) {
    die("Black coupon type not found in database.");
}

echo "<h1>Coupon Value Fix</h1>";
echo "<p>Black coupon type value: " . $couponType['value'] . " KD</p>";

// Get all coupons of Black type
$query = "SELECT id, code, initial_balance, current_balance, status FROM coupons WHERE coupon_type_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $couponType['id']);
$stmt->execute();

echo "<h2>Current Coupon Values:</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Code</th><th>Initial Balance</th><th>Current Balance</th><th>Status</th></tr>";

$coupons = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $coupons[] = $row;
    echo "<tr>";
    echo "<td>" . $row['code'] . "</td>";
    echo "<td>" . $row['initial_balance'] . " KD</td>";
    echo "<td>" . $row['current_balance'] . " KD</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Fix the coupon values
if (isset($_POST['fix_values'])) {
    $correctValue = $couponType['value'];
    
    // Update all available coupons to match the coupon type value
    $query = "UPDATE coupons 
              SET initial_balance = ?, current_balance = ?
              WHERE coupon_type_id = ? AND status = 'available'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $correctValue);
    $stmt->bindParam(2, $correctValue);
    $stmt->bindParam(3, $couponType['id']);
    $stmt->execute();
    
    // For assigned coupons, update initial_balance and adjust current_balance proportionally
    $query = "SELECT id, initial_balance, current_balance FROM coupons 
              WHERE coupon_type_id = ? AND status = 'assigned'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $couponType['id']);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $oldInitial = $row['initial_balance'];
        $oldCurrent = $row['current_balance'];
        
        // Calculate new current balance proportionally
        $newCurrent = ($oldCurrent / $oldInitial) * $correctValue;
        
        // Update the coupon
        $updateQuery = "UPDATE coupons SET initial_balance = ?, current_balance = ? WHERE id = ?";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(1, $correctValue);
        $updateStmt->bindParam(2, $newCurrent);
        $updateStmt->bindParam(3, $row['id']);
        $updateStmt->execute();
    }
    
    echo "<p style='color: green; font-weight: bold;'>Coupon values have been fixed!</p>";
    
    // Show updated values
    $query = "SELECT id, code, initial_balance, current_balance, status FROM coupons WHERE coupon_type_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $couponType['id']);
    $stmt->execute();
    
    echo "<h2>Updated Coupon Values:</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Code</th><th>Initial Balance</th><th>Current Balance</th><th>Status</th></tr>";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . $row['code'] . "</td>";
        echo "<td>" . $row['initial_balance'] . " KD</td>";
        echo "<td>" . $row['current_balance'] . " KD</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<form method='post'>";
    echo "<p>Click the button below to fix the coupon values to match the coupon type value (" . $couponType['value'] . " KD):</p>";
    echo "<input type='submit' name='fix_values' value='Fix Coupon Values'>";
    echo "</form>";
}
?>
