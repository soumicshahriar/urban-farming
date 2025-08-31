<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is a planner
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'planner') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle automatic result generation
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if($action == 'generate_auto_results') {
        try {
            $db->beginTransaction();
            
            // Get completed drone requests without results
            $completed_requests_query = "SELECT dr.*, f.name as farm_name, d.name as drone_name, d.drone_type
                                       FROM drone_requests dr 
                                       JOIN farms f ON dr.farm_id = f.id 
                                       LEFT JOIN drones d ON dr.drone_id = d.id
                                       WHERE dr.status = 'completed' 
                                       AND dr.id NOT IN (SELECT drone_request_id FROM drone_results)
                                       LIMIT 5";
            
            $completed_requests_stmt = $db->prepare($completed_requests_query);
            $completed_requests_stmt->execute();
            $completed_requests = $completed_requests_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results_generated = 0;
            
            foreach($completed_requests as $request) {
                // Get sensor data for this farm
                $sensor_data_query = "SELECT sr.*, id.device_type, id.device_name 
                                     FROM sensor_readings sr
                                     JOIN iot_devices id ON sr.device_id = id.id
                                     WHERE id.farm_id = (SELECT farm_id FROM drone_requests WHERE id = ?)
                                     ORDER BY sr.timestamp DESC 
                                     LIMIT 10";
                
                $sensor_data_stmt = $db->prepare($sensor_data_query);
                $sensor_data_stmt->execute([$request['id']]);
                $sensor_data = $sensor_data_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Generate results based on sensor data and drone purpose
                $auto_results = generateAutoResults($request, $sensor_data);
                
                // Insert auto-generated results
                $insert_query = "INSERT INTO drone_results (
                    drone_request_id, drone_id, operation_type, area_covered, duration_minutes,
                    efficiency_score, coverage_percentage, issues_encountered, recommendations, data_collected, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->execute([
                    $request['id'],
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
                
                $results_generated++;
            }
            
            // Award green points for auto-generation
            if($results_generated > 0) {
                $points_awarded = $results_generated * 3;
                $points_query = "UPDATE users SET green_points = green_points + ? WHERE id = ?";
                $points_stmt = $db->prepare($points_query);
                $points_stmt->execute([$points_awarded, $_SESSION['user_id']]);
                $_SESSION['green_points'] += $points_awarded;
                
                // Log the action
                $log_query = "INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->execute([$_SESSION['user_id'], 'auto_results_generated', "Generated $results_generated auto drone results"]);
            }
            
            $db->commit();
            $success_message = "Successfully generated $results_generated automatic drone results based on sensor data! +" . ($results_generated * 3) . " Green Points earned.";
            
        } catch (Exception $e) {
            $db->rollback();
            $error_message = "Error generating automatic results: " . $e->getMessage();
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

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_requests,
    SUM(CASE WHEN status = 'completed' AND id IN (SELECT drone_request_id FROM drone_results) THEN 1 ELSE 0 END) as with_results,
    SUM(CASE WHEN status = 'completed' AND id NOT IN (SELECT drone_request_id FROM drone_results) THEN 1 ELSE 0 END) as without_results
FROM drone_requests";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

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
                <a href="drone_approvals.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-drone me-2"></i>Drone Approvals
                </a>
                <a href="auto_drone_results.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-robot me-2"></i>Auto Results
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
                <h2><i class="fas fa-robot me-2"></i>Automatic Drone Results Generator</h2>
                <a href="planner_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
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
            
            <!-- Summary Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-drone fa-2x mb-2 text-primary"></i>
                            <h4><?php echo $stats['total_requests']; ?></h4>
                            <p class="mb-0">Total Requests</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                            <h4><?php echo $stats['completed_requests']; ?></h4>
                            <p class="mb-0">Completed</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-bar fa-2x mb-2 text-info"></i>
                            <h4><?php echo $stats['with_results']; ?></h4>
                            <p class="mb-0">With Results</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-robot fa-2x mb-2 text-warning"></i>
                            <h4><?php echo $stats['without_results']; ?></h4>
                            <p class="mb-0">Need Results</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Auto Generation Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-robot me-2"></i>Automatic Results Generation</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6><i class="fas fa-info-circle me-2"></i>How It Works</h6>
                            <p class="text-muted">
                                The automatic results generator analyzes sensor data from IoT devices on farms to create 
                                realistic drone operation results. It considers:
                            </p>
                            <ul class="text-muted">
                                <li><strong>Temperature sensors:</strong> Affects spraying efficiency and biological agent effectiveness</li>
                                <li><strong>Humidity sensors:</strong> Impacts spray drift and monitoring conditions</li>
                                <li><strong>Soil moisture sensors:</strong> Influences crop monitoring accuracy</li>
                                <li><strong>Light sensors:</strong> Affects image quality and monitoring precision</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <form method="POST" onsubmit="return confirm('Generate automatic results for up to 5 completed drone requests?')">
                                    <input type="hidden" name="action" value="generate_auto_results">
                                    <button type="submit" class="btn btn-blue-color btn-lg">
                                        <i class="fas fa-robot me-2"></i>Generate Auto Results
                                    </button>
                                </form>
                                <small class="text-muted mt-2 d-block">
                                    +3 Green Points per result generated
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Auto-Generated Results -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history me-2"></i>Recent Auto-Generated Results</h5>
                </div>
                <div class="card-body">
                    <?php
                    $recent_results_query = "SELECT dr.*, f.name as farm_name, d.name as drone_name, 
                                                  DATE_FORMAT(dr.created_at, '%M %d, %Y %h:%i %p') as generated_date
                                           FROM drone_results dr
                                           JOIN drone_requests drq ON dr.drone_request_id = drq.id
                                           JOIN farms f ON drq.farm_id = f.id
                                           LEFT JOIN drones d ON dr.drone_id = d.id
                                           WHERE dr.created_by = ?
                                           ORDER BY dr.created_at DESC
                                           LIMIT 10";
                    
                    $recent_results_stmt = $db->prepare($recent_results_query);
                    $recent_results_stmt->execute([$_SESSION['user_id']]);
                    $recent_results = $recent_results_stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <?php if(empty($recent_results)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-robot fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No auto-generated results yet.</p>
                            <p class="text-muted">Click "Generate Auto Results" to create results based on sensor data.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Farm</th>
                                        <th>Operation Type</th>
                                        <th>Performance</th>
                                        <th>Generated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recent_results as $result): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($result['farm_name']); ?></strong>
                                                <?php if($result['drone_name']): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-drone me-1"></i><?php echo htmlspecialchars($result['drone_name']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo ucfirst(str_replace('_', ' ', $result['operation_type'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="row">
                                                    <div class="col-6">
                                                        <small class="text-muted">Efficiency:</small><br>
                                                        <span class="badge bg-<?php echo $result['efficiency_score'] > 0.8 ? 'success' : ($result['efficiency_score'] > 0.6 ? 'warning' : 'danger'); ?>">
                                                            <?php echo round($result['efficiency_score'] * 100, 1); ?>%
                                                        </span>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted">Coverage:</small><br>
                                                        <span class="badge bg-<?php echo $result['coverage_percentage'] > 90 ? 'success' : ($result['coverage_percentage'] > 75 ? 'warning' : 'danger'); ?>">
                                                            <?php echo $result['coverage_percentage']; ?>%
                                                        </span>
                                                    </div>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo $result['area_covered']; ?> acres, <?php echo $result['duration_minutes']; ?> min
                                                </small>
                                            </td>
                                            <td><?php echo $result['generated_date']; ?></td>
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

<?php include 'includes/footer.php'; ?>
