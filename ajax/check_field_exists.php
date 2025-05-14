<?php
// Include configuration file
require_once '../config/config.php';
require_once '../config/database.php';

// Set headers for AJAX response
header('Content-Type: application/json');

// Check if required parameters are provided
if (!isset($_POST['field']) || !isset($_POST['value'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// Get parameters
$field = $_POST['field'];
$value = $_POST['value'];

// Validate field name to prevent SQL injection
$allowedFields = ['civil_id', 'mobile_number', 'file_number', 'email'];
if (!in_array($field, $allowedFields)) {
    echo json_encode(['error' => 'Invalid field name']);
    exit;
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Prepare query to check if field exists
$query = "SELECT COUNT(*) as count FROM users WHERE $field = :value";
$stmt = $db->prepare($query);
$stmt->bindParam(':value', $value);
$stmt->execute();

// Get result
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$exists = ($row['count'] > 0);

// Return result
echo json_encode(['exists' => $exists]);
?>
