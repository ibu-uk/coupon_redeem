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
        // Check if user is not an admin
        $user->id = $userId;
        if ($user->readOne() && $user->role !== 'admin') {
            // Check if user has any assigned coupons
            $hasCoupons = false;
            
            // Check if user is a buyer with assigned coupons
            if ($user->role === 'buyer') {
                $coupon->buyer_id = $userId;
                $stmt = $coupon->getByBuyer();
                if ($stmt->rowCount() > 0) {
                    $hasCoupons = true;
                }
            }
            
            // Check if user is a recipient with assigned coupons
            if ($user->role === 'recipient') {
                $coupon->recipient_id = $userId;
                $stmt = $coupon->getByRecipient();
                if ($stmt->rowCount() > 0) {
                    $hasCoupons = true;
                }
            }
            
            if ($hasCoupons) {
                $error = "Cannot delete user. Please unassign all coupons from this user first.";
            } else {
                // Delete user
                if ($user->delete()) {
                    $success = "User deleted successfully.";
                } else {
                    $error = "Failed to delete user.";
                }
            }
        } else {
            $error = "Cannot delete admin users.";
        }
    }
}

// Process user creation
if (isset($_POST['create_user'])) {
    // Get form data
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $email = trim($_POST['email']);
    $fullName = trim($_POST['full_name']);
    $civilId = trim($_POST['civil_id']);
    $mobileNumber = trim($_POST['mobile_number']);
    $fileNumber = trim($_POST['file_number']);
    $role = $_POST['role'];
    
    // Different validation based on role
    if (empty($username) || empty($password) || empty($role)) {
        $error = "Username, password, and role are required.";
    } elseif ($role !== 'staff' && (empty($email) || empty($fullName))) {
        $error = "Email and full name are required for non-staff users.";
    } else {
        // Check if username already exists
        $user->username = $username;
        if ($user->getByUsername()) {
            $error = "Username already exists.";
        } else {
            // Check if email already exists
            $user->email = $email;
            if ($user->getByEmail()) {
                $error = "Email already exists.";
            } else {
                // Set user properties
                $user->username = $username;
                $user->password = $password;
                $user->email = $email;
                $user->full_name = $fullName;
                $user->civil_id = $civilId;
                $user->mobile_number = $mobileNumber;
                $user->file_number = $fileNumber;
                $user->role = $role;
                $user->purchase_date = date('Y-m-d');
                $user->entry_date = date('Y-m-d');
                $user->created_by_admin_id = $_SESSION['user_id']; // Track who created this user
                
                // Create user
                if ($user->create()) {
                    $success = "User created successfully.";
                } else {
                    $error = "Failed to create user.";
                }
            }
        }
    }
}

