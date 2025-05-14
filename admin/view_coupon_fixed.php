<?php
// Include configuration file
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../models/Coupon.php';
require_once '../models/RedemptionLog.php';
require_once '../models/User.php';

// Check if format is JSON
$isJson = isset($_GET['format']) && $_GET['format'] === 'json';

// Only check login for non-JSON requests
if(!$isJson && (!isLoggedIn() || (!hasRole('admin') && !hasRole('staff')))) {
    redirect('../login.php');
}

// Check if coupon ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    if($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Coupon ID is required']);
        exit;
    } else {
        $_SESSION['message'] = "Coupon ID is required.";
        $_SESSION['message_type'] = "danger";
        redirect('admin/manage_coupons.php');
    }
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize coupon object
$coupon = new Coupon($db);
$coupon->id = $_GET['id'];

// Get coupon details
if(!$coupon->readOne()) {
    if($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Coupon not found']);
        exit;
    } else {
        $_SESSION['message'] = "Coupon not found.";
        $_SESSION['message_type'] = "danger";
        redirect('admin/manage_coupons.php');
    }
}

// Get buyer details if available
$buyer = null;
if($coupon->buyer_id) {
    $buyer = new User($db);
    $buyer->id = $coupon->buyer_id;
    $buyer->readOne();
}

// Initialize redemption log object
$redemptionLog = new RedemptionLog($db);
$redemptionLog->coupon_id = $coupon->id;
$redemptionLogs = $redemptionLog->getByCoupon();

// If JSON format is requested, return JSON response
if($isJson) {
    header('Content-Type: application/json');
    $couponData = [
        'id' => $coupon->id,
        'code' => $coupon->code,
        'coupon_type_id' => $coupon->coupon_type_id,
        'coupon_type_name' => $coupon->coupon_type_name,
        'coupon_type_value' => $coupon->coupon_type_value,
        'initial_balance' => $coupon->initial_balance,
        'current_balance' => $coupon->current_balance,
        'status' => $coupon->status,
        'issue_date' => $coupon->issue_date,
        'created_at' => $coupon->created_at,
        'buyer_id' => $coupon->buyer_id,
        'buyer_name' => $buyer ? $buyer->full_name : null,
        'recipient_name' => $coupon->recipient_name,
        'recipient_civil_id' => $coupon->recipient_civil_id,
        'recipient_mobile' => $coupon->recipient_mobile
    ];
    echo json_encode($couponData);
    exit;
}

