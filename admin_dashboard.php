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

// Get system statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM users WHERE role = 'farmer') as total_farmers,
                (SELECT COUNT(*) FROM users WHERE role = 'planner') as total_planners,
                (SELECT COUNT(*) FROM farms WHERE status = 'approved') as approved_farms,
                (SELECT COUNT(*) FROM farms WHERE status = 'pending') as pending_farms,
                (SELECT COUNT(*) FROM drone_requests WHERE status = 'completed') as completed_drones,
                (SELECT COUNT(*) FROM drone_requests WHERE status = 'pending') as pending_drones,
                (SELECT COUNT(*) FROM drones WHERE status = 'available') as available_drones,
                (SELECT COUNT(*) FROM seed_listings WHERE status = 'sold') as seed_transactions";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent system logs
$logs_query = "SELECT sl.*, u.username 
               FROM system_logs sl 
               LEFT JOIN users u ON sl.user_id = u.id 
               ORDER BY sl.created_at DESC 
               LIMIT 10";
$logs_stmt = $db->prepare($logs_query);
$logs_stmt->execute();
$logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get Green Points leaderboard
$leaderboard_query = "SELECT username, green_points, role 
                     FROM users 
                     ORDER BY green_points DESC 
                     LIMIT 10";
$leaderboard_stmt = $db->prepare($leaderboard_query);
$leaderboard_stmt->execute();
$leaderboard = $leaderboard_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent AI recommendations
$ai_recommendations_query = "SELECT ar.*, u.username 
                            FROM ai_recommendations ar 
                            JOIN users u ON ar.user_id = u.id 
                            ORDER BY ar.created_at DESC 
                            LIMIT 5";
$ai_recommendations_stmt = $db->prepare($ai_recommendations_query);
$ai_recommendations_stmt->execute();
$ai_recommendations = $ai_recommendations_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <div class="sidebar">
            <h5><i class="fas fa-shield-alt me-2"></i>Admin Dashboard</h5>
            <hr>
            <div class="mb-3">
                <strong>Welcome, <?php echo $_SESSION['username']; ?>!</strong>
                
            </div>
            
            <div class="list-group">
                <a href="admin_dashboard.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
                <a href="global_monitoring.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-globe me-2"></i>Global Monitoring
                </a>
                <a href="user_management.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-users me-2"></i>User Management
                </a>
                <a href="system_logs.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-list me-2"></i>System Logs
                </a>
                <a href="seed_approvals.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-seedling me-2"></i>Seed Approvals
                </a>
                <a href="admin_green_points.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-star me-2"></i>Green Points
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="main-content">
            <!-- System Overview Stats -->
            <div class="row g-4">
    <!-- Total Users -->
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card dashboard-stats shadow-sm border-0 h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo $stats['total_farmers'] + $stats['total_planners']; ?></h4>
                            <p class="mb-1 text-color">Total Users</p>
                            <small class="text-color">
                                <?php echo $stats['total_farmers']; ?> Farmers, <?php echo $stats['total_planners']; ?> Planners
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Active Farms -->
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card dashboard-stats shadow-sm border-0 h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-farm fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo $stats['approved_farms']; ?></h4>
                            <p class="mb-1 text-color">Active Farms</p>
                            <small class="text-color"><?php echo $stats['pending_farms']; ?> Pending</small>
                        </div>
                    </div>
                </div>

                <!-- Drone Missions -->
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card dashboard-stats shadow-sm border-0 h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-drone fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo $stats['completed_drones']; ?></h4>
                            <p class="mb-1 text-color">Drone Missions</p>
                            <small class="text-color"><?php echo $stats['pending_drones']; ?> Pending</small>
                        </div>
                    </div>
                </div>

                <!-- Seed Sales -->
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card dashboard-stats shadow-sm border-0 h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-seedling fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo $stats['seed_transactions']; ?></h4>
                            <p class="mb-1 text-color">Seed Sales</p>
                            <small class="text-color">Marketplace Activity</small>
                        </div>
                    </div>
                </div>
            </div>

            
            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <a href="user_management.php" class="btn btn-blue-color w-100 mb-2">
                                        <i class="fas fa-users me-2"></i>Manage Users
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="global_monitoring.php" class="btn btn-success w-100 mb-2">
                                        <i class="fas fa-globe me-2"></i>System Monitor
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="system_logs.php" class="btn btn-info w-100 mb-2">
                                        <i class="fas fa-list me-2"></i>View Logs
                                    </a>
                                </div>
                                                                 <div class="col-md-3">
                                     <a href="admin_green_points.php" class="btn btn-warning w-100 mb-2">
                                         <i class="fas fa-star me-2"></i>Green Points
                                     </a>
                                 </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Health and AI Insights -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-heartbeat me-2"></i>System Health</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>Database Performance</span>
                                            <span class="text-success">Excellent</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" style="width: 95%"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>IoT Device Uptime</span>
                                            <span class="text-success">98.5%</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" style="width: 98.5%"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>Drone Fleet Status</span>
                                            <span class="text-warning">85%</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-warning" style="width: 85%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>User Satisfaction</span>
                                            <span class="text-success">4.8/5</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" style="width: 96%"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>Green Points Distribution</span>
                                            <span class="text-info">Balanced</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-info" style="width: 88%"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>AI Recommendation Accuracy</span>
                                            <span class="text-success">92%</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" style="width: 92%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-brain me-2"></i>AI Insights</h5>
                        </div>
                        <div class="card-body">
                            <?php if(empty($ai_recommendations)): ?>
                                <p class="text-muted">No AI insights available.</p>
                            <?php else: ?>
                                <?php foreach($ai_recommendations as $rec): ?>
                                    <div class="mb-3 p-2 border-start border-primary">
                                        <small class="text-muted"><?php echo htmlspecialchars($rec['username']); ?></small>
                                        <p class="mb-1"><?php echo htmlspecialchars($rec['recommendation_text']); ?></p>
                                        <small class="text-muted"><?php echo date('M j, g:i A', strtotime($rec['created_at'])); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Green Points Leaderboard and Recent Activity -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-trophy me-2"></i>Green Points Leaderboard</h5>
                        </div>
                        <div class="card-body">
                            <?php if(empty($leaderboard)): ?>
                                <p class="text-muted">No leaderboard data available.</p>
                            <?php else: ?>
                                <?php foreach($leaderboard as $index => $user): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-<?php echo $index < 3 ? 'warning' : 'secondary'; ?> me-2">
                                                #<?php echo $index + 1; ?>
                                            </span>
                                            <div>
                                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo ucfirst($user['role']); ?></small>
                                            </div>
                                        </div>
                                        <div class="green-points">
                                            <i class="fas fa-star me-1"></i><?php echo $user['green_points']; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-history me-2"></i>Recent System Activity</h5>
                        </div>
                        <div class="card-body">
                            <?php if(empty($logs)): ?>
                                <p class="text-muted">No recent activity.</p>
                            <?php else: ?>
                                <?php foreach($logs as $log): ?>
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($log['username'] ?? 'System'); ?> • 
                                                    <?php echo htmlspecialchars($log['details']); ?>
                                                </small>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('M j, g:i A', strtotime($log['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Alerts -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-exclamation-triangle me-2"></i>System Alerts</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>System Update:</strong> All systems are operating normally. No critical issues detected.
                            </div>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Maintenance Notice:</strong> Drone fleet maintenance scheduled for tomorrow at 2 AM.
                            </div>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Performance:</strong> Green Points system is functioning optimally with 98% user satisfaction.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
