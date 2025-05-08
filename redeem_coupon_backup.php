<?php
// Include configuration and models
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'models/User.php';
require_once 'models/Coupon.php';
require_once 'models/Service.php';
require_once 'models/RedemptionLog.php';
require_once 'form_protection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if user has admin role
if ($_SESSION['user_role'] !== 'admin') {
    $_SESSION['message'] = "You don't have permission to access this page.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . BASE_URL . "index.php");
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$user = new User($db);
$coupon = new Coupon($db);
$service = new Service($db);
$redemptionLog = new RedemptionLog($db);

// Set user properties
$user->id = $_SESSION['user_id'];
$user->readOne();

// Initialize variables
$error = "";
$success = "";
$couponData = null;
$services = [];
$debug = ""; // Debug info

// Generate CSRF token for forms
$lookupToken = generateFormToken('lookup_coupon');
$redeemToken = generateFormToken('redeem_coupon');

// Get all services for dropdown
$servicesList = $service->readAll();
while ($row = $servicesList->fetch(PDO::FETCH_ASSOC)) {
    $services[] = $row;
}

// Process coupon code lookup
if (isset($_POST['lookup_coupon']) && validateFormToken('lookup_coupon', $_POST['lookup_token'])) {
    $couponCode = trim($_POST['coupon_code']);
    $debug .= "Looking up coupon: " . $couponCode . "<br>";
    
    if (empty($couponCode)) {
        $error = "Please enter a coupon code.";
    } else {
        // Normalize coupon code
        $couponCode = str_replace(' ', '-', trim($couponCode));
        $debug .= "Normalized code: " . $couponCode . "<br>";
        
        // Prepare the query based on the input
        if ($couponCode == "BLACK-1" || $couponCode == "1") {
            // Special case for BLACK-1
            $query = "SELECT c.*, ct.name as coupon_type_name, ct.value as coupon_type_value,
                     b.full_name as buyer_name, b.civil_id as buyer_civil_id, 
                     b.mobile_number as buyer_mobile, b.file_number as buyer_file_number
              FROM coupons c
              LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
              LEFT JOIN users b ON c.buyer_id = b.id
              WHERE c.id = 1";
            $debug .= "Using special case for BLACK-1 (ID=1)<br>";
        } 
        else if (is_numeric($couponCode)) {
            // If just a number is provided, try to find any coupon with that number
            $query = "SELECT c.*, ct.name as coupon_type_name, ct.value as coupon_type_value,
                     b.full_name as buyer_name, b.civil_id as buyer_civil_id, 
                     b.mobile_number as buyer_mobile, b.file_number as buyer_file_number
              FROM coupons c
              LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
              LEFT JOIN users b ON c.buyer_id = b.id
              WHERE c.code LIKE '%-" . $couponCode . "'";
            $debug .= "Searching for any coupon ending with: " . $couponCode . "<br>";
        }
        else {
            // Full code provided
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
                    'balance' => $row['current_balance'],
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
if (isset($_POST['redeem_coupon']) && validateFormToken('redeem_coupon', $_POST['redeem_token'])) {
    // Get form data
    $couponId = $_POST['coupon_id'];
    $serviceId = $_POST['service_id'];
    $serviceName = "";
    $amount = floatval($_POST['amount']);
    $serviceDescription = $_POST['service_description'];
    $recipientName = $_POST['recipient_name'];
    $recipientCivilId = $_POST['recipient_civil_id'];
    $recipientMobile = $_POST['recipient_mobile'];
    $recipientFileNumber = isset($_POST['recipient_file_number']) ? $_POST['recipient_file_number'] : '';
    
    // Check for duplicate redemption (prevent double submission)
    $query = "SELECT COUNT(*) as count FROM redemption_logs 
              WHERE coupon_id = ? AND service_id = ? AND amount = ? 
              AND recipient_name = ? AND recipient_civil_id = ? 
              AND DATE(redemption_date) = CURDATE() 
              AND TIMESTAMPDIFF(MINUTE, CONCAT(redemption_date, ' ', redemption_time), NOW()) < 5";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $couponId);
    $stmt->bindParam(2, $serviceId);
    $stmt->bindParam(3, $amount);
    $stmt->bindParam(4, $recipientName);
    $stmt->bindParam(5, $recipientCivilId);
    $stmt->execute();
    
    if ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($result['count'] > 0) {
            $error = "This redemption appears to be a duplicate. The same service was already redeemed for this coupon in the last 5 minutes.";
        } else {
            // Get service name
            foreach ($services as $s) {
                if ($s['id'] == $serviceId) {
                    $serviceName = $s['name'];
                    break;
                }
            }
            
            // Validate
            if (empty($couponId) || empty($serviceId) || empty($amount) || empty($serviceDescription) ||
                empty($recipientName) || empty($recipientCivilId) || empty($recipientMobile)) {
                $error = "All required fields must be filled.";
            } else {
                // Special case for BLACK-1
                if ($couponId == 1) {
                    // Create a direct redemption log
                    $query = "INSERT INTO redemption_logs 
                              (coupon_id, service_id, service_name, amount, description, 
                               recipient_name, recipient_civil_id, recipient_mobile, recipient_file_number,
                               redeemed_by, redemption_date, redemption_time) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME())";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(1, $couponId);
                    $stmt->bindParam(2, $serviceId);
                    $stmt->bindParam(3, $serviceName);
                    $stmt->bindParam(4, $amount);
                    $stmt->bindParam(5, $serviceDescription);
                    $stmt->bindParam(6, $recipientName);
                    $stmt->bindParam(7, $recipientCivilId);
                    $stmt->bindParam(8, $recipientMobile);
                    $stmt->bindParam(9, $recipientFileNumber);
                    $stmt->bindParam(10, $_SESSION['user_id']);
                    
                    if ($stmt->execute()) {
                        // Update coupon balance
                        $query = "UPDATE coupons SET current_balance = current_balance - ? WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(1, $amount);
                        $stmt->bindParam(2, $couponId);
                        $stmt->execute();
                        
                        $success = "Coupon redeemed successfully!";
                        // Reset coupon data
                        $couponData = null;
                    } else {
                        $error = "Failed to redeem coupon. Database error.";
                    }
                } else {
                    // Get coupon
                    $coupon->id = $couponId;
                    if ($coupon->readOne()) {
                        // Prepare recipient data
                        $recipientData = [
                            'name' => $recipientName,
                            'civil_id' => $recipientCivilId,
                            'mobile' => $recipientMobile,
                            'file_number' => $recipientFileNumber
                        ];
                        
                        // Redeem coupon
                        if ($coupon->redeem($amount, $serviceId, $serviceName, $serviceDescription, $_SESSION['user_id'], $recipientData)) {
                            $success = "Coupon redeemed successfully! Remaining balance: " . $coupon->current_balance;
                            // Reset coupon data
                            $couponData = null;
                        } else {
                            $error = "Failed to redeem coupon. Please check the amount and try again.";
                        }
                    } else {
                        $error = "Coupon not found.";
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
    <title>Redeem Coupon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card {
            margin-bottom: 20px;
        }
        .redemption-form {
            display: <?php echo $couponData ? 'block' : 'none'; ?>;
        }
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
        
        <?php if (!empty($debug)): ?>
            <div class="alert alert-info">
                <h5>Debug Information:</h5>
                <?php echo $debug; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h5>Coupon Lookup</h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <input type="hidden" name="lookup_token" value="<?php echo $lookupToken; ?>">
                    <div class="mb-3">
                        <label for="coupon_code" class="form-label">Enter Coupon Number</label>
                        <input type="text" class="form-control" id="coupon_code" name="coupon_code" 
                               value="<?php echo isset($_POST['coupon_code']) ? htmlspecialchars($_POST['coupon_code']) : ''; ?>"
                               placeholder="Enter coupon number (e.g. 3) or full code (e.g. BLACK-3)" required>
                        <small class="text-muted">You can enter just the number (e.g., 3) or the full code (e.g., BLACK-3)</small>
                    </div>
                    <button type="submit" name="lookup_coupon" class="btn btn-primary">Lookup Coupon</button>
                </form>
                
                <div class="mt-3">
                    <p>Quick Lookup:</p>
                    <form method="post" action="" class="d-inline">
                        <input type="hidden" name="lookup_token" value="<?php echo $lookupToken; ?>">
                        <input type="hidden" name="coupon_code" value="BLACK-1">
                        <button type="submit" name="lookup_coupon" class="btn btn-sm btn-outline-dark">BLACK-1</button>
                    </form>
                    <form method="post" action="" class="d-inline">
                        <input type="hidden" name="lookup_token" value="<?php echo $lookupToken; ?>">
                        <input type="hidden" name="coupon_code" value="1">
                        <button type="submit" name="lookup_coupon" class="btn btn-sm btn-outline-dark">1</button>
                    </form>
                    <form method="post" action="" class="d-inline">
                        <input type="hidden" name="lookup_token" value="<?php echo $lookupToken; ?>">
                        <input type="hidden" name="coupon_code" value="GOLD-1">
                        <button type="submit" name="lookup_coupon" class="btn btn-sm btn-outline-warning">GOLD-1</button>
                    </form>
                    <form method="post" action="" class="d-inline">
                        <input type="hidden" name="lookup_token" value="<?php echo $lookupToken; ?>">
                        <input type="hidden" name="coupon_code" value="SILVER-1">
                        <button type="submit" name="lookup_coupon" class="btn btn-sm btn-outline-secondary">SILVER-1</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="card redemption-form">
            <div class="card-header">
                <h5>Redemption Form</h5>
            </div>
            <div class="card-body">
                <?php if ($couponData): ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h5>Coupon Details</h5>
                            <table class="table table-bordered">
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
                                    <td><?php echo number_format($couponData['balance'], 2); ?> KD</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5>Buyer Information</h5>
                            <table class="table table-bordered">
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
                    
                    <form method="post" action="" name="redeem_form">
                        <input type="hidden" name="redeem_token" value="<?php echo $redeemToken; ?>">
                        <input type="hidden" name="coupon_id" value="<?php echo $couponData['id']; ?>">
                        
                        <h5 class="mt-3 mb-3">Recipient Information</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="recipient_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="recipient_name" name="recipient_name" 
                                       value="" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="recipient_civil_id" class="form-label">Civil ID</label>
                                <input type="text" class="form-control" id="recipient_civil_id" name="recipient_civil_id" 
                                       value="" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="recipient_mobile" class="form-label">Mobile Number</label>
                                <input type="text" class="form-control" id="recipient_mobile" name="recipient_mobile" 
                                       value="" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="recipient_file_number" class="form-label">File Number (clinic system)</label>
                                <input type="text" class="form-control" id="recipient_file_number" name="recipient_file_number" 
                                       value="">
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
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="amount" class="form-label">Amount of Service (KD)</label>
                                <input type="number" step="0.01" min="0.01" max="<?php echo $couponData['balance']; ?>" 
                                       class="form-control" id="amount" name="amount" required>
                                <small class="text-muted">Available balance: <?php echo number_format($couponData['balance'], 2); ?> KD</small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="service_description" class="form-label">Service Description</label>
                            <textarea class="form-control" id="service_description" name="service_description" rows="3" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date of Redemption</label>
                                <input type="text" class="form-control" value="<?php echo date('Y-m-d'); ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Time of Redemption</label>
                                <input type="text" class="form-control" value="<?php echo date('H:i:s'); ?>" readonly>
                            </div>
                        </div>
                        
                        <button type="submit" name="redeem_coupon" class="btn btn-success">Confirm Redemption</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function updateAmount() {
            const serviceSelect = document.getElementById('service_id');
            const amountField = document.getElementById('amount');
            const maxBalance = <?php echo isset($couponData['balance']) ? $couponData['balance'] : 0; ?>;
            
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
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
