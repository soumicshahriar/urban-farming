<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if farm ID is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . ($_SESSION['role'] == 'farmer' ? 'farmer_dashboard.php' : 'planner_dashboard.php'));
    exit();
}

$farm_id = $_GET['id'];
$database = new Database();
$db = $database->getConnection();

// Get farm details
$farm_query = "SELECT f.*, u.username as farmer_name, u.email as farmer_email, 
                      approver.username as approver_name
               FROM farms f 
               JOIN users u ON f.farmer_id = u.id 
               LEFT JOIN users approver ON f.approved_by = approver.id
               WHERE f.id = ?";
$farm_stmt = $db->prepare($farm_query);
$farm_stmt->execute([$farm_id]);
$farm = $farm_stmt->fetch(PDO::FETCH_ASSOC);

if(!$farm) {
    header('Location: ' . ($_SESSION['role'] == 'farmer' ? 'farmer_dashboard.php' : 'planner_dashboard.php'));
    exit();
}

// Check if user has permission to view this farm
if($_SESSION['role'] == 'farmer' && $farm['farmer_id'] != $_SESSION['user_id']) {
    header('Location: farmer_dashboard.php');
    exit();
}

// Get IoT devices for this farm
$iot_query = "SELECT * FROM iot_devices WHERE farm_id = ? ORDER BY device_type, device_name";
$iot_stmt = $db->prepare($iot_query);
$iot_stmt->execute([$farm_id]);
$iot_devices = $iot_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent IoT readings
$readings_query = "SELECT ir.*, id.device_name, id.device_type 
                  FROM iot_readings ir 
                  JOIN iot_devices id ON ir.device_id = id.id 
                  WHERE id.farm_id = ? 
                  ORDER BY ir.timestamp DESC 
                  LIMIT 20";
$readings_stmt = $db->prepare($readings_query);
$readings_stmt->execute([$farm_id]);
$recent_readings = $readings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get drone requests for this farm
$drone_requests_query = "SELECT dr.*, d.name as drone_name, approver.username as approver_name
                        FROM drone_requests dr 
                        LEFT JOIN drones d ON dr.drone_id = d.id
                        LEFT JOIN users approver ON dr.approved_by = approver.id
                        WHERE dr.farm_id = ? 
                        ORDER BY dr.created_at DESC";
$drone_requests_stmt = $db->prepare($drone_requests_query);
$drone_requests_stmt->execute([$farm_id]);
$drone_requests = $drone_requests_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get AI recommendations for this farm
$ai_query = "SELECT ar.* FROM ai_recommendations ar 
             JOIN users u ON ar.user_id = u.id 
             WHERE u.id = ? AND ar.recommendation_type IN ('irrigation', 'pest_control')
             ORDER BY ar.created_at DESC LIMIT 5";
