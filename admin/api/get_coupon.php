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

// Check if ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Coupon ID is required'
    ]);
    exit;
}

// Set ID
$coupon->id = $_GET['id'];

// Get coupon details
$couponData = $coupon->readOne();

if($couponData) {
    // Get recipient information if available
    if($couponData['recipient_id']) {
        // Get recipient details from users table
        $recipientQuery = "SELECT full_name, email, mobile_number, civil_id FROM users WHERE id = ?";
        $stmt = $db->prepare($recipientQuery);
        $stmt->bindParam(1, $couponData['recipient_id']);
        $stmt->execute();
        $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($recipient) {
            $couponData['recipient_name'] = $recipient['full_name'];
            $couponData['recipient_email'] = $recipient['email'];
            $couponData['recipient_mobile'] = $recipient['mobile_number'];
            $couponData['recipient_civil_id'] = $recipient['civil_id'];
        }
    } else {
        // Check if there are redemption logs with recipient info
        $recipientQuery = "SELECT recipient_name, recipient_civil_id, recipient_mobile, recipient_file_number 
                          FROM redemption_logs 
                          WHERE coupon_id = ? 
                          ORDER BY redemption_date DESC, redemption_time DESC 
                          LIMIT 1";
        $stmt = $db->prepare($recipientQuery);
        $stmt->bindParam(1, $couponData['id']);
        $stmt->execute();
        $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($recipient) {
            $couponData['recipient_name'] = $recipient['recipient_name'];
            $couponData['recipient_civil_id'] = $recipient['recipient_civil_id'];
            $couponData['recipient_mobile'] = $recipient['recipient_mobile'];
            $couponData['recipient_file_number'] = $recipient['recipient_file_number'];
        }
    }
    
    // Return success response with coupon data
    echo json_encode([
        'success' => true,
        'coupon' => $couponData
    ]);
} else {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Coupon not found'
    ]);
}
?>
