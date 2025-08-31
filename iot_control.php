<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is a farmer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'farmer') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$farmer_id = $_SESSION['user_id'];

// Handle device control actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $device_id = $_POST['device_id'] ?? null;
    
    if ($action == 'toggle_device' && $device_id) {
        // Get current device status
        $status_query = "SELECT status FROM iot_devices WHERE id = ? AND farm_id IN (SELECT id FROM farms WHERE farmer_id = ?)";
        $status_stmt = $db->prepare($status_query);
        $status_stmt->execute([$device_id, $farmer_id]);
        $current_status = $status_stmt->fetchColumn();
        
        if ($current_status) {
            $new_status = $current_status == 'active' ? 'inactive' : 'active';
            
            // Update device status
            $update_query = "UPDATE iot_devices SET status = ? WHERE id = ? AND farm_id IN (SELECT id FROM farms WHERE farmer_id = ?)";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$new_status, $device_id, $farmer_id]);
            
            // Log the action
            $log_query = "INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->execute([$farmer_id, 'iot_device_toggle', "Device ID: $device_id, New Status: $new_status"]);
            
            $success_message = "Device status updated successfully!";
        }
    } elseif ($action == 'set_mode' && $device_id) {
        $control_mode = $_POST['control_mode'] ?? 'manual';
        
        // Update device control mode (you might need to add a control_mode column to iot_devices)
        $update_query = "UPDATE iot_devices SET control_mode = ? WHERE id = ? AND farm_id IN (SELECT id FROM farms WHERE farmer_id = ?)";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$control_mode, $device_id, $farmer_id]);
        
        // Log the action
        $log_query = "INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([$farmer_id, 'iot_control_mode', "Device ID: $device_id, Mode: $control_mode"]);
        
        $success_message = "Control mode updated successfully!";
    }
}

// Get all IoT devices for the farmer's farms
$devices_query = "SELECT id.*, f.name as farm_name, 
                  (SELECT COUNT(*) FROM iot_readings sr WHERE sr.device_id = id.id) as reading_count,
                  (SELECT sr.reading_value FROM iot_readings sr WHERE sr.device_id = id.id ORDER BY sr.timestamp DESC LIMIT 1) as latest_reading
                  FROM iot_devices id 
                  JOIN farms f ON id.farm_id = f.id 
                  WHERE f.farmer_id = ? 
                  ORDER BY f.name, id.device_type";
try {
    $devices_stmt = $db->prepare($devices_query);
    $devices_stmt->execute([$farmer_id]);
    $devices = $devices_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $devices = [];
    error_log("IoT control devices query error: " . $e->getMessage());
}

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total_devices,
                SUM(CASE WHEN id.status = 'active' THEN 1 ELSE 0 END) as active_devices,
                AVG(id.last_reading) as avg_reading
                FROM iot_devices id 
                JOIN farms f ON id.farm_id = f.id 
                WHERE f.farmer_id = ?";
