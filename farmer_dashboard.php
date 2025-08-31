<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is a farmer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'farmer') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get farmer's farms
$farms_query = "SELECT * FROM farms WHERE farmer_id = ? ORDER BY created_at DESC";
$farms_stmt = $db->prepare($farms_query);
$farms_stmt->execute([$_SESSION['user_id']]);
$farms = $farms_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending drone requests
$drone_requests_query = "SELECT dr.*, f.name as farm_name FROM drone_requests dr 
                        JOIN farms f ON dr.farm_id = f.id 
                        WHERE dr.farmer_id = ? ORDER BY dr.created_at DESC LIMIT 5";
$drone_requests_stmt = $db->prepare($drone_requests_query);
$drone_requests_stmt->execute([$_SESSION['user_id']]);
$drone_requests = $drone_requests_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get IoT devices count
$iot_count_query = "SELECT COUNT(*) as count FROM iot_devices i 
                    JOIN farms f ON i.farm_id = f.id 
                    WHERE f.farmer_id = ?";
$iot_count_stmt = $db->prepare($iot_count_query);
$iot_count_stmt->execute([$_SESSION['user_id']]);
$iot_count = $iot_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get recent AI recommendations
$ai_recommendations_query = "SELECT * FROM ai_recommendations 
                            WHERE user_id = ? ORDER BY created_at DESC LIMIT 3";
$ai_recommendations_stmt = $db->prepare($ai_recommendations_query);
$ai_recommendations_stmt->execute([$_SESSION['user_id']]);
$ai_recommendations = $ai_recommendations_stmt->fetchAll(PDO::FETCH_ASSOC);

