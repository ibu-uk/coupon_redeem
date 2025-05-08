<?php
// Application configuration

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base URL configuration
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . $host . '/coupon%20redeem/';

// Define constants
define('BASE_URL', $baseUrl);
define('ROOT_PATH', dirname(__DIR__) . '/');
define('INCLUDE_PATH', ROOT_PATH . 'includes/');
define('TEMPLATE_PATH', ROOT_PATH . 'templates/');

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Time zone
date_default_timezone_set('UTC');

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check if user has specific role
function hasRole($role) {
    // If checking for multiple roles (array)
    if (is_array($role)) {
        return isLoggedIn() && in_array($_SESSION['user_role'], $role);
    }
    // Single role check
    return isLoggedIn() && $_SESSION['user_role'] === $role;
}

// Function to check if user has staff or admin privileges
function hasStaffPrivileges() {
    return isLoggedIn() && ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'staff');
}

// Function to redirect
function redirect($path) {
    header("Location: " . BASE_URL . $path);
    exit();
}

// Function to sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>
