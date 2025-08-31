<?php
session_start();
require_once 'config/database.php';
require_once 'includes/NotificationHelper.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$database = new Database();
$notificationHelper = new NotificationHelper($database);

$action = $_GET['action'] ?? 'get_notifications';
$userId = $_SESSION['user_id'];

switch($action) {
    case 'get_notifications':
        $limit = $_GET['limit'] ?? 10;
        $offset = $_GET['offset'] ?? 0;
        $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
        
        $notifications = $notificationHelper->getNotifications($userId, $limit, $offset, $unreadOnly);
        
        // Format notifications for response
        $formattedNotifications = [];
        foreach($notifications as $notification) {
            $formattedNotifications[] = [
                'id' => $notification['id'],
                'title' => $notification['title'],
                'message' => $notification['message'],
                'type' => $notification['type'],
                'category' => $notification['category'],
                'is_read' => (bool)$notification['is_read'],
                'created_at' => $notification['created_at'],
                'formatted_time' => NotificationHelper::formatNotificationTime($notification['created_at']),
                'icon' => NotificationHelper::getNotificationIcon($notification['type']),
                'badge_color' => NotificationHelper::getNotificationBadgeColor($notification['type'])
            ];
        }
        
        echo json_encode([
            'success' => true,
            'notifications' => $formattedNotifications
        ]);
        break;
        
    case 'get_unread_count':
        $count = $notificationHelper->getUnreadCount($userId);
        echo json_encode([
            'success' => true,
            'count' => $count
        ]);
        break;
        
    case 'mark_as_read':
        if($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit();
        }
        
        $notificationId = $_POST['notification_id'] ?? null;
        if(!$notificationId) {
            http_response_code(400);
            echo json_encode(['error' => 'Notification ID is required']);
            exit();
        }
        
        $success = $notificationHelper->markAsRead($notificationId, $userId);
        echo json_encode([
            'success' => $success
        ]);
        break;
        
    case 'mark_all_as_read':
        if($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit();
        }
        
        $success = $notificationHelper->markAllAsRead($userId);
        echo json_encode([
            'success' => $success
        ]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>
