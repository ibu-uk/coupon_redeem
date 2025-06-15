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
$coupon = new Coupon($db);
$redemptionLog = new RedemptionLog($db);

// Get parameters from query string
$couponId = isset($_GET['coupon_id']) ? intval($_GET['coupon_id']) : 0;
$recipientCivilId = isset($_GET['recipient_civil_id']) ? $_GET['recipient_civil_id'] : '';
$format = isset($_GET['format']) ? $_GET['format'] : 'html'; // html, print, pdf

// Validate parameters
if ($couponId <= 0 || empty($recipientCivilId)) {
    die("Invalid parameters");
}

// Get coupon details
$coupon->id = $couponId;
if (!$coupon->readOne()) {
    die("Coupon not found");
}

// Get redemption logs for this coupon and recipient
$query = "SELECT * 
          FROM redemption_logs
          WHERE coupon_id = ? AND recipient_civil_id = ?
          ORDER BY redemption_date DESC, redemption_time DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $couponId);
$stmt->bindParam(2, $recipientCivilId);
$stmt->execute();
$redemptionLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recipient details from the first log
$recipientName = !empty($redemptionLogs) ? $redemptionLogs[0]['recipient_name'] : 'Unknown';
$recipientMobile = !empty($redemptionLogs) ? $redemptionLogs[0]['recipient_mobile'] : '';
$recipientFileNumber = !empty($redemptionLogs) ? $redemptionLogs[0]['recipient_file_number'] : '';

// Calculate totals
$totalAmount = 0;
foreach ($redemptionLogs as $log) {
    $totalAmount += $log['amount'];
}

// Handle different output formats
if ($format === 'pdf') {
    // PDF output will be handled by JavaScript in the HTML
    $format = 'html';
    $showPdfButton = true;
} else {
    $showPdfButton = false;
}

// Include header only for HTML format
if ($format === 'html') {
    include_once '../includes/header.php';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Recipient Redemption Report</title>
    <?php if ($format === 'print'): ?>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            background-color: #007bff;
            color: white;
            padding: 10px;
            margin-bottom: 20px;
        }
        h1, h2, h3, h4 {
            margin-top: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
    <?php endif; ?>
</head>
<body <?php if ($format === 'print') echo 'onload="window.print();"'; ?>>

<?php if ($format === 'html'): ?>
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2>Recipient Redemption Report</h2>
            <p class="text-muted">Redemption details for <?php echo htmlspecialchars($recipientName); ?></p>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Redemption Details</h5>
                    <div>
                        <a href="?coupon_id=<?php echo $couponId; ?>&recipient_civil_id=<?php echo urlencode($recipientCivilId); ?>&format=print" target="_blank" class="btn btn-primary">
                            <i class="fas fa-print"></i> Print
                        </a>
                        <button id="pdfButton" class="btn btn-danger">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                    </div>
                </div>
                <div class="card-body">
<?php endif; ?>

    <div <?php if ($format === 'html') echo 'class="print-content"'; ?>>
        <?php if ($format === 'print'): ?>
        <div class="header">
            <h2>BATO CLINIC - COUPON MANAGEMENT</h2>
        </div>
        <h3 style="text-align: center;">Redemption Report for <?php echo htmlspecialchars($recipientName); ?></h3>
        <p style="text-align: center;">Generated on: <?php echo date('Y-m-d H:i'); ?></p>
        <?php endif; ?>
        
        <div class="info-section">
            <h4>Coupon Information</h4>
            <table>
                <tr>
                    <th style="width: 30%;">Coupon Code:</th>
                    <td><?php echo $coupon->code; ?></td>
                </tr>
                <tr>
                    <th>Type:</th>
                    <td><?php echo $coupon->coupon_type_name; ?></td>
                </tr>
            </table>
            
            <h4>Buyer Information</h4>
            <table>
                <tr>
                    <th style="width: 30%;">Name:</th>
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
            </table>
            
            <h4>Recipient Information</h4>
            <table>
                <tr>
                    <th style="width: 30%;">Name:</th>
                    <td><?php echo htmlspecialchars($recipientName); ?></td>
                </tr>
                <tr>
                    <th>Civil ID:</th>
                    <td><?php echo htmlspecialchars($recipientCivilId); ?></td>
                </tr>
                <?php if (!empty($recipientMobile)): ?>
                <tr>
                    <th>Mobile:</th>
                    <td><?php echo htmlspecialchars($recipientMobile); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($recipientFileNumber)): ?>
                <tr>
                    <th>File Number:</th>
                    <td><?php echo htmlspecialchars($recipientFileNumber); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Total Redeemed Amount:</th>
                    <td><?php echo number_format($totalAmount, 2); ?> KD</td>
                </tr>
            </table>
        </div>
        
        <h4>Redemption Transactions</h4>
        <table id="redemptionTable">
            <thead>
                <tr style="<?php if ($format === 'print') echo 'background-color: #007bff; color: white;'; ?>">
                    <th>Date & Time</th>
                    <th>Service</th>
                    <th>Amount</th>
                    <th>Remaining Balance</th>
                    <th>Redeemed By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($redemptionLogs) > 0): ?>
                    <?php foreach ($redemptionLogs as $log): ?>
                    <tr>
                        <td>
                            <?php echo date('Y-m-d', strtotime($log['redemption_date'])); ?><br>
                            <small><?php echo date('h:i A', strtotime($log['redemption_time'])); ?></small>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($log['service_name']); ?><br>
                            <small><?php echo htmlspecialchars($log['service_description']); ?></small>
                        </td>
                        <td><?php echo number_format($log['amount'], 2); ?> KD</td>
                        <td><?php echo number_format($log['remaining_balance'], 2); ?> KD</td>
                        <td>System Administrator</td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">No redemption records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="footer" style="page-break-inside: avoid;">
            <p style="margin: 5px 0;">Thank you for your purchase!<br>
            BATO CLINIC - HAVE A NICE DAY!<br>
            For any inquiries, please contact us: 6007 2702</p>
        </div>
    </div>

