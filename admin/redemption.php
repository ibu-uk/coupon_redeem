<?php
// Include configuration file
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../models/Coupon.php';
require_once '../models/RedemptionLog.php';

// Check if user is logged in and has admin or staff role
if(!isLoggedIn() || (!hasRole('admin') && !hasRole('staff'))) {
    redirect('../login.php');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize coupon object
$coupon = new Coupon($db);

// Check if coupon code is provided
$couponCode = isset($_GET['code']) ? $_GET['code'] : '';
$couponFound = false;

if(!empty($couponCode)) {
    $coupon->code = $couponCode;
    if($coupon->getByCode()) {
        $couponFound = true;
        
        // Initialize redemption log object
        $redemptionLog = new RedemptionLog($db);
        $redemptionLog->coupon_id = $coupon->id;
        $redemptionLogs = $redemptionLog->getByCoupon();
    }
}

// Process redemption form
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem'])) {
    $amount = $_POST['amount'];
    $serviceDescription = $_POST['service_description'];
    
    // Validate amount
    if(!is_numeric($amount) || $amount <= 0) {
        $error = "Please enter a valid amount.";
    } elseif($amount > $coupon->current_balance) {
        $error = "Amount exceeds available balance.";
    } else {
        // Redeem amount
        if($coupon->redeem($amount, $serviceDescription, $_SESSION['user_id'])) {
            $_SESSION['message'] = "Amount redeemed successfully!";
            $_SESSION['message_type'] = "success";
            
            // Refresh page to show updated balance
            redirect('admin/redemption.php?code=' . $coupon->code);
        } else {
            $error = "Failed to redeem amount.";
        }
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h2 class="mb-4">Coupon Redemption</h2>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Lookup Coupon</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-8">
                        <input type="text" class="form-control form-control-lg" name="code" placeholder="Enter coupon code..." value="<?php echo htmlspecialchars($couponCode); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary btn-lg w-100">Lookup</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if(!empty($couponCode) && !$couponFound): ?>
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> Coupon with code <strong><?php echo htmlspecialchars($couponCode); ?></strong> not found.
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if($couponFound): ?>
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Coupon Details</h5>
                    <span class="badge <?php echo strtolower($coupon->coupon_type_name) === 'black' ? 'badge-black' : (strtolower($coupon->coupon_type_name) === 'gold' ? 'badge-gold' : 'badge-silver'); ?>">
                        <?php echo $coupon->coupon_type_name; ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Coupon Code:</strong> <?php echo $coupon->code; ?></p>
                            <p><strong>Initial Balance:</strong> <?php echo number_format($coupon->initial_balance, 2); ?> KD</p>
                            <p><strong>Current Balance:</strong> <span class="text-<?php echo $coupon->current_balance > 0 ? 'success' : 'danger'; ?> fw-bold"><?php echo number_format($coupon->current_balance, 2); ?> KD</span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Issue Date:</strong> <?php echo date('d M Y', strtotime($coupon->issue_date)); ?></p>
                            <p><strong>Expiry Date:</strong> <?php echo date('d M Y', strtotime($coupon->expiry_date)); ?></p>
                            <p><strong>Status:</strong> 
                                <?php if($coupon->status === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php elseif($coupon->status === 'expired'): ?>
                                    <span class="badge bg-danger">Expired</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Fully Redeemed</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Buyer Information</h6>
                            <p><strong>Name:</strong> <?php echo $coupon->buyer_name; ?></p>
                            <p><strong>Email:</strong> <?php echo $coupon->buyer_email; ?></p>
                            <p><strong>Civil ID:</strong> <?php echo $coupon->buyer_civil_id; ?></p>
                            <p><strong>Mobile:</strong> <?php echo $coupon->buyer_mobile; ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Recipient Information</h6>
                            <?php if($coupon->recipient_id): ?>
                                <p><strong>Name:</strong> <?php echo $coupon->recipient_name; ?></p>
                                <p><strong>Email:</strong> <?php echo $coupon->recipient_email; ?></p>
                            <?php else: ?>
                                <p class="text-muted">No recipient assigned yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if($coupon->status === 'active'): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Redeem Service</h5>
                    </div>
                    <div class="card-body">
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount (KD)</label>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0.01" max="<?php echo $coupon->current_balance; ?>" required>
                                <div class="form-text">Maximum available: <?php echo number_format($coupon->current_balance, 2); ?> KD</div>
                            </div>
                            <div class="mb-3">
                                <label for="service_description" class="form-label">Service Description</label>
                                <textarea class="form-control" id="service_description" name="service_description" rows="3" required></textarea>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" name="redeem" class="btn btn-success">Redeem Amount</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mt-4" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> This coupon is <?php echo $coupon->status === 'expired' ? 'expired' : 'fully redeemed'; ?> and cannot be used for redemption.
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Redemption History</h5>
                </div>
                <div class="card-body">
                    <?php if($redemptionLogs->rowCount() > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Service</th>
                                        <th>Redeemed By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $redemptionLogs->fetch(PDO::FETCH_ASSOC)): ?>
                                        <tr>
                                            <td><?php echo date('d M Y H:i', strtotime($row['redemption_date'])); ?></td>
                                            <td><?php echo number_format($row['amount'], 2); ?> KD</td>
                                            <td><?php echo $row['service_description']; ?></td>
                                            <td><?php echo $row['redeemer_name']; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted">No redemption history found.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Redemption Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Initial Value</h6>
                                    <h3 class="card-text"><?php echo number_format($coupon->initial_balance, 2); ?> KD</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Redeemed Amount</h6>
                                    <h3 class="card-text"><?php echo number_format($coupon->initial_balance - $coupon->current_balance, 2); ?> KD</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h6>Redemption Progress</h6>
                        <div class="progress" style="height: 25px;">
                            <?php 
                                $redemptionPercentage = $coupon->initial_balance > 0 ? 
                                    (($coupon->initial_balance - $coupon->current_balance) / $coupon->initial_balance) * 100 : 0;
                            ?>
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $redemptionPercentage; ?>%;" aria-valuenow="<?php echo $redemptionPercentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                <?php echo number_format($redemptionPercentage, 1); ?>%
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <small>0 KD</small>
                            <small><?php echo number_format($coupon->initial_balance, 2); ?> KD</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// Include footer
include_once '../includes/footer.php';
?>
