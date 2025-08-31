<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Add missing description column to seed_listings table
    $alter_query = "ALTER TABLE seed_listings ADD COLUMN description TEXT AFTER is_non_gmo";
    $db->exec($alter_query);
    
    echo "<!DOCTYPE html>";
    echo "<html><head><title>Seed Listing Description Added</title>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
    echo "</head><body class='bg-light'>";
    echo "<div class='container mt-5'>";
    echo "<div class='row justify-content-center'>";
    echo "<div class='col-md-8'>";
    echo "<div class='card shadow'>";
    echo "<div class='card-body text-center'>";
    echo "<h2 class='text-success mb-4'>✅ Seed Listing Description Added Successfully!</h2>";
    echo "<p class='lead'>The missing <code>description</code> column has been added to the <code>seed_listings</code> table.</p>";
    echo "<hr>";
    echo "<h4>Column Added:</h4>";
    echo "<ul class='list-unstyled'>";
    echo "<li>✅ <strong>description</strong> - TEXT field for detailed seed descriptions</li>";
    echo "</ul>";
    echo "<hr>";
    echo "<h4>What's Fixed:</h4>";
    echo "<ul class='list-unstyled'>";
    echo "<li>✅ Sell Seeds page will no longer crash</li>";
    echo "<li>✅ Farmers can add detailed descriptions to their seed listings</li>";
    echo "<li>✅ Better seed marketplace with more information</li>";
    echo "<li>✅ Enhanced user experience for seed buyers</li>";
    echo "</ul>";
    echo "<hr>";
    echo "<div class='d-grid gap-2 d-md-block'>";
    echo "<a href='sell_seeds.php' class='btn btn-primary me-md-2'>Go to Sell Seeds</a>";
    echo "<a href='marketplace.php' class='btn btn-success me-md-2'>Go to Marketplace</a>";
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
    echo "<h2 class='text-danger mb-4'>❌ Error Adding Description Column</h2>";
    echo "<p class='text-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<a href='index.php' class='btn btn-primary'>Go to Home</a>";
    echo "</div></div></div></div></div>";
    echo "</body></html>";
}
?>
