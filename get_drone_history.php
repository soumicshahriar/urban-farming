<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is a planner
if(!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

if($_SESSION['role'] != 'planner') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Planner role required. Current role: ' . $_SESSION['role']]);
    exit();
}

if(!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Drone ID required']);
    exit();
}

$drone_id = $_GET['id'];

$database = new Database();
$db = $database->getConnection();

try {
    // Get drone information
    $drone_query = "SELECT name, drone_type FROM drones WHERE id = ?";
    $drone_stmt = $db->prepare($drone_query);
    $drone_stmt->execute([$drone_id]);
    $drone = $drone_stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$drone) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Drone not found']);
        exit();
    }
    
    // Get all missions for this drone
    $missions_query = "SELECT dr.*, f.name as farm_name, u.username as farmer_name,
                              (SELECT COUNT(*) FROM drone_results WHERE drone_request_id = dr.id) as has_results
                       FROM drone_requests dr
                       JOIN farms f ON dr.farm_id = f.id
                       JOIN users u ON dr.farmer_id = u.id
                       WHERE dr.drone_id = ?
                       ORDER BY dr.created_at DESC";
    
    $missions_stmt = $db->prepare($missions_query);
    $missions_stmt->execute([$drone_id]);
    $missions = $missions_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $total_missions = count($missions);
    $completed_missions = count(array_filter($missions, function($m) { return $m['status'] == 'completed'; }));
    $success_rate = $total_missions > 0 ? round(($completed_missions / $total_missions) * 100, 1) : 0;
    
    // Generate HTML content
    $html = '
    <div class="mb-4">
        <h5>' . htmlspecialchars($drone['name']) . ' - Mission History</h5>
        <p class="text-muted">' . ucfirst($drone['drone_type']) . ' Drone</p>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h4 class="text-primary">' . $total_missions . '</h4>
                    <small class="text-muted">Total Missions</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h4 class="text-success">' . $completed_missions . '</h4>
                    <small class="text-muted">Completed</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h4 class="text-info">' . $success_rate . '%</h4>
                    <small class="text-muted">Success Rate</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h4 class="text-warning">' . ($total_missions - $completed_missions) . '</h4>
                    <small class="text-muted">In Progress</small>
                </div>
            </div>
        </div>
    </div>';
    
    if(!empty($missions)) {
        $html .= '
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Mission Date</th>
                        <th>Farm</th>
                        <th>Farmer</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Duration</th>
                        <th>Results</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach($missions as $mission) {
            // Calculate duration if completed
            $duration = '-';
            if($mission['status'] == 'completed' && $mission['updated_at']) {
                $start = new DateTime($mission['created_at']);
                $end = new DateTime($mission['updated_at']);
                $diff = $start->diff($end);
                $duration = $diff->format('%Hh %im');
            }
            
            $html .= '
                    <tr>
                        <td>
                            <div>
                                <strong>' . date('M j, Y', strtotime($mission['created_at'])) . '</strong>
                                <br>
                                <small class="text-muted">' . date('g:i A', strtotime($mission['created_at'])) . '</small>
                            </div>
                        </td>
                        <td>' . htmlspecialchars($mission['farm_name']) . '</td>
                        <td>' . htmlspecialchars($mission['farmer_name']) . '</td>
                        <td>
                            <span class="badge bg-secondary">
                                ' . ucfirst(str_replace('_', ' ', $mission['purpose'])) . '
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-' . getStatusColor($mission['status']) . '">
                                ' . ucfirst($mission['status']) . '
                            </span>
                        </td>
                        <td>' . $duration . '</td>
                        <td>';
            
            if($mission['has_results'] > 0) {
                $html .= '<span class="badge bg-success"><i class="fas fa-chart-line me-1"></i>Available</span>';
            } elseif($mission['status'] == 'completed') {
                $html .= '<span class="badge bg-warning">Pending</span>';
            } else {
                $html .= '<span class="text-muted">-</span>';
            }
            
            $html .= '</td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <button class="btn btn-outline-primary" onclick="viewMissionDetails(' . $mission['id'] . ')" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>';
            
            if($mission['has_results'] > 0) {
                $html .= '
                                <button class="btn btn-outline-success" onclick="viewMissionResults(' . $mission['id'] . ')" title="View Results">
                                    <i class="fas fa-chart-line"></i>
                                </button>';
            }
            
            $html .= '
                            </div>
                        </td>
                    </tr>';
        }
        
        $html .= '
                </tbody>
            </table>
        </div>';
    } else {
        $html .= '
        <div class="text-center py-4">
            <i class="fas fa-drone fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No Missions Yet</h5>
            <p class="text-muted">This drone hasn\'t been assigned to any missions yet.</p>
        </div>';
    }
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error loading drone history: ' . $e->getMessage()]);
}

// Helper function
function getStatusColor($status) {
    switch($status) {
        case 'available': return 'success';
        case 'assigned': return 'warning';
        case 'en_route': return 'primary';
        case 'active': return 'info';
        case 'completed': return 'secondary';
        case 'maintenance': return 'danger';
        default: return 'secondary';
    }
}
?>
