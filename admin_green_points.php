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

$success_message = '';
$error_message = '';

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['adjust_points'])) {
        $user_id = (int)$_POST['user_id'];
        $adjustment_type = $_POST['adjustment_type'];
        $points = (int)$_POST['points'];
        $reason = trim($_POST['reason']);
        
        if ($points <= 0) {
            $error_message = "Points must be greater than 0.";
        } elseif (empty($reason)) {
            $error_message = "Reason is required for point adjustment.";
        } else {
            try {
                $db->beginTransaction();
                
                // Get current user points
                $user_query = "SELECT green_points, username FROM users WHERE id = ?";
                $user_stmt = $db->prepare($user_query);
                $user_stmt->execute([$user_id]);
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $new_points = $adjustment_type == 'add' ? $user['green_points'] + $points : $user['green_points'] - $points;
                    
                    // Ensure points don't go below 0
                    if ($new_points < 0) {
                        $new_points = 0;
                    }
                    
                    // Update user points
                    $update_query = "UPDATE users SET green_points = ? WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([$new_points, $user_id]);
                    
                    // Log the transaction
                    $transaction_query = "INSERT INTO green_points_transactions (user_id, transaction_type, amount, description, related_entity_type, related_entity_id) VALUES (?, ?, ?, ?, 'admin_adjustment', ?)";
                    $transaction_stmt = $db->prepare($transaction_query);
                    $transaction_type = $adjustment_type == 'add' ? 'earned' : 'spent';
                    $description = "Admin adjustment: " . ($adjustment_type == 'add' ? '+' : '-') . $points . " points - " . $reason;
                    $transaction_stmt->execute([$user_id, $transaction_type, $points, $description, $_SESSION['user_id']]);
                    
                    // Log admin action
                    $log_query = "INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->execute([$_SESSION['user_id'], 'green_points_adjustment', "Adjusted points for user {$user['username']}: {$adjustment_type} {$points} points. Reason: {$reason}"]);
                    
                    $db->commit();
                    $success_message = "Successfully adjusted " . ($adjustment_type == 'add' ? 'added' : 'subtracted') . " {$points} points for user {$user['username']}.";
                } else {
                    $error_message = "User not found.";
                }
            } catch (Exception $e) {
                $db->rollback();
                $error_message = "Error adjusting points: " . $e->getMessage();
            }
        }
    }
}

// Get system-wide statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_users,
        SUM(green_points) as total_points,
        AVG(green_points) as avg_points,
        MAX(green_points) as max_points,
        COUNT(CASE WHEN green_points > 0 THEN 1 END) as active_users
    FROM users";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$system_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get top users by green points
$top_users_query = "
    SELECT id, username, role, green_points, created_at
    FROM users 
    ORDER BY green_points DESC 
    LIMIT 10";
$top_users_stmt = $db->prepare($top_users_query);
$top_users_stmt->execute();
$top_users = $top_users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent transactions
$recent_transactions_query = "
    SELECT gpt.*, u.username, u.role
    FROM green_points_transactions gpt
    JOIN users u ON gpt.user_id = u.id
    ORDER BY gpt.created_at DESC 
    LIMIT 20";
$transactions_stmt = $db->prepare($recent_transactions_query);
$transactions_stmt->execute();
$recent_transactions = $transactions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get points breakdown by activity type
$activity_breakdown_query = "
    SELECT 
        related_entity_type,
        SUM(CASE WHEN transaction_type = 'earned' THEN amount ELSE 0 END) as total_earned,
        SUM(CASE WHEN transaction_type = 'spent' THEN amount ELSE 0 END) as total_spent,
        COUNT(*) as transaction_count
    FROM green_points_transactions 
    GROUP BY related_entity_type
    ORDER BY total_earned DESC";
