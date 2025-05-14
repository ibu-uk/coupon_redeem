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

// Get all coupons directly without pagination
$search_condition = "";
if (!empty($search)) {
    $searchTerm = "%" . htmlspecialchars(strip_tags($search)) . "%";
    $search_condition = " WHERE c.code LIKE '$searchTerm' OR b.full_name LIKE '$searchTerm' OR 
                         c.id IN (SELECT coupon_id FROM redemption_logs WHERE recipient_name LIKE '$searchTerm')";
}

// Direct query to get all coupons without pagination limits
$query = "SELECT c.*, ct.name as coupon_type_name,
             b.full_name as buyer_name, 
             COALESCE(r.full_name, 
                (SELECT rl.recipient_name FROM redemption_logs rl 
                 WHERE rl.coupon_id = c.id 
                 ORDER BY rl.redemption_date DESC, rl.redemption_time DESC 
                 LIMIT 1)
             ) as recipient_name,
             (SELECT COUNT(*) FROM redemption_logs WHERE coupon_id = c.id) as redemption_count,
             (SELECT COUNT(DISTINCT recipient_name) FROM redemption_logs WHERE coupon_id = c.id) as unique_recipients,
             CASE 
                 WHEN c.recipient_id IS NOT NULL THEN 1
                 WHEN (SELECT COUNT(*) FROM redemption_logs WHERE coupon_id = c.id) > 0 THEN 1
                 ELSE 0
             END as has_recipients
      FROM coupons c
      LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
      LEFT JOIN users b ON c.buyer_id = b.id
      LEFT JOIN users r ON c.recipient_id = r.id
      LEFT JOIN (
          SELECT coupon_id, COUNT(*) as log_count 
          FROM redemption_logs 
          GROUP BY coupon_id
      ) rl ON c.id = rl.coupon_id
      $search_condition
      ORDER BY ct.name ASC, SUBSTRING(c.code, 1, 1) ASC, CAST(SUBSTRING(c.code, 2) AS UNSIGNED) ASC";

// Prepare and execute the query
$coupons = $db->prepare($query);
$coupons->execute();

// Get total count for display
$totalCoupons = $coupons->rowCount();

// Initialize variables for debug info
$page = 1; // Since we're showing all coupons at once
$limit = $totalCoupons; // All coupons are shown
$totalPages = 1; // Only one page since all coupons are shown

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
                
                <!-- All coupons are loaded at once -->
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
            try {
                const modal = new bootstrap.Modal(document.getElementById('printCouponModal'));
                modal.show();
                
                // Show loading state
                document.getElementById('printCouponModalBody').innerHTML = `
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading coupon details...</p>
                    </div>
                `;
                
                // Fetch coupon details with proper error handling
                fetch('<?php echo BASE_URL; ?>admin/api/get_coupon.php?id=' + couponId)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if(data && data.success && data.coupon) {
                            const coupon = data.coupon;
                            const modalBody = document.getElementById('printCouponModalBody');
                            
                            // Format coupon data safely with fallbacks for missing values
                            const code = coupon.code || 'Unknown';
                            const typeName = (coupon.coupon_type_name || 'Unknown').toLowerCase();
                            const initialBalance = coupon.initial_balance ? parseFloat(coupon.initial_balance).toFixed(2) : '0.00';
                            const currentBalance = coupon.current_balance ? parseFloat(coupon.current_balance).toFixed(2) : '0.00';
                            const issueDate = coupon.issue_date || 'Not set';
                            const status = coupon.status || 'Unknown';
                            const buyerName = coupon.buyer_name || 'Not assigned';
                            const recipientName = coupon.recipient_name || 'Not assigned';
                            
                            modalBody.innerHTML = `
                                <div class="coupon-print-container">
                                    <div class="coupon-header">
                                        <h3>${code}</h3>
                                        <div class="coupon-type ${typeName}">${coupon.coupon_type_name || 'Unknown'}</div>
                                    </div>
                                    <div class="coupon-details">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Initial Balance:</strong> ${initialBalance} KD</p>
                                                <p><strong>Current Balance:</strong> ${currentBalance} KD</p>
                                                <p><strong>Issue Date:</strong> ${issueDate}</p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Status:</strong> ${status}</p>
                                                <p><strong>Buyer:</strong> ${buyerName}</p>
                                                <p><strong>Recipient:</strong> ${recipientName}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="coupon-qr">
                                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(code)}" alt="QR Code">
                                    </div>
                                </div>
                            `;
                            
                            // Setup print button functionality
                            const printBtn = document.getElementById('printCouponBtn');
                            if (printBtn) {
                                printBtn.onclick = function() {
                                    try {
                                        const printWindow = window.open('', '_blank');
                                        if (!printWindow) {
                                            alert('Please allow popups for this website to print coupons.');
                                            return;
                                        }
                                        
                                        const couponContainer = document.querySelector('.coupon-print-container');
                                        if (!couponContainer) {
                                            alert('Print content not found. Please try again.');
                                            return;
                                        }
                                        
                                        printWindow.document.write(`
                                            <html>
                                            <head>
                                                <title>Print Coupon - ${code}</title>
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
                                                ${couponContainer.outerHTML}
                                                <script>
                                                    window.onload = function() { window.print(); setTimeout(function() { window.close(); }, 500); }
                                                </script>
                                            </body>
                                            </html>
                                        `);
                                        printWindow.document.close();
                                    } catch (err) {
                                        console.error('Print error:', err);
                                        alert('An error occurred while trying to print. Please try again.');
                                    }
                                };
                            }
                        } else {
                            document.getElementById('printCouponModalBody').innerHTML = '<div class="text-center">Loading...</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('printCouponModalBody').innerHTML = '<div class="text-center">Unable to load data</div>';
                    });
            }
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
