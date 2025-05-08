<?php
// Include configuration and models
require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Check if database connection is working
echo "<h1>Diagnosing Main Redemption Page Issues</h1>";

echo "<h2>1. Database Connection Test</h2>";
if ($db) {
    echo "<p style='color:green'>✓ Database connection successful</p>";
} else {
    echo "<p style='color:red'>✗ Database connection failed</p>";
}

// Check if BLACK-1 coupon exists
echo "<h2>2. BLACK-1 Coupon Existence Test</h2>";
$query = "SELECT * FROM coupons WHERE id = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo "<p style='color:green'>✓ BLACK-1 coupon found in database</p>";
    echo "<pre>";
    print_r($row);
    echo "</pre>";
} else {
    echo "<p style='color:red'>✗ BLACK-1 coupon not found in database</p>";
}

// Test the exact query used in redeem_coupon.php
echo "<h2>3. Testing Exact Query from redeem_coupon.php</h2>";
$query = "SELECT c.*, ct.name as coupon_type_name, ct.value as coupon_type_value,
         b.full_name as buyer_name, b.civil_id as buyer_civil_id, 
         b.mobile_number as buyer_mobile, b.file_number as buyer_file_number
  FROM coupons c
  LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
  LEFT JOIN users b ON c.buyer_id = b.id
  WHERE c.id = 1";

$stmt = $db->prepare($query);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo "<p style='color:green'>✓ Query returns results</p>";
    echo "<pre>";
    print_r($row);
    echo "</pre>";
} else {
    echo "<p style='color:red'>✗ Query returns no results</p>";
}

// Check form submission handling in redeem_coupon.php
echo "<h2>4. Form Submission Analysis</h2>";
echo "<p>The main redemption page uses POST method with CSRF token validation.</p>";
echo "<p>Check if CSRF token is being generated correctly:</p>";

// Include form protection to test token generation
require_once 'form_protection.php';
$testToken = generateFormToken('test_token');
if ($testToken) {
    echo "<p style='color:green'>✓ CSRF token generation works</p>";
    echo "<p>Generated token: " . $testToken . "</p>";
} else {
    echo "<p style='color:red'>✗ CSRF token generation failed</p>";
}

// Check session handling
echo "<h2>5. Session Handling</h2>";
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session data:</p>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    echo "<p style='color:green'>✓ User is logged in (ID: " . $_SESSION['user_id'] . ")</p>";
} else {
    echo "<p style='color:red'>✗ User is not logged in - this will prevent redemption page from working</p>";
}

// Check if user has admin role
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    echo "<p style='color:green'>✓ User has admin role</p>";
} else {
    echo "<p style='color:red'>✗ User does not have admin role - this will prevent redemption page from working</p>";
}

// Check for any PHP errors or warnings
echo "<h2>6. PHP Error Log</h2>";
$error_log_path = ini_get('error_log');
echo "<p>PHP error log path: " . ($error_log_path ? $error_log_path : 'Not configured') . "</p>";

// Check if we can include the main redemption page without executing it
echo "<h2>7. File Existence Check</h2>";
if (file_exists('redeem_coupon.php')) {
    echo "<p style='color:green'>✓ redeem_coupon.php file exists</p>";
    
    // Get file size and modification time
    echo "<p>File size: " . filesize('redeem_coupon.php') . " bytes</p>";
    echo "<p>Last modified: " . date("Y-m-d H:i:s", filemtime('redeem_coupon.php')) . "</p>";
} else {
    echo "<p style='color:red'>✗ redeem_coupon.php file does not exist</p>";
}

// Provide recommendations
echo "<h2>Recommendations</h2>";
echo "<ol>";
echo "<li>Make sure you are logged in as an admin user before accessing the redemption page</li>";
echo "<li>Check if there are any PHP errors in the error log</li>";
echo "<li>Try using the simplified redemption page (final_redeem.php) which we confirmed is working</li>";
echo "<li>Check if the form submission is working correctly by adding more debug output</li>";
echo "<li>Try clearing your browser cache or using a different browser</li>";
echo "</ol>";

// Provide links to test pages
echo "<h2>Test Links</h2>";
echo "<ul>";
echo "<li><a href='redeem_coupon.php'>Main Redemption Page</a></li>";
echo "<li><a href='final_redeem.php'>Simplified Redemption Page</a></li>";
echo "<li><a href='debug_form.php'>Debug Form</a></li>";
echo "</ul>";
?>
