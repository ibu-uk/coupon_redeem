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

<!-- Add HTML2Canvas and jsPDF libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

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
                <button type="button" class="btn btn-danger ms-2" id="pdfCouponBtn">
                    <i class="fas fa-file-pdf me-2"></i> PDF
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
                        if(strtolower($coupon->coupon_type_name) === 'black') {
                            echo 'badge-black';
                        } else if(strtolower($coupon->coupon_type_name) === 'gold') {
                            echo 'badge-gold';
                        } else if(strtolower($coupon->coupon_type_name) === 'silver') {
                            echo 'badge-silver';
                        } else {
                            echo 'bg-secondary';
                        }
                    ?>">
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
                            <h2 class="mb-0"><?php echo $coupon->code; ?></h2>
                            <p class="text-muted mb-0">
                                <?php 
                                // Display coupon number explanation for new format
                                $couponNumber = substr($coupon->code, 1); // Extract number part (e.g., "101" from "B101")
                                echo "Coupon #" . ($couponNumber - 100); // Convert back to original number for reference
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Initial Balance:</strong> <?php echo number_format($coupon->initial_balance, 2); ?> KD</p>
                        <p><strong>Current Balance:</strong> <span class="text-<?php echo $coupon->current_balance > 0 ? 'success' : 'danger'; ?> fw-bold"><?php echo number_format($coupon->current_balance, 2); ?> KD</span></p>
                        <p><strong>Issue Date:</strong> <?php echo $coupon->issue_date ? date('d M Y', strtotime($coupon->issue_date)) : 'Not assigned yet'; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status:</strong> 
                            <?php if($coupon->status === 'available'): ?>
                                <span class="badge bg-info">Available</span>
                            <?php elseif($coupon->status === 'assigned'): ?>
                                <span class="badge bg-success">Assigned</span>
                            <?php elseif($coupon->status === 'fully_redeemed'): ?>
                                <span class="badge bg-danger">Fully Redeemed</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h6>Redemption Progress</h6>
                    <div class="progress" style="height: 25px;">
                        <?php 
                        $redeemed = $coupon->initial_balance - $coupon->current_balance;
                        $percentage = ($redeemed / $coupon->initial_balance) * 100;
                        ?>
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php echo number_format($percentage, 0); ?>%
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <small>0 KD</small>
                        <small><?php echo number_format($coupon->initial_balance, 2); ?> KD</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Buyer Information section removed as requested -->
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Recipients Information</h5>
            </div>
            <div class="card-body">
                <?php 
                // Get all recipients who have redeemed this coupon
                $recipientQuery = "SELECT DISTINCT rl.recipient_name, rl.recipient_civil_id, rl.recipient_mobile, rl.recipient_file_number, 
                                   COUNT(rl.id) as redemption_count, SUM(rl.amount) as total_amount,
                                   MAX(CONCAT(rl.redemption_date, ' ', rl.redemption_time)) as last_redemption
                            FROM redemption_logs rl 
                            WHERE rl.coupon_id = ? 
                            GROUP BY rl.recipient_name, rl.recipient_civil_id, rl.recipient_mobile, rl.recipient_file_number
                            ORDER BY last_redemption DESC";
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
                                <th>Recipient Name</th>
                                <th>Civil ID</th>
                                <th>Mobile</th>
                                <th>File Number</th>
                                <th>Redemptions</th>
                                <th>Total Amount</th>
                                <th>Last Used</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recipients as $recipient): ?>
                            <tr>
                                <td><?php echo $recipient['recipient_name']; ?></td>
                                <td><?php echo $recipient['recipient_civil_id']; ?></td>
                                <td><?php echo $recipient['recipient_mobile']; ?></td>
                                <td><?php echo $recipient['recipient_file_number']; ?></td>
                                <td><span class="badge bg-info"><?php echo $recipient['redemption_count']; ?></span></td>
                                <td><?php echo number_format($recipient['total_amount'], 2); ?> KD</td>
                                <td><?php echo date('d M Y H:i', strtotime($recipient['last_redemption'])); ?></td>
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
                <?php if($redemptionLogs->rowCount() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Service</th>
                                    <th>Amount</th>
                                    <th>Remaining</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $redemptionLogs->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo date('d M Y', strtotime($row['redemption_date'])); ?></td>
                                        <td><?php echo $row['service_name']; ?></td>
                                        <td><?php echo number_format($row['amount'], 2); ?> KD</td>
                                        <td><?php echo number_format($row['remaining_balance'], 2); ?> KD</td>
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
                    <h2>Coupon Management System</h2>
                    <h4>Coupon Details</h4>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <h5>Coupon Information</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th>Code:</th>
                                <td><?php echo $coupon->code; ?></td>
                            </tr>
                            <tr>
                                <th>Type:</th>
                                <td><?php echo $coupon->coupon_type_name; ?></td>
                            </tr>
                            <tr>
                                <th>Value:</th>
                                <td><?php echo number_format($coupon->coupon_type_value, 2); ?> KD</td>
                            </tr>
                            <tr>
                                <th>Initial Balance:</th>
                                <td><?php echo number_format($coupon->initial_balance, 2); ?> KD</td>
                            </tr>
                            <tr>
                                <th>Current Balance:</th>
                                <td id="print-current-balance"><?php echo number_format($coupon->current_balance, 2); ?> KD</td>
                            </tr>
                            <tr>
                                <th>Issue Date:</th>
                                <td><?php echo $coupon->issue_date ? date('d M Y', strtotime($coupon->issue_date)) : 'Not assigned yet'; ?></td>
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
                    <h5>Thank you for your purchase!</h5>
                    <h6>BATO CLINIC - HAVE A NICE DAY!</h6>
                    <p class="mt-3">For any inquiries, please contact us: 6007 2702</p>
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
    // Print and PDF coupon functionality
    document.addEventListener('DOMContentLoaded', function() {
        const printCouponBtn = document.getElementById('printCouponBtn');
        const pdfCouponBtn = document.getElementById('pdfCouponBtn');
        const printButton = document.getElementById('printButton');
        const printCouponModal = new bootstrap.Modal(document.getElementById('printCouponModal'));
        
        printCouponBtn.addEventListener('click', function() {
            printCouponModal.show();
        });
        
        // PDF Export functionality
        pdfCouponBtn.addEventListener('click', function() {
            // Update the current balance in the print view with the latest value from the main view
            const mainViewBalance = document.querySelector('.text-success, .text-danger').textContent;
            document.getElementById('print-current-balance').textContent = mainViewBalance;
            
            // Show the modal first to prepare the content
            printCouponModal.show();
            
            // Wait a moment for the modal to be fully visible
            setTimeout(() => {
                const { jsPDF } = window.jspdf;
                const content = document.getElementById('printCouponContent');
                
                // Create PDF
                html2canvas(content).then(canvas => {
                    const imgData = canvas.toDataURL('image/png');
                    const pdf = new jsPDF('p', 'mm', 'a4');
                    const imgProps = pdf.getImageProperties(imgData);
                    const pdfWidth = pdf.internal.pageSize.getWidth();
                    const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
                    
                    pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
                    pdf.save(`Coupon_${document.querySelector('h2.mb-0').textContent}.pdf`);
                    
                    // Hide the modal after PDF is generated
                    printCouponModal.hide();
                });
            }, 500);
        });
        
        // Print functionality
        printButton.addEventListener('click', function() {
            // Update the current balance in the print view with the latest value from the main view
            const mainViewBalance = document.querySelector('.text-success, .text-danger').textContent;
            document.getElementById('print-current-balance').textContent = mainViewBalance;
            
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
