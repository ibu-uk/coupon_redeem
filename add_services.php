<?php
// Include configuration and models
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'models/Service.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Initialize service object
$service = new Service($db);

// Array of services to add
$services = [
    ['name' => 'Dental Implant', 'default_price' => 350.00],
    ['name' => 'PFM', 'default_price' => 70.00],
    ['name' => 'Extraction', 'default_price' => 10.00],
    ['name' => 'Orthodontic Treatment (Full)', 'default_price' => 700.00],
    ['name' => 'Orthodontic Treatment (1st Installment)', 'default_price' => 300.00],
    ['name' => 'Orthodontic Treatment (2nd Installment)', 'default_price' => 200.00],
    ['name' => 'Orthodontic Treatment (3rd Installment)', 'default_price' => 200.00],
    ['name' => 'Orthodontic Treatment (Per Month)', 'default_price' => 25.00],
    ['name' => 'Orthodontic Treatment (Retainer)', 'default_price' => 50.00],
    ['name' => 'Orthodontic Treatment (Consultation)', 'default_price' => 15.00],
    ['name' => 'Root Canal Treatment (Anterior)', 'default_price' => 70.00],
    ['name' => 'Root Canal Treatment (Premolar)', 'default_price' => 100.00],
    ['name' => 'Root Canal Treatment (Molar)', 'default_price' => 150.00],
    ['name' => 'Composite Filling', 'default_price' => 25.00],
    ['name' => 'Amalgam Filling', 'default_price' => 20.00],
    ['name' => 'Scaling & Polishing', 'default_price' => 25.00],
    ['name' => 'Consultation', 'default_price' => 10.00],
    ['name' => 'X-Ray', 'default_price' => 5.00],
    ['name' => 'Panoramic X-Ray', 'default_price' => 15.00],
    ['name' => 'Teeth Whitening', 'default_price' => 150.00],
    ['name' => 'Veneer (Composite)', 'default_price' => 60.00],
    ['name' => 'Veneer (Porcelain)', 'default_price' => 200.00],
    ['name' => 'Crown (PFM)', 'default_price' => 120.00],
    ['name' => 'Crown (Zirconia)', 'default_price' => 200.00],
    ['name' => 'Crown (E-Max)', 'default_price' => 250.00],
    ['name' => 'Bridge (Per Unit)', 'default_price' => 120.00],
    ['name' => 'Denture (Complete)', 'default_price' => 250.00],
    ['name' => 'Denture (Partial)', 'default_price' => 150.00],
    ['name' => 'Denture Repair', 'default_price' => 30.00],
    ['name' => 'Gum Treatment (Per Quadrant)', 'default_price' => 50.00],
    ['name' => 'Surgical Extraction', 'default_price' => 50.00],
    ['name' => 'Wisdom Tooth Extraction', 'default_price' => 80.00],
    ['name' => 'Pediatric Dental Treatment', 'default_price' => 30.00],
    ['name' => 'Sealant (Per Tooth)', 'default_price' => 15.00],
    ['name' => 'Fluoride Treatment', 'default_price' => 20.00],
    ['name' => 'Mouth Guard', 'default_price' => 50.00],
    ['name' => 'Splint', 'default_price' => 60.00],
    ['name' => 'Post & Core', 'default_price' => 50.00],
    ['name' => 'Inlay/Onlay', 'default_price' => 150.00],
    ['name' => 'Oral Surgery (Minor)', 'default_price' => 70.00],
    ['name' => 'Oral Surgery (Major)', 'default_price' => 150.00],
    ['name' => 'Implant Abutment', 'default_price' => 150.00],
    ['name' => 'Implant Crown', 'default_price' => 200.00],
    ['name' => 'Bone Graft', 'default_price' => 200.00],
    ['name' => 'Sinus Lift', 'default_price' => 300.00],
    ['name' => 'Gum Graft', 'default_price' => 200.00],
    ['name' => 'Gingivectomy', 'default_price' => 100.00],
    ['name' => 'Laser Treatment', 'default_price' => 80.00],
    ['name' => 'Botox (Per Area)', 'default_price' => 150.00],
    ['name' => 'Dermal Fillers', 'default_price' => 200.00],
    ['name' => 'Dental Check-up', 'default_price' => 15.00],
    ['name' => 'Emergency Treatment', 'default_price' => 50.00],
    ['name' => 'Dental Cleaning', 'default_price' => 30.00],
    ['name' => 'Deep Cleaning', 'default_price' => 60.00],
    ['name' => 'Periodontal Treatment', 'default_price' => 100.00],
    ['name' => 'Dental Abscess Treatment', 'default_price' => 50.00],
    ['name' => 'Dental Bonding', 'default_price' => 40.00],
    ['name' => 'Dental Sealants', 'default_price' => 20.00],
    ['name' => 'Dental Crowns', 'default_price' => 150.00],
    ['name' => 'Dental Bridges', 'default_price' => 300.00],
    ['name' => 'Dental Veneers', 'default_price' => 200.00],
    ['name' => 'Dental Implants', 'default_price' => 500.00],
    ['name' => 'Teeth Whitening (In-Office)', 'default_price' => 200.00],
    ['name' => 'Teeth Whitening (Take-Home Kit)', 'default_price' => 100.00],
    ['name' => 'Oral Cancer Screening', 'default_price' => 30.00],
    ['name' => 'TMJ Treatment', 'default_price' => 80.00],
    ['name' => 'Bruxism Treatment', 'default_price' => 60.00],
    ['name' => 'Dental X-rays', 'default_price' => 10.00],
    ['name' => 'Dental CT Scan', 'default_price' => 100.00],
    ['name' => 'Dental Consultation', 'default_price' => 20.00],
    ['name' => 'Pediatric Dental Exam', 'default_price' => 25.00],
    ['name' => 'Dental Filling', 'default_price' => 30.00],
    ['name' => 'Tooth Extraction', 'default_price' => 40.00],
    ['name' => 'Wisdom Tooth Removal', 'default_price' => 100.00],
    ['name' => 'Root Canal Therapy', 'default_price' => 120.00],
    ['name' => 'Dental Crown', 'default_price' => 180.00],
    ['name' => 'Dental Bridge', 'default_price' => 350.00],
    ['name' => 'Dental Implant (Full)', 'default_price' => 600.00],
    ['name' => 'Dentures (Full Set)', 'default_price' => 400.00],
    ['name' => 'Partial Dentures', 'default_price' => 250.00],
    ['name' => 'Invisalign Treatment', 'default_price' => 800.00],
    ['name' => 'Dental Braces', 'default_price' => 700.00],
    ['name' => 'Periodontal Scaling', 'default_price' => 70.00],
    ['name' => 'Gum Disease Treatment', 'default_price' => 90.00],
    ['name' => 'Dental Sealant Application', 'default_price' => 25.00],
    ['name' => 'Fluoride Application', 'default_price' => 20.00],
    ['name' => 'Dental Restoration', 'default_price' => 50.00],
    ['name' => 'Cosmetic Dentistry Consultation', 'default_price' => 30.00],
    ['name' => 'Smile Makeover', 'default_price' => 500.00],
    ['name' => 'Dental Prophylaxis', 'default_price' => 35.00],
    ['name' => 'Dental Examination', 'default_price' => 15.00],
    ['name' => 'Oral Hygiene Instruction', 'default_price' => 10.00],
    ['name' => 'Dental Pain Management', 'default_price' => 40.00],
    ['name' => 'Dental Sedation', 'default_price' => 100.00],
    ['name' => 'Dental Anesthesia', 'default_price' => 50.00],
    ['name' => 'Dental Impressions', 'default_price' => 30.00],
    ['name' => 'Dental Prosthetics', 'default_price' => 200.00],
    ['name' => 'Dental Appliance Repair', 'default_price' => 40.00],
    ['name' => 'Dental Appliance Adjustment', 'default_price' => 20.00],
    ['name' => 'Dental Appliance Cleaning', 'default_price' => 15.00],
    ['name' => 'Dental Appliance Replacement', 'default_price' => 100.00],
    ['name' => 'Dental Appliance Fitting', 'default_price' => 30.00],
    ['name' => 'Dental Appliance Consultation', 'default_price' => 20.00],
    ['name' => 'Dental Appliance Maintenance', 'default_price' => 25.00],
    ['name' => 'Dental Appliance Inspection', 'default_price' => 15.00],
    ['name' => 'Dental Appliance Modification', 'default_price' => 35.00],
    ['name' => 'Dental Appliance Design', 'default_price' => 50.00],
    ['name' => 'Dental Appliance Fabrication', 'default_price' => 80.00],
    ['name' => 'Dental Appliance Installation', 'default_price' => 40.00],
    ['name' => 'Dental Appliance Removal', 'default_price' => 20.00],
    ['name' => 'Dental Appliance Storage', 'default_price' => 10.00],
    ['name' => 'Dental Appliance Delivery', 'default_price' => 15.00],
    ['name' => 'Dental Appliance Pickup', 'default_price' => 15.00],
    ['name' => 'Dental Appliance Shipping', 'default_price' => 20.00],
    ['name' => 'Dental Appliance Insurance', 'default_price' => 30.00],
    ['name' => 'Dental Appliance Warranty', 'default_price' => 40.00],
    ['name' => 'Dental Appliance Registration', 'default_price' => 10.00],
    ['name' => 'Dental Appliance Documentation', 'default_price' => 5.00],
    ['name' => 'Dental Appliance Certification', 'default_price' => 15.00],
    ['name' => 'Dental Appliance Verification', 'default_price' => 10.00],
    ['name' => 'Dental Appliance Authentication', 'default_price' => 20.00],
    ['name' => 'Dental Appliance Validation', 'default_price' => 15.00],
    ['name' => 'Dental Appliance Testing', 'default_price' => 25.00],
    ['name' => 'Dental Appliance Inspection', 'default_price' => 20.00],
    ['name' => 'Dental Appliance Evaluation', 'default_price' => 30.00],
    ['name' => 'Dental Appliance Assessment', 'default_price' => 25.00],
    ['name' => 'Dental Appliance Analysis', 'default_price' => 35.00],
    ['name' => 'Dental Appliance Review', 'default_price' => 20.00],
    ['name' => 'Dental Appliance Audit', 'default_price' => 30.00],
    ['name' => 'Dental Appliance Inspection', 'default_price' => 25.00],
    ['name' => 'Dental Appliance Examination', 'default_price' => 20.00],
    ['name' => 'Dental Appliance Checkup', 'default_price' => 15.00],
    ['name' => 'Dental Appliance Maintenance', 'default_price' => 25.00],
    ['name' => 'Dental Appliance Servicing', 'default_price' => 30.00],
    ['name' => 'Dental Appliance Repair', 'default_price' => 40.00],
    ['name' => 'Dental Appliance Restoration', 'default_price' => 50.00],
    ['name' => 'Dental Appliance Refurbishment', 'default_price' => 60.00],
    ['name' => 'Dental Appliance Renewal', 'default_price' => 70.00],
    ['name' => 'Dental Appliance Replacement', 'default_price' => 80.00],
    ['name' => 'Dental Appliance Upgrade', 'default_price' => 90.00],
    ['name' => 'Dental Appliance Enhancement', 'default_price' => 100.00],
];

