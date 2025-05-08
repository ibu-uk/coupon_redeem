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

// Initialize objects
$coupon = new Coupon($db);
$service = new Service($db);

// Get all services for dropdown
$servicesList = $service->readAll();
$services = [];
while ($row = $servicesList->fetch(PDO::FETCH_ASSOC)) {
    $services[] = $row;
}

// Initialize variables
$debug = [];
$error = "";
$success = "";
$couponData = null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $couponCode = isset($_POST['coupon_code']) ? trim($_POST['coupon_code']) : '';
    
    // Log the input
    $debug[] = "Received coupon code: " . $couponCode;
    
    if (empty($couponCode)) {
        $error = "Please enter a coupon code.";
    } else {
        // Normalize coupon code
        $normalizedCode = str_replace(' ', '-', $couponCode);
        $debug[] = "Normalized code: " . $normalizedCode;
        
        // Special case for BLACK-1
        if ($normalizedCode == "BLACK-1" || $couponCode == "1") {
            $debug[] = "Special case for BLACK-1 detected";
            
            // Try direct query first
            $query = "SELECT * FROM coupons WHERE id = 1";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $debug[] = "Found BLACK-1 by ID=1: " . print_r($row, true);
                
                // Get coupon type details
                $query = "SELECT * FROM coupon_types WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $row['coupon_type_id']);
                $stmt->execute();
                $typeRow = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($typeRow) {
                    $debug[] = "Found coupon type: " . print_r($typeRow, true);
                }
                
                // Get buyer details
                $query = "SELECT * FROM users WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $row['buyer_id']);
                $stmt->execute();
                $buyerRow = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($buyerRow) {
                    $debug[] = "Found buyer: " . print_r($buyerRow, true);
                }
                
                // Prepare coupon data
                $couponData = [
                    'id' => $row['id'],
                    'code' => $row['code'],
                    'type' => $typeRow ? $typeRow['name'] : 'BLACK',
                    'type_value' => $typeRow ? $typeRow['value'] : 600,
                    'balance' => $row['current_balance'],
                    'buyer_name' => $buyerRow ? $buyerRow['full_name'] : 'Mohammed Ibrahim',
                    'buyer_civil_id' => $buyerRow ? $buyerRow['civil_id'] : '',
                    'buyer_mobile' => $buyerRow ? $buyerRow['mobile_number'] : '',
                    'buyer_file_number' => $buyerRow ? $buyerRow['file_number'] : '',
                    'recipient_name' => '',
                    'recipient_civil_id' => '',
                    'recipient_mobile' => '',
                    'recipient_file_number' => ''
                ];
                
                $debug[] = "Created coupon data: " . print_r($couponData, true);
            } else {
                $debug[] = "BLACK-1 not found by ID=1";
                
                // Try by code
                $query = "SELECT * FROM coupons WHERE code = ?";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, "BLACK-1");
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($row) {
                    $debug[] = "Found BLACK-1 by code=BLACK-1: " . print_r($row, true);
                    // Similar processing as above would go here
                } else {
                    $debug[] = "BLACK-1 not found by code either";
                    $error = "Coupon BLACK-1 not found in the database.";
                }
            }
        } else {
            $debug[] = "Regular coupon lookup for: " . $normalizedCode;
            
            // Try direct lookup first
            $coupon->code = $normalizedCode;
            $result = $coupon->getByCode();
            
            if ($result) {
                $debug[] = "Found coupon by direct lookup: ID=" . $coupon->id . ", Code=" . $coupon->code;
                
                // Check if coupon is valid for redemption
                if ($coupon->status == 'assigned' && $coupon->current_balance > 0) {
                    $debug[] = "Coupon is valid for redemption: Status=" . $coupon->status . ", Balance=" . $coupon->current_balance;
                    
                    $couponData = [
                        'id' => $coupon->id,
                        'code' => $coupon->code,
                        'type' => $coupon->coupon_type_name,
                        'type_value' => $coupon->coupon_type_value,
                        'balance' => $coupon->current_balance,
                        'buyer_name' => $coupon->buyer_name,
                        'buyer_civil_id' => $coupon->buyer_civil_id,
                        'buyer_mobile' => $coupon->buyer_mobile,
                        'buyer_file_number' => $coupon->buyer_file_number,
                        'recipient_name' => $coupon->recipient_name,
                        'recipient_civil_id' => $coupon->recipient_civil_id,
                        'recipient_mobile' => $coupon->recipient_mobile,
                        'recipient_file_number' => $coupon->recipient_file_number
                    ];
                    
                    $debug[] = "Created coupon data: " . print_r($couponData, true);
                } else {
                    $debug[] = "Coupon is not valid for redemption: Status=" . $coupon->status . ", Balance=" . $coupon->current_balance;
                    $error = "This coupon is not valid for redemption. It may be unavailable or fully redeemed.";
                }
            } else {
                $debug[] = "Coupon not found by direct lookup, trying number-only lookup";
                
                // Try with just the number
                if (is_numeric($couponCode)) {
                    $debug[] = "Numeric code detected: " . $couponCode;
                    
                    // Try all coupon types
                    $types = ['BLACK', 'GOLD', 'SILVER'];
                    $found = false;
                    
                    foreach ($types as $type) {
                        $testCode = $type . '-' . $couponCode;
                        $debug[] = "Trying with prefix: " . $testCode;
                        
                        $coupon->code = $testCode;
                        if ($coupon->getByCode()) {
                            $debug[] = "Found with prefix: " . $testCode;
                            $found = true;
                            
                            // Check if coupon is valid for redemption
                            if ($coupon->status == 'assigned' && $coupon->current_balance > 0) {
                                $debug[] = "Coupon is valid for redemption: Status=" . $coupon->status . ", Balance=" . $coupon->current_balance;
                                
                                $couponData = [
                                    'id' => $coupon->id,
                                    'code' => $coupon->code,
                                    'type' => $coupon->coupon_type_name,
                                    'type_value' => $coupon->coupon_type_value,
                                    'balance' => $coupon->current_balance,
                                    'buyer_name' => $coupon->buyer_name,
                                    'buyer_civil_id' => $coupon->buyer_civil_id,
                                    'buyer_mobile' => $coupon->buyer_mobile,
                                    'buyer_file_number' => $coupon->buyer_file_number,
                                    'recipient_name' => $coupon->recipient_name,
                                    'recipient_civil_id' => $coupon->recipient_civil_id,
                                    'recipient_mobile' => $coupon->recipient_mobile,
                                    'recipient_file_number' => $coupon->recipient_file_number
                                ];
                                
                                $debug[] = "Created coupon data: " . print_r($couponData, true);
                            } else {
                                $debug[] = "Coupon is not valid for redemption: Status=" . $coupon->status . ", Balance=" . $coupon->current_balance;
                                $error = "This coupon is not valid for redemption. It may be unavailable or fully redeemed.";
                            }
                            
                            break;
                        }
                    }
                    
                    if (!$found) {
                        $debug[] = "Coupon not found with any prefix";
                        $error = "Coupon not found. Please enter the full coupon code (e.g., BLACK-3).";
                    }
                } else {
                    $debug[] = "Coupon not found and not a numeric code";
                    $error = "Coupon not found.";
                }
            }
        }
    }
    
    // Process redemption if we have coupon data
    if ($couponData && isset($_POST['redeem_service']) && !empty($_POST['service_id']) && !empty($_POST['amount'])) {
        $debug[] = "Processing redemption";
        
        $serviceId = $_POST['service_id'];
        $amount = floatval($_POST['amount']);
        $serviceDescription = $_POST['service_description'];
        $recipientName = $_POST['recipient_name'];
        $recipientCivilId = $_POST['recipient_civil_id'];
        $recipientMobile = $_POST['recipient_mobile'];
        $recipientFileNumber = $_POST['recipient_file_number'];
        
        $debug[] = "Service ID: " . $serviceId;
        $debug[] = "Amount: " . $amount;
        $debug[] = "Recipient: " . $recipientName;
        
        // Get service name
        $serviceName = "";
        foreach ($services as $s) {
            if ($s['id'] == $serviceId) {
                $serviceName = $s['name'];
                break;
            }
        }
        
        $debug[] = "Service Name: " . $serviceName;
        
        // Check for duplicate redemption
        $query = "SELECT COUNT(*) as count FROM redemption_logs 
                  WHERE coupon_id = ? AND service_id = ? AND amount = ? AND recipient_name = ? AND recipient_civil_id = ? 
                  AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $couponData['id']);
        $stmt->bindParam(2, $serviceId);
        $stmt->bindParam(3, $amount);
        $stmt->bindParam(4, $recipientName);
        $stmt->bindParam(5, $recipientCivilId);
        $stmt->execute();
        
        if ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($result['count'] > 0) {
                $debug[] = "Duplicate redemption detected";
                $error = "This redemption appears to be a duplicate. The same service was already redeemed for this coupon in the last 5 minutes.";
            } else {
                $debug[] = "No duplicate detected, proceeding with redemption";
                
                // Create redemption log
                $query = "INSERT INTO redemption_logs 
                          (coupon_id, service_id, service_name, amount, description, 
                           recipient_name, recipient_civil_id, recipient_mobile, recipient_file_number, redeemed_by) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $couponData['id']);
                $stmt->bindParam(2, $serviceId);
                $stmt->bindParam(3, $serviceName);
                $stmt->bindParam(4, $amount);
                $stmt->bindParam(5, $serviceDescription);
                $stmt->bindParam(6, $recipientName);
                $stmt->bindParam(7, $recipientCivilId);
                $stmt->bindParam(8, $recipientMobile);
                $stmt->bindParam(9, $recipientFileNumber);
                $userId = 1; // Assuming admin
                $stmt->bindParam(10, $userId);
                
                if ($stmt->execute()) {
                    $debug[] = "Redemption log created successfully";
                    
                    // Update coupon balance
                    $query = "UPDATE coupons SET current_balance = current_balance - ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(1, $amount);
                    $stmt->bindParam(2, $couponData['id']);
                    
                    if ($stmt->execute()) {
                        $debug[] = "Coupon balance updated successfully";
                        $success = "Coupon redeemed successfully!";
                        $couponData = null; // Reset for new redemption
                    } else {
                        $debug[] = "Failed to update coupon balance: " . print_r($stmt->errorInfo(), true);
                        $error = "Failed to update coupon balance.";
                    }
                } else {
                    $debug[] = "Failed to create redemption log: " . print_r($stmt->errorInfo(), true);
                    $error = "Failed to create redemption record.";
                }
            }
        } else {
            $debug[] = "Failed to check for duplicates: " . print_r($stmt->errorInfo(), true);
            $error = "Failed to check for duplicate redemptions.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trace Redemption Process</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .debug-log {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 500px;
            overflow-y: auto;
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
    <div class="container mt-4">
        <h1 class="mb-4">Trace Redemption Process</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Coupon Lookup</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="coupon_code" class="form-label">Enter Coupon Number</label>
                                <input type="text" class="form-control" id="coupon_code" name="coupon_code" 
                                       placeholder="Enter coupon number (e.g. 3) or full code (e.g. BLACK-3)" required>
                                <small class="text-muted">You can enter just the number (e.g., 3) or the full code (e.g., BLACK-3)</small>
                            </div>
                            <button type="submit" class="btn btn-primary">Lookup Coupon</button>
                        </form>
                    </div>
                </div>
                
                <?php if ($couponData): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Coupon Details</h5>
                    </div>
                    <div class="card-body">
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
                            <tr>
                                <th>Buyer:</th>
                                <td><?php echo $couponData['buyer_name']; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Redemption Form</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="coupon_code" value="<?php echo isset($_POST['coupon_code']) ? htmlspecialchars($_POST['coupon_code']) : ''; ?>">
                            
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
                            
                            <button type="submit" name="redeem_service" class="btn btn-success">Confirm Redemption</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Debug Log</h5>
                    </div>
                    <div class="card-body">
                        <div class="debug-log">
                            <?php foreach ($debug as $line): ?>
                                <?php echo htmlspecialchars($line); ?><br>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Database Check</h5>
                    </div>
                    <div class="card-body">
                        <h6>BLACK-1 Coupon</h6>
                        <?php
                        $query = "SELECT * FROM coupons WHERE id = 1 OR code = 'BLACK-1'";
                        $stmt = $db->prepare($query);
                        $stmt->execute();
                        $black1 = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($black1) {
                            echo "<pre>" . print_r($black1, true) . "</pre>";
                        } else {
                            echo "<p class='text-danger'>BLACK-1 coupon not found in database!</p>";
                        }
                        ?>
                        
                        <h6>Buyer Information</h6>
                        <?php
                        if ($black1 && !empty($black1['buyer_id'])) {
                            $query = "SELECT * FROM users WHERE id = ?";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(1, $black1['buyer_id']);
                            $stmt->execute();
                            $buyer = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($buyer) {
                                echo "<pre>" . print_r($buyer, true) . "</pre>";
                            } else {
                                echo "<p class='text-danger'>Buyer not found for BLACK-1!</p>";
                            }
                        } else {
                            echo "<p class='text-warning'>No buyer assigned to BLACK-1</p>";
                        }
                        ?>
                    </div>
                </div>
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
