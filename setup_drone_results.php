<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Create drone_results table
    $create_table_query = "
    CREATE TABLE IF NOT EXISTS drone_results (
        id INT PRIMARY KEY AUTO_INCREMENT,
        drone_request_id INT NOT NULL,
        drone_id INT NOT NULL,
        operation_type VARCHAR(100) NOT NULL,
        area_covered DECIMAL(10,2) NOT NULL,
        duration_minutes INT NOT NULL,
        efficiency_score DECIMAL(5,2) NOT NULL,
        coverage_percentage DECIMAL(5,2) NOT NULL,
        issues_encountered TEXT,
        recommendations TEXT,
        data_collected JSON,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (drone_request_id) REFERENCES drone_requests(id) ON DELETE CASCADE,
        FOREIGN KEY (drone_id) REFERENCES drones(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_drone_request (drone_request_id),
        INDEX idx_created_at (created_at)
    )";
    
    $db->exec($create_table_query);
    
    echo "<h2>✅ Drone Results Table Created Successfully!</h2>";
    echo "<p>The <code>drone_results</code> table has been added to the database.</p>";
    
    echo "<h3>Table Structure:</h3>";
    echo "<ul>";
    echo "<li><strong>id</strong> - Primary key</li>";
    echo "<li><strong>drone_request_id</strong> - Reference to drone_requests table</li>";
    echo "<li><strong>drone_id</strong> - Reference to drones table</li>";
    echo "<li><strong>operation_type</strong> - Type of drone operation</li>";
    echo "<li><strong>area_covered</strong> - Area covered in hectares</li>";
    echo "<li><strong>duration_minutes</strong> - Duration of operation</li>";
    echo "<li><strong>efficiency_score</strong> - Efficiency rating (0-100)</li>";
    echo "<li><strong>coverage_percentage</strong> - Coverage percentage (0-100)</li>";
    echo "<li><strong>issues_encountered</strong> - Any issues during operation</li>";
    echo "<li><strong>recommendations</strong> - Recommendations for future operations</li>";
    echo "<li><strong>data_collected</strong> - JSON data from sensors</li>";
    echo "<li><strong>created_by</strong> - User who created the results</li>";
    echo "<li><strong>created_at</strong> - Timestamp of creation</li>";
    echo "<li><strong>updated_at</strong> - Timestamp of last update</li>";
    echo "</ul>";
    
    echo "<h3>Features Enabled:</h3>";
    echo "<ul>";
    echo "<li>✅ Drone operation results tracking</li>";
    echo "<li>✅ Automatic results generation based on sensor data</li>";
    echo "<li>✅ Manual results entry by planners</li>";
    echo "<li>✅ Results history and analytics</li>";
    echo "<li>✅ Green points rewards for results management</li>";
    echo "</ul>";
    
    echo "<p><a href='farmer_dashboard.php' class='btn btn-primary'>Go to Farmer Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<h2>❌ Error Creating Table</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
