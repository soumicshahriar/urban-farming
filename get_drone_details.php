<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is a planner
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'planner') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
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
    // Get drone details
    $drone_query = "SELECT d.*, 
                           (SELECT COUNT(*) FROM drone_requests WHERE drone_id = d.id AND status = 'completed') as completed_missions,
                           (SELECT COUNT(*) FROM drone_requests WHERE drone_id = d.id AND status IN ('assigned', 'en_route', 'active')) as active_missions,
                           (SELECT COUNT(*) FROM drone_requests WHERE drone_id = d.id) as total_missions
                    FROM drones d 
                    WHERE d.id = ?";
    
    $drone_stmt = $db->prepare($drone_query);
    $drone_stmt->execute([$drone_id]);
    $drone = $drone_stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$drone) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Drone not found']);
        exit();
    }
    
    // Get recent missions
    $missions_query = "SELECT dr.*, f.name as farm_name, u.username as farmer_name
                      FROM drone_requests dr
                      JOIN farms f ON dr.farm_id = f.id
                      JOIN users u ON dr.farmer_id = u.id
                      WHERE dr.drone_id = ?
                      ORDER BY dr.created_at DESC
                      LIMIT 5";
    
    $missions_stmt = $db->prepare($missions_query);
    $missions_stmt->execute([$drone_id]);
    $missions = $missions_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate HTML content
    $html = '
    <div class="row">
        <div class="col-md-6">
            <h6 class="text-muted mb-3">Drone Information</h6>
            <table class="table table-borderless">
                <tr>
                    <td><strong>Name:</strong></td>
                    <td>' . htmlspecialchars($drone['name']) . '</td>
                </tr>
                <tr>
                    <td><strong>Type:</strong></td>
                    <td><span class="badge bg-' . getDroneTypeColor($drone['drone_type']) . '">' . ucfirst($drone['drone_type']) . '</span></td>
                </tr>
                <tr>
                    <td><strong>Status:</strong></td>
                    <td><span class="badge bg-' . getStatusColor($drone['status']) . '">' . ucfirst($drone['status']) . '</span></td>
                </tr>
                <tr>
                    <td><strong>Battery Level:</strong></td>
                    <td>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar ' . getBatteryColor($drone['battery_level']) . '" style="width: ' . $drone['battery_level'] . '%">
                                ' . $drone['battery_level'] . '%
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td><strong>Created:</strong></td>
                    <td>' . date('M j, Y', strtotime($drone['created_at'])) . '</td>
                </tr>
                ' . ($drone['last_maintenance'] ? '
                <tr>
                    <td><strong>Last Maintenance:</strong></td>
                    <td>' . date('M j, Y', strtotime($drone['last_maintenance'])) . '</td>
                </tr>' : '') . '
                ' . ($drone['notes'] ? '
                <tr>
                    <td><strong>Notes:</strong></td>
                    <td>' . htmlspecialchars($drone['notes']) . '</td>
                </tr>' : '') . '
            </table>
        </div>
        <div class="col-md-6">
            <h6 class="text-muted mb-3">Mission Statistics</h6>
            <div class="row text-center">
                <div class="col-4">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h4 class="text-primary">' . $drone['total_missions'] . '</h4>
                            <small class="text-muted">Total Missions</small>
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h4 class="text-success">' . $drone['completed_missions'] . '</h4>
                            <small class="text-muted">Completed</small>
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h4 class="text-warning">' . $drone['active_missions'] . '</h4>
                            <small class="text-muted">Active</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';
    
    if(!empty($missions)) {
        $html .= '
        <hr>
        <h6 class="text-muted mb-3">Recent Missions</h6>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Farm</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach($missions as $mission) {
            $html .= '
                    <tr>
                        <td>' . htmlspecialchars($mission['farm_name']) . '</td>
                        <td>' . ucfirst(str_replace('_', ' ', $mission['purpose'])) . '</td>
                        <td><span class="badge bg-' . getStatusColor($mission['status']) . '">' . ucfirst($mission['status']) . '</span></td>
                        <td>' . date('M j, Y', strtotime($mission['created_at'])) . '</td>
                    </tr>';
        }
        
        $html .= '
                </tbody>
            </table>
        </div>';
    }
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error loading drone details: ' . $e->getMessage()]);
}

// Helper functions
function getDroneTypeColor($type) {
    switch($type) {
        case 'survey': return 'info';
        case 'spraying': return 'warning';
        case 'monitoring': return 'primary';
        case 'biological': return 'success';
        default: return 'secondary';
    }
}

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

function getBatteryColor($level) {
    if($level > 70) return 'bg-success';
    if($level > 30) return 'bg-warning';
    return 'bg-danger';
}
?>
