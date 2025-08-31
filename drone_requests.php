<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle status filter
$status_filter = $_GET['status'] ?? '';
$farm_filter = $_GET['farm_id'] ?? '';

// Build the query based on user role and filters
$where_conditions = [];
$params = [];

if($_SESSION['role'] == 'farmer') {
    $where_conditions[] = "dr.farmer_id = ?";
    $params[] = $_SESSION['user_id'];
}

if($status_filter) {
    $where_conditions[] = "dr.status = ?";
    $params[] = $status_filter;
}

if($farm_filter) {
    $where_conditions[] = "dr.farm_id = ?";
    $params[] = $farm_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get drone requests
$requests_query = "SELECT dr.*, f.name as farm_name, u.username as farmer_name, u.email as farmer_email, 
                         d.name as drone_name
                  FROM drone_requests dr 
                  JOIN farms f ON dr.farm_id = f.id 
                  JOIN users u ON dr.farmer_id = u.id 
                  LEFT JOIN drones d ON dr.drone_id = d.id
                  $where_clause
                  ORDER BY dr.created_at DESC";
$requests_stmt = $db->prepare($requests_query);
$requests_stmt->execute($params);
$drone_requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's farms for filter (if farmer)
$user_farms = [];
if($_SESSION['role'] == 'farmer') {
    $farms_query = "SELECT id, name FROM farms WHERE farmer_id = ? ORDER BY name";
    $farms_stmt = $db->prepare($farms_query);
    $farms_stmt->execute([$_SESSION['user_id']]);
    $user_farms = $farms_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get statistics
$stats_query = "SELECT status, COUNT(*) as count FROM drone_requests";
if($_SESSION['role'] == 'farmer') {
    $stats_query .= " WHERE farmer_id = ?";
    $stats_params = [$_SESSION['user_id']];
} else {
    $stats_params = [];
}
$stats_query .= " GROUP BY status";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute($stats_params);
$stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <div class="sidebar">
                <h5><i class="fas fa-drone me-2"></i>Drone Requests</h5>
                <hr>
                <div class="mb-3">
                    <strong>Welcome, <?php echo $_SESSION['username']; ?>!</strong>
                    <div class="green-points mt-2">
                        <i class="fas fa-star me-1"></i><?php echo $_SESSION['green_points']; ?> Green Points
                    </div>
                </div>
                
                <div class="list-group">
                    <?php if($_SESSION['role'] == 'farmer'): ?>
                        <a href="farmer_dashboard.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a href="farm_requests.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-farm me-2"></i>My Farms
                        </a>
                        <a href="request_drone.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-plus me-2"></i>New Drone Request
                        </a>
                        <a href="drone_requests.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-drone me-2"></i>My Drone Requests
                        </a>
                        <a href="iot_monitoring.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-sensor me-2"></i>IoT Monitoring
                        </a>
                        <a href="marketplace.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-store me-2"></i>Marketplace
                        </a>
                    <?php elseif($_SESSION['role'] == 'planner'): ?>
                        <a href="planner_dashboard.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a href="farm_approvals.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-check-circle me-2"></i>Farm Approvals
                        </a>
                        <a href="drone_approvals.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-drone me-2"></i>Drone Approvals
                        </a>
                        <a href="drone_requests.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-list me-2"></i>All Drone Requests
                        </a>
                        <a href="drone_inventory.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-boxes me-2"></i>Drone Inventory
                        </a>
                    <?php else: ?>
                        <a href="admin_dashboard.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a href="drone_requests.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-drone me-2"></i>All Drone Requests
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-drone me-2"></i>Drone Requests</h2>
                    <?php if($_SESSION['role'] == 'farmer'): ?>
                        <a href="request_drone.php" class="btn btn-blue-color">
                            <i class="fas fa-plus me-2"></i>New Request
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row g-3 text-color">
                    <?php
                    $statuses = ['pending', 'approved', 'rejected', 'en_route', 'active', 'completed'];
                    $status_labels = [
                        'pending' => 'Pending',
                        'approved' => 'Approved', 
                        'rejected' => 'Rejected',
                        'en_route' => 'En Route',
                        'active' => 'Active',
                        'completed' => 'Completed'
                    ];
                    $status_colors = [
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'en_route' => 'primary',
                        'active' => 'info',
                        'completed' => 'secondary'
                    ];
                    
                    foreach($statuses as $status):
                        $count = 0;
                        foreach($stats as $stat) {
                            if($stat['status'] == $status) {
                                $count = $stat['count'];
                                break;
                            }
                        }
                    ?>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="card dashboard-stats h-100 shadow-sm border-0 hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-drone fa-2x mb-2"<?php echo $status_colors[$status]; ?>"></i>
                                    <h4 class="fw-bold mb-1"><?php echo $count; ?></h4>
                                    <p class="mb-0 text-color"><?php echo $status_labels[$status]; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

<!-- Optional CSS for hover effect -->
<style>
    .hover-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .hover-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 6px 18px rgba(0,0,0,0.12);
    }
</style>


<!-- Optional CSS for hover effect -->
<style>
    .hover-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .hover-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 6px 18px rgba(0,0,0,0.12);
    }
