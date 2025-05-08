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

// Initialize redemption log object for report data
$redemptionLog = new RedemptionLog($db);
$reportData = $redemptionLog->getReportData();

// Get dashboard statistics
$dashboardStats = $redemptionLog->getDashboardStats();
$stats = $dashboardStats->fetch(PDO::FETCH_ASSOC);

// Include header
include_once '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h2 class="mb-4">BATO CLINIC - Admin Dashboard</h2>
    </div>
</div>

<!-- Highlight Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card primary">
            <div class="card-body">
                <div class="row">
                    <div class="col-8">
                        <h5 class="card-title">Total Buyers</h5>
                        <h2 class="display-6"><?php echo intval($stats['total_buyers']); ?></h2>
                    </div>
                    <div class="col-4 text-end">
                        <i class="fas fa-users fa-3x text-muted"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card success">
            <div class="card-body">
                <div class="row">
                    <div class="col-8">
                        <h5 class="card-title">Redemptions</h5>
                        <h2 class="display-6">
                            <?php 
                            // Count redemptions
                            $redemptionQuery = "SELECT COUNT(*) as count FROM redemption_logs";
                            $redemptionStmt = $db->prepare($redemptionQuery);
                            $redemptionStmt->execute();
                            $redemptionCount = $redemptionStmt->fetch(PDO::FETCH_ASSOC);
                            echo intval($redemptionCount['count']);
                            ?>
                        </h2>
                    </div>
                    <div class="col-4 text-end">
                        <i class="fas fa-receipt fa-3x text-muted"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card warning">
            <div class="card-body">
                <div class="row">
                    <div class="col-8">
                        <h5 class="card-title">Assigned Coupons</h5>
                        <h2 class="display-6"><?php echo intval($stats['assigned_coupons']); ?></h2>
                    </div>
                    <div class="col-4 text-end">
                        <i class="fas fa-ticket-alt fa-3x text-muted"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card danger">
            <div class="card-body">
                <div class="row">
                    <div class="col-8">
                        <h5 class="card-title">Fully Redeemed</h5>
                        <h2 class="display-6"><?php echo intval($stats['fully_redeemed']); ?></h2>
                    </div>
                    <div class="col-4 text-end">
                        <i class="fas fa-check-circle fa-3x text-muted"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Coupon Statistics</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Coupon Type</th>
                                <th>Total Coupons</th>
                                <th>Total Value</th>
                                <th>Total Redeemed</th>
                                <th>Total Remaining</th>
                                <th>Redemption %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $reportData->execute(); // Reset the cursor
                            $totalRow = [
                                'total_coupons' => 0,
                                'total_value' => 0,
                                'total_redeemed' => 0,
                                'total_remaining' => 0
                            ];
                            
                            while($row = $reportData->fetch(PDO::FETCH_ASSOC)):
                                // Calculate redemption percentage
                                $redemptionPercentage = $row['total_value'] > 0 ? ($row['total_redeemed'] / $row['total_value']) * 100 : 0;
                                
                                // Add to totals
                                $totalRow['total_coupons'] += intval($row['total_coupons']);
                                $totalRow['total_value'] += floatval($row['total_value']);
                                $totalRow['total_redeemed'] += floatval($row['total_redeemed']);
                                $totalRow['total_remaining'] += floatval($row['total_remaining']);
                            ?>
                                <tr>
                                    <td>
                                        <?php if($row['coupon_type'] == 'Black'): ?>
                                            <span class="badge badge-black"><?php echo $row['coupon_type']; ?></span>
                                        <?php elseif($row['coupon_type'] == 'Gold'): ?>
                                            <span class="badge badge-gold"><?php echo $row['coupon_type']; ?></span>
                                        <?php elseif($row['coupon_type'] == 'Silver'): ?>
                                            <span class="badge badge-silver"><?php echo $row['coupon_type']; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?php echo $row['coupon_type']; ?></span>
                                        <?php endif; ?>
                                        <small class="ms-2">(<?php echo number_format($row['coupon_value'], 0); ?> KD)</small>
                                    </td>
                                    <td><?php echo intval($row['total_coupons']); ?></td>
                                    <td><?php echo number_format(floatval($row['total_value']), 2); ?> KD</td>
                                    <td><?php echo number_format(floatval($row['total_redeemed']), 2); ?> KD</td>
                                    <td><?php echo number_format(floatval($row['total_remaining']), 2); ?> KD</td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo round($redemptionPercentage); ?>%" aria-valuenow="<?php echo round($redemptionPercentage); ?>" aria-valuemin="0" aria-valuemax="100"><?php echo round($redemptionPercentage); ?>%</div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            
                            <!-- Add total row -->
                            <tr class="table-active">
                                <td><strong>Total</strong></td>
                                <td><strong><?php echo $totalRow['total_coupons']; ?></strong></td>
                                <td><strong><?php echo number_format($totalRow['total_value'], 2); ?> KD</strong></td>
                                <td><strong><?php echo number_format($totalRow['total_redeemed'], 2); ?> KD</strong></td>
                                <td><strong><?php echo number_format($totalRow['total_remaining'], 2); ?> KD</strong></td>
                                <td>
                                    <?php 
                                    $totalRedemptionPercentage = $totalRow['total_value'] > 0 ? ($totalRow['total_redeemed'] / $totalRow['total_value']) * 100 : 0;
                                    ?>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo round($totalRedemptionPercentage); ?>%" aria-valuenow="<?php echo round($totalRedemptionPercentage); ?>" aria-valuemin="0" aria-valuemax="100"><?php echo round($totalRedemptionPercentage); ?>%</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Admin Action Cards -->
<div class="row mt-4">
    <div class="col-md-12">
        <h4 class="mb-3">Admin Actions</h4>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Manage Users</h5>
                <p class="card-text">Create, edit and manage all system users.</p>
                <a href="manage_users.php" class="btn btn-primary">
                    <i class="fas fa-users"></i> Manage Users
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Manage Recipients & Buyers</h5>
                <p class="card-text">Dedicated page to manage recipients and buyers with bulk actions.</p>
                <a href="manage_recipients.php" class="btn btn-success">
                    <i class="fas fa-user-tag"></i> Manage Recipients & Buyers
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Manage Coupons</h5>
                <p class="card-text">Create, edit and manage all system coupons.</p>
                <a href="manage_coupons.php" class="btn btn-warning">
                    <i class="fas fa-ticket-alt"></i> Manage Coupons
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Database Reset</h5>
                <p class="card-text">Reset database records and start fresh.</p>
                <a href="reset_database.php" class="btn btn-danger">
                    <i class="fas fa-database"></i> Reset Database
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>
