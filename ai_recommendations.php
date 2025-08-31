<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is either a farmer or planner
if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['farmer', 'planner'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get AI recommendations based on user role
if($_SESSION['role'] == 'planner') {
    // Planners can see all recommendations
    $recommendations_query = "SELECT ar.*, u.username 
                             FROM ai_recommendations ar 
                             JOIN users u ON ar.user_id = u.id 
                             ORDER BY ar.created_at DESC";
    $recommendations_stmt = $db->prepare($recommendations_query);
    $recommendations_stmt->execute();
} else {
    // Farmers can only see their own recommendations
    $recommendations_query = "SELECT ar.*, u.username 
                             FROM ai_recommendations ar 
                             JOIN users u ON ar.user_id = u.id 
                             WHERE ar.user_id = ?
                             ORDER BY ar.created_at DESC";
    $recommendations_stmt = $db->prepare($recommendations_query);
    $recommendations_stmt->execute([$_SESSION['user_id']]);
}

$recommendations = $recommendations_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <div class="sidebar">
            <?php if($_SESSION['role'] == 'planner'): ?>
                <h5><i class="fas fa-clipboard-check me-2"></i>Planner Dashboard</h5>
            <?php else: ?>
                <h5><i class="fas fa-user-farmer me-2"></i>Farmer Dashboard</h5>
            <?php endif; ?>
            <hr>
            <div class="mb-3">
                <strong>Welcome, <?php echo $_SESSION['username']; ?>!</strong>
                <div class="green-points mt-2">
                    <i class="fas fa-star me-1"></i><?php echo $_SESSION['green_points']; ?> Green Points
                </div>
            </div>
            
            <div class="list-group">
                <?php if($_SESSION['role'] == 'planner'): ?>
                    <a href="planner_dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="farm_approvals.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-check-circle me-2"></i>Farm Approvals
                    </a>
                    <a href="drone_approvals.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-drone me-2"></i>Drone Approvals
                    </a>
                    <a href="drone_inventory.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-boxes me-2"></i>Drone Inventory
                    </a>
                <?php else: ?>
                    <a href="farmer_dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="farm_requests.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-farm me-2"></i>My Farms
                    </a>
                    <a href="drone_requests.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-drone me-2"></i>Drone Requests
                    </a>
                    <a href="farmer_drone_results.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-line me-2"></i>Drone Results
                    </a>
                    <a href="iot_monitoring.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-microchip me-2"></i>IoT Monitoring
                    </a>
                    <a href="marketplace.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-store me-2"></i>Marketplace
                    </a>
                <?php endif; ?>
                <a href="ai_recommendations.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-brain me-2"></i>AI Recommendations
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="btn-blue-color"><i class="fas fa-brain me-2"></i>AI Recommendations & Insights</h2>
                <?php if($_SESSION['role'] == 'planner'): ?>
                    <a href="planner_dashboard.php" class="btn btn-blue-color">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                <?php else: ?>
                    <a href="farmer_dashboard.php" class="btn btn-blue-color">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- AI Insights Overview -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-lightbulb fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo count($recommendations); ?></h4>
                            <p class="mb-0 text-color">Total Recommendations</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo count(array_filter($recommendations, function($r) { return $r['is_followed']; })); ?></h4>
                            <p class="mb-0 text-color">Followed Recommendations</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-star fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo array_sum(array_column($recommendations, 'green_points_earned')); ?></h4>
                            <p class="mb-0 text-color">Green Points Earned</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- AI Recommendations List -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-brain me-2"></i>All AI Recommendations</h5>
                </div>
                <div class="card-body">
                    <?php if(empty($recommendations)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-brain fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No AI recommendations available yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($recommendations as $rec): ?>
                            <div class="card mb-3 border-start border-primary">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title mb-0">
                                                    <i class="fas fa-lightbulb me-2 text-warning"></i>
                                                    <?php echo ucfirst(str_replace('_', ' ', $rec['recommendation_type'])); ?> Recommendation
                                                </h6>
                                                <span class="badge bg-<?php echo $rec['is_followed'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $rec['is_followed'] ? 'Followed' : 'Pending'; ?>
                                                </span>
                                            </div>
                                            <p class="card-text"><?php echo htmlspecialchars($rec['recommendation_text']); ?></p>
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($rec['username']); ?> • 
                                                <i class="fas fa-clock me-1"></i><?php echo date('M j, Y g:i A', strtotime($rec['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <?php if($rec['green_points_earned'] > 0): ?>
                                                <div class="green-points mb-2">
                                                    <i class="fas fa-star me-1"></i>+<?php echo $rec['green_points_earned']; ?> Green Points
                                                </div>
                                            <?php endif; ?>
                                            <?php if($rec['sensor_data']): ?>
                                                <button class="btn btn-sm btn-outline-info" data-bs-toggle="collapse" data-bs-target="#sensorData<?php echo $rec['id']; ?>">
                                                    <i class="fas fa-chart-line me-1"></i>View Sensor Data
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if($rec['sensor_data']): ?>
                                        <div class="collapse mt-3" id="sensorData<?php echo $rec['id']; ?>">
                                            <div class="card card-body bg-light">
                                                <h6><i class="fas fa-chart-line me-2"></i>Sensor Data Analysis</h6>
                                                <pre class="mb-0"><code><?php echo htmlspecialchars($rec['sensor_data']); ?></code></pre>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- AI Insights Summary -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-pie me-2"></i>Recommendation Types</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="recommendationTypesChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-trending-up me-2"></i>Follow-up Rate</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="followUpRateChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Recommendation Types Chart
    const typesCtx = document.getElementById('recommendationTypesChart').getContext('2d');
    const typesData = {
        labels: ['Drone Type', 'Timing', 'Irrigation', 'Pest Control'],
        datasets: [{
            data: [
                <?php echo count(array_filter($recommendations, function($r) { return $r['recommendation_type'] == 'drone_type'; })); ?>,
                <?php echo count(array_filter($recommendations, function($r) { return $r['recommendation_type'] == 'timing'; })); ?>,
                <?php echo count(array_filter($recommendations, function($r) { return $r['recommendation_type'] == 'irrigation'; })); ?>,
                <?php echo count(array_filter($recommendations, function($r) { return $r['recommendation_type'] == 'pest_control'; })); ?>
            ],
            backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0']
        }]
    };
    new Chart(typesCtx, {
        type: 'doughnut',
        data: typesData,
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    
    // Follow-up Rate Chart
    const followCtx = document.getElementById('followUpRateChart').getContext('2d');
    const totalRecs = <?php echo count($recommendations); ?>;
    const followedRecs = <?php echo count(array_filter($recommendations, function($r) { return $r['is_followed']; })); ?>;
    const followData = {
        labels: ['Followed', 'Not Followed'],
        datasets: [{
            data: [followedRecs, totalRecs - followedRecs],
            backgroundColor: ['#28a745', '#6c757d']
        }]
    };
    new Chart(followCtx, {
        type: 'pie',
        data: followData,
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
