<?php
session_start();
require_once 'config/database.php';
require_once 'includes/NotificationHelper.php';

// Check if user is logged in and is a planner
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'planner') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$notificationHelper = new NotificationHelper($database);

// Handle approval/rejection actions
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $notes = $_POST['notes'] ?? '';
    
    if($action == 'approve') {
        // Get the selected drone ID from the form
        $selected_drone_id = $_POST['selected_drone_id'] ?? null;
        
        if(!$selected_drone_id) {
            $error_message = "Please select a drone for assignment.";
        } else {
            // Verify the drone is available
            $drone_check_query = "SELECT id, name, drone_type, battery_level FROM drones WHERE id = ? AND status = 'available'";
            $drone_check_stmt = $db->prepare($drone_check_query);
            $drone_check_stmt->execute([$selected_drone_id]);
            $drone = $drone_check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if($drone) {
                try {
                    $db->beginTransaction();
                    
                    // Update drone request
                    try {
                        $update_query = "UPDATE drone_requests SET status = 'approved', approved_by = ?, approved_at = NOW(), drone_id = ?, notes = ? WHERE id = ?";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->execute([$_SESSION['user_id'], $drone['id'], $notes, $request_id]);
                    } catch (PDOException $e) {
                        // If approved_by, approved_at, or notes columns don't exist, update without them
                        $update_query = "UPDATE drone_requests SET status = 'approved', drone_id = ? WHERE id = ?";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->execute([$drone['id'], $request_id]);
                    }
                    
                    // Update drone status
                    $drone_update = "UPDATE drones SET status = 'assigned' WHERE id = ?";
                    $drone_stmt = $db->prepare($drone_update);
                    $drone_stmt->execute([$drone['id']]);
                    
                    // Award green points to planner
                    $points_query = "UPDATE users SET green_points = green_points + 5 WHERE id = ?";
                    $points_stmt = $db->prepare($points_query);
                    $points_stmt->execute([$_SESSION['user_id']]);
                    $_SESSION['green_points'] += 5;
                    
                    // Get drone request details for notification
                    $drone_request_query = "SELECT dr.purpose, dr.farmer_id FROM drone_requests dr WHERE dr.id = ?";
                    $drone_request_stmt = $db->prepare($drone_request_query);
                    $drone_request_stmt->execute([$request_id]);
                    $drone_request = $drone_request_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Log the action
                    $log_query = "INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->execute([$_SESSION['user_id'], 'drone_approved', "Drone request ID: $request_id approved, Drone ID: {$drone['id']} ({$drone['name']})"]);
                    
                    // Send notifications
                    $notificationHelper->notifyDroneRequestApproval($request_id, $drone_request['farmer_id'], $drone_request['purpose'], true);
                    
                    $db->commit();
                    $success_message = "Drone request approved successfully! Assigned drone: {$drone['name']} (ID: {$drone['id']}). +5 Green Points earned.";
                } catch (Exception $e) {
                    $db->rollback();
                    $error_message = "Error approving drone request: " . $e->getMessage();
                }
            } else {
                $error_message = "Selected drone is not available. Please choose another drone.";
            }
        }
    } elseif($action == 'reject') {
        // Get drone request details for notification
        $drone_request_query = "SELECT dr.purpose, dr.farmer_id FROM drone_requests dr WHERE dr.id = ?";
        $drone_request_stmt = $db->prepare($drone_request_query);
        $drone_request_stmt->execute([$request_id]);
        $drone_request = $drone_request_stmt->fetch(PDO::FETCH_ASSOC);
        
        try {
            $update_query = "UPDATE drone_requests SET status = 'rejected', approved_by = ?, approved_at = NOW(), notes = ? WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$_SESSION['user_id'], $notes, $request_id]);
        } catch (PDOException $e) {
            // If approved_by, approved_at, or notes columns don't exist, update without them
            $update_query = "UPDATE drone_requests SET status = 'rejected' WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$request_id]);
        }
        
        // Log the action
        $log_query = "INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([$_SESSION['user_id'], 'drone_rejected', "Drone request ID: $request_id rejected"]);
        
        // Send notifications
        $notificationHelper->notifyDroneRequestApproval($request_id, $drone_request['farmer_id'], $drone_request['purpose'], false);
        
        $success_message = "Drone request rejected successfully.";
    } elseif($action == 'complete') {
        // Get the drone request details with farm information
        $request_query = "SELECT dr.drone_id, dr.purpose, f.id as farm_id, f.name as farm_name, d.name as drone_name, d.drone_type 
                         FROM drone_requests dr 
                         JOIN farms f ON dr.farm_id = f.id 
                         LEFT JOIN drones d ON dr.drone_id = d.id 
                         WHERE dr.id = ?";
        $request_stmt = $db->prepare($request_query);
        $request_stmt->execute([$request_id]);
        $request = $request_stmt->fetch(PDO::FETCH_ASSOC);
        
        if($request && $request['drone_id']) {
            try {
                $db->beginTransaction();
                
                // Update drone request status to completed
                try {
                    $update_query = "UPDATE drone_requests SET status = 'completed', notes = ? WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([$notes, $request_id]);
                } catch (PDOException $e) {
                    // If notes column doesn't exist, update without notes
                    $update_query = "UPDATE drone_requests SET status = 'completed' WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([$request_id]);
                }
                
                // Update drone status back to available
                $drone_update = "UPDATE drones SET status = 'available' WHERE id = ?";
                $drone_stmt = $db->prepare($drone_update);
                $drone_stmt->execute([$request['drone_id']]);
                
                // Get sensor data for this farm to generate automatic results
                $sensor_data_query = "SELECT sr.*, id.device_type, id.device_name 
                                     FROM sensor_readings sr
                                     JOIN iot_devices id ON sr.device_id = id.id
                                     WHERE id.farm_id = ?
                                     ORDER BY sr.timestamp DESC 
                                     LIMIT 10";
                
                $sensor_data_stmt = $db->prepare($sensor_data_query);
                $sensor_data_stmt->execute([$request['farm_id']]);
                $sensor_data = $sensor_data_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Generate automatic results based on sensor data
                $auto_results = generateAutoResults($request, $sensor_data);
                
                // Insert auto-generated results
                $insert_query = "INSERT INTO drone_results (
                    drone_request_id, drone_id, operation_type, area_covered, duration_minutes,
                    efficiency_score, coverage_percentage, issues_encountered, recommendations, data_collected, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->execute([
                    $request_id,
                    $request['drone_id'],
                    $auto_results['operation_type'],
                    $auto_results['area_covered'],
                    $auto_results['duration_minutes'],
                    $auto_results['efficiency_score'],
                    $auto_results['coverage_percentage'],
                    $auto_results['issues_encountered'],
                    $auto_results['recommendations'],
                    $auto_results['data_collected'],
                    $_SESSION['user_id']
                ]);
                
                // Award green points to planner for completion and auto-generation
                $points_query = "UPDATE users SET green_points = green_points + 5 WHERE id = ?";
                $points_stmt = $db->prepare($points_query);
                $points_stmt->execute([$_SESSION['user_id']]);
                $_SESSION['green_points'] += 5;
                
                // Log the action
                $log_query = "INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->execute([$_SESSION['user_id'], 'drone_completed_with_auto_results', "Drone request ID: $request_id completed with auto-generated results based on sensor data"]);
                
                $db->commit();
                $success_message = "Drone request completed successfully! Auto-generated results based on sensor data. Drone returned to available pool. +5 Green Points earned.";
            } catch (Exception $e) {
                $db->rollback();
                $error_message = "Error completing drone request: " . $e->getMessage();
            }
        } else {
            $error_message = "Cannot complete request: No drone assigned to this request.";
        }
    } elseif($action == 'add_results') {
        $request_id = $_POST['request_id'];
        $operation_type = $_POST['operation_type'];
        $area_covered = $_POST['area_covered'];
        $duration_minutes = $_POST['duration_minutes'];
        $efficiency_score = $_POST['efficiency_score'];
        $coverage_percentage = $_POST['coverage_percentage'];
        $issues_encountered = $_POST['issues_encountered'];
        $recommendations = $_POST['recommendations'];
        $data_collected = $_POST['data_collected'];
        
        // Get drone request details
        $request_query = "SELECT drone_id FROM drone_requests WHERE id = ? AND status = 'completed'";
        $request_stmt = $db->prepare($request_query);
        $request_stmt->execute([$request_id]);
        $request = $request_stmt->fetch(PDO::FETCH_ASSOC);
        
        if($request && $request['drone_id']) {
            try {
                $db->beginTransaction();
                
                // Insert drone results
                $insert_query = "INSERT INTO drone_results (
                    drone_request_id, drone_id, operation_type, area_covered, duration_minutes,
                    efficiency_score, coverage_percentage, issues_encountered, recommendations, data_collected, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->execute([
                    $request_id, $request['drone_id'], $operation_type, $area_covered, $duration_minutes,
                    $efficiency_score, $coverage_percentage, $issues_encountered, $recommendations, $data_collected, $_SESSION['user_id']
                ]);
                
                // Award green points for adding results
                $points_query = "UPDATE users SET green_points = green_points + 2 WHERE id = ?";
                $points_stmt = $db->prepare($points_query);
                $points_stmt->execute([$_SESSION['user_id']]);
                $_SESSION['green_points'] += 2;
                
                // Log the action
                $log_query = "INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->execute([$_SESSION['user_id'], 'drone_results_added', "Added results for drone request ID: $request_id"]);
                
                $db->commit();
                $success_message = "Drone operation results added successfully! +2 Green Points earned.";
            } catch (Exception $e) {
                $db->rollback();
                $error_message = "Error adding results: " . $e->getMessage();
            }
        } else {
            $error_message = "Cannot add results: Request not found or not completed.";
        }
    }
}

