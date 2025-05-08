<?php
// Include configuration file
require_once 'config/config.php';
require_once 'config/database.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Query to get all recipients
$query = "SELECT id, username, full_name, role FROM users WHERE role = 'recipient'";
$stmt = $db->prepare($query);
$stmt->execute();

echo "<h2>Recipients in Database</h2>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Role</th></tr>";

$count = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['username'] . "</td>";
    echo "<td>" . $row['full_name'] . "</td>";
    echo "<td>" . $row['role'] . "</td>";
    echo "</tr>";
    $count++;
}

echo "</table>";
echo "<p>Total Recipients: $count</p>";

// If no recipients found with role='recipient', check if they might have a different role
if ($count == 0) {
    echo "<h2>Checking for users named Ismail</h2>";
    $query = "SELECT id, username, full_name, role FROM users WHERE full_name LIKE '%Ismail%'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Role</th></tr>";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['username'] . "</td>";
        echo "<td>" . $row['full_name'] . "</td>";
        echo "<td>" . $row['role'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Fix: Update Ismail's role to 'recipient' if found with a different role
    $query = "UPDATE users SET role = 'recipient' WHERE full_name LIKE '%Ismail%' AND role != 'recipient'";
    $stmt = $db->prepare($query);
    $result = $stmt->execute();
    
    if ($result) {
        echo "<p>Updated Ismail's role to 'recipient'</p>";
    }
}
?>
