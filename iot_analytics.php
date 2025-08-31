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

// Get date range filter
$date_range = $_GET['date_range'] ?? '7d';
$device_filter = $_GET['device_id'] ?? '';

// Calculate date range
$end_date = date('Y-m-d H:i:s');
switch($date_range) {
    case '1d':
        $start_date = date('Y-m-d H:i:s', strtotime('-1 day'));
        break;
    case '7d':
        $start_date = date('Y-m-d H:i:s', strtotime('-7 days'));
        break;
    case '30d':
        $start_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        break;
    case '90d':
        $start_date = date('Y-m-d H:i:s', strtotime('-90 days'));
        break;
    default:
        $start_date = date('Y-m-d H:i:s', strtotime('-7 days'));
}

// Get user's IoT devices
$devices_query = "SELECT id.*, f.name as farm_name 
                  FROM iot_devices id
                  JOIN farms f ON id.farm_id = f.id
                  WHERE f.farmer_id = ?
                  ORDER BY id.device_name";
$devices_stmt = $db->prepare($devices_query);
$devices_stmt->execute([$_SESSION['user_id']]);
$iot_devices = $devices_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get sensor readings for analytics
$where_conditions = ["f.farmer_id = ?", "sr.timestamp BETWEEN ? AND ?"];
$params = [$_SESSION['user_id'], $start_date, $end_date];

