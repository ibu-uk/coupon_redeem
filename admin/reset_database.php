<?php
// Include configuration and models
require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Check if user has admin role
if ($_SESSION['user_role'] !== 'admin') {
    $_SESSION['message'] = "You don't have permission to access this page.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . BASE_URL . "index.php");
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Initialize variables
$error = "";
$success = "";

// Process reset request
if (isset($_POST['reset_database'])) {
    $resetType = $_POST['reset_type'];
    
    try {
        // Begin transaction
        $db->beginTransaction();
        
        // Based on reset type, perform different actions
        switch ($resetType) {
            case 'all_users':
                // Delete all users except admin
                $query = "DELETE FROM users WHERE role != 'admin'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $success = "All non-admin users have been deleted successfully.";
                break;
                
            case 'buyers':
                // Delete all buyers
                $query = "DELETE FROM users WHERE role = 'buyer'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                // Reset buyer_id in coupons table
                $query = "UPDATE coupons SET buyer_id = NULL, status = 'available' WHERE buyer_id IS NOT NULL";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                $success = "All buyers have been deleted and associated coupons reset to available.";
                break;
                
            case 'recipients':
                // Delete all recipients
                $query = "DELETE FROM users WHERE role = 'recipient'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                // Reset recipient_id in coupons table
                $query = "UPDATE coupons SET recipient_id = NULL WHERE recipient_id IS NOT NULL";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                $success = "All recipients have been deleted and associated coupons updated.";
                break;
                
            case 'redemption_logs':
                // Delete all redemption logs
                $query = "DELETE FROM redemption_logs";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                // Reset coupons to assigned status if they were fully_redeemed
                $query = "UPDATE coupons SET status = 'assigned' WHERE status = 'fully_redeemed'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                $success = "All redemption logs have been deleted and fully redeemed coupons reset to assigned status.";
                break;
                
            case 'complete_reset':
                // Delete all data except admin users
                
                // First, delete redemption logs
                $query = "DELETE FROM redemption_logs";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                // Reset all coupons to available status, remove foreign key references, and restore initial balance
                $query = "UPDATE coupons SET 
                            buyer_id = NULL, 
                            recipient_id = NULL, 
                            status = 'available',
                            current_balance = initial_balance";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                // Delete all non-admin users
                $query = "DELETE FROM users WHERE role != 'admin'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                $success = "Complete reset performed successfully. All data except admin users has been reset. Coupon balances have been restored to their original values.";
                break;
                
            default:
                $error = "Invalid reset type selected.";
                throw new Exception("Invalid reset type");
        }
        
        // Commit transaction
        $db->commit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <h1 class="mb-4">Database Management</h1>
            
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
                                <option value="redemption_logs">Delete All Redemption Logs</option>
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
            
            <div class="mb-4">
                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                <a href="manage_users.php" class="btn btn-primary">Manage Users</a>
            </div>
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

<?php
// Include footer
include_once '../includes/footer.php';
?>