// Function to generate automatic results based on sensor data
function generateAutoResults($request, $sensor_data) {
    $purpose = $request['purpose'];
    $farm_name = $request['farm_name'];
    
    // Analyze sensor data
    $avg_temperature = 0;
    $avg_humidity = 0;
    $avg_soil_moisture = 0;
    $avg_light = 0;
    $sensor_count = 0;
    
    foreach($sensor_data as $reading) {
        if($reading['device_type'] == 'temperature') {
            $avg_temperature += $reading['value'];
            $sensor_count++;
        } elseif($reading['device_type'] == 'humidity') {
            $avg_humidity += $reading['value'];
        } elseif($reading['device_type'] == 'soil_moisture') {
            $avg_soil_moisture += $reading['value'];
        } elseif($reading['device_type'] == 'light') {
            $avg_light += $reading['value'];
        }
    }
    
    if($sensor_count > 0) {
        $avg_temperature /= $sensor_count;
        $avg_humidity /= $sensor_count;
        $avg_soil_moisture /= $sensor_count;
        $avg_light /= $sensor_count;
    }
    
    // Generate results based on purpose and sensor data
    switch($purpose) {
        case 'pest_control_spraying':
            $operation_type = 'pest_control_spraying';
            $area_covered = rand(15, 35);
            $duration_minutes = rand(30, 60);
            
            // Efficiency based on weather conditions
            $efficiency_score = 0.85;
            if($avg_temperature > 25 && $avg_temperature < 35) $efficiency_score += 0.05;
            if($avg_humidity > 40 && $avg_humidity < 70) $efficiency_score += 0.05;
            if($avg_light > 500) $efficiency_score += 0.05;
            
            $coverage_percentage = min(100, $efficiency_score * 100 + rand(-5, 5));
            
            $issues_encountered = "Standard operation conditions";
            if($avg_temperature > 35) $issues_encountered = "High temperature affected spray effectiveness";
            if($avg_humidity > 80) $issues_encountered = "High humidity caused spray drift";
            
            $recommendations = "Optimal spraying conditions achieved";
            if($avg_temperature > 35) $recommendations = "Consider early morning spraying to avoid high temperatures";
            if($avg_humidity > 80) $recommendations = "Monitor humidity levels for future operations";
            
            $data_collected = "Spray coverage data, weather conditions (Temp: " . round($avg_temperature, 1) . "°C, Humidity: " . round($avg_humidity, 1) . "%), GPS coordinates";
            break;
            
        case 'crop_monitoring':
            $operation_type = 'crop_monitoring';
            $area_covered = rand(10, 25);
            $duration_minutes = rand(20, 45);
            
            $efficiency_score = 0.90;
            if($avg_light > 600) $efficiency_score += 0.05;
            if($avg_temperature > 20 && $avg_temperature < 30) $efficiency_score += 0.05;
            
            $coverage_percentage = min(100, $efficiency_score * 100 + rand(-3, 3));
            
            $issues_encountered = "Clear monitoring conditions";
            if($avg_light < 300) $issues_encountered = "Low light conditions affected image quality";
            
            $recommendations = "Excellent monitoring coverage achieved";
            if($avg_light < 300) $recommendations = "Schedule monitoring during brighter conditions";
            
            $data_collected = "NDVI data, crop health images, environmental conditions (Light: " . round($avg_light, 1) . " lux, Temp: " . round($avg_temperature, 1) . "°C)";
            break;
            
        case 'biological_control':
            $operation_type = 'biological_control';
            $area_covered = rand(8, 20);
            $duration_minutes = rand(25, 50);
            
            $efficiency_score = 0.88;
            if($avg_temperature > 22 && $avg_temperature < 28) $efficiency_score += 0.07;
            if($avg_humidity > 50 && $avg_humidity < 75) $efficiency_score += 0.05;
            
            $coverage_percentage = min(100, $efficiency_score * 100 + rand(-4, 4));
            
            $issues_encountered = "Optimal conditions for biological agents";
            if($avg_temperature < 20) $issues_encountered = "Low temperature may affect biological agent effectiveness";
            
            $recommendations = "Ideal conditions for biological control achieved";
            if($avg_temperature < 20) $recommendations = "Monitor temperature for optimal biological agent performance";
            
            $data_collected = "Biological agent distribution, environmental monitoring (Temp: " . round($avg_temperature, 1) . "°C, Humidity: " . round($avg_humidity, 1) . "%)";
            break;
            
        default:
            $operation_type = 'general_operation';
            $area_covered = rand(12, 30);
            $duration_minutes = rand(25, 55);
            $efficiency_score = 0.85;
            $coverage_percentage = 90;
            $issues_encountered = "Standard operation completed";
            $recommendations = "Operation completed successfully";
            $data_collected = "General operation data, environmental readings";
    }
    
    return [
        'operation_type' => $operation_type,
        'area_covered' => $area_covered,
        'duration_minutes' => $duration_minutes,
        'efficiency_score' => min(1.0, $efficiency_score),
        'coverage_percentage' => $coverage_percentage,
        'issues_encountered' => $issues_encountered,
        'recommendations' => $recommendations,
        'data_collected' => $data_collected
    ];
}

