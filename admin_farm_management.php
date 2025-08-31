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

// Handle status filter
$status_filter = $_GET['status'] ?? '';
$farm_type_filter = $_GET['farm_type'] ?? '';

// Build the query based on filters
$where_conditions = [];
$params = [];

if($status_filter) {
    $where_conditions[] = "f.status = ?";
    $params[] = $status_filter;
}

if($farm_type_filter) {
    $where_conditions[] = "f.farm_type = ?";
    $params[] = $farm_type_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get all farms with farmer information
$farms_query = "SELECT f.*, u.username as farmer_name, u.email as farmer_email,
                       (SELECT COUNT(*) FROM iot_devices WHERE farm_id = f.id) as iot_count,
                       (SELECT COUNT(*) FROM drone_requests WHERE farm_id = f.id) as drone_requests_count
                FROM farms f 
                JOIN users u ON f.farmer_id = u.id 
                $where_clause
                ORDER BY f.created_at DESC";
$farms_stmt = $db->prepare($farms_query);
$farms_stmt->execute($params);
$farms = $farms_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT status, COUNT(*) as count FROM farms GROUP BY status";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <div class="sidebar">
                <h5><i class="fas fa-chart-line me-2"></i>Admin Dashboard</h5>
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
                    <a href="global_monitoring.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-line me-2"></i>Global Monitoring
                    </a>
                    <a href="drone_requests.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-drone me-2"></i>All Drone Requests
                    </a>
                    <a href="admin_farm_management.php" class="list-group-item list-group-item-action active">
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
                    <h2><i class="fas fa-farm me-2"></i>Farm Management</h2>
                    <div class="text-end">
                        <small class="text-muted">Total Farms: <?php echo count($farms); ?></small>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <?php
                    $statuses = ['pending', 'approved', 'rejected'];
                    foreach($statuses as $status):
                        $status_count = 0;
                        foreach($stats as $stat) {
                            if($stat['status'] == $status) {
                                $status_count = $stat['count'];
                                break;
                            }
                        }
                    ?>
                        <div class="col-md-4 mb-3">
                            <div class="card dashboard-stats">
                                <div class="card-body text-center">
                                    <i class="fas fa-farm fa-2x mb-2 text-<?php echo getStatusColor($status); ?>"></i>
                                    <h4 class="text-color"><?php echo $status_count; ?></h4>
                                    <p class="mb-0 text-color"><?php echo ucfirst($status); ?> Farms</p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="status" class="form-label">Status Filter</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="farm_type" class="form-label">Farm Type</label>
                                <select class="form-select" id="farm_type" name="farm_type">
                                    <option value="">All Types</option>
                                    <option value="vegetable" <?php echo $farm_type_filter == 'vegetable' ? 'selected' : ''; ?>>Vegetable</option>
                                    <option value="fruit" <?php echo $farm_type_filter == 'fruit' ? 'selected' : ''; ?>>Fruit</option>
                                    <option value="grain" <?php echo $farm_type_filter == 'grain' ? 'selected' : ''; ?>>Grain</option>
                                    <option value="mixed" <?php echo $farm_type_filter == 'mixed' ? 'selected' : ''; ?>>Mixed</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-filter me-1"></i>Filter
                                </button>
                                <a href="admin_farm_management.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Farms List -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>All Farms</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($farms)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-farm fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No farms found matching the current filters.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Farm Name</th>
                                            <th>Farmer</th>
                                            <th>Type</th>
                                            <th>Location</th>
                                            <th>Status</th>
                                            <th>IoT Devices</th>
                                            <th>Drone Requests</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($farms as $farm): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($farm['name']); ?></strong>
                                                </td>
                                                <td>
                                                    <div><?php echo htmlspecialchars($farm['farmer_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($farm['farmer_email']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo ucfirst($farm['farm_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($farm['location']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusColor($farm['status']); ?>">
                                                        <?php echo ucfirst($farm['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo $farm['iot_count']; ?> devices</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $farm['drone_requests_count']; ?> requests</span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($farm['created_at'])); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewFarmDetails(<?php echo $farm['id']; ?>)">
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
        </div>
    </div>
</div>

<!-- Farm Details Modal -->
<div class="modal fade" id="farmDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Farm Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="farmDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function viewFarmDetails(farmId) {
    // Show loading state
    document.getElementById('farmDetailsContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading farm details...</p>
        </div>
    `;
    
    // Show modal first
    new bootstrap.Modal(document.getElementById('farmDetailsModal')).show();
    
    // Load farm details via AJAX
    fetch(`view_farm.php?id=${farmId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            document.getElementById('farmDetailsContent').innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('farmDetailsContent').innerHTML = `
                <div class="alert alert-danger">
                    <h5>Error Loading Farm Details</h5>
                    <p>Failed to load farm details. Please try again.</p>
                    <small class="text-muted">Error: ${error.message}</small>
                </div>
            `;
        });
}
</script>

<?php
// Helper function to get status colors
function getStatusColor($status) {
    switch($status) {
        case 'pending': return 'warning';
        case 'approved': return 'success';
        case 'rejected': return 'danger';
        default: return 'secondary';
    }
}
?>

<?php include 'includes/footer.php'; ?>
