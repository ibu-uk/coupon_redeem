<?php
// Include configuration file
require_once 'config/config.php';
require_once 'config/database.php';

// Session protection functions
function generateFormToken($formName) {
    $token = md5(uniqid(rand(), true));
    $_SESSION[$formName.'_token'] = $token;
    $_SESSION[$formName.'_token_time'] = time();
    return $token;
}

function validateFormToken($formName, $token) {
    // Check if token exists in session
    if (!isset($_SESSION[$formName.'_token']) || !isset($_SESSION[$formName.'_token_time'])) {
        return false;
    }
    
    // Check if token matches
    if ($_SESSION[$formName.'_token'] !== $token) {
        return false;
    }
    
    // Check if token is expired (30 minutes)
    $tokenTime = $_SESSION[$formName.'_token_time'];
    if (time() - $tokenTime > 1800) {
        unset($_SESSION[$formName.'_token']);
        unset($_SESSION[$formName.'_token_time']);
        return false;
    }
    
    // Token is valid, remove it to prevent reuse
    unset($_SESSION[$formName.'_token']);
    unset($_SESSION[$formName.'_token_time']);
    return true;
}

// Function to check for duplicate submissions
function checkDuplicateSubmission($db, $tableName, $conditions, $timeWindow = 5) {
    $query = "SELECT COUNT(*) as count FROM " . $tableName . " WHERE ";
    $whereConditions = [];
    $params = [];
    
    foreach ($conditions as $field => $value) {
        $whereConditions[] = "$field = ?";
        $params[] = $value;
    }
    
    $query .= implode(" AND ", $whereConditions);
    
    // Add time window condition if applicable
    if ($tableName === 'redemption_logs') {
        $query .= " AND DATE(redemption_date) = CURDATE() AND TIMESTAMPDIFF(MINUTE, CONCAT(redemption_date, ' ', redemption_time), NOW()) < $timeWindow";
    } else {
        $query .= " AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) < $timeWindow";
    }
    
    $stmt = $db->prepare($query);
    
    for ($i = 0; $i < count($params); $i++) {
        $stmt->bindParam($i+1, $params[$i]);
    }
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] > 0;
}

// If this file is accessed directly, redirect to home
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("Location: index.php");
    exit();
}
?>
