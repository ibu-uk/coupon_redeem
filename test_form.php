<?php
// Include configuration and models
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'form_protection.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Initialize variables
$debug = "";
$error = "";
$success = "";
$result = null;

// Generate CSRF token
$token = generateFormToken('test_form');

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debug .= "Form submitted<br>";
    $debug .= "POST data: " . print_r($_POST, true) . "<br>";
    
    if (isset($_POST['coupon_code'])) {
        $couponCode = trim($_POST['coupon_code']);
        $debug .= "Coupon code: " . $couponCode . "<br>";
        
        // Simple direct query
        $query = "SELECT * FROM coupons WHERE id = 1";
        $debug .= "Query: " . $query . "<br>";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $debug .= "Found coupon with ID=1:<br>";
            $debug .= print_r($row, true) . "<br>";
            $result = $row;
            $success = "Coupon found successfully!";
        } else {
            $debug .= "No coupon found with ID=1<br>";
            $error = "Coupon not found.";
        }
    } else {
        $debug .= "No coupon code provided<br>";
        $error = "Please enter a coupon code.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Test Form</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Simple Form</h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <input type="hidden" name="token" value="<?php echo $token; ?>">
                    <div class="mb-3">
                        <label for="coupon_code" class="form-label">Enter Coupon Code</label>
                        <input type="text" class="form-control" id="coupon_code" name="coupon_code" value="BLACK-1">
                    </div>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </form>
            </div>
        </div>
        
        <?php if ($result): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Result</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th>ID</th>
                            <td><?php echo $result['id']; ?></td>
                        </tr>
                        <tr>
                            <th>Code</th>
                            <td><?php echo $result['code']; ?></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td><?php echo $result['status']; ?></td>
                        </tr>
                        <tr>
                            <th>Balance</th>
                            <td><?php echo $result['current_balance']; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h5>Debug Information</h5>
            </div>
            <div class="card-body">
                <pre><?php echo $debug; ?></pre>
            </div>
        </div>
    </div>
</body>
</html>
