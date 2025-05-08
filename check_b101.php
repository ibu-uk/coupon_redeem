<?php
// Include configuration and database
require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Coupon code to check
$couponCode = "B101";

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

echo "<h1>B101 Coupon Check Results</h1>";

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
    
    // Check redemption logs for this coupon
    echo "<h3>Redemption Logs:</h3>";
    $logsQuery = "SELECT * FROM redemption_logs WHERE coupon_id = ? ORDER BY created_at DESC";
    $logsStmt = $db->prepare($logsQuery);
    $logsStmt->bindParam(1, $coupon['id']);
    $logsStmt->execute();
    
    $logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($logs) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Service</th><th>Amount</th><th>Recipient</th><th>Date</th></tr>";
        
        foreach ($logs as $log) {
            echo "<tr>";
            echo "<td>" . $log['id'] . "</td>";
            echo "<td>" . $log['service_name'] . "</td>";
            echo "<td>" . $log['amount'] . " KD</td>";
            echo "<td>" . $log['recipient_name'] . "</td>";
            echo "<td>" . $log['created_at'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No redemption logs found for this coupon.</p>";
    }
    
    // Check if there's a difference between initial and current balance
    if ($coupon['initial_balance'] != $coupon['current_balance']) {
        echo "<h3>Balance Discrepancy:</h3>";
        echo "<p>There is a difference of " . ($coupon['initial_balance'] - $coupon['current_balance']) . " KD between initial and current balance.</p>";
        
        // Sum up all redemptions
        $sumQuery = "SELECT SUM(amount) as total FROM redemption_logs WHERE coupon_id = ?";
        $sumStmt = $db->prepare($sumQuery);
        $sumStmt->bindParam(1, $coupon['id']);
        $sumStmt->execute();
        $sum = $sumStmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<p>Total redemptions: " . ($sum['total'] ?? 0) . " KD</p>";
        
        if (($sum['total'] ?? 0) != ($coupon['initial_balance'] - $coupon['current_balance'])) {
            echo "<p style='color: red;'>Warning: The sum of redemptions does not match the balance difference!</p>";
        }
    }
    
} else {
    echo "<h2>Coupon NOT Found in Database!</h2>";
    echo "<p>The coupon with code '$couponCode' does not exist in the database.</p>";
    
    // Check if any similar coupons exist
    $similarQuery = "SELECT code FROM coupons WHERE code LIKE ?";
    $similarStmt = $db->prepare($similarQuery);
    $similarParam = "B%";
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

// Check if there are any redemption logs in the system at all
echo "<h3>Recent Redemption Logs in System:</h3>";
$recentLogsQuery = "SELECT * FROM redemption_logs ORDER BY created_at DESC LIMIT 5";
$recentLogsStmt = $db->prepare($recentLogsQuery);
$recentLogsStmt->execute();
$recentLogs = $recentLogsStmt->fetchAll(PDO::FETCH_ASSOC);

if (count($recentLogs) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Coupon ID</th><th>Service</th><th>Amount</th><th>Recipient</th><th>Date</th></tr>";
    
    foreach ($recentLogs as $log) {
        echo "<tr>";
        echo "<td>" . $log['id'] . "</td>";
        echo "<td>" . $log['coupon_id'] . "</td>";
        echo "<td>" . $log['service_name'] . "</td>";
        echo "<td>" . $log['amount'] . " KD</td>";
        echo "<td>" . $log['recipient_name'] . "</td>";
        echo "<td>" . $log['created_at'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No recent redemption logs found in the system.</p>";
}
?>
