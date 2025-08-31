<?php
session_start();
require_once 'config/database.php';
require_once 'includes/NotificationHelper.php';

// Check if user is logged in and is a planner
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'planner') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$notificationHelper = new NotificationHelper($database);

// Handle approval/rejection actions
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && isset($_POST['farm_id'])) {
    $farm_id = $_POST['farm_id'];
    $action = $_POST['action'];
    $notes = $_POST['notes'] ?? '';
    
    if($action == 'approve') {
        // Get farm details for notification
        $farm_query = "SELECT f.name, f.farmer_id FROM farms f WHERE f.id = ?";
        $farm_stmt = $db->prepare($farm_query);
        $farm_stmt->execute([$farm_id]);
        $farm = $farm_stmt->fetch(PDO::FETCH_ASSOC);
        
        try {
            $update_query = "UPDATE farms SET status = 'approved', notes = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$notes, $farm_id]);
        } catch (PDOException $e) {
            // If notes column doesn't exist, update without notes
            $update_query = "UPDATE farms SET status = 'approved', updated_at = NOW() WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$farm_id]);
        }
        
        // Award green points to planner for approval
        $points_query = "UPDATE users SET green_points = green_points + 5 WHERE id = ?";
        $points_stmt = $db->prepare($points_query);
        $points_stmt->execute([$_SESSION['user_id']]);
        $_SESSION['green_points'] += 5;
        
        // Log the action
        $log_query = "INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([$_SESSION['user_id'], 'farm_approved', "Farm ID: $farm_id approved"]);
        
        // Send notifications
        $notificationHelper->notifyFarmApproval($farm_id, $farm['farmer_id'], $farm['name'], true);
        
        $success_message = "Farm approved successfully! +5 Green Points earned.";
    } elseif($action == 'reject') {
        // Get farm details for notification
        $farm_query = "SELECT f.name, f.farmer_id FROM farms f WHERE f.id = ?";
        $farm_stmt = $db->prepare($farm_query);
        $farm_stmt->execute([$farm_id]);
        $farm = $farm_stmt->fetch(PDO::FETCH_ASSOC);
        
        try {
            $update_query = "UPDATE farms SET status = 'rejected', notes = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$notes, $farm_id]);
        } catch (PDOException $e) {
            // If notes column doesn't exist, update without notes
            $update_query = "UPDATE farms SET status = 'rejected', updated_at = NOW() WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$farm_id]);
        }
        
        // Log the action
        $log_query = "INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([$_SESSION['user_id'], 'farm_rejected', "Farm ID: $farm_id rejected"]);
        
        // Send notifications
        $notificationHelper->notifyFarmApproval($farm_id, $farm['farmer_id'], $farm['name'], false);
        
        $success_message = "Farm rejected successfully.";
    }
}

// Get all farm requests
$farms_query = "SELECT f.*, u.username as farmer_name, u.email as farmer_email 
                FROM farms f 
                JOIN users u ON f.farmer_id = u.id 
                ORDER BY f.created_at DESC";
$farms_stmt = $db->prepare($farms_query);
$farms_stmt->execute();
$farms = $farms_stmt->fetchAll(PDO::FETCH_ASSOC);

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
                <a href="farm_approvals.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-check-circle me-2"></i>Farm Approvals
                </a>
                <a href="drone_approvals.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-drone me-2"></i>Drone Approvals
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
                <h2><i class="fas fa-check-circle me-2"></i>Farm Approvals</h2>
                <a href="planner_dashboard.php" class="btn btn-blue-color">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
            
            <?php if(isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Farm Requests Table -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-farm me-2"></i>All Farm Requests</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Farm Name</th>
                                    <th>Farmer</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($farms as $farm): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($farm['name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($farm['crops']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($farm['farmer_name']); ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($farm['farmer_email']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo ucfirst($farm['farm_type']); ?></span>
                                            <br>
                                            <small class="text-muted"><?php echo ucfirst($farm['soil_type']); ?> soil</small>
                                        </td>
                                        <td><?php echo htmlspecialchars($farm['location']); ?></td>
                                        <td>
                                            <?php if($farm['status'] == 'pending'): ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php elseif($farm['status'] == 'approved'): ?>
                                                <span class="badge bg-success">Approved</span>
                                            <?php elseif($farm['status'] == 'rejected'): ?>
                                                <span class="badge bg-danger">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($farm['created_at'])); ?></td>
                                        <td>
                                            <?php if($farm['status'] == 'pending'): ?>
                                                <button class="btn btn-sm btn-success" onclick="openApprovalModal(<?php echo $farm['id']; ?>, '<?php echo htmlspecialchars($farm['name']); ?>', 'approve')">
                                                    <i class="fas fa-check me-1"></i>Approve
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="openApprovalModal(<?php echo $farm['id']; ?>, '<?php echo htmlspecialchars($farm['name']); ?>', 'reject')">
                                                    <i class="fas fa-times me-1"></i>Reject
                                                </button>
                                            <?php else: ?>
                                                <small class="text-muted"><?php echo ucfirst($farm['status']); ?></small>
                                            <?php endif; ?>
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

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1" aria-labelledby="approvalModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="approvalForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="approvalModalLabel">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="farm_id" id="modalFarmId">
                    <input type="hidden" name="action" id="modalAction">
                    
                    <p>Are you sure you want to <span id="actionText"></span> <strong id="farmName"></strong>?</p>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Add any notes about this decision..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="confirmBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openApprovalModal(farmId, farmName, action) {
    document.getElementById('modalFarmId').value = farmId;
    document.getElementById('modalAction').value = action;
    document.getElementById('farmName').textContent = farmName;
    document.getElementById('actionText').textContent = action === 'approve' ? 'approve' : 'reject';
    
    const confirmBtn = document.getElementById('confirmBtn');
    if (action === 'approve') {
        confirmBtn.className = 'btn btn-success';
        confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i>Approve';
    } else {
        confirmBtn.className = 'btn btn-danger';
        confirmBtn.innerHTML = '<i class="fas fa-times me-1"></i>Reject';
    }
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('approvalModal'));
    modal.show();
}

// Handle form submission
document.getElementById('approvalForm').addEventListener('submit', function(e) {
    const confirmBtn = document.getElementById('confirmBtn');
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';
});

// Auto-hide success message after 5 seconds
setTimeout(function() {
    const alert = document.querySelector('.alert-success');
    if (alert) {
        alert.style.display = 'none';
    }
}, 5000);
</script>

<?php include 'includes/footer.php'; ?>


