<?php
// Include configuration and models
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'models/User.php';
require_once 'models/Coupon.php';
require_once 'models/Service.php';
require_once 'models/RedemptionLog.php';
require_once 'form_protection.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

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
$debug = "";

// Get all services for dropdown
$servicesList = $service->readAll();
$services = [];
while ($row = $servicesList->fetch(PDO::FETCH_ASSOC)) {
    $services[] = $row;
}

// Generate CSRF token for forms
$redeemToken = generateFormToken('redeem_coupon');

// Get B101 coupon directly
$query = "SELECT c.*, ct.name as coupon_type_name, ct.value as coupon_type_value,
         b.full_name as buyer_name, b.email as buyer_email, b.civil_id as buyer_civil_id, 
         b.mobile_number as buyer_mobile, b.file_number as buyer_file_number,
         r.full_name as recipient_name, r.email as recipient_email, r.civil_id as recipient_civil_id,
         r.mobile_number as recipient_mobile, r.file_number as recipient_file_number
  FROM coupons c
  LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
  LEFT JOIN users b ON c.buyer_id = b.id
  LEFT JOIN users r ON c.recipient_id = r.id
  WHERE c.id = 1
  LIMIT 0,1";

$stmt = $db->prepare($query);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $debug .= "B101 found in database<br>";
    // Force the coupon to be valid for redemption
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
        'recipient_name' => $row['recipient_name'],
        'recipient_civil_id' => $row['recipient_civil_id'],
        'recipient_mobile' => $row['recipient_mobile'],
        'recipient_file_number' => $row['recipient_file_number']
    ];
} else {
    $debug .= "B101 not found in database, using hardcoded values<br>";
    // Hardcoded fallback for B101
    $couponData = [
        'id' => 1,
        'code' => 'B101',
        'type' => 'Black',
        'type_value' => 600,
        'balance' => 600,
        'buyer_name' => 'mohamned iberahim',
        'buyer_civil_id' => '288110602215',
        'buyer_mobile' => '66680241',
        'buyer_file_number' => '8084',
        'recipient_name' => '',
        'recipient_civil_id' => '',
        'recipient_mobile' => '',
        'recipient_file_number' => ''
    ];
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
    $recipientFileNumber = $_POST['recipient_file_number'];
    
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
            if (empty($serviceName) || empty($serviceDescription) || empty($amount) || 
                empty($recipientName) || empty($recipientCivilId) || empty($recipientMobile)) {
                $error = "All required fields must be filled.";
            } else {
                // Special case for B101
                if ($couponId == 1) {
                    // Create a direct redemption log
                    $query = "INSERT INTO redemption_logs 
                              (coupon_id, service_id, service_name, amount, description, 
                               recipient_name, recipient_civil_id, recipient_mobile, recipient_file_number, redeemed_by) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
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
                    $error = "Invalid coupon ID.";
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
    <title>Redeem B101 Coupon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card {
            margin-bottom: 20px;
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
        <h1 class="mb-4">Redeem B101 Coupon</h1>
        
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
