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

// Get all drone requests for this farmer with results
$requests_query = "SELECT dr.*, f.name as farm_name, d.name as drone_name, d.drone_type,
                         (SELECT COUNT(*) FROM drone_results WHERE drone_request_id = dr.id) as has_results,
                         (SELECT created_at FROM drone_results WHERE drone_request_id = dr.id ORDER BY created_at DESC LIMIT 1) as latest_result_date
                  FROM drone_requests dr 
                  JOIN farms f ON dr.farm_id = f.id 
                  LEFT JOIN drones d ON dr.drone_id = d.id
                  WHERE dr.farmer_id = ?
                  ORDER BY dr.created_at DESC";

$requests_stmt = $db->prepare($requests_query);
$requests_stmt->execute([$_SESSION['user_id']]);
$requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);

// Check for new results (results created in the last 24 hours)
$new_results_count = 0;
foreach($requests as $request) {
    if($request['latest_result_date']) {
        $result_date = new DateTime($request['latest_result_date']);
        $now = new DateTime();
        $diff = $now->diff($result_date);
        if($diff->days == 0 && $diff->h < 24) {
            $new_results_count++;
        }
    }
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <div class="sidebar">
            <h5><i class="fas fa-seedling me-2"></i>Farmer Dashboard</h5>
            <hr>
            <div class="mb-3">
                <strong>Welcome, <?php echo $_SESSION['username']; ?>!</strong>
                <div class="green-points mt-2">
                    <i class="fas fa-star me-1"></i><?php echo $_SESSION['green_points']; ?> Green Points
                </div>
            </div>
            
            <div class="list-group">
                <a href="farmer_dashboard.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
                <a href="my_farms.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-map-marker-alt me-2"></i>My Farms
                </a>
                <a href="drone_requests.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-drone me-2"></i>Drone Requests
                </a>
                <a href="farmer_drone_results.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-chart-line me-2"></i>Drone Results
                    <?php if($new_results_count > 0): ?>
                        <span class="badge bg-success ms-auto"><?php echo $new_results_count; ?> new</span>
                    <?php endif; ?>
                </a>
                <a href="marketplace.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-store me-2"></i>Seed Marketplace
                </a>
                <a href="iot_monitoring.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-microchip me-2"></i>IoT Monitoring
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-chart-line me-2"></i>Drone Operation Results</h2>
                <a href="farmer_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
            
            <?php if($new_results_count > 0): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-bell me-2"></i>
                    <strong>New Results Available!</strong> You have <?php echo $new_results_count; ?> new drone operation result<?php echo $new_results_count > 1 ? 's' : ''; ?> based on sensor data from your farms.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Summary Statistics -->
            <div class="row g-3">
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card dashboard-stats h-100 shadow-sm border-0 hover-card">
                        <div class="card-body text-center">
                            <i class="fas fa-drone fa-2x mb-2 text-color"></i>
                            <h4 class="fw-bold mb-1 text-color"><?php echo count($requests); ?></h4>
                            <p class="mb-0 text-color">Total Requests</p>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card dashboard-stats h-100 shadow-sm border-0 hover-card">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle fa-2x mb-2 text-color "></i>
                            <h4 class="fw-bold mb-1 text-color">
                                <?php echo count(array_filter($requests, function($r) { return $r['status'] == 'completed'; })); ?>
                            </h4>
                            <p class="mb-0 text-color">Completed</p>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card dashboard-stats h-100 shadow-sm border-0 hover-card">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-bar fa-2x mb-2 text-color"></i>
                            <h4 class="fw-bold mb-1 text-color">
                                <?php echo count(array_filter($requests, function($r) { return $r['has_results'] > 0; })); ?>
                            </h4>
                            <p class="mb-0 text-color ">With Results</p>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card dashboard-stats h-100 shadow-sm border-0 hover-card">
                        <div class="card-body text-center">
                            <i class="fas fa-robot fa-2x mb-2 text-color"></i>
                            <h4 class="fw-bold mb-1 text-color"><?php echo $new_results_count; ?></h4>
                            <p class="mb-0 text-color">New Results</p>
                        </div>
                    </div>
                </div>
            </div>

<!-- Optional hover effect -->
<style>
    .hover-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .hover-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 6px 18px rgba(0,0,0,0.12);
    }
