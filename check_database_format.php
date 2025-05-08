<?php
// Include configuration file
require_once 'config/config.php';
require_once 'config/database.php';

// Check if user is logged in and has admin role
if(!isLoggedIn() || !hasRole('admin')) {
    echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>";
    echo "<h2>Access Denied</h2>";
    echo "<p>You must be logged in as an administrator to access this page.</p>";
    echo "<p><a href='login.php' style='display: inline-block; background-color: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>Go to Login</a></p>";
    echo "</div>";
    exit;
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Function to check coupon codes format
function checkCouponFormat($db) {
    // Get all coupons
    $query = "SELECT id, code, coupon_type_id FROM coupons ORDER BY id";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all coupon types
    $query = "SELECT id, name FROM coupon_types";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $couponTypes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $couponTypes[$row['id']] = $row['name'];
    }
    
    $oldFormatCoupons = [];
    $newFormatCoupons = [];
    $invalidFormatCoupons = [];
    
    foreach ($coupons as $coupon) {
        $code = $coupon['code'];
        $typeId = $coupon['coupon_type_id'];
        $typeName = isset($couponTypes[$typeId]) ? $couponTypes[$typeId] : 'Unknown';
        
        // Check if it's in the old format (e.g., BLACK-1)
        if (preg_match('/^[A-Z]+-\d+$/', $code)) {
            $oldFormatCoupons[] = [
                'id' => $coupon['id'],
                'code' => $code,
                'type' => $typeName
            ];
        }
        // Check if it's in the new format (e.g., B101)
        else if (preg_match('/^[BGS]\d{3}$/', $code)) {
            $newFormatCoupons[] = [
                'id' => $coupon['id'],
                'code' => $code,
                'type' => $typeName
            ];
        }
        // Invalid format
        else {
            $invalidFormatCoupons[] = [
                'id' => $coupon['id'],
                'code' => $code,
                'type' => $typeName
            ];
        }
    }
    
    return [
        'old_format' => $oldFormatCoupons,
        'new_format' => $newFormatCoupons,
        'invalid_format' => $invalidFormatCoupons,
        'total' => count($coupons)
    ];
}

// Function to check redemption logs for coupon code references
function checkRedemptionLogs($db) {
    $query = "SELECT id, coupon_id, service_name, description FROM redemption_logs ORDER BY id DESC LIMIT 20";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get coupon codes for the logs
    $couponCodes = [];
    foreach ($logs as $log) {
        $couponId = $log['coupon_id'];
        $query = "SELECT code FROM coupons WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $couponId);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $couponCodes[$couponId] = $row['code'];
        }
    }
    
    return [
        'logs' => $logs,
        'coupon_codes' => $couponCodes
    ];
}

// Check coupon format
$formatResults = checkCouponFormat($db);
$logResults = checkRedemptionLogs($db);

// Display results
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Coupon Format Check</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
    <div class='container mt-4'>
        <h1>Coupon Format Check</h1>
        <p>This page checks the format of coupon codes in the database to ensure they match the new format (B101, G101, S101, etc.).</p>
        
        <div class='card mb-4'>
            <div class='card-header'>
                <h5 class='card-title'>Summary</h5>
            </div>
            <div class='card-body'>
                <p><strong>Total Coupons:</strong> " . $formatResults['total'] . "</p>
                <p><strong>New Format Coupons (B101, G101, S101):</strong> " . count($formatResults['new_format']) . "</p>
                <p><strong>Old Format Coupons (BLACK-1, GOLD-1):</strong> " . count($formatResults['old_format']) . "</p>
                <p><strong>Invalid Format Coupons:</strong> " . count($formatResults['invalid_format']) . "</p>
                
                <div class='progress' style='height: 30px;'>
                    <div class='progress-bar bg-success' role='progressbar' style='width: " . (count($formatResults['new_format']) / $formatResults['total'] * 100) . "%;' 
                        aria-valuenow='" . count($formatResults['new_format']) . "' aria-valuemin='0' aria-valuemax='" . $formatResults['total'] . "'>
                        " . round(count($formatResults['new_format']) / $formatResults['total'] * 100) . "% New Format
                    </div>
                    <div class='progress-bar bg-warning' role='progressbar' style='width: " . (count($formatResults['old_format']) / $formatResults['total'] * 100) . "%;' 
                        aria-valuenow='" . count($formatResults['old_format']) . "' aria-valuemin='0' aria-valuemax='" . $formatResults['total'] . "'>
                        " . round(count($formatResults['old_format']) / $formatResults['total'] * 100) . "% Old Format
                    </div>
                    <div class='progress-bar bg-danger' role='progressbar' style='width: " . (count($formatResults['invalid_format']) / $formatResults['total'] * 100) . "%;' 
                        aria-valuenow='" . count($formatResults['invalid_format']) . "' aria-valuemin='0' aria-valuemax='" . $formatResults['total'] . "'>
                        " . round(count($formatResults['invalid_format']) / $formatResults['total'] * 100) . "% Invalid
                    </div>
                </div>
                
                <div class='mt-3'>
                    <a href='update_coupon_codes.php' class='btn btn-primary'>Update All Coupons to New Format</a>
                </div>
            </div>
        </div>";

if (count($formatResults['old_format']) > 0) {
    echo "<div class='card mb-4'>
            <div class='card-header'>
                <h5 class='card-title'>Old Format Coupons</h5>
            </div>
            <div class='card-body'>
                <div class='table-responsive'>
                    <table class='table table-striped'>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Code</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>";
    
    foreach ($formatResults['old_format'] as $coupon) {
        echo "<tr>
                <td>" . $coupon['id'] . "</td>
                <td>" . $coupon['code'] . "</td>
                <td>" . $coupon['type'] . "</td>
              </tr>";
    }
    
    echo "      </tbody>
                    </table>
                </div>
            </div>
        </div>";
}

if (count($formatResults['new_format']) > 0) {
    echo "<div class='card mb-4'>
            <div class='card-header'>
                <h5 class='card-title'>New Format Coupons</h5>
            </div>
            <div class='card-body'>
                <div class='table-responsive'>
                    <table class='table table-striped'>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Code</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>";
    
    foreach ($formatResults['new_format'] as $coupon) {
        echo "<tr>
                <td>" . $coupon['id'] . "</td>
                <td>" . $coupon['code'] . "</td>
                <td>" . $coupon['type'] . "</td>
              </tr>";
    }
    
    echo "      </tbody>
                    </table>
                </div>
            </div>
        </div>";
}

echo "<div class='card mb-4'>
        <div class='card-header'>
            <h5 class='card-title'>Recent Redemption Logs</h5>
        </div>
        <div class='card-body'>
            <div class='table-responsive'>
                <table class='table table-striped'>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Coupon ID</th>
                            <th>Coupon Code</th>
                            <th>Service Name</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>";

foreach ($logResults['logs'] as $log) {
    $couponId = $log['coupon_id'];
    $couponCode = isset($logResults['coupon_codes'][$couponId]) ? $logResults['coupon_codes'][$couponId] : 'Unknown';
    
    echo "<tr>
            <td>" . $log['id'] . "</td>
            <td>" . $couponId . "</td>
            <td>" . $couponCode . "</td>
            <td>" . $log['service_name'] . "</td>
            <td>" . $log['description'] . "</td>
          </tr>";
}

echo "      </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class='mb-4'>
        <a href='admin/manage_coupons.php' class='btn btn-secondary'>Back to Manage Coupons</a>
    </div>
    
    </div>
</body>
</html>";
?>
