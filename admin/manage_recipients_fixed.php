<?php
// Include configuration and models
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../models/User.php';
require_once '../models/Coupon.php';

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

// Initialize objects
$user = new User($db);
$coupon = new Coupon($db);

// Initialize variables
$error = "";
$success = "";

// Process user deletion
if (isset($_POST['delete_user'])) {
    $userId = $_POST['user_id'];
    
    if (empty($userId)) {
        $error = "User ID is required.";
    } else {
        // Check if user is a recipient or buyer
        $user->id = $userId;
        if ($user->readOne() && ($user->role === 'recipient' || $user->role === 'buyer')) {
            // If user is a recipient, update any coupons assigned to them
            if ($user->role === 'recipient') {
                $query = "UPDATE coupons SET recipient_id = NULL WHERE recipient_id = ?";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $userId);
                $stmt->execute();
            }
            
            // If user is a buyer, update any coupons assigned to them
            if ($user->role === 'buyer') {
                $query = "UPDATE coupons SET buyer_id = NULL, status = 'available' WHERE buyer_id = ?";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $userId);
                $stmt->execute();
            }
            
            // Delete user
            if ($user->delete()) {
                $success = "User deleted successfully and associated coupons updated.";
            } else {
                $error = "Failed to delete user.";
            }
        } else {
            $error = "Cannot delete admin users or user not found.";
        }
    }
}

// Process bulk deletion
if (isset($_POST['bulk_delete'])) {
    $userType = $_POST['user_type'];
    
    if ($userType === 'recipients') {
        // Update coupons first
        $query = "UPDATE coupons SET recipient_id = NULL WHERE recipient_id IS NOT NULL";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        // Delete all recipients
        $query = "DELETE FROM users WHERE role = 'recipient'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $success = "All recipients have been deleted and associated coupons updated.";
    } elseif ($userType === 'buyers') {
        // Update coupons first
        $query = "UPDATE coupons SET buyer_id = NULL, status = 'available' WHERE buyer_id IS NOT NULL";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        // Delete all buyers
        $query = "DELETE FROM users WHERE role = 'buyer'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $success = "All buyers have been deleted and associated coupons reset to available.";
    } else {
        $error = "Invalid user type selected.";
    }
}

// Get recipients from users table
$recipients = [];
$query = "SELECT * FROM users WHERE role = 'recipient' ORDER BY id DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Also get recipients from redemption logs who may not be in users table
$query = "SELECT DISTINCT 
            recipient_name as full_name, 
            recipient_civil_id as civil_id, 
            recipient_mobile as mobile_number, 
            recipient_file_number as file_number,
            NULL as id,
            NULL as email,
            'redemption_log' as source
          FROM redemption_logs 
          WHERE recipient_name IS NOT NULL AND recipient_name != ''
          ORDER BY id DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$redemptionRecipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Combine recipients from both sources, avoiding duplicates
$combinedRecipients = [];
$seenCivilIds = [];

// First add users table recipients
foreach ($recipients as $recipient) {
    $combinedRecipients[] = $recipient;
    $seenCivilIds[] = $recipient['civil_id'];
}

// Then add redemption log recipients if not already included
foreach ($redemptionRecipients as $recipient) {
    if (!in_array($recipient['civil_id'], $seenCivilIds)) {
        $combinedRecipients[] = $recipient;
        $seenCivilIds[] = $recipient['civil_id'];
    }
}

// Use the combined list
$recipients = $combinedRecipients;

