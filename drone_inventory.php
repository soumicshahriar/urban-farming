<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is a planner or admin
if(!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'planner' && $_SESSION['role'] != 'admin')) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get all drones with additional information
$drones_query = "SELECT d.*, 
                        (SELECT COUNT(*) FROM drone_requests WHERE drone_id = d.id AND status = 'completed') as completed_missions,
                        (SELECT COUNT(*) FROM drone_requests WHERE drone_id = d.id AND status IN ('assigned', 'en_route', 'active')) as active_missions
                 FROM drones d 
                 ORDER BY drone_type, name";
$drones_stmt = $db->prepare($drones_query);
$drones_stmt->execute();
$drones = $drones_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get drone statistics
$stats_query = "SELECT drone_type, status, COUNT(*) as count FROM drones GROUP BY drone_type, status";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
                    <div class="sidebar">
                <h5><i class="fas fa-clipboard-check me-2"></i><?php echo $_SESSION['role'] == 'admin' ? 'Admin' : 'Planner'; ?> Dashboard</h5>
                <hr>
                <div class="mb-3">
                    <strong>Welcome, <?php echo $_SESSION['username']; ?>!</strong>
                    <div class="green-points mt-2">
                        <i class="fas fa-star me-1"></i><?php echo $_SESSION['green_points']; ?> Green Points
                    </div>
                </div>
                
                <div class="list-group">
                    <?php if($_SESSION['role'] == 'admin'): ?>
                        <a href="admin_dashboard.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a href="global_monitoring.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-chart-line me-2"></i>Global Monitoring
                        </a>
                        <a href="drone_requests.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-drone me-2"></i>All Drone Requests
                        </a>
                        <a href="admin_farm_management.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-farm me-2"></i>Farm Management
                        </a>
                        <a href="drone_inventory.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-boxes me-2"></i>Drone Inventory
                        </a>
                        <a href="user_management.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-users-cog me-2"></i>User Management
                        </a>
                        <a href="system_logs.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-clipboard-list me-2"></i>System Logs
                        </a>
                    <?php else: ?>
                        <a href="planner_dashboard.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a href="farm_approvals.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-check-circle me-2"></i>Farm Approvals
                        </a>
                        <a href="drone_approvals.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-drone me-2"></i>Drone Approvals
                        </a>
                        <a href="drone_inventory.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-boxes me-2"></i>Drone Inventory
                        </a>
                    <?php endif; ?>
                </div>
            </div>
    </div>
    
    <div class="col-md-9">
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-boxes me-2"></i>Drone Inventory</h2>
                <div>
                    <button class="btn btn-outline-primary me-2" onclick="toggleView()">
                        <i class="fas fa-th-large me-1"></i>Card View
                    </button>
                    <button class="btn btn-outline-secondary me-2" onclick="toggleView()">
                        <i class="fas fa-list me-1"></i>Table View
                    </button>
                    <a href="<?php echo $_SESSION['role'] == 'admin' ? 'admin_dashboard.php' : 'planner_dashboard.php'; ?>" class="btn btn-blue-color">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <?php
                $drone_types = ['survey', 'spraying', 'monitoring', 'biological'];
                foreach($drone_types as $type):
                    $type_stats = array_filter($stats, function($s) use ($type) { return $s['drone_type'] == $type; });
                    $total = array_sum(array_column($type_stats, 'count'));
                    $available = 0;
                    foreach($type_stats as $stat) {
                        if($stat['status'] == 'available') {
                            $available = $stat['count'];
                            break;
                        }
                    }
                ?>
                    <div class="col-md-3 mb-3">
                        <div class="card dashboard-stats">
                            <div class="card-body text-center">
                                <i class="fas fa-drone fa-2x mb-2 text-primary"></i>
                                <h4 class="text-color"><?php echo $available; ?>/<?php echo $total; ?></h4>
                                <p class="mb-0 text-color"><?php echo ucfirst($type); ?> Drones</p>
                                <small class="text-color">Available</small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Card View -->
            <div id="cardView" class="drone-cards-container">
                <div class="row">
                    <?php foreach($drones as $drone): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card drone-card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">
                                        <i class="fas fa-drone me-2"></i><?php echo htmlspecialchars($drone['name']); ?>
                                    </h6>
                                    <span class="badge bg-<?php echo getDroneTypeColor($drone['drone_type']); ?>">
                                        <?php echo ucfirst($drone['drone_type']); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <!-- Status Badge -->
                                    <div class="mb-3">
                                        <span class="badge bg-<?php echo getStatusColor($drone['status']); ?> fs-6">
                                            <i class="fas fa-<?php echo getStatusIcon($drone['status']); ?> me-1"></i>
                                            <?php echo ucfirst($drone['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Battery Level -->
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <small class="text-muted">Battery Level</small>
                                            <small class="fw-bold"><?php echo $drone['battery_level']; ?>%</small>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar <?php echo getBatteryColor($drone['battery_level']); ?>" 
                                                 style="width: <?php echo $drone['battery_level']; ?>%">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Mission Stats -->
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <div class="text-center">
                                                <div class="h5 mb-0 text-success"><?php echo $drone['completed_missions']; ?></div>
                                                <small class="text-muted">Completed</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-center">
                                                <div class="h5 mb-0 text-warning"><?php echo $drone['active_missions']; ?></div>
                                                <small class="text-muted">Active</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Details -->
                                    <div class="drone-details">
                                        <div class="detail-item">
                                            <i class="fas fa-calendar-alt text-muted me-2"></i>
                                            <small class="text-muted">Created:</small>
                                            <span class="ms-1"><?php echo date('M j, Y', strtotime($drone['created_at'])); ?></span>
                                        </div>
                                        <?php if($drone['last_maintenance']): ?>
                                            <div class="detail-item">
                                                <i class="fas fa-wrench text-muted me-2"></i>
                                                <small class="text-muted">Last Maintenance:</small>
                                                <span class="ms-1"><?php echo date('M j, Y', strtotime($drone['last_maintenance'])); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if(isset($drone['notes']) && $drone['notes']): ?>
                                            <div class="detail-item">
                                                <i class="fas fa-sticky-note text-muted me-2"></i>
                                                <small class="text-muted">Notes:</small>
                                                <span class="ms-1"><?php echo htmlspecialchars(substr($drone['notes'], 0, 50)); ?><?php echo strlen($drone['notes']) > 50 ? '...' : ''; ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div class="d-flex justify-content-between">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewDroneDetails(<?php echo $drone['id']; ?>)">
                                            <i class="fas fa-eye me-1"></i>Details
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="viewDroneHistory(<?php echo $drone['id']; ?>)">
                                            <i class="fas fa-history me-1"></i>History
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Table View (Hidden by default) -->
            <div id="tableView" class="drone-table-container" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-drone me-2"></i>All Drones</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Drone Name</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Battery Level</th>
                                        <th>Missions</th>
                                        <th>Last Maintenance</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($drones as $drone): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($drone['name']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getDroneTypeColor($drone['drone_type']); ?>">
                                                    <?php echo ucfirst($drone['drone_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getStatusColor($drone['status']); ?>">
                                                    <?php echo ucfirst($drone['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar <?php echo getBatteryColor($drone['battery_level']); ?>" 
                                                         style="width: <?php echo $drone['battery_level']; ?>%">
                                                        <?php echo $drone['battery_level']; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-success me-1"><?php echo $drone['completed_missions']; ?> Completed</span>
                                                <span class="badge bg-warning"><?php echo $drone['active_missions']; ?> Active</span>
                                            </td>
                                            <td>
                                                <?php if($drone['last_maintenance']): ?>
                                                    <?php echo date('M j, Y', strtotime($drone['last_maintenance'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Never</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($drone['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewDroneDetails(<?php echo $drone['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-secondary" onclick="viewDroneHistory(<?php echo $drone['id']; ?>)">
                                                    <i class="fas fa-history"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Drone Details Modal -->
<div class="modal fade" id="droneDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Drone Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="droneDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Drone History Modal -->
<div class="modal fade" id="droneHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Drone Mission History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="droneHistoryContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function toggleView() {
    const cardView = document.getElementById('cardView');
    const tableView = document.getElementById('tableView');
    
    if (cardView.style.display === 'none') {
        cardView.style.display = 'block';
        tableView.style.display = 'none';
    } else {
        cardView.style.display = 'none';
        tableView.style.display = 'block';
    }
}

function viewDroneDetails(droneId) {
    // Show loading state
    document.getElementById('droneDetailsContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading drone details...</p>
        </div>
    `;
    
    // Show modal first
    new bootstrap.Modal(document.getElementById('droneDetailsModal')).show();
    
    // Load drone details via AJAX
    fetch(`get_drone_details.php?id=${droneId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                document.getElementById('droneDetailsContent').innerHTML = data.html;
            } else {
                document.getElementById('droneDetailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <h5>Error Loading Drone Details</h5>
                        <p>${data.message || 'Unknown error occurred'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('droneDetailsContent').innerHTML = `
                <div class="alert alert-danger">
                    <h5>Error Loading Drone Details</h5>
                    <p>Failed to load drone details. Please try again.</p>
                    <small class="text-muted">Error: ${error.message}</small>
                </div>
            `;
        });
}

function viewDroneHistory(droneId) {
    // Show loading state
    document.getElementById('droneHistoryContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading drone history...</p>
        </div>
    `;
    
    // Show modal first
    new bootstrap.Modal(document.getElementById('droneHistoryModal')).show();
    
    // Load drone history via AJAX
    fetch(`get_drone_history.php?id=${droneId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                document.getElementById('droneHistoryContent').innerHTML = data.html;
            } else {
                document.getElementById('droneHistoryContent').innerHTML = `
                    <div class="alert alert-danger">
                        <h5>Error Loading Drone History</h5>
                        <p>${data.message || 'Unknown error occurred'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('droneHistoryContent').innerHTML = `
                <div class="alert alert-danger">
                    <h5>Error Loading Drone History</h5>
                    <p>Failed to load drone history. Please try again.</p>
                    <small class="text-muted">Error: ${error.message}</small>
                </div>
            `;
        });
}
</script>

<style>
.drone-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border: 1px solid #e9ecef;
}

.drone-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.drone-card .card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
}

.drone-details {
    font-size: 0.875rem;
}

.detail-item {
    display: flex;
    align-items: center;
    margin-bottom: 0.5rem;
}

.detail-item i {
    width: 16px;
}

.progress {
    border-radius: 10px;
    background-color: #f8f9fa;
}

.progress-bar {
    border-radius: 10px;
}

.badge {
    font-size: 0.75rem;
}

.dashboard-stats {
    transition: transform 0.2s;
}

.dashboard-stats:hover {
    transform: translateY(-2px);
}

/* Status colors */
.status-available { background-color: #d4edda; color: #155724; }
.status-assigned { background-color: #fff3cd; color: #856404; }
.status-en_route { background-color: #cce7ff; color: #004085; }
.status-active { background-color: #d1ecf1; color: #0c5460; }
.status-completed { background-color: #e2e3e5; color: #383d41; }
.status-maintenance { background-color: #f8d7da; color: #721c24; }
</style>

<?php
// Helper functions
function getDroneTypeColor($type) {
    switch($type) {
        case 'survey': return 'info';
        case 'spraying': return 'warning';
        case 'monitoring': return 'primary';
        case 'biological': return 'success';
        default: return 'secondary';
    }
}

function getStatusColor($status) {
    switch($status) {
        case 'available': return 'success';
        case 'assigned': return 'warning';
        case 'en_route': return 'primary';
        case 'active': return 'info';
        case 'completed': return 'secondary';
        case 'maintenance': return 'danger';
        default: return 'secondary';
    }
}

function getStatusIcon($status) {
    switch($status) {
        case 'available': return 'check-circle';
        case 'assigned': return 'clock';
        case 'en_route': return 'plane';
        case 'active': return 'play-circle';
        case 'completed': return 'check-double';
        case 'maintenance': return 'wrench';
        default: return 'question-circle';
    }
}

function getBatteryColor($level) {
    if($level > 70) return 'bg-success';
    if($level > 30) return 'bg-warning';
    return 'bg-danger';
}
?>


