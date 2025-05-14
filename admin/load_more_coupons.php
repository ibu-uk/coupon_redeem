<?php
// Include configuration file
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../models/Coupon.php';

// Check if user is logged in and has admin role
if(!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize coupon object
$coupon = new Coupon($db);

// Get parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get coupons with pagination
$coupons = $coupon->readAll($page, $limit, $search);

// Start output buffer
ob_start();

// Output coupons as HTML
if($coupons->rowCount() > 0) {
    while($row = $coupons->fetch(PDO::FETCH_ASSOC)) {
        ?>
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
                    <span class="badge bg-success">Assigned</span>
                    <?php if($row['redemption_count'] > 0): ?>
                        <span class="badge bg-info ms-1"><?php echo $row['redemption_count']; ?></span>
                    <?php endif; ?>
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
        <?php
    }
} else {
    echo '<tr><td colspan="7" class="text-center">No more coupons found</td></tr>';
}

// Get the output buffer content
$html = ob_get_clean();

// Return JSON response
header('Content-Type: application/json');

// Make sure to properly encode HTML content and set has_more flag
echo json_encode([
    'html' => $html,
    'has_more' => ($coupons->rowCount() >= $limit)
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
