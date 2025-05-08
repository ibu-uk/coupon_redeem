<?php
// Include configuration file
require_once 'config/config.php';
require_once 'config/database.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check coupon status
$query = "SELECT COUNT(*) as count, status FROM coupons GROUP BY status";
$stmt = $db->prepare($query);
$stmt->execute();

echo "<h3>Coupon Status Summary:</h3>";
echo "<ul>";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<li>{$row['status']}: {$row['count']}</li>";
}
echo "</ul>";

// Check coupon types
$query = "SELECT ct.name, COUNT(*) as count FROM coupons c JOIN coupon_types ct ON c.coupon_type_id = ct.id GROUP BY ct.name";
$stmt = $db->prepare($query);
$stmt->execute();

echo "<h3>Coupon Types Summary:</h3>";
echo "<ul>";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<li>{$row['name']}: {$row['count']}</li>";
}
echo "</ul>";

// Check if there are any available coupons
$query = "SELECT COUNT(*) as count FROM coupons WHERE status = 'available'";
$stmt = $db->prepare($query);
$stmt->execute();
$availableCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($availableCount == 0) {
    echo "<h3>No available coupons found!</h3>";
    
    // Check if the database has been initialized with pre-created coupons
    $query = "SELECT COUNT(*) as count FROM coupons";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $totalCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($totalCount == 0) {
        echo "<p>The database does not contain any coupons. The initial coupons have not been created.</p>";
        echo "<h4>Solution:</h4>";
        echo "<p>Run the database initialization script to create the pre-defined coupons:</p>";
        echo "<a href='init_coupons.php' class='btn btn-primary'>Initialize Coupons</a>";
    } else {
        echo "<p>All coupons have been assigned or redeemed. You need to create new coupons.</p>";
    }
}

// Display all coupons for debugging
$query = "SELECT c.*, ct.name as coupon_type_name FROM coupons c 
          JOIN coupon_types ct ON c.coupon_type_id = ct.id 
          ORDER BY c.code LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();

echo "<h3>Sample Coupons (first 10):</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Code</th><th>Type</th><th>Status</th><th>Balance</th><th>Buyer ID</th></tr>";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>{$row['code']}</td>";
    echo "<td>{$row['coupon_type_name']}</td>";
    echo "<td>{$row['status']}</td>";
    echo "<td>{$row['current_balance']}</td>";
    echo "<td>{$row['buyer_id']}</td>";
    echo "</tr>";
}
echo "</table>";
?>