$ai_stmt = $db->prepare($ai_query);
$ai_stmt->execute([$farm['farmer_id']]);
$ai_recommendations = $ai_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <div class="sidebar">
                <h5><i class="fas fa-farm me-2"></i>Farm Details</h5>
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
                            <i class="fas fa-drone me-2"></i>Request Drone
                        </a>
                        <a href="iot_monitoring.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-sensor me-2"></i>IoT Monitoring
                        </a>
                        <a href="marketplace.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-store me-2"></i>Marketplace
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
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-farm me-2"></i><?php echo htmlspecialchars($farm['name']); ?></h2>
                    <a href="<?php echo $_SESSION['role'] == 'farmer' ? 'farmer_dashboard.php' : 'planner_dashboard.php'; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
                
                <!-- Farm Overview -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-info-circle me-2"></i>Farm Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Farm Name:</strong> <?php echo htmlspecialchars($farm['name']); ?></p>
                                        <p><strong>Location:</strong> <?php echo htmlspecialchars($farm['location']); ?></p>
                                        <p><strong>Farm Type:</strong> 
                                            <span class="badge bg-info"><?php echo ucfirst($farm['farm_type']); ?></span>
                                        </p>
                                        <p><strong>Soil Type:</strong> <?php echo ucfirst($farm['soil_type']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Farmer:</strong> <?php echo htmlspecialchars($farm['farmer_name']); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($farm['farmer_email']); ?></p>
                                        <p><strong>Status:</strong> 
                                            <?php if($farm['status'] == 'pending'): ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php elseif($farm['status'] == 'approved'): ?>
                                                <span class="badge bg-success">Approved</span>
                                            <?php elseif($farm['status'] == 'rejected'): ?>
                                                <span class="badge bg-danger">Rejected</span>
                                            <?php endif; ?>
                                        </p>
                                        <p><strong>Created:</strong> <?php echo date('M j, Y', strtotime($farm['created_at'])); ?></p>
                                    </div>
                                </div>
                                
                                <?php if($farm['crops']): ?>
                                    <div class="mt-3">
                                        <strong>Crops:</strong>
                                        <p class="mb-0"><?php echo htmlspecialchars($farm['crops']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if($farm['notes']): ?>
                                    <div class="mt-3">
                                        <strong>Notes:</strong>
                                        <p class="mb-0"><?php echo htmlspecialchars($farm['notes']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if($farm['approved_by']): ?>
                                    <div class="mt-3">
                                        <strong>Approved by:</strong> <?php echo htmlspecialchars($farm['approver_name']); ?>
                                        <br>
                                        <strong>Approved on:</strong> <?php echo date('M j, Y g:i A', strtotime($farm['approved_at'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-bar me-2"></i>Quick Stats</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <i class="fas fa-sensor fa-2x text-primary mb-2"></i>
                                    <h4><?php echo count($iot_devices); ?></h4>
                                    <p class="mb-0">IoT Devices</p>
                                </div>
                                <div class="text-center mb-3">
                                    <i class="fas fa-drone fa-2x text-success mb-2"></i>
                                    <h4><?php echo count($drone_requests); ?></h4>
                                    <p class="mb-0">Drone Requests</p>
                                </div>
                                <div class="text-center">
                                    <i class="fas fa-brain fa-2x text-warning mb-2"></i>
                                    <h4><?php echo count($ai_recommendations); ?></h4>
                                    <p class="mb-0">AI Recommendations</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- IoT Devices -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-sensor me-2"></i>IoT Devices</h5>
                            </div>
                            <div class="card-body">
                                <?php if(empty($iot_devices)): ?>
                                    <p class="text-muted">No IoT devices assigned to this farm yet.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Device Name</th>
                                                    <th>Type</th>
                                                    <th>Status</th>
                                                    <th>Last Reading</th>
                                                    <th>Last Updated</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($iot_devices as $device): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($device['device_name']); ?></strong></td>
                                                        <td>
                                                            <span class="badge bg-info">
                                                                <?php echo ucfirst(str_replace('_', ' ', $device['device_type'])); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if($device['status'] == 'active'): ?>
                                                                <span class="badge bg-success">Active</span>
                                                            <?php elseif($device['status'] == 'inactive'): ?>
                                                                <span class="badge bg-secondary">Inactive</span>
                                                            <?php elseif($device['status'] == 'maintenance'): ?>
                                                                <span class="badge bg-warning">Maintenance</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if($device['last_reading']): ?>
                                                                <?php echo $device['last_reading']; ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">No data</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo date('M j, Y g:i A', strtotime($device['last_updated'])); ?></td>
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
                
                <!-- Recent IoT Readings -->
                <?php if(!empty($recent_readings)): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-chart-line me-2"></i>Recent Sensor Readings</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Device</th>
                                                    <th>Type</th>
                                                    <th>Reading</th>
                                                    <th>Timestamp</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($recent_readings as $reading): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($reading['device_name']); ?></td>
                                                        <td><?php echo ucfirst(str_replace('_', ' ', $reading['reading_type'])); ?></td>
                                                        <td><strong><?php echo $reading['reading_value']; ?></strong></td>
                                                        <td><?php echo date('M j, g:i A', strtotime($reading['timestamp'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Drone Requests -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-drone me-2"></i>Drone Requests</h5>
                            </div>
                            <div class="card-body">
                                <?php if(empty($drone_requests)): ?>
                                    <p class="text-muted">No drone requests for this farm yet.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Purpose</th>
                                                    <th>Location</th>
                                                    <th>Preferred Time</th>
                                                    <th>Status</th>
                                                    <th>Assigned Drone</th>
                                                    <th>Created</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($drone_requests as $request): ?>
                                                    <tr>
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
                                                                <?php echo htmlspecialchars($request['drone_name']); ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">Not assigned</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
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
                
                <!-- AI Recommendations -->
                <?php if(!empty($ai_recommendations)): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-brain me-2"></i>AI Recommendations</h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach($ai_recommendations as $rec): ?>
                                        <div class="border-start border-primary ps-3 mb-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <h6 class="mb-1">
                                                    <i class="fas fa-lightbulb me-2 text-warning"></i>
                                                    <?php echo ucfirst(str_replace('_', ' ', $rec['recommendation_type'])); ?> Recommendation
                                                </h6>
                                                <span class="badge bg-<?php echo $rec['is_followed'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $rec['is_followed'] ? 'Followed' : 'Pending'; ?>
                                                </span>
                                            </div>
                                            <p class="mb-1"><?php echo htmlspecialchars($rec['recommendation_text']); ?></p>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i><?php echo date('M j, Y g:i A', strtotime($rec['created_at'])); ?>
                                                <?php if($rec['green_points_earned'] > 0): ?>
                                                    • <i class="fas fa-star me-1"></i>+<?php echo $rec['green_points_earned']; ?> Green Points
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
