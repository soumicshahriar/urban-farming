<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is a planner
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'planner') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get pending farm requests
$pending_farms_query = "SELECT f.*, u.username as farmer_name 
                       FROM farms f 
                       JOIN users u ON f.farmer_id = u.id 
                       WHERE f.status = 'pending' 
                       ORDER BY f.created_at ASC";
$pending_farms_stmt = $db->prepare($pending_farms_query);
$pending_farms_stmt->execute();
$pending_farms = $pending_farms_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending drone requests
$pending_drones_query = "SELECT dr.*, f.name as farm_name, u.username as farmer_name 
                        FROM drone_requests dr 
                        JOIN farms f ON dr.farm_id = f.id 
                        JOIN users u ON dr.farmer_id = u.id 
                        WHERE dr.status = 'pending' 
                        ORDER BY dr.created_at ASC";
$pending_drones_stmt = $db->prepare($pending_drones_query);
$pending_drones_stmt->execute();
$pending_drones = $pending_drones_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get drone inventory
$drone_inventory_query = "SELECT drone_type, status, COUNT(*) as count 
                         FROM drones 
                         GROUP BY drone_type, status";
$drone_inventory_stmt = $db->prepare($drone_inventory_query);
$drone_inventory_stmt->execute();
$drone_inventory = $drone_inventory_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent AI recommendations
$ai_recommendations_query = "SELECT * FROM ai_recommendations 
                            WHERE recommendation_type IN ('drone_type', 'timing') 
                            ORDER BY created_at DESC LIMIT 5";
$ai_recommendations_stmt = $db->prepare($ai_recommendations_query);
$ai_recommendations_stmt->execute();
$ai_recommendations = $ai_recommendations_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_farms = count($pending_farms);
$total_drone_requests = count($pending_drones);
$total_drones = array_sum(array_column($drone_inventory, 'count'));
$available_drones = array_sum(array_column(array_filter($drone_inventory, function($item) {
    return $item['status'] == 'available';
}), 'count'));

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
                <a href="planner_dashboard.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
                <a href="farm_approvals.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-check-circle me-2"></i>Farm Approvals
                </a>
                <a href="drone_approvals.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-drone me-2"></i>Drone Approvals
                </a>
                <a href="auto_drone_results.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-robot me-2"></i>Auto Results
                </a>
                <a href="drone_inventory.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-boxes me-2"></i>Drone Inventory
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="main-content">
            <!-- Stats Cards -->
            <div class="row g-3">
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card dashboard-stats h-100 shadow-sm border-0 hover-card">
                        <div class="card-body text-center">
                            <i class="fas fa-tractor fa-2x mb-2 text-color"></i>
                            <h4 class="fw-bold mb-1 text-color"><?php echo $total_farms; ?></h4>
                            <p class="mb-0 text-color">Pending Farms</p>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card dashboard-stats h-100 shadow-sm border-0 hover-card">
                        <div class="card-body text-center">
                            <i class="fas fa-drone fa-2x mb-2 text-color"></i>
                            <h4 class="fw-bold mb-1 text-color"><?php echo $total_drone_requests; ?></h4>
                            <p class="mb-0 text-color">Pending Drone Requests</p>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card dashboard-stats h-100 shadow-sm border-0 hover-card">
                        <div class="card-body text-center">
                            <i class="fas fa-boxes fa-2x mb-2 text-color"></i>
                            <h4 class="fw-bold mb-1 text-color"><?php echo $total_drones; ?></h4>
                            <p class="mb-0 text-color">Total Drones</p>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card dashboard-stats h-100 shadow-sm border-0 hover-card">
                        <div class="card-body text-center">
                            <i class="fas fa-star fa-2x mb-2 text-color"></i>
                            <h4 class="fw-bold mb-1 text-color"><?php echo $_SESSION['green_points']; ?></h4>
                            <p class="mb-0 text-color">Green Points</p>
                        </div>
                    </div>
                </div>
            </div>

<!-- Hover effect -->
<style>
    .hover-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .hover-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 6px 18px rgba(0,0,0,0.12);
    }
