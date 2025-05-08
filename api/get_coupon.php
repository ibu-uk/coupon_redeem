<?php
// Include configuration file
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../models/Coupon.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if user is logged in
if(!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Check if coupon ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Coupon ID is required'
    ]);
    exit;
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize coupon object
$coupon = new Coupon($db);
$coupon->id = $_GET['id'];

// Get coupon details
if($coupon->readOne()) {
    // Return coupon details
    echo json_encode([
        'success' => true,
        'coupon' => [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'coupon_type_id' => $coupon->coupon_type_id,
            'coupon_type_name' => $coupon->coupon_type_name,
            'buyer_id' => $coupon->buyer_id,
            'buyer_name' => $coupon->buyer_name,
            'buyer_email' => $coupon->buyer_email,
            'buyer_civil_id' => $coupon->buyer_civil_id,
            'buyer_mobile' => $coupon->buyer_mobile,
            'recipient_id' => $coupon->recipient_id,
            'recipient_name' => $coupon->recipient_name,
            'recipient_mobile' => $coupon->recipient_mobile,
            'recipient_civil_id' => $coupon->recipient_civil_id,
            'recipient_file_number' => $coupon->recipient_file_number,
            'initial_balance' => $coupon->initial_balance,
            'current_balance' => $coupon->current_balance,
            'issue_date' => $coupon->issue_date,
            'status' => $coupon->status
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Coupon not found'
    ]);
}
?>
