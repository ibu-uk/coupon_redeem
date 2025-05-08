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
$debug = "";

// Directly load BLACK-1 coupon
$query = "SELECT c.*, ct.name as coupon_type_name, ct.value as coupon_type_value,
         b.full_name as buyer_name, b.email as buyer_email, b.civil_id as buyer_civil_id, 
         b.mobile_number as buyer_mobile, b.file_number as buyer_file_number,
         r.full_name as recipient_name, r.email as recipient_email, r.civil_id as recipient_civil_id,
         r.mobile_number as recipient_mobile, r.file_number as recipient_file_number
  FROM coupons c
  LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
  LEFT JOIN users b ON c.buyer_id = b.id
  LEFT JOIN users r ON c.recipient_id = r.id
  WHERE c.code = 'BLACK-1'
  LIMIT 0,1";

$stmt = $db->prepare($query);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    // Set coupon properties
    $coupon->id = $row['id'];
    $coupon->code = $row['code'];
    $coupon->coupon_type_id = $row['coupon_type_id'];
    $coupon->buyer_id = $row['buyer_id'];
    $coupon->recipient_id = $row['recipient_id'];
    $coupon->initial_balance = $row['initial_balance'];
    $coupon->current_balance = $row['current_balance'];
    $coupon->issue_date = $row['issue_date'];
    $coupon->status = $row['status'];
    
    // Set coupon data for the form
    $couponData = [
        'id' => $coupon->id,
        'code' => $coupon->code,
        'type' => $row['coupon_type_name'],
        'type_value' => $row['coupon_type_value'],
        'balance' => $coupon->current_balance,
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
    $error = "BLACK-1 coupon not found in the database.";
}

// Get all services for dropdown
$servicesList = $service->readAll();
while ($row = $servicesList->fetch(PDO::FETCH_ASSOC)) {
    $services[] = $row;
}

// Process redemption
if (isset($_POST['redeem_coupon'])) {
    // Get form data
    $couponId = $_POST['coupon_id'];
    $serviceId = $_POST['service_id'];
    $serviceName = "";
    $amount = $_POST['amount'];
    $serviceDescription = $_POST['service_description'];
    $recipientName = $_POST['recipient_name'];
    $recipientCivilId = $_POST['recipient_civil_id'];
    $recipientMobile = $_POST['recipient_mobile'];
    $recipientFileNumber = $_POST['recipient_file_number'];
    
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
            if ($coupon->redeem($amount, $serviceId, $serviceName, $serviceDescription, $user->id, $recipientData)) {
                $success = "Coupon redeemed successfully!";
                
                // Reset form
                $couponData = null;
            } else {
                $error = "Failed to redeem coupon. Please check the amount and try again.";
            }
        } else {
            $error = "Coupon not found.";
        }
    }
}

// Include header
include_once 'includes/header.php';
?>

<div class="container mt-4">
    <h1 class="mb-4">Direct Redemption for BLACK-1</h1>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="card redemption-form">
        <div class="card-header">
            <h5>Redemption Form for BLACK-1</h5>
        </div>
        <div class="card-body">
            <?php if ($couponData): ?>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h5>Coupon Details</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th>Coupon Code:</th>
                                <td><?php echo $couponData['code']; ?></td>
                            </tr>
                            <tr>
                                <th>Type:</th>
                                <td>
                                    <span class="badge <?php 
                                        if(strtolower($couponData['type']) === 'black') {
                                            echo 'badge-black';
                                        } else if(strtolower($couponData['type']) === 'gold') {
                                            echo 'badge-gold';
                                        } else if(strtolower($couponData['type']) === 'silver') {
                                            echo 'badge-silver';
                                        } else {
                                            echo 'bg-secondary';
                                        }
                                    ?>">
                                        <?php echo $couponData['type']; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Value:</th>
                                <td><?php echo number_format($couponData['type_value'], 2); ?> KD</td>
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
                            <tr>
                                <th>File Number:</th>
                                <td><?php echo $couponData['buyer_file_number']; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <form method="post" action="">
                    <input type="hidden" name="coupon_id" value="<?php echo $couponData['id']; ?>">
                    
                    <h5 class="mt-3 mb-3">Recipient Information</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="recipient_name" class="form-label">Recipient Name</label>
                            <input type="text" class="form-control" id="recipient_name" name="recipient_name" 
                                   value="<?php echo $couponData['recipient_name']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="recipient_civil_id" class="form-label">Recipient Civil ID</label>
                            <input type="text" class="form-control" id="recipient_civil_id" name="recipient_civil_id" 
                                   value="<?php echo $couponData['recipient_civil_id']; ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="recipient_mobile" class="form-label">Recipient Mobile</label>
                            <input type="text" class="form-control" id="recipient_mobile" name="recipient_mobile" 
                                   value="<?php echo $couponData['recipient_mobile']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="recipient_file_number" class="form-label">Recipient File Number</label>
                            <input type="text" class="form-control" id="recipient_file_number" name="recipient_file_number" 
                                   value="<?php echo $couponData['recipient_file_number']; ?>">
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
            <?php else: ?>
                <p class="text-center">No coupon data available.</p>
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

<?php include_once 'includes/footer.php'; ?>