</style>

            
            <!-- Drone Results Table -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-line me-2"></i>My Drone Operation Results</h5>
                </div>
                <div class="card-body">
                    <?php if(empty($requests)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-drone fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No drone requests found.</p>
                            <p class="text-muted">Create a drone request to see results here.</p>
                            <a href="drone_requests.php" class="btn btn-blue-color">
                                <i class="fas fa-plus me-2"></i>Create Drone Request
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Farm</th>
                                        <th>Purpose</th>
                                        <th>Status</th>
                                        <th>Drone</th>
                                        <th>Results</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($requests as $request): ?>
                                        <?php 
                                        $is_new_result = false;
                                        if($request['latest_result_date']) {
                                            $result_date = new DateTime($request['latest_result_date']);
                                            $now = new DateTime();
                                            $diff = $now->diff($result_date);
                                            $is_new_result = ($diff->days == 0 && $diff->h < 24);
                                        }
                                        ?>
                                        <tr class="<?php echo $is_new_result ? 'table-success' : ''; ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($request['farm_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($request['location']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo ucfirst(str_replace('_', ' ', $request['purpose'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if($request['status'] == 'pending'): ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php elseif($request['status'] == 'approved'): ?>
                                                    <span class="badge bg-success">Approved</span>
                                                <?php elseif($request['status'] == 'rejected'): ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php elseif($request['status'] == 'completed'): ?>
                                                    <span class="badge bg-secondary">Completed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary"><?php echo ucfirst($request['status']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($request['drone_name']): ?>
                                                    <i class="fas fa-drone me-1"></i>
                                                    <?php echo htmlspecialchars($request['drone_name']); ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo ucfirst($request['drone_type']); ?></small>
                                                <?php else: ?>
                                                    <small class="text-muted">Not assigned</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($request['has_results'] > 0): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-chart-line me-1"></i>Available
                                                    </span>
                                                    <?php if($is_new_result): ?>
                                                        <br><small class="text-success"><i class="fas fa-star me-1"></i>New!</small>
                                                    <?php endif; ?>
                                                <?php elseif($request['status'] == 'completed'): ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php else: ?>
                                                    <small class="text-muted">-</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                                            <td>
                                                <?php if($request['status'] == 'completed' && $request['has_results'] > 0): ?>
                                                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#resultsModal" 
                                                            data-request-id="<?php echo $request['id']; ?>" data-farm-name="<?php echo htmlspecialchars($request['farm_name']); ?>">
                                                        <i class="fas fa-chart-line me-1"></i>View Results
                                                        <?php if($is_new_result): ?>
                                                            <i class="fas fa-star text-warning ms-1"></i>
                                                        <?php endif; ?>
                                                    </button>
                                                <?php elseif($request['status'] == 'completed' && $request['has_results'] == 0): ?>
                                                    <small class="text-muted">Results pending</small>
                                                <?php else: ?>
                                                    <small class="text-muted">-</small>
                                                <?php endif; ?>
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

<!-- Results Modal -->
<div class="modal fade" id="resultsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Drone Operation Results</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="resultsModalBody">
                <!-- Results will be loaded here via AJAX -->
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading results...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-blue-color" onclick="exportResults()">
                    <i class="fas fa-download me-1"></i>Export Results
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle results modal
    const resultsModal = document.getElementById('resultsModal');
    if (resultsModal) {
        resultsModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const requestId = button.getAttribute('data-request-id');
            const farmName = button.getAttribute('data-farm-name');
            
            // Update modal title
            this.querySelector('.modal-title').textContent = `Drone Operation Results - ${farmName}`;
            
            // Load results via AJAX
            loadDroneResults(requestId);
        });
    }
});