// Counter for successful and failed insertions
$success = 0;
$failed = 0;
$duplicates = 0;

// Check for existing services to avoid duplicates
$existingServices = [];
$stmt = $service->readAll();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existingServices[] = strtolower($row['name']);
}

// Add each service
echo "<h1>Adding Services</h1>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Name</th><th>Price</th><th>Status</th></tr>";

foreach ($services as $serviceData) {
    // Check if service already exists
    if (in_array(strtolower($serviceData['name']), $existingServices)) {
        echo "<tr><td>{$serviceData['name']}</td><td>{$serviceData['default_price']}</td><td>Skipped (Already exists)</td></tr>";
        $duplicates++;
        continue;
    }
    
    // Set service properties
    $service->name = $serviceData['name'];
    $service->description = $serviceData['name']; // Using name as description
    $service->default_price = $serviceData['default_price'];
    
    // Create the service
    if ($service->create()) {
        echo "<tr><td>{$serviceData['name']}</td><td>{$serviceData['default_price']}</td><td>Added successfully</td></tr>";
        $success++;
    } else {
        echo "<tr><td>{$serviceData['name']}</td><td>{$serviceData['default_price']}</td><td>Failed to add</td></tr>";
        $failed++;
    }
}

echo "</table>";
echo "<h2>Summary</h2>";
echo "<p>Total services processed: " . count($services) . "</p>";
echo "<p>Successfully added: $success</p>";
echo "<p>Failed to add: $failed</p>";
echo "<p>Duplicates skipped: $duplicates</p>";
echo "<p><a href='admin/manage_services.php' class='btn btn-primary'>Go to Services Management</a></p>";
?>
