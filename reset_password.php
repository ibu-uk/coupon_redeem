<?php
// Generate password hash for 'admin123'
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Password: $password<br>";
echo "Hash: $hash<br>";

// SQL to update admin password
echo "SQL to update password:<br>";
echo "UPDATE users SET password = '$hash' WHERE username = 'admin';";
?>
