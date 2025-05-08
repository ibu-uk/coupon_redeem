<?php
// Include configuration file
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../models/Coupon.php';
require_once '../models/User.php';

// Check if user is logged in and has buyer role
if(!isLoggedIn() || !hasRole('buyer')) {
    redirect('login.php');
}

// Check if coupon ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "Coupon ID is required.";
    $_SESSION['message_type'] = "danger";
    redirect('buyer/dashboard.php');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize coupon object
$coupon = new Coupon($db);
$coupon->id = $_GET['id'];

// Get coupon details
if(!$coupon->readOne()) {
    $_SESSION['message'] = "Coupon not found.";
    $_SESSION['message_type'] = "danger";
    redirect('buyer/dashboard.php');
}

// Check if the coupon belongs to the current user
if($coupon->buyer_id != $_SESSION['user_id']) {
    $_SESSION['message'] = "You don't have permission to assign a recipient to this coupon.";
    $_SESSION['message_type'] = "danger";
    redirect('buyer/dashboard.php');
}

// Check if the coupon already has a recipient
if($coupon->recipient_id) {
    $_SESSION['message'] = "This coupon already has a recipient assigned.";
    $_SESSION['message_type'] = "warning";
    redirect('buyer/dashboard.php');
}

// Check if the coupon is active
if($coupon->status !== 'active') {
    $_SESSION['message'] = "Only active coupons can have recipients assigned.";
    $_SESSION['message_type'] = "warning";
    redirect('buyer/dashboard.php');
}

// Process form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize user object for recipient
    $recipient = new User($db);
    
    // Check if existing user is selected
    if(isset($_POST['existing_user']) && !empty($_POST['existing_user'])) {
        $recipient->id = $_POST['existing_user'];
        if($recipient->readOne()) {
            $coupon->recipient_id = $recipient->id;
            if($coupon->assignRecipient()) {
                $_SESSION['message'] = "Recipient assigned successfully!";
                $_SESSION['message_type'] = "success";
                redirect('buyer/dashboard.php');
            } else {
                $error = "Failed to assign recipient.";
            }
        } else {
            $error = "Selected recipient not found.";
        }
    }
    // Create new recipient
    else {
        $recipient->username = $_POST['email']; // Use email as username
        $recipient->email = $_POST['email'];
        $recipient->full_name = $_POST['full_name'];
        $recipient->role = 'recipient';
        
        // Generate a random password
        $randomPassword = substr(md5(rand()), 0, 8);
        $recipient->password = $randomPassword;
        
        // Create recipient
        if($recipient->create()) {
            $coupon->recipient_id = $recipient->id;
            if($coupon->assignRecipient()) {
                $_SESSION['message'] = "Recipient created and assigned successfully!";
                $_SESSION['message_type'] = "success";
                redirect('buyer/dashboard.php');
            } else {
                $error = "Failed to assign recipient.";
            }
        } else {
            $error = "Failed to create recipient.";
        }
    }
}

// Get existing recipients
$query = "SELECT id, full_name, email FROM users WHERE role = 'recipient' ORDER BY full_name";
$stmt = $db->prepare($query);
$stmt->execute();
$recipients = $stmt;

// Include header
include_once '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Assign Recipient</h2>
            <a href="<?php echo BASE_URL; ?>buyer/dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to My Coupons
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Assign a Recipient to Your Coupon</h5>
            </div>
            <div class="card-body">
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <div class="mb-4">
                    <h6>Coupon Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Coupon Code:</strong> <?php echo $coupon->code; ?></p>
                            <p><strong>Coupon Type:</strong> 
                                <span class="badge <?php echo strtolower($coupon->coupon_type_name) === 'black' ? 'badge-black' : (strtolower($coupon->coupon_type_name) === 'gold' ? 'badge-gold' : 'badge-silver'); ?>">
                                    <?php echo $coupon->coupon_type_name; ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Value:</strong> <?php echo number_format($coupon->initial_balance, 2); ?> KD</p>
                            <p><strong>Expiry Date:</strong> <?php echo date('d M Y', strtotime($coupon->expiry_date)); ?></p>
                        </div>
                    </div>
                </div>
                
                <ul class="nav nav-tabs" id="recipientTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="new-recipient-tab" data-bs-toggle="tab" data-bs-target="#new-recipient" type="button" role="tab" aria-controls="new-recipient" aria-selected="true">New Recipient</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="existing-recipient-tab" data-bs-toggle="tab" data-bs-target="#existing-recipient" type="button" role="tab" aria-controls="existing-recipient" aria-selected="false">Existing Recipient</button>
                    </li>
                </ul>
                
                <div class="tab-content p-3 border border-top-0 rounded-bottom" id="recipientTabsContent">
                    <!-- New Recipient Tab -->
                    <div class="tab-pane fade show active" id="new-recipient" role="tabpanel" aria-labelledby="new-recipient-tab">
                        <form method="POST" action="" id="newRecipientForm">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                                <div class="form-text">The recipient will use this email to access their account.</div>
                            </div>
                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">Assign New Recipient</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Existing Recipient Tab -->
                    <div class="tab-pane fade" id="existing-recipient" role="tabpanel" aria-labelledby="existing-recipient-tab">
                        <?php if($recipients->rowCount() > 0): ?>
                            <form method="POST" action="" id="existingRecipientForm">
                                <div class="mb-3">
                                    <label for="existing_user" class="form-label">Select Recipient</label>
                                    <select class="form-select" id="existing_user" name="existing_user" required>
                                        <option value="">-- Select a recipient --</option>
                                        <?php while($row = $recipients->fetch(PDO::FETCH_ASSOC)): ?>
                                            <option value="<?php echo $row['id']; ?>"><?php echo $row['full_name']; ?> (<?php echo $row['email']; ?>)</option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="d-grid gap-2 mt-4">
                                    <button type="submit" class="btn btn-primary">Assign Existing Recipient</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info" role="alert">
                                <i class="fas fa-info-circle me-2"></i> No existing recipients found. Please create a new recipient.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Information</h5>
            </div>
            <div class="card-body">
                <p>Assigning a recipient to your coupon allows them to redeem services using the coupon value.</p>
                <p>You can either:</p>
                <ul>
                    <li>Create a new recipient account, or</li>
                    <li>Select an existing recipient if available</li>
                </ul>
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> Once a recipient is assigned, it cannot be changed.
                </div>
                <p>The recipient will receive access to use this coupon for services until the expiry date or until the balance is fully redeemed.</p>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>
