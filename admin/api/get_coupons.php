<?php
// Include configuration file
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../models/Coupon.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and has admin role
if(!isLoggedIn() || !hasRole('admin')) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize coupon object
$coupon = new Coupon($db);

// Get parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get coupons with pagination
$couponsStmt = $coupon->readAll($page, $limit, $search);
$totalCoupons = $coupon->countAll($search);
$totalPages = ceil($totalCoupons / $limit);

// Prepare response
$response = [
    'success' => true,
    'page' => $page,
    'total_pages' => $totalPages,
    'total_coupons' => $totalCoupons,
    'coupons' => []
];

// Fetch all coupons
while($row = $couponsStmt->fetch(PDO::FETCH_ASSOC)) {
    // Check if there are redemption logs for this coupon
    $recipientQuery = "SELECT COUNT(DISTINCT recipient_name) as unique_recipients 
                      FROM redemption_logs 
                      WHERE coupon_id = ?";
    $stmt = $db->prepare($recipientQuery);
    $stmt->bindParam(1, $row['id']);
    $stmt->execute();
    $recipientResult = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Add recipient count to coupon data
    $row['unique_recipients'] = $recipientResult ? (int)$recipientResult['unique_recipients'] : 0;
    
    // Add coupon to response
    $response['coupons'][] = $row;
}

// Return JSON response
echo json_encode($response);
?>
