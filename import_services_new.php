<?php
// Include database connection
require_once 'config/database.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

// List of services to import
$services = [
    ['name' => 'Follow Up', 'price' => 0.000],
    ['name' => 'Filler Retouch', 'price' => 0.000],
    ['name' => 'Urine Culture', 'price' => 10.000],
    ['name' => 'AMH', 'price' => 30.000],
    ['name' => 'Hyaluronidase (1ml)', 'price' => 20.000],
    ['name' => 'Tightening For Double Chin Withi RF Package', 'price' => 150.000],
    ['name' => 'Tightening For Double Chin With RF', 'price' => 70.000],
    ['name' => 'Double chin Meso + RF Package', 'price' => 360.000],
    ['name' => 'Double chin Meso Package', 'price' => 210.000],
    ['name' => 'Double chin Meso Session', 'price' => 85.000],
    ['name' => 'Progesterone', 'price' => 7.000],
    ['name' => 'Normal Sline', 'price' => 6.000],
    ['name' => 'Hair Treatment-5Sessions + Plasma-3Sessions+Blood Test', 'price' => 1042.000],
    ['name' => '4D Session-Including (yaqoot,Saffron,Neofound & Prp)', 'price' => 395.000],
    ['name' => 'Botox Retouch', 'price' => 0.000],
    ['name' => 'SHBG', 'price' => 11.000],
    ['name' => 'DHEA-S', 'price' => 10.000],
    ['name' => 'HAYDRAFACIAL', 'price' => 55.000],
    ['name' => 'Plasma', 'price' => 80.000],
    ['name' => 'Rft blood test', 'price' => 18.000],
    ['name' => 'Lft blood test', 'price' => 20.000],
    ['name' => 'Microneedling Rf with hydrafacial', 'price' => 225.000],
    ['name' => 'Hair Treatment', 'price' => 150.000],
    ['name' => 'Urine Routine', 'price' => 6.000],
    ['name' => 'Yaqoot', 'price' => 130.000],
    ['name' => 'Cholesterol', 'price' => 5.000],
    ['name' => 'PTH', 'price' => 9.000],
    ['name' => 'LH', 'price' => 6.000],
    ['name' => 'Fsh', 'price' => 6.000],
    ['name' => 'Estradiol', 'price' => 9.000],
    ['name' => 'Glutathione Blood Test', 'price' => 40.000],
    ['name' => 'Mezo Deep Injection-4', 'price' => 400.000],
    ['name' => 'Mezo Deep Injection-3', 'price' => 300.000],
    ['name' => 'Mezo Deep Injection-2', 'price' => 200.000],
    ['name' => 'Mezo Deep Injection', 'price' => 120.000],
    ['name' => 'Microneedling RF', 'price' => 175.000],
    ['name' => 'Teosyal KISS', 'price' => 140.000],
    ['name' => 'Teosyal RHA3', 'price' => 140.000],
    ['name' => 'Teosyal RHA4', 'price' => 140.000],
    ['name' => 'Follow-Up - 50', 'price' => 50.000],
    ['name' => 'D Bilirubin - 6', 'price' => 6.000],
    ['name' => 'T Bilirubin - 6', 'price' => 6.000],
    ['name' => 'Olfen Injection - 5', 'price' => 5.000],
    ['name' => 'Skin Booster - 85', 'price' => 85.000],
    ['name' => 'PROFHILO - 150', 'price' => 150.000],
    ['name' => 'Hyaluronidase (5ml) - 100', 'price' => 100.000],
    ['name' => 'Hyaluronidase (4ml) - 80', 'price' => 80.000],
    ['name' => 'Hyaluronidase (3ml) - 60', 'price' => 60.000],
    ['name' => 'Hyaluronidase (2ml) - 40', 'price' => 40.000],
    ['name' => 'Teosyal RHA', 'price' => 140.000],
    ['name' => 'Juvederm Ultra 4 - 160', 'price' => 160.000],
    ['name' => 'Juvederm Ultra 3 - 160', 'price' => 160.000],
    ['name' => 'Botox Underarm - 200', 'price' => 200.000],
    ['name' => 'Botox Neck- 130', 'price' => 130.000],
    ['name' => 'Botox Joline- 70', 'price' => 70.000],
    ['name' => 'Botox Gum Smile - 30', 'price' => 30.000],
    ['name' => 'Botox Nose - 50', 'price' => 50.000],
    ['name' => 'Botox Only Head - 120', 'price' => 120.000],
    ['name' => 'Botox Only Eyes - 90', 'price' => 90.000],
    ['name' => 'Botox Head & Eyes - 150', 'price' => 150.000],
    ['name' => 'INJECTION-NEOFOUND-120', 'price' => 120.000],
    ['name' => 'Blood Test- Fbs -6', 'price' => 6.000],
    ['name' => 'CONSULTATION - 40', 'price' => 40.000],
    ['name' => 'MICRO EYE LINER', 'price' => 250.000],
    ['name' => 'ALT ( SGPT )-6', 'price' => 6.000],
    ['name' => 'AST (SGOT )-6', 'price' => 6.000],
    ['name' => 'GGT-6', 'price' => 6.000],
    ['name' => 'Electrolytes ( Na,K,CI)-15', 'price' => 15.000],
    ['name' => 'Cheatinine-6', 'price' => 6.000],
    ['name' => 'Urea-6', 'price' => 6.000],
    ['name' => 'Blood Test-Hba1c-10', 'price' => 10.000],
    ['name' => 'Growth Hormone-10', 'price' => 10.000],
    ['name' => 'Home IR blood test-22', 'price' => 22.000],
    ['name' => 'Blood Test - Mg -8', 'price' => 8.000],
    ['name' => 'Blood Test- Copper -20', 'price' => 20.000],
    ['name' => 'BLOOD GROUP TEST-4', 'price' => 4.000],
    ['name' => 'Drip Glutathione-4', 'price' => 220.000],
    ['name' => 'Drip Glutathione-3', 'price' => 195.000],
    ['name' => 'Drip Glutathione-2', 'price' => 150.000],
    ['name' => 'Drip Glutathione-1', 'price' => 85.000],
    ['name' => 'REMOVAL SESSION - LASER', 'price' => 70.000],
    ['name' => 'REMOVAL SESSION', 'price' => 100.000],
    ['name' => 'EYELINER RETOUCH', 'price' => 20.000],
    ['name' => 'EYELINER', 'price' => 250.000],
    ['name' => 'MICRO RETOUCH', 'price' => 20.000],
    ['name' => 'MICRO', 'price' => 300.000],
    ['name' => 'TAWREED RETOUCH-2', 'price' => 20.000],
    ['name' => 'TAWREED RETOUCH-1', 'price' => 50.000],
    ['name' => 'TAWREED', 'price' => 350.000],
    ['name' => 'LAVIN AREA WITH MEZO - 3 SESSIONS', 'price' => 300.000],
    ['name' => 'LAVIN AREA WITH MEZO - 2 SESSIONS', 'price' => 200.000],
    ['name' => 'LAVIN 1 AREA WITH MEZO', 'price' => 120.000],
    ['name' => 'LAVIN WHITE MEZO -FACE', 'price' => 150.000],
    ['name' => 'LAVIN WHITE MEZO BACK', 'price' => 200.000],
    ['name' => 'LAVIN W/O MEZO BACK', 'price' => 150.000],
    ['name' => 'ROACCUTANE ALTERNATIVE 3 SESSIONS', 'price' => 225.000],
    ['name' => 'ROACCUTANE ALTERNATIVE 2 SESSIONS', 'price' => 150.000],
    ['name' => 'SALICYLIC ACID', 'price' => 30.000],
    ['name' => 'PEEL COSMELAN', 'price' => 200.000],
    ['name' => 'PEEL GREEN FOR BACK', 'price' => 140.000],
    ['name' => 'PEEL GREEN FOR FACE', 'price' => 70.000],
    ['name' => 'PEEL BKINI', 'price' => 200.000],
    ['name' => 'PEEL 3 AREA', 'price' => 180.000],
    ['name' => 'PEEL 1 AREA', 'price' => 70.000],
    ['name' => 'Blood Test -( Hormones + Vitamins ) 102', 'price' => 102.000],
    ['name' => 'Hair Treatment 550+2', 'price' => 550.000],
    ['name' => 'Hair Treatment-200', 'price' => 200.000],
    ['name' => 'Blood Test', 'price' => 72.000],
    ['name' => 'PROLACTIN', 'price' => 6.000],
    ['name' => 'FREE TESTO', 'price' => 12.000],
    ['name' => 'TESTO', 'price' => 6.000],
    ['name' => 'FT4', 'price' => 6.000],
    ['name' => 'FT3', 'price' => 6.000],
    ['name' => 'TSH', 'price' => 6.000],
    ['name' => 'VIT D', 'price' => 15.000],
    ['name' => 'VIT B12', 'price' => 9.000],
    ['name' => 'ZINC', 'price' => 12.000],
    ['name' => 'FERRITIN', 'price' => 9.000],
    ['name' => 'IRON', 'price' => 6.000],
    ['name' => 'Blood Test ( HORMONES )', 'price' => 42.000],
    ['name' => 'Blood Test ( VITAMINS )', 'price' => 60.000],
    ['name' => 'Hair Treatment half head +1', 'price' => 450.000],
    ['name' => 'Hair Treatment full head +2', 'price' => 650.000],
    ['name' => 'Hair Treatment - Renew Half Head', 'price' => 300.000],
    ['name' => 'Hair Treatment - Renew full Head', 'price' => 350.000],
    ['name' => 'DERMAPEN MEZZO / PLASMA 1SESSION', 'price' => 120.000],
    ['name' => 'DERMAPEN MEZZO / PLASMA 3SESSION', 'price' => 290.000],
    ['name' => 'ROSE SESSION', 'price' => 130.000],
    ['name' => 'PROFHILO', 'price' => 199.000],
    ['name' => 'Profound', 'price' => 85.000],
    ['name' => 'MEZZO UNDER EYE', 'price' => 90.000],
    ['name' => 'MEZZO', 'price' => 199.000],
    ['name' => 'MEZZO 1', 'price' => 85.000],
    ['name' => 'HAYDRAFACIAL & DERMAPEN', 'price' => 150.000],
    ['name' => 'WASH HAIR', 'price' => 2.000],
    ['name' => 'CONSULTATION', 'price' => 20.000],
    ['name' => 'MASK NEBULISATION', 'price' => 10.000],
    ['name' => 'INJECTION VOLTAREN', 'price' => 5.000],
    ['name' => 'INJECTION NEUROBION', 'price' => 5.000],
    ['name' => 'INJECTION D', 'price' => 10.000],
    ['name' => 'INJECTION B12', 'price' => 10.000],
    ['name' => 'DRIP SERUM', 'price' => 10.000],
    ['name' => 'DRIP IRON', 'price' => 30.000],
    ['name' => 'LASER CHET+ ABDOMEN', 'price' => 25.000],
    ['name' => 'LASER FULL HANDS (M)', 'price' => 20.000],
    ['name' => 'LASER SHOLDER', 'price' => 10.000],
    ['name' => 'LASER BKINI', 'price' => 10.000],
    ['name' => 'LASER BUTTOCKS', 'price' => 10.000],
    ['name' => 'LASER FULL HAND (W)', 'price' => 15.000],
    ['name' => 'LASER NECK (W&M)', 'price' => 10.000],
    ['name' => 'LASER FULL LEGS (W&M)', 'price' => 15.000],
    ['name' => 'LASER LOWER LEG (W&M)', 'price' => 10.000],
    ['name' => 'LASER THIGS (W&M)', 'price' => 10.000],
    ['name' => 'LASER BACK (W&M)', 'price' => 15.000],
    ['name' => 'LASER ABDOMEN (W&M)', 'price' => 15.000],
    ['name' => 'LASER CHEST (W&M)', 'price' => 15.000],
    ['name' => 'LASER UNDER ARMS (W&M)', 'price' => 10.000],
    ['name' => 'LASER UPPER ARMS (W&M)', 'price' => 10.000],
    ['name' => 'LASER LOWER ARMS (W&M)', 'price' => 10.000],
    ['name' => 'LASER CHIN (W&M)', 'price' => 5.000],
    ['name' => 'LASER UPPER LIP (W&M)', 'price' => 5.000],
    ['name' => 'LASER FACE (W&M)', 'price' => 10.000],
    ['name' => 'LASER FULL BODY MAN', 'price' => 70.000],
    ['name' => 'LASER FULL BODY STUDENTS W/O B&A', 'price' => 40.000],
    ['name' => 'LASER FULL BODY STUDENTS', 'price' => 40.000],
    ['name' => 'LASER FULL BODY W/O B&A', 'price' => 48.000],
    ['name' => 'LASER FULL BODY', 'price' => 58.000],
    ['name' => 'Deposit - Arboon', 'price' => 30.000],
    ['name' => 'Medical Consultation', 'price' => 40.000] // Added this as the first service
];

