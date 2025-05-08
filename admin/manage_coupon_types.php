<?php
// Include configuration file
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../models/CouponType.php';
require_once '../models/Coupon.php';

// Check if user is logged in and has admin or staff role
if(!isLoggedIn() || (!hasRole('admin') && !hasRole('staff'))) {
    redirect('../login.php');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize coupon type object
$couponType = new CouponType($db);

// Process form submission for updating coupon type
$success = "";
$error = "";

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_coupon_type'])) {
    // Get form data
    $couponTypeId = $_POST['coupon_type_id'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $value = $_POST['value'];
    
    // Validate inputs
    if(empty($couponTypeId) || empty($value) || !is_numeric($value) || $value <= 0) {
        $error = "Please provide a valid value for the coupon type.";
    } else if(empty($name)) {
        $error = "Please provide a valid name for the coupon type.";
    } else {
        // Set coupon type properties
        $couponType->id = $couponTypeId;
        
        // Check if coupon type exists
        if($couponType->readOne()) {
            $couponType->name = $name;
            $couponType->description = $description;
            $couponType->value = $value;
            
            // Update coupon type
            if($couponType->update()) {
                $success = "Coupon type updated successfully! All related coupons have been updated.";
            } else {
                $error = "Failed to update coupon type.";
            }
        } else {
            $error = "Coupon type not found.";
        }
    }
}

// Get coupon type statistics
$couponTypeStats = $couponType->getUsageStatistics();

// Include header
include_once '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h2 class="mb-4">Manage Coupon Types</h2>
    </div>
</div>

<?php if(!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if(!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Coupon Types</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Value (KD)</th>
                                <th>Description</th>
                                <th>Total Coupons</th>
                                <th>Available</th>
                                <th>Assigned</th>
                                <th>Redeemed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $couponTypeStats->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td>
                                        <span class="badge <?php echo strtolower($row['name']) === 'black' ? 'badge-black' : (strtolower($row['name']) === 'gold' ? 'badge-gold' : 'badge-silver'); ?>">
                                            <?php echo $row['name']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($row['value'], 2); ?> KD</td>
                                    <td><?php echo isset($row['description']) ? $row['description'] : ''; ?></td>
                                    <td><?php echo $row['total_coupons']; ?></td>
                                    <td><?php echo $row['available_coupons']; ?></td>
                                    <td><?php echo $row['assigned_coupons']; ?></td>
                                    <td><?php echo $row['redeemed_coupons']; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-coupon-type" 
                                                data-id="<?php echo $row['id']; ?>" 
                                                data-name="<?php echo $row['name']; ?>"
                                                data-value="<?php echo $row['value']; ?>"
                                                data-description="<?php echo isset($row['description']) ? $row['description'] : ''; ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editCouponTypeModal">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Coupon Type Modal -->
<div class="modal fade" id="editCouponTypeModal" tabindex="-1" aria-labelledby="editCouponTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCouponTypeModalLabel">Edit Coupon Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="coupon_type_id" id="edit_coupon_type_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Coupon Type Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                        <small class="text-muted">Changing the name will update all related coupon codes (e.g., BLACK-1 to WHITE-1).</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_value" class="form-label">Value (KD)</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" id="edit_value" name="value" required>
                        <small class="text-muted">This is the monetary value of the coupon type.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_coupon_type" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Handle edit coupon type button click
    document.addEventListener('DOMContentLoaded', function() {
        const editButtons = document.querySelectorAll('.edit-coupon-type');
        
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const value = this.getAttribute('data-value');
                const description = this.getAttribute('data-description');
                
                document.getElementById('edit_coupon_type_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_value').value = value;
                document.getElementById('edit_description').value = description;
            });
        });
    });
</script>

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

<?php
// Include footer
include_once '../includes/footer.php';
?>
