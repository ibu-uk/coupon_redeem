<?php
// Include configuration and models
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'models/User.php';
require_once 'models/Coupon.php';
require_once 'models/Service.php';
require_once 'models/RedemptionLog.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$service = new Service($db);
$redemptionLog = new RedemptionLog($db);

// Initialize variables
$error = "";
$success = "";
$debug = "";
$couponData = null;

// Debug information
$debug .= "Session ID: " . session_id() . "<br>";
if (isset($_SESSION['user_id'])) {
    $debug .= "User ID: " . $_SESSION['user_id'] . "<br>";
    $debug .= "User Role: " . $_SESSION['user_role'] . "<br>";
} else {
    $debug .= "User not logged in<br>";
}

// Get all services for dropdown
$servicesList = $service->readAll();
$services = [];
while ($row = $servicesList->fetch(PDO::FETCH_ASSOC)) {
    $services[] = $row;
}

// Process coupon code lookup (works with both GET and POST)
$couponCode = "";
if (isset($_REQUEST['coupon_code'])) {
    $couponCode = trim($_REQUEST['coupon_code']);
    $debug .= "Looking up coupon: " . $couponCode . "<br>";
    
    if (empty($couponCode)) {
        $error = "Please enter a coupon code.";
    } else {
        // Normalize coupon code
        $couponCode = str_replace(' ', '-', trim($couponCode));
        $debug .= "Normalized code: " . $couponCode . "<br>";
        
        // Prepare the query based on the input
        
        // Check if a coupon type was selected
        $selectedType = isset($_POST['coupon_type']) ? $_POST['coupon_type'] : '';
        
        // Handle numeric input with coupon type selection
        if (is_numeric($couponCode) && !empty($selectedType)) {
            $typePrefix = strtoupper(substr($selectedType, 0, 1));
            $query = "SELECT c.*, ct.name as coupon_type_name, ct.value as coupon_type_value,
                     b.full_name as buyer_name, b.civil_id as buyer_civil_id, 
                     b.mobile_number as buyer_mobile, b.file_number as buyer_file_number
              FROM coupons c
              LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
              LEFT JOIN users b ON c.buyer_id = b.id
              WHERE c.code = '{$typePrefix}{$couponCode}'";
            $debug .= "Searching for {$selectedType} coupon #{$couponCode} (code: {$typePrefix}{$couponCode})<br>";
        }
        // Handle numeric input without coupon type selection (default to B prefix)
        else if (is_numeric($couponCode)) {
            $query = "SELECT c.*, ct.name as coupon_type_name, ct.value as coupon_type_value,
                     b.full_name as buyer_name, b.civil_id as buyer_civil_id, 
                     b.mobile_number as buyer_mobile, b.file_number as buyer_file_number
              FROM coupons c
              LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
              LEFT JOIN users b ON c.buyer_id = b.id
              WHERE c.code = 'B{$couponCode}'";
            $debug .= "No coupon type selected, defaulting to Black (B{$couponCode})<br>";
        }
        else {
            // Full code provided (e.g. B101, G101, etc.)
            $query = "SELECT c.*, ct.name as coupon_type_name, ct.value as coupon_type_value,
                     b.full_name as buyer_name, b.civil_id as buyer_civil_id, 
                     b.mobile_number as buyer_mobile, b.file_number as buyer_file_number
              FROM coupons c
              LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
              LEFT JOIN users b ON c.buyer_id = b.id
              WHERE c.code = '" . $couponCode . "'";
            $debug .= "Searching for exact code: " . $couponCode . "<br>";
        }
        
        $debug .= "Executing query: " . $query . "<br>";
        
        // Execute the query
        $stmt = $db->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $debug .= "Coupon found!<br>";
            $debug .= "ID: " . $row['id'] . ", Code: " . $row['code'] . ", Status: " . $row['status'] . "<br>";
            
            // Check if coupon is valid for redemption
            if ($row['status'] == 'assigned' && $row['current_balance'] > 0) {
                $debug .= "Coupon is valid for redemption<br>";
                
                // Create coupon data
                $couponData = [
                    'id' => $row['id'],
                    'code' => $row['code'],
                    'type' => $row['coupon_type_name'],
                    'type_value' => $row['coupon_type_value'],
                    'current_balance' => $row['current_balance'],
                    'buyer_name' => $row['buyer_name'],
                    'buyer_civil_id' => $row['buyer_civil_id'],
                    'buyer_mobile' => $row['buyer_mobile'],
                    'buyer_file_number' => $row['buyer_file_number'],
                    'recipient_name' => '',
                    'recipient_civil_id' => '',
                    'recipient_mobile' => '',
                    'recipient_file_number' => ''
                ];
                
                $debug .= "Coupon data created successfully<br>";
            } else {
                $debug .= "Coupon is not valid for redemption: Status=" . $row['status'] . ", Balance=" . $row['current_balance'] . "<br>";
                $error = "This coupon is not valid for redemption. It may be unavailable or fully redeemed.";
            }
        } else {
            $debug .= "Coupon not found<br>";
            $error = "Coupon not found. Please check the code and try again.";
        }
    }
}

