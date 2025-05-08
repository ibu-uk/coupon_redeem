<?php
// Script to check and install required dependencies

// Check if TCPDF library exists
if (!file_exists(__DIR__ . '/lib/tcpdf/tcpdf.php')) {
    echo "TCPDF library not found. Installing...<br>";
    
    // Create lib directory if it doesn't exist
    if (!is_dir(__DIR__ . '/lib')) {
        mkdir(__DIR__ . '/lib', 0755, true);
    }
    
    // Create tcpdf directory if it doesn't exist
    if (!is_dir(__DIR__ . '/lib/tcpdf')) {
        mkdir(__DIR__ . '/lib/tcpdf', 0755, true);
    }
    
    // URL to the TCPDF library
    $tcpdfUrl = 'https://github.com/tecnickcom/TCPDF/archive/refs/tags/6.6.2.zip';
    $zipFile = __DIR__ . '/lib/tcpdf.zip';
    
    // Download TCPDF
    echo "Downloading TCPDF...<br>";
    $zipContent = file_get_contents($tcpdfUrl);
    if ($zipContent === false) {
        die("Failed to download TCPDF. Please check your internet connection and try again.");
    }
    
    // Save the zip file
    file_put_contents($zipFile, $zipContent);
    
    // Extract the zip file
    echo "Extracting TCPDF...<br>";
    $zip = new ZipArchive;
    if ($zip->open($zipFile) === TRUE) {
        $zip->extractTo(__DIR__ . '/lib/');
        $zip->close();
        
        // Move files from the extracted directory to the tcpdf directory
        echo "Installing TCPDF...<br>";
        $extractedDir = __DIR__ . '/lib/TCPDF-6.6.2';
        $tcpdfDir = __DIR__ . '/lib/tcpdf';
        
        // Copy files
        copyDir($extractedDir, $tcpdfDir);
        
        // Clean up
        deleteDir($extractedDir);
        unlink($zipFile);
        
        echo "TCPDF installed successfully!<br>";
    } else {
        die("Failed to extract TCPDF. Please try again or install manually.");
    }
} else {
    echo "TCPDF library is already installed.<br>";
}

echo "<br>All dependencies are installed. Your system is ready to use!<br>";
echo "<a href='index.php'>Go to the application</a>";

// Helper function to copy directory
function copyDir($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while (($file = readdir($dir)) !== false) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                copyDir($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

// Helper function to delete directory
function deleteDir($dirPath) {
    if (!is_dir($dirPath)) {
        return;
    }
    
    $files = scandir($dirPath);
    foreach ($files as $file) {
        if ($file != "." && $file != "..") {
            $filePath = $dirPath . '/' . $file;
            if (is_dir($filePath)) {
                deleteDir($filePath);
            } else {
                unlink($filePath);
            }
        }
    }
    rmdir($dirPath);
}
?>
