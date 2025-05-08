<?php
// Include configuration file
require_once 'config/config.php';

// Destroy session
session_destroy();

// Redirect to login page
header("Location: " . BASE_URL . "login.php");
exit();
?>