// Get all users except current admin
$users = [];
$stmt = $user->readAll();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Skip the current admin user
    if ($row['id'] != $_SESSION['user_id']) {
        $users[] = $row;
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <h1 class="mb-4">Manage Users</h1>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="mb-3">
                <button id="create-user-button" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create New User
                </button>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">User List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped datatable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Civil ID</th>
                                    <th>Mobile</th>
                                    <th>File Number</th>
                                    <th>Creation Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td><?php echo $u['id']; ?></td>
                                        <td><?php echo $u['username']; ?></td>
                                        <td><?php echo $u['full_name']; ?></td>
                                        <td><?php echo $u['email']; ?></td>
                                        <td>
                                            <?php if ($u['role'] === 'admin'): ?>
                                                <span class="badge bg-danger">Admin</span>
                                            <?php elseif ($u['role'] === 'buyer'): ?>
                                                <span class="badge bg-primary">Buyer</span>
                                            <?php elseif ($u['role'] === 'recipient'): ?>
                                                <span class="badge bg-success">Recipient</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $u['civil_id']; ?></td>
                                        <td><?php echo $u['mobile_number']; ?></td>
                                        <td><?php echo $u['file_number']; ?></td>
                                        <td>
                                            <span class="badge bg-success">Auto-Generated</span>
                                        </td>
                                        <td>
                                            <?php if ($u['role'] !== 'admin'): ?>
                                                <button type="button" class="btn btn-sm btn-danger delete-user" 
                                                        data-id="<?php echo $u['id']; ?>"
                                                        data-name="<?php echo $u['full_name']; ?>"
                                                        data-role="<?php echo $u['role']; ?>">
                                                    Delete
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">No actions</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteUserModalLabel">Delete User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the <span id="delete-user-role"></span> <strong><span id="delete-user-name"></span></strong>?</p>
                <p class="text-danger">This action cannot be undone. The user will be permanently deleted from the system.</p>
                <form method="post" action="" id="delete-user-form">
                    <input type="hidden" name="user_id" id="delete-user-id">
                    <button type="submit" name="delete_user" class="btn btn-danger">Delete User</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createUserModalLabel">Create User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="" id="create-user-form">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username:</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password:</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email:</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name:</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="civil_id" class="form-label">Civil ID:</label>
                        <input type="text" class="form-control" id="civil_id" name="civil_id">
                    </div>
                    <div class="mb-3">
                        <label for="mobile_number" class="form-label">Mobile Number:</label>
                        <input type="text" class="form-control" id="mobile_number" name="mobile_number">
                    </div>
                    <div class="mb-3">
                        <label for="file_number" class="form-label">File Number:</label>
                        <input type="text" class="form-control" id="file_number" name="file_number">
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Role:</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="staff">Staff</option>
                            <option value="buyer">Buyer</option>
                            <option value="recipient">Recipient</option>
                        </select>
                    </div>
                    <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize delete user modal
    document.addEventListener('DOMContentLoaded', function() {
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
        
        // Initialize create user modal
        const createUserButton = document.getElementById('create-user-button');
        createUserButton.addEventListener('click', function() {
            const createModal = new bootstrap.Modal(document.getElementById('createUserModal'));
            createModal.show();
        });
        
        // Handle role selection to show/hide fields
        const roleSelect = document.getElementById('role');
        const emailField = document.getElementById('email').parentNode;
        const fullNameField = document.getElementById('full_name').parentNode;
        const civilIdField = document.getElementById('civil_id').parentNode;
        const mobileNumberField = document.getElementById('mobile_number').parentNode;
        const fileNumberField = document.getElementById('file_number').parentNode;
        
        // Function to toggle required attribute
        function toggleRequired(element, required) {
            const input = element.querySelector('input');
            if (input) {
                if (required) {
                    input.setAttribute('required', '');
                } else {
                    input.removeAttribute('required');
                }
            }
        }
        
        // Initial check when modal opens
        document.getElementById('createUserModal').addEventListener('show.bs.modal', function() {
            // Reset form
            document.getElementById('create-user-form').reset();
            // Show all fields by default
            emailField.style.display = 'block';
            fullNameField.style.display = 'block';
            civilIdField.style.display = 'block';
            mobileNumberField.style.display = 'block';
            fileNumberField.style.display = 'block';
            // Reset required attributes
            toggleRequired(emailField, true);
            toggleRequired(fullNameField, true);
        });
        
        // Handle role change
        roleSelect.addEventListener('change', function() {
            if (this.value === 'staff') {
                // Hide optional fields for staff
                emailField.style.display = 'none';
                fullNameField.style.display = 'none';
                civilIdField.style.display = 'none';
                mobileNumberField.style.display = 'none';
                fileNumberField.style.display = 'none';
                // Remove required attribute
                toggleRequired(emailField, false);
                toggleRequired(fullNameField, false);
            } else {
                // Show all fields for other roles
                emailField.style.display = 'block';
                fullNameField.style.display = 'block';
                civilIdField.style.display = 'block';
                mobileNumberField.style.display = 'block';
                fileNumberField.style.display = 'block';
                // Add required attribute back
                toggleRequired(emailField, true);
                toggleRequired(fullNameField, true);
            }
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
