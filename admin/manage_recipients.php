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
$redemptionLog = new RedemptionLog($db);

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
            // Check if there's already an active transaction
            try {
                $inTransaction = $db->inTransaction();
            } catch (Exception $e) {
                $inTransaction = false;
            }
            
            // Only start a transaction if one isn't already active
            $manageTransaction = !$inTransaction;
            if ($manageTransaction) {
                $db->beginTransaction();
            }
            
            try {
                // If user is a recipient, handle redemption logs and update coupons
                if ($user->role === 'recipient') {
                    // First, reverse any redemption amounts and delete redemption logs
                    $result = $redemptionLog->deleteByRecipientAndReverseBalance($userId, $user->full_name, $user->civil_id);
                    
                    if (!$result['success']) {
                        throw new Exception("Failed to process redemption logs: " . ($result['error'] ?? 'Unknown error'));
                    }
                    
                    // Update coupons to remove recipient reference
                    $query = "UPDATE coupons SET recipient_id = NULL WHERE recipient_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(1, $userId);
                    $stmt->execute();
                    
                    // Log the reversal details for admin reference
                    $reversalDetails = "Deleted " . $result['deleted_count'] . " redemption logs and updated " . 
                                      count($result['updated_coupons']) . " coupon balances.";
                }
                
                // If user is a buyer, update any coupons assigned to them
                if ($user->role === 'buyer') {
                    $query = "UPDATE coupons SET buyer_id = NULL, status = 'available' WHERE buyer_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(1, $userId);
                    $stmt->execute();
                }
                
                // Delete user
                if (!$user->delete()) {
                    throw new Exception("Failed to delete user record.");
                }
                
                // Only commit if we started the transaction
                if ($manageTransaction) {
                    $db->commit();
                }
                
                $success = "User deleted successfully and associated data updated.";
                if (isset($reversalDetails)) {
                    $success .= " " . $reversalDetails;
                }
            } catch (Exception $e) {
                // Only rollback if we started the transaction
                if ($manageTransaction) {
                    $db->rollBack();
                }
                $error = "Error: " . $e->getMessage();
            }
        } else {
            $error = "Cannot delete admin users or user not found.";
        }
    }
}

// Process bulk deletion
if (isset($_POST['bulk_delete'])) {
    $userType = $_POST['user_type'];
    
    // Check if there's already an active transaction
    try {
        $inTransaction = $db->inTransaction();
    } catch (Exception $e) {
        $inTransaction = false;
    }
    
    // Only start a transaction if one isn't already active
    $manageTransaction = !$inTransaction;
    if ($manageTransaction) {
        $db->beginTransaction();
    }
    
    try {
        if ($userType === 'recipients') {
            // First, get all redemption logs to process individually
            $query = "SELECT id, coupon_id, amount FROM redemption_logs";
            $stmt = $db->prepare($query);
            $stmt->execute();
            
            $updatedCoupons = 0;
            $totalReversed = 0;
            $deletedLogs = 0;
            $couponAmounts = [];
            
            // First collect all redemption data and delete logs individually
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $logId = $row['id'];
                $couponId = $row['coupon_id'];
                $amount = $row['amount'];
                
                // Track total amount per coupon
                if (!isset($couponAmounts[$couponId])) {
                    $couponAmounts[$couponId] = 0;
                }
                $couponAmounts[$couponId] += $amount;
                $totalReversed += $amount;
                
                // Delete each redemption log individually to avoid foreign key issues
                $deleteQuery = "DELETE FROM redemption_logs WHERE id = ?";
                $deleteStmt = $db->prepare($deleteQuery);
                $deleteStmt->bindParam(1, $logId);
                $deleteStmt->execute();
                $deletedLogs++;
            }
            
            // Now update coupon balances
            foreach ($couponAmounts as $couponId => $amountToReverse) {
                // Update coupon balance by adding back the redeemed amount
                $updateQuery = "UPDATE coupons 
                               SET current_balance = current_balance + :amount,
                                   status = CASE 
                                       WHEN status = 'fully_redeemed' THEN 'assigned' 
                                       ELSE status 
                                   END 
                               WHERE id = :coupon_id";
                
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':amount', $amountToReverse);
                $updateStmt->bindParam(':coupon_id', $couponId);
                $updateStmt->execute();
                $updatedCoupons++;
            }
            
            // Update coupons to remove recipient references
            $query = "UPDATE coupons SET recipient_id = NULL WHERE recipient_id IS NOT NULL";
            $stmt = $db->prepare($query);
            $stmt->execute();
            
            // Delete all recipients
            $query = "DELETE FROM users WHERE role = 'recipient'";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $deletedUsers = $stmt->rowCount();
            
            // Only commit if we started the transaction
            if ($manageTransaction) {
                $db->commit();
            }
            
            $success = "All recipients have been deleted and associated coupons updated. ";
            $success .= "Deleted $deletedLogs redemption logs, reversed $totalReversed KD across $updatedCoupons coupons, and removed $deletedUsers recipient users.";
            
        } elseif ($userType === 'buyers') {
            // Update coupons first
            $query = "UPDATE coupons SET buyer_id = NULL, status = 'available' WHERE buyer_id IS NOT NULL";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $updatedCoupons = $stmt->rowCount();
            
            // Delete all buyers
            $query = "DELETE FROM users WHERE role = 'buyer'";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $deletedUsers = $stmt->rowCount();
            
            // Only commit if we started the transaction
            if ($manageTransaction) {
                $db->commit();
            }
            
            $success = "All buyers have been deleted and $updatedCoupons coupons reset to available status.";
        } else {
            throw new Exception("Invalid user type selected.");
        }
    } catch (Exception $e) {
        // Only rollback if we started the transaction
        if ($manageTransaction) {
            $db->rollBack();
        }
        $error = "Error: " . $e->getMessage();
    }
}

