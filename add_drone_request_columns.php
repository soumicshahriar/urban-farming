<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Add missing columns to drone_requests table
    $alter_queries = [
        "ALTER TABLE drone_requests ADD COLUMN approved_by INT AFTER status",
        "ALTER TABLE drone_requests ADD COLUMN approved_at TIMESTAMP NULL AFTER approved_by",
        "ALTER TABLE drone_requests ADD COLUMN notes TEXT AFTER approved_at",
        "ALTER TABLE drone_requests ADD FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL"
    ];
    
    foreach ($alter_queries as $query) {
        $db->exec($query);
    }
    
    echo "<!DOCTYPE html>";
    echo "<html><head><title>Drone Request Columns Added</title>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
    echo "</head><body class='bg-light'>";
    echo "<div class='container mt-5'>";
    echo "<div class='row justify-content-center'>";
    echo "<div class='col-md-8'>";
    echo "<div class='card shadow'>";
    echo "<div class='card-body text-center'>";
    echo "<h2 class='text-success mb-4'>✅ Drone Request Columns Added Successfully!</h2>";
    echo "<p class='lead'>The missing columns have been added to the <code>drone_requests</code> table.</p>";
    echo "<hr>";
    echo "<h4>Columns Added:</h4>";
    echo "<ul class='list-unstyled'>";
    echo "<li>✅ <strong>approved_by</strong> - Reference to the user who approved/rejected</li>";
    echo "<li>✅ <strong>approved_at</strong> - Timestamp when the request was approved/rejected</li>";
    echo "<li>✅ <strong>notes</strong> - Notes about the approval/rejection decision</li>";
    echo "</ul>";
    echo "<hr>";
    echo "<h4>What's Fixed:</h4>";
    echo "<ul class='list-unstyled'>";
    echo "<li>✅ Drone Approvals will no longer crash</li>";
    echo "<li>✅ Planners can add notes when approving/rejecting drone requests</li>";
    echo "<li>✅ Approval decisions can be tracked with timestamps</li>";
    echo "<li>✅ Better audit trail for drone request approvals</li>";
    echo "</ul>";
    echo "<hr>";
    echo "<div class='d-grid gap-2 d-md-block'>";
    echo "<a href='drone_approvals.php' class='btn btn-primary me-md-2'>Go to Drone Approvals</a>";
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
    echo "<h2 class='text-danger mb-4'>❌ Error Adding Columns</h2>";
    echo "<p class='text-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<a href='index.php' class='btn btn-primary'>Go to Home</a>";
    echo "</div></div></div></div></div>";
    echo "</body></html>";
}
?>
