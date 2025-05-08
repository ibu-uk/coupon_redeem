<?php
// Include configuration file
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../models/Coupon.php';

// Check if user is logged in and has admin role
if(!isLoggedIn() || !hasRole('admin')) {
    redirect('login.php');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize coupon object
$coupon = new Coupon($db);

// Get search term
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get page number
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;

// Get coupons with pagination
$coupons = $coupon->readAll($page, $limit, $search);
$totalCoupons = $coupon->countAll($search);
$totalPages = ceil($totalCoupons / $limit);

// Include header
include_once '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Coupons</h2>
            <a href="<?php echo BASE_URL; ?>admin/create_coupon.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create New Coupon
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">All Coupons</h5>
                    <form class="d-flex" action="" method="GET" id="searchForm">
                        <input class="form-control me-2" type="search" placeholder="Search coupons..." name="search" id="searchInput" value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-primary" type="submit">Search</button>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="couponsTable">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Buyer</th>
                                <th>Recipient</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="couponsTableBody">
                            <?php if($coupons->rowCount() > 0): ?>
                                <?php while($row = $coupons->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo $row['code']; ?></td>
                                        <td>
                                            <span class="badge <?php echo strtolower($row['coupon_type_name']) === 'black' ? 'badge-black' : (strtolower($row['coupon_type_name']) === 'gold' ? 'badge-gold' : 'badge-silver'); ?>">
                                                <?php echo $row['coupon_type_name']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $row['buyer_name'] ? $row['buyer_name'] : '<span class="text-muted">Not assigned</span>'; ?></td>
                                        <td>
                                        <?php
                                        // Simple approach - just check status and redemption count
                                        if ($row['status'] == 'assigned') {
                                            // Check for redemption logs
                                            $redemptionQuery = "SELECT COUNT(*) as count FROM redemption_logs WHERE coupon_id = ?";
                                            $redemptionStmt = $db->prepare($redemptionQuery);
                                            $redemptionStmt->bindParam(1, $row['id']);
                                            $redemptionStmt->execute();
                                            $redemptionResult = $redemptionStmt->fetch(PDO::FETCH_ASSOC);
                                            $redemptionCount = $redemptionResult['count'];
                                            
                                            echo "<span class='badge bg-success'>Assigned</span>";
                                            
                                            if ($redemptionCount > 0) {
                                                echo " <span class='badge bg-info'>{$redemptionCount}</span>";
                                                
                                                // Get the most recent recipient
                                                $recipientQuery = "SELECT recipient_name FROM redemption_logs 
                                                                  WHERE coupon_id = ? 
                                                                  ORDER BY redemption_date DESC, redemption_time DESC 
                                                                  LIMIT 1";
                                                $recipientStmt = $db->prepare($recipientQuery);
                                                $recipientStmt->bindParam(1, $row['id']);
                                                $recipientStmt->execute();
                                                $recipient = $recipientStmt->fetch(PDO::FETCH_ASSOC);
                                                
                                                if ($recipient) {
                                                    echo "<div><small>" . htmlspecialchars($recipient['recipient_name']) . "</small></div>";
                                                }
                                            }
                                            
                                            echo " <a href='view_coupon.php?id={$row['id']}' class='small text-decoration-none'><i class='fas fa-info-circle'></i></a>";
                                        } else {
                                            echo "<span class='text-muted'>Not assigned</span>";
                                        }
                                        ?>
                                        </td>
                                        <td><?php echo number_format($row['current_balance'], 2); ?> KD</td>
                                        <td>
                                            <?php if($row['status'] === 'assigned'): ?>
                                                <span class="badge bg-success">Assigned</span>
                                            <?php elseif($row['status'] === 'fully_redeemed'): ?>
                                                <span class="badge bg-danger">Fully Redeemed</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">Available</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="view_coupon.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button class="btn btn-sm btn-secondary print-coupon" data-coupon-id="<?php echo $row['id']; ?>">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No coupons found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if($totalPages > 1): ?>
                <div class="d-flex justify-content-center mt-4">
                    <nav aria-label="Page navigation">
                        <ul class="pagination">
                            <?php if($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
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
            <div class="modal-body" id="printCouponModalBody">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="printCouponBtn">Print</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize print coupon buttons
        initPrintCouponButtons();
        
        // Function to initialize print coupon buttons
        function initPrintCouponButtons() {
            document.querySelectorAll('.print-coupon').forEach(button => {
                button.addEventListener('click', function() {
                    const couponId = this.getAttribute('data-coupon-id');
                    fetchCouponDetails(couponId);
                });
            });
        }
        
        // Function to fetch coupon details for printing
        function fetchCouponDetails(couponId) {
            const modal = new bootstrap.Modal(document.getElementById('printCouponModal'));
            modal.show();
            
            fetch('<?php echo BASE_URL; ?>admin/api/get_coupon.php?id=' + couponId)
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        const coupon = data.coupon;
                        const modalBody = document.getElementById('printCouponModalBody');
                        
                        modalBody.innerHTML = `
                            <div class="coupon-print-container">
                                <div class="coupon-header">
                                    <h3>${coupon.code}</h3>
                                    <div class="coupon-type ${coupon.coupon_type_name.toLowerCase()}">${coupon.coupon_type_name}</div>
                                </div>
                                <div class="coupon-details">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Initial Balance:</strong> ${parseFloat(coupon.initial_balance).toFixed(2)} KD</p>
                                            <p><strong>Current Balance:</strong> ${parseFloat(coupon.current_balance).toFixed(2)} KD</p>
                                            <p><strong>Issue Date:</strong> ${coupon.issue_date}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Status:</strong> ${coupon.status}</p>
                                            <p><strong>Buyer:</strong> ${coupon.buyer_name || 'Not assigned'}</p>
                                            <p><strong>Recipient:</strong> ${coupon.recipient_name || 'Not assigned'}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="coupon-qr">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(coupon.code)}" alt="QR Code">
                                </div>
                            </div>
                        `;
                        
                        document.getElementById('printCouponBtn').onclick = function() {
                            const printWindow = window.open('', '_blank');
                            printWindow.document.write(`
                                <html>
                                <head>
                                    <title>Print Coupon - ${coupon.code}</title>
                                    <style>
                                        body { font-family: Arial, sans-serif; }
                                        .coupon-print-container { max-width: 800px; margin: 0 auto; padding: 20px; border: 2px solid #333; }
                                        .coupon-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
                                        .coupon-type { padding: 5px 10px; border-radius: 5px; font-weight: bold; }
                                        .black { background-color: #000; color: #fff; }
                                        .gold { background-color: #FFD700; color: #000; }
                                        .silver { background-color: #C0C0C0; color: #000; }
                                        .coupon-details { margin-bottom: 20px; }
                                        .coupon-qr { text-align: center; }
                                    </style>
                                </head>
                                <body>
                                    ${document.querySelector('.coupon-print-container').outerHTML}
                                    <script>
                                        window.onload = function() { window.print(); setTimeout(function() { window.close(); }, 500); }
                                    </script>
                                </body>
                                </html>
                            `);
                            printWindow.document.close();
                        };
                    } else {
                        document.getElementById('printCouponModalBody').innerHTML = `
                            <div class="alert alert-danger">
                                Failed to load coupon details. Please try again.
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('printCouponModalBody').innerHTML = `
                        <div class="alert alert-danger">
                            An error occurred while fetching coupon details. Please try again.
                        </div>
                    `;
                });
        }
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
