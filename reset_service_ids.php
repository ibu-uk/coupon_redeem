<?php
// Include configuration and models
require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Check if we should execute the reset
$executed = false;
$error = '';
$message = '';

if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    try {
        // Get the maximum ID currently in use
        $query = "SELECT MAX(id) as max_id FROM services";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $maxId = $result['max_id'] ?? 0;
        
        // Reset the auto-increment value to max_id + 1
        $query = "ALTER TABLE services AUTO_INCREMENT = " . ($maxId + 1);
        $stmt = $db->prepare($query);
        $result = $stmt->execute();
        
        if ($result) {
            $executed = true;
            $message = "Auto-increment value for services table has been reset to " . ($maxId + 1);
        } else {
            $error = "Failed to reset auto-increment value.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Include header
include_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <h2>Reset Service IDs</h2>
            
            <?php if ($executed): ?>
                <div class="alert alert-success">
                    <?php echo $message; ?>
                </div>
                <div class="mt-3">
                    <a href="import_services.php" class="btn btn-primary">Go to Import Services</a>
                    <a href="admin/manage_services.php" class="btn btn-secondary">Go to Services Management</a>
                </div>
            <?php elseif (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
                <div class="mt-3">
                    <a href="reset_service_ids.php" class="btn btn-primary">Try Again</a>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Reset Auto-Increment Value</h5>
                    </div>
                    <div class="card-body">
                        <p>This tool will reset the auto-increment value for the services table to ensure new services are added with sequential IDs.</p>
                        
                        <div class="alert alert-warning">
                            <p><strong>Note:</strong> This operation will not change any existing services or their IDs. It only affects the ID assignment for new services that will be added in the future.</p>
                        </div>
                        
                        <h6>Current Services:</h6>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Service Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query = "SELECT id, name FROM services ORDER BY id";
                                $stmt = $db->prepare($query);
                                $stmt->execute();
                                
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<tr>";
                                    echo "<td>{$row['id']}</td>";
                                    echo "<td>{$row['name']}</td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                        
                        <div class="mt-3">
                            <a href="?confirm=yes" class="btn btn-primary">Reset Auto-Increment Value</a>
                            <a href="import_services.php" class="btn btn-secondary">Back to Import Services</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>
