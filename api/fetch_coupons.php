<?php
// Include configuration file
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../models/Coupon.php';

// Set headers for JSON response
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
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get coupons with pagination
$coupons = $coupon->readAll($page, $limit, $search);
$totalCoupons = $coupon->countAll($search);
$totalPages = ceil($totalCoupons / $limit);

// Prepare response data
$data = [
    'success' => true,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => $totalPages,
    'total_records' => $totalCoupons,
    'has_more' => ($page < $totalPages),
    'coupons' => []
];

// Process coupons
while($row = $coupons->fetch(PDO::FETCH_ASSOC)) {
    $coupon_item = [
        'id' => $row['id'],
        'code' => $row['code'],
        'coupon_type_name' => $row['coupon_type_name'],
        'coupon_type_value' => isset($row['value']) ? $row['value'] : $row['initial_balance'],
        'buyer_name' => $row['buyer_name'] ? $row['buyer_name'] : 'Not assigned',
        'recipient_name' => $row['recipient_name'] ? $row['recipient_name'] : 'Not assigned',
        'current_balance' => number_format($row['current_balance'], 2),
        'status' => $row['status']
    ];
    
    $data['coupons'][] = $coupon_item;
}

// Return JSON response
echo json_encode($data);
?>
