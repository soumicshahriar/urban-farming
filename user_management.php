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

// Handle user actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_POST['user_id'] ?? 0;
    
    if($action && $user_id) {
        switch($action) {
            case 'update_role':
                $new_role = $_POST['new_role'] ?? '';
                if(in_array($new_role, ['farmer', 'planner', 'admin'])) {
                    $update_query = "UPDATE users SET role = ? WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    if($update_stmt->execute([$new_role, $user_id])) {
                        $success_message = "User role updated successfully!";
                        
                        // Log the action
                        $log_query = "INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
                        $log_stmt = $db->prepare($log_query);
                        $log_stmt->execute([$_SESSION['user_id'], 'user_role_updated', "User ID: $user_id, New Role: $new_role"]);
                    } else {
                        $error_message = "Failed to update user role.";
                    }
                }
                break;
                
            case 'update_green_points':
                $new_points = $_POST['new_points'] ?? 0;
                if(is_numeric($new_points) && $new_points >= 0) {
                    $update_query = "UPDATE users SET green_points = ? WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    if($update_stmt->execute([$new_points, $user_id])) {
                        $success_message = "Green Points updated successfully!";
                        
                        // Log the action
                        $log_query = "INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
                        $log_stmt = $db->prepare($log_query);
                        $log_stmt->execute([$_SESSION['user_id'], 'green_points_updated', "User ID: $user_id, New Points: $new_points"]);
                    } else {
                        $error_message = "Failed to update Green Points.";
                    }
                }
                break;
                
            case 'deactivate_user':
                // Note: Since users table doesn't have status column, we'll just log the action
                $success_message = "User deactivation logged successfully!";
                
                // Log the action
                $log_query = "INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->execute([$_SESSION['user_id'], 'user_deactivated', "User ID: $user_id"]);
                break;
        }
    }
}

// Handle filters
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search_term = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if($role_filter) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

// Note: Status filtering removed since users table doesn't have status column
// if($status_filter) {
//     $where_conditions[] = "status = ?";
//     $params[] = $status_filter;
// }

