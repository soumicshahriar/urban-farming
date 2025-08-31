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

// Handle filters
$action_filter = $_GET['action'] ?? '';
$user_filter = $_GET['user_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search_term = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if($action_filter) {
    $where_conditions[] = "sl.action = ?";
    $params[] = $action_filter;
}

if($user_filter) {
    $where_conditions[] = "sl.user_id = ?";
    $params[] = $user_filter;
}

if($date_from) {
    $where_conditions[] = "DATE(sl.created_at) >= ?";
    $params[] = $date_from;
}

if($date_to) {
    $where_conditions[] = "DATE(sl.created_at) <= ?";
    $params[] = $date_to;
}

if($search_term) {
    $where_conditions[] = "(sl.details LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get system logs with user information
$logs_query = "SELECT sl.*, u.username, u.email, u.role 
               FROM system_logs sl 
               LEFT JOIN users u ON sl.user_id = u.id 
               $where_clause
               ORDER BY sl.created_at DESC 
               LIMIT 1000";

$logs_stmt = $db->prepare($logs_query);
$logs_stmt->execute($params);
$system_logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique actions for filter
$actions_query = "SELECT DISTINCT action FROM system_logs ORDER BY action";
$actions_stmt = $db->prepare($actions_query);
$actions_stmt->execute();
$actions = $actions_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get users for filter
$users_query = "SELECT id, username, email FROM users ORDER BY username";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get log statistics
$stats_query = "SELECT 
                    COUNT(*) as total_logs,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT action) as unique_actions,
                    DATE(created_at) as log_date,
                    COUNT(*) as daily_count
                FROM system_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY log_date DESC";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$log_stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get action frequency
$action_freq_query = "SELECT action, COUNT(*) as count 
                      FROM system_logs 
                      GROUP BY action 
                      ORDER BY count DESC 
                      LIMIT 10";
$action_freq_stmt = $db->prepare($action_freq_query);
$action_freq_stmt->execute();
$action_frequency = $action_freq_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <div class="sidebar">
                <h5><i class="fas fa-history me-2"></i>System Logs</h5>
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
                    <a href="user_management.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-users me-2"></i>User Management
                    </a>
                    <a href="system_logs.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-history me-2"></i>System Logs
                    </a>
                    <a href="drone_requests.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-drone me-2"></i>All Drone Requests
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-history me-2"></i>System Activity Logs</h2>
                    <div class="text-end">
                        <small class="text-muted">Total Logs: <?php echo count($system_logs); ?></small>
                    </div>
                </div>
                
                <!-- Log Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card dashboard-stats">
                            <div class="card-body text-center">
                                <i class="fas fa-history fa-2x mb-2 text-primary"></i>
                                <h4><?php echo count($system_logs); ?></h4>
                                <p class="mb-0">Recent Logs</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card dashboard-stats">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x mb-2 text-success"></i>
                                <h4><?php echo count(array_unique(array_column($system_logs, 'user_id'))); ?></h4>
                                <p class="mb-0">Active Users</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card dashboard-stats">
                            <div class="card-body text-center">
                                <i class="fas fa-tasks fa-2x mb-2 text-info"></i>
                                <h4><?php echo count(array_unique(array_column($system_logs, 'action'))); ?></h4>
                                <p class="mb-0">Action Types</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card dashboard-stats">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar fa-2x mb-2 text-warning"></i>
                                <h4><?php echo count($log_stats); ?></h4>
                                <p class="mb-0">Active Days</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-filter me-2"></i>Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row">
                            <div class="col-md-2">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search_term); ?>" 
                                       placeholder="Details, username, email">
                            </div>
                            <div class="col-md-2">
                                <label for="action" class="form-label">Action</label>
                                <select class="form-select" id="action" name="action">
                                    <option value="">All Actions</option>
                                    <?php foreach($actions as $action): ?>
                                        <option value="<?php echo $action; ?>" <?php echo $action_filter == $action ? 'selected' : ''; ?>>
                                            <?php echo ucfirst(str_replace('_', ' ', $action)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="user_id" class="form-label">User</label>
                                <select class="form-select" id="user_id" name="user_id">
                                    <option value="">All Users</option>
                                    <?php foreach($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-blue-color me-2">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                                <a href="system_logs.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Action Frequency Chart -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-bar me-2"></i>Most Common Actions</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="actionChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-line me-2"></i>Daily Activity (Last 7 Days)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="dailyChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- System Logs Table -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>System Logs</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($system_logs)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No system logs found matching your criteria.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Timestamp</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($system_logs as $log): ?>
                                            <tr>
                                                <td>
                                                    <div class="small">
                                                        <strong><?php echo date('M j, Y', strtotime($log['created_at'])); ?></strong>
                                                        <br>
                                                        <span class="text-muted"><?php echo date('g:i:s A', strtotime($log['created_at'])); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if($log['username']): ?>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar me-2">
                                                                <i class="fas fa-user-circle text-primary"></i>
                                                            </div>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                                                <br>
                                                                <small class="text-muted">
                                                                    <?php echo ucfirst($log['role']); ?> • <?php echo htmlspecialchars($log['email']); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">System</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="text-wrap" style="max-width: 300px;">
                                                        <?php echo htmlspecialchars($log['details']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo $log['ip_address'] ? htmlspecialchars($log['ip_address']) : 'N/A'; ?>
                                                    </small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination Info -->
                            <div class="mt-3">
                                <small class="text-muted">
                                    Showing <?php echo count($system_logs); ?> most recent logs. 
                                    Use filters to narrow down results.
                                </small>
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
    // Action Frequency Chart
    const actionCtx = document.getElementById('actionChart').getContext('2d');
    new Chart(actionCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($action_frequency, 'action')); ?>,
            datasets: [{
                label: 'Count',
                data: <?php echo json_encode(array_column($action_frequency, 'count')); ?>,
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
    
    // Daily Activity Chart
    const dailyCtx = document.getElementById('dailyChart').getContext('2d');
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($log_stats, 'log_date')); ?>,
            datasets: [{
                label: 'Logs per Day',
                data: <?php echo json_encode(array_column($log_stats, 'daily_count')); ?>,
                backgroundColor: 'rgba(40, 167, 69, 0.2)',
                borderColor: '#28a745',
                borderWidth: 2,
                fill: true
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
});
</script>

<?php include 'includes/footer.php'; ?>
