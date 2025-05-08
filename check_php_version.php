<?php
// Display PHP version information
echo "<h2>PHP Version Information</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>PHP Extensions: </p>";
echo "<pre>";
print_r(get_loaded_extensions());
echo "</pre>";
?>
