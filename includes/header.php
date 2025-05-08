<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BATO CLINIC - COUPON MANAGEMENT</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
            font-size: 16px;
            line-height: 1.6;
        }
        .navbar-brand {
            font-weight: bold;
            letter-spacing: 1px;
            font-size: 1.4rem;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            background-color: #ffffff;
            border: none;
        }
        .card-header {
            background-color: #424242;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            font-weight: bold;
            color: #fff;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            font-size: 1.2rem;
            padding: 12px 20px;
        }
        .card-body {
            padding: 20px;
            font-size: 1.05rem;
        }
        .btn {
            font-size: 1.05rem;
            padding: 8px 16px;
        }
        .btn-primary {
            background-color: #ff6d00;
            border-color: #ff6d00;
        }
        .btn-primary:hover {
            background-color: #ff5722;
            border-color: #ff5722;
        }
        .btn-success {
            background-color: #43a047;
            border-color: #43a047;
        }
        .btn-success:hover {
            background-color: #388e3c;
            border-color: #388e3c;
        }
        .btn-danger {
            background-color: #d32f2f;
            border-color: #d32f2f;
        }
        .btn-danger:hover {
            background-color: #c62828;
            border-color: #c62828;
        }
        .btn-info {
            background-color: #546e7a;
            border-color: #546e7a;
            color: #fff;
        }
        .btn-info:hover {
            background-color: #455a64;
            border-color: #455a64;
            color: #fff;
        }
        .table th {
            font-weight: 600;
            background-color: #424242;
            color: #fff;
            font-size: 1.1rem;
            padding: 12px;
        }
        .table td {
            font-size: 1.05rem;
            padding: 12px;
            vertical-align: middle;
        }
        .table {
            background-color: #ffffff;
            border-radius: 5px;
            overflow: hidden;
        }
        .badge {
            font-size: 0.9rem;
            padding: 6px 10px;
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
        .form-control {
            font-size: 1.05rem;
            padding: 10px 15px;
            height: auto;
        }
        .form-control:focus {
            border-color: #ff6d00;
            box-shadow: 0 0 0 0.2rem rgba(255, 109, 0, 0.25);
        }
        .form-label {
            font-size: 1.05rem;
            font-weight: 500;
        }
        .navbar {
            background-color: #424242 !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 12px 0;
        }
        .navbar-nav {
            gap: 5px;
        }
        .nav-item {
            display: flex;
            align-items: center;
        }
        .nav-link {
            color: #fff !important;
            font-weight: 500;
            padding: 10px 18px;
            margin: 0 5px;
            border-radius: 4px;
            transition: all 0.2s ease;
            font-size: 1.1rem;
        }
        .nav-link:hover {
            color: #fff !important;
            background-color: rgba(255, 109, 0, 0.2);
        }
        .nav-link.active {
            background-color: rgba(255, 109, 0, 0.3);
        }
        .pagination .page-link {
            color: #ff6d00;
            font-size: 1.05rem;
            padding: 8px 16px;
        }
        .pagination .page-item.active .page-link {
            background-color: #ff6d00;
            border-color: #ff6d00;
        }
        /* Full width container */
        .container {
            max-width: 100% !important;
            padding-left: 25px;
            padding-right: 25px;
        }
        /* Adjust table for better full-width display */
        .table-responsive {
            overflow-x: auto;
        }
        /* Dashboard stat cards */
        .stat-card {
            border-left: 4px solid #ff6d00;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .stat-card h3 {
            margin-bottom: 8px;
            font-size: 1.8rem;
            font-weight: 600;
        }
        .stat-card p {
            margin-bottom: 0;
            color: #6c757d;
            font-size: 1.05rem;
        }
        .stat-card .icon {
            font-size: 2.2rem;
            color: #ff6d00;
            margin-bottom: 12px;
        }
        .stat-card.primary {
            border-left-color: #ff6d00;
        }
        .stat-card.success {
            border-left-color: #43a047;
        }
        .stat-card.warning {
            border-left-color: #ffab00;
        }
        .stat-card.danger {
            border-left-color: #d32f2f;
        }
        /* Headings */
        h1, h2, h3, h4, h5, h6 {
            font-weight: 600;
            margin-bottom: 1rem;
        }
        h1 { font-size: 2.2rem; }
        h2 { font-size: 1.9rem; }
        h3 { font-size: 1.6rem; }
        h4 { font-size: 1.4rem; }
        h5 { font-size: 1.2rem; }
        h6 { font-size: 1.1rem; }
        
        /* Alert messages */
        .alert {
            font-size: 1.05rem;
            padding: 15px 20px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>">BATO CLINIC - COUPON MANAGEMENT</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if(isLoggedIn()): ?>
                        <?php if(hasRole('admin')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>admin/dashboard.php">Dashboard</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>admin/create_coupon.php">Assign Coupon</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>admin/manage_coupons.php">Manage Coupons</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>admin/manage_coupon_types.php">Manage Coupon Types</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>admin/manage_services.php">Manage Services</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>admin/manage_users.php">Manage Users</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>redeem_coupon.php">Redeem Coupon</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>admin/reports.php">Reports</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>admin/buyer_redemption_report.php">Redemption History</a>
                            </li>
                        <?php elseif(hasRole('staff')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>admin/dashboard.php">Dashboard</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>admin/create_coupon.php">Assign Coupon</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>admin/manage_coupons.php">Manage Coupons</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>redeem_coupon.php">Redeem Coupon</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>admin/reports.php">Reports</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>admin/buyer_redemption_report.php">Redemption History</a>
                            </li>
                        <?php elseif(hasRole('buyer')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>buyer/dashboard.php">My Coupons</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>buyer/assign_recipient.php">Assign Recipient</a>
                            </li>
                        <?php elseif(hasRole('recipient')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>recipient/dashboard.php">My Coupons</a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if(isLoggedIn()): ?>
                        <li class="nav-item">
                            <span class="nav-link">Welcome, <?php echo $_SESSION['user_name']; ?></span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>login.php">Login</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container"><?php if(isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert" id="autoHideAlert">
            <?php echo $_SESSION['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <script>
            // Auto-hide alert after 3 seconds
            setTimeout(function() {
                const alertElement = document.getElementById('autoHideAlert');
                if (alertElement) {
                    const bsAlert = new bootstrap.Alert(alertElement);
                    bsAlert.close();
                }
            }, 3000);
        </script>
        <?php 
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
        ?>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