// Include header for HTML response
include_once '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Coupon Details</h2>
            <div>
                <a href="<?php echo BASE_URL; ?>admin/manage_coupons.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Coupons
                </a>
                <?php if($coupon->status === 'assigned' && $coupon->current_balance > 0): ?>
                <a href="<?php echo BASE_URL; ?>redeem_coupon.php" class="btn btn-success ms-2">
                    <i class="fas fa-money-bill-wave me-2"></i> Redeem
                </a>
                <?php endif; ?>
                <button type="button" class="btn btn-primary ms-2" id="printCouponBtn">
                    <i class="fas fa-print me-2"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<?php if($coupon->status === 'fully_redeemed'): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i> This coupon is fully redeemed and cannot be used for redemption.
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Coupon Information</h5>
                <div>
                    <span class="badge <?php 
                        if($coupon->status === 'available') echo 'bg-secondary';
                        elseif($coupon->status === 'assigned') echo 'bg-success';
                        elseif($coupon->status === 'fully_redeemed') echo 'bg-danger';
                        else echo 'bg-primary';
                    ?>">
                        <?php echo ucfirst($coupon->status); ?>
                    </span>
                    <span class="badge bg-primary ms-2">
                        <?php echo $coupon->coupon_type_name; ?>
                    </span>
                    <small class="ms-2">(<?php echo number_format($coupon->coupon_type_value, 0); ?> KD)</small>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-12 text-center">
                        <h4>Coupon Code</h4>
                        <div class="border p-3 rounded bg-light">
                            <p class="display-6 mb-0 coupon-code">
                                <?php 
                                // Format coupon code for better readability
                                $code = $coupon->code;
                                echo chunk_split($code, 4, ' ');
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Initial Balance:</strong> <?php echo number_format($coupon->initial_balance, 2); ?> KD</p>
                        <p><strong>Current Balance:</strong> <?php echo number_format($coupon->current_balance, 2); ?> KD</p>
                        <p><strong>Redeemed Amount:</strong> <?php echo number_format($coupon->initial_balance - $coupon->current_balance, 2); ?> KD</p>
                        <p>
                            <strong>Redemption %:</strong> 
                            <?php 
                            $redemptionPercentage = $coupon->initial_balance > 0 ? (($coupon->initial_balance - $coupon->current_balance) / $coupon->initial_balance) * 100 : 0;
                            echo round($redemptionPercentage, 2) . '%';
                            ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Issue Date:</strong> <?php echo date('d M Y', strtotime($coupon->issue_date)); ?></p>
                        <p><strong>Created:</strong> <?php echo date('d M Y H:i', strtotime($coupon->created_at)); ?></p>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h6>Redemption Progress</h6>
                    <div class="progress" style="height: 25px;">
                        <?php 
                        $progressClass = 'bg-success';
                        if($redemptionPercentage > 75) {
                            $progressClass = 'bg-danger';
                        } elseif($redemptionPercentage > 50) {
                            $progressClass = 'bg-warning';
                        }
                        ?>
                        <div class="progress-bar <?php echo $progressClass; ?>" role="progressbar" style="width: <?php echo $redemptionPercentage; ?>%" aria-valuenow="<?php echo $redemptionPercentage; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo round($redemptionPercentage); ?>%</div>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <small>0 KD</small>
                        <small><?php echo number_format($coupon->initial_balance, 2); ?> KD</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Buyer Information</h5>
            </div>
            <div class="card-body">
                <?php if($buyer): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <tr>
                            <th width="30%">Name:</th>
                            <td><?php echo $buyer->full_name; ?></td>
                        </tr>
                        <tr>
                            <th>Civil ID:</th>
                            <td><?php echo $buyer->civil_id; ?></td>
                        </tr>
                        <tr>
                            <th>Mobile:</th>
                            <td><?php echo $buyer->mobile_number; ?></td>
                        </tr>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-center text-muted">This coupon has not been assigned to a buyer yet.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Recipients Information</h5>
            </div>
            <div class="card-body">
                <?php 
                // Get all recipients who have redeemed this coupon - FIXED QUERY FOR SQL_MODE=ONLY_FULL_GROUP_BY
                $recipientQuery = "SELECT DISTINCT 
                                    rl.recipient_name, 
                                    rl.recipient_civil_id, 
                                    rl.recipient_mobile, 
                                    rl.recipient_file_number, 
                                    COUNT(rl.id) as redemption_count, 
                                    SUM(rl.amount) as total_amount,
                                    MAX(rl.redemption_date) as last_redemption_date,
                                    MAX(rl.redemption_time) as last_redemption_time
                             FROM redemption_logs rl 
                             WHERE rl.coupon_id = ? 
                             GROUP BY rl.recipient_name, rl.recipient_civil_id, rl.recipient_mobile, rl.recipient_file_number
                             ORDER BY last_redemption_date DESC, last_redemption_time DESC";
                $stmt = $db->prepare($recipientQuery);
                $stmt->bindParam(1, $coupon->id);
                $stmt->execute();
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if(count($recipients) > 0): 
                ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Recipient</th>
                                <th>Civil ID</th>
                                <th>Mobile</th>
                                <th>File #</th>
                                <th>Redemptions</th>
                                <th>Amount</th>
                                <th>Last Redemption</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recipients as $recipient): ?>
                            <tr>
                                <td><?php echo $recipient['recipient_name']; ?></td>
                                <td><?php echo $recipient['recipient_civil_id']; ?></td>
                                <td><?php echo $recipient['recipient_mobile']; ?></td>
                                <td><?php echo $recipient['recipient_file_number']; ?></td>
                                <td><?php echo $recipient['redemption_count']; ?></td>
                                <td><?php echo number_format($recipient['total_amount'], 2); ?> KD</td>
                                <td>
                                    <?php 
                                    // Format the last redemption date and time
                                    echo date('d M Y', strtotime($recipient['last_redemption_date'])) . ' ' . 
                                         date('H:i', strtotime($recipient['last_redemption_time']));
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-center text-muted">No recipients have redeemed this coupon yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Redemption History</h5>
            </div>
            <div class="card-body">
                <?php if(count($redemptionLogs) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Recipient</th>
                                <th>Amount</th>
                                <th>Balance After</th>
                                <th>Staff</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($redemptionLogs as $log): ?>
                            <tr>
                                <td>
                                    <?php 
                                    echo date('d M Y', strtotime($log['redemption_date'])) . '<br>' . 
                                         '<small>' . date('H:i:s', strtotime($log['redemption_time'])) . '</small>';
                                    ?>
                                </td>
                                <td>
                                    <?php echo $log['recipient_name']; ?><br>
                                    <small class="text-muted"><?php echo $log['recipient_civil_id']; ?></small>
                                </td>
                                <td class="text-danger">-<?php echo number_format($log['amount'], 2); ?> KD</td>
                                <td><?php echo number_format($log['balance_after'], 2); ?> KD</td>
                                <td><?php echo $log['staff_name']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-center text-muted">No redemption history available for this coupon.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Print Coupon Modal -->
<div class="modal fade" id="printCouponModal" tabindex="-1" aria-labelledby="printCouponModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="printCouponModalLabel">Print Coupon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="printCouponContent">
                <div class="text-center mb-4">
                    <h3>BATO CLINIC</h3>
                    <p>Coupon Voucher</p>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <h5>Coupon Details</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th>Coupon Code:</th>
                                <td><?php echo chunk_split($coupon->code, 4, ' '); ?></td>
                            </tr>
                            <tr>
                                <th>Type:</th>
                                <td><?php echo $coupon->coupon_type_name; ?> (<?php echo number_format($coupon->coupon_type_value, 0); ?> KD)</td>
                            </tr>
                            <tr>
                                <th>Value:</th>
                                <td><?php echo number_format($coupon->initial_balance, 2); ?> KD</td>
                            </tr>
                            <tr>
                                <th>Issue Date:</th>
                                <td><?php echo date('d M Y', strtotime($coupon->issue_date)); ?></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td><?php echo ucfirst($coupon->status); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>Buyer Information</h5>
                        <?php if($buyer): ?>
                        <table class="table table-bordered">
                            <tr>
                                <th>Name:</th>
                                <td><?php echo $buyer->full_name; ?></td>
                            </tr>
                            <tr>
                                <th>Civil ID:</th>
                                <td><?php echo $buyer->civil_id; ?></td>
                            </tr>
                            <tr>
                                <th>Mobile:</th>
                                <td><?php echo $buyer->mobile_number; ?></td>
                            </tr>
                            <tr>
                                <th>File Number:</th>
                                <td><?php echo $buyer->file_number; ?></td>
                            </tr>
                            <tr>
                                <th>Purchase Date:</th>
                                <td><?php echo $buyer->purchase_date ? date('d M Y', strtotime($buyer->purchase_date)) : 'N/A'; ?></td>
                            </tr>
                        </table>
                        <?php else: ?>
                        <p class="text-center">No buyer information available</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <p>Thank you for your purchase!</p>
                    <p><small>For any inquiries, please contact us.</small></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="printButton">Print</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Print coupon functionality
    document.addEventListener('DOMContentLoaded', function() {
        const printCouponBtn = document.getElementById('printCouponBtn');
        const printButton = document.getElementById('printButton');
        const printCouponModal = new bootstrap.Modal(document.getElementById('printCouponModal'));
        
        printCouponBtn.addEventListener('click', function() {
            printCouponModal.show();
        });
        
        // Print functionality
        printButton.addEventListener('click', function() {
            const printContents = document.getElementById('printCouponContent').innerHTML;
            const originalContents = document.body.innerHTML;
            
            document.body.innerHTML = `
                <html>
                    <head>
                        <title>Print Coupon</title>
                        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
                        <style>
                            @media print {
                                body {
                                    padding: 20px;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="container">
                            ${printContents}
                        </div>
                    </body>
                </html>
            `;
            
            window.print();
            document.body.innerHTML = originalContents;
            location.reload();
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