try {
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute([$farmer_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total_devices' => 0, 'active_devices' => 0, 'avg_reading' => 0];
    error_log("IoT control stats query error: " . $e->getMessage());
}

// Get recent sensor readings
$readings_query = "SELECT sr.*, id.device_name, id.device_type, f.name as farm_name
                   FROM iot_readings sr
                   JOIN iot_devices id ON sr.device_id = id.id
                   JOIN farms f ON id.farm_id = f.id
                   WHERE f.farmer_id = ?
                   ORDER BY sr.timestamp DESC
                   LIMIT 10";
try {
    $readings_stmt = $db->prepare($readings_query);
    $readings_stmt->execute([$farmer_id]);
    $recent_readings = $readings_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_readings = [];
    error_log("IoT control readings query error: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <div class="sidebar">
                <h5><i class="fas fa-microchip me-2"></i>IoT Control</h5>
                <hr>
                <div class="mb-3">
                    <strong>Welcome, <?php echo $_SESSION['username']; ?>!</strong>
                    <div class="green-points mt-2">
                        <i class="fas fa-star me-1"></i><?php echo $_SESSION['green_points']; ?> Green Points
                    </div>
                </div>
                
                <div class="list-group">
                    <a href="farmer_dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="farm_requests.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-farm me-2"></i>My Farms
                    </a>
                    <a href="request_drone.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-drone me-2"></i>Request Drone
                    </a>
                    <a href="iot_monitoring.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-line me-2"></i>IoT Monitoring
                    </a>
                    <a href="iot_control.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-microchip me-2"></i>IoT Control
                    </a>
                    <a href="iot_analytics.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-bar me-2"></i>IoT Analytics
                    </a>
                    <a href="marketplace.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-store me-2"></i>Marketplace
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-microchip me-2"></i>IoT Device Control</h2>
                    <div class="text-end">
                        <small class="text-muted">Auto-refresh: <span id="refreshTimer">30</span>s</small>
                    </div>
                </div>
                
                <?php if(isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card dashboard-stats">
                            <div class="card-body text-center">
                                <i class="fas fa-microchip fa-2x mb-2 text-color"></i>
                                <h4 class="text-color"><?php echo $stats['total_devices']; ?></h4>
                                <p class="mb-0 text-color">Total Devices</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card dashboard-stats">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-2x mb-2 text-color"></i>
                                <h4 class="text-color"><?php echo $stats['active_devices']; ?></h4>
                                <p class="mb-0 text-color">Active Devices</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card dashboard-stats">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-line fa-2x mb-2 text-color"></i>
                                <h4 class="text-color"><?php echo number_format($stats['avg_reading'], 1); ?></h4>
                                <p class="mb-0 text-color">Avg Reading</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Device Control Panels -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-sliders-h me-2"></i>Device Control Panels</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($devices)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-microchip fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No IoT devices found.</p>
                                <p class="text-muted">Devices will appear here once they are assigned to your farms.</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach($devices as $device): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-<?php echo getDeviceIcon($device['device_type']); ?> me-2"></i>
                                                    <?php echo htmlspecialchars($device['device_name']); ?>
                                                </h6>
                                                <span class="badge bg-<?php echo $device['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($device['status']); ?>
                                                </span>
                                            </div>
                                            <div class="card-body">
                                                <p class="text-muted mb-2">
                                                    <small>Farm: <?php echo htmlspecialchars($device['farm_name']); ?></small>
                                                </p>
                                                <p class="text-muted mb-2">
                                                    <small>Type: <?php echo ucfirst(str_replace('_', ' ', $device['device_type'])); ?></small>
                                                </p>
                                                <?php if($device['latest_reading']): ?>
                                                    <p class="mb-2">
                                                        <strong>Latest Reading:</strong> 
                                                        <span class="text-primary"><?php echo $device['latest_reading']; ?></span>
                                                    </p>
                                                <?php endif; ?>
                                                <p class="mb-3">
                                                    <small class="text-muted">Readings: <?php echo $device['reading_count']; ?></small>
                                                </p>
                                                
                                                <!-- Control Buttons -->
                                                <div class="btn-group w-100" role="group">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle_device">
                                                        <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-<?php echo $device['status'] == 'active' ? 'warning' : 'success'; ?>">
                                                            <i class="fas fa-<?php echo $device['status'] == 'active' ? 'pause' : 'play'; ?> me-1"></i>
                                                            <?php echo $device['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Sensor Readings -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line me-2"></i>Recent Sensor Readings</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($recent_readings)): ?>
                            <div class="text-center py-3">
                                <i class="fas fa-chart-line fa-2x text-muted mb-2"></i>
                                <p class="text-muted">No sensor readings available.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Device</th>
                                            <th>Farm</th>
                                            <th>Type</th>
                                            <th>Value</th>
                                            <th>Timestamp</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recent_readings as $reading): ?>
                                            <tr>
                                                <td>
                                                    <i class="fas fa-<?php echo getDeviceIcon($reading['device_type']); ?> me-2"></i>
                                                    <?php echo htmlspecialchars($reading['device_name']); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($reading['farm_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo ucfirst(str_replace('_', ' ', $reading['sensor_type'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?php echo $reading['reading_value']; ?></strong>
                                                </td>
                                                <td><?php echo date('M j, Y g:i A', strtotime($reading['timestamp'])); ?></td>
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
</div>

<script>
// Auto-refresh functionality
let refreshTimer = 30;
const timerElement = document.getElementById('refreshTimer');

setInterval(function() {
    refreshTimer--;
    timerElement.textContent = refreshTimer;
    
    if (refreshTimer <= 0) {
        location.reload();
        refreshTimer = 30;
    }
}, 1000);

// Update timer display every second
setInterval(function() {
    timerElement.textContent = refreshTimer;
}, 1000);
</script>

<?php
// Helper function to get device icons
function getDeviceIcon($device_type) {
    switch($device_type) {
        case 'temperature':
            return 'thermometer-half';
        case 'humidity':
            return 'tint';
        case 'soil_moisture':
            return 'droplet';
        case 'light':
            return 'sun';
        case 'water_flow':
            return 'water';
        case 'pump':
            return 'pump-soap';
        case 'fan':
            return 'fan';
        case 'light_control':
            return 'lightbulb';
        default:
            return 'microchip';
    }
}
?>

<?php include 'includes/footer.php'; ?>
