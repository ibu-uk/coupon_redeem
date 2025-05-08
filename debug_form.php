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
$token = generateFormToken('debug_form');

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>POST Data Received</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    if (isset($_POST['coupon_code'])) {
        $couponCode = trim($_POST['coupon_code']);
        echo "<p>Coupon code: " . $couponCode . "</p>";
        
        // Simple direct query
        $query = "SELECT * FROM coupons WHERE id = 1";
        echo "<p>Query: " . $query . "</p>";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            echo "<h3>Found coupon with ID=1:</h3>";
            echo "<pre>";
            print_r($row);
            echo "</pre>";
            $result = $row;
        } else {
            echo "<p style='color:red'>No coupon found with ID=1</p>";
        }
    } else {
        echo "<p style='color:red'>No coupon code provided</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Debug Form</h1>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Form with GET Method</h5>
            </div>
            <div class="card-body">
                <form method="get" action="">
                    <div class="mb-3">
                        <label for="coupon_code_get" class="form-label">Enter Coupon Code (GET)</label>
                        <input type="text" class="form-control" id="coupon_code_get" name="coupon_code" value="BLACK-1">
                    </div>
                    <button type="submit" class="btn btn-primary">Submit with GET</button>
                </form>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Form with POST Method</h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <input type="hidden" name="token" value="<?php echo $token; ?>">
                    <div class="mb-3">
                        <label for="coupon_code_post" class="form-label">Enter Coupon Code (POST)</label>
                        <input type="text" class="form-control" id="coupon_code_post" name="coupon_code" value="BLACK-1">
                    </div>
                    <button type="submit" class="btn btn-primary">Submit with POST</button>
                </form>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Form with POST Method (No CSRF)</h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="coupon_code_post_no_csrf" class="form-label">Enter Coupon Code (POST, No CSRF)</label>
                        <input type="text" class="form-control" id="coupon_code_post_no_csrf" name="coupon_code" value="BLACK-1">
                    </div>
                    <button type="submit" class="btn btn-primary">Submit with POST (No CSRF)</button>
                </form>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Direct Link Tests</h5>
            </div>
            <div class="card-body">
                <p>Click these links to test direct GET requests:</p>
                <ul>
                    <li><a href="?coupon_code=BLACK-1">Test BLACK-1</a></li>
                    <li><a href="?coupon_code=1">Test 1</a></li>
                </ul>
            </div>
        </div>
        
        <?php if (isset($_GET['coupon_code'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5>GET Data Received</h5>
                </div>
                <div class="card-body">
                    <pre><?php print_r($_GET); ?></pre>
                    <p>Coupon code: <?php echo $_GET['coupon_code']; ?></p>
                    
                    <?php
                    // Simple direct query
                    $query = "SELECT * FROM coupons WHERE id = 1";
                    echo "<p>Query: " . $query . "</p>";
                    
                    $stmt = $db->prepare($query);
                    $stmt->execute();
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($row) {
                        echo "<h3>Found coupon with ID=1:</h3>";
                        echo "<pre>";
                        print_r($row);
                        echo "</pre>";
                    } else {
                        echo "<p style='color:red'>No coupon found with ID=1</p>";
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h5>Server Information</h5>
            </div>
            <div class="card-body">
                <h6>PHP Version: <?php echo phpversion(); ?></h6>
                <h6>Server Software: <?php echo $_SERVER['SERVER_SOFTWARE']; ?></h6>
                <h6>Request Method: <?php echo $_SERVER['REQUEST_METHOD']; ?></h6>
                <h6>Current Script: <?php echo $_SERVER['SCRIPT_NAME']; ?></h6>
            </div>
        </div>
    </div>
</body>
</html>
