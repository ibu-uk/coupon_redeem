<?php
// Include configuration file
require_once '../config/config.php';
require_once '../config/database.php';

// Set headers for AJAX response
header('Content-Type: application/json');
header('Cache-Control: private, max-age=60'); // Allow browser caching for 60 seconds

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check if search term is provided
if (!isset($_GET['search']) || empty($_GET['search'])) {
    echo json_encode(['error' => 'Search term is required']);
    exit;
}

// Get search term and type
$search = trim($_GET['search']);
$type = isset($_GET['type']) ? $_GET['type'] : 'all'; // 'buyer', 'recipient', or 'all'

// Minimum search length to reduce load
if (strlen($search) < 3) {
    echo json_encode(['error' => 'Please enter at least 3 characters to search']);
    exit;
}

// Prepare query to search for customers - optimized for performance
$query = "SELECT id, full_name, email, civil_id, mobile_number, file_number, role FROM users WHERE ";

// Determine the most likely search field based on the input format
$exactMatch = false;
$params = [];
$conditions = [];

// Check if search looks like a civil ID (mostly numbers)
if (preg_match('/^[0-9]{4,}$/', $search)) {
    $conditions[] = "civil_id LIKE ?";
    $params[] = "$search%"; // Starts with for better index usage
    $exactMatch = true;
} 
// Check if search looks like a mobile number
else if (preg_match('/^[0-9+]{4,}$/', $search)) {
    $conditions[] = "mobile_number LIKE ?";
    $params[] = "$search%"; // Starts with for better index usage
    $exactMatch = true;
} 
// Check if search looks like an email
else if (strpos($search, '@') !== false) {
    $conditions[] = "email LIKE ?";
    $params[] = "$search%"; // Starts with for better index usage
    $exactMatch = true;
}
// Otherwise use a more general search
else {
    // Search by name (most common)
    $conditions[] = "full_name LIKE ?";
    $params[] = "$search%"; // Starts with for better index usage
    
    // Add other fields with lower priority
    $conditions[] = "email LIKE ?";
    $params[] = "%$search%";
    
    $conditions[] = "civil_id LIKE ?";
    $params[] = "$search%";
    
    $conditions[] = "mobile_number LIKE ?";
    $params[] = "$search%";
    
    $conditions[] = "file_number LIKE ?";
    $params[] = "$search%";
}

// Add role filter if specified
$roleCondition = '';
if ($type === 'buyer') {
    $roleCondition = "role = 'buyer'";
} else if ($type === 'recipient') {
    $roleCondition = "role = 'recipient'";
} else {
    // For 'all', include both buyers and recipients
    $roleCondition = "(role = 'buyer' OR role = 'recipient')";
}

// Combine conditions with OR for search fields and AND for role
$query .= "(" . implode(" OR ", $conditions) . ") AND " . $roleCondition;

// Add ordering to prioritize exact matches
$query .= " ORDER BY " . ($exactMatch ? 
    "CASE WHEN {$conditions[0]} THEN 1 ELSE 2 END" : 
    "full_name");

// Limit results to prevent overwhelming response
$query .= " LIMIT 15";

// Prepare and execute query
$stmt = $db->prepare($query);

// Bind parameters
for ($i = 0; $i < count($params); $i++) {
    $stmt->bindParam($i + 1, $params[$i]);
}

// Add timeout to prevent long-running queries
$db->setAttribute(PDO::ATTR_TIMEOUT, 5); // 5 seconds timeout

// Execute with error handling
try {
    $startTime = microtime(true);
    $stmt->execute();
    $executionTime = microtime(true) - $startTime;
    
    // Log slow queries (over 1 second)
    if ($executionTime > 1) {
        error_log("Slow customer search query: {$executionTime}s for term '{$search}'");
    }
    
    // Fetch results
    $customers = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Format the display name to include identifying information
        $displayName = $row['full_name'];
        
        // Add civil ID if available (but truncate for privacy/display)
        if (!empty($row['civil_id'])) {
            $civilId = $row['civil_id'];
            $maskedCivilId = substr($civilId, 0, 3) . '...' . substr($civilId, -3);
            $displayName .= " (ID: {$maskedCivilId})";
        } 
        // Otherwise add mobile if available (but truncate for privacy/display)
        else if (!empty($row['mobile_number'])) {
            $mobile = $row['mobile_number'];
            $maskedMobile = substr($mobile, 0, 3) . '...' . substr($mobile, -3);
            $displayName .= " (Mobile: {$maskedMobile})";
        }
        
        $customers[] = [
            'id' => $row['id'],
            'display_name' => $displayName,
            'full_name' => $row['full_name'],
            'email' => $row['email'],
            'civil_id' => $row['civil_id'],
            'mobile_number' => $row['mobile_number'],
            'file_number' => $row['file_number'],
            'role' => $row['role']
        ];
    }
    
    // Return results with execution stats (for debugging)
    $response = [
        'customers' => $customers,
        'count' => count($customers),
        'execution_time_ms' => round($executionTime * 1000)
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    // Log error but return generic message to user
    error_log("Customer search error: " . $e->getMessage());
    echo json_encode([
        'error' => 'Database search error. Please try a more specific search term.',
        'customers' => []
    ]);
}
?>
