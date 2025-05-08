<?php
// Include configuration file
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../models/Coupon.php';

// Debug mode
$debug_mode = isset($_GET['debug']) ? true : false;

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

// Debug information
$debug_info = [];
$debug_info['page'] = $page;
$debug_info['limit'] = $limit;
$debug_info['total_coupons'] = $totalCoupons;
$debug_info['total_pages'] = $totalPages;

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
                <?php if(isset($_GET['debug'])): ?>
                <div class="alert alert-info mb-3">
                    <h5>Debug Information</h5>
                    <p>Total Coupons: <?php echo $totalCoupons; ?></p>
                    <p>Current Page: <?php echo $page; ?></p>
                    <p>Total Pages: <?php echo $totalPages; ?></p>
                    <p>Coupons Count: <?php echo $coupons->rowCount(); ?></p>
                </div>
                <?php endif; ?>
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
                                            <?php if($row['has_recipients'] == 1): ?>
                                                <?php
                                                // Get the most recent recipient and total unique recipients
                                                $recipientQuery = "SELECT DISTINCT recipient_name, 
                                                                   COUNT(DISTINCT recipient_name) as unique_recipients,
                                                                   MAX(CONCAT(redemption_date, ' ', redemption_time)) as last_redemption
                                                             FROM redemption_logs 
                                                             WHERE coupon_id = ? 
                                                             GROUP BY recipient_name
                                                             ORDER BY last_redemption DESC";
                                                $stmt = $db->prepare($recipientQuery);
                                                $stmt->bindParam(1, $row['id']);
                                                $stmt->execute();
                                                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                $totalUniqueRecipients = count($recipients);
                                                $mostRecentRecipient = $totalUniqueRecipients > 0 ? $recipients[0]['recipient_name'] : '';
                                                ?>
                                                
                                                <?php if($totalUniqueRecipients > 0): ?>
                                                    <div>
                                                        <span class="badge bg-success">Assigned</span>
                                                        <?php if($totalUniqueRecipients > 1): ?>
                                                            <span class="badge bg-info ms-1" title="<?php echo $totalUniqueRecipients; ?> unique recipients">
                                                                <?php echo $totalUniqueRecipients; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mt-1">
                                                        <small><?php echo htmlspecialchars($mostRecentRecipient); ?></small>
                                                        <?php if($totalUniqueRecipients > 1): ?>
                                                            <a href="view_coupon.php?id=<?php echo $row['id']; ?>#recipients" class="ms-1 small text-decoration-none" title="View all recipients">
                                                                <i class="fas fa-users"></i> +<?php echo $totalUniqueRecipients - 1; ?> more
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Assigned</span>
                                                    <?php if($row['redemption_count'] > 1): ?>
                                                        <span class="badge bg-info ms-1" title="<?php echo $row['redemption_count']; ?> redemptions for this coupon">
                                                            <?php echo $row['redemption_count']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <a href="view_coupon.php?id=<?php echo $row['id']; ?>#recipients" class="ms-1 small text-decoration-none" title="View all recipients">
                                                        <i class="fas fa-users"></i>
                                                    </a>
                                                <?php endif; ?>
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
                
                <div class="d-flex justify-content-center mt-4" id="loadMoreContainer">
                    <button id="loadMoreButton" class="btn btn-primary" <?php echo ($page >= $totalPages) ? 'style="display: none;"' : ''; ?>>
                        Load More <i class="fas fa-spinner fa-spin d-none" id="loadingIndicator"></i>
                    </button>
                </div>
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
        
        // Current page for lazy loading
        let currentPage = <?php echo $page; ?>;
        const totalPages = <?php echo $totalPages; ?>;
        const loadMoreButton = document.getElementById('loadMoreButton');
        const loadingIndicator = document.getElementById('loadingIndicator');
        const couponsTableBody = document.getElementById('couponsTableBody');
        const searchTerm = '<?php echo htmlspecialchars($search); ?>';
        
        // Add event listener to load more button
        if (loadMoreButton) {
            loadMoreButton.addEventListener('click', function() {
                loadMoreCoupons();
            });
        }
        
        // Function to load more coupons via AJAX
        function loadMoreCoupons() {
            // Show loading indicator
            loadingIndicator.classList.remove('d-none');
            loadMoreButton.disabled = true;
            
            // Increment page number
            currentPage++;
            
            // Fetch next page of coupons
            fetch(`<?php echo BASE_URL; ?>admin/api/get_coupons.php?page=${currentPage}&search=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    if(data.success && data.coupons.length > 0) {
                        // Append new coupons to the table
                        data.coupons.forEach(coupon => {
                            const row = document.createElement('tr');
                            
                            // Determine badge class based on coupon type
                            const typeBadgeClass = coupon.coupon_type_name.toLowerCase() === 'black' ? 'badge-black' : 
                                                  (coupon.coupon_type_name.toLowerCase() === 'gold' ? 'badge-gold' : 'badge-silver');
                            
                            // Determine status badge
                            let statusBadge = '';
                            if(coupon.status === 'assigned') {
                                statusBadge = '<span class="badge bg-success">Assigned</span>';
                            } else if(coupon.status === 'fully_redeemed') {
                                statusBadge = '<span class="badge bg-danger">Fully Redeemed</span>';
                            } else {
                                statusBadge = '<span class="badge bg-primary">Available</span>';
                            }
                            
                            // Render recipient cell
                            let recipientCell = '';
                            if (coupon.has_recipients == 1) {
                                recipientCell = `<span class="badge bg-success">Assigned</span>`;
                                if (coupon.redemption_count > 0) {
                                    recipientCell += ` <span class="badge bg-info">${coupon.redemption_count}</span>`;
                                }
                                recipientCell += ` <a href="view_coupon.php?id=${coupon.id}" class="small text-decoration-none"><i class="fas fa-info-circle"></i></a>`;
                            } else {
                                recipientCell = '<span class="text-muted">Not assigned</span>';
                            }
                            
                            // Set row content
                            row.innerHTML = `
                                <td>${coupon.code}</td>
                                <td><span class="badge ${typeBadgeClass}">${coupon.coupon_type_name}</span></td>
                                <td>${coupon.buyer_name ? coupon.buyer_name : '<span class="text-muted">Not assigned</span>'}</td>
                                <td>${recipientCell}</td>
                                <td>${parseFloat(coupon.current_balance).toFixed(2)} KD</td>
                                <td>${statusBadge}</td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view_coupon.php?id=${coupon.id}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-sm btn-secondary print-coupon" data-coupon-id="${coupon.id}">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </div>
                                </td>
                            `;
                            
                            couponsTableBody.appendChild(row);
                        });
                        
                        // Reinitialize print buttons for new rows
                        initPrintCouponButtons();
                        
                        // Hide load more button if last page reached
                        if(currentPage >= totalPages) {
                            loadMoreButton.style.display = 'none';
                        }
                    } else {
                        // No more coupons or error
                        loadMoreButton.style.display = 'none';
                    }
                    
                    // Hide loading indicator
                    loadingIndicator.classList.add('d-none');
                    loadMoreButton.disabled = false;
                })
                .catch(error => {
                    console.error('Error loading more coupons:', error);
                    loadingIndicator.classList.add('d-none');
                    loadMoreButton.disabled = false;
                    
                    // Show error message
                    const errorAlert = document.createElement('div');
                    errorAlert.className = 'alert alert-danger mt-3';
                    errorAlert.textContent = 'Failed to load more coupons. Please try again.';
                    document.getElementById('loadMoreContainer').appendChild(errorAlert);
                    
                    // Auto-remove error after 5 seconds
                    setTimeout(() => {
                        errorAlert.remove();
                    }, 5000);
                });
        }
        
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