if($device_filter) {
    $where_conditions[] = "sr.device_id = ?";
    $params[] = $device_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$readings_query = "SELECT sr.*, id.device_type, id.device_name, f.name as farm_name
                   FROM iot_readings sr
                   JOIN iot_devices id ON sr.device_id = id.id
                   JOIN farms f ON id.farm_id = f.id
                   $where_clause
                   ORDER BY sr.timestamp DESC";
try {
    $readings_stmt = $db->prepare($readings_query);
    $readings_stmt->execute($params);
    $sensor_readings = $readings_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If there's an error, show empty readings
    $sensor_readings = [];
    error_log("IoT analytics query error: " . $e->getMessage());
}

// Calculate analytics
$total_readings = count($sensor_readings);
$avg_value = $total_readings > 0 ? array_sum(array_column($sensor_readings, 'reading_value')) / $total_readings : 0;
$min_value = $total_readings > 0 ? min(array_column($sensor_readings, 'reading_value')) : 0;
$max_value = $total_readings > 0 ? max(array_column($sensor_readings, 'reading_value')) : 0;

// Get device statistics
$device_stats_query = "SELECT 
                        id.device_type,
                        COUNT(*) as reading_count,
                        AVG(sr.reading_value) as avg_value,
                        MIN(sr.reading_value) as min_value,
                        MAX(sr.reading_value) as max_value
                       FROM iot_readings sr
                       JOIN iot_devices id ON sr.device_id = id.id
                       JOIN farms f ON id.farm_id = f.id
                       WHERE f.farmer_id = ? AND sr.timestamp BETWEEN ? AND ?
                       GROUP BY id.device_type";
try {
    $device_stats_stmt = $db->prepare($device_stats_query);
    $device_stats_stmt->execute([$_SESSION['user_id'], $start_date, $end_date]);
    $device_stats = $device_stats_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $device_stats = [];
    error_log("Device stats query error: " . $e->getMessage());
}

// Get hourly data for charts
$hourly_query = "SELECT 
                   DATE(sr.timestamp) as date,
                   HOUR(sr.timestamp) as hour,
                   AVG(sr.reading_value) as avg_value,
                   COUNT(*) as reading_count
                 FROM iot_readings sr
                 JOIN iot_devices id ON sr.device_id = id.id
                 JOIN farms f ON id.farm_id = f.id
                 WHERE f.farmer_id = ? AND sr.timestamp BETWEEN ? AND ?
                 GROUP BY DATE(sr.timestamp), HOUR(sr.timestamp)
                 ORDER BY date, hour";
try {
    $hourly_stmt = $db->prepare($hourly_query);
    $hourly_stmt->execute([$_SESSION['user_id'], $start_date, $end_date]);
    $hourly_data = $hourly_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $hourly_data = [];
    error_log("Hourly data query error: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <div class="sidebar">
                <h5><i class="fas fa-chart-bar me-2"></i>IoT Analytics</h5>
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
                    <a href="iot_control.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-microchip me-2"></i>IoT Control
                    </a>
                    <a href="iot_analytics.php" class="list-group-item list-group-item-action active">
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
                    <h2><i class="fas fa-chart-bar me-2"></i>IoT Analytics & Insights</h2>
                    <div class="text-end">
                        <small class="text-muted">Total Readings: <?php echo number_format($total_readings); ?></small>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-filter me-2"></i>Analytics Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row">
                            <div class="col-md-4">
                                <label for="date_range" class="form-label">Date Range</label>
                                <select class="form-select" id="date_range" name="date_range">
                                    <option value="1d" <?php echo $date_range == '1d' ? 'selected' : ''; ?>>Last 24 Hours</option>
                                    <option value="7d" <?php echo $date_range == '7d' ? 'selected' : ''; ?>>Last 7 Days</option>
                                    <option value="30d" <?php echo $date_range == '30d' ? 'selected' : ''; ?>>Last 30 Days</option>
                                    <option value="90d" <?php echo $date_range == '90d' ? 'selected' : ''; ?>>Last 90 Days</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="device_id" class="form-label">Device</label>
                                <select class="form-select" id="device_id" name="device_id">
                                    <option value="">All Devices</option>
                                    <?php foreach($iot_devices as $device): ?>
                                        <option value="<?php echo $device['id']; ?>" <?php echo $device_filter == $device['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($device['device_name']); ?> (<?php echo htmlspecialchars($device['farm_name']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-blue-color me-2">
                                    <i class="fas fa-search me-1"></i>Apply Filters
                                </button>
                                <a href="iot_analytics.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Analytics Overview -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card dashboard-stats">
                            <div class="card-body text-center">
                                <i class="fas fa-database fa-2x mb-2 text-color"></i>
                                <h4 class="text-color"><?php echo number_format($total_readings); ?></h4>
                                <p class="mb-0 text-color">Total Readings</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-stats">
                            <div class="card-body text-center">
                                <i class="fas fa-calculator fa-2x mb-2 text-color"></i>
                                <h4 class="text-color"><?php echo number_format($avg_value, 1); ?></h4>
                                <p class="mb-0 text-color">Average Value</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-stats">
                            <div class="card-body text-center">
                                <i class="fas fa-arrow-down fa-2x mb-2 text-info"></i>
                                <h4 class="text-color"><?php echo number_format($min_value, 1); ?></h4>
                                <p class="mb-0 text-color">Minimum Value</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-stats">
                            <div class="card-body text-center">
                                <i class="fas fa-arrow-up fa-2x mb-2 text-warning"></i>
                                <h4 class="text-color"><?php echo number_format($max_value, 1); ?></h4>
                                <p class="mb-0 text-color">Maximum Value</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-line me-2"></i>Sensor Data Trends</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="sensorTrendsChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-pie me-2"></i>Device Type Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="deviceTypeChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Device Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-table me-2"></i>Device Statistics</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($device_stats)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No device statistics available.</p>
                                <p class="text-muted">Sensor data will appear here once devices are active.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Device Type</th>
                                            <th>Readings</th>
                                            <th>Average</th>
                                            <th>Minimum</th>
                                            <th>Maximum</th>
                                            <th>Trend</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($device_stats as $stat): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar me-3">
                                                            <i class="fas fa-<?php echo getDeviceIcon($stat['device_type']); ?> fa-2x text-primary"></i>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo ucfirst($stat['device_type']); ?></strong>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo number_format($stat['reading_count']); ?></span>
                                                </td>
                                                <td>
                                                    <strong><?php echo number_format($stat['avg_value'], 1); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="text-info"><?php echo number_format($stat['min_value'], 1); ?></span>
                                                </td>
                                                <td>
                                                    <span class="text-warning"><?php echo number_format($stat['max_value'], 1); ?></span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $trend = getTrendIndicator($stat['avg_value'], $stat['min_value'], $stat['max_value']);
                                                    ?>
                                                    <i class="fas fa-<?php echo $trend['icon']; ?> text-<?php echo $trend['color']; ?>"></i>
                                                    <small class="text-muted"><?php echo $trend['text']; ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Readings -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>Recent Sensor Readings</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($sensor_readings)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-microchip fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No sensor readings found.</p>
                                <p class="text-muted">Readings will appear here once devices are active.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Device</th>
                                            <th>Farm</th>
                                            <th>Value</th>
                                            <th>Status</th>
                                            <th>Timestamp</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach(array_slice($sensor_readings, 0, 20) as $reading): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar me-3">
                                                            <i class="fas fa-<?php echo getDeviceIcon($reading['device_type']); ?> fa-2x text-primary"></i>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($reading['device_name']); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo ucfirst($reading['device_type']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <strong><?php echo htmlspecialchars($reading['farm_name']); ?></strong>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <strong><?php echo $reading['reading_value']; ?> <?php echo $reading['reading_type']; ?></strong>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status = getSensorStatus($reading['device_type'], $reading['reading_value']);
                                                    ?>
                                                    <span class="badge bg-<?php echo $status['class']; ?>"><?php echo $status['text']; ?></span>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <strong><?php echo date('M j, g:i A', strtotime($reading['timestamp'])); ?></strong>
                                                    </div>
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
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Sensor Trends Chart
const trendsCtx = document.getElementById('sensorTrendsChart').getContext('2d');
const trendsChart = new Chart(trendsCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_map(function($item) { return date('M j, g:i A', strtotime($item['date'] . ' ' . $item['hour'] . ':00:00')); }, $hourly_data)); ?>,
        datasets: [{
            label: 'Average Sensor Value',
            data: <?php echo json_encode(array_column($hourly_data, 'avg_value')); ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Device Type Distribution Chart
const deviceCtx = document.getElementById('deviceTypeChart').getContext('2d');
const deviceChart = new Chart(deviceCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_map(function($item) { return ucfirst($item['device_type']); }, $device_stats)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($device_stats, 'reading_count')); ?>,
            backgroundColor: [
                'rgba(255, 99, 132, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 206, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(153, 102, 255, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<?php
// Helper functions
function getDeviceIcon($deviceType) {
    $icons = [
        'sensor' => 'microchip',
        'pump' => 'tint',
        'fan' => 'wind',
        'light' => 'lightbulb',
        'camera' => 'video',
        'thermostat' => 'thermometer-half'
    ];
    return $icons[$deviceType] ?? 'microchip';
}

function getSensorStatus($deviceType, $value) {
    switch($deviceType) {
        case 'sensor':
            if($value < 20) return ['class' => 'danger', 'text' => 'Low'];
            if($value > 80) return ['class' => 'warning', 'text' => 'High'];
            return ['class' => 'success', 'text' => 'Normal'];
        case 'pump':
            return ['class' => 'info', 'text' => 'Active'];
        case 'fan':
            return ['class' => 'info', 'text' => 'Running'];
        case 'light':
            return ['class' => 'info', 'text' => 'On'];
        default:
            return ['class' => 'secondary', 'text' => 'Unknown'];
    }
}

function getTrendIndicator($avg, $min, $max) {
    $range = $max - $min;
    $avg_position = ($avg - $min) / $range;
    
    if($avg_position < 0.3) {
        return ['icon' => 'arrow-down', 'color' => 'danger', 'text' => 'Decreasing'];
    } elseif($avg_position > 0.7) {
        return ['icon' => 'arrow-up', 'color' => 'success', 'text' => 'Increasing'];
    } else {
        return ['icon' => 'minus', 'color' => 'secondary', 'text' => 'Stable'];
    }
}
?>

<?php include 'includes/footer.php'; ?>
