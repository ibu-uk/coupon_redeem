<?php
// Include configuration and models
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../models/Service.php';

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

// Initialize service object
$service = new Service($db);

// Initialize variables
$error = "";
$success = "";

// Process service creation
if (isset($_POST['create_service'])) {
    // Get form data
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $defaultPrice = floatval($_POST['default_price']);
    
    if (empty($name) || empty($defaultPrice)) {
        $error = "Service name and price are required.";
    } else {
        // Set service properties
        $service->name = $name;
        $service->description = $description;
        $service->default_price = $defaultPrice;
        
        // Create service
        if ($service->create()) {
            $success = "Service created successfully.";
        } else {
            $error = "Failed to create service.";
        }
    }
}

// Process service update
if (isset($_POST['update_service'])) {
    // Get form data
    $id = $_POST['service_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $defaultPrice = floatval($_POST['default_price']);
    
    if (empty($id) || empty($name) || empty($defaultPrice)) {
        $error = "Service ID, name, and price are required.";
    } else {
        // Set service properties
        $service->id = $id;
        $service->name = $name;
        $service->description = $description;
        $service->default_price = $defaultPrice;
        
        // Update service
        if ($service->update()) {
            $success = "Service updated successfully.";
        } else {
            $error = "Failed to update service.";
        }
    }
}

// Process service deletion
if (isset($_POST['delete_service'])) {
    // Get service ID
    $id = $_POST['service_id'];
    
    if (empty($id)) {
        $error = "Service ID is required.";
    } else {
        // Set service ID
        $service->id = $id;
        
        // Delete service
        if ($service->delete()) {
            $success = "Service deleted successfully.";
        } else {
            $error = "Failed to delete service.";
        }
    }
}

// Get all services
$services = [];
$stmt = $service->readAll();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $services[] = $row;
}

// Include header
include_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <h1 class="mb-4">Manage Services</h1>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Add New Service</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="name" class="form-label">Service Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="default_price" class="form-label">Default Price (KD)</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="default_price" name="default_price" required>
                        </div>
                        <button type="submit" name="create_service" class="btn btn-primary">Add Service</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Service List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped datatable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Price (KD)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($services as $s): ?>
                                    <tr>
                                        <td><?php echo $s['id']; ?></td>
                                        <td><?php echo $s['name']; ?></td>
                                        <td><?php echo $s['description']; ?></td>
                                        <td><?php echo number_format($s['default_price'], 2); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary edit-service" 
                                                    data-id="<?php echo $s['id']; ?>"
                                                    data-name="<?php echo $s['name']; ?>"
                                                    data-description="<?php echo htmlspecialchars($s['description']); ?>"
                                                    data-price="<?php echo $s['default_price']; ?>">
                                                Edit
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger delete-service" 
                                                    data-id="<?php echo $s['id']; ?>"
                                                    data-name="<?php echo $s['name']; ?>">
                                                Delete
                                            </button>
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

<!-- Edit Service Modal -->
<div class="modal fade" id="editServiceModal" tabindex="-1" aria-labelledby="editServiceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editServiceModalLabel">Edit Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="" id="edit-service-form">
                    <input type="hidden" name="service_id" id="edit-service-id">
                    <div class="mb-3">
                        <label for="edit-name" class="form-label">Service Name</label>
                        <input type="text" class="form-control" id="edit-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit-description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit-default-price" class="form-label">Default Price (KD)</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" id="edit-default-price" name="default_price" required>
                    </div>
                    <button type="submit" name="update_service" class="btn btn-primary">Update Service</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Service Modal -->
<div class="modal fade" id="deleteServiceModal" tabindex="-1" aria-labelledby="deleteServiceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteServiceModalLabel">Delete Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the service: <span id="delete-service-name"></span>?</p>
                <form method="post" action="" id="delete-service-form">
                    <input type="hidden" name="service_id" id="delete-service-id">
                    <button type="submit" name="delete_service" class="btn btn-danger">Delete</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize edit service modal
    document.addEventListener('DOMContentLoaded', function() {
        const editButtons = document.querySelectorAll('.edit-service');
        const deleteButtons = document.querySelectorAll('.delete-service');
        
        // Edit service
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const description = this.getAttribute('data-description');
                const price = this.getAttribute('data-price');
                
                document.getElementById('edit-service-id').value = id;
                document.getElementById('edit-name').value = name;
                document.getElementById('edit-description').value = description;
                document.getElementById('edit-default-price').value = price;
                
                const editModal = new bootstrap.Modal(document.getElementById('editServiceModal'));
                editModal.show();
            });
        });
        
        // Delete service
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                document.getElementById('delete-service-id').value = id;
                document.getElementById('delete-service-name').textContent = name;
                
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteServiceModal'));
                deleteModal.show();
            });
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
