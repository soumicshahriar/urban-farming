<?php
class NotificationHelper {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->getConnection();
    }
    
    /**
     * Create a new notification
     */
    public function createNotification($userId, $title, $message, $type = 'info', $category = 'system', $relatedEntityType = 'system', $relatedEntityId = null) {
        $query = "INSERT INTO notifications (user_id, title, message, type, category, related_entity_type, related_entity_id) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$userId, $title, $message, $type, $category, $relatedEntityType, $relatedEntityId]);
    }
    
    /**
     * Get unread notifications count for a user
     */
    public function getUnreadCount($userId) {
        $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
    
    /**
     * Get notifications for a user (with pagination)
     */
    public function getNotifications($userId, $limit = 10, $offset = 0, $unreadOnly = false) {
        $whereClause = "WHERE user_id = ?";
        if ($unreadOnly) {
            $whereClause .= " AND is_read = FALSE";
        }
        
        // Cast limit and offset to integers and validate
        $limit = max(1, (int)$limit);
        $offset = max(0, (int)$offset);
        
        // Use LIMIT and OFFSET directly in the query since MariaDB doesn't support parameterized LIMIT/OFFSET
        $query = "SELECT * FROM notifications $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $userId) {
        $query = "UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$notificationId, $userId]);
    }
    
    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($userId) {
        $query = "UPDATE notifications SET is_read = TRUE WHERE user_id = ?";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$userId]);
    }
    
    /**
     * Delete old notifications (older than 30 days)
     */
    public function cleanupOldNotifications() {
        $query = "DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute();
    }
    
    /**
     * Create notification for new farm request
     */
    public function notifyNewFarmRequest($farmId, $farmerId, $farmName) {
        // Notify planners
        $planners = $this->getUsersByRole('planner');
        foreach ($planners as $planner) {
            $this->createNotification(
                $planner['id'],
                'New Farm Request Received',
                "A new farm request '$farmName' has been submitted and requires your approval.",
                'info',
                'farm_request',
                'farm',
                $farmId
            );
        }
        
        // Notify admins
        $admins = $this->getUsersByRole('admin');
        foreach ($admins as $admin) {
            $this->createNotification(
                $admin['id'],
                'New Request Submitted by Farmer',
                "A farmer has submitted a new farm request '$farmName' that is pending planner approval.",
                'info',
                'farm_request',
                'farm',
                $farmId
            );
        }
    }
    
    /**
     * Create notification for farm approval
     */
    public function notifyFarmApproval($farmId, $farmerId, $farmName, $approved = true) {
        $status = $approved ? 'approved' : 'rejected';
        $type = $approved ? 'success' : 'warning';
        
        // Notify farmer
        $this->createNotification(
            $farmerId,
            "Your Farm Request Has Been " . ucfirst($status),
            "Your farm request '$farmName' has been $status by the planner.",
            $type,
            'approval',
            'farm',
            $farmId
        );
        
        // Notify admins
        $admins = $this->getUsersByRole('admin');
        foreach ($admins as $admin) {
            $this->createNotification(
                $admin['id'],
                "Planner " . ucfirst($status) . " a Farm Request",
                "A planner has $status the farm request '$farmName'.",
                'info',
                'approval',
                'farm',
                $farmId
            );
        }
    }
    
    /**
     * Create notification for new drone request
     */
    public function notifyNewDroneRequest($droneRequestId, $farmerId, $purpose, $location) {
        // Notify planners
        $planners = $this->getUsersByRole('planner');
        foreach ($planners as $planner) {
            $this->createNotification(
                $planner['id'],
                'New Drone Service Request',
                "A new drone service request for $purpose at $location requires your approval.",
                'info',
                'drone_request',
                'drone_request',
                $droneRequestId
            );
        }
        
        // Notify admins
        $admins = $this->getUsersByRole('admin');
        foreach ($admins as $admin) {
            $this->createNotification(
                $admin['id'],
                'New Drone Request Submitted',
                "A farmer has submitted a new drone service request for $purpose that is pending approval.",
                'info',
                'drone_request',
                'drone_request',
                $droneRequestId
            );
        }
    }
    
    /**
     * Create notification for drone request approval
     */
    public function notifyDroneRequestApproval($droneRequestId, $farmerId, $purpose, $approved = true) {
        $status = $approved ? 'approved' : 'rejected';
        $type = $approved ? 'success' : 'warning';
        
        // Notify farmer
        $this->createNotification(
            $farmerId,
            "Your Drone Request Has Been " . ucfirst($status),
            "Your drone service request for $purpose has been $status.",
            $type,
            'approval',
            'drone_request',
            $droneRequestId
        );
        
        // Notify admins
        $admins = $this->getUsersByRole('admin');
        foreach ($admins as $admin) {
            $this->createNotification(
                $admin['id'],
                "Drone Request " . ucfirst($status),
                "A drone service request for $purpose has been $status by a planner.",
                'info',
                'approval',
                'drone_request',
                $droneRequestId
            );
        }
    }
    
    /**
     * Get users by role
     */
    private function getUsersByRole($role) {
        $query = "SELECT id, username, email FROM users WHERE role = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get notification icon based on type
     */
    public static function getNotificationIcon($type) {
        switch ($type) {
            case 'success':
                return 'fas fa-check-circle';
            case 'warning':
                return 'fas fa-exclamation-triangle';
            case 'error':
                return 'fas fa-times-circle';
            default:
                return 'fas fa-info-circle';
        }
    }
    
    /**
     * Get notification badge color based on type
     */
    public static function getNotificationBadgeColor($type) {
        switch ($type) {
            case 'success':
                return 'bg-success';
            case 'warning':
                return 'bg-warning';
            case 'error':
                return 'bg-danger';
            default:
                return 'bg-info';
        }
    }
    
    /**
     * Format notification time
     */
    public static function formatNotificationTime($timestamp) {
        $time = strtotime($timestamp);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } else {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }
    }
}
?>
