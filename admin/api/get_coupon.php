<?php
// Include configuration file
require_once '../../config/config.php';
require_once '../../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Check if ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Coupon ID is required'
    ]);
    exit;
}

// Sanitize the coupon ID
$couponId = htmlspecialchars(strip_tags($_GET['id']));

// Direct query to get coupon details
$query = "SELECT c.*, ct.name as coupon_type_name, ct.value as coupon_type_value,
                 b.full_name as buyer_name, b.email as buyer_email, b.civil_id as buyer_civil_id, 
                 b.mobile_number as buyer_mobile, b.file_number as buyer_file_number,
                 r.full_name as recipient_name, r.email as recipient_email, r.civil_id as recipient_civil_id,
                 r.mobile_number as recipient_mobile, r.file_number as recipient_file_number
          FROM coupons c
          LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
          LEFT JOIN users b ON c.buyer_id = b.id
          LEFT JOIN users r ON c.recipient_id = r.id
          WHERE c.id = ?
          LIMIT 0,1";

// Prepare and execute the query
try {
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $couponId);
    $stmt->execute();
    
    // Get the coupon data
    $couponData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Log the data if debug is enabled
    if(isset($_GET['debug'])) {
        error_log('Raw coupon data: ' . print_r($couponData, true));
    }
    
    if($couponData) {
        // Get recipient information from redemption logs if not already set
        if(empty($couponData['recipient_name'])) {
            // Check if there are redemption logs with recipient info
            $recipientQuery = "SELECT recipient_name, recipient_civil_id, recipient_mobile, recipient_file_number 
                              FROM redemption_logs 
                              WHERE coupon_id = ? 
                              ORDER BY redemption_date DESC, redemption_time DESC 
                              LIMIT 1";
            $recipientStmt = $db->prepare($recipientQuery);
            $recipientStmt->bindParam(1, $couponId);
            $recipientStmt->execute();
            $recipient = $recipientStmt->fetch(PDO::FETCH_ASSOC);
            
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
} catch (PDOException $e) {
    // Log the error
    error_log('Database error: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'error_details' => $e->getMessage()
    ]);
}
?>
