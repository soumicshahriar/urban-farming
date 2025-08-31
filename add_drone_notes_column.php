<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Add missing notes column to drones table
    $alter_query = "ALTER TABLE drones ADD COLUMN notes TEXT AFTER last_maintenance";
    $db->exec($alter_query);
    
    echo "<!DOCTYPE html>";
    echo "<html><head><title>Drone Notes Column Added</title>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
    echo "</head><body class='bg-light'>";
    echo "<div class='container mt-5'>";
    echo "<div class='row justify-content-center'>";
    echo "<div class='col-md-8'>";
    echo "<div class='card shadow'>";
    echo "<div class='card-body text-center'>";
    echo "<h2 class='text-success mb-4'>✅ Drone Notes Column Added Successfully!</h2>";
    echo "<p class='lead'>The missing <code>notes</code> column has been added to the <code>drones</code> table.</p>";
    echo "<hr>";
    echo "<h4>Column Added:</h4>";
    echo "<ul class='list-unstyled'>";
    echo "<li>✅ <strong>notes</strong> - TEXT column for storing drone notes and comments</li>";
    echo "</ul>";
    echo "<hr>";
    echo "<h4>What's Fixed:</h4>";
    echo "<ul class='list-unstyled'>";
    echo "<li>✅ Drone Inventory will no longer show warnings</li>";
    echo "<li>✅ Admins can add notes to drones</li>";
    echo "<li>✅ Better drone management and tracking</li>";
    echo "<li>✅ Notes will be displayed in drone details</li>";
    echo "</ul>";
    echo "<hr>";
    echo "<div class='d-grid gap-2 d-md-block'>";
    echo "<a href='drone_inventory.php' class='btn btn-primary me-md-2'>Go to Drone Inventory</a>";
    echo "<a href='admin_dashboard.php' class='btn btn-success me-md-2'>Go to Admin Dashboard</a>";
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
    echo "<h2 class='text-danger mb-4'>❌ Error Adding Column</h2>";
    echo "<p class='text-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<a href='index.php' class='btn btn-primary'>Go to Home</a>";
    echo "</div></div></div></div></div>";
    echo "</body></html>";
}
?>
