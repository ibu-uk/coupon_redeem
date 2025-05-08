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

// Initialize redemption log object
$redemptionLog = new RedemptionLog($db);

// Get report data
$reportData = $redemptionLog->getReportData();

// Get monthly sales data
$currentYear = date('Y');
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;
$monthlySalesData = $redemptionLog->getMonthlySalesData($selectedYear);

// Get redemption logs with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$redemptionLogs = $redemptionLog->readAll($page, $limit, $search);
$totalLogs = $redemptionLog->countAll($search);
$totalPages = ceil($totalLogs / $limit);

// Process export request
if(isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="coupon_report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Create Excel content
    echo '<table border="1">';
    echo '<tr><th>Coupon Code</th><th>Coupon Type</th><th>Buyer</th><th>Redeemer</th><th>Amount</th><th>Service Description</th><th>Redemption Date</th></tr>';
    
    // Get all redemption logs for export (no pagination)
    $allLogs = $redemptionLog->readAll(1, 1000, $search);
    
    while($row = $allLogs->fetch(PDO::FETCH_ASSOC)) {
        echo '<tr>';
        echo '<td>' . $row['coupon_code'] . '</td>';
        echo '<td>' . $row['coupon_type'] . '</td>';
        echo '<td>' . $row['buyer_name'] . '</td>';
        echo '<td>' . $row['redeemer_name'] . '</td>';
        echo '<td>' . $row['amount'] . '</td>';
        echo '<td>' . $row['service_description'] . '</td>';
        echo '<td>' . $row['redemption_date'] . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    exit;
}

// Process PDF export
if(isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // Redirect to PDF generation script
    redirect('admin/generate_pdf_report.php?search=' . urlencode($search));
}

// Include header
include_once '../includes/header.php';
?>

<style>
    .badge-black {
        background-color: #000;
        color: #fff;
    }
    .badge-gold {
        background-color: #ffd700;
        color: #000;
    }
    .badge-silver {
        background-color: #ccc;
        color: #000;
    }
</style>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Reports</h2>
            <div class="btn-group">
                <a href="<?php echo BASE_URL; ?>admin/reports.php?export=excel&search=<?php echo urlencode($search); ?>" class="btn btn-success">
                    <i class="fas fa-file-excel me-2"></i> Export to Excel
                </a>
                <a href="<?php echo BASE_URL; ?>admin/reports.php?export=pdf&search=<?php echo urlencode($search); ?>" class="btn btn-danger">
                    <i class="fas fa-file-pdf me-2"></i> Export to PDF
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Coupon Summary</h5>
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
                            $totalCouponsCount = 0;
                            $totalCouponsValue = 0;
                            $totalRedeemedAmount = 0;
                            $totalRemainingAmount = 0;
                            
                            while($row = $reportData->fetch(PDO::FETCH_ASSOC)): 
                                $redemptionPercentage = $row['total_value'] > 0 ? ($row['total_redeemed'] / $row['total_value']) * 100 : 0;
                                
                                $totalCouponsCount += $row['total_coupons'];
                                $totalCouponsValue += $row['total_value'];
                                $totalRedeemedAmount += $row['total_redeemed'];
                                $totalRemainingAmount += $row['total_remaining'];
                            ?>
                                <tr>
                                    <td>
                                        <?php 
                                        $typeLower = strtolower($row['coupon_type']);
                                        $badgeClass = $typeLower === 'black' ? 'badge-black' : 
                                                     ($typeLower === 'gold' ? 'badge-gold' : 
                                                     ($typeLower === 'silver' ? 'badge-silver' : 'bg-secondary'));
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>">
                                            <?php echo $row['coupon_type']; ?>
                                        </span>
                                        <?php if(isset($row['coupon_value'])): ?>
                                        <small class="ms-2">(<?php echo number_format($row['coupon_value'], 0); ?> KD)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $row['total_coupons']; ?></td>
                                    <td><?php echo number_format($row['total_value'], 2); ?> KD</td>
                                    <td><?php echo number_format($row['total_redeemed'], 2); ?> KD</td>
                                    <td><?php echo number_format($row['total_remaining'], 2); ?> KD</td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" style="width: <?php echo $redemptionPercentage; ?>%;" aria-valuenow="<?php echo $redemptionPercentage; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo number_format($redemptionPercentage, 1); ?>%</div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            
                            <?php 
                                $totalRedemptionPercentage = $totalCouponsValue > 0 ? ($totalRedeemedAmount / $totalCouponsValue) * 100 : 0;
                            ?>
                            <tr class="table-active">
                                <th>Total</th>
                                <th><?php echo $totalCouponsCount; ?></th>
                                <th><?php echo number_format($totalCouponsValue, 2); ?> KD</th>
                                <th><?php echo number_format($totalRedeemedAmount, 2); ?> KD</th>
                                <th><?php echo number_format($totalRemainingAmount, 2); ?> KD</th>
                                <th>
                                    <div class="progress">
                                        <div class="progress-bar bg-dark" role="progressbar" style="width: <?php echo $totalRedemptionPercentage; ?>%;" aria-valuenow="<?php echo $totalRedemptionPercentage; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo number_format($totalRedemptionPercentage, 1); ?>%</div>
                                    </div>
                                </th>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Coupon Analytics</h5>
            </div>
            <div class="card-body py-2">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-center mb-1" style="font-size: 1rem; font-weight: 600; color: #333;">Type Distribution</h6>
                        <div style="height: 160px;">
                            <canvas id="couponDistributionChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-center mb-1" style="font-size: 1rem; font-weight: 600; color: #333;">Redemption Status</h6>
                        <div style="height: 160px;">
                            <canvas id="redemptionStatusChart"></canvas>
                        </div>
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
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Redemption Transactions</h5>
                    <form class="d-flex" action="" method="GET">
                        <input class="form-control me-2" type="search" placeholder="Search transactions..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-primary" type="submit">Search</button>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Coupon Code</th>
                                <th>Type</th>
                                <th>Buyer</th>
                                <th>Redeemer</th>
                                <th>Amount</th>
                                <th>Service</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($redemptionLogs->rowCount() > 0): ?>
                                <?php while($row = $redemptionLogs->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo $row['coupon_code']; ?></td>
                                        <td>
                                            <?php 
                                            $typeLower = strtolower($row['coupon_type']);
                                            $badgeClass = $typeLower === 'black' ? 'badge-black' : 
                                                         ($typeLower === 'gold' ? 'badge-gold' : 
                                                         ($typeLower === 'silver' ? 'badge-silver' : 'bg-secondary'));
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>">
                                                <?php echo $row['coupon_type']; ?>
                                            </span>
                                            <?php if(isset($row['coupon_value'])): ?>
                                            <small class="ms-2">(<?php echo number_format($row['coupon_value'], 0); ?> KD)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $row['buyer_name']; ?></td>
                                        <td><?php echo $row['redeemer_name']; ?></td>
                                        <td><?php echo number_format($row['amount'], 2); ?> KD</td>
                                        <td><?php echo $row['service_name']; ?></td>
                                        <td>
                                            <?php 
                                            // Combine redemption_date and redemption_time for proper datetime display
                                            $dateTimeStr = $row['redemption_date'] . ' ' . $row['redemption_time'];
                                            echo date('d M Y H:i:s', strtotime($dateTimeStr)); 
                                            ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No redemption transactions found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if($totalPages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mt-4">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&year=<?php echo $selectedYear; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&year=<?php echo $selectedYear; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&year=<?php echo $selectedYear; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Prepare data for charts
    document.addEventListener('DOMContentLoaded', function() {
        // Get data from PHP for charts
        <?php
        // Reset the cursor
        $reportData->execute();
        
        // Arrays to store data for charts
        echo "const couponTypes = [];\n";
        echo "const couponCounts = [];\n";
        echo "const couponValues = [];\n";
        echo "const couponColors = [];\n";
        
        // Define colors for different coupon types
        echo "const typeColors = {\n";
        echo "    'Black': '#000000',\n";
        echo "    'Gold': '#FFD700',\n";
        echo "    'Silver': '#C0C0C0'\n";
        echo "};\n";
        
        // Get redemption status data
        $totalAvailable = 0;
        $totalAssigned = 0;
        $totalPartiallyRedeemed = 0;
        $totalFullyRedeemed = 0;
        
        // Get coupon status counts from database
        $couponStatusQuery = "SELECT 
                                status, 
                                COUNT(*) as count 
                              FROM coupons 
                              GROUP BY status";
        $couponStatusStmt = $db->prepare($couponStatusQuery);
        $couponStatusStmt->execute();
        
        while($statusRow = $couponStatusStmt->fetch(PDO::FETCH_ASSOC)) {
            if($statusRow['status'] === 'available') {
                $totalAvailable = $statusRow['count'];
            } else if($statusRow['status'] === 'assigned') {
                $totalAssigned = $statusRow['count'];
            } else if($statusRow['status'] === 'partially_redeemed') {
                $totalPartiallyRedeemed = $statusRow['count'];
            } else if($statusRow['status'] === 'fully_redeemed') {
                $totalFullyRedeemed = $statusRow['count'];
            }
        }
        
        // Process report data for coupon type distribution
        while($row = $reportData->fetch(PDO::FETCH_ASSOC)) {
            echo "couponTypes.push('" . $row['coupon_type'] . " (" . number_format($row['coupon_value'], 0) . " KD)');\n";
            echo "couponCounts.push(" . $row['total_coupons'] . ");\n";
            echo "couponValues.push(" . $row['total_value'] . ");\n";
            
            $typeLower = strtolower($row['coupon_type']);
            if(in_array($typeLower, ['black', 'gold', 'silver'])) {
                echo "couponColors.push(typeColors['" . ucfirst($typeLower) . "']);\n";
            } else {
                // Generate a random color for other types
                echo "couponColors.push('#" . substr(md5(rand()), 0, 6) . "');\n";
            }
        }
        ?>
        
        // Create Coupon Type Distribution chart
        const distributionCtx = document.getElementById('couponDistributionChart').getContext('2d');
        new Chart(distributionCtx, {
            type: 'pie',
            data: {
                labels: couponTypes,
                datasets: [{
                    data: couponCounts,
                    backgroundColor: couponColors,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                layout: {
                    padding: 2
                },
                plugins: {
                    legend: {
                        position: 'right',
                        align: 'center',
                        labels: {
                            boxWidth: 12,
                            padding: 8,
                            font: {
                                size: 11,
                                weight: 'bold'
                            },
                            color: '#333',
                            generateLabels: function(chart) {
                                const original = Chart.overrides.pie.plugins.legend.labels.generateLabels;
                                const labels = original.call(this, chart);
                                
                                // Show full coupon type name with value
                                labels.forEach(label => {
                                    const type = label.text.split(' (')[0];
                                    const typeIndex = couponTypes.findIndex(t => t.startsWith(type));
                                    if (typeIndex >= 0) {
                                        label.text = type;
                                    }
                                });
                                
                                return labels;
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // Create Redemption Status chart
        const statusCtx = document.getElementById('redemptionStatusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Available', 'Assigned', 'Partially Redeemed', 'Fully Redeemed'],
                datasets: [{
                    data: [
                        <?php echo $totalAvailable; ?>, 
                        <?php echo $totalAssigned; ?>, 
                        <?php echo $totalPartiallyRedeemed; ?>, 
                        <?php echo $totalFullyRedeemed; ?>
                    ],
                    backgroundColor: [
                        '#4caf50',  // Available - Green
                        '#ff9800',  // Assigned - Orange
                        '#2196f3',  // Partially Redeemed - Blue
                        '#f44336'   // Fully Redeemed - Red
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                layout: {
                    padding: 2
                },
                plugins: {
                    legend: {
                        position: 'right',
                        align: 'center',
                        labels: {
                            boxWidth: 12,
                            padding: 8,
                            font: {
                                size: 11,
                                weight: 'bold'
                            },
                            color: '#333'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
