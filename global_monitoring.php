<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is an admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get system-wide statistics
$stats = [];

// User statistics
$user_stats_query = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$user_stats_stmt = $db->prepare($user_stats_query);
$user_stats_stmt->execute();
$user_stats = $user_stats_stmt->fetchAll(PDO::FETCH_ASSOC);

// Farm statistics
$farm_stats_query = "SELECT status, COUNT(*) as count FROM farms GROUP BY status";
$farm_stats_stmt = $db->prepare($farm_stats_query);
$farm_stats_stmt->execute();
$farm_stats = $farm_stats_stmt->fetchAll(PDO::FETCH_ASSOC);

// Drone statistics
$drone_stats_query = "SELECT status, COUNT(*) as count FROM drones GROUP BY status";
$drone_stats_stmt = $db->prepare($drone_stats_query);
$drone_stats_stmt->execute();
$drone_stats = $drone_stats_stmt->fetchAll(PDO::FETCH_ASSOC);

// Drone request statistics
$drone_request_stats_query = "SELECT status, COUNT(*) as count FROM drone_requests GROUP BY status";
$drone_request_stmt = $db->prepare($drone_request_stats_query);
$drone_request_stmt->execute();
$drone_request_stats = $drone_request_stmt->fetchAll(PDO::FETCH_ASSOC);

// IoT device statistics
$iot_stats_query = "SELECT device_type, COUNT(*) as count FROM iot_devices GROUP BY device_type";
$iot_stats_stmt = $db->prepare($iot_stats_query);
$iot_stats_stmt->execute();
$iot_stats = $iot_stats_stmt->fetchAll(PDO::FETCH_ASSOC);

// Seed marketplace statistics
$seed_stats_query = "SELECT status, COUNT(*) as count FROM seed_listings GROUP BY status";
$seed_stats_stmt = $db->prepare($seed_stats_query);
$seed_stats_stmt->execute();
$seed_stats = $seed_stats_stmt->fetchAll(PDO::FETCH_ASSOC);

// Green Points statistics
$green_points_query = "SELECT SUM(amount) as total_earned FROM green_points_transactions WHERE transaction_type = 'earned'";
$green_points_stmt = $db->prepare($green_points_query);
$green_points_stmt->execute();
$total_green_points = $green_points_stmt->fetch(PDO::FETCH_ASSOC)['total_earned'] ?? 0;

// Recent system activities
$recent_activities_query = "SELECT sl.*, u.username FROM system_logs sl 
                           LEFT JOIN users u ON sl.user_id = u.id 
                           ORDER BY sl.created_at DESC LIMIT 10";
$recent_activities_stmt = $db->prepare($recent_activities_query);
$recent_activities_stmt->execute();
$recent_activities = $recent_activities_stmt->fetchAll(PDO::FETCH_ASSOC);

// System health indicators
$system_health = [];

