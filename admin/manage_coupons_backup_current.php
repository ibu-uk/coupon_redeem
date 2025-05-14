<?php
// Include configuration file
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../models/Coupon.php';

// Check if user is logged in and has admin or staff role
if(!isLoggedIn() || (!hasRole('admin') && !hasRole('staff'))) {
    redirect('../login.php');
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
$limit = 50; // Increased limit to show more coupons per page

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
                                        <td>
                                            <?php if($row['status'] == 'assigned'): ?>
                                                <?php echo $row['buyer_name'] ? $row['buyer_name'] : '<span class="badge bg-success">Assigned</span>'; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            // Check for redemption logs
                                            $redemptionQuery = "SELECT COUNT(*) as count FROM redemption_logs WHERE coupon_id = ?";
                                            $redemptionStmt = $db->prepare($redemptionQuery);
                                            $redemptionStmt->bindParam(1, $row['id']);
                                            $redemptionStmt->execute();
                                            $redemptionResult = $redemptionStmt->fetch(PDO::FETCH_ASSOC);
                                            
                                            if($redemptionResult['count'] > 0): 
                                            ?>
                                                <span class="badge bg-success">Assigned</span>
                                                <span class="badge bg-info ms-1"><?php echo $redemptionResult['count']; ?></span>
                                                <a href="view_coupon.php?id=<?php echo $row['id']; ?>" class="ms-1 small text-decoration-none">
                                                    <i class="fas fa-info-circle"></i>
                                                </a>
                                            <?php elseif($row['recipient_name']): ?>
                                                <span class="badge bg-success">Assigned</span>
                                                <a href="view_coupon.php?id=<?php echo $row['id']; ?>" class="ms-1 small text-decoration-none">
                                                    <i class="fas fa-info-circle"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
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
                            
                            <?php 
                            // Show limited page numbers with ellipsis
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            if ($startPage > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '">1</a></li>';
                                if ($startPage > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            for($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php 
                            endfor;
                            
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '&search=' . urlencode($search) . '">' . $totalPages . '</a></li>';
                            }
                            ?>
                            
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
        document.querySelectorAll('.print-coupon').forEach(button => {
            button.addEventListener('click', function() {
                const couponId = this.getAttribute('data-coupon-id');
                printCoupon(couponId);
            });
        });
        
        // Function to print coupon
        function printCoupon(couponId) {
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('printCouponModal'));
            modal.show();
            
            // Get coupon details
            fetch('view_coupon.php?id=' + couponId + '&format=json')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.id) {
                        const coupon = data;
                        const modalBody = document.getElementById('printCouponModalBody');
                        
                        // Create print content
                        modalBody.innerHTML = `
                            <div class="coupon-print-container">
                                <div class="coupon-header d-flex justify-content-between align-items-center mb-4">
                                    <div class="logo-area">
                                        <h2 class="mb-0">Coupon Management System</h2>
                                        <p class="text-muted">Official Coupon Certificate</p>
                                    </div>
                                    <div class="coupon-type-badge ${coupon.coupon_type_name.toLowerCase()}">
                                        ${coupon.coupon_type_name}
                                        <div class="value-text">${parseFloat(coupon.coupon_type_value).toFixed(0)} KD</div>
                                    </div>
                                </div>
                                
                                <div class="coupon-code-section text-center mb-4 p-3 border bg-light rounded">
                                    <h3 class="mb-1">Coupon Code</h3>
                                    <h1 class="display-4 mb-0 fw-bold">${coupon.code}</h1>
                                </div>
                                
                                <div class="row mb-4">
                                    <div class="col-md-7">
                                        <div class="card h-100">
                                            <div class="card-header bg-light">
                                                <h5 class="mb-0">Coupon Details</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-6"><strong>Initial Balance:</strong></div>
                                                    <div class="col-6 text-end">${parseFloat(coupon.initial_balance).toFixed(2)} KD</div>
                                                </div>
                                                <hr class="my-2">
                                                <div class="row">
                                                    <div class="col-6"><strong>Current Balance:</strong></div>
                                                    <div class="col-6 text-end fw-bold">${parseFloat(coupon.current_balance).toFixed(2)} KD</div>
                                                </div>
                                                <hr class="my-2">
                                                <div class="row">
                                                    <div class="col-6"><strong>Issue Date:</strong></div>
                                                    <div class="col-6 text-end">${coupon.issue_date || 'N/A'}</div>
                                                </div>
                                                <hr class="my-2">
                                                <div class="row">
                                                    <div class="col-6"><strong>Status:</strong></div>
                                                    <div class="col-6 text-end">
                                                        <span class="badge ${coupon.status === 'assigned' ? 'bg-success' : coupon.status === 'fully_redeemed' ? 'bg-danger' : 'bg-primary'}">
                                                            ${coupon.status.charAt(0).toUpperCase() + coupon.status.slice(1)}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="card h-100">
                                            <div class="card-header bg-light">
                                                <h5 class="mb-0">Ownership Information</h5>
                                            </div>
                                            <div class="card-body">
                                                <p><strong>Buyer:</strong> ${coupon.buyer_name || 'Not assigned'}</p>
                                                <p><strong>Recipient:</strong> ${coupon.recipient_name || 'Not assigned'}</p>
                                                <p><strong>Recipient Civil ID:</strong> ${coupon.recipient_civil_id || 'N/A'}</p>
                                                <p><strong>Recipient Mobile:</strong> ${coupon.recipient_mobile || 'N/A'}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="validation-note p-3 border rounded">
                                            <h6><i class="fas fa-info-circle me-2"></i>Validation Information</h6>
                                            <p class="mb-0 small">This coupon can be validated by entering the coupon code in the redemption system.</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="footer-note text-center mt-4 pt-3 border-top">
                                    <p class="mb-1"><small>This coupon is subject to terms and conditions of the Coupon Management System.</small></p>
                                    <p class="mb-0"><small>Generated on: ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}</small></p>
                                </div>
                            </div>
                        `;
                        
                        // Set up print button
                        document.getElementById('printCouponBtn').onclick = function() {
                            const printWindow = window.open('', '_blank');
                            printWindow.document.write(`
                                <html>
                                <head>
                                    <title>Print Coupon - ${coupon.code}</title>
                                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
                                    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
                                    <style>
                                        @media print {
                                            @page { size: A4; margin: 0.5cm; }
                                            body { padding: 0; margin: 0; }
                                            .no-print { display: none !important; }
                                        }
                                        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f8f9fa; }
                                        .coupon-print-container { max-width: 800px; margin: 20px auto; padding: 25px; border: 1px solid #ddd; background-color: white; box-shadow: 0 0 15px rgba(0,0,0,0.1); border-radius: 8px; }
                                        
                                        /* Header styles */
                                        .logo-area h2 { color: #333; font-weight: 600; }
                                        .logo-area p { color: #6c757d; }
                                        
                                        /* Coupon type badge styles */
                                        .coupon-type-badge { padding: 10px 15px; border-radius: 8px; font-weight: bold; text-align: center; position: relative; min-width: 120px; }
                                        .coupon-type-badge .value-text { font-size: 0.85rem; margin-top: 3px; }
                                        .black { background-color: #000; color: #fff; }
                                        .gold { background-color: #FFD700; color: #000; box-shadow: 0 0 10px rgba(255, 215, 0, 0.5); }
                                        .silver { background-color: #C0C0C0; color: #000; box-shadow: 0 0 10px rgba(192, 192, 192, 0.5); }
                                        
                                        /* Coupon code section */
                                        .coupon-code-section { background-color: #f8f9fa; border: 1px dashed #6c757d !important; }
                                        .coupon-code-section h1 { letter-spacing: 2px; }
                                        
                                        /* Card styles */
                                        .card { border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; height: 100%; }
                                        .card-header { background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; padding: 12px 15px; }
                                        .card-body { padding: 15px; }
                                        
                                        /* Validation note */
                                        .validation-note { background-color: #f8f9fa; }
                                        
                                        /* Footer */
                                        .footer-note { color: #6c757d; }
                                        
                                        /* Print button */
                                        .print-controls { text-align: center; margin: 20px 0; }
                                    </style>
                                </head>
                                <body>
                                    ${document.querySelector('.coupon-print-container').outerHTML}
                                    <div class="print-controls no-print">
                                        <button class="btn btn-primary" onclick="window.print();">Print Now</button>
                                        <button class="btn btn-secondary" onclick="window.close();">Close</button>
                                    </div>
                                </body>
                                </html>
                            `);
                            printWindow.document.close();
                            // Directly trigger print dialog
                            printWindow.onload = function() {
                                printWindow.print();
                            };
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
