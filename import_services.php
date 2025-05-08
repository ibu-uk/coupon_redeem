<?php
// Include configuration and models
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'models/Service.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Check if the remove action is requested
if (isset($_GET['action']) && $_GET['action'] == 'remove') {
    // Remove all services
    $query = "TRUNCATE TABLE services";
    $stmt = $db->prepare($query);
    $result = $stmt->execute();
    
    if ($result) {
        $message = "All services have been successfully removed.";
        $messageType = "success";
    } else {
        $message = "Failed to remove services.";
        $messageType = "danger";
    }
}

// Check if form was submitted for importing Excel file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    // Process the uploaded Excel file
    $uploadDir = 'uploads/';
    
    // Create uploads directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileName = basename($_FILES['excel_file']['name']);
    $targetFilePath = $uploadDir . $fileName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
    
    // Allow certain file formats
    $allowTypes = array('xls', 'xlsx', 'csv');
    if (in_array($fileType, $allowTypes)) {
        // Upload file to server
        if (move_uploaded_file($_FILES["excel_file"]["tmp_name"], $targetFilePath)) {
            // Process the file based on its type
            if ($fileType == 'csv') {
                // Process CSV file
                $file = fopen($targetFilePath, 'r');
                $success = 0;
                $failed = 0;
                
                // Skip header row
                fgetcsv($file);
                
                while (($line = fgetcsv($file)) !== FALSE) {
                    // Assuming CSV format: name, description, default_price
                    $name = $line[0];
                    $description = isset($line[1]) ? $line[1] : $line[0];
                    $default_price = isset($line[2]) ? floatval($line[2]) : 0;
                    
                    // Create service
                    $service = new Service($db);
                    $service->name = $name;
                    $service->description = $description;
                    $service->default_price = $default_price;
                    
                    if ($service->create()) {
                        $success++;
                    } else {
                        $failed++;
                    }
                }
                fclose($file);
                
                $message = "Import completed. Successfully added: $success services. Failed: $failed services.";
                $messageType = "success";
            } else {
                // For Excel files, we need PHPExcel library
                $message = "Excel files (.xls, .xlsx) require the PHPExcel library which is not installed. Please use CSV format instead.";
                $messageType = "warning";
            }
        } else {
            $message = "Failed to upload the file.";
            $messageType = "danger";
        }
    } else {
        $message = "Only XLS, XLSX, and CSV files are allowed.";
        $messageType = "danger";
    }
}

// Include header
include_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <h2>Service Management</h2>
            
            <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Remove All Services</h5>
                </div>
                <div class="card-body">
                    <p class="text-danger">Warning: This will remove ALL services from the database. This action cannot be undone.</p>
                    <a href="?action=remove" class="btn btn-danger" onclick="return confirm('Are you sure you want to remove all services? This action cannot be undone.');">Remove All Services</a>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Import Services from CSV</h5>
                </div>
                <div class="card-body">
                    <p>Upload a CSV file with your services. The file should have the following columns:</p>
                    <ol>
                        <li>Service Name (required)</li>
                        <li>Description (optional, will use Service Name if empty)</li>
                        <li>Default Price (required)</li>
                    </ol>
                    
                    <div class="alert alert-info">
                        <h6>How to convert your Excel file to CSV:</h6>
                        <ol>
                            <li>Open your Excel file</li>
                            <li>Go to File > Save As</li>
                            <li>Select "CSV (Comma delimited) (*.csv)" from the "Save as type" dropdown</li>
                            <li>Click Save</li>
                            <li>Click "Yes" if Excel warns about features not compatible with CSV</li>
                            <li>Upload the saved CSV file below</li>
                        </ol>
                    </div>
                    
                    <form action="" method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="excel_file" class="form-label">Select CSV File</label>
                            <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".csv" required>
                            <small class="text-muted">Only CSV files are supported</small>
                        </div>
                        <button type="submit" class="btn btn-primary">Import Services</button>
                    </form>
                    
                    <div class="mt-3">
                        <h6>Sample CSV Format:</h6>
                        <pre>Service Name,Description,Default Price
Dental Cleaning,Professional teeth cleaning,30.00
Root Canal,Root canal treatment,150.00
Tooth Extraction,Extraction of tooth,50.00</pre>
                    </div>
                </div>
            </div>
            
            <div class="mt-3">
                <a href="admin/manage_services.php" class="btn btn-secondary">Back to Service Management</a>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>