$breakdown_stmt = $db->prepare($activity_breakdown_query);
$breakdown_stmt->execute();
$activity_breakdown = $breakdown_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for point adjustment
$all_users_query = "SELECT id, username, role, green_points FROM users ORDER BY username";
$all_users_stmt = $db->prepare($all_users_query);
$all_users_stmt->execute();
$all_users = $all_users_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <div class="sidebar">
            <h5><i class="fas fa-star me-2"></i>Green Points Admin</h5>
            <hr>
            <div class="list-group">
                <a href="admin_green_points.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-chart-line me-2"></i>Points Overview
                </a>
                <a href="admin_dashboard.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
                <a href="seed_approvals.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-seedling me-2"></i>Seed Approvals
                </a>
                <a href="user_management.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-users me-2"></i>User Management
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-star me-2"></i>Green Points Administration</h2>
                <button class="btn btn-blue-color" data-bs-toggle="modal" data-bs-target="#adjustPointsModal">
                    <i class="fas fa-plus me-2"></i>Adjust Points
                </button>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <!-- System Statistics -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo number_format($system_stats['total_users']); ?></h4>
                            <p class="mb-0 text-color">Total Users</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-star fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo number_format($system_stats['total_points']); ?></h4>
                            <p class="mb-0 text-color">Total Points</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-bar fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo number_format($system_stats['avg_points'], 1); ?></h4>
                            <p class="mb-0 text-color">Avg Points</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-trophy fa-2x mb-2 text-color "></i>
                            <h4 class="text-color"><?php echo number_format($system_stats['max_points']); ?></h4>
                            <p class="mb-0 text-color">Max Points</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-user-check fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo number_format($system_stats['active_users']); ?></h4>
                            <p class="mb-0 text-color">Active Users</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-percentage fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo $system_stats['total_users'] > 0 ? round(($system_stats['active_users'] / $system_stats['total_users']) * 100, 1) : 0; ?>%</h4>
                            <p class="mb-0 text-color">Engagement</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Users and Activity Breakdown -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-trophy me-2"></i>Top Users by Green Points</h5>
                        </div>
                        <div class="card-body">
                            <?php if(empty($top_users)): ?>
                                <p class="text-muted">No users found.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Rank</th>
                                                <th>User</th>
                                                <th>Role</th>
                                                <th>Points</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($top_users as $index => $user): ?>
                                                <tr>
                                                    <td>
                                                        <?php if($index == 0): ?>
                                                            <span class="badge bg-warning">🥇</span>
                                                        <?php elseif($index == 1): ?>
                                                            <span class="badge bg-secondary">🥈</span>
                                                        <?php elseif($index == 2): ?>
                                                            <span class="badge bg-warning">🥉</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-light text-dark"><?php echo $index + 1; ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo ucfirst($user['role']); ?></span>
                                                    </td>
                                                    <td>
                                                        <strong class="text-success"><?php echo number_format($user['green_points']); ?></strong>
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
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-pie me-2"></i>Points by Activity Type</h5>
                        </div>
                        <div class="card-body">
                            <?php if(empty($activity_breakdown)): ?>
                                <p class="text-muted">No activity data available.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Activity</th>
                                                <th>Earned</th>
                                                <th>Spent</th>
                                                <th>Count</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($activity_breakdown as $activity): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-primary">
                                                            <?php echo ucfirst(str_replace('_', ' ', $activity['related_entity_type'])); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-success">
                                                        <i class="fas fa-plus me-1"></i><?php echo number_format($activity['total_earned']); ?>
                                                    </td>
                                                    <td class="text-danger">
                                                        <i class="fas fa-minus me-1"></i><?php echo number_format($activity['total_spent']); ?>
                                                    </td>
                                                    <td><?php echo number_format($activity['transaction_count']); ?></td>
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
            
            <!-- Recent Transactions -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list me-2"></i>Recent Green Points Transactions</h5>
                </div>
                <div class="card-body">
                    <?php if(empty($recent_transactions)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-star fa-3x text-muted mb-3"></i>
                            <h4>No transactions yet</h4>
                            <p class="text-muted">No green points transactions have been recorded yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="transactionsTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>User</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Activity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recent_transactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <div><?php echo date('M j, Y', strtotime($transaction['created_at'])); ?></div>
                                                    <small class="text-muted"><?php echo date('g:i A', strtotime($transaction['created_at'])); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($transaction['username']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">@<?php echo htmlspecialchars($transaction['username']); ?></small>
                                                    <span class="badge bg-primary ms-1"><?php echo ucfirst($transaction['role']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if($transaction['transaction_type'] == 'earned'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-plus me-1"></i>Earned
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-minus me-1"></i>Spent
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                            <td>
                                                <span class="<?php echo $transaction['transaction_type'] == 'earned' ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo $transaction['transaction_type'] == 'earned' ? '+' : '-'; ?>
                                                    <?php echo $transaction['amount']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo ucfirst(str_replace('_', ' ', $transaction['related_entity_type'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Export Options -->
                        <div class="mt-3">
                            <button class="btn btn-outline-primary btn-sm" onclick="exportTransactions()">
                                <i class="fas fa-download me-1"></i>Export Transactions
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="printTransactions()">
                                <i class="fas fa-print me-1"></i>Print Report
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Adjust Points Modal -->
<div class="modal fade" id="adjustPointsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-star me-2"></i>Adjust User Points</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="user_id" class="form-label">Select User</label>
                        <select class="form-select" id="user_id" name="user_id" required>
                            <option value="">Choose a user...</option>
                            <?php foreach($all_users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username']); ?> 
                                    (@<?php echo htmlspecialchars($user['username']); ?>) - 
                                    <?php echo ucfirst($user['role']); ?> - 
                                    <?php echo $user['green_points']; ?> points
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="adjustment_type" class="form-label">Adjustment Type</label>
                        <select class="form-select" id="adjustment_type" name="adjustment_type" required>
                            <option value="add">Add Points</option>
                            <option value="subtract">Subtract Points</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="points" class="form-label">Points Amount</label>
                        <input type="number" class="form-control" id="points" name="points" min="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Adjustment</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" 
                                  placeholder="Please provide a reason for this point adjustment..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="adjust_points" class="btn btn-blue-color">
                        <i class="fas fa-save me-2"></i>Adjust Points
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Export transactions to CSV
function exportTransactions() {
    const table = document.getElementById('transactionsTable');
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    
    let csv = 'Date,User,Type,Description,Amount,Activity\n';
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const rowData = [];
        
        cells.forEach((cell, index) => {
            let text = cell.textContent.trim();
            text = text.replace(/,/g, ';').replace(/\n/g, ' ');
            rowData.push('"' + text + '"');
        });
        
        csv += rowData.join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'green_points_transactions_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Print transactions
function printTransactions() {
    window.print();
}

// Add search functionality
document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('transactionsTable');
    if (table) {
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'form-control mb-3';
        searchInput.placeholder = 'Search transactions...';
        searchInput.id = 'searchTransactions';
        
        table.parentNode.insertBefore(searchInput, table);
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
});
</script>

<style>
@media print {
    .sidebar, .btn, .card-header, .modal {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
