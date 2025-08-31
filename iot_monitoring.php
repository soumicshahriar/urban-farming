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

// Helper functions for IoT devices
function generateDummySensorData($device_type) {
    $data = [];
    
    switch($device_type) {
        case 'soil_moisture':
            $data = [
                'current' => rand(25, 85),
                'min' => rand(20, 30),
                'avg' => rand(45, 65),
                'max' => rand(80, 95),
                'unit' => '%',
                'battery' => rand(60, 95),
                'signal' => rand(75, 100)
            ];
            break;
        case 'temperature':
            $data = [
                'current' => rand(18, 32),
                'min' => rand(15, 20),
                'avg' => rand(22, 28),
                'max' => rand(30, 35),
                'unit' => '°C',
                'battery' => rand(70, 98),
                'signal' => rand(80, 100)
            ];
            break;
        case 'humidity':
            $data = [
                'current' => rand(40, 80),
                'min' => rand(35, 45),
                'avg' => rand(50, 70),
                'max' => rand(75, 85),
                'unit' => '%',
                'battery' => rand(65, 92),
                'signal' => rand(85, 100)
            ];
            break;
        case 'light':
            $data = [
                'current' => rand(200, 1200),
                'min' => rand(100, 300),
                'avg' => rand(500, 800),
                'max' => rand(1000, 1500),
                'unit' => 'lux',
                'battery' => rand(55, 90),
                'signal' => rand(70, 95)
            ];
            break;
        case 'water_flow':
            $data = [
                'current' => rand(2, 15),
                'min' => rand(0, 3),
                'avg' => rand(5, 10),
                'max' => rand(12, 20),
                'unit' => 'L/min',
                'battery' => rand(80, 96),
                'signal' => rand(90, 100)
            ];
            break;
        case 'ph_sensor':
            $data = [
                'current' => rand(60, 80) / 10,
                'min' => rand(55, 65) / 10,
                'avg' => rand(65, 75) / 10,
                'max' => rand(75, 85) / 10,
                'unit' => 'pH',
                'battery' => rand(75, 94),
                'signal' => rand(88, 100)
            ];
            break;
        default:
            $data = [
                'current' => rand(50, 100),
                'min' => rand(40, 60),
                'avg' => rand(60, 80),
                'max' => rand(80, 100),
                'unit' => 'units',
                'battery' => rand(60, 95),
                'signal' => rand(75, 100)
            ];
    }
    
    return $data;
}

function getDeviceIcon($device_type) {
    switch($device_type) {
        case 'soil_moisture': return 'fa-tint';
        case 'temperature': return 'fa-thermometer-half';
        case 'humidity': return 'fa-cloud';
        case 'light': return 'fa-sun';
        case 'water_flow': return 'fa-water';
        case 'ph_sensor': return 'fa-flask';
        default: return 'fa-microchip';
    }
}

function getDeviceColor($device_type) {
    switch($device_type) {
        case 'soil_moisture': return 'primary';
        case 'temperature': return 'danger';
        case 'humidity': return 'info';
        case 'light': return 'warning';
        case 'water_flow': return 'success';
        case 'ph_sensor': return 'secondary';
        default: return 'dark';
    }
}

// Get farmer's farms with IoT devices
$farms_query = "SELECT f.*, 
                (SELECT COUNT(*) FROM iot_devices WHERE farm_id = f.id AND status = 'active') as active_devices
                FROM farms f 
                WHERE f.farmer_id = ? AND f.status = 'approved'
                ORDER BY f.name";
$farms_stmt = $db->prepare($farms_query);
$farms_stmt->execute([$_SESSION['user_id']]);
$farms = $farms_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get IoT devices for all farms
$devices_query = "SELECT i.*, f.name as farm_name 
                 FROM iot_devices i 
                 JOIN farms f ON i.farm_id = f.id 
                 WHERE f.farmer_id = ? 
                 ORDER BY f.name, i.device_type";