// Remove duplicates by name
$unique_services = [];
$seen_names = [];

foreach ($services as $service) {
    if (!isset($seen_names[$service['name']])) {
        $unique_services[] = $service;
        $seen_names[$service['name']] = true;
    }
}

// Sort services alphabetically
usort($unique_services, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// Move "Medical Consultation" to the top
foreach ($unique_services as $key => $service) {
    if ($service['name'] === 'Medical Consultation') {
        // Remove it from current position
        $medical_consultation = $unique_services[$key];
        unset($unique_services[$key]);
        
        // Add it to the beginning
        array_unshift($unique_services, $medical_consultation);
        
        // Reindex array
        $unique_services = array_values($unique_services);
        break;
    }
}

try {
    // Start transaction
    $db->beginTransaction();
    
    // Disable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Delete all from redemption_logs
    $db->exec("DELETE FROM redemption_logs");
    
    // Delete all from services
    $db->exec("DELETE FROM services");
    
    // Reset auto increment for services
    $db->exec("ALTER TABLE services AUTO_INCREMENT = 1");
    
    // Insert services with new IDs
    $query = "INSERT INTO services (name, description, default_price) VALUES (?, ?, ?)";
    $stmt = $db->prepare($query);
    
    $count = 0;
    foreach ($unique_services as $service) {
        $description = "";
        $stmt->bindParam(1, $service['name']);
        $stmt->bindParam(2, $description);
        $stmt->bindParam(3, $service['price']);
        $stmt->execute();
        $count++;
    }
    
    // Re-enable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Commit transaction
    $db->commit();
    
    echo "<!DOCTYPE html>
<html>
<head>
    <title>Import Services</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        h2 {
            color: #333;
            margin-top: 30px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .btn {
            display: inline-block;
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class='alert alert-success'>
        <h3>Success!</h3>
        <p>Successfully imported $count services with sequential IDs starting from 1.</p>
        <p>All duplicate services have been removed.</p>
        <p>Medical Consultation is now ID 1.</p>
    </div>
    
    <a href='admin/manage_services.php' class='btn'>Go to Manage Services</a>
</body>
</html>";
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    // Re-enable foreign key checks in case of error
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "<!DOCTYPE html>
<html>
<head>
    <title>Import Services Error</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        h2 {
            color: #333;
            margin-top: 30px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .btn {
            display: inline-block;
            padding: 10px 15px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .btn:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class='alert alert-danger'>
        <h3>Error</h3>
        <p>An error occurred: " . $e->getMessage() . "</p>
    </div>
    
    <a href='admin/manage_services.php' class='btn'>Go to Manage Services</a>
</body>
</html>";
}
?>
