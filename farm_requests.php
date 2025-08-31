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

// Get all farms for the farmer
$farms_query = "SELECT f.*, 
                (SELECT COUNT(*) FROM iot_devices WHERE farm_id = f.id) as iot_count,
                (SELECT COUNT(*) FROM drone_requests WHERE farm_id = f.id) as drone_requests_count
                FROM farms f 
                WHERE f.farmer_id = ? 
                ORDER BY f.created_at DESC";
$farms_stmt = $db->prepare($farms_query);
$farms_stmt->execute([$_SESSION['user_id']]);
$farms = $farms_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <div class="sidebar">
            <h5><i class="fas fa-farm me-2"></i>Farm Management</h5>
            <hr>
            <div class="list-group">
                <a href="farm_requests.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-list me-2"></i>My Farms
                </a>
                <a href="create_farm.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-plus me-2"></i>Create New Farm
                </a>
                <a href="farmer_dashboard.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-farm me-2"></i>My Farms</h2>
                <a href="create_farm.php" class="btn btn-blue-color">
                    <i class="fas fa-plus me-2"></i>Create New Farm
                </a>
            </div>
            
            <?php if(empty($farms)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-farm fa-3x text-muted mb-3"></i>
                        <h4>No farms yet</h4>
                        <p class="text-muted">Start by creating your first farm to begin urban farming.</p>
                        <a href="create_farm.php" class="btn btn-blue-color">
                            <i class="fas fa-plus me-2"></i>Create Your First Farm
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach($farms as $farm): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-farm me-2"></i><?php echo htmlspecialchars($farm['name']); ?>
                                    </h5>
                                    <span class="status-badge status-<?php echo $farm['status']; ?>">
                                        <?php echo ucfirst($farm['status']); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Type</small>
                                            <div><strong><?php echo ucfirst($farm['farm_type']); ?></strong></div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Location</small>
                                            <div><strong><?php echo htmlspecialchars($farm['location']); ?></strong></div>
                                        </div>
                                    </div>
                                    
                                    <?php if($farm['crops']): ?>
                                        <div class="mb-3">
                                            <small class="text-muted">Crops</small>
                                            <div><?php echo htmlspecialchars($farm['crops']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if($farm['soil_type']): ?>
                                        <div class="mb-3">
                                            <small class="text-muted">Soil Type</small>
                                            <div><?php echo htmlspecialchars($farm['soil_type']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-microchip me-2 text-primary"></i>
                                                <div>
                                                    <small class="text-muted">IoT Devices</small>
                                                    <div><strong><?php echo $farm['iot_count']; ?></strong></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-drone me-2 text-success"></i>
                                                <div>
                                                    <small class="text-muted">Drone Requests</small>
                                                    <div><strong><?php echo $farm['drone_requests_count']; ?></strong></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">Created</small>
                                        <div><?php echo date('M j, Y', strtotime($farm['created_at'])); ?></div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div class="d-flex justify-content-between">
                                        <a href="view_farm.php?id=<?php echo $farm['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </a>
                                        <?php if($farm['status'] == 'approved'): ?>
                                            <a href="request_drone.php?farm_id=<?php echo $farm['id']; ?>" class="btn btn-sm btn-success">
                                                <i class="fas fa-drone me-1"></i>Request Drone
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Summary Statistics -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-bar me-2"></i>Farm Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <div class="dashboard-stats">
                                            <h4 class="text-color"><?php echo count($farms); ?></h4>
                                            <p class="mb-0 text-color">Total Farms</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="dashboard-stats">
                                            <h4 class="text-color"><?php echo count(array_filter($farms, function($f) { return $f['status'] == 'approved'; })); ?></h4>
                                            <p class="mb-0 text-color">Approved Farms</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="dashboard-stats">
                                            <h4 class="text-color"><?php echo count(array_filter($farms, function($f) { return $f['status'] == 'pending'; })); ?></h4>
                                            <p class="mb-0 text-color">Pending Approval</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="dashboard-stats">
                                            <h4 class="text-color"><?php echo array_sum(array_column($farms, 'iot_count')); ?></h4>
                                            <p class="mb-0 text-color">Total IoT Devices</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