// Get all drone requests with results
// Check if drone_results table exists
$table_exists = false;
try {
    $check_table_query = "SHOW TABLES LIKE 'drone_results'";
    $check_table_stmt = $db->prepare($check_table_query);
    $check_table_stmt->execute();
    $table_exists = $check_table_stmt->rowCount() > 0;
} catch (Exception $e) {
    $table_exists = false;
}

// Get all drone requests with results
if ($table_exists) {
    $requests_query = "SELECT dr.*, f.name as farm_name, u.username as farmer_name, u.email as farmer_email, d.name as drone_name, d.drone_type as drone_type, d.battery_level,
                             (SELECT COUNT(*) FROM drone_results WHERE drone_request_id = dr.id) as has_results
                      FROM drone_requests dr 
                      JOIN farms f ON dr.farm_id = f.id 
                      JOIN users u ON dr.farmer_id = u.id 
                      LEFT JOIN drones d ON dr.drone_id = d.id
                      ORDER BY dr.created_at DESC";
} else {
    $requests_query = "SELECT dr.*, f.name as farm_name, u.username as farmer_name, u.email as farmer_email, d.name as drone_name, d.drone_type as drone_type, d.battery_level,
                             0 as has_results
                      FROM drone_requests dr 
                      JOIN farms f ON dr.farm_id = f.id 
                      JOIN users u ON dr.farmer_id = u.id 
                      LEFT JOIN drones d ON dr.drone_id = d.id
                      ORDER BY dr.created_at DESC";
}

