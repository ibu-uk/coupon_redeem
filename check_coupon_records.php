<?php
// Include configuration and models
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'models/User.php';
require_once 'models/Coupon.php';
require_once 'models/Service.php';
require_once 'form_protection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$coupon = new Coupon($db);

// Get all coupons
$query = "SELECT c.*, ct.name as coupon_type_name, ct.value as coupon_type_value,
         b.full_name as buyer_name, b.email as buyer_email, b.civil_id as buyer_civil_id, 
         b.mobile_number as buyer_mobile, b.file_number as buyer_file_number,
         r.full_name as recipient_name, r.email as recipient_email, r.civil_id as recipient_civil_id,
         r.mobile_number as recipient_mobile, r.file_number as recipient_file_number
  FROM coupons c
  LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
  LEFT JOIN users b ON c.buyer_id = b.id
  LEFT JOIN users r ON c.recipient_id = r.id
  ORDER BY c.code ASC";

$stmt = $db->prepare($query);
$stmt->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coupon Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .badge-black {
            background-color: #000;
            color: #fff;
        }
        .badge-gold {
            background-color: #FFD700;
            color: #000;
        }
        .badge-silver {
            background-color: #C0C0C0;
            color: #000;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container mt-4">
        <h1 class="mb-4">Coupon Records</h1>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Redeemable Coupons (Assigned Status)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Balance</th>
                                <th>Buyer</th>
                                <th>Recipient</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $redeemableCount = 0;
                            $stmt->execute();
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                if ($row['status'] == 'assigned' && $row['current_balance'] > 0) {
                                    $redeemableCount++;
                            ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><strong><?php echo $row['code']; ?></strong></td>
                                    <td>
                                        <span class="badge <?php 
                                            if (strtolower($row['coupon_type_name']) == 'black') {
                                                echo 'badge-black';
                                            } elseif (strtolower($row['coupon_type_name']) == 'gold') {
                                                echo 'badge-gold';
                                            } elseif (strtolower($row['coupon_type_name']) == 'silver') {
                                                echo 'badge-silver';
                                            } else {
                                                echo 'bg-secondary';
                                            }
                                        ?>">
                                            <?php echo $row['coupon_type_name']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $row['status']; ?></td>
                                    <td><?php echo number_format($row['current_balance'], 2); ?> KD</td>
                                    <td><?php echo $row['buyer_name']; ?></td>
                                    <td><?php echo $row['recipient_name']; ?></td>
                                    <td>
                                        <form method="post" action="redeem_coupon.php">
                                            <input type="hidden" name="lookup_token" value="<?php echo generateFormToken('lookup_coupon'); ?>">
                                            <input type="hidden" name="coupon_code" value="<?php echo $row['code']; ?>">
                                            <button type="submit" name="lookup_coupon" class="btn btn-sm btn-primary">Redeem</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php 
                                }
                            }
                            if ($redeemableCount == 0) {
                                echo '<tr><td colspan="8" class="text-center">No redeemable coupons found</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>All Coupons</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Balance</th>
                                <th>Buyer</th>
                                <th>Recipient</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $stmt->execute();
                            $totalCount = 0;
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $totalCount++;
                            ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><strong><?php echo $row['code']; ?></strong></td>
                                    <td>
                                        <span class="badge <?php 
                                            if (strtolower($row['coupon_type_name']) == 'black') {
                                                echo 'badge-black';
                                            } elseif (strtolower($row['coupon_type_name']) == 'gold') {
                                                echo 'badge-gold';
                                            } elseif (strtolower($row['coupon_type_name']) == 'silver') {
                                                echo 'badge-silver';
                                            } else {
                                                echo 'bg-secondary';
                                            }
                                        ?>">
                                            <?php echo $row['coupon_type_name']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $row['status']; ?></td>
                                    <td><?php echo number_format($row['current_balance'], 2); ?> KD</td>
                                    <td><?php echo $row['buyer_name']; ?></td>
                                    <td><?php echo $row['recipient_name']; ?></td>
                                </tr>
                            <?php 
                            }
                            if ($totalCount == 0) {
                                echo '<tr><td colspan="7" class="text-center">No coupons found</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
