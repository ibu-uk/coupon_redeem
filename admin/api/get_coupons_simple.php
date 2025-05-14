<?php
// Include configuration file
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../models/Coupon.php';

// Set content type to JSON
header('Content-Type: application/json');

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
    // Add coupon to response
    $response['coupons'][] = $row;
}

// Return JSON response
echo json_encode($response);
?>