// Check for new drone results (results created in the last 24 hours)
$new_results_count = 0;
try {
    $new_results_query = "SELECT COUNT(*) as count FROM drone_results dr
                          JOIN drone_requests drq ON dr.drone_request_id = drq.id
                          WHERE drq.farmer_id = ? 
                          AND dr.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $new_results_stmt = $db->prepare($new_results_query);
    $new_results_stmt->execute([$_SESSION['user_id']]);
    $new_results_count = $new_results_stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    // If drone_results table doesn't exist, set count to 0
    $new_results_count = 0;
    // Optionally log the error for debugging
    error_log("Drone results table not found: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <div class="sidebar">
            <h5><i class="fas fa-user-farmer me-2"></i>Farmer Dashboard</h5>
            <hr>
            <div class="mb-3">
                <strong>Welcome, <?php echo $_SESSION['username']; ?>!</strong>
                <div class="green-points mt-2">
                    <i class="fas fa-star me-1"></i><?php echo $_SESSION['green_points']; ?> Green Points
                </div>
            </div>
            
            <div class="list-group">
                <a href="farm_requests.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-farm me-2"></i>My Farms
                </a>
                <a href="drone_requests.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-drone me-2"></i>Drone Requests
                </a>
                <a href="farmer_drone_results.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-chart-line me-2"></i>Drone Results
                    <?php if($new_results_count > 0): ?>
                        <span class="badge bg-success ms-auto"><?php echo $new_results_count; ?> new</span>
                    <?php endif; ?>
                </a>
                <a href="iot_monitoring.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-microchip me-2"></i>IoT Monitoring
                </a>
                <a href="marketplace.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-store me-2"></i>Marketplace
                </a>
                <a href="ai_recommendations.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-brain me-2"></i>AI Recommendations
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="main-content">
            <?php if($new_results_count > 0): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-bell me-2"></i>
                    <strong>New Drone Results Available!</strong> You have <?php echo $new_results_count; ?> new drone operation result<?php echo $new_results_count > 1 ? 's' : ''; ?> based on sensor data from your farms. 
                    <a href="farmer_drone_results.php" class="alert-link">View Results</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <!-- My Farms -->
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card dashboard-stats h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-tractor fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo count($farms); ?></h4>
                            <p class="mb-0 text-color">My Farms</p>
                        </div>
                    </div>
                </div>

                <!-- Drone Requests -->
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card dashboard-stats h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-drone fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo count($drone_requests); ?></h4>
                            <p class="mb-0 text-color">Drone Requests</p>
                        </div>
                    </div>
                </div>

                <!-- IoT Devices -->
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card dashboard-stats h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-microchip fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo $iot_count; ?></h4>
                            <p class="mb-0 text-color">IoT Devices</p>
                        </div>
                    </div>
                </div>

                <!-- Green Points -->
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card dashboard-stats h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-star fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo $_SESSION['green_points']; ?></h4>
                            <p class="mb-0 text-color">Green Points</p>
                        </div>
                    </div>
                </div>
            </div>

            
            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <!-- Create New Farm -->
                                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                    <a href="create_farm.php" class="btn btn-blue-color w-100">
                                        <i class="fas fa-plus me-2"></i>Create Farm
                                    </a>
                                </div>

                                <!-- Request Drone -->
                                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                    <a href="request_drone.php" class="btn btn-info w-100">
                                        <i class="fas fa-drone me-2"></i>Drone
                                    </a>
                                </div>

                                <!-- Marketplace -->
                                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                    <a href="marketplace.php" class="btn btn-warning w-100">
                                        <i class="fas fa-store me-2"></i>Marketplace
                                    </a>
                                </div>

                                <!-- IoT Monitoring -->
                                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                    <a href="iot_monitoring.php" class="btn btn-secondary w-100">
                                        <i class="fas fa-microchip me-2"></i>IoT
                                    </a>
                                </div>

                                <!-- Drone Results -->
                                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                    <a href="farmer_drone_results.php" class="btn btn-success w-100">
                                        <i class="fas fa-chart-line me-2"></i>Results
                                        <?php if($new_results_count > 0): ?>
                                            <span class="badge bg-light text-dark ms-1"><?php echo $new_results_count; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>

                                <!-- Purchase History -->
                                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                    <a href="purchase_history.php" class="btn btn-dark w-100">
                                        <i class="fas fa-history me-2"></i>History
                                    </a>
                                </div>

                                <!-- Green Points -->
                                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                    <a href="green_points.php" class="btn btn-success w-100">
                                        <i class="fas fa-star me-2"></i>Points
                                    </a>
                                </div>

                                <!-- Profile -->
                                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                    <a href="profile.php" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-user me-2"></i>Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            
            <!-- My Farms -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-farm me-2"></i>My Farms</h5>
                            <a href="farm_requests.php" class="btn btn-sm btn-blue-color">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if(empty($farms)): ?>
                                <p class="text-muted">No farms yet. <a href="create_farm.php">Create your first farm</a></p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Farm Name</th>
                                                <th>Type</th>
                                                <th>Location</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach(array_slice($farms, 0, 3) as $farm): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($farm['name']); ?></td>
                                                    <td><?php echo ucfirst($farm['farm_type']); ?></td>
                                                    <td><?php echo htmlspecialchars($farm['location']); ?></td>
                                                    <td>
                                                        <span class="status-badge status-<?php echo $farm['status']; ?>">
                                                            <?php echo ucfirst($farm['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="view_farm.php?id=<?php echo $farm['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
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
            
            <!-- Recent Drone Requests -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-drone me-2"></i>Recent Drone Requests</h5>
                            <a href="drone_requests.php" class="btn btn-sm btn-blue-color">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if(empty($drone_requests)): ?>
                                <p class="text-muted">No drone requests yet.</p>
                            <?php else: ?>
                                <?php foreach($drone_requests as $request): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <strong><?php echo htmlspecialchars($request['farm_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo ucfirst($request['purpose']); ?></small>
                                        </div>
                                        <span class="status-badge status-<?php echo $request['status']; ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- AI Recommendations -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-brain me-2"></i>AI Recommendations</h5>
                        </div>
                        <div class="card-body">
                            <?php if(empty($ai_recommendations)): ?>
                                <p class="text-muted">No AI recommendations yet.</p>
                            <?php else: ?>
                                <?php foreach($ai_recommendations as $rec): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <strong><?php echo ucfirst($rec['recommendation_type']); ?></strong>
                                            <small class="text-muted"><?php echo date('M j', strtotime($rec['created_at'])); ?></small>
                                        </div>
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
        </div>
    </div>
</div>

<style>
.badge {
    font-size: 0.75rem;
}
.alert-link {
    text-decoration: underline;
}
</style>

<?php include 'includes/footer.php'; ?>
