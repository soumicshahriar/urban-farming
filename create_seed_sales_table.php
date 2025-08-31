<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Create seed_sales table
    $create_table_query = "
    CREATE TABLE IF NOT EXISTS seed_sales (
        id INT PRIMARY KEY AUTO_INCREMENT,
        listing_id INT NOT NULL,
        buyer_id INT NOT NULL,
        seller_id INT NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        green_points_earned_buyer INT DEFAULT 0,
        green_points_earned_seller INT DEFAULT 0,
        status ENUM('completed', 'pending', 'cancelled') DEFAULT 'completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (listing_id) REFERENCES seed_listings(id) ON DELETE CASCADE,
        FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_buyer_id (buyer_id),
        INDEX idx_seller_id (seller_id),
        INDEX idx_transaction_date (transaction_date)
    )";
    
    $db->exec($create_table_query);
    
    echo "<!DOCTYPE html>";
    echo "<html><head><title>Seed Sales Table Created</title>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
    echo "</head><body class='bg-light'>";
    echo "<div class='container mt-5'>";
    echo "<div class='row justify-content-center'>";
    echo "<div class='col-md-8'>";
    echo "<div class='card shadow'>";
    echo "<div class='card-body text-center'>";
    echo "<h2 class='text-success mb-4'>✅ Seed Sales Table Created Successfully!</h2>";
    echo "<p class='lead'>The <code>seed_sales</code> table has been created to track seed purchase transactions.</p>";
    echo "<hr>";
    echo "<h4>Table Structure:</h4>";
    echo "<ul class='list-unstyled'>";
    echo "<li>✅ <strong>id</strong> - Primary key</li>";
    echo "<li>✅ <strong>listing_id</strong> - Reference to seed listing</li>";
    echo "<li>✅ <strong>buyer_id</strong> - Reference to buyer user</li>";
    echo "<li>✅ <strong>seller_id</strong> - Reference to seller user</li>";
    echo "<li>✅ <strong>quantity</strong> - Number of units purchased</li>";
    echo "<li>✅ <strong>unit_price</strong> - Price per unit</li>";
    echo "<li>✅ <strong>total_price</strong> - Total transaction amount</li>";
    echo "<li>✅ <strong>transaction_date</strong> - When the purchase occurred</li>";
    echo "<li>✅ <strong>green_points_earned</strong> - Green points for buyer and seller</li>";
    echo "<li>✅ <strong>status</strong> - Transaction status</li>";
    echo "</ul>";
    echo "<hr>";
    echo "<h4>What's Fixed:</h4>";
    echo "<ul class='list-unstyled'>";
    echo "<li>✅ Purchase History will no longer crash</li>";
    echo "<li>✅ Seed sales transactions can be tracked</li>";
    echo "<li>✅ Green points can be awarded for purchases</li>";
    echo "<li>✅ Better transaction history and reporting</li>";
    echo "</ul>";
    echo "<hr>";
    echo "<div class='d-grid gap-2 d-md-block'>";
    echo "<a href='purchase_history.php' class='btn btn-primary me-md-2'>Go to Purchase History</a>";
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
    echo "<h2 class='text-danger mb-4'>❌ Error Creating Table</h2>";
    echo "<p class='text-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<a href='index.php' class='btn btn-primary'>Go to Home</a>";
    echo "</div></div></div></div></div>";
    echo "</body></html>";
}
?>
