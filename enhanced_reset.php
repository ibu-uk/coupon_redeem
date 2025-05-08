<?php
// Include configuration files
require_once 'config/config.php';
require_once 'config/database.php';

// Check if user is logged in and has admin role
if(!isLoggedIn() || !hasRole('admin')) {
    echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>";
    echo "<h2>Access Denied</h2>";
    echo "<p>You must be logged in as an administrator to access this page.</p>";
    echo "<p><a href='login.php' style='display: inline-block; background-color: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>Go to Login</a></p>";
    echo "</div>";
    exit;
}

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Function to execute SQL with error handling
function executeSql($conn, $sql, $message) {
    try {
        $conn->exec($sql);
        echo "✓ $message<br>";
        return true;
    } catch (PDOException $e) {
        echo "✗ Error in $message: " . $e->getMessage() . "<br>";
        return false;
    }
}

// Initialize variables
$error = "";
$success = "";
$resetType = isset($_POST['reset_type']) ? $_POST['reset_type'] : '';

// Process reset request
if (isset($_POST['reset_database'])) {
    // Start transaction
    $conn->beginTransaction();
    
    try {
        // Disable foreign key checks to avoid constraint issues
        executeSql($conn, "SET FOREIGN_KEY_CHECKS = 0", "Disabled foreign key checks");
        
        switch ($resetType) {
            case 'all_users':
                // Delete all users except admin
                executeSql($conn, "DELETE FROM users WHERE role != 'admin'", "Deleted all non-admin users");
                
                // Reset all coupons to available status
                executeSql($conn, "UPDATE coupons SET buyer_id = NULL, recipient_id = NULL, status = 'available'", "Reset all coupons to available status");
                
                $success = "All non-admin users have been deleted successfully and coupons reset.";
                break;
                
            case 'buyers':
                // Delete all buyers
                executeSql($conn, "DELETE FROM users WHERE role = 'buyer'", "Deleted all buyers");
                
                // Reset buyer_id in coupons table
                executeSql($conn, "UPDATE coupons SET buyer_id = NULL, status = 'available' WHERE buyer_id IS NOT NULL", "Reset buyer references in coupons");
                
                $success = "All buyers have been deleted and associated coupons reset to available.";
                break;
                
            case 'recipients':
                // Delete all recipients
                executeSql($conn, "DELETE FROM users WHERE role = 'recipient'", "Deleted all recipients");
                
                // Reset recipient_id in coupons table
                executeSql($conn, "UPDATE coupons SET recipient_id = NULL WHERE recipient_id IS NOT NULL", "Reset recipient references in coupons");
                
                $success = "All recipients have been deleted and associated coupons updated.";
                break;
                
            case 'redemption_logs':
                // Delete all redemption logs
                executeSql($conn, "DELETE FROM redemption_logs", "Deleted all redemption logs");
                
                // Reset coupon balances to match initial balance
                executeSql($conn, "UPDATE coupons SET current_balance = initial_balance, status = 'assigned' WHERE status = 'fully_redeemed'", "Reset coupon balances");
                
                $success = "All redemption logs have been deleted and coupon balances reset to their initial values.";
                break;
                
            case 'complete_reset':
                // Delete all data except admin users and coupon types
                
                // First, delete redemption logs
                executeSql($conn, "DELETE FROM redemption_logs", "Deleted all redemption logs");
                
                // Reset all coupons to available status and reset balances
                executeSql($conn, "UPDATE coupons SET buyer_id = NULL, recipient_id = NULL, status = 'available', current_balance = initial_balance", "Reset all coupons");
                
                // Delete all non-admin users
                executeSql($conn, "DELETE FROM users WHERE role != 'admin'", "Deleted all non-admin users");
                
                // Fix any Black coupons (especially B101) to ensure they have correct balance
                $blackTypeQuery = "SELECT id, value FROM coupon_types WHERE name = 'Black'";
                $stmt = $conn->prepare($blackTypeQuery);
                $stmt->execute();
                $blackType = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($blackType) {
                    $blackTypeId = $blackType['id'];
                    $blackValue = $blackType['value'];
                    
                    // Update all Black coupons to have correct initial and current balance
                    $updateBlackSql = "UPDATE coupons SET initial_balance = ?, current_balance = ? WHERE coupon_type_id = ?";
                    $updateStmt = $conn->prepare($updateBlackSql);
                    $updateStmt->execute([$blackValue, $blackValue, $blackTypeId]);
                    
                    echo "✓ Fixed all Black coupon balances to $blackValue KD<br>";
                }
                
                $success = "Complete database reset successful. All users (except admins) and redemption logs have been deleted, and all coupons reset to available status with correct balances.";
                break;
                
            default:
                $error = "Invalid reset type selected.";
                throw new Exception("Invalid reset type");
        }
        
        // Re-enable foreign key checks
        executeSql($conn, "SET FOREIGN_KEY_CHECKS = 1", "Re-enabled foreign key checks");
        
        // Commit transaction
        $conn->commit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Get current database stats
$stats = [];

// Count users by role
$userQuery = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$stmt = $conn->prepare($userQuery);
$stmt->execute();
$userStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats['admin_users'] = 0;
$stats['buyers'] = 0;
$stats['recipients'] = 0;

foreach ($userStats as $roleStat) {
    if ($roleStat['role'] == 'admin') {
        $stats['admin_users'] = $roleStat['count'];
    } elseif ($roleStat['role'] == 'buyer') {
        $stats['buyers'] = $roleStat['count'];
    } elseif ($roleStat['role'] == 'recipient') {
        $stats['recipients'] = $roleStat['count'];
    }
}

// Count redemption logs
$logsQuery = "SELECT COUNT(*) as count FROM redemption_logs";
$stmt = $conn->prepare($logsQuery);
$stmt->execute();
$logsResult = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['redemption_logs'] = $logsResult['count'];

// Count coupons by status
$couponQuery = "SELECT status, COUNT(*) as count FROM coupons GROUP BY status";
$stmt = $conn->prepare($couponQuery);
$stmt->execute();
$couponStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats['available_coupons'] = 0;
$stats['assigned_coupons'] = 0;
$stats['fully_redeemed_coupons'] = 0;

foreach ($couponStats as $statusStat) {
    if ($statusStat['status'] == 'available') {
        $stats['available_coupons'] = $statusStat['count'];
    } elseif ($statusStat['status'] == 'assigned') {
        $stats['assigned_coupons'] = $statusStat['count'];
    } elseif ($statusStat['status'] == 'fully_redeemed') {
        $stats['fully_redeemed_coupons'] = $statusStat['count'];
    }
}

// Check B101 coupon specifically
$b101Query = "SELECT c.*, ct.name as coupon_type_name, ct.value as coupon_type_value 
              FROM coupons c 
              LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id 
              WHERE c.code = 'B101'";
$stmt = $conn->prepare($b101Query);
$stmt->execute();
$b101 = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Database Reset</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .stat-card {
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h1 class="mb-4">Enhanced Database Reset</h1>
                
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
            </div>
        </div>
        
        <!-- Current Database Stats -->
        <div class="row mb-4">
            <div class="col-md-12">
                <h3>Current Database Statistics</h3>
            </div>
            
            <div class="col-md-3">
                <div class="card stat-card bg-light">
                    <div class="card-body text-center">
                        <div class="stat-value"><?php echo $stats['buyers']; ?></div>
                        <div class="stat-label">BUYERS</div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card stat-card bg-light">
                    <div class="card-body text-center">
                        <div class="stat-value"><?php echo $stats['recipients']; ?></div>
                        <div class="stat-label">RECIPIENTS</div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card stat-card bg-light">
                    <div class="card-body text-center">
                        <div class="stat-value"><?php echo $stats['redemption_logs']; ?></div>
                        <div class="stat-label">REDEMPTION LOGS</div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card stat-card bg-light">
                    <div class="card-body text-center">
                        <div class="stat-value"><?php echo ($stats['available_coupons'] + $stats['assigned_coupons'] + $stats['fully_redeemed_coupons']); ?></div>
                        <div class="stat-label">TOTAL COUPONS</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Coupon Status -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body text-center">
                        <div class="stat-value"><?php echo $stats['available_coupons']; ?></div>
                        <div class="stat-label">AVAILABLE COUPONS</div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card stat-card bg-warning text-dark">
                    <div class="card-body text-center">
                        <div class="stat-value"><?php echo $stats['assigned_coupons']; ?></div>
                        <div class="stat-label">ASSIGNED COUPONS</div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card stat-card bg-danger text-white">
                    <div class="card-body text-center">
                        <div class="stat-value"><?php echo $stats['fully_redeemed_coupons']; ?></div>
                        <div class="stat-label">FULLY REDEEMED COUPONS</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- B101 Coupon Status -->
        <?php if ($b101): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0">B101 Coupon Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table">
                                    <tr>
                                        <th>Coupon Code</th>
                                        <td><?php echo $b101['code']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Type</th>
                                        <td><?php echo $b101['coupon_type_name']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Type Value</th>
                                        <td><?php echo $b101['coupon_type_value']; ?> KD</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table">
                                    <tr>
                                        <th>Initial Balance</th>
                                        <td><?php echo $b101['initial_balance']; ?> KD</td>
                                    </tr>
                                    <tr>
                                        <th>Current Balance</th>
                                        <td>
                                            <?php echo $b101['current_balance']; ?> KD
                                            <?php if ($b101['initial_balance'] != $b101['current_balance']): ?>
                                                <span class="badge bg-danger">Discrepancy!</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td><?php echo $b101['status']; ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <?php if ($b101['initial_balance'] != $b101['current_balance']): ?>
                        <div class="alert alert-warning">
                            <strong>Warning!</strong> B101 coupon has a balance discrepancy. The current balance (<?php echo $b101['current_balance']; ?> KD) 
                            does not match the initial balance (<?php echo $b101['initial_balance']; ?> KD).
                        </div>
                        <a href="fix_b101_balance.php" class="btn btn-warning">Fix B101 Balance</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Reset Options -->
        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="card-title mb-0">Reset Database</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <strong>Warning!</strong> These actions cannot be undone. Please be careful when resetting data.
                        </div>
                        
                        <form method="post" action="" id="reset-form">
                            <div class="mb-3">
                                <label for="reset_type" class="form-label">Select Reset Type:</label>
                                <select class="form-select" id="reset_type" name="reset_type" required>
                                    <option value="">Select Reset Type</option>
                                    <option value="all_users">Delete All Users (except admins)</option>
                                    <option value="buyers">Delete All Buyers</option>
                                    <option value="recipients">Delete All Recipients</option>
                                    <option value="redemption_logs">Delete All Redemption Logs & Reset Balances</option>
                                    <option value="complete_reset">Complete Reset (All except admins)</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confirm_reset" required>
                                    <label class="form-check-label" for="confirm_reset">
                                        I understand that this action cannot be undone
                                    </label>
                                </div>
                            </div>
                            
                            <button type="button" id="reset-confirm-button" class="btn btn-danger">
                                Reset Database
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12 mb-4">
                <a href="admin/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                <a href="check_b101.php" class="btn btn-info">Check B101 Coupon</a>
            </div>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmResetModal" tabindex="-1" aria-labelledby="confirmResetModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="confirmResetModalLabel">Confirm Reset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you absolutely sure you want to proceed with this reset?</p>
                    <p id="reset-type-display" class="fw-bold"></p>
                    <p>This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="reset-form" name="reset_database" class="btn btn-danger">Yes, Reset Now</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Reset confirmation
            const resetButton = document.getElementById('reset-confirm-button');
            const resetForm = document.getElementById('reset-form');
            const resetTypeSelect = document.getElementById('reset_type');
            const confirmCheckbox = document.getElementById('confirm_reset');
            const resetTypeDisplay = document.getElementById('reset-type-display');
            
            resetButton.addEventListener('click', function() {
                // Check if form is valid
                if (resetTypeSelect.value === '') {
                    alert('Please select a reset type.');
                    return;
                }
                
                if (!confirmCheckbox.checked) {
                    alert('Please confirm that you understand this action cannot be undone.');
                    return;
                }
                
                // Set the reset type in the modal
                let resetTypeText = resetTypeSelect.options[resetTypeSelect.selectedIndex].text;
                resetTypeDisplay.textContent = resetTypeText;
                
                // Show confirmation modal
                const confirmModal = new bootstrap.Modal(document.getElementById('confirmResetModal'));
                confirmModal.show();
            });
        });
    </script>
</body>
</html>
