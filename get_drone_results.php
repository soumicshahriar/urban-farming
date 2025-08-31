<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is a planner or farmer
if(!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'planner' && $_SESSION['role'] != 'farmer')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['request_id'])) {
    echo json_encode(['success' => false, 'message' => 'Request ID is required']);
    exit();
}

$request_id = (int)$_GET['request_id'];

try {
    // Get drone results
    $results_query = "SELECT * FROM drone_results WHERE drone_request_id = ? ORDER BY created_at DESC LIMIT 1";
    $results_stmt = $db->prepare($results_query);
    $results_stmt->execute([$request_id]);
    $results = $results_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$results) {
        echo json_encode(['success' => false, 'message' => 'No results found for this request']);
        exit();
    }
    
    // Get drone request details
    $request_query = "SELECT dr.*, f.name as farm_name, f.id as farm_id, d.name as drone_name, 
                             DATE_FORMAT(dr.updated_at, '%M %d, %Y %h:%i %p') as completed_date
                      FROM drone_requests dr 
                      JOIN farms f ON dr.farm_id = f.id 
                      LEFT JOIN drones d ON dr.drone_id = d.id
                      WHERE dr.id = ?";
    
    // If user is a farmer, ensure they can only access their own requests
    if($_SESSION['role'] == 'farmer') {
        $request_query .= " AND dr.farmer_id = ?";
        $request_stmt = $db->prepare($request_query);
        $request_stmt->execute([$request_id, $_SESSION['user_id']]);
    } else {
        $request_stmt = $db->prepare($request_query);
        $request_stmt->execute([$request_id]);
    }
    
    $request = $request_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit();
    }
    
    // Get recent sensor data for context (last 24 hours)
    $sensor_query = "SELECT sr.*, id.device_type, id.device_name
                     FROM sensor_readings sr
                     JOIN iot_devices id ON sr.device_id = id.id
                     WHERE id.farm_id = ? 
                     AND sr.timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                     ORDER BY sr.timestamp DESC
                     LIMIT 20";
    
    $sensor_stmt = $db->prepare($sensor_query);
    $sensor_stmt->execute([$request['farm_id']]);
    $sensor_data = $sensor_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate sensor averages for context
    $sensor_averages = [];
    $sensor_counts = [];
    
    foreach ($sensor_data as $reading) {
        $device_type = $reading['device_type'];
        if (!isset($sensor_averages[$device_type])) {
            $sensor_averages[$device_type] = 0;
            $sensor_counts[$device_type] = 0;
        }
        $sensor_averages[$device_type] += $reading['value'];
        $sensor_counts[$device_type]++;
    }
    
    foreach ($sensor_averages as $type => $total) {
        if ($sensor_counts[$type] > 0) {
            $sensor_averages[$type] = round($total / $sensor_counts[$type], 1);
        }
    }
    
    // Determine if results were auto-generated based on sensor data
    $is_auto_generated = false;
    if (strpos($results['data_collected'], 'sensor data') !== false || 
        strpos($results['data_collected'], 'environmental') !== false ||
        strpos($results['data_collected'], 'weather conditions') !== false) {
        $is_auto_generated = true;
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'request' => $request,
        'sensor_data' => $sensor_data,
        'sensor_averages' => $sensor_averages,
        'is_auto_generated' => $is_auto_generated,
        'sensor_context' => [
            'total_readings' => count($sensor_data),
            'time_period' => 'Last 24 hours',
            'devices_count' => count(array_unique(array_column($sensor_data, 'device_type')))
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