$devices_stmt = $db->prepare($devices_query);
$devices_stmt->execute([$_SESSION['user_id']]);
$devices = $devices_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent sensor readings for charts
$readings_query = "SELECT ir.*, i.device_type, i.device_name, f.name as farm_name
                  FROM iot_readings ir
                  JOIN iot_devices i ON ir.device_id = i.id
                  JOIN farms f ON i.farm_id = f.id
                  WHERE f.farmer_id = ?
                  ORDER BY ir.timestamp DESC
                  LIMIT 100";
$readings_stmt = $db->prepare($readings_query);
$readings_stmt->execute([$_SESSION['user_id']]);
$readings = $readings_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <div class="sidebar">
            <h5><i class="fas fa-microchip me-2"></i>IoT Monitoring</h5>
            <hr>
            <div class="list-group">
                <a href="iot_monitoring.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
                <a href="iot_control.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-sliders-h me-2"></i>Device Control
                </a>
                <a href="iot_analytics.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-chart-line me-2"></i>Analytics
                </a>
                <a href="farmer_dashboard.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-microchip me-2"></i>IoT Monitoring Dashboard</h2>
                <div class="real-time-indicator"></div>
                <small class="text-muted">Real-time updates</small>
            </div>
            
            <!-- Farm Overview -->
            <div class="row mb-4">
                <?php foreach($farms as $farm): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card iot-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($farm['name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($farm['location']); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <div class="h5 mb-0"><?php echo $farm['active_devices']; ?></div>
                                        <small class="text-muted">Active Devices</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Individual Sensor Cards -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-microchip me-2"></i>Device Status - Individual Sensors</h5>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="refreshSensorData()">
                                    <i class="fas fa-sync-alt me-1"></i>Refresh
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="toggleAutoRefresh()">
                                    <i class="fas fa-play me-1"></i>Auto Refresh
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if(empty($devices)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-microchip fa-3x text-muted mb-3"></i>
                                    <h4>No IoT Devices Found</h4>
                                    <p class="text-muted">Add devices to your farms to start monitoring.</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach($devices as $device): ?>
                                        <?php
                                        // Generate dummy data for each device
                                        $dummy_data = generateDummySensorData($device['device_type']);
                                        $status_color = $device['status'] == 'active' ? 'success' : 
                                                      ($device['status'] == 'maintenance' ? 'warning' : 'danger');
                                        $icon = getDeviceIcon($device['device_type']);
                                        $color = getDeviceColor($device['device_type']);
                                        ?>
                                        <div class="col-md-4 col-lg-3 mb-3">
                                            <div class="card sensor-card h-100" data-device-id="<?php echo $device['id']; ?>">
                                                <div class="card-header bg-light">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($device['device_name']); ?></h6>
                                                        <span class="badge bg-<?php echo $status_color; ?>">
                                                            <?php echo ucfirst($device['status']); ?>
                                                        </span>
                                                    </div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($device['farm_name']); ?></small>
                                                </div>
                                                <div class="card-body text-center">
                                                    <div class="sensor-icon mb-3">
                                                        <i class="fas <?php echo $icon; ?> fa-3x text-<?php echo $color; ?>"></i>
                                                    </div>
                                                    
                                                    <!-- Current Reading -->
                                                    <div class="current-reading mb-3">
                                                        <div class="h3 text-<?php echo $color; ?> mb-1">
                                                            <span class="sensor-value"><?php echo $dummy_data['current']; ?></span>
                                                            <span class="sensor-unit"><?php echo $dummy_data['unit']; ?></span>
                                                        </div>
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <span class="last-updated"><?php echo date('g:i A'); ?></span>
                                                        </small>
                                                    </div>
                                                    
                                                    <!-- Sensor Stats -->
                                                    <div class="sensor-stats">
                                                        <div class="row text-center">
                                                            <div class="col-4">
                                                                <div class="stat-item">
                                                                    <div class="stat-label">Min</div>
                                                                    <div class="stat-value text-info"><?php echo $dummy_data['min']; ?></div>
                                                                </div>
                                                            </div>
                                                            <div class="col-4">
                                                                <div class="stat-item">
                                                                    <div class="stat-label">Avg</div>
                                                                    <div class="stat-value text-primary"><?php echo $dummy_data['avg']; ?></div>
                                                                </div>
                                                            </div>
                                                            <div class="col-4">
                                                                <div class="stat-item">
                                                                    <div class="stat-label">Max</div>
                                                                    <div class="stat-value text-danger"><?php echo $dummy_data['max']; ?></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Battery/Health Status -->
                                                    <div class="device-health mt-3">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="health-label">Battery:</span>
                                                            <div class="progress flex-grow-1 mx-2" style="height: 8px;">
                                                                <div class="progress-bar bg-success" role="progressbar" 
                                                                     style="width: <?php echo $dummy_data['battery']; ?>%"></div>
                                                            </div>
                                                            <span class="health-value"><?php echo $dummy_data['battery']; ?>%</span>
                                                        </div>
                                                        <div class="d-flex justify-content-between align-items-center mt-1">
                                                            <span class="health-label">Signal:</span>
                                                            <div class="progress flex-grow-1 mx-2" style="height: 8px;">
                                                                <div class="progress-bar bg-info" role="progressbar" 
                                                                     style="width: <?php echo $dummy_data['signal']; ?>%"></div>
                                                            </div>
                                                            <span class="health-value"><?php echo $dummy_data['signal']; ?>%</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card-footer bg-light">
                                                    <div class="d-flex justify-content-between">
                                                        <button class="btn btn-sm btn-outline-primary" onclick="viewDeviceDetails(<?php echo $device['id']; ?>)">
                                                            <i class="fas fa-eye me-1"></i>Details
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleDevice(<?php echo $device['id']; ?>)">
                                                            <i class="fas fa-power-off me-1"></i>Toggle
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Device Status -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-list me-2"></i>Device Status</h5>
                        </div>
                        <div class="card-body">
                            <?php if(empty($devices)): ?>
                                <p class="text-muted">No devices to display.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Device</th>
                                                <th>Farm</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th>Last Reading</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($devices as $device): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($device['device_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($device['farm_name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-secondary">
                                                            <?php echo ucwords(str_replace('_', ' ', $device['device_type'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $status_color = $device['status'] == 'active' ? 'success' : 
                                                                      ($device['status'] == 'maintenance' ? 'warning' : 'danger');
                                                        ?>
                                                        <span class="badge bg-<?php echo $status_color; ?>">
                                                            <?php echo ucfirst($device['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if($device['last_reading']): ?>
                                                            <?php echo $device['last_reading']; ?>
                                                            <br><small class="text-muted">
                                                                <?php echo date('M j, g:i A', strtotime($device['last_updated'])); ?>
                                                            </small>
                                                        <?php else: ?>
                                                            <span class="text-muted">No data</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="viewDeviceDetails(<?php echo $device['id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
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
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-line me-2"></i>Sensor Trends</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="sensorChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Alerts and Notifications -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-bell me-2"></i>Recent Alerts</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>System Status:</strong> All IoT devices are operating normally.
                            </div>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Maintenance Reminder:</strong> Temperature sensor on Farm A needs calibration in 3 days.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Chart.js for sensor trends
const ctx = document.getElementById('sensorChart').getContext('2d');
const sensorChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['6 AM', '8 AM', '10 AM', '12 PM', '2 PM', '4 PM', '6 PM'],
        datasets: [{
            label: 'Temperature (°C)',
            data: [22, 24, 26, 28, 27, 25, 23],
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.1)',
            tension: 0.1
        }, {
            label: 'Humidity (%)',
            data: [65, 60, 55, 50, 55, 60, 65],
            borderColor: 'rgb(54, 162, 235)',
            backgroundColor: 'rgba(54, 162, 235, 0.1)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Global variables for auto-refresh
let autoRefreshInterval = null;
let isAutoRefreshEnabled = false;

// Real-time updates simulation for sensor cards
function updateSensorData() {
    const sensorCards = document.querySelectorAll('.sensor-card');
    
    sensorCards.forEach(card => {
        const deviceId = card.dataset.deviceId;
        const sensorValue = card.querySelector('.sensor-value');
        const lastUpdated = card.querySelector('.last-updated');
        const batteryBar = card.querySelector('.progress-bar.bg-success');
        const signalBar = card.querySelector('.progress-bar.bg-info');
        const batteryValue = card.querySelector('.health-value');
        const signalValue = card.querySelectorAll('.health-value')[1];
        
        if (sensorValue) {
            const currentValue = parseFloat(sensorValue.textContent);
            const variation = (Math.random() - 0.5) * 4; // ±2 variation
            const newValue = Math.max(0, currentValue + variation);
            sensorValue.textContent = newValue.toFixed(1);
        }
        
        if (lastUpdated) {
            lastUpdated.textContent = new Date().toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit',
                hour12: true 
            });
        }
        
        // Update battery and signal levels with small variations
        if (batteryBar && batteryValue) {
            const currentBattery = parseInt(batteryBar.style.width);
            const batteryChange = (Math.random() - 0.5) * 2; // ±1% change
            const newBattery = Math.max(10, Math.min(100, currentBattery + batteryChange));
            batteryBar.style.width = newBattery + '%';
            batteryValue.textContent = Math.round(newBattery) + '%';
        }
        
        if (signalBar && signalValue) {
            const currentSignal = parseInt(signalBar.style.width);
            const signalChange = (Math.random() - 0.5) * 3; // ±1.5% change
            const newSignal = Math.max(50, Math.min(100, currentSignal + signalChange));
            signalBar.style.width = newSignal + '%';
            signalValue.textContent = Math.round(newSignal) + '%';
        }
    });
    
    // Update real-time indicator
    const indicator = document.querySelector('.real-time-indicator');
    if (indicator) {
        indicator.innerHTML = '<i class="fas fa-circle text-success"></i>';
        setTimeout(() => {
            indicator.innerHTML = '<i class="fas fa-circle text-muted"></i>';
        }, 500);
    }
}

// Refresh sensor data manually
function refreshSensorData() {
    updateSensorData();
    
    // Show refresh feedback
    const refreshBtn = document.querySelector('button[onclick="refreshSensorData()"]');
    const originalText = refreshBtn.innerHTML;
    refreshBtn.innerHTML = '<i class="fas fa-check me-1"></i>Refreshed';
    refreshBtn.classList.remove('btn-outline-primary');
    refreshBtn.classList.add('btn-success');
    
    setTimeout(() => {
        refreshBtn.innerHTML = originalText;
        refreshBtn.classList.remove('btn-success');
        refreshBtn.classList.add('btn-outline-primary');
    }, 2000);
}

// Toggle auto-refresh functionality
function toggleAutoRefresh() {
    const toggleBtn = document.querySelector('button[onclick="toggleAutoRefresh()"]');
    
    if (isAutoRefreshEnabled) {
        // Stop auto-refresh
        clearInterval(autoRefreshInterval);
        isAutoRefreshEnabled = false;
        toggleBtn.innerHTML = '<i class="fas fa-play me-1"></i>Auto Refresh';
        toggleBtn.classList.remove('btn-success');
        toggleBtn.classList.add('btn-outline-success');
    } else {
        // Start auto-refresh
        autoRefreshInterval = setInterval(updateSensorData, 10000); // Update every 10 seconds
        isAutoRefreshEnabled = true;
        toggleBtn.innerHTML = '<i class="fas fa-pause me-1"></i>Stop Auto';
        toggleBtn.classList.remove('btn-outline-success');
        toggleBtn.classList.add('btn-success');
    }
}

// Device control functions
function viewDeviceDetails(deviceId) {
    // Create and show device details modal
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'deviceModal';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Device Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Device Information</h6>
                            <p><strong>Device ID:</strong> ${deviceId}</p>
                            <p><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
                            <p><strong>Last Calibration:</strong> 2 weeks ago</p>
                            <p><strong>Next Maintenance:</strong> 3 weeks from now</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Performance Metrics</h6>
                            <p><strong>Uptime:</strong> 99.2%</p>
                            <p><strong>Accuracy:</strong> ±0.5%</p>
                            <p><strong>Data Points:</strong> 1,247 readings</p>
                            <p><strong>Alerts:</strong> 2 (resolved)</p>
                        </div>
                    </div>
                    <hr>
                    <h6>Recent Readings</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Value</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td>2:30 PM</td><td>24.5°C</td><td><span class="badge bg-success">Normal</span></td></tr>
                                <tr><td>2:25 PM</td><td>24.3°C</td><td><span class="badge bg-success">Normal</span></td></tr>
                                <tr><td>2:20 PM</td><td>24.7°C</td><td><span class="badge bg-success">Normal</span></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-blue-color">Export Data</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const modalInstance = new bootstrap.Modal(modal);
    modalInstance.show();
    
    // Remove modal from DOM after it's hidden
    modal.addEventListener('hidden.bs.modal', function() {
        document.body.removeChild(modal);
    });
}

function toggleDevice(deviceId) {
    const card = document.querySelector(`[data-device-id="${deviceId}"]`);
    const statusBadge = card.querySelector('.badge');
    const toggleBtn = card.querySelector('button[onclick="toggleDevice(' + deviceId + ')"]');
    
    if (statusBadge.classList.contains('bg-success')) {
        // Turn off device
        statusBadge.textContent = 'Inactive';
        statusBadge.classList.remove('bg-success');
        statusBadge.classList.add('bg-secondary');
        toggleBtn.innerHTML = '<i class="fas fa-power-off me-1"></i>Turn On';
        toggleBtn.classList.remove('btn-outline-secondary');
        toggleBtn.classList.add('btn-outline-success');
    } else {
        // Turn on device
        statusBadge.textContent = 'Active';
        statusBadge.classList.remove('bg-secondary');
        statusBadge.classList.add('bg-success');
        toggleBtn.innerHTML = '<i class="fas fa-power-off me-1"></i>Turn Off';
        toggleBtn.classList.remove('btn-outline-success');
        toggleBtn.classList.add('btn-outline-secondary');
    }
}

// Initialize real-time updates
document.addEventListener('DOMContentLoaded', function() {
    // Start with manual refresh
    updateSensorData();
    
    // Add some sample devices if none exist
    const sensorCards = document.querySelectorAll('.sensor-card');
    if (sensorCards.length === 0) {
        console.log('No sensor cards found. Add some IoT devices to see the monitoring dashboard.');
    }
});
</script>

<style>
/* IoT Monitoring Dashboard Styles */
.sensor-card {
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.sensor-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.sensor-card .card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
}

.sensor-icon {
    position: relative;
}

.sensor-icon::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 60px;
    height: 60px;
    background: rgba(0,123,255,0.1);
    border-radius: 50%;
    z-index: -1;
}

.current-reading {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border-radius: 8px;
    padding: 15px;
    margin: 10px 0;
}

.sensor-stats {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 10px;
    margin: 10px 0;
}

.stat-item {
    padding: 5px;
}

.stat-label {
    font-size: 0.75rem;
    color: #6c757d;
    font-weight: 600;
    text-transform: uppercase;
}

.stat-value {
    font-size: 0.9rem;
    font-weight: 700;
}

.device-health {
    background: #ffffff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 10px;
    margin: 10px 0;
}

.health-label {
    font-size: 0.8rem;
    color: #495057;
    font-weight: 600;
    min-width: 50px;
}

.health-value {
    font-size: 0.8rem;
    font-weight: 700;
    color: #495057;
    min-width: 35px;
    text-align: right;
}

.progress {
    background-color: #e9ecef;
    border-radius: 10px;
}

.progress-bar {
    border-radius: 10px;
    transition: width 0.3s ease;
}

.real-time-indicator {
    display: inline-block;
    margin-left: 10px;
}

.real-time-indicator i {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

/* Device status badges */
.badge {
    font-size: 0.7rem;
    padding: 0.35em 0.65em;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .sensor-card {
        margin-bottom: 1rem;
    }
    
    .current-reading .h3 {
        font-size: 1.5rem;
    }
    
    .stat-value {
        font-size: 0.8rem;
    }
}

/* Auto-refresh button states */
.btn-outline-success.active {
    background-color: #28a745;
    border-color: #28a745;
    color: white;
}

/* Modal enhancements */
.modal-body {
    max-height: 70vh;
    overflow-y: auto;
}

/* Chart container */
#sensorChart {
    max-height: 300px;
}
</style>

<?php include 'includes/footer.php'; ?>