</style>

            
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
                                    <a href="farm_approvals.php" class="btn btn-blue-color w-100 mb-2">
                                        <i class="fas fa-check-circle me-2"></i>Review Farms
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="drone_approvals.php" class="btn btn-blue-color w-100 mb-2">
                                        <i class="fas fa-drone me-2"></i>Review Drones
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="drone_inventory.php" class="btn btn-blue-color w-100 mb-2">
                                        <i class="fas fa-boxes me-2"></i>Manage Inventory
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="ai_recommendations.php" class="btn btn-blue-color w-100 mb-2">
                                        <i class="fas fa-brain me-2"></i>AI Insights
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pending Farm Requests -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-farm me-2 "></i>Pending Farm Requests</h5>
                            <a href="farm_approvals.php" class="btn btn-sm btn-blue-color">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if(empty($pending_farms)): ?>
                                <p class="text-muted">No pending farm requests.</p>
                            <?php else: ?>
                                <?php foreach(array_slice($pending_farms, 0, 3) as $farm): ?>
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?php echo htmlspecialchars($farm['name']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($farm['farmer_name']); ?> • 
                                                    <?php echo ucfirst($farm['farm_type']); ?>
                                                </small>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('M j', strtotime($farm['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Drone Requests -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-drone me-2"></i>Pending Drone Requests</h5>
                            <a href="drone_approvals.php" class="btn btn-sm btn-blue-color">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if(empty($pending_drones)): ?>
                                <p class="text-muted">No pending drone requests.</p>
                            <?php else: ?>
                                <?php foreach(array_slice($pending_drones, 0, 3) as $drone): ?>
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?php echo htmlspecialchars($drone['farm_name']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($drone['farmer_name']); ?> • 
                                                    <?php echo ucfirst(str_replace('_', ' ', $drone['purpose'])); ?>
                                                </small>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('M j', strtotime($drone['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Drone Inventory Overview -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-boxes me-2"></i>Drone Inventory Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php 
                                $drone_types = ['survey', 'spraying', 'monitoring', 'biological'];
                                foreach($drone_types as $type):
                                    $type_drones = array_filter($drone_inventory, function($d) use ($type) { 
                                        return $d['drone_type'] == $type; 
                                    });
                                ?>
                                    <div class="col-md-3 mb-3">
                                        <div class="text-center">
                                            <i class="fas fa-drone fa-2x text-primary mb-2"></i>
                                            <h6><?php echo ucfirst($type); ?> Drones</h6>
                                            <?php
                                            $available = 0;
                                            $total = 0;
                                            foreach($type_drones as $drone) {
                                                $total += $drone['count'];
                                                if($drone['status'] == 'available') {
                                                    $available += $drone['count'];
                                                }
                                            }
                                            ?>
                                            <div class="h5 mb-0"><?php echo $available; ?>/<?php echo $total; ?></div>
                                            <small class="text-muted">Available</small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- AI Recommendations -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-brain me-2"></i>AI Recommendations</h5>
                        </div>
                        <div class="card-body">
                            <?php if(empty($ai_recommendations)): ?>
                                <p class="text-muted">No AI recommendations yet.</p>
                            <?php else: ?>
                                <?php foreach($ai_recommendations as $rec): ?>
                                    <div class="mb-3 p-2 border-start border-primary">
                                        <small class="text-muted"><?php echo ucfirst($rec['recommendation_type']); ?></small>
                                        <p class="mb-1"><?php echo htmlspecialchars($rec['recommendation_text']); ?></p>
                                        <?php if($rec['green_points_earned'] > 0): ?>
                                            <small class="text-success">
                                                <i class="fas fa-star me-1"></i>+<?php echo $rec['green_points_earned']; ?> Green Points
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-history me-2"></i>Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Action</th>
                                            <th>Details</th>
                                            <th>User</th>
                                            <th>Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><span class="badge bg-success">Farm Approved</span></td>
                                            <td>Green Valley Farm - Vegetable</td>
                                            <td>john_farmer</td>
                                            <td>2 hours ago</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-primary">Drone Assigned</span></td>
                                            <td>Survey Drone 1 → Farm A</td>
                                            <td>jane_farmer</td>
                                            <td>4 hours ago</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-warning">Maintenance</span></td>
                                            <td>Spraying Drone 2 scheduled</td>
                                            <td>System</td>
                                            <td>6 hours ago</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