$requests_stmt = $db->prepare($requests_query);
$requests_stmt->execute();
$requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available drones with detailed info
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

// Get available drones count by type for reference
$available_drones_count_query = "SELECT drone_type, COUNT(*) as count FROM drones WHERE status = 'available' GROUP BY drone_type";
$available_drones_count_stmt = $db->prepare($available_drones_count_query);
$available_drones_count_stmt->execute();
$available_drones_count = $available_drones_count_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a map for easy lookup
$drone_availability = [];
foreach($available_drones_count as $drone) {
    $drone_availability[$drone['drone_type']] = $drone['count'];
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <div class="sidebar">
            <h5><i class="fas fa-clipboard-check me-2"></i>Planner Dashboard</h5>
            <hr>
            <div class="mb-3">
                <strong>Welcome, <?php echo $_SESSION['username']; ?>!</strong>
                <div class="green-points mt-2">
                    <i class="fas fa-star me-1"></i><?php echo $_SESSION['green_points']; ?> Green Points
                </div>
            </div>
            
            <div class="list-group">
                <a href="planner_dashboard.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
                <a href="farm_approvals.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-check-circle me-2"></i>Farm Approvals
                </a>
                <a href="drone_approvals.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-drone me-2"></i>Drone Approvals
                </a>
                <a href="drone_inventory.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-boxes me-2"></i>Drone Inventory
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-drone me-2"></i>Drone Approvals</h2>
                <div>
                    <a href="planner_dashboard.php" class="btn btn-blue-color">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
            
            <?php if(isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if(isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!$table_exists): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Drone Results System:</strong> The drone results table has not been created yet. 
                    <a href="test_drone_results_table.php" class="btn btn-sm btn-warning ms-2">
                        <i class="fas fa-database me-1"></i>Setup Results System
                    </a>
                </div>
            <?php endif; ?>
            
            <!-- Summary Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-clock fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo count(array_filter($requests, function($r) { return $r['status'] == 'pending'; })); ?></h4>
                            <p class="mb-0 text-color">Pending Requests</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle fa-2x mb-2  text-color"></i>
                            <h4 class="text-color"><?php echo count(array_filter($requests, function($r) { return $r['status'] == 'approved'; })); ?></h4>
                            <p class="mb-0 text-color">Approved Requests</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-drone fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo array_sum($drone_availability); ?></h4>
                            <p class="mb-0 text-color">Available Drones</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-star fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo $_SESSION['green_points']; ?></h4>
                            <p class="mb-0 text-color">Your Green Points</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Available Drones Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle me-2"></i>Available Drones for Assignment</h5>
                </div>
                <div class="card-body">
                    <?php if(empty($available_drones)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                            <p class="text-muted">No drones available for assignment.</p>
                            <p class="text-muted">All drones are currently assigned or in maintenance.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($drones_by_type as $drone_type => $drones): ?>
                            <div class="mb-4">
                                <h6 class="text-primary">
                                    <i class="fas fa-<?php 
                                        echo $drone_type == 'survey' ? 'eye' : 
                                            ($drone_type == 'spraying' ? 'spray-can' : 
                                            ($drone_type == 'monitoring' ? 'video' : 'leaf')); 
                                    ?> me-2"></i>
                                    <?php echo ucfirst($drone_type); ?> Drones (<?php echo count($drones); ?>)
                                </h6>
                                <div class="row">
                                    <?php foreach($drones as $drone): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card border-primary h-100">
                                                <div class="card-body">
                                                    <h6 class="card-title"><?php echo htmlspecialchars($drone['name']); ?></h6>
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <small class="text-muted">Battery:</small><br>
                                                            <span class="badge bg-<?php echo $drone['battery_level'] > 50 ? 'success' : ($drone['battery_level'] > 20 ? 'warning' : 'danger'); ?>">
                                                                <?php echo $drone['battery_level']; ?>%
                                                            </span>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="text-muted">Type:</small><br>
                                                            <span class="badge bg-info"><?php echo ucfirst($drone['drone_type']); ?></span>
                                                        </div>
                                                    </div>
                                                    <?php if($drone['last_maintenance']): ?>
                                                        <div class="mt-2">
                                                            <small class="text-muted">Last Maintenance:</small><br>
                                                            <small><?php echo date('M j, Y', strtotime($drone['last_maintenance'])); ?></small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Drone Requests Table -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-drone me-2"></i>All Drone Requests</h5>
                </div>
                <div class="card-body">
                    <?php if(empty($requests)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-drone fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No drone requests found.</p>
                            <p class="text-muted">Farmers need to create drone requests first.</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        $pending_count = 0;
                        foreach($requests as $request) {
                            if($request['status'] == 'pending') $pending_count++;
                        }
                        ?>
                        <?php if($pending_count == 0): ?>
                            <div class="alert alert-info mb-4">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>No pending requests to review.</strong> All current drone requests have been processed.
                                <br>
                                <small class="text-muted">New drone requests created by farmers will appear here for approval.</small>
                            </div>
                        <?php endif; ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Farm</th>
                                    <th>Farmer</th>
                                    <th>Purpose</th>
                                    <th>Location</th>
                                    <th>Preferred Time</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($requests as $request): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($request['farm_name']); ?></strong>
                                            <?php if($request['drone_name']): ?>
                                                <br>
                                                <small class="text-success">
                                                    <i class="fas fa-drone me-1"></i>Assigned: <?php echo htmlspecialchars($request['drone_name']); ?>
                                                    <?php if($request['battery_level']): ?>
                                                        <br><span class="badge bg-<?php echo $request['battery_level'] > 50 ? 'success' : ($request['battery_level'] > 20 ? 'warning' : 'danger'); ?>">
                                                            <?php echo $request['battery_level']; ?>% battery
                                                        </span>
                                                    <?php endif; ?>
                                                </small>
                                            <?php else: ?>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>Awaiting assignment
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($request['farmer_name']); ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($request['farmer_email']); ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                            $purpose_type = str_replace('pest_control_', '', $request['purpose']);
                                            $drone_type = $purpose_type == 'spraying' ? 'spraying' : ($purpose_type == 'monitoring' ? 'monitoring' : ($purpose_type == 'biological' ? 'biological' : 'survey'));
                                            $available_count = $drone_availability[$drone_type] ?? 0;
                                            ?>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst(str_replace('_', ' ', $request['purpose'])); ?>
                                            </span>
                                            <?php if($request['status'] == 'pending'): ?>
                                                <br>
                                                <?php if($available_count > 0): ?>
                                                    <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#droneSelectionModal" 
                                                            data-request-id="<?php echo $request['id']; ?>" data-farm-name="<?php echo htmlspecialchars($request['farm_name']); ?>" 
                                                            data-drone-type="<?php echo $drone_type; ?>" data-purpose="<?php echo $request['purpose']; ?>">
                                                        <i class="fas fa-drone me-1"></i><?php echo $available_count; ?> drone<?php echo $available_count != 1 ? 's' : ''; ?> available
                                                    </button>
                                                <?php else: ?>
                                                    <small class="text-danger">
                                                        <i class="fas fa-times me-1"></i>No drones available
                                                    </small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['location']); ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($request['preferred_time'])); ?></td>
                                        <td>
                                            <?php if($request['status'] == 'pending'): ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php elseif($request['status'] == 'approved'): ?>
                                                <span class="badge bg-success">Approved</span>
                                            <?php elseif($request['status'] == 'rejected'): ?>
                                                <span class="badge bg-danger">Rejected</span>
                                            <?php elseif($request['status'] == 'en_route'): ?>
                                                <span class="badge bg-primary">En Route</span>
                                            <?php elseif($request['status'] == 'active'): ?>
                                                <span class="badge bg-info">Active</span>
                                            <?php elseif($request['status'] == 'completed'): ?>
                                                <span class="badge bg-secondary">Completed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                                        <td>
                                            <?php if($request['status'] == 'pending'): ?>
                                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#approvalModal" 
                                                        data-request-id="<?php echo $request['id']; ?>" data-farm-name="<?php echo htmlspecialchars($request['farm_name']); ?>" data-action="reject">
                                                    <i class="fas fa-times me-1"></i>Reject
                                                </button>
                                            <?php elseif($request['status'] == 'approved' || $request['status'] == 'active'): ?>
                                                <button class="btn btn-sm btn-blue-color" data-bs-toggle="modal" data-bs-target="#approvalModal" 
                                                        data-request-id="<?php echo $request['id']; ?>" data-farm-name="<?php echo htmlspecialchars($request['farm_name']); ?>" data-action="complete">
                                                    <i class="fas fa-robot me-1"></i>Complete & Auto-Generate
                                                </button>
                                            <?php elseif($request['status'] == 'completed'): ?>
                                                <?php if($request['has_results'] > 0): ?>
                                                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#resultsModal" 
                                                            data-request-id="<?php echo $request['id']; ?>" data-farm-name="<?php echo htmlspecialchars($request['farm_name']); ?>">
                                                        <i class="fas fa-chart-line me-1"></i>View Results
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#addResultsModal" 
                                                            data-request-id="<?php echo $request['id']; ?>" data-farm-name="<?php echo htmlspecialchars($request['farm_name']); ?>">
                                                        <i class="fas fa-plus me-1"></i>Add Results
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <small class="text-muted"><?php echo ucfirst($request['status']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="request_id" id="modalRequestId">
                    <input type="hidden" name="action" id="modalAction">
                    
                    <p>Are you sure you want to <span id="actionText"></span> the drone request for <strong id="farmName"></strong>?</p>
                    
                    <!-- Completion Info -->
                    <div id="completionInfo" style="display: none;">
                        <div class="alert alert-info">
                            <i class="fas fa-robot me-2"></i>
                            <strong>Mark as Completed:</strong> This will return the assigned drone to the available pool, mark the request as completed, and <strong>automatically generate results based on sensor data</strong>.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Add any notes about this decision..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="confirmBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Drone Results Modal -->
<div class="modal fade" id="resultsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Drone Operation Results</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="resultsModalBody">
                <!-- Results will be loaded here via AJAX -->
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading results...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-blue-color" onclick="exportResults()">
                    <i class="fas fa-download me-1"></i>Export Results
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Results Modal -->
<div class="modal fade" id="addResultsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="addResultsForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add Drone Operation Results</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_results">
                    <input type="hidden" name="request_id" id="addResultsRequestId">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="operation_type" class="form-label">Operation Type</label>
                                <select class="form-select" id="operation_type" name="operation_type" required>
                                    <option value="">Select operation type...</option>
                                    <option value="pest_control_spraying">Pest Control Spraying</option>
                                    <option value="crop_monitoring">Crop Monitoring</option>
                                    <option value="biological_control">Biological Control</option>
                                    <option value="survey_mapping">Survey & Mapping</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="area_covered" class="form-label">Area Covered (acres)</label>
                                <input type="number" class="form-control" id="area_covered" name="area_covered" step="0.1" min="0" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="duration_minutes" class="form-label">Duration (minutes)</label>
                                <input type="number" class="form-control" id="duration_minutes" name="duration_minutes" min="1" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="efficiency_score" class="form-label">Efficiency Score (0-1)</label>
                                <input type="number" class="form-control" id="efficiency_score" name="efficiency_score" step="0.01" min="0" max="1" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="coverage_percentage" class="form-label">Coverage Percentage (%)</label>
                                <input type="number" class="form-control" id="coverage_percentage" name="coverage_percentage" step="0.1" min="0" max="100" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="issues_encountered" class="form-label">Issues Encountered</label>
                        <textarea class="form-control" id="issues_encountered" name="issues_encountered" rows="2" placeholder="Describe any issues encountered during the operation..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="recommendations" class="form-label">Recommendations</label>
                        <textarea class="form-control" id="recommendations" name="recommendations" rows="2" placeholder="Provide recommendations for future operations..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="data_collected" class="form-label">Data Collected</label>
                        <textarea class="form-control" id="data_collected" name="data_collected" rows="2" placeholder="Describe the data collected during the operation..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-blue-color">
                        <i class="fas fa-save me-1"></i>Save Results
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Test Modal -->
<div class="modal fade" id="testModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Test Modal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>If you can see this, Bootstrap modals are working!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Drone Selection Modal -->
<div class="modal fade" id="droneSelectionModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Available Drone for Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6><i class="fas fa-info-circle me-2"></i>Request Details</h6>
                        <p><strong>Farm:</strong> <span id="modalFarmName"></span></p>
                        <p><strong>Purpose:</strong> <span id="modalPurpose"></span></p>
                        <p><strong>Request ID:</strong> <span id="modalRequestId"></span></p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-drone me-2"></i>Available Drones</h6>
                        <p><strong>Type Required:</strong> <span id="modalDroneType"></span></p>
                        <p><strong>Count Available:</strong> <span id="modalDroneCount"></span></p>
                    </div>
                </div>
                
                <div id="availableDronesList">
                    <!-- Available drones will be populated here -->
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Instructions:</strong> Click on a drone card to select it, then click "Approve with Selected Drone" to complete the assignment.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="approveWithSelectedDrone" disabled>
                    <i class="fas fa-check me-1"></i>Approve with Selected Drone
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded - checking Bootstrap availability');
    console.log('Bootstrap available:', typeof bootstrap !== 'undefined');
    
    // Handle approval modal (for reject and complete actions)
    const approvalModal = document.getElementById('approvalModal');
    if (approvalModal) {
        approvalModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const requestId = button.getAttribute('data-request-id');
            const farmName = button.getAttribute('data-farm-name');
            const action = button.getAttribute('data-action');
            
            document.getElementById('modalRequestId').value = requestId;
            document.getElementById('modalAction').value = action;
            document.getElementById('farmName').textContent = farmName;
            document.getElementById('actionText').textContent = action === 'complete' ? 'complete and auto-generate results for' : 'reject';
            
            const confirmBtn = document.getElementById('confirmBtn');
            const completionInfo = document.getElementById('completionInfo');
            
            // Hide all info sections first
            completionInfo.style.display = 'none';
            
            if (action === 'complete') {
                confirmBtn.className = 'btn btn-blue-color';
                confirmBtn.innerHTML = '<i class="fas fa-robot me-1"></i>Complete & Auto-Generate';
                completionInfo.style.display = 'block';
            } else {
                confirmBtn.className = 'btn btn-danger';
                confirmBtn.innerHTML = '<i class="fas fa-times me-1"></i>Reject';
            }
        });
    }
    
    // Handle drone selection modal
    const droneSelectionModal = document.getElementById('droneSelectionModal');
    console.log('Drone selection modal element:', droneSelectionModal);
    
    if (droneSelectionModal) {
        droneSelectionModal.addEventListener('show.bs.modal', function (event) {
            console.log('Modal show event triggered');
            console.log('Event:', event);
            console.log('Related target:', event.relatedTarget);
            const button = event.relatedTarget;
            console.log('Button element:', button);
            console.log('Button attributes:', button.attributes);
            
            const requestId = button.getAttribute('data-request-id');
            const farmName = button.getAttribute('data-farm-name');
            const droneType = button.getAttribute('data-drone-type');
            const purpose = button.getAttribute('data-purpose');
            
            console.log('Request ID:', requestId);
            console.log('Farm Name:', farmName);
            console.log('Drone Type:', droneType);
            console.log('Purpose:', purpose);
            
            // Populate modal header information
            document.getElementById('modalFarmName').textContent = farmName;
            document.getElementById('modalRequestId').textContent = requestId;
            document.getElementById('modalPurpose').textContent = purpose.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            document.getElementById('modalDroneType').textContent = droneType.charAt(0).toUpperCase() + droneType.slice(1);
            
            // Get available drones for this type
            const availableDrones = <?php echo json_encode($drones_by_type); ?>;
            const dronesForType = availableDrones[droneType] || [];
            
            document.getElementById('modalDroneCount').textContent = dronesForType.length;
            
            // Populate available drones list
            const dronesListContainer = document.getElementById('availableDronesList');
            dronesListContainer.innerHTML = '';
            
            if (dronesForType.length === 0) {
                dronesListContainer.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        No available drones of type "${droneType}" found.
                    </div>
                `;
                document.getElementById('approveWithSelectedDrone').disabled = true;
                return;
            }
            
            const dronesRow = document.createElement('div');
            dronesRow.className = 'row';
            
            dronesForType.forEach(drone => {
                const droneCol = document.createElement('div');
                droneCol.className = 'col-md-6 col-lg-4 mb-3';
                
                const batteryColor = drone.battery_level > 50 ? 'success' : (drone.battery_level > 20 ? 'warning' : 'danger');
                const maintenanceDate = drone.last_maintenance ? new Date(drone.last_maintenance).toLocaleDateString() : 'Not recorded';
                
                droneCol.innerHTML = `
                    <div class="card drone-card h-100" data-drone-id="${drone.id}" style="cursor: pointer; border: 2px solid #dee2e6;">
                        <div class="card-body">
                            <h6 class="card-title">${drone.name}</h6>
                            <div class="row mb-2">
                                <div class="col-6">
                                    <small class="text-muted">Type:</small><br>
                                    <span class="badge bg-info">${drone.drone_type.charAt(0).toUpperCase() + drone.drone_type.slice(1)}</span>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Battery:</small><br>
                                    <span class="badge bg-${batteryColor}">${drone.battery_level}%</span>
                                </div>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Last Maintenance:</small><br>
                                <small>${maintenanceDate}</small>
                            </div>
                            <div class="text-center">
                                <small class="text-muted">Click to select this drone</small>
                            </div>
                        </div>
                    </div>
                `;
                
                dronesRow.appendChild(droneCol);
            });
            
            dronesListContainer.appendChild(dronesRow);
            
            // Handle drone card selection
            let selectedDroneId = null;
            const droneCards = dronesListContainer.querySelectorAll('.drone-card');
            
            // Store the current request ID for the approve button
            const approveBtn = document.getElementById('approveWithSelectedDrone');
            approveBtn.setAttribute('data-current-request-id', requestId);
            approveBtn.setAttribute('data-current-selected-drone', '');
            
            // Handle approve with selected drone (using event delegation)
            approveBtn.onclick = function() {
                console.log('Approve button clicked');
                const currentRequestId = this.getAttribute('data-current-request-id');
                const currentSelectedDrone = this.getAttribute('data-current-selected-drone');
                
                console.log('Current request ID:', currentRequestId);
                console.log('Current selected drone:', currentSelectedDrone);
                
                if (currentSelectedDrone) {
                    // Create a form and submit it
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';
                    
                    const requestIdInput = document.createElement('input');
                    requestIdInput.type = 'hidden';
                    requestIdInput.name = 'request_id';
                    requestIdInput.value = currentRequestId;
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'approve';
                    
                    const droneIdInput = document.createElement('input');
                    droneIdInput.type = 'hidden';
                    droneIdInput.name = 'selected_drone_id';
                    droneIdInput.value = currentSelectedDrone;
                    
                    const notesInput = document.createElement('input');
                    notesInput.type = 'hidden';
                    notesInput.name = 'notes';
                    notesInput.value = 'Approved via drone selection modal';
                    
                    form.appendChild(requestIdInput);
                    form.appendChild(actionInput);
                    form.appendChild(droneIdInput);
                    form.appendChild(notesInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            };
            
            // Update the drone card click handlers to also update the approve button
            droneCards.forEach(card => {
                card.addEventListener('click', function() {
                    console.log('Drone card clicked:', this.getAttribute('data-drone-id'));
                    
                    // Remove selection from all cards
                    droneCards.forEach(c => {
                        c.style.border = '2px solid #dee2e6';
                        c.style.backgroundColor = '';
                    });
                    
                    // Select this card
                    this.style.border = '2px solid #28a745';
                    this.style.backgroundColor = '#f8fff9';
                    selectedDroneId = this.getAttribute('data-drone-id');
                    
                    // Update approve button
                    approveBtn.setAttribute('data-current-selected-drone', selectedDroneId);
                    approveBtn.disabled = false;
                    
                    console.log('Selected drone ID:', selectedDroneId);
                    console.log('Approve button disabled:', approveBtn.disabled);
                });
            });
        });
    }
    
    // Handle results modal
    const resultsModal = document.getElementById('resultsModal');
    if (resultsModal) {
        resultsModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const requestId = button.getAttribute('data-request-id');
            const farmName = button.getAttribute('data-farm-name');
            
            // Update modal title
            this.querySelector('.modal-title').textContent = `Drone Operation Results - ${farmName}`;
            
            // Load results via AJAX
            loadDroneResults(requestId);
        });
    }
    
    // Handle add results modal
    const addResultsModal = document.getElementById('addResultsModal');
    if (addResultsModal) {
        addResultsModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const requestId = button.getAttribute('data-request-id');
            const farmName = button.getAttribute('data-farm-name');
            
            // Update modal title and set request ID
            this.querySelector('.modal-title').textContent = `Add Drone Results - ${farmName}`;
            document.getElementById('addResultsRequestId').value = requestId;
        });
    }
});

// Load drone results via AJAX
function loadDroneResults(requestId) {
    const modalBody = document.getElementById('resultsModalBody');
    
    // Show loading spinner
    modalBody.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading results...</p>
        </div>
    `;
    
    // Make AJAX request
    fetch(`get_drone_results.php?request_id=${requestId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayDroneResults(data.results, data.request);
            } else {
                modalBody.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading results: ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error loading results: ${error.message}
                </div>
            `;
        });
}

// Display drone results
function displayDroneResults(results, request) {
    const modalBody = document.getElementById('resultsModalBody');
    
    modalBody.innerHTML = `
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Operation Summary</h6>
                    <p class="mb-1"><strong>Farm:</strong> ${request.farm_name}</p>
                    <p class="mb-1"><strong>Purpose:</strong> ${request.purpose}</p>
                    <p class="mb-1"><strong>Drone:</strong> ${request.drone_name}</p>
                    <p class="mb-0"><strong>Completed:</strong> ${request.completed_date}</p>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-chart-bar me-2"></i>Performance Metrics</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="metric-item">
                                    <div class="metric-value text-primary">${results.area_covered}</div>
                                    <div class="metric-label">Acres Covered</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-item">
                                    <div class="metric-value text-success">${results.duration_minutes}</div>
                                    <div class="metric-label">Minutes</div>
                                </div>
                            </div>
                        </div>
                        <div class="row text-center mt-3">
                            <div class="col-6">
                                <div class="metric-item">
                                    <div class="metric-value text-warning">${(results.efficiency_score * 100).toFixed(1)}%</div>
                                    <div class="metric-label">Efficiency</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-item">
                                    <div class="metric-value text-info">${results.coverage_percentage}%</div>
                                    <div class="metric-label">Coverage</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-clipboard-list me-2"></i>Operation Details</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Operation Type:</strong> ${results.operation_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</p>
                        <p><strong>Issues Encountered:</strong> ${results.issues_encountered || 'None reported'}</p>
                        <p><strong>Recommendations:</strong> ${results.recommendations || 'No specific recommendations'}</p>
                        <p><strong>Data Collected:</strong> ${results.data_collected || 'Standard operation data'}</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-chart-line me-2"></i>Performance Analysis</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="progress mb-2" style="height: 25px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: ${results.coverage_percentage}%">
                                        ${results.coverage_percentage}% Coverage
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="progress mb-2" style="height: 25px;">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: ${results.efficiency_score * 100}%">
                                        ${(results.efficiency_score * 100).toFixed(1)}% Efficiency
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="progress mb-2" style="height: 25px;">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: ${(results.area_covered / 50) * 100}%">
                                        ${results.area_covered} Acres
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Export results function
function exportResults() {
    // Implementation for exporting results
    alert('Export functionality will be implemented here.');
}
</script>

<style>
.metric-item {
    padding: 10px;
    border-radius: 8px;
    background: #f8f9fa;
}

.metric-value {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.metric-label {
    font-size: 0.8rem;
    color: #6c757d;
    text-transform: uppercase;
    font-weight: 600;
}
</style>


