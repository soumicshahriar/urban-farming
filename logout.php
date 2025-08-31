<?php
session_start();
require_once 'config/database.php';

if(isset($_SESSION['user_id'])) {
    try {
        // Log the logout
        $database = new Database();
        $db = $database->getConnection();
        
        // First, verify that the user still exists in the database
        $check_user_query = "SELECT id FROM users WHERE id = ?";
        $check_stmt = $db->prepare($check_user_query);
        $check_stmt->execute([$_SESSION['user_id']]);
        
        if ($check_stmt->rowCount() > 0) {
            // User exists, safe to log the logout
            $log_query = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->execute([$_SESSION['user_id'], 'user_logout', "User logged out", $_SERVER['REMOTE_ADDR']]);
        } else {
            // User doesn't exist, log without user_id (NULL)
            $log_query = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (NULL, ?, ?, ?)";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->execute(['user_logout', "User logged out (user_id not found)", $_SERVER['REMOTE_ADDR']]);
        }
    } catch (PDOException $e) {
        // If logging fails, just continue with logout process
        // Don't let logging errors prevent logout
        error_log("Logout logging failed: " . $e->getMessage());
    }
}

// Destroy session
session_destroy();

// Redirect to login page
header('Location: index.php');
exit();
?>