if($search_term) {
    $where_conditions[] = "(username LIKE ? OR email LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get users with statistics
$users_query = "SELECT u.*, 
                       COUNT(DISTINCT f.id) as farm_count,
                       COUNT(DISTINCT dr.id) as drone_request_count,
                       COUNT(DISTINCT sl.id) as seed_listing_count,
                       SUM(gpt.amount) as total_green_points_earned
                FROM users u 
                LEFT JOIN farms f ON u.id = f.farmer_id
                LEFT JOIN drone_requests dr ON u.id = dr.farmer_id
                LEFT JOIN seed_listings sl ON u.id = sl.seller_id
                LEFT JOIN green_points_transactions gpt ON u.id = gpt.user_id AND gpt.transaction_type = 'earned'
                $where_clause
                GROUP BY u.id
                ORDER BY u.created_at DESC";

$users_stmt = $db->prepare($users_query);
$users_stmt->execute($params);
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user statistics
$user_stats_query = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$user_stats_stmt = $db->prepare($user_stats_query);
$user_stats_stmt->execute();
$user_stats = $user_stats_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get Green Points statistics
$green_points_stats_query = "SELECT 
                                SUM(green_points) as total_points,
                                AVG(green_points) as avg_points,
                                MAX(green_points) as max_points
                             FROM users";
$green_points_stats_stmt = $db->prepare($green_points_stats_query);
$green_points_stats_stmt->execute();
$green_points_stats = $green_points_stats_stmt->fetch(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <div class="sidebar">
                <h5><i class="fas fa-users me-2"></i>User Management</h5>
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
                    <a href="user_management.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-users me-2"></i>User Management
                    </a>
                    <a href="drone_requests.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-drone me-2"></i>All Drone Requests
                    </a>
                    <a href="farm_approvals.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-farm me-2"></i>Farm Approvals
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-users me-2"></i>User Management</h2>
                    <div class="text-end">
                        <small class="text-muted">Total Users: <?php echo count($users); ?></small>
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
                
                <!-- User Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card dashboard-stats">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x mb-2 text-color"></i>
                                <h4 class="text-color"><?php echo array_sum(array_column($user_stats, 'count')); ?></h4>
                                <p class="mb-0 text-color">Total Users</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card dashboard-stats">
                            <div class="card-body text-center">
                                <i class="fas fa-star fa-2x mb-2 text-color"></i>
                                <h4 class="text-color"><?php echo number_format($green_points_stats['total_points'] ?? 0); ?></h4>
                                <p class="mb-0 text-color">Total Green Points</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card dashboard-stats">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-line fa-2x mb-2 text-color"></i>
                                <h4 class="text-color"><?php echo number_format($green_points_stats['avg_points'] ?? 0, 0); ?></h4>
                                <p class="mb-0 text-color">Avg Points/User</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card dashboard-stats">
                            <div class="card-body text-center">
                                <i class="fas fa-trophy fa-2x mb-2 text-color"></i>
                                <h4 class="text-color"><?php echo number_format($green_points_stats['max_points'] ?? 0); ?></h4>
                                <p class="mb-0 text-color">Highest Points</p>
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
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search_term); ?>" 
                                       placeholder="Username or Email">
                            </div>
                            <div class="col-md-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" id="role" name="role">
                                    <option value="">All Roles</option>
                                    <option value="farmer" <?php echo $role_filter == 'farmer' ? 'selected' : ''; ?>>Farmer</option>
                                    <option value="planner" <?php echo $role_filter == 'planner' ? 'selected' : ''; ?>>Planner</option>
                                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                            <!-- Status filter removed since users table doesn't have status column -->
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" disabled>
                                    <option>All Users Active</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-blue-color me-2">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                                <a href="user_management.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Users Table -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>Users</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($users)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No users found matching your criteria.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Role</th>
                                            <th>Green Points</th>
                                            <th>Activity</th>
                                            <th>Status</th>
                                            <th>Joined</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar me-3">
                                                            <i class="fas fa-user-circle fa-2x text-primary"></i>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if($user['role'] == 'farmer'): ?>
                                                        <span class="badge bg-success">Farmer</span>
                                                    <?php elseif($user['role'] == 'planner'): ?>
                                                        <span class="badge bg-info">Planner</span>
                                                    <?php elseif($user['role'] == 'admin'): ?>
                                                        <span class="badge bg-danger">Admin</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-star text-warning me-1"></i>
                                                        <strong><?php echo number_format($user['green_points']); ?></strong>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <div>Farms: <strong><?php echo $user['farm_count']; ?></strong></div>
                                                        <div>Drone Requests: <strong><?php echo $user['drone_request_count']; ?></strong></div>
                                                        <div>Seed Listings: <strong><?php echo $user['seed_listing_count']; ?></strong></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success">Active</span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#userModal" 
                                                                data-user-data='<?php echo json_encode($user); ?>'>
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#editUserModal" 
                                                                data-user-data='<?php echo json_encode($user); ?>'>
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if($user['id'] != $_SESSION['user_id']): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deactivateUser(<?php echo $user['id']; ?>)">
                                                                <i class="fas fa-ban"></i>
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

<!-- User Details Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userModalBody">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editUserForm">
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="mb-3">
                        <label for="edit_username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="edit_username" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_role" class="form-label">Role</label>
                        <select class="form-select" id="edit_role" name="new_role" required>
                            <option value="farmer">Farmer</option>
                            <option value="planner">Planner</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_green_points" class="form-label">Green Points</label>
                        <input type="number" class="form-control" id="edit_green_points" name="new_points" min="0" required>
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-blue-color">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // User details modal
    const userModal = document.getElementById('userModal');
    if (userModal) {
        userModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userData = JSON.parse(button.getAttribute('data-user-data'));
            
            const modalBody = document.getElementById('userModalBody');
            modalBody.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>User Information</h6>
                        <p><strong>Username:</strong> ${userData.username}</p>
                        <p><strong>Email:</strong> ${userData.email}</p>
                        <p><strong>Role:</strong> 
                            <span class="badge bg-${getRoleColor(userData.role)}">${userData.role.charAt(0).toUpperCase() + userData.role.slice(1)}</span>
                        </p>
                                                 <p><strong>Status:</strong> 
                             <span class="badge bg-success">Active</span>
                         </p>
                        <p><strong>Joined:</strong> ${new Date(userData.created_at).toLocaleDateString()}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Activity Summary</h6>
                        <p><strong>Farms:</strong> ${userData.farm_count}</p>
                        <p><strong>Drone Requests:</strong> ${userData.drone_request_count}</p>
                        <p><strong>Seed Listings:</strong> ${userData.seed_listing_count}</p>
                        <p><strong>Green Points:</strong> <i class="fas fa-star text-warning"></i> ${parseInt(userData.green_points).toLocaleString()}</p>
                        <p><strong>Total Earned:</strong> <i class="fas fa-star text-warning"></i> ${parseInt(userData.total_green_points_earned || 0).toLocaleString()}</p>
                    </div>
                </div>
            `;
        });
    }
    
    // Edit user modal
    const editUserModal = document.getElementById('editUserModal');
    if (editUserModal) {
        editUserModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userData = JSON.parse(button.getAttribute('data-user-data'));
            
            document.getElementById('edit_user_id').value = userData.id;
            document.getElementById('edit_username').value = userData.username;
            document.getElementById('edit_role').value = userData.role;
            document.getElementById('edit_green_points').value = userData.green_points;
        });
    }
});

function getRoleColor(role) {
    const colors = {
        'farmer': 'success',
        'planner': 'info',
        'admin': 'danger'
    };
    return colors[role] || 'secondary';
}

function deactivateUser(userId) {
    if (confirm('Are you sure you want to deactivate this user? This action can be reversed later.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="deactivate_user">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