// Process redemption
if (isset($_POST['redeem_coupon'])) {
    // Get form data
    $couponId = $_POST['coupon_id'];
    $serviceId = $_POST['service_id'];
    $serviceName = "";
    $amount = floatval($_POST['amount']);
    $serviceDescription = $_POST['service_description'];
    $recipientName = $_POST['recipient_name'];
    $recipientCivilId = $_POST['recipient_civil_id'];
    $recipientMobile = $_POST['recipient_mobile'];
    $recipientFileNumber = $_POST['recipient_file_number'];
    
    $debug .= "Processing redemption for coupon ID: " . $couponId . "<br>";
    
    // Fetch the current coupon data to ensure we have the latest balance
    $query = "SELECT c.*, ct.name as coupon_type_name, ct.value as coupon_type_value,
             b.full_name as buyer_name, b.civil_id as buyer_civil_id, 
             b.mobile_number as buyer_mobile, b.file_number as buyer_file_number
      FROM coupons c
      LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
      LEFT JOIN users b ON c.buyer_id = b.id
      WHERE c.id = ?";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $couponId);
    $stmt->execute();
    $couponData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$couponData) {
        $error = "Could not retrieve coupon data. Please try again.";
        $debug .= "Failed to retrieve coupon data for ID: " . $couponId . "<br>";
    } else {
        // Check for duplicate redemption
        $query = "SELECT COUNT(*) as count FROM redemption_logs 
                  WHERE coupon_id = ? AND service_id = ? AND amount = ? AND recipient_name = ? AND recipient_civil_id = ? 
                  AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $couponId);
        $stmt->bindParam(2, $serviceId);
        $stmt->bindParam(3, $amount);
        $stmt->bindParam(4, $recipientName);
        $stmt->bindParam(5, $recipientCivilId);
        $stmt->execute();
        
        if ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($result['count'] > 0) {
                $debug .= "Duplicate redemption detected<br>";
                $error = "This redemption appears to be a duplicate. The same service was already redeemed for this coupon in the last 5 minutes.";
            } else {
                // Get service name
                foreach ($services as $s) {
                    if ($s['id'] == $serviceId) {
                        $serviceName = $s['name'];
                        break;
                    }
                }
                
                // Check required fields
                if (empty($serviceName) || empty($amount) || 
                    empty($recipientName) || empty($recipientCivilId) || empty($recipientMobile)) {
                    $debug .= "Missing required fields<br>";
                    $error = "All required fields must be filled.";
                } 
                // Check if amount exceeds available balance
                else if (!isset($couponData['current_balance']) || $amount > $couponData['current_balance']) {
                    $debug .= "Amount exceeds available balance<br>";
                    $currentBalance = isset($couponData['current_balance']) ? $couponData['current_balance'] : 0;
                    $error = "The service amount (" . number_format($amount, 2) . " KD) exceeds the available balance (" . number_format($currentBalance, 2) . " KD). Please enter a smaller amount.";
                }
                else {
                    $debug .= "Creating redemption log<br>";
                    
                    // Begin transaction
                    $db->beginTransaction();
                    try {
                        // Create redemption log
                        $query = "INSERT INTO redemption_logs 
                                  (coupon_id, service_id, service_name, amount, 
                                   recipient_name, recipient_civil_id, recipient_mobile, recipient_file_number, 
                                   redeemed_by, redemption_date, redemption_time, remaining_balance, service_description) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), 
                                  (SELECT current_balance - ? FROM coupons WHERE id = ?), ?)";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(1, $couponId);
                        $stmt->bindParam(2, $serviceId);
                        $stmt->bindParam(3, $serviceName);
                        $stmt->bindParam(4, $amount);
                        $stmt->bindParam(5, $recipientName);
                        $stmt->bindParam(6, $recipientCivilId);
                        $stmt->bindParam(7, $recipientMobile);
                        $stmt->bindParam(8, $recipientFileNumber);
                        $stmt->bindParam(9, $_SESSION['user_id']);
                        $stmt->bindParam(10, $amount);
                        $stmt->bindParam(11, $couponId);
                        $stmt->bindParam(12, $serviceDescription);
                        
                        // Check if all parameters are valid
                        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
                            throw new Exception("User session is invalid. Please log in again.");
                        }
                        
                        $stmt->execute();
                        $debug .= "Redemption log created successfully<br>";
                        
                        // Update coupon balance and assign recipient if not already assigned
                        $query = "UPDATE coupons SET 
                                  current_balance = current_balance - ?,
                                  status = CASE 
                                      WHEN (current_balance - ?) <= 0 THEN 'fully_redeemed' 
                                      ELSE status 
                                  END,
                                  recipient_id = CASE 
                                      WHEN recipient_id IS NULL THEN (
                                          SELECT id FROM users 
                                          WHERE civil_id = ? AND role = 'recipient'
                                          LIMIT 1
                                      )
                                      ELSE recipient_id 
                                  END
                                  WHERE id = ?";
                                  
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(1, $amount);
                        $stmt->bindParam(2, $amount);
                        $stmt->bindParam(3, $recipientCivilId);
                        $stmt->bindParam(4, $couponId);
                        
                        $stmt->execute();
                        $debug .= "Coupon balance updated and recipient assigned successfully<br>";
                        
                        // Check if recipient exists in users table, if not, create them
                        $query = "SELECT COUNT(*) as count FROM users WHERE civil_id = ? AND role = 'recipient'";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(1, $recipientCivilId);
                        $stmt->execute();
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($result['count'] == 0 && !empty($recipientCivilId)) {
                            // Generate a username based on civil ID
                            $username = 'recipient_' . $recipientCivilId;
                            
                            // Generate a default password for the recipient
                            $defaultPassword = 'recipient' . substr($recipientCivilId, -4) . rand(10, 99);
                            $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
                            
                            // Create a new recipient user
                            $query = "INSERT INTO users (full_name, civil_id, mobile_number, file_number, role, created_at, username, password, email) 
                                      VALUES (?, ?, ?, ?, 'recipient', NOW(), ?, ?, ?)";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(1, $recipientName);
                            $stmt->bindParam(2, $recipientCivilId);
                            $stmt->bindParam(3, $recipientMobile);
                            $stmt->bindParam(4, $recipientFileNumber);
                            $stmt->bindParam(5, $username);
                            $stmt->bindParam(6, $hashedPassword);
                            // Generate a placeholder email
                            $email = 'recipient_' . $recipientCivilId . '@placeholder.com';
                            $stmt->bindParam(7, $email);
                            $stmt->execute();
                            
                            $newRecipientId = $db->lastInsertId();
                            $debug .= "Created new recipient user with ID: " . $newRecipientId . "<br>";
                            
                            // Update the coupon with the new recipient ID
                            $query = "UPDATE coupons SET recipient_id = ? WHERE id = ?";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(1, $newRecipientId);
                            $stmt->bindParam(2, $couponId);
                            $stmt->execute();
                        }
                        
                        // Commit transaction
                        $db->commit();
                        $success = "Coupon redeemed successfully!";
                        // Reset coupon data
                        $couponData = null;
                        
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $db->rollBack();
                        $debug .= "Transaction failed: " . $e->getMessage() . "<br>";
                        
                        // Provide more specific error message based on the exception
                        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                            $error = "This redemption appears to be a duplicate. The same service was already redeemed for this coupon.";
                        } else if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                            $error = "Database reference error. One of the selected items may no longer exist.";
                        } else if (strpos($e->getMessage(), 'session') !== false) {
                            $error = $e->getMessage();
                        } else {
                            $error = "Failed to process the redemption: " . $e->getMessage();
                        }
                        
                        // Log the full error for debugging
                        error_log("Redemption error: " . $e->getMessage());
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Coupon Redemption</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .badge-black {
            background-color: #000;
            color: #fff;
        }
        .badge-gold {
            background-color: #FFD700;
            color: #000;
        }
        .badge-silver {
            background-color: #C0C0C0;
            color: #000;
        }
        .coupon-code {
            font-weight: bold;
            font-size: 1.2em;
        }
        .redemption-form {
            margin-top: 15px;
        }
        .card-header {
            background-color: #343a40;
            color: white;
            padding: 8px 15px;
        }
        .section-header {
            background-color: #343a40;
            color: white;
            padding: 5px 10px;
            margin-bottom: 0;
        }
        .details-table {
            margin-bottom: 15px;
        }
        .details-table th, .details-table td {
            padding: 6px 10px;
        }
        .mb-3 {
            margin-bottom: 10px !important;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container mt-4">
        <h1 class="mb-4">Coupon Redemption</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h5>Coupon Lookup</h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="coupon_code" class="form-label">Enter Coupon Number</label>
                        <input type="text" class="form-control" id="coupon_code" name="coupon_code" 
                               value="<?php echo htmlspecialchars($couponCode); ?>"
                               placeholder="Enter coupon number (e.g. 101) or full code (e.g. B101)" required>
                        <small class="text-muted">You can enter just the number (e.g., 101) or the full code (e.g., B101)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="coupon_type" class="form-label">Coupon Type (optional, helps when entering just a number)</label>
                        <select class="form-select" id="coupon_type" name="coupon_type">
                            <option value="">Select a type</option>
                            <option value="black">Black</option>
                            <option value="gold">Gold</option>
                            <option value="silver">Silver</option>
                        </select>
                        <small class="text-muted">This helps disambiguate when you enter just a number (e.g., "101" could be B101, G101, or S101)</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Lookup Coupon</button>
                </form>
            </div>
        </div>
        
        <?php if ($couponData): ?>
        <div class="card redemption-form">
            <div class="card-header">
                <h5>Redemption Form</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h5 class="section-header">Coupon Details</h5>
                        <table class="table table-bordered details-table">
                            <tr>
                                <th>Coupon Code:</th>
                                <td class="coupon-code"><?php echo $couponData['code']; ?></td>
                            </tr>
                            <tr>
                                <th>Type:</th>
                                <td>
                                    <span class="badge <?php 
                                        if (strtolower($couponData['type']) == 'black') {
                                            echo 'badge-black';
                                        } elseif (strtolower($couponData['type']) == 'gold') {
                                            echo 'badge-gold';
                                        } elseif (strtolower($couponData['type']) == 'silver') {
                                            echo 'badge-silver';
                                        } else {
                                            echo 'bg-secondary';
                                        }
                                    ?>">
                                        <?php echo $couponData['type']; ?>
                                    </span>
                                    <?php if(isset($couponData['type_value'])): ?>
                                    <small class="ms-2">(<?php echo number_format($couponData['type_value'], 0); ?> KD)</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Available Balance:</th>
                                <td><?php echo number_format(isset($couponData['current_balance']) ? $couponData['current_balance'] : 0, 2); ?> KD</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5 class="section-header">Buyer Information</h5>
                        <table class="table table-bordered details-table">
                            <tr>
                                <th>Name:</th>
                                <td><?php echo $couponData['buyer_name']; ?></td>
                            </tr>
                            <tr>
                                <th>Civil ID:</th>
                                <td><?php echo $couponData['buyer_civil_id']; ?></td>
                            </tr>
                            <tr>
                                <th>Mobile:</th>
                                <td><?php echo $couponData['buyer_mobile']; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="recipient_search" class="form-label">Search Existing Recipients</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="recipient_search" placeholder="Search by name, ID, mobile, or file number...">
                        <button class="btn btn-outline-secondary" type="button" id="recipient_search_button">Search</button>
                    </div>
                    <small class="text-muted">Search for existing recipients to auto-fill the form</small>
                    <div id="recipient_search_results" class="mt-2" style="display: none;">
                        <select class="form-select" id="recipient_select" size="5">
                            <!-- Search results will appear here -->
                        </select>
                    </div>
                </div>
                
                <form method="post" action="" name="redeem_form">
                    <input type="hidden" name="coupon_id" value="<?php echo $couponData['id']; ?>">
                    
                    <h5 class="mt-3 mb-3">Recipient Information</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="recipient_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="recipient_name" name="recipient_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="recipient_civil_id" class="form-label">Civil ID</label>
                            <input type="text" class="form-control" id="recipient_civil_id" name="recipient_civil_id" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="recipient_mobile" class="form-label">Mobile Number</label>
                            <input type="text" class="form-control" id="recipient_mobile" name="recipient_mobile" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="recipient_file_number" class="form-label">File Number (clinic system)</label>
                            <input type="text" class="form-control" id="recipient_file_number" name="recipient_file_number">
                        </div>
                    </div>
                    
                    <h5 class="mt-3 mb-3">Service Information</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="service_id" class="form-label">Service Name</label>
                            <select class="form-select" id="service_id" name="service_id" required onchange="updateAmount()">
                                <option value="">Select a service</option>
                                <?php foreach ($services as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" data-price="<?php echo $s['default_price']; ?>">
                                        <?php echo $s['name']; ?> - <?php echo number_format($s['default_price'], 2); ?> KD
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Select a service to automatically fill the amount</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="amount" class="form-label">Amount of Service (KD)</label>
                            <input type="number" step="0.01" min="0.01" max="<?php echo isset($couponData['current_balance']) ? $couponData['current_balance'] : 0; ?>" 
                                   class="form-control" id="amount" name="amount" required>
                            <small class="text-muted">Available balance: <span class="fw-bold text-success"><?php echo number_format(isset($couponData['current_balance']) ? $couponData['current_balance'] : 0, 2); ?> KD</span></small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="service_description" class="form-label">Service Description <small class="text-muted">(Optional)</small></label>
                        <textarea class="form-control" id="service_description" name="service_description" rows="3"></textarea>
                    </div>
                    
                    <button type="submit" name="redeem_coupon" class="btn btn-success">Confirm Redemption</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        function updateAmount() {
            const serviceSelect = document.getElementById('service_id');
            const amountField = document.getElementById('amount');
            const maxBalance = <?php echo isset($couponData['current_balance']) ? $couponData['current_balance'] : 0; ?>;
            
            if (serviceSelect.selectedIndex > 0) {
                const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
                const defaultPrice = parseFloat(selectedOption.getAttribute('data-price'));
                
                // Make sure the amount doesn't exceed the available balance
                const amount = Math.min(defaultPrice, maxBalance);
                amountField.value = amount.toFixed(2);
            } else {
                amountField.value = '';
            }
        }

        // Additional validation before form submission
        document.addEventListener('DOMContentLoaded', function() {
            const redeemForm = document.querySelector('form[name="redeem_form"]');
            if (redeemForm) {
                redeemForm.addEventListener('submit', function(e) {
                    const amountField = document.getElementById('amount');
                    const maxBalance = <?php echo isset($couponData['current_balance']) ? $couponData['current_balance'] : 0; ?>;
                    
                    if (parseFloat(amountField.value) > maxBalance) {
                        e.preventDefault();
                        alert('The service amount exceeds the available balance. Maximum allowed: ' + maxBalance.toFixed(2) + ' KD');
                        amountField.focus();
                    }
                });
            }
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Recipient search functionality - optimized for performance
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('recipient_search');
            const searchButton = document.getElementById('recipient_search_button');
            const searchResults = document.getElementById('recipient_search_results');
            const recipientSelect = document.getElementById('recipient_select');
            
            // Only proceed if we're on the redemption form page (with these elements)
            if (!searchInput || !searchButton || !searchResults || !recipientSelect) {
                return;
            }
            
            // Recipient form fields
            const recipientNameInput = document.getElementById('recipient_name');
            const recipientCivilIdInput = document.getElementById('recipient_civil_id');
            const recipientMobileInput = document.getElementById('recipient_mobile');
            const recipientFileNumberInput = document.getElementById('recipient_file_number');
            
            // Debounce function to prevent excessive API calls
            let searchTimeout = null;
            function debounce(func, delay) {
                return function() {
                    const context = this;
                    const args = arguments;
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => func.apply(context, args), delay);
                };
            }
            
            // Cache for search results to reduce API calls
            const searchCache = {};
            const CACHE_EXPIRY = 5 * 60 * 1000; // 5 minutes
            
            // Search function
            function searchRecipients() {
                const searchTerm = searchInput.value.trim();
                if (searchTerm.length < 3) {
                    searchResults.style.display = 'none';
                    return;
                }
                
                // Check cache first
                const cacheKey = `recipient:${searchTerm}`;
                const cachedResult = searchCache[cacheKey];
                if (cachedResult && (Date.now() - cachedResult.timestamp < CACHE_EXPIRY)) {
                    displaySearchResults(cachedResult.data);
                    return;
                }
                
                // Clear previous results
                recipientSelect.innerHTML = '';
                
                // Show loading indicator
                recipientSelect.innerHTML = '<option disabled>Searching...</option>';
                searchResults.style.display = 'block';
                
                // Make AJAX request to search API with a timeout
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 5000); // 5 second timeout
                
                fetch(`api/search_customers.php?search=${encodeURIComponent(searchTerm)}&type=all`, {
                    signal: controller.signal
                })
                    .then(response => response.json())
                    .then(data => {
                        clearTimeout(timeoutId);
                        
                        // Cache the results
                        searchCache[cacheKey] = {
                            data: data,
                            timestamp: Date.now()
                        };
                        
                        displaySearchResults(data);
                    })
                    .catch(error => {
                        clearTimeout(timeoutId);
                        if (error.name === 'AbortError') {
                            recipientSelect.innerHTML = '<option disabled>Search timed out. Please try again with a more specific search.</option>';
                        } else {
                            console.error('Error searching recipients:', error);
                            recipientSelect.innerHTML = '<option disabled>Error searching recipients</option>';
                        }
                    });
            }
            
            // Display search results
            function displaySearchResults(data) {
                // Clear loading indicator
                recipientSelect.innerHTML = '';
                
                if (data.error) {
                    recipientSelect.innerHTML = `<option disabled>${data.error}</option>`;
                    return;
                }
                
                if (!data.customers || data.customers.length === 0) {
                    recipientSelect.innerHTML = '<option disabled>No recipients found</option>';
                    return;
                }
                
                // Add customers to select dropdown
                data.customers.forEach(customer => {
                    const option = document.createElement('option');
                    option.value = customer.id;
                    option.textContent = customer.display_name;
                    option.dataset.fullName = customer.full_name;
                    option.dataset.civilId = customer.civil_id || '';
                    option.dataset.mobileNumber = customer.mobile_number || '';
                    option.dataset.fileNumber = customer.file_number || '';
                    recipientSelect.appendChild(option);
                });
                
                // Show performance info for debugging
                if (data.execution_time_ms) {
                    const perfOption = document.createElement('option');
                    perfOption.disabled = true;
                    perfOption.textContent = `Found ${data.count} results in ${data.execution_time_ms}ms`;
                    recipientSelect.appendChild(perfOption);
                }
            }
            
            // Search button click event
            searchButton.addEventListener('click', searchRecipients);
            
            // Search on input with debounce (300ms)
            searchInput.addEventListener('input', debounce(function() {
                if (this.value.trim().length >= 3) {
                    searchRecipients();
                } else {
                    searchResults.style.display = 'none';
                }
            }, 300));
            
            // Search on Enter key
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchRecipients();
                }
            });
            
            // Recipient selection event
            recipientSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.disabled) return; // Skip disabled options
                
                // Fill form fields with selected recipient data
                recipientNameInput.value = selectedOption.dataset.fullName;
                recipientCivilIdInput.value = selectedOption.dataset.civilId;
                recipientMobileInput.value = selectedOption.dataset.mobileNumber;
                recipientFileNumberInput.value = selectedOption.dataset.fileNumber;
            });
        });
    </script>
    
    <style>
        /* Recipient search styles */
        #recipient_search_results {
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 15px;
        }
        #recipient_select {
            width: 100%;
        }
        #recipient_select option {
            padding: 8px;
            cursor: pointer;
        }
        #recipient_select option:hover {
            background-color: #f8f9fa;
        }
    </style>
</body>
</html>
