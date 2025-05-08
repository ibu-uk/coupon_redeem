<?php
// Include configuration file
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../models/Coupon.php';

// Check if user is logged in and has buyer role
if(!isLoggedIn() || !hasRole('buyer')) {
    redirect('login.php');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize coupon object
$coupon = new Coupon($db);
$coupon->buyer_id = $_SESSION['user_id'];

// Get page number
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;

// Get coupons with pagination
$coupons = $coupon->getByBuyer($page, $limit);

// Include header
include_once '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h2 class="mb-4">My Coupons</h2>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Your Purchased Coupons</h5>
            </div>
            <div class="card-body">
                <?php if($coupons->rowCount() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Type</th>
                                    <th>Value</th>
                                    <th>Balance</th>
                                    <th>Expiry Date</th>
                                    <th>Recipient</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $coupons->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo $row['code']; ?></td>
                                        <td>
                                            <span class="badge <?php echo strtolower($row['coupon_type_name']) === 'black' ? 'badge-black' : (strtolower($row['coupon_type_name']) === 'gold' ? 'badge-gold' : 'badge-silver'); ?>">
                                                <?php echo $row['coupon_type_name']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($row['initial_balance'], 2); ?> KD</td>
                                        <td><?php echo number_format($row['current_balance'], 2); ?> KD</td>
                                        <td><?php echo date('d M Y', strtotime($row['expiry_date'])); ?></td>
                                        <td>
                                            <?php if($row['recipient_name']): ?>
                                                <?php echo $row['recipient_name']; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($row['status'] === 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php elseif($row['status'] === 'expired'): ?>
                                                <span class="badge bg-danger">Expired</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Fully Redeemed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="<?php echo BASE_URL; ?>buyer/view_coupon.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if(!$row['recipient_id'] && $row['status'] === 'active'): ?>
                                                    <a href="<?php echo BASE_URL; ?>buyer/assign_recipient.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Assign Recipient">
                                                        <i class="fas fa-user-plus"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-sm btn-success print-coupon" data-coupon-id="<?php echo $row['id']; ?>" data-bs-toggle="tooltip" title="Print">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i> You don't have any coupons yet.
                    </div>
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
                    <h3>Coupon Management System</h3>
                    <p>Your coupon details</p>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <h5>Coupon Details</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th>Coupon Code:</th>
                                <td id="print-coupon-code"></td>
                            </tr>
                            <tr>
                                <th>Coupon Type:</th>
                                <td id="print-coupon-type"></td>
                            </tr>
                            <tr>
                                <th>Value:</th>
                                <td id="print-coupon-value"></td>
                            </tr>
                            <tr>
                                <th>Issue Date:</th>
                                <td id="print-issue-date"></td>
                            </tr>
                            <tr>
                                <th>Expiry Date:</th>
                                <td id="print-expiry-date"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>Recipient Information</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th>Name:</th>
                                <td id="print-recipient-name"></td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td id="print-recipient-email"></td>
                            </tr>
                        </table>
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
        const printCouponButtons = document.querySelectorAll('.print-coupon');
        const printButton = document.getElementById('printButton');
        const printCouponModal = new bootstrap.Modal(document.getElementById('printCouponModal'));
        
        printCouponButtons.forEach(button => {
            button.addEventListener('click', function() {
                const couponId = this.getAttribute('data-coupon-id');
                
                // Fetch coupon details via AJAX
                fetch('<?php echo BASE_URL; ?>api/get_coupon.php?id=' + couponId)
                    .then(response => response.json())
                    .then(data => {
                        if(data.success) {
                            // Populate modal with coupon details
                            document.getElementById('print-coupon-code').textContent = data.coupon.code;
                            document.getElementById('print-coupon-type').textContent = data.coupon.coupon_type_name;
                            document.getElementById('print-coupon-value').textContent = data.coupon.initial_balance + ' KD';
                            document.getElementById('print-issue-date').textContent = new Date(data.coupon.issue_date).toLocaleDateString();
                            document.getElementById('print-expiry-date').textContent = new Date(data.coupon.expiry_date).toLocaleDateString();
                            
                            // Recipient information
                            if(data.coupon.recipient_name) {
                                document.getElementById('print-recipient-name').textContent = data.coupon.recipient_name;
                                document.getElementById('print-recipient-email').textContent = data.coupon.recipient_email;
                            } else {
                                document.getElementById('print-recipient-name').textContent = 'Not assigned';
                                document.getElementById('print-recipient-email').textContent = 'Not assigned';
                            }
                            
                            // Show modal
                            printCouponModal.show();
                        } else {
                            alert('Failed to fetch coupon details.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while fetching coupon details.');
                    });
            });
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
