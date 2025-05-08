<?php
// Run admin login check to ensure admin account is valid
require_once 'admin_login_check.php';

// Include configuration file
require_once 'config/config.php';

// Redirect to login page if not logged in
if(!isLoggedIn()) {
    redirect('login.php');
}

// Redirect based on user role
if(hasRole('admin')) {
    redirect('admin/dashboard.php');
} elseif(hasRole('staff')) {
    redirect('admin/dashboard.php');
} elseif(hasRole('buyer')) {
    redirect('buyer/dashboard.php');
} elseif(hasRole('recipient')) {
    redirect('recipient/dashboard.php');
}
?>
