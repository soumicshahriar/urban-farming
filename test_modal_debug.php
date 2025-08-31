<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Modal Debug Test</h2>";

// Test 1: Check if drones_by_type data is properly formatted
echo "<h3>1. Drones by Type Data Structure</h3>";
$available_drones_query = "SELECT id, name, drone_type, battery_level, last_maintenance, created_at 
                          FROM drones 
                          WHERE status = 'available' 
                          ORDER BY drone_type, name";
$available_drones_stmt = $db->prepare($available_drones_query);
$available_drones_stmt->execute();
$available_drones = $available_drones_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group available drones by type
$drones_by_type = [];
foreach($available_drones as $drone) {
    $drones_by_type[$drone['drone_type']][] = $drone;
}

echo "<p><strong>Available Drones by Type:</strong></p>";
echo "<pre>" . json_encode($drones_by_type, JSON_PRETTY_PRINT) . "</pre>";

// Test 2: Check if there are any pending requests
echo "<h3>2. Pending Drone Requests</h3>";
$requests_query = "SELECT dr.*, f.name as farm_name, u.username as farmer_name 
                  FROM drone_requests dr 
                  JOIN farms f ON dr.farm_id = f.id 
                  JOIN users u ON dr.farmer_id = u.id 
                  WHERE dr.status = 'pending'
                  ORDER BY dr.created_at DESC";
$requests_stmt = $db->prepare($requests_query);
$requests_stmt->execute();
$pending_requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);

if(empty($pending_requests)) {
    echo "<p style='color: orange;'>⚠️ No pending drone requests found.</p>";
    echo "<p>To test the modal, you need to create a drone request first.</p>";
} else {
    echo "<p style='color: green;'>✅ Found " . count($pending_requests) . " pending request(s):</p>";
    echo "<ul>";
    foreach($pending_requests as $request) {
        $purpose_type = str_replace('pest_control_', '', $request['purpose']);
        $drone_type = $purpose_type == 'spraying' ? 'spraying' : ($purpose_type == 'monitoring' ? 'monitoring' : ($purpose_type == 'biological' ? 'biological' : 'survey'));
        
        $available_count = isset($drones_by_type[$drone_type]) ? count($drones_by_type[$drone_type]) : 0;
        
        echo "<li><strong>Request #{$request['id']}:</strong> {$request['farm_name']} - {$request['purpose']} - <span style='color: " . ($available_count > 0 ? 'green' : 'red') . ";'>{$available_count} drone(s) available</span></li>";
    }
    echo "</ul>";
}

// Test 3: JavaScript test
echo "<h3>3. JavaScript Modal Test</h3>";
echo "<p>Click the button below to test if the modal opens:</p>";
echo "<button class='btn btn-blue-color' data-bs-toggle='modal' data-bs-target='#testModal'>Test Modal</button>";

echo "<h3>4. Debug Information</h3>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
echo "<p><strong>To debug the modal issue:</strong></p>";
echo "<ol>";
echo "<li>Check browser console for JavaScript errors</li>";
echo "<li>Verify that Bootstrap JS is loaded</li>";
echo "<li>Check if the modal HTML is properly generated</li>";
echo "<li>Verify that the data attributes are correctly set</li>";
echo "<li>Test if the modal trigger button has the correct data-bs-target</li>";
echo "</ol>";
echo "</div>";

// Test modal
echo "
<!-- Test Modal -->
<div class='modal fade' id='testModal' tabindex='-1'>
    <div class='modal-dialog'>
        <div class='modal-content'>
            <div class='modal-header'>
                <h5 class='modal-title'>Test Modal</h5>
                <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
            </div>
            <div class='modal-body'>
                <p>If you can see this, the modal system is working!</p>
            </div>
            <div class='modal-footer'>
                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Close</button>
            </div>
        </div>
    </div>
</div>
";

// Include Bootstrap CSS and JS for testing
echo "
<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js'></script>
";
?>
