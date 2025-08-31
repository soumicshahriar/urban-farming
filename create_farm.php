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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $farm_name = trim($_POST['farm_name']);
    $location = trim($_POST['location']);
    $farm_type = $_POST['farm_type'];
    $crops = trim($_POST['crops']);
    $soil_type = trim($_POST['soil_type']);
    $iot_devices = isset($_POST['iot_devices']) ? $_POST['iot_devices'] : [];
    
    // Validation
    if (empty($farm_name) || empty($location) || empty($farm_type)) {
        $error = "Farm name, location, and type are required";
    } else {
        try {
            $db->beginTransaction();
            
            // Insert farm
            $farm_query = "INSERT INTO farms (farmer_id, name, location, farm_type, crops, soil_type) 
                          VALUES (?, ?, ?, ?, ?, ?)";
            $farm_stmt = $db->prepare($farm_query);
            $farm_stmt->execute([$_SESSION['user_id'], $farm_name, $location, $farm_type, $crops, $soil_type]);
            
            $farm_id = $db->lastInsertId();
            
            // Insert IoT devices
            if (!empty($iot_devices)) {
                $iot_query = "INSERT INTO iot_devices (farm_id, device_type, device_name, status) VALUES (?, ?, ?, 'active')";
                $iot_stmt = $db->prepare($iot_query);
                
                foreach ($iot_devices as $device_type) {
                    $device_name = ucfirst(str_replace('_', ' ', $device_type)) . " Sensor";
                    $iot_stmt->execute([$farm_id, $device_type, $device_name]);
                }
            }
            
            // Log the farm creation
            $log_query = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->execute([$_SESSION['user_id'], 'farm_creation', "Created farm: $farm_name", $_SERVER['REMOTE_ADDR']]);
            
            // Send notifications to planners and admins
            $notificationHelper->notifyNewFarmRequest($farm_id, $_SESSION['user_id'], $farm_name);
            
            $db->commit();
            $success = "Farm created successfully! It will be reviewed by a planner.";
            
        } catch (Exception $e) {
            $db->rollback();
            $error = "Error creating farm: " . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <div class="sidebar">
            <h5><i class="fas fa-farm me-2"></i>Farm Management</h5>
            <hr>
            <div class="list-group">
                <a href="farm_requests.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-list me-2"></i>My Farms
                </a>
                <a href="create_farm.php" class="list-group-item list-group-item-action active">
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
                <h2><i class="fas fa-plus me-2"></i>Create New Farm</h2>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <br><a href="farm_requests.php" class="alert-link">View my farms</a>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="farm_name" class="form-label">Farm Name *</label>
                                    <input type="text" class="form-control" id="farm_name" name="farm_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="farm_type" class="form-label">Farm Type *</label>
                                    <select class="form-select" id="farm_type" name="farm_type" required>
                                        <option value="">Select farm type</option>
                                        <option value="vegetable">Vegetable Farm</option>
                                        <option value="fruit">Fruit Farm</option>
                                        <option value="grain">Grain Farm</option>
                                        <option value="mixed">Mixed Farm</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="location" class="form-label">Location *</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   placeholder="Enter farm location/address" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="crops" class="form-label">Crops to be Grown</label>
                                    <textarea class="form-control" id="crops" name="crops" rows="3" 
                                              placeholder="e.g., Tomatoes, Lettuce, Carrots"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="soil_type" class="form-label">Soil Type</label>
                                    <input type="text" class="form-control" id="soil_type" name="soil_type" 
                                           placeholder="e.g., Loamy, Sandy, Clay">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">IoT Devices (Optional)</label>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="iot_devices[]" value="soil_moisture" id="soil_moisture">
                                        <label class="form-check-label" for="soil_moisture">
                                            <i class="fas fa-tint me-1"></i>Soil Moisture
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="iot_devices[]" value="temperature" id="temperature">
                                        <label class="form-check-label" for="temperature">
                                            <i class="fas fa-thermometer-half me-1"></i>Temperature
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="iot_devices[]" value="humidity" id="humidity">
                                        <label class="form-check-label" for="humidity">
                                            <i class="fas fa-cloud me-1"></i>Humidity
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="iot_devices[]" value="light" id="light">
                                        <label class="form-check-label" for="light">
                                            <i class="fas fa-sun me-1"></i>Light Intensity
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="iot_devices[]" value="water_flow" id="water_flow">
                                        <label class="form-check-label" for="water_flow">
                                            <i class="fas fa-water me-1"></i>Water Flow
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="iot_devices[]" value="pump" id="pump">
                                        <label class="form-check-label" for="pump">
                                            <i class="fas fa-pump-soap me-1"></i>Irrigation Pump
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="iot_devices[]" value="fan" id="fan">
                                        <label class="form-check-label" for="fan">
                                            <i class="fas fa-fan me-1"></i>Ventilation Fan
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="iot_devices[]" value="light_control" id="light_control">
                                        <label class="form-check-label" for="light_control">
                                            <i class="fas fa-lightbulb me-1"></i>Light Control
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="farm_requests.php" class="btn btn-blue-color">
                                <i class="fas fa-arrow-left me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-blue-color">
                                <i class="fas fa-save me-2"></i>Create Farm
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
