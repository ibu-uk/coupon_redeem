<?php
// Include configuration and database
require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Check if user is logged in and has admin role
if(!isLoggedIn() || !hasRole('admin')) {
    echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>";
    echo "<h2>Access Denied</h2>";
    echo "<p>You must be logged in as an administrator to access this page.</p>";
    echo "<p><a href='login.php' style='display: inline-block; background-color: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>Go to Login</a></p>";
    echo "</div>";
    exit;
}

// Initialize variables
$error = "";
$success = "";

// Process the fix
if (isset($_POST['fix_balance'])) {
    try {
        // Begin transaction
        $db->beginTransaction();
        
        // Get the coupon type value for Black coupons
        $query = "SELECT value FROM coupon_types WHERE name = 'Black'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $blackValue = $result ? $result['value'] : 700;
        
        // Update B101 coupon balance
        $query = "UPDATE coupons SET current_balance = initial_balance, initial_balance = ? WHERE code = 'B101'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $blackValue);
        $stmt->execute();
        
        // Delete any redemption logs for B101
        $query = "DELETE FROM redemption_logs WHERE coupon_id IN (SELECT id FROM coupons WHERE code = 'B101')";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        // Commit transaction
        $db->commit();
        
        $success = "B101 coupon has been reset to its full balance of " . $blackValue . " KD and all redemption logs have been cleared.";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Get current B101 status
$query = "SELECT c.*, ct.name as coupon_type_name, ct.value as coupon_type_value 
          FROM coupons c 
          LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id 
          WHERE c.code = 'B101'";
$stmt = $db->prepare($query);
$stmt->execute();
$coupon = $stmt->fetch(PDO::FETCH_ASSOC);

// Count redemption logs
$logsCount = 0;
if ($coupon) {
    $query = "SELECT COUNT(*) as count FROM redemption_logs WHERE coupon_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $coupon['id']);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $logsCount = $result['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix B101 Coupon Balance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Fix B101 Coupon Balance</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">Current B101 Status</h5>
            </div>
            <div class="card-body">
                <?php if ($coupon): ?>
                    <table class="table">
                        <tr>
                            <th>Coupon Code</th>
                            <td><?php echo $coupon['code']; ?></td>
                        </tr>
                        <tr>
                            <th>Type</th>
                            <td><?php echo $coupon['coupon_type_name']; ?></td>
                        </tr>
                        <tr>
                            <th>Type Value</th>
                            <td><?php echo $coupon['coupon_type_value']; ?> KD</td>
                        </tr>
                        <tr>
                            <th>Initial Balance</th>
                            <td><?php echo $coupon['initial_balance']; ?> KD</td>
                        </tr>
                        <tr>
                            <th>Current Balance</th>
                            <td><?php echo $coupon['current_balance']; ?> KD</td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td><?php echo $coupon['status']; ?></td>
                        </tr>
                        <tr>
                            <th>Redemption Logs</th>
                            <td><?php echo $logsCount; ?> log(s)</td>
                        </tr>
                    </table>
                    
                    <form method="post" action="">
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="confirm" required>
                            <label class="form-check-label" for="confirm">I understand this will reset the B101 coupon balance and delete all redemption logs</label>
                        </div>
                        <button type="submit" name="fix_balance" class="btn btn-primary">Fix B101 Balance</button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning">
                        B101 coupon not found in the database.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mb-3">
            <a href="admin/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            <a href="check_b101.php" class="btn btn-info">View B101 Details</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