// Check for farms pending approval
$pending_farms_query = "SELECT COUNT(*) as count FROM farms WHERE status = 'pending'";
$pending_farms_stmt = $db->prepare($pending_farms_query);
$pending_farms_stmt->execute();
$system_health['pending_farms'] = $pending_farms_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Check for drone requests pending approval
$pending_drones_query = "SELECT COUNT(*) as count FROM drone_requests WHERE status = 'pending'";
$pending_drones_stmt = $db->prepare($pending_drones_query);
$pending_drones_stmt->execute();
$system_health['pending_drones'] = $pending_drones_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Check for seed listings pending approval
$pending_seeds_query = "SELECT COUNT(*) as count FROM seed_listings WHERE status = 'pending'";
$pending_seeds_stmt = $db->prepare($pending_seeds_query);
$pending_seeds_stmt->execute();
$system_health['pending_seeds'] = $pending_seeds_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Check for drones in maintenance
$maintenance_drones_query = "SELECT COUNT(*) as count FROM drones WHERE status = 'maintenance'";
$maintenance_drones_stmt = $db->prepare($maintenance_drones_query);
$maintenance_drones_stmt->execute();
$system_health['maintenance_drones'] = $maintenance_drones_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Check for inactive IoT devices
$inactive_iot_query = "SELECT COUNT(*) as count FROM iot_devices WHERE status = 'inactive'";
$inactive_iot_stmt = $db->prepare($inactive_iot_query);
$inactive_iot_stmt->execute();
$system_health['inactive_iot'] = $inactive_iot_stmt->fetch(PDO::FETCH_ASSOC)['count'];

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <div class="sidebar">
                <h5><i class="fas fa-chart-line me-2"></i>Global Monitoring</h5>
                <hr>
                <div class="mb-3">
                    <strong>Welcome, <?php echo $_SESSION['username']; ?>!</strong>
                    <div class="green-points mt-2">
                        <i class="fas fa-star me-1"></i><?php echo $_SESSION['green_points']; ?> Green Points
                    </div>
                </div>
                
                <div class="list-group">
                    <a href="admin_dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="global_monitoring.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-chart-line me-2"></i>Global Monitoring
                    </a>
                    <a href="drone_requests.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-drone me-2"></i>All Drone Requests
                    </a>
                    <a href="admin_farm_management.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-farm me-2"></i>Farm Management
                    </a>
                    <a href="drone_inventory.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-boxes me-2"></i>Drone Inventory
                    </a>
                    <a href="user_management.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-users-cog me-2"></i>User Management
                    </a>
                    <a href="system_logs.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-clipboard-list me-2"></i>System Logs
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-chart-line me-2"></i>Global System Monitoring</h2>
                    <div class="text-end">
                        <small class="text-muted">Last updated: <?php echo date('M j, Y g:i A'); ?></small>
                    </div>
                </div>
                
                <!-- System Health Alerts -->
                <div class="row mb-4">
                    <?php if($system_health['pending_farms'] > 0): ?>
                        <div class="col-md-4 mb-3">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong><?php echo $system_health['pending_farms']; ?></strong> farms pending approval
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($system_health['pending_drones'] > 0): ?>
                        <div class="col-md-4 mb-3">
                            <div class="alert alert-info">
                                <i class="fas fa-drone me-2"></i>
                                <strong><?php echo $system_health['pending_drones']; ?></strong> drone requests pending
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($system_health['pending_seeds'] > 0): ?>
                        <div class="col-md-4 mb-3">
                            <div class="alert alert-success">
                                <i class="fas fa-seedling me-2"></i>
                                <strong><?php echo $system_health['pending_seeds']; ?></strong> seed listings pending review
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($system_health['maintenance_drones'] > 0): ?>
                        <div class="col-md-4 mb-3">
                            <div class="alert alert-danger">
                                <i class="fas fa-tools me-2"></i>
                                <strong><?php echo $system_health['maintenance_drones']; ?></strong> drones in maintenance
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($system_health['inactive_iot'] > 0): ?>
                        <div class="col-md-4 mb-3">
                            <div class="alert alert-secondary">
                                <i class="fas fa-sensor me-2"></i>
                                <strong><?php echo $system_health['inactive_iot']; ?></strong> IoT devices inactive
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- System Statistics -->
                <div class="row mb-4">
                    <!-- User Statistics -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-users me-2"></i>User Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="userChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Farm Statistics -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-farm me-2"></i>Farm Status</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="farmChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Drone Statistics -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-drone me-2"></i>Drone Fleet Status</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="droneChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Drone Request Statistics -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-clipboard-list me-2"></i>Drone Request Status</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="droneRequestChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Key Metrics -->
                <div class="row g-4 mb-5">
                <!-- Total Users -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="card dashboard-stats shadow-sm border-0 h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x mb-2 text-color"></i>
                                <h4 class="text-color"><?php echo array_sum(array_column($user_stats, 'count')); ?></h4>
                                <p class="mb-0 text-color">Total Users</p>
                            </div>
                        </div>
                    </div>

                    <!-- Total Farms -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="card dashboard-stats shadow-sm border-0 h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-farm fa-2x mb-2 text-color"></i>
                                <h4 class="text-color"><?php echo array_sum(array_column($farm_stats, 'count')); ?></h4>
                                <p class="mb-0 text-color">Total Farms</p>
                            </div>
                        </div>
                    </div>

                    <!-- Total Drones -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="card dashboard-stats shadow-sm border-0 h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-drone fa-2x mb-2 text-color"></i>
                                <h4 class="text-color"><?php echo array_sum(array_column($drone_stats, 'count')); ?></h4>
                                <p class="mb-0 text-color">Total Drones</p>
                            </div>
                        </div>
                    </div>

                    <!-- Green Points -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="card dashboard-stats shadow-sm border-0 h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-star fa-2x mb-2 text-color"></i>
                                <h4 class="text-color"><?php echo number_format($total_green_points); ?></h4>
                                <p class="mb-0 text-color">Green Points Earned</p>
                            </div>
                        </div>
                    </div>
                </div>

                
                <!-- IoT and Marketplace Statistics -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-sensor me-2"></i>IoT Device Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="iotChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-store me-2"></i>Seed Marketplace Status</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="seedChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent System Activities -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history me-2"></i>Recent System Activities</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($recent_activities)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No recent activities found.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                            <th>Timestamp</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recent_activities as $activity): ?>
                                            <tr>
                                                <td>
                                                    <?php if($activity['username']): ?>
                                                        <strong><?php echo htmlspecialchars($activity['username']); ?></strong>
                                                    <?php else: ?>
                                                        <span class="text-muted">System</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo ucfirst(str_replace('_', ' ', $activity['action'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($activity['details']); ?></td>
                                                <td><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></td>
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
document.addEventListener('DOMContentLoaded', function() {
    // User Distribution Chart
    const userCtx = document.getElementById('userChart').getContext('2d');
    new Chart(userCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($user_stats, 'role')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($user_stats, 'count')); ?>,
                backgroundColor: ['#007bff', '#28a745', '#dc3545'],
                borderWidth: 2
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
    
    // Farm Status Chart
    const farmCtx = document.getElementById('farmChart').getContext('2d');
    new Chart(farmCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($farm_stats, 'status')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($farm_stats, 'count')); ?>,
                backgroundColor: ['#ffc107', '#28a745', '#dc3545'],
                borderWidth: 2
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
    
    // Drone Fleet Status Chart
    const droneCtx = document.getElementById('droneChart').getContext('2d');
    new Chart(droneCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($drone_stats, 'status')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($drone_stats, 'count')); ?>,
                backgroundColor: ['#28a745', '#007bff', '#ffc107', '#17a2b8', '#6c757d', '#dc3545'],
                borderWidth: 2
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
    
    // Drone Request Status Chart
    const droneRequestCtx = document.getElementById('droneRequestChart').getContext('2d');
    new Chart(droneRequestCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($drone_request_stats, 'status')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($drone_request_stats, 'count')); ?>,
                backgroundColor: ['#ffc107', '#28a745', '#dc3545', '#007bff', '#17a2b8', '#6c757d'],
                borderWidth: 2
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
    
    // IoT Device Distribution Chart
    const iotCtx = document.getElementById('iotChart').getContext('2d');
    new Chart(iotCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($iot_stats, 'device_type')); ?>,
            datasets: [{
                label: 'Device Count',
                data: <?php echo json_encode(array_column($iot_stats, 'count')); ?>,
                backgroundColor: '#17a2b8',
                borderColor: '#138496',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    
    // Seed Marketplace Status Chart
    const seedCtx = document.getElementById('seedChart').getContext('2d');
    new Chart(seedCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($seed_stats, 'status')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($seed_stats, 'count')); ?>,
                backgroundColor: ['#ffc107', '#28a745', '#6c757d'],
                borderWidth: 2
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
});
</script>

<?php include 'includes/footer.php'; ?>
