<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Create notifications table
    $create_table_query = "
    CREATE TABLE IF NOT EXISTS notifications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
        category ENUM('farm_request', 'drone_request', 'approval', 'system', 'marketplace') DEFAULT 'system',
        related_entity_type ENUM('farm', 'drone_request', 'seed_listing', 'system') DEFAULT 'system',
        related_entity_id INT,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_read (user_id, is_read),
        INDEX idx_created_at (created_at)
    )";
    
    $db->exec($create_table_query);
    
    echo "✅ Notifications table created successfully!\n";
    echo "🎉 Real-time notification system is now ready!\n";
    echo "\nFeatures implemented:\n";
    echo "🔔 Notification bell with badge in header\n";
    echo "📱 Real-time notification updates\n";
    echo "📋 Dedicated notifications page\n";
    echo "✅ Mark as read functionality\n";
    echo "🔄 Auto-refresh every 30 seconds\n";
    echo "\nNotification triggers:\n";
    echo "• New farm requests → Notify planners & admins\n";
    echo "• Farm approvals/rejections → Notify farmers & admins\n";
    echo "• New drone requests → Notify planners & admins\n";
    echo "• Drone request approvals/rejections → Notify farmers & admins\n";
    
} catch (Exception $e) {
    echo "❌ Error setting up notifications: " . $e->getMessage() . "\n";
}
?>
