<?php
// Include configuration and models
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../models/User.php';
require_once '../models/Coupon.php';
require_once '../models/RedemptionLog.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Check if user has admin or staff role
if ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'staff') {
    $_SESSION['message'] = "You don't have permission to access this page.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . BASE_URL . "index.php");
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$user = new User($db);
$coupon = new Coupon($db);
$redemptionLog = new RedemptionLog($db);

// Get buyer ID from query string if provided
$buyerId = isset($_GET['buyer_id']) ? intval($_GET['buyer_id']) : 0;
$couponId = isset($_GET['coupon_id']) ? intval($_GET['coupon_id']) : 0;

// Get all buyers who have purchased coupons
$query = "SELECT DISTINCT u.id, u.full_name, u.email, u.civil_id, u.mobile_number,
          GROUP_CONCAT(c.code ORDER BY c.code SEPARATOR ', ') as coupon_codes
          FROM users u 
          JOIN coupons c ON u.id = c.buyer_id 
          WHERE u.role = 'buyer' AND c.status IN ('assigned', 'fully_redeemed')
          GROUP BY u.id, u.full_name, u.email, u.civil_id, u.mobile_number
          ORDER BY u.full_name";
$stmt = $db->prepare($query);
$stmt->execute();
$buyers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get buyer details if buyer ID is provided
$buyerDetails = null;
if ($buyerId > 0) {
    $query = "SELECT u.id, u.full_name, u.email, u.civil_id, u.mobile_number, u.file_number, 
                    COUNT(c.id) as total_coupons,
                    SUM(c.initial_balance) as total_initial_balance,
                    SUM(c.current_balance) as total_current_balance,
                    SUM(c.initial_balance - c.current_balance) as total_used_amount
              FROM users u 
              LEFT JOIN coupons c ON u.id = c.buyer_id
              WHERE u.id = ? AND u.role = 'buyer'
              GROUP BY u.id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $buyerId);
    $stmt->execute();
    $buyerDetails = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get coupons for a specific buyer if buyer ID is provided
$coupons = [];
if ($buyerId > 0) {
    $query = "SELECT c.id, c.code, c.initial_balance, c.current_balance, 
                    ct.name as coupon_type, c.status, 
                    c.issue_date, c.recipient_id, r.full_name as recipient_name,
                    r.civil_id as recipient_civil_id, r.mobile_number as recipient_mobile
              FROM coupons c 
              LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
              LEFT JOIN users r ON c.recipient_id = r.id
              WHERE c.buyer_id = ? AND c.status IN ('assigned', 'fully_redeemed')
              ORDER BY ct.name, c.code";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $buyerId);
    $stmt->execute();
    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get redemption logs for a specific coupon if coupon ID is provided
$redemptionLogs = [];
if ($couponId > 0) {
    $redemptionLog->coupon_id = $couponId;
    $result = $redemptionLog->getByCoupon();
    $redemptionLogs = $result->fetchAll(PDO::FETCH_ASSOC);
    
    // Get coupon details
    $coupon->id = $couponId;
    $coupon->readOne();
}

// Include header
include_once '../includes/header.php';
?>

<!-- Additional CSS for reports -->
<style>
    .table-header {
        background-color: #007bff !important;
        color: white !important;
    }
    .export-buttons {
        margin-bottom: 15px;
    }
    .export-buttons .btn {
        margin-right: 10px;
    }
    .buyer-info {
        margin-bottom: 20px;
    }
    .buyer-info-table {
        width: 100%;
        margin-bottom: 20px;
        border-collapse: collapse;
    }
    .buyer-info-table th {
        background-color: #f8f9fa;
        padding: 8px;
        text-align: left;
        width: 200px;
        border: 1px solid #dee2e6;
    }
    .buyer-info-table td {
        padding: 8px;
        border: 1px solid #dee2e6;
    }
    .buyer-info-header {
        background-color: #007bff;
        color: white;
        padding: 10px;
        text-align: center;
        font-size: 16px;
        margin-bottom: 10px;
    }
    @media print {
        .no-print {
            display: none !important;
        }
        .print-only {
            display: block !important;
        }
        .buyer-info-section {
            display: block !important;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        body {
            padding: 0;
            margin: 0;
        }
        .container {
            width: 100%;
            max-width: 100%;
            padding: 0;
            margin: 0;
        }
        .card {
            border: none !important;
            box-shadow: none !important;
        }
        .card-header {
            background-color: #f8f9fa !important;
            color: #000 !important;
            border-bottom: 1px solid #ddd !important;
        }
        .table-header {
            background-color: #f8f9fa !important;
            color: #000 !important;
            border: 1px solid #ddd !important;
        }
        .buyer-info {
            page-break-inside: avoid;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th, table td {
            border: 1px solid #ddd;
        }
    }
</style>

<div class="row mb-4 no-print">
    <div class="col-md-12">
        <h2>Buyer-Recipient Redemption Report</h2>
        <p class="text-muted">Track all redemptions by recipients under each buyer</p>
    </div>
</div>

<div class="row no-print">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Select Buyer</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <form method="get" action="">
                            <div class="mb-3">
                                <label for="buyer_id" class="form-label">Buyer</label>
                                <select class="form-select" id="buyer_id" name="buyer_id" onchange="this.form.submit()">
                                    <option value="">Select a buyer</option>
                                    <?php foreach ($buyers as $buyer): ?>
                                        <option value="<?php echo $buyer['id']; ?>" <?php echo ($buyerId == $buyer['id']) ? 'selected' : ''; ?>>
                                            <?php echo $buyer['full_name']; ?> (<?php echo $buyer['civil_id']; ?>) - <?php echo $buyer['coupon_codes']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($buyerId > 0 && !empty($coupons)): ?>
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            Coupons for <?php echo $buyerDetails['full_name']; ?>
                        </h5>
                        <div class="export-buttons">
                            <button class="btn btn-success" onclick="exportTableToExcel('reportTable', 'buyer_report_<?php echo $buyerId; ?>')">
                                <i class="fas fa-file-excel"></i> Export to Excel
                            </button>
                            <button class="btn btn-danger" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf"></i> Export to PDF
                            </button>
                            <button class="btn btn-info" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="reportTable">
                            <thead>
                                <tr class="table-header">
                                    <th>Coupon Code</th>
                                    <th>Type</th>
                                    <th>Initial Balance</th>
                                    <th>Current Balance</th>
                                    <th>Used Amount</th>
                                    <th>Recipient</th>
                                    <th>Status</th>
                                    <th class="no-print">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($coupons as $c): ?>
                                    <tr>
                                        <td><?php echo $c['code']; ?></td>
                                        <td>
                                            <span class="badge <?php echo strtolower($c['coupon_type']) === 'black' ? 'badge-black' : (strtolower($c['coupon_type']) === 'gold' ? 'badge-gold' : 'badge-silver'); ?>">
                                                <?php echo $c['coupon_type']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($c['initial_balance'], 2); ?> KD</td>
                                        <td><?php echo number_format($c['current_balance'], 2); ?> KD</td>
                                        <td><?php echo number_format($c['initial_balance'] - $c['current_balance'], 2); ?> KD</td>
                                        <td>
                                            <?php
                                            // Get all recipients who have redeemed this coupon from redemption logs
                                            $recipientQuery = "SELECT DISTINCT recipient_name, recipient_civil_id, recipient_mobile, 
                                                                COUNT(id) as redemption_count, SUM(amount) as total_amount,
                                                                MAX(CONCAT(redemption_date, ' ', redemption_time)) as last_redemption
                                                         FROM redemption_logs 
                                                         WHERE coupon_id = ? 
                                                         GROUP BY recipient_name, recipient_civil_id, recipient_mobile
                                                         ORDER BY last_redemption DESC";
                                            $stmt = $db->prepare($recipientQuery);
                                            $stmt->bindParam(1, $c['id']);
                                            $stmt->execute();
                                            $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            $totalUniqueRecipients = count($recipients);
                                            
                                            if ($totalUniqueRecipients > 0):
                                                // Show the most recent recipient
                                                $mostRecent = $recipients[0];
                                            ?>
                                                <div>
                                                    <?php echo htmlspecialchars($mostRecent['recipient_name']); ?>
                                                    <small class="text-muted">
                                                        <br>Civil ID: <?php echo htmlspecialchars($mostRecent['recipient_civil_id']); ?>
                                                        <br>Mobile: <?php echo htmlspecialchars($mostRecent['recipient_mobile']); ?>
                                                    </small>
                                                </div>
                                                
                                                <?php if ($totalUniqueRecipients > 1): ?>
                                                    <div class="mt-2">
                                                        <a href="view_coupon.php?id=<?php echo $c['id']; ?>#recipients" class="btn btn-sm btn-outline-info">
                                                            <i class="fas fa-users"></i> +<?php echo $totalUniqueRecipients - 1; ?> more recipients
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            <?php elseif (!empty($c['recipient_name'])): ?>
                                                <?php echo $c['recipient_name']; ?><br>
                                                <small class="text-muted">
                                                    Civil ID: <?php echo $c['recipient_civil_id']; ?><br>
                                                    Mobile: <?php echo $c['recipient_mobile']; ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($c['status'] === 'assigned'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php elseif ($c['status'] === 'fully_redeemed'): ?>
                                                <span class="badge bg-secondary">Fully Redeemed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="no-print">
                                            <a href="?buyer_id=<?php echo $buyerId; ?>&coupon_id=<?php echo $c['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-history"></i> View Redemptions
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($buyerId > 0 && !empty($buyerDetails)): ?>
    <!-- Buyer Information Section - Hidden on screen but visible in print -->
    <div class="buyer-info-section print-only" style="display: none;">
        <div class="buyer-info-header">BATO CLINIC - COUPON MANAGEMENT</div>
        <h3 style="text-align: center;">Buyer Information</h3>
        <table class="buyer-info-table">
            <tr>
                <th>Buyer Name:</th>
                <td><?php echo $buyerDetails['full_name']; ?></td>
            </tr>
            <tr>
                <th>Civil ID:</th>
                <td><?php echo $buyerDetails['civil_id']; ?></td>
            </tr>
            <tr>
                <th>Mobile Number:</th>
                <td><?php echo $buyerDetails['mobile_number']; ?></td>
            </tr>
            <tr>
                <th>File Number:</th>
                <td><?php echo $buyerDetails['file_number']; ?></td>
            </tr>
            <tr>
                <th>Total Coupons:</th>
                <td><?php echo $buyerDetails['total_coupons']; ?></td>
            </tr>
            <tr>
                <th>Total Initial Balance:</th>
                <td><?php echo number_format($buyerDetails['total_initial_balance'], 2); ?> KD</td>
            </tr>
            <tr>
                <th>Total Current Balance:</th>
                <td><?php echo number_format($buyerDetails['total_current_balance'], 2); ?> KD</td>
            </tr>
            <tr>
                <th>Total Used Amount:</th>
                <td><?php echo number_format($buyerDetails['total_used_amount'], 2); ?> KD</td>
            </tr>
        </table>
    </div>
<?php endif; ?>

<?php if ($couponId > 0 && !empty($redemptionLogs)): ?>
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            Redemption History for Coupon <?php echo $coupon->code; ?>
                        </h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Coupon Details:</strong><br>
                                <strong>Code:</strong> <?php echo $coupon->code; ?><br>
                                <strong>Type:</strong> <?php echo $coupon->coupon_type_name; ?><br>
                                <strong>Initial Balance:</strong> <?php echo number_format($coupon->initial_balance, 2); ?> KD<br>
                                <strong>Current Balance:</strong> <?php echo number_format($coupon->current_balance, 2); ?> KD<br>
                                <strong>Used Amount:</strong> <?php echo number_format($coupon->initial_balance - $coupon->current_balance, 2); ?> KD
                            </div>
                            <div class="col-md-6">
                                <strong>Buyer Information:</strong><br>
                                <strong>Name:</strong> <?php echo $coupon->buyer_name; ?><br>
                                <strong>Civil ID:</strong> <?php echo $coupon->buyer_civil_id; ?><br>
                                <strong>Mobile:</strong> <?php echo $coupon->buyer_mobile; ?><br>
                                <strong>File Number:</strong> <?php echo $coupon->buyer_file_number; ?><br>
                                <strong>Purchase Date:</strong> <?php echo $coupon->issue_date; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                        <h5 class="mb-0">Redemption Transactions</h5>
                        <div class="export-buttons no-print">
                            <button class="btn btn-sm btn-success" onclick="exportTableToExcel('redemptionTable', 'redemption_history')">
                                <i class="fas fa-file-excel"></i> Export to Excel
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="exportCouponRedemptionToPDF()">
                                <i class="fas fa-file-pdf"></i> PDF
                            </button>
                            <button class="btn btn-sm btn-primary" onclick="printCouponRedemption()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="redemptionTable">
                            <thead>
                                <tr class="table-header">
                                    <th>Date & Time</th>
                                    <th>Recipient Details</th>
                                    <th>Service Information</th>
                                    <th>Amount</th>
                                    <th>Remaining Balance</th>
                                    <th>Redeemed By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($redemptionLogs as $log): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo date('Y-m-d', strtotime($log['redemption_date'])); ?></strong><br>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($log['redemption_time'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo $log['recipient_name']; ?></strong><br>
                                            <small>
                                                <strong>Civil ID:</strong> <?php echo $log['recipient_civil_id']; ?><br>
                                                <strong>Mobile:</strong> <?php echo $log['recipient_mobile']; ?><br>
                                                <?php if (!empty($log['recipient_file_number'])): ?>
                                                <strong>File Number:</strong> <?php echo $log['recipient_file_number']; ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong><?php echo $log['service_name']; ?></strong><br>
                                            <small><?php echo $log['service_description']; ?></small>
                                        </td>
                                        <td class="text-danger">
                                            <strong><?php echo number_format($log['amount'], 2); ?> KD</strong>
                                        </td>
                                        <td>
                                            <strong><?php echo number_format($log['remaining_balance'], 2); ?> KD</strong>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <div><?php echo $log['redeemer_name']; ?></div>
                                                <div class="mt-2 no-print">
                                                    <a href="print_recipient_report.php?coupon_id=<?php echo $couponId; ?>&recipient_civil_id=<?php echo urlencode($log['recipient_civil_id']); ?>&format=print" target="_blank" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-print"></i> Print
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <a href="?buyer_id=<?php echo $buyerId; ?>" class="btn btn-primary mt-3 no-print">
                        <i class="fas fa-arrow-left"></i> Back to Coupons
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Coupon Redemption Print View - Hidden on screen but visible in print -->
<div class="coupon-redemption-print print-only" style="display: none;" id="couponRedemptionPrint">
    <div class="buyer-info-header">BATO CLINIC - COUPON MANAGEMENT</div>
    <h3 style="text-align: center;">Redemption History for Coupon <?php echo $coupon->code; ?></h3>
    <p style="text-align: center;">Generated on: <?php echo date('Y-m-d H:i'); ?></p>
    
    <div class="row" style="margin-top: 20px;">
        <div class="col-md-6" style="width: 50%; float: left;">
            <h4>Coupon Details</h4>
            <table class="buyer-info-table">
                <tr>
                    <th>Code:</th>
                    <td><?php echo $coupon->code; ?></td>
                </tr>
                <tr>
                    <th>Type:</th>
                    <td><?php echo $coupon->coupon_type_name; ?></td>
                </tr>
                <tr>
                    <th>Initial Balance:</th>
                    <td><?php echo number_format($coupon->initial_balance, 2); ?> KD</td>
                </tr>
                <tr>
                    <th>Current Balance:</th>
                    <td><?php echo number_format($coupon->current_balance, 2); ?> KD</td>
                </tr>
                <tr>
                    <th>Used Amount:</th>
                    <td><?php echo number_format($coupon->initial_balance - $coupon->current_balance, 2); ?> KD</td>
                </tr>
            </table>
        </div>
        <div class="col-md-6" style="width: 50%; float: left;">
            <h4>Buyer Information</h4>
            <table class="buyer-info-table">
                <tr>
                    <th>Name:</th>
                    <td><?php echo $coupon->buyer_name; ?></td>
                </tr>
                <tr>
                    <th>Civil ID:</th>
                    <td><?php echo $coupon->buyer_civil_id; ?></td>
                </tr>
                <tr>
                    <th>Mobile:</th>
                    <td><?php echo $coupon->buyer_mobile; ?></td>
                </tr>
                <tr>
                    <th>File Number:</th>
                    <td><?php echo $coupon->buyer_file_number; ?></td>
                </tr>
                <tr>
                    <th>Issue Date:</th>
                    <td><?php echo date('Y-m-d', strtotime($coupon->issue_date)); ?></td>
                </tr>
            </table>
        </div>
    </div>
    
    <div style="clear: both; margin-top: 20px;">
        <h4>Redemption Transactions</h4>
        <table class="table table-bordered" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background-color: #007bff; color: white;">
                    <th style="border: 1px solid #dee2e6; padding: 8px;">Date & Time</th>
                    <th style="border: 1px solid #dee2e6; padding: 8px;">Recipient Details</th>
                    <th style="border: 1px solid #dee2e6; padding: 8px;">Service Information</th>
                    <th style="border: 1px solid #dee2e6; padding: 8px;">Amount</th>
                    <th style="border: 1px solid #dee2e6; padding: 8px;">Remaining Balance</th>
                    <th style="border: 1px solid #dee2e6; padding: 8px;">Redeemed By</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($redemptionLogs as $log): ?>
                    <tr>
                        <td style="border: 1px solid #dee2e6; padding: 8px;">
                            <strong><?php echo date('Y-m-d', strtotime($log['redemption_date'])); ?></strong><br>
                            <small><?php echo date('h:i A', strtotime($log['redemption_time'])); ?></small>
                        </td>
                        <td style="border: 1px solid #dee2e6; padding: 8px;">
                            <strong><?php echo $log['recipient_name']; ?></strong><br>
                            <small>
                                <strong>Civil ID:</strong> <?php echo $log['recipient_civil_id']; ?><br>
                                <strong>Mobile:</strong> <?php echo $log['recipient_mobile']; ?><br>
                                <?php if (!empty($log['recipient_file_number'])): ?>
                                <strong>File Number:</strong> <?php echo $log['recipient_file_number']; ?>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td style="border: 1px solid #dee2e6; padding: 8px;">
                            <strong><?php echo $log['service_name']; ?></strong><br>
                            <small><?php echo $log['service_description']; ?></small>
                        </td>
                        <td style="border: 1px solid #dee2e6; padding: 8px; color: #dc3545;">
                            <strong><?php echo number_format($log['amount'], 2); ?> KD</strong>
                        </td>
                        <td style="border: 1px solid #dee2e6; padding: 8px;">
                            <strong><?php echo number_format($log['remaining_balance'], 2); ?> KD</strong>
                        </td>
                        <td style="border: 1px solid #dee2e6; padding: 8px;">
                            <?php echo $log['redeemer_name']; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add necessary scripts for export functionality -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
    // Export to Excel function
    function exportTableToExcel(tableID, filename = '') {
        const downloadLink = document.createElement("a");
        const dataType = 'application/vnd.ms-excel';
        const table = document.getElementById(tableID);
        const tableHTML = table.outerHTML.replace(/ /g, '%20');
        
        filename = filename ? filename + '.xls' : 'excel_data.xls';
        
        // Create download link element
        downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
        downloadLink.download = filename;
        
        // Triggering the function
        downloadLink.click();
    }
    
    // Print Coupon Redemption History
    function printCouponRedemption() {
        const printContents = document.getElementById('couponRedemptionPrint').innerHTML;
        const originalContents = document.body.innerHTML;
        
        document.body.innerHTML = `
            <html>
                <head>
                    <title>Redemption History for Coupon <?php echo $coupon->code; ?></title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        @media print {
                            body {
                                padding: 20px;
                            }
                            table {
                                width: 100%;
                                border-collapse: collapse;
                            }
                            th, td {
                                border: 1px solid #dee2e6;
                                padding: 8px;
                                text-align: left;
                            }
                            th {
                                background-color: #007bff !important;
                                color: white !important;
                                -webkit-print-color-adjust: exact;
                                print-color-adjust: exact;
                            }
                        }
                    </style>
                </head>
                <body>
                    ${printContents}
                </body>
            </html>
        `;
        
        window.print();
        document.body.innerHTML = originalContents;
        location.reload();
    }
    
    // Export Coupon Redemption to PDF
    function exportCouponRedemptionToPDF() {
        window.jspdf.jsPDF = window.jspdf.jsPDF;
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        // Add title
        doc.setFontSize(16);
        doc.text('BATO CLINIC - COUPON MANAGEMENT', 14, 20);
        doc.setFontSize(14);
        doc.text('Redemption History for Coupon <?php echo $coupon->code; ?>', 14, 30);
        
        // Add date
        doc.setFontSize(10);
        doc.text('Generated on: ' + new Date().toLocaleDateString(), 14, 40);
        
        // Add coupon information
        doc.setFontSize(12);
        doc.text('Coupon Information:', 14, 50);
        
        // Create a table for coupon information
        const couponData = [
            ['Code:', '<?php echo $coupon->code; ?>'],
            ['Type:', '<?php echo $coupon->coupon_type_name; ?>'],
            ['Initial Balance:', '<?php echo number_format($coupon->initial_balance, 2); ?> KD'],
            ['Current Balance:', '<?php echo number_format($coupon->current_balance, 2); ?> KD'],
            ['Used Amount:', '<?php echo number_format($coupon->initial_balance - $coupon->current_balance, 2); ?> KD']
        ];
        
        doc.autoTable({
            startY: 55,
            head: [['Information', 'Details']],
            body: couponData,
            theme: 'grid',
            headStyles: {
                fillColor: [0, 123, 255],
                textColor: [255, 255, 255],
                fontSize: 10
            },
            styles: {
                fontSize: 9
            },
            columnStyles: {
                0: {fontStyle: 'bold', cellWidth: 60},
                1: {cellWidth: 80}
            }
        });
        
        // Add buyer information
        const finalY1 = (doc.lastAutoTable.finalY || 50) + 10;
        doc.text('Buyer Information:', 14, finalY1);
        
        // Create a table for buyer information
        const buyerData = [
            ['Name:', '<?php echo $coupon->buyer_name; ?>'],
            ['Civil ID:', '<?php echo $coupon->buyer_civil_id; ?>'],
            ['Mobile:', '<?php echo $coupon->buyer_mobile; ?>'],
            ['File Number:', '<?php echo $coupon->buyer_file_number; ?>'],
            ['Issue Date:', '<?php echo date("Y-m-d", strtotime($coupon->issue_date)); ?>']
        ];
        
        doc.autoTable({
            startY: finalY1 + 5,
            head: [['Information', 'Details']],
            body: buyerData,
            theme: 'grid',
            headStyles: {
                fillColor: [0, 123, 255],
                textColor: [255, 255, 255],
                fontSize: 10
            },
            styles: {
                fontSize: 9
            },
            columnStyles: {
                0: {fontStyle: 'bold', cellWidth: 60},
                1: {cellWidth: 80}
            }
        });
        
        // Add redemption transactions
        const finalY2 = (doc.lastAutoTable.finalY || 50) + 10;
        doc.text('Redemption Transactions:', 14, finalY2);
        
        // Add redemption table
        doc.autoTable({ 
            html: '#redemptionTable',
            startY: finalY2 + 5,
            styles: {
                fontSize: 8
            },
            columnStyles: {
                0: {cellWidth: 25}, // Date & Time
                1: {cellWidth: 35}, // Recipient Details
                2: {cellWidth: 35}, // Service Information
                3: {cellWidth: 20}, // Amount
                4: {cellWidth: 25}, // Remaining Balance
                5: {cellWidth: 30}  // Redeemed By
            },
            headStyles: {
                fillColor: [0, 123, 255],
                textColor: [255, 255, 255]
            }
        });
        
        // Save the PDF
        doc.save('Coupon_<?php echo $coupon->code; ?>_Redemption_History.pdf');
    }
    
    // Export to PDF function - simplified and more reliable
    function exportToPDF() {
        window.jspdf.jsPDF = window.jspdf.jsPDF;
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        // Add title
        doc.setFontSize(16);
        doc.text('BATO CLINIC - COUPON MANAGEMENT', 14, 20);
        doc.setFontSize(14);
        doc.text('Buyer Redemption Report', 14, 30);
        
        // Add date
        doc.setFontSize(10);
        doc.text('Generated on: ' + new Date().toLocaleDateString(), 14, 40);
        
        // Add buyer information
        doc.setFontSize(12);
        doc.text('Buyer Information:', 14, 50);
        
        // Create a table for buyer information
        const buyerData = [
            ['Buyer Name:', '<?php echo $buyerDetails['full_name']; ?>'],
            ['Civil ID:', '<?php echo $buyerDetails['civil_id']; ?>'],
            ['Mobile Number:', '<?php echo $buyerDetails['mobile_number']; ?>'],
            ['File Number:', '<?php echo $buyerDetails['file_number']; ?>'],
            ['Total Coupons:', '<?php echo $buyerDetails['total_coupons']; ?>'],
            ['Total Initial Balance:', '<?php echo number_format($buyerDetails['total_initial_balance'], 2); ?> KD'],
            ['Total Current Balance:', '<?php echo number_format($buyerDetails['total_current_balance'], 2); ?> KD'],
            ['Used Amount:', '<?php echo number_format($buyerDetails['total_used_amount'], 2); ?> KD']
        ];
        
        doc.autoTable({
            startY: 55,
            head: [['Information', 'Details']],
            body: buyerData,
            theme: 'grid',
            headStyles: {
                fillColor: [0, 123, 255],
                textColor: [255, 255, 255],
                fontSize: 10
            },
            styles: {
                fontSize: 9
            },
            columnStyles: {
                0: {fontStyle: 'bold', cellWidth: 60},
                1: {cellWidth: 80}
            }
        });
        
        // Get the final Y position after the buyer info table
        const finalY = (doc.lastAutoTable.finalY || 50) + 10;
        
        // Add redemption table
        doc.autoTable({ 
            html: '#reportTable',
            startY: finalY,
            styles: {
                fontSize: 8
            },
            columnStyles: {
                0: {cellWidth: 25},
                1: {cellWidth: 35},
                2: {cellWidth: 35},
                3: {cellWidth: 20},
                4: {cellWidth: 25},
                5: {cellWidth: 30}
            },
            headStyles: {
                fillColor: [0, 123, 255],
                textColor: [255, 255, 255]
            }
        });
        
        // Save the PDF
        doc.save('buyer_report_<?php echo $buyerId; ?>.pdf');
    }
    
    // Export to PDF function
    function exportTableToPDF(tableID, filename = '') {
        window.jspdf.jsPDF = window.jspdf.jsPDF;
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        // Add title
        doc.setFontSize(16);
        doc.text('BATO CLINIC - COUPON MANAGEMENT', 14, 20);
        doc.setFontSize(14);
        doc.text('Redemption Report', 14, 30);
        
        // Add date
        doc.setFontSize(10);
        doc.text('Generated on: ' + new Date().toLocaleDateString(), 14, 40);
        
        // Add table
        doc.autoTable({ 
            html: '#' + tableID,
            startY: 45,
            styles: {
                fontSize: 8
            },
            columnStyles: {
                0: {cellWidth: 25},
                1: {cellWidth: 40},
                2: {cellWidth: 40},
                3: {cellWidth: 20},
                4: {cellWidth: 20},
                5: {cellWidth: 25}
            },
            headStyles: {
                fillColor: [0, 123, 255],
                textColor: [255, 255, 255]
            }
        });
        
        // Save the PDF
        doc.save(filename + '.pdf');
    }
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