// Get buyers
$buyers = [];
$query = "SELECT * FROM users WHERE role = 'buyer' ORDER BY id DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$buyers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include header
include_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <h1 class="mb-4">Manage Recipients & Buyers</h1>
            
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
            
            <div class="mb-3">
                <a href="manage_users.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Create New User
                </a>
                <a href="reset_database.php" class="btn btn-danger">
                    <i class="fas fa-database"></i> Database Reset Options
                </a>
            </div>
        </div>
    </div>
    
    <!-- Recipients Section -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recipients List</h5>
                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#bulkDeleteRecipientsModal">
                        <i class="fas fa-trash"></i> Delete All Recipients
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="recipients-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Civil ID</th>
                                    <th>Mobile</th>
                                    <th>File Number</th>
                                    <th>Assigned Coupons</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recipients)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No recipients found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recipients as $recipient): ?>
                                        <tr>
                                            <td><?php echo $recipient['id'] ?? 'N/A'; ?></td>
                                            <td><?php echo $recipient['full_name']; ?></td>
                                            <td><?php echo $recipient['email'] ?? 'N/A'; ?></td>
                                            <td><?php echo $recipient['civil_id']; ?></td>
                                            <td><?php echo $recipient['mobile_number']; ?></td>
                                            <td><?php echo $recipient['file_number']; ?></td>
                                            <td>
                                                <?php
                                                if (isset($recipient['id'])) {
                                                    // Get assigned coupons
                                                    $query = "SELECT c.id, c.code, c.current_balance, ct.name as type_name 
                                                              FROM coupons c 
                                                              LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id 
                                                              WHERE c.recipient_id = ?";
                                                    $stmt = $db->prepare($query);
                                                    $stmt->bindParam(1, $recipient['id']);
                                                    $stmt->execute();
                                                    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                    
                                                    if (count($coupons) > 0) {
                                                        echo '<ul class="list-unstyled mb-0">';
                                                        foreach ($coupons as $coupon) {
                                                            echo '<li><span class="badge bg-info">' . $coupon['code'] . '</span> ' . 
                                                                 '<small>(' . $coupon['type_name'] . ' - ' . $coupon['current_balance'] . ' KD)</small></li>';
                                                        }
                                                        echo '</ul>';
                                                    } else {
                                                        echo '<span class="text-muted">None</span>';
                                                    }
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php if (isset($recipient['id'])): ?>
                                                <button type="button" class="btn btn-sm btn-danger delete-user" 
                                                        data-id="<?php echo $recipient['id']; ?>"
                                                        data-name="<?php echo $recipient['full_name']; ?>"
                                                        data-role="recipient">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                                <?php else: ?>
                                                <span class="badge bg-secondary">From Redemption Log</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Buyers Section -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Buyers List</h5>
                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#bulkDeleteBuyersModal">
                        <i class="fas fa-trash"></i> Delete All Buyers
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="buyers-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Civil ID</th>
                                    <th>Mobile</th>
                                    <th>File Number</th>
                                    <th>Assigned Coupons</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($buyers)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No buyers found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($buyers as $buyer): ?>
                                        <tr>
                                            <td><?php echo $buyer['id']; ?></td>
                                            <td><?php echo $buyer['full_name']; ?></td>
                                            <td><?php echo $buyer['email']; ?></td>
                                            <td><?php echo $buyer['civil_id']; ?></td>
                                            <td><?php echo $buyer['mobile_number']; ?></td>
                                            <td><?php echo $buyer['file_number']; ?></td>
                                            <td>
                                                <?php
                                                // Get assigned coupons
                                                $query = "SELECT c.id, c.code, c.current_balance, ct.name as type_name 
                                                          FROM coupons c 
                                                          LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id 
                                                          WHERE c.buyer_id = ?";
                                                $stmt = $db->prepare($query);
                                                $stmt->bindParam(1, $buyer['id']);
                                                $stmt->execute();
                                                $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                
                                                if (count($coupons) > 0) {
                                                    echo '<ul class="list-unstyled mb-0">';
                                                    foreach ($coupons as $coupon) {
                                                        echo '<li><span class="badge bg-primary">' . $coupon['code'] . '</span> ' . 
                                                             '<small>(' . $coupon['type_name'] . ' - ' . $coupon['current_balance'] . ' KD)</small></li>';
                                                    }
                                                    echo '</ul>';
                                                } else {
                                                    echo '<span class="text-muted">None</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-danger delete-user" 
                                                        data-id="<?php echo $buyer['id']; ?>"
                                                        data-name="<?php echo $buyer['full_name']; ?>"
                                                        data-role="buyer">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="mb-4">
                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteUserModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this user?</p>
                <p><strong>Name:</strong> <span id="delete-user-name"></span></p>
                <p><strong>Role:</strong> <span id="delete-user-role"></span></p>
                <p>This action cannot be undone. Any coupons assigned to this user will be updated.</p>
            </div>
            <div class="modal-footer">
                <form method="post" action="">
                    <input type="hidden" id="delete-user-id" name="user_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_user" class="btn btn-danger">Delete User</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Delete Recipients Modal -->
<div class="modal fade" id="bulkDeleteRecipientsModal" tabindex="-1" aria-labelledby="bulkDeleteRecipientsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="bulkDeleteRecipientsModalLabel">Delete All Recipients</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <strong>Warning!</strong> This will delete ALL recipients from the system.
                </div>
                <p>Are you sure you want to delete all recipients? This action cannot be undone.</p>
                <p>All coupons assigned to these recipients will be updated.</p>
            </div>
            <div class="modal-footer">
                <form method="post" action="">
                    <input type="hidden" name="user_type" value="recipients">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="bulk_delete" class="btn btn-danger">Delete All Recipients</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Delete Buyers Modal -->
<div class="modal fade" id="bulkDeleteBuyersModal" tabindex="-1" aria-labelledby="bulkDeleteBuyersModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="bulkDeleteBuyersModalLabel">Delete All Buyers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <strong>Warning!</strong> This will delete ALL buyers from the system.
                </div>
                <p>Are you sure you want to delete all buyers? This action cannot be undone.</p>
                <p>All coupons assigned to these buyers will be reset to available status.</p>
            </div>
            <div class="modal-footer">
                <form method="post" action="">
                    <input type="hidden" name="user_type" value="buyers">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="bulk_delete" class="btn btn-danger">Delete All Buyers</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add this before the closing body tag -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize delete user modal
        const deleteButtons = document.querySelectorAll('.delete-user');
        
        // Delete user
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const role = this.getAttribute('data-role');
                
                document.getElementById('delete-user-id').value = id;
                document.getElementById('delete-user-name').textContent = name;
                document.getElementById('delete-user-role').textContent = role;
                
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
                deleteModal.show();
            });
        });
    });
</script>

<!-- Initialize DataTables separately -->
<script>
    // Wait for page to fully load
    window.addEventListener('load', function() {
        // Check if jQuery and DataTables are available
        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.DataTable !== 'undefined') {
            // Initialize recipients table
            if (document.getElementById('recipients-table')) {
                jQuery('#recipients-table').DataTable({
                    "order": [[0, "desc"]],
                    "language": {
                        "emptyTable": "No recipients found"
                    }
                });
            }
            
            // Initialize buyers table
            if (document.getElementById('buyers-table')) {
                jQuery('#buyers-table').DataTable({
                    "order": [[0, "desc"]],
                    "language": {
                        "emptyTable": "No buyers found"
                    }
                });
            }
        }
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
