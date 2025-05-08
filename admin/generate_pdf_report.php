<?php
// Include configuration file
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../models/RedemptionLog.php';

// Check if user is logged in and has admin role
if(!isLoggedIn() || !hasRole('admin')) {
    redirect('login.php');
}

// Get search parameter
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize redemption log object
$redemptionLog = new RedemptionLog($db);

// Get report data
$reportData = $redemptionLog->getReportData();

// Get all redemption logs for the report
$allLogs = $redemptionLog->readAll(1, 1000, $search);

// Require TCPDF library
require_once('../lib/tcpdf/tcpdf.php');

// Create new PDF document
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Coupon Management System');
$pdf->SetAuthor('Admin');
$pdf->SetTitle('Coupon Redemption Report');
$pdf->SetSubject('Coupon Redemption Report');
$pdf->SetKeywords('Coupon, Redemption, Report');

// Set default header data
$pdf->SetHeaderData('', 0, 'Coupon Management System', 'Redemption Report - ' . date('Y-m-d'));

// Set header and footer fonts
$pdf->setHeaderFont(Array('helvetica', '', 10));
$pdf->setFooterFont(Array('helvetica', '', 8));

// Set default monospaced font
$pdf->SetDefaultMonospacedFont('courier');

// Set margins
$pdf->SetMargins(15, 20, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 25);

// Set image scale factor
$pdf->setImageScale(1.25);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', 'B', 16);

// Title
$pdf->Cell(0, 10, 'Coupon Redemption Report', 0, 1, 'C');
$pdf->Ln(5);

// Summary Table
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Coupon Summary', 0, 1, 'L');

$pdf->SetFont('helvetica', '', 10);

// Table header
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(40, 7, 'Coupon Type', 1, 0, 'C', 1);
$pdf->Cell(30, 7, 'Total Coupons', 1, 0, 'C', 1);
$pdf->Cell(30, 7, 'Total Value', 1, 0, 'C', 1);
$pdf->Cell(30, 7, 'Redeemed', 1, 0, 'C', 1);
$pdf->Cell(30, 7, 'Remaining', 1, 0, 'C', 1);
$pdf->Cell(30, 7, 'Redemption %', 1, 1, 'C', 1);

// Reset data cursor
$reportData->execute();

// Table data
$totalCouponsCount = 0;
$totalCouponsValue = 0;
$totalRedeemedAmount = 0;
$totalRemainingAmount = 0;

while($row = $reportData->fetch(PDO::FETCH_ASSOC)) {
    $redemptionPercentage = $row['total_value'] > 0 ? ($row['total_redeemed'] / $row['total_value']) * 100 : 0;
    
    $totalCouponsCount += $row['total_coupons'];
    $totalCouponsValue += $row['total_value'];
    $totalRedeemedAmount += $row['total_redeemed'];
    $totalRemainingAmount += $row['total_remaining'];
    
    // Format coupon type display with value
    $couponTypeDisplay = $row['coupon_type'];
    if(isset($row['coupon_value'])) {
        $couponTypeDisplay .= ' (' . number_format($row['coupon_value'], 0) . ' KD)';
    }
    
    $pdf->Cell(40, 7, $couponTypeDisplay, 1, 0, 'L');
    $pdf->Cell(30, 7, $row['total_coupons'], 1, 0, 'C');
    $pdf->Cell(30, 7, number_format($row['total_value'], 2) . ' KD', 1, 0, 'R');
    $pdf->Cell(30, 7, number_format($row['total_redeemed'], 2) . ' KD', 1, 0, 'R');
    $pdf->Cell(30, 7, number_format($row['total_remaining'], 2) . ' KD', 1, 0, 'R');
    $pdf->Cell(30, 7, number_format($redemptionPercentage, 1) . '%', 1, 1, 'C');
}

// Total row
$totalRedemptionPercentage = $totalCouponsValue > 0 ? ($totalRedeemedAmount / $totalCouponsValue) * 100 : 0;

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 7, 'Total', 1, 0, 'L', 1);
$pdf->Cell(30, 7, $totalCouponsCount, 1, 0, 'C', 1);
$pdf->Cell(30, 7, number_format($totalCouponsValue, 2) . ' KD', 1, 0, 'R', 1);
$pdf->Cell(30, 7, number_format($totalRedeemedAmount, 2) . ' KD', 1, 0, 'R', 1);
$pdf->Cell(30, 7, number_format($totalRemainingAmount, 2) . ' KD', 1, 0, 'R', 1);
$pdf->Cell(30, 7, number_format($totalRedemptionPercentage, 1) . '%', 1, 1, 'C', 1);

$pdf->Ln(10);

// Transactions Table
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Redemption Transactions', 0, 1, 'L');

if($search) {
    $pdf->SetFont('helvetica', 'I', 10);
    $pdf->Cell(0, 7, 'Search: ' . $search, 0, 1, 'L');
}

$pdf->SetFont('helvetica', '', 10);

// Table header
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(30, 7, 'Coupon Code', 1, 0, 'C', 1);
$pdf->Cell(30, 7, 'Type', 1, 0, 'C', 1);
$pdf->Cell(30, 7, 'Buyer', 1, 0, 'C', 1);
$pdf->Cell(30, 7, 'Redeemer', 1, 0, 'C', 1);
$pdf->Cell(20, 7, 'Amount', 1, 0, 'C', 1);
$pdf->Cell(50, 7, 'Date', 1, 1, 'C', 1);

// Table data
if($allLogs->rowCount() > 0) {
    while($row = $allLogs->fetch(PDO::FETCH_ASSOC)) {
        // Truncate long names to fit in the cell
        $buyerName = (strlen($row['buyer_name']) > 15) ? substr($row['buyer_name'], 0, 15) . '...' : $row['buyer_name'];
        $redeemerName = (strlen($row['redeemer_name']) > 15) ? substr($row['redeemer_name'], 0, 15) . '...' : $row['redeemer_name'];
        
        // Format coupon type display with value
        $couponTypeDisplay = $row['coupon_type'];
        if(isset($row['coupon_value'])) {
            $couponTypeDisplay .= ' (' . number_format($row['coupon_value'], 0) . ' KD)';
        }
        
        $pdf->Cell(30, 7, $row['coupon_code'], 1, 0, 'L');
        $pdf->Cell(30, 7, $couponTypeDisplay, 1, 0, 'C');
        $pdf->Cell(30, 7, $buyerName, 1, 0, 'L');
        $pdf->Cell(30, 7, $redeemerName, 1, 0, 'L');
        $pdf->Cell(20, 7, number_format($row['amount'], 2) . ' KD', 1, 0, 'R');
        $pdf->Cell(50, 7, date('d M Y H:i', strtotime($row['redemption_date'])), 1, 1, 'C');
    }
} else {
    $pdf->Cell(190, 7, 'No redemption transactions found.', 1, 1, 'C');
}

// Add generation info
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 5, 'Report generated on ' . date('Y-m-d H:i:s'), 0, 1, 'R');

// Output the PDF
$pdf->Output('coupon_report_' . date('Y-m-d') . '.pdf', 'D');
?>
