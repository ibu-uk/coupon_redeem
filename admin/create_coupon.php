<?php
// Include configuration file
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../models/User.php';
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
$couponTypes = $couponType->readAll();

// Process form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate coupon selection
    if(empty($_POST['coupon_id'])) {
        $error = "Please select a coupon to assign.";
    } else {
        // Get selected coupon
        $coupon = new Coupon($db);
        $coupon->id = $_POST['coupon_id'];
        
        if($coupon->readOne()) {
            // Verify coupon is still available
            if($coupon->status !== 'available') {
                $error = "This coupon (". $coupon->code .") is no longer available. It may have been assigned by another administrator. Please select a different coupon.";
            } else {
                // Initialize user object for buyer
                $buyer = new User($db);
                $buyer->full_name = $_POST['buyer_name'];
                $buyer->email = $_POST['buyer_email'];
                $buyer->civil_id = $_POST['buyer_civil_id'];
                $buyer->mobile_number = $_POST['buyer_mobile'];
                $buyer->file_number = $_POST['buyer_file_number'];
                $buyer->purchase_date = $_POST['purchase_date'];
                $buyer->entry_date = date('Y-m-d'); // Today's date
                
                // Generate a username from email (before @ symbol) or civil ID if email is not provided
                if (!empty($_POST['buyer_email'])) {
                    $emailParts = explode('@', $_POST['buyer_email']);
                    $buyer->username = $emailParts[0] . rand(100, 999);
                } else {
                    // Use civil ID if email is not provided
                    $buyer->username = 'user_' . substr($_POST['buyer_civil_id'], -6) . rand(100, 999);
                }
                
                // Generate a random password
                $randomPassword = substr(md5(rand()), 0, 8);
                $buyer->password = $randomPassword;
                
                // Create or find buyer
                $buyerId = $buyer->findOrCreateBuyer();
                
                if($buyerId) {
                    // Assign coupon to buyer
                    $coupon->buyer_id = $buyerId;
                    
                    if($coupon->assignToBuyer()) {
                        // Set success message
                        $_SESSION['message'] = "Coupon assigned successfully! Coupon Code: {$coupon->code}";
                        $_SESSION['message_type'] = "success";
                        
                        // Redirect to manage coupons page
                        redirect('admin/manage_coupons.php');
                    } else {
                        $error = "Failed to assign coupon to buyer. The coupon may no longer be available.";
                    }
                } else {
                    $error = "Failed to create or find buyer.";
                }
            }
        } else {
            $error = "Selected coupon not found.";
        }
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h2 class="mb-4">Assign Coupon to Buyer</h2>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Coupon Assignment</h5>
            </div>
            <div class="card-body">
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="" id="createCouponForm">
                    <div class="mb-3">
                        <label for="coupon_type_id" class="form-label">Coupon Type</label>
                        <div class="row">
                            <?php 
                            // Reset the cursor
                            $couponTypes->execute();
                            while($row = $couponTypes->fetch(PDO::FETCH_ASSOC)): 
                                // Get available coupons for this type
                                $coupon = new Coupon($db);
                                $availableCoupons = $coupon->getAvailableCouponsByType($row['id']);
                                $availableCount = $availableCoupons->rowCount();
                            ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card coupon-type-card" data-coupon-type-id="<?php echo $row['id']; ?>">
                                        <div class="card-body text-center">
                                            <span class="badge <?php echo strtolower($row['name']) === 'black' ? 'badge-black' : (strtolower($row['name']) === 'gold' ? 'badge-gold' : 'badge-silver'); ?> mb-2">
                                                <?php echo $row['name']; ?>
                                            </span>
                                            <h3 class="card-title"><?php echo number_format($row['value']); ?> KD</h3>
                                            <p class="card-text"><?php echo $availableCount; ?> coupons available</p>
                                            
                                            <?php if($availableCount > 0): ?>
                                                <select class="form-select coupon-select" data-type-id="<?php echo $row['id']; ?>">
                                                    <option value="">Select a coupon</option>
                                                    <?php 
                                                    while($couponRow = $availableCoupons->fetch(PDO::FETCH_ASSOC)): 
                                                        $couponCode = $couponRow['code'];
                                                    ?>
                                                        <option value="<?php echo $couponRow['id']; ?>"><?php echo $couponCode; ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                            <?php else: ?>
                                                <div class="alert alert-warning">No available coupons</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <input type="hidden" name="coupon_id" id="coupon_id" required>
                    </div>
                    
                    <h4 class="mt-4 mb-3">Buyer Information</h4>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="buyer_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="buyer_name" name="buyer_name" value="<?php echo isset($_POST['buyer_name']) ? htmlspecialchars($_POST['buyer_name']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="buyer_civil_id" class="form-label">Civil ID</label>
                                <input type="text" class="form-control" id="buyer_civil_id" name="buyer_civil_id" value="<?php echo isset($_POST['buyer_civil_id']) ? htmlspecialchars($_POST['buyer_civil_id']) : ''; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="buyer_mobile" class="form-label">Mobile Number</label>
                                <input type="text" class="form-control" id="buyer_mobile" name="buyer_mobile" value="<?php echo isset($_POST['buyer_mobile']) ? htmlspecialchars($_POST['buyer_mobile']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="buyer_email" class="form-label">Email <small class="text-muted">(Optional)</small></label>
                                <input type="email" class="form-control" id="buyer_email" name="buyer_email" value="<?php echo isset($_POST['buyer_email']) ? htmlspecialchars($_POST['buyer_email']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="buyer_file_number" class="form-label">File Number (clinic system)</label>
                                <input type="text" class="form-control" id="buyer_file_number" name="buyer_file_number" value="<?php echo isset($_POST['buyer_file_number']) ? htmlspecialchars($_POST['buyer_file_number']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="purchase_date" class="form-label">Date of Purchase</label>
                                <input type="date" class="form-control" id="purchase_date" name="purchase_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Date of Entry</label>
                                <input type="text" class="form-control" value="<?php echo date('Y-m-d'); ?>" readonly>
                                <small class="text-muted">Automatically set to today's date</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">Assign Coupon</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Coupon Information</h5>
            </div>
            <div class="card-body">
                <p>Assigning a coupon to a buyer will:</p>
                <ul>
                    <li>Mark the coupon as assigned</li>
                    <li>Record the buyer's information</li>
                    <li>Set the issue date to today</li>
                </ul>
                <p>The buyer will be able to assign a recipient later.</p>
                <hr>
                <h6>Coupon Types:</h6>
                <ul>
                    <?php
                    // Reset the cursor to display coupon types with their current values
                    $couponTypes->execute();
                    while($typeRow = $couponTypes->fetch(PDO::FETCH_ASSOC)): 
                    ?>
                        <li><strong><?php echo $typeRow['name']; ?> Card:</strong> <?php echo number_format($typeRow['value']); ?> KD</li>
                    <?php endwhile; ?>
                </ul>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i> Coupons are pre-created in batches and have no expiry date.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Handle coupon selection
    document.addEventListener('DOMContentLoaded', function() {
        const couponSelects = document.querySelectorAll('.coupon-select');
        const couponIdInput = document.getElementById('coupon_id');
        const couponTypeCards = document.querySelectorAll('.coupon-type-card');
        
        couponSelects.forEach(select => {
            select.addEventListener('change', function() {
                // Clear all other selects
                couponSelects.forEach(otherSelect => {
                    if(otherSelect !== select && select.value) {
                        otherSelect.value = '';
                    }
                });
                
                // Set the selected coupon ID
                couponIdInput.value = select.value;
                
                // Update card styles
                couponTypeCards.forEach(card => {
                    card.classList.remove('border-primary');
                });
                
                if(select.value) {
                    const typeId = select.getAttribute('data-type-id');
                    const card = document.querySelector(`.coupon-type-card[data-coupon-type-id="${typeId}"]`);
                    card.classList.add('border-primary');
                }
            });
        });
        
        // AJAX validation for fields that need to be unique
        const civilIdInput = document.getElementById('buyer_civil_id');
        const mobileInput = document.getElementById('buyer_mobile');
        const fileNumberInput = document.getElementById('buyer_file_number');
        const emailInput = document.getElementById('buyer_email');
        
        // Function to check if a field value already exists
        function checkFieldExists(field, value, fieldName) {
            // Create a feedback element if it doesn't exist
            let feedbackId = `${field.id}_feedback`;
            let feedbackElement = document.getElementById(feedbackId);
            
            if (!feedbackElement) {
                feedbackElement = document.createElement('div');
                feedbackElement.id = feedbackId;
                feedbackElement.className = 'invalid-feedback';
                field.parentNode.appendChild(feedbackElement);
            }
            
            // Don't check empty values
            if (!value.trim()) {
                field.classList.remove('is-invalid');
                field.classList.remove('is-valid');
                return;
            }
            
            // Make AJAX request to check if field exists
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '../ajax/check_field_exists.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (this.status === 200) {
                    const response = JSON.parse(this.responseText);
                    
                    if (response.exists) {
                        // Instead of marking as invalid, show an informational message
                        field.classList.remove('is-invalid');
                        field.classList.add('is-valid');
                        
                        // Create or update an info element
                        let infoId = `${field.id}_info`;
                        let infoElement = document.getElementById(infoId);
                        
                        if (!infoElement) {
                            infoElement = document.createElement('div');
                            infoElement.id = infoId;
                            infoElement.className = 'text-info small mt-1';
                            field.parentNode.appendChild(infoElement);
                        }
                        
                        infoElement.textContent = `Existing customer found with this ${fieldName}. The coupon will be assigned to this customer.`;
                        infoElement.style.display = 'block';
                        feedbackElement.style.display = 'none';
                    } else {
                        field.classList.remove('is-invalid');
                        field.classList.add('is-valid');
                        feedbackElement.style.display = 'none';
                        
                        // Hide info message if it exists
                        let infoElement = document.getElementById(`${field.id}_info`);
                        if (infoElement) {
                            infoElement.style.display = 'none';
                        }
                    }
                }
            };
            
            xhr.send(`field=${encodeURIComponent(fieldName)}&value=${encodeURIComponent(value)}`);
        }
        
        // Add blur event listeners to check fields when user leaves the field
        civilIdInput.addEventListener('blur', function() {
            checkFieldExists(this, this.value, 'civil_id');
        });
        
        mobileInput.addEventListener('blur', function() {
            checkFieldExists(this, this.value, 'mobile_number');
        });
        
        fileNumberInput.addEventListener('blur', function() {
            checkFieldExists(this, this.value, 'file_number');
        });
        
        emailInput.addEventListener('blur', function() {
            if (this.value.trim()) {
                checkFieldExists(this, this.value, 'email');
            }
        });
        
        // Form validation
        const form = document.getElementById('createCouponForm');
        form.addEventListener('submit', function(event) {
            // Check if a coupon is selected
            if(!couponIdInput.value) {
                event.preventDefault();
                alert('Please select a coupon');
                return;
            }
            
            // Check if any field has the is-invalid class
            // Note: We no longer block submission for existing customers
            // as the backend will handle assigning the coupon to the existing customer
            const invalidFields = form.querySelectorAll('.is-invalid');
            if (invalidFields.length > 0) {
                // Check if these are actual errors or just existing customer notifications
                let hasRealErrors = false;
                invalidFields.forEach(field => {
                    // Check if there's an info message for this field
                    const infoElement = document.getElementById(`${field.id}_info`);
                    if (!infoElement || infoElement.style.display === 'none') {
                        hasRealErrors = true;
                    }
                });
                
                if (hasRealErrors) {
                    event.preventDefault();
                    alert('Please fix the highlighted errors before submitting the form.');
                    invalidFields[0].focus();
                }
            }
        });
    });
</script>

<style>
    .coupon-type-card {
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .coupon-type-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .coupon-type-card.border-primary {
        border: 2px solid #007bff;
    }
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
