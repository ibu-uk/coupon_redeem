<?php
// Include configuration and models
require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

echo "<h1>BLACK-1 Coupon Debug</h1>";
echo "<p>This tool will help diagnose issues with the BLACK-1 coupon.</p>";

// Check if BLACK-1 exists with exact match
$query1 = "SELECT * FROM coupons WHERE code = 'BLACK-1'";
$stmt1 = $db->prepare($query1);
$stmt1->execute();
$row1 = $stmt1->fetch(PDO::FETCH_ASSOC);

echo "<h2>Exact Match: code = 'BLACK-1'</h2>";
if ($row1) {
    echo "<p style='color:green'>✓ Found coupon with code 'BLACK-1'</p>";
    echo "<pre>" . print_r($row1, true) . "</pre>";
} else {
    echo "<p style='color:red'>✗ No coupon found with code 'BLACK-1'</p>";
}

// Check if BLACK 1 exists with space
$query2 = "SELECT * FROM coupons WHERE code = 'BLACK 1'";
$stmt2 = $db->prepare($query2);
$stmt2->execute();
$row2 = $stmt2->fetch(PDO::FETCH_ASSOC);

echo "<h2>Exact Match: code = 'BLACK 1' (with space)</h2>";
if ($row2) {
    echo "<p style='color:green'>✓ Found coupon with code 'BLACK 1'</p>";
    echo "<pre>" . print_r($row2, true) . "</pre>";
} else {
    echo "<p style='color:red'>✗ No coupon found with code 'BLACK 1'</p>";
}

// Check with LIKE
$query3 = "SELECT * FROM coupons WHERE code LIKE '%BLACK%1%'";
$stmt3 = $db->prepare($query3);
$stmt3->execute();
$results3 = [];
while ($row = $stmt3->fetch(PDO::FETCH_ASSOC)) {
    $results3[] = $row;
}

echo "<h2>Pattern Match: code LIKE '%BLACK%1%'</h2>";
if (count($results3) > 0) {
    echo "<p style='color:green'>✓ Found " . count($results3) . " coupon(s) matching pattern</p>";
    foreach ($results3 as $row) {
        echo "<pre>" . print_r($row, true) . "</pre>";
    }
} else {
    echo "<p style='color:red'>✗ No coupons found matching pattern</p>";
}

// Show all coupons
$query4 = "SELECT id, code, status, current_balance FROM coupons ORDER BY id";
$stmt4 = $db->prepare($query4);
$stmt4->execute();
$results4 = [];
while ($row = $stmt4->fetch(PDO::FETCH_ASSOC)) {
    $results4[] = $row;
}

echo "<h2>All Coupons in Database</h2>";
if (count($results4) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Code</th><th>Status</th><th>Balance</th></tr>";
    foreach ($results4 as $row) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['code'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . $row['current_balance'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>✗ No coupons found in database</p>";
}

// Create a fix button
echo "<h2>Fix BLACK-1 Coupon</h2>";
if (isset($_GET['fix']) && $_GET['fix'] == 'true') {
    // Update BLACK 1 to BLACK-1 if needed
    $updateQuery = "UPDATE coupons SET code = 'BLACK-1' WHERE code = 'BLACK 1'";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute();
    $rowCount = $updateStmt->rowCount();
    
    if ($rowCount > 0) {
        echo "<p style='color:green'>✓ Updated " . $rowCount . " coupon(s) from 'BLACK 1' to 'BLACK-1'</p>";
    } else {
        echo "<p>No coupons needed to be updated</p>";
    }
    
    // Ensure BLACK-1 exists
    $checkQuery = "SELECT * FROM coupons WHERE code = 'BLACK-1'";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute();
    $checkRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$checkRow) {
        // Create BLACK-1 if it doesn't exist
        $insertQuery = "INSERT INTO coupons (code, coupon_type_id, initial_balance, current_balance, status) 
                        VALUES ('BLACK-1', 1, 700, 700, 'assigned')";
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->execute();
        echo "<p style='color:green'>✓ Created new BLACK-1 coupon</p>";
    }
    
    echo "<p><a href='debug_black1.php'>Refresh to see changes</a></p>";
} else {
    echo "<p><a href='debug_black1.php?fix=true' style='padding:10px; background-color:#4CAF50; color:white; text-decoration:none; border-radius:5px;'>Fix BLACK-1 Coupon</a></p>";
}
?>