// Get recipients from users table
$recipients = [];
$query = "SELECT * FROM users WHERE role = 'recipient' ORDER BY id DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Also get recipients from redemption logs who may not be in users table
$query = "SELECT 
            recipient_name as full_name, 
            recipient_civil_id as civil_id, 
            recipient_mobile as mobile_number, 
            recipient_file_number as file_number,
            NULL as id,
            NULL as email,
            'redemption_log' as source
          FROM redemption_logs 
          WHERE recipient_name IS NOT NULL AND recipient_name != ''
          GROUP BY recipient_name, recipient_civil_id, recipient_mobile, recipient_file_number
          ORDER BY MAX(id) DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$redemptionRecipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Combine recipients from both sources, avoiding duplicates
$combinedRecipients = [];
$seenCivilIds = [];
$seenNames = [];

// First add users table recipients
foreach ($recipients as $recipient) {
    $combinedRecipients[] = $recipient;
    if (!empty($recipient['civil_id'])) {
        $seenCivilIds[] = $recipient['civil_id'];
    }
    if (!empty($recipient['full_name'])) {
        $seenNames[] = strtolower($recipient['full_name']);
    }
}

// Then add redemption log recipients if not already included
foreach ($redemptionRecipients as $recipient) {
    // Check by civil ID if available
    $isDuplicate = false;
    
    if (!empty($recipient['civil_id']) && in_array($recipient['civil_id'], $seenCivilIds)) {
        $isDuplicate = true;
    }
    
    // Also check by name as a fallback
    if (!$isDuplicate && !empty($recipient['full_name']) && in_array(strtolower($recipient['full_name']), $seenNames)) {
        $isDuplicate = true;
    }
    
    if (!$isDuplicate) {
        $combinedRecipients[] = $recipient;
        if (!empty($recipient['civil_id'])) {
            $seenCivilIds[] = $recipient['civil_id'];
        }
        if (!empty($recipient['full_name'])) {
            $seenNames[] = strtolower($recipient['full_name']);
        }
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

<style>
    /* Custom badge styles for gold and silver coupons */
    .badge-gold {
        background-color: #FFD700;
        color: #000;
    }
    .badge-silver {
        background-color: #C0C0C0;
        color: #000;
    }
    .coupon-badges {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
</style>

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
                        <table class="table table-bordered table-striped datatable">
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
                                            <td><?php echo $recipient['id']; ?></td>
                                            <td><?php echo $recipient['full_name']; ?></td>
                                            <td><?php echo $recipient['email']; ?></td>
                                            <td><?php echo $recipient['civil_id']; ?></td>
                                            <td><?php echo $recipient['mobile_number']; ?></td>
                                            <td><?php echo $recipient['file_number']; ?></td>
                                            <td>
                                                <?php
                                                if (isset($recipient['id'])) {
                                                    // First check direct coupon assignments
                                                    $query = "SELECT c.id, c.code, c.current_balance, ct.name as type_name 
                                                              FROM coupons c 
                                                              LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id 
                                                              WHERE c.recipient_id = ?";
                                                    $stmt = $db->prepare($query);
                                                    $stmt->bindParam(1, $recipient['id']);
                                                    $stmt->execute();
                                                    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                    
                                                    // Also check redemption logs for this recipient
                                                    // Use the correct fields from redemption_logs
                                                    $query2 = "SELECT DISTINCT c.id, c.code, c.current_balance, ct.name as type_name 
                                                               FROM redemption_logs rl
                                                               JOIN coupons c ON rl.coupon_id = c.id
                                                               LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
                                                               WHERE (rl.recipient_civil_id = ? OR rl.recipient_mobile = ?)
                                                               GROUP BY c.id";
                                                    $stmt2 = $db->prepare($query2);
                                                    $stmt2->bindParam(1, $recipient['civil_id']);
                                                    $stmt2->bindParam(2, $recipient['mobile_number']);
                                                    $stmt2->execute();
                                                    $redemptionCoupons = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                                                    
                                                    // Merge the results, avoiding duplicates
                                                    $couponIds = array_column($coupons, 'id');
                                                    foreach ($redemptionCoupons as $coupon) {
                                                        if (!in_array($coupon['id'], $couponIds)) {
                                                            $coupons[] = $coupon;
                                                            $couponIds[] = $coupon['id'];
                                                        }
                                                    }
                                                    
                                                    if (count($coupons) > 0) {
                                                        echo '<div class="coupon-badges">';
                                                        foreach ($coupons as $coupon) {
                                                            // Determine badge color based on coupon code
                                                            $badgeClass = 'bg-info';
                                                            $firstLetter = strtoupper(substr($coupon['code'], 0, 1));
                                                            if ($firstLetter == 'B') {
                                                                $badgeClass = 'bg-dark'; // Black coupons
                                                            } elseif ($firstLetter == 'G') {
                                                                $badgeClass = 'badge-gold'; // Gold coupons
                                                            } elseif ($firstLetter == 'S') {
                                                                $badgeClass = 'badge-silver'; // Silver coupons
                                                            }
                                                            
                                                            echo '<div class="mb-2">';
                                                            echo '<span class="badge ' . $badgeClass . ' me-1" style="font-size: 0.9rem;">' . $coupon['code'] . '</span>';
                                                            echo '<span class="badge bg-secondary">' . $coupon['type_name'] . '</span>';
                                                            echo '</div>';
                                                        }
                                                        echo '</div>';
                                                    } else {
                                                        echo '<span class="text-muted">None</span>';
                                                    }
                                                } else {
                                                    // For recipients from redemption logs, show coupons they've used
                                                    // First try by civil ID and mobile number
                                                    $query = "SELECT DISTINCT c.id, c.code, c.current_balance, ct.name as type_name 
                                                              FROM redemption_logs rl
                                                              JOIN coupons c ON rl.coupon_id = c.id
                                                              LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
                                                              WHERE rl.recipient_civil_id = ? OR rl.recipient_mobile = ?
                                                              GROUP BY c.id";
                                                    $stmt = $db->prepare($query);
                                                    $stmt->bindParam(1, $recipient['civil_id']);
                                                    $stmt->bindParam(2, $recipient['mobile_number']);
                                                    $stmt->execute();
                                                    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                    
                                                    if (count($coupons) > 0) {
                                                        echo '<div class="coupon-badges">';
                                                        foreach ($coupons as $coupon) {
                                                            // Determine badge color based on coupon code
                                                            $badgeClass = 'bg-info';
                                                            $firstLetter = strtoupper(substr($coupon['code'], 0, 1));
                                                            if ($firstLetter == 'B') {
                                                                $badgeClass = 'bg-dark'; // Black coupons
                                                            } elseif ($firstLetter == 'G') {
                                                                $badgeClass = 'badge-gold'; // Gold coupons
                                                            } elseif ($firstLetter == 'S') {
                                                                $badgeClass = 'badge-silver'; // Silver coupons
                                                            }
                                                            
                                                            echo '<div class="mb-2">';
                                                            echo '<span class="badge ' . $badgeClass . ' me-1" style="font-size: 0.9rem;">' . $coupon['code'] . '</span>';
                                                            echo '<span class="badge bg-secondary">' . $coupon['type_name'] . '</span>';
                                                            echo '</div>';
                                                        }
                                                        echo '</div>';
                                                    } else {
                                                        echo '<span class="text-muted">From redemption logs</span>';
                                                    }
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="manage_recipient_services.php?id=<?php echo $recipient['id']; ?>&name=<?php echo urlencode($recipient['full_name']); ?>&civil_id=<?php echo urlencode($recipient['civil_id']); ?>" class="btn btn-sm btn-primary me-1" title="Manage Services">
                                                    <i class="fas fa-list-check"></i> Services
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger delete-user" 
                                                        data-id="<?php echo $recipient['id']; ?>"
                                                        data-name="<?php echo $recipient['full_name']; ?>"
                                                        data-role="recipient">
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
                        <table class="table table-bordered table-striped datatable">
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
                                                // Get assigned coupons with details
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
        
        // Prevent DataTables from being initialized on this page
        // This will allow any global initialization to handle the tables
        window.preventDataTablesInit = true;
        
        // If DataTables is already initialized, we need to handle it
        if (typeof $.fn.DataTable !== 'undefined') {
            // Add a small delay to ensure this runs after any automatic initializations
            setTimeout(function() {
                // For any DataTable that shows the warning, we can add this class
                // to prevent future initializations
                $('.dataTables_wrapper').addClass('already-initialized');
            }, 500);
        }
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