</style>

                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-filter me-2"></i>Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row">
                            <?php if($_SESSION['role'] == 'farmer' && !empty($user_farms)): ?>
                                <div class="col-md-4">
                                    <label for="farm_id" class="form-label">Farm</label>
                                    <select class="form-select" id="farm_id" name="farm_id">
                                        <option value="">All Farms</option>
                                        <?php foreach($user_farms as $farm): ?>
                                            <option value="<?php echo $farm['id']; ?>" <?php echo $farm_filter == $farm['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($farm['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div class="col-md-4">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <?php foreach($statuses as $status): ?>
                                        <option value="<?php echo $status; ?>" <?php echo $status_filter == $status ? 'selected' : ''; ?>>
                                            <?php echo $status_labels[$status]; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-blue-color me-2">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                                <a href="drone_requests.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Drone Requests Table -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>Drone Requests</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($drone_requests)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-drone fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No drone requests found.</p>
                                <?php if($_SESSION['role'] == 'farmer'): ?>
                                    <a href="request_drone.php" class="btn btn-blue-color">
                                        <i class="fas fa-plus me-2"></i>Create First Request
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Farm</th>
                                            <?php if($_SESSION['role'] != 'farmer'): ?>
                                                <th>Farmer</th>
                                            <?php endif; ?>
                                            <th>Purpose</th>
                                            <th>Location</th>
                                            <th>Preferred Time</th>
                                            <th>Status</th>
                                            <th>Assigned Drone</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($drone_requests as $request): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($request['farm_name']); ?></strong>
                                                    <br>
                                                    <a href="view_farm.php?id=<?php echo $request['farm_id']; ?>" class="text-decoration-none">
                                                        <small class="text-muted">View Farm</small>
                                                    </a>
                                                </td>
                                                <?php if($_SESSION['role'] != 'farmer'): ?>
                                                    <td>
                                                        <?php echo htmlspecialchars($request['farmer_name']); ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($request['farmer_email']); ?></small>
                                                    </td>
                                                <?php endif; ?>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo ucfirst(str_replace('_', ' ', $request['purpose'])); ?>
                                                    </span>
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
                                                <td>
                                                    <?php if($request['drone_name']): ?>
                                                        <span class="text-success"><?php echo htmlspecialchars($request['drone_name']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#requestModal" 
                                                                data-request-id="<?php echo $request['id']; ?>" data-request-data='<?php echo json_encode($request); ?>'>
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if($_SESSION['role'] == 'farmer' && $request['status'] == 'pending'): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="cancelRequest(<?php echo $request['id']; ?>)">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php endif; ?>
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

<!-- Request Details Modal -->
<div class="modal fade" id="requestModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Drone Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="requestModalBody">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Request details modal
    const requestModal = document.getElementById('requestModal');
    if (requestModal) {
        requestModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const requestData = JSON.parse(button.getAttribute('data-request-data'));
            
            const modalBody = document.getElementById('requestModalBody');
            modalBody.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Request Information</h6>
                        <p><strong>Farm:</strong> ${requestData.farm_name}</p>
                        <p><strong>Purpose:</strong> ${requestData.purpose.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</p>
                        <p><strong>Location:</strong> ${requestData.location}</p>
                        <p><strong>Preferred Time:</strong> ${new Date(requestData.preferred_time).toLocaleString()}</p>
                        <p><strong>Status:</strong> 
                            <span class="badge bg-${getStatusColor(requestData.status)}">${getStatusLabel(requestData.status)}</span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6>Assignment Details</h6>
                        <p><strong>Assigned Drone:</strong> ${requestData.drone_name || 'Not assigned'}</p>
                        <p><strong>Approved By:</strong> ${requestData.status === 'approved' ? 'Planner' : 'Not approved'}</p>
                        <p><strong>Created:</strong> ${new Date(requestData.created_at).toLocaleString()}</p>
                        ${requestData.notes ? `<p><strong>Notes:</strong> ${requestData.notes}</p>` : ''}
                        ${requestData.result_report ? `<p><strong>Result Report:</strong> ${requestData.result_report}</p>` : ''}
                    </div>
                </div>
            `;
        });
    }
});

function getStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'approved': 'success',
        'rejected': 'danger',
        'en_route': 'primary',
        'active': 'info',
        'completed': 'secondary'
    };
    return colors[status] || 'secondary';
}

function getStatusLabel(status) {
    const labels = {
        'pending': 'Pending',
        'approved': 'Approved',
        'rejected': 'Rejected',
        'en_route': 'En Route',
        'active': 'Active',
        'completed': 'Completed'
    };
    return labels[status] || status;
}

function cancelRequest(requestId) {
    if (confirm('Are you sure you want to cancel this drone request?')) {
        // Here you would typically make an AJAX call to cancel the request
        alert('Request cancellation functionality would be implemented here.');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