<?php if ($format === 'html'): ?>
                </div>
            </div>
            
            <div class="mt-3">
                <a href="buyer_redemption_report.php?buyer_id=<?php echo $coupon->buyer_id; ?>&coupon_id=<?php echo $couponId; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Redemption Report
                </a>
            </div>
        </div>
    </div>
</div>

<!-- PDF.js library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // PDF generation
        document.getElementById('pdfButton').addEventListener('click', function() {
            window.jspdf.jsPDF = window.jspdf.jsPDF;
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Add title
            doc.setFontSize(16);
            doc.text('BATO CLINIC - COUPON MANAGEMENT', 14, 20);
            doc.setFontSize(14);
            doc.text('Redemption Report for <?php echo addslashes($recipientName); ?>', 14, 30);
            
            // Add date
            doc.setFontSize(10);
            doc.text('Generated on: ' + new Date().toLocaleDateString(), 14, 40);
            
            // Add coupon information
            doc.setFontSize(12);
            doc.text('Coupon Information:', 14, 50);
            
            // Create a table for coupon information
            const couponData = [
                ['Coupon Code:', '<?php echo $coupon->code; ?>'],
                ['Type:', '<?php echo $coupon->coupon_type_name; ?>']
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
            
            const buyerData = [
                ['Name:', '<?php echo addslashes($coupon->buyer_name); ?>'],
                ['Civil ID:', '<?php echo $coupon->buyer_civil_id; ?>'],
                ['Mobile:', '<?php echo $coupon->buyer_mobile; ?>'],
                ['File Number:', '<?php echo $coupon->buyer_file_number; ?>']
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
            
            // Add recipient information
            const finalY2 = (doc.lastAutoTable.finalY || 50) + 10;
            doc.text('Recipient Information:', 14, finalY2);
            
            const recipientData = [
                ['Name:', '<?php echo addslashes($recipientName); ?>'],
                ['Civil ID:', '<?php echo $recipientCivilId; ?>'],
                <?php if (!empty($recipientMobile)): ?>
                ['Mobile:', '<?php echo $recipientMobile; ?>'],
                <?php endif; ?>
                <?php if (!empty($recipientFileNumber)): ?>
                ['File Number:', '<?php echo $recipientFileNumber; ?>'],
                <?php endif; ?>
                ['Total Redeemed Amount:', '<?php echo number_format($totalAmount, 2); ?> KD']
            ];
            
            doc.autoTable({
                startY: finalY2 + 5,
                head: [['Information', 'Details']],
                body: recipientData,
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
            
            // Prepare data for redemption table
            const redemptionData = [];
            <?php foreach ($redemptionLogs as $log): ?>
            redemptionData.push([
                '<?php echo date('Y-m-d', strtotime($log['redemption_date'])) . "\n" . date('h:i A', strtotime($log['redemption_time'])); ?>',
                '<?php echo addslashes($log['service_name']) . "\n" . addslashes($log['service_description']); ?>',
                '<?php echo number_format($log['amount'], 2); ?> KD',
                '<?php echo number_format($log['remaining_balance'], 2); ?> KD',
                'System Administrator'
            ]);
            <?php endforeach; ?>
            
            // Add redemption table
            const finalY3 = (doc.lastAutoTable.finalY || 50) + 10;
            doc.text('Redemption Transactions:', 14, finalY3);
            
            doc.autoTable({
                startY: finalY3 + 5,
                head: [['Date & Time', 'Service', 'Amount', 'Remaining Balance', 'Redeemed By']],
                body: redemptionData,
                theme: 'grid',
                headStyles: {
                    fillColor: [0, 123, 255],
                    textColor: [255, 255, 255],
                    fontSize: 10
                },
                styles: {
                    fontSize: 9
                }
            });
            
            // Add thank you message
            const finalY4 = (doc.lastAutoTable.finalY || 50) + 15;
            doc.setFontSize(11);
            doc.text('Thank you for your purchase!', doc.internal.pageSize.getWidth() / 2, finalY4, { align: 'center' });
            doc.text('BATO CLINIC - HAVE A NICE DAY!', doc.internal.pageSize.getWidth() / 2, finalY4 + 7, { align: 'center' });
            doc.setFontSize(10);
            doc.text('For any inquiries, please contact us: 6007 2702', doc.internal.pageSize.getWidth() / 2, finalY4 + 15, { align: 'center' });
            
            // Save the PDF
            doc.save('recipient_report_<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $recipientName); ?>.pdf');
        });
        
        <?php if ($showPdfButton): ?>
        // Auto-trigger PDF download
        document.getElementById('pdfButton').click();
        <?php endif; ?>
    });
</script>
<?php endif; ?>

</body>
</html>

<?php
// Include footer only for HTML format
if ($format === 'html') {
    include_once '../includes/footer.php';
}
?>
