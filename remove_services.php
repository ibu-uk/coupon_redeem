<?php
// Include configuration and models
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'models/Service.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Find services that are in use (referenced in redemption_logs)
$query = "SELECT DISTINCT service_id FROM redemption_logs";
$stmt = $db->prepare($query);
$stmt->execute();
$usedServiceIds = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $usedServiceIds[] = $row['service_id'];
}

// Get all services
$query = "SELECT id, name FROM services";
$stmt = $db->prepare($query);
$stmt->execute();
$allServices = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $allServices[$row['id']] = $row['name'];
}

// Process removal if confirmed
$removed = 0;
$preserved = 0;
$message = "";

if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    // Remove services that are not in use
    foreach ($allServices as $id => $name) {
        if (!in_array($id, $usedServiceIds)) {
            $service = new Service($db);
            $service->id = $id;
            if ($service->delete()) {
                $removed++;
            }
        } else {
            $preserved++;
        }
    }
    
    $message = "<div class='alert alert-success'>Operation completed. Removed $removed services. Preserved $preserved services that are in use.</div>";
}

// Include header
include_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <h2>Remove Services</h2>
            
            <?php if (!empty($message)): ?>
                <?php echo $message; ?>
            <?php endif; ?>
            
            <?php if (!isset($_GET['confirm'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Services Management</h5>
                    </div>
                    <div class="card-body">
                        <p>This tool will safely remove services that are not currently in use by any redemption logs.</p>
                        
                        <h6>Services in use (will be preserved):</h6>
                        <ul>
                            <?php 
                            $inUseCount = 0;
                            foreach ($usedServiceIds as $id) {
                                if (isset($allServices[$id])) {
                                    echo "<li>{$allServices[$id]} (ID: $id)</li>";
                                    $inUseCount++;
                                }
                            }
                            if ($inUseCount == 0) {
                                echo "<li>No services are currently in use.</li>";
                            }
                            ?>
                        </ul>
                        
                        <h6>Services that will be removed:</h6>
                        <ul>
                            <?php 
                            $toRemoveCount = 0;
                            foreach ($allServices as $id => $name) {
                                if (!in_array($id, $usedServiceIds)) {
                                    echo "<li>$name (ID: $id)</li>";
                                    $toRemoveCount++;
                                }
                            }
                            if ($toRemoveCount == 0) {
                                echo "<li>No services to remove.</li>";
                            }
                            ?>
                        </ul>
                        
                        <div class="mt-3">
                            <a href="?confirm=yes" class="btn btn-danger" onclick="return confirm('Are you sure you want to remove <?php echo $toRemoveCount; ?> services? This action cannot be undone.');">Remove Unused Services</a>
                            <a href="import_services.php" class="btn btn-secondary">Back to Import Services</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="mt-3">
                    <a href="import_services.php" class="btn btn-primary">Back to Import Services</a>
                    <a href="admin/manage_services.php" class="btn btn-secondary">Go to Services Management</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>
