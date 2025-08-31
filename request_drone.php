<?php
session_start();
require_once 'config/database.php';
require_once 'includes/NotificationHelper.php';

// Check if user is logged in and is a farmer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'farmer') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$notificationHelper = new NotificationHelper($database);

$farm_id = isset($_GET['farm_id']) ? (int)$_GET['farm_id'] : 0;
$error = '';
$success = '';

// Get farmer's approved farms
$farms_query = "SELECT * FROM farms WHERE farmer_id = ? AND status = 'approved' ORDER BY name";
$farms_stmt = $db->prepare($farms_query);
$farms_stmt->execute([$_SESSION['user_id']]);
$farms = $farms_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get AI recommendations for the selected farm
$ai_recommendations = [];
if ($farm_id) {
    $ai_query = "SELECT * FROM ai_recommendations WHERE user_id = ? AND recommendation_type IN ('drone_type', 'timing') ORDER BY created_at DESC LIMIT 3";
    $ai_stmt = $db->prepare($ai_query);
    $ai_stmt->execute([$_SESSION['user_id']]);
    $ai_recommendations = $ai_stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $selected_farm_id = (int)$_POST['farm_id'];
    $purpose = $_POST['purpose'];
    $location = trim($_POST['location']);
    $preferred_time = $_POST['preferred_time'];
    
    // Validation
    if (empty($selected_farm_id) || empty($purpose) || empty($location) || empty($preferred_time)) {
        $error = "All fields are required";
    } else {
        // Check if farm belongs to user and is approved
        $farm_check_query = "SELECT id FROM farms WHERE id = ? AND farmer_id = ? AND status = 'approved'";
        $farm_check_stmt = $db->prepare($farm_check_query);
        $farm_check_stmt->execute([$selected_farm_id, $_SESSION['user_id']]);
        
        if ($farm_check_stmt->rowCount() == 0) {
            $error = "Invalid farm selected";
        } else {
            try {
                $db->beginTransaction();
                
                // Insert drone request
                $request_query = "INSERT INTO drone_requests (farmer_id, farm_id, purpose, location, preferred_time) 
                                 VALUES (?, ?, ?, ?, ?)";
                $request_stmt = $db->prepare($request_query);
                $request_stmt->execute([$_SESSION['user_id'], $selected_farm_id, $purpose, $location, $preferred_time]);
                
                $request_id = $db->lastInsertId();
                
                // Generate AI recommendation for this request
                $ai_text = "Drone request submitted for " . ucfirst($purpose) . " at " . $location . ". ";
                $ai_text .= "Recommended time: " . date('M j, Y g:i A', strtotime($preferred_time)) . ". ";
                $ai_text .= "Following AI recommendations earns Green Points!";
                
                $ai_insert_query = "INSERT INTO ai_recommendations (user_id, recommendation_type, recommendation_text) 
                                   VALUES (?, 'drone_type', ?)";
                $ai_insert_stmt = $db->prepare($ai_insert_query);
                $ai_insert_stmt->execute([$_SESSION['user_id'], $ai_text]);
                
                // Award Green Points for following AI recommendations
                $green_points = 5;
                $points_query = "UPDATE users SET green_points = green_points + ? WHERE id = ?";
                $points_stmt = $db->prepare($points_query);
                $points_stmt->execute([$green_points, $_SESSION['user_id']]);
                
                // Update session green points
                $_SESSION['green_points'] += $green_points;
                
                // Log the transaction
                $transaction_query = "INSERT INTO green_points_transactions (user_id, transaction_type, amount, description, related_entity_type, related_entity_id) 
                                    VALUES (?, 'earned', ?, ?, 'drone_request', ?)";
                $transaction_stmt = $db->prepare($transaction_query);
                $transaction_stmt->execute([$_SESSION['user_id'], $green_points, "Drone request following AI recommendations", $request_id]);
                
                // Log the request
                $log_query = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->execute([$_SESSION['user_id'], 'drone_request', "Submitted drone request for $purpose", $_SERVER['REMOTE_ADDR']]);
                
                // Send notifications to planners and admins
                $notificationHelper->notifyNewDroneRequest($request_id, $_SESSION['user_id'], $purpose, $location);
                
                $db->commit();
                $success = "Drone request submitted successfully! You earned $green_points Green Points for following AI recommendations.";
                
            } catch (Exception $e) {
                $db->rollback();
                $error = "Error submitting request: " . $e->getMessage();
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <div class="sidebar">
            <h5><i class="fas fa-drone me-2"></i>Drone Services</h5>
            <hr>
            <div class="list-group">
                <a href="request_drone.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-plus me-2"></i>Request Drone
                </a>
                <a href="drone_requests.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-list me-2"></i>My Requests
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
                <h2><i class="fas fa-drone me-2"></i>Request Drone Service</h2>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <br><a href="drone_requests.php" class="alert-link">View my drone requests</a>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-edit me-2"></i>Drone Request Form</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="farm_id" class="form-label">Select Farm *</label>
                                    <select class="form-select" id="farm_id" name="farm_id" required>
                                        <option value="">Choose a farm</option>
                                        <?php foreach($farms as $farm): ?>
                                            <option value="<?php echo $farm['id']; ?>" <?php echo $farm_id == $farm['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($farm['name']); ?> - <?php echo htmlspecialchars($farm['location']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="purpose" class="form-label">Purpose *</label>
                                    <select class="form-select" id="purpose" name="purpose" required>
                                        <option value="">Select purpose</option>
                                        <option value="survey">Survey & Mapping</option>
                                        <option value="pest_control_spraying">Pest Control - Spraying</option>
                                        <option value="pest_control_monitoring">Pest Control - Monitoring</option>
                                        <option value="pest_control_biological">Pest Control - Biological</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="location" class="form-label">Specific Location *</label>
                                    <input type="text" class="form-control" id="location" name="location" 
                                           placeholder="e.g., North field, Zone A, etc." required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="preferred_time" class="form-label">Preferred Time *</label>
                                    <input type="datetime-local" class="form-control" id="preferred_time" name="preferred_time" required>
                                </div>
                                
                                <button type="submit" class="btn btn-blue-color">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Request
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- AI Recommendations -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-brain me-2"></i>AI Recommendations</h5>
                        </div>
                        <div class="card-body">
                            <?php if(empty($ai_recommendations)): ?>
                                <p class="text-muted">No AI recommendations yet. Submit your first request to get personalized recommendations!</p>
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
                    
                    <!-- Green Points Info -->
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-star me-2"></i>Green Points</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <div class="green-points">
                                    <i class="fas fa-star me-1"></i><?php echo $_SESSION['green_points']; ?> Points
                                </div>
                            </div>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i>Following AI recommendations: +5 points</li>
                                <li><i class="fas fa-check text-success me-2"></i>Eco-friendly timing: +3 points</li>
                                <li><i class="fas fa-check text-success me-2"></i>Efficient drone usage: +2 points</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-fill location when farm is selected
document.getElementById('farm_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    if (selectedOption.value) {
        const farmLocation = selectedOption.text.split(' - ')[1];
        document.getElementById('location').value = farmLocation;
    }
});

// Set default time to tomorrow morning
const tomorrow = new Date();
tomorrow.setDate(tomorrow.getDate() + 1);
tomorrow.setHours(6, 30, 0, 0);
document.getElementById('preferred_time').value = tomorrow.toISOString().slice(0, 16);
</script>

<?php include 'includes/footer.php'; ?>
