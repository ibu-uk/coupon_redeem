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

// Check if user has admin role - restrict this page to admin only
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
$redemptionLog = new RedemptionLog($db);

// Initialize variables
$error = "";
$success = "";
$recipient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$recipient_name = isset($_GET['name']) ? $_GET['name'] : '';
$recipient_civil_id = isset($_GET['civil_id']) ? $_GET['civil_id'] : '';

// Check if recipient ID or name is provided
if (empty($recipient_id) && empty($recipient_name) && empty($recipient_civil_id)) {
    $_SESSION['message'] = "Recipient information is required.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . BASE_URL . "admin/manage_recipients.php");
    exit();
}

// Process service deletion
if (isset($_POST['delete_services']) && isset($_POST['service_ids']) && is_array($_POST['service_ids'])) {
    $serviceIds = $_POST['service_ids'];
    
    // Check if there's at least one service selected
    if (count($serviceIds) > 0) {
        // Delete selected services and reverse amounts
        $result = $redemptionLog->deleteSpecificLogsAndReverseBalance($serviceIds);
        
        if ($result['success']) {
            $success = "Successfully deleted " . $result['deleted_count'] . " service(s) and reversed " . 
                      number_format($result['total_reversed'], 2) . " KD across " . 
                      count($result['updated_coupons']) . " coupon(s).";
        } else {
            $error = "Error: " . ($result['error'] ?? 'Unknown error occurred.');
        }
    } else {
        $error = "No services were selected for deletion.";
    }
}

// Get recipient information if ID is provided
if ($recipient_id > 0) {
    $user->id = $recipient_id;
    $user->readOne();
    $recipient_name = $user->full_name;
    $recipient_civil_id = $user->civil_id;
}

// Query to get all services used by this recipient
$query = "SELECT rl.*, c.code as coupon_code, c.current_balance, c.initial_balance, ct.name as coupon_type 
          FROM redemption_logs rl
          LEFT JOIN coupons c ON rl.coupon_id = c.id
          LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
          WHERE 1=1 ";

$params = [];

// The redemption_logs table doesn't have a recipient_id column
// Instead, we need to use recipient_name or recipient_civil_id
if (!empty($recipient_name)) {
    $query .= "AND rl.recipient_name = ? ";
    $params[] = $recipient_name;
}

if (!empty($recipient_civil_id)) {
    $query .= "AND rl.recipient_civil_id = ? ";
    $params[] = $recipient_civil_id;
}

$query .= "ORDER BY rl.redemption_date DESC, rl.redemption_time DESC";

$stmt = $db->prepare($query);

// Bind parameters
for ($i = 0; $i < count($params); $i++) {
    $stmt->bindParam($i + 1, $params[$i]);
}

$stmt->execute();

// Include header
include_once '../includes/header.php';
?>

<div class="container mt-4">
    <?php if(!empty($error)): ?>
    <div class="alert alert-danger">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>
    
    <?php if(!empty($success)): ?>
    <div class="alert alert-success">
        <?php echo $success; ?>
    </div>
    <?php endif; ?>
    
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Manage Recipient Services</h2>
                <a href="<?php echo BASE_URL; ?>admin/manage_recipients.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Recipients
                </a>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Recipient Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($recipient_name); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Civil ID:</strong> <?php echo htmlspecialchars($recipient_civil_id); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <form method="post" action="" id="servicesForm">
        <div class="card">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Services History</h5>
                <div>
                    <button type="button" class="btn btn-light btn-sm" id="selectAll">Select All</button>
                    <button type="button" class="btn btn-light btn-sm" id="deselectAll">Deselect All</button>
                </div>
            </div>
            <div class="card-body">
                <?php if($stmt->rowCount() > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="servicesTable">
                        <thead>
                            <tr>
                                <th width="40px">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="selectAllCheckbox">
                                    </div>
                                </th>
                                <th>Date & Time</th>
                                <th>Coupon</th>
                                <th>Service</th>
                                <th>Amount</th>
                                <th>Remaining Balance</th>
                                <th>Redeemed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input service-checkbox" type="checkbox" name="service_ids[]" value="<?php echo $row['id']; ?>">
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($row['redemption_date'] . ' ' . $row['redemption_time']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo strtolower($row['coupon_type']) === 'black' ? 'dark' : (strtolower($row['coupon_type']) === 'gold' ? 'warning' : 'secondary'); ?>">
                                        <?php echo htmlspecialchars($row['coupon_type']); ?>
                                    </span>
                                    <?php echo htmlspecialchars($row['coupon_code']); ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['service_name']); ?></strong>
                                    <?php if(!empty($row['service_description'])): ?>
                                    <p class="small text-muted mb-0"><?php echo htmlspecialchars($row['service_description']); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($row['amount'], 2); ?> KD</td>
                                <td><?php echo number_format($row['remaining_balance'], 2); ?> KD</td>
                                <td><?php echo htmlspecialchars($row['redeemed_by']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
                        <i class="fas fa-trash"></i> Delete Selected Services
                    </button>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    No services found for this recipient.
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Confirm Delete Modal -->
        <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <strong>Warning!</strong> This will delete the selected services and reverse the amounts back to the respective coupons.
                        </div>
                        <p>Are you sure you want to delete the selected services? This action cannot be undone.</p>
                        <p id="selectedServicesCount"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_services" class="btn btn-danger">Delete Selected Services</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle select all checkbox
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const serviceCheckboxes = document.querySelectorAll('.service-checkbox');
    const selectAllBtn = document.getElementById('selectAll');
    const deselectAllBtn = document.getElementById('deselectAll');
    
    // Update selected count in modal
    function updateSelectedCount() {
        const selectedCount = document.querySelectorAll('.service-checkbox:checked').length;
        document.getElementById('selectedServicesCount').textContent = `Selected services: ${selectedCount}`;
        
        // Disable delete button if no services selected
        const deleteBtn = document.querySelector('button[name="delete_services"]');
        if (deleteBtn) {
            deleteBtn.disabled = selectedCount === 0;
        }
    }
    
    // Select all button
    selectAllBtn.addEventListener('click', function() {
        serviceCheckboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        selectAllCheckbox.checked = true;
        updateSelectedCount();
    });
    
    // Deselect all button
    deselectAllBtn.addEventListener('click', function() {
        serviceCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        selectAllCheckbox.checked = false;
        updateSelectedCount();
    });
    
    // Select all checkbox
    selectAllCheckbox.addEventListener('change', function() {
        serviceCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelectedCount();
    });
    
    // Individual checkboxes
    serviceCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Check if all checkboxes are checked
            const allChecked = Array.from(serviceCheckboxes).every(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;
            updateSelectedCount();
        });
    });
    
    // Update count when modal is opened
    const confirmDeleteModal = document.getElementById('confirmDeleteModal');
    confirmDeleteModal.addEventListener('show.bs.modal', function() {
        updateSelectedCount();
    });
    
    // Initialize DataTable if available
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#servicesTable').DataTable({
            "order": [[1, "desc"]], // Sort by date/time by default
            "pageLength": 25,
            "language": {
                "emptyTable": "No services found for this recipient"
            }
        });
    }
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
