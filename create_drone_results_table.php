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
    
    echo "<!DOCTYPE html>";
    echo "<html><head><title>Drone Results Table Created</title>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
    echo "</head><body class='bg-light'>";
    echo "<div class='container mt-5'>";
    echo "<div class='row justify-content-center'>";
    echo "<div class='col-md-8'>";
    echo "<div class='card shadow'>";
    echo "<div class='card-body text-center'>";
    echo "<h2 class='text-success mb-4'>✅ Drone Results Table Created Successfully!</h2>";
    echo "<p class='lead'>The <code>drone_results</code> table has been added to the database.</p>";
    echo "<hr>";
    echo "<h4>What's Fixed:</h4>";
    echo "<ul class='list-unstyled'>";
    echo "<li>✅ Farmer Dashboard will no longer crash</li>";
    echo "<li>✅ Drone Results functionality is now available</li>";
    echo "<li>✅ Planners can add drone operation results</li>";
    echo "<li>✅ Farmers can view their drone results</li>";
    echo "</ul>";
    echo "<hr>";
    echo "<div class='d-grid gap-2 d-md-block'>";
    echo "<a href='farmer_dashboard.php' class='btn btn-primary me-md-2'>Go to Farmer Dashboard</a>";
    echo "<a href='index.php' class='btn btn-secondary'>Go to Home</a>";
    echo "</div>";
    echo "</div></div></div></div></div>";
    echo "</body></html>";
    
} catch (PDOException $e) {
    echo "<!DOCTYPE html>";
    echo "<html><head><title>Error</title>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
    echo "</head><body class='bg-light'>";
    echo "<div class='container mt-5'>";
    echo "<div class='row justify-content-center'>";
    echo "<div class='col-md-8'>";
    echo "<div class='card shadow'>";
    echo "<div class='card-body text-center'>";
    echo "<h2 class='text-danger mb-4'>❌ Error Creating Table</h2>";
    echo "<p class='text-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<a href='index.php' class='btn btn-primary'>Go to Home</a>";
    echo "</div></div></div></div></div>";
    echo "</body></html>";
}
?>