// Load drone results via AJAX
function loadDroneResults(requestId) {
    const modalBody = document.getElementById('resultsModalBody');
    
    // Show loading spinner
    modalBody.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading results...</p>
        </div>
    `;
    
    // Make AJAX request
    fetch(`get_drone_results.php?request_id=${requestId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayDroneResults(data.results, data.request, data.sensor_averages, data.is_auto_generated, data.sensor_context);
            } else {
                modalBody.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading results: ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error loading results: ${error.message}
                </div>
            `;
        });
}

// Display drone results
function displayDroneResults(results, request, sensorAverages, isAutoGenerated, sensorContext) {
    const modalBody = document.getElementById('resultsModalBody');
    
    // Create sensor context HTML
    let sensorContextHTML = '';
    if (Object.keys(sensorAverages).length > 0) {
        sensorContextHTML = `
            <div class="col-md-12 mb-3">
                <div class="alert alert-info">
                    <h6><i class="fas fa-microchip me-2"></i>Sensor Data Context (${sensorContext.time_period})</h6>
                    <div class="row">
                        ${Object.entries(sensorAverages).map(([type, value]) => `
                            <div class="col-md-3">
                                <strong>${type.charAt(0).toUpperCase() + type.slice(1).replace('_', ' ')}:</strong> 
                                ${value}${type === 'temperature' ? '°C' : type === 'humidity' ? '%' : type === 'light' ? ' lux' : ''}
                            </div>
                        `).join('')}
                    </div>
                    <small class="text-muted">Based on ${sensorContext.total_readings} readings from ${sensorContext.devices_count} devices</small>
                </div>
            </div>
        `;
    }
    
    // Create auto-generation indicator
    const autoGenIndicator = isAutoGenerated ? `
        <div class="col-md-12 mb-3">
            <div class="alert alert-warning">
                <i class="fas fa-robot me-2"></i>
                <strong>AI-Generated Results:</strong> These results were automatically generated based on sensor data from your farm to provide real-time insights.
            </div>
        </div>
    ` : '';
    
    modalBody.innerHTML = `
        ${autoGenIndicator}
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Operation Summary</h6>
                    <p class="mb-1"><strong>Farm:</strong> ${request.farm_name}</p>
                    <p class="mb-1"><strong>Purpose:</strong> ${request.purpose}</p>
                    <p class="mb-1"><strong>Drone:</strong> ${request.drone_name}</p>
                    <p class="mb-0"><strong>Completed:</strong> ${request.completed_date}</p>
                </div>
            </div>
        </div>
        
        ${sensorContextHTML}
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-chart-bar me-2"></i>Performance Metrics</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="metric-item">
                                    <div class="metric-value text-primary">${results.area_covered}</div>
                                    <div class="metric-label">Acres Covered</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-item">
                                    <div class="metric-value text-success">${results.duration_minutes}</div>
                                    <div class="metric-label">Minutes</div>
                                </div>
                            </div>
                        </div>
                        <div class="row text-center mt-3">
                            <div class="col-6">
                                <div class="metric-item">
                                    <div class="metric-value text-warning">${(results.efficiency_score * 100).toFixed(1)}%</div>
                                    <div class="metric-label">Efficiency</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-item">
                                    <div class="metric-value text-info">${results.coverage_percentage}%</div>
                                    <div class="metric-label">Coverage</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-clipboard-list me-2"></i>Operation Details</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Operation Type:</strong> ${results.operation_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</p>
                        <p><strong>Issues Encountered:</strong> ${results.issues_encountered || 'None reported'}</p>
                        <p><strong>Recommendations:</strong> ${results.recommendations || 'No specific recommendations'}</p>
                        <p><strong>Data Collected:</strong> ${results.data_collected || 'Standard operation data'}</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-chart-line me-2"></i>Performance Analysis</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="progress mb-2" style="height: 25px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: ${results.coverage_percentage}%">
                                        ${results.coverage_percentage}% Coverage
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="progress mb-2" style="height: 25px;">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: ${results.efficiency_score * 100}%">
                                        ${(results.efficiency_score * 100).toFixed(1)}% Efficiency
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="progress mb-2" style="height: 25px;">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: ${(results.area_covered / 50) * 100}%">
                                        ${results.area_covered} Acres
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Export results function
function exportResults() {
    alert('Export functionality will be implemented here.');
}
</script>

<style>
.metric-item {
    padding: 10px;
    border-radius: 8px;
    background: #f8f9fa;
}

.metric-value {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.metric-label {
    font-size: 0.8rem;
    color: #6c757d;
    text-transform: uppercase;
    font-weight: 600;
}

.table-success {
    background-color: rgba(40, 167, 69, 0.1) !important;
}

.badge {
    font-size: 0.75rem;
}
</style>

<?php include 'includes/footer.php'; ?>
