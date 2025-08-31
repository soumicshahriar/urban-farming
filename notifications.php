<?php
session_start();
require_once 'config/database.php';
require_once 'includes/NotificationHelper.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$notificationHelper = new NotificationHelper($database);

// Handle mark all as read
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_all_read'])) {
    $notificationHelper->markAllAsRead($_SESSION['user_id']);
    header('Location: notifications.php');
    exit();
}

// Get notifications with pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$notifications = $notificationHelper->getNotifications($_SESSION['user_id'], $limit, $offset);
$unreadCount = $notificationHelper->getUnreadCount($_SESSION['user_id']);

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <div class="sidebar">
            <h5><i class="fas fa-bell me-2"></i>Notifications</h5>
            <hr>
            <div class="mb-3">
                <strong>Welcome, <?php echo $_SESSION['username']; ?>!</strong>
                <div class="green-points mt-2">
                    <i class="fas fa-star me-1"></i><?php echo $_SESSION['green_points']; ?> Green Points
                </div>
            </div>
            
            <div class="list-group">
                <?php if($_SESSION['role'] == 'farmer'): ?>
                    <a href="farmer_dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="farm_requests.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-farm me-2"></i>My Farms
                    </a>
                    <a href="drone_requests.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-drone me-2"></i>Drone Requests
                    </a>
                <?php elseif($_SESSION['role'] == 'planner'): ?>
                    <a href="planner_dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="farm_approvals.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-check-circle me-2"></i>Farm Approvals
                    </a>
                    <a href="drone_approvals.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-drone me-2"></i>Drone Approvals
                    </a>
                <?php elseif($_SESSION['role'] == 'admin'): ?>
                    <a href="admin_dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="global_monitoring.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-globe me-2"></i>Global Monitoring
                    </a>
                    <a href="user_management.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-users me-2"></i>User Management
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-bell me-2"></i>Notifications</h2>
                <div>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="mark_all_read" class="btn btn-outline-primary me-2">
                            <i class="fas fa-check-double me-1"></i>Mark All as Read
                        </button>
                    </form>
                    <a href="<?php echo $_SESSION['role']; ?>_dashboard.php" class="btn btn-blue-color">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
            
            <!-- Notification Stats -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-warning">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $unreadCount; ?>
                            </h5>
                            <p class="card-text">Unread Notifications</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-info">
                                <i class="fas fa-list me-2"></i><?php echo count($notifications); ?>
                            </h5>
                            <p class="card-text">Recent Notifications</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['green_points']; ?>
                            </h5>
                            <p class="card-text">Green Points</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Notifications List -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-bell me-2"></i>All Notifications</h5>
                </div>
                <div class="card-body">
                    <?php if(empty($notifications)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No notifications yet</h5>
                            <p class="text-muted">You're all caught up! New notifications will appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="notifications-list">
                            <?php foreach($notifications as $notification): ?>
                                <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>" 
                                     data-notification-id="<?php echo $notification['id']; ?>">
                                    <div class="d-flex align-items-start">
                                        <div class="notification-icon me-3">
                                            <i class="<?php echo NotificationHelper::getNotificationIcon($notification['type']); ?> fa-2x 
                                                       <?php echo NotificationHelper::getNotificationBadgeColor($notification['type']); ?>"></i>
                                        </div>
                                        <div class="notification-content flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <h6 class="notification-title mb-1">
                                                    <?php echo htmlspecialchars($notification['title']); ?>
                                                    <?php if(!$notification['is_read']): ?>
                                                        <span class="badge bg-danger ms-2">New</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?php echo NotificationHelper::formatNotificationTime($notification['created_at']); ?>
                                                </small>
                                            </div>
                                            <p class="notification-message mb-2">
                                                <?php echo htmlspecialchars($notification['message']); ?>
                                            </p>
                                            <div class="notification-meta">
                                                <span class="badge bg-secondary me-2"><?php echo ucfirst($notification['category']); ?></span>
                                                <?php if(!$notification['is_read']): ?>
                                                    <button class="btn btn-sm btn-outline-primary mark-read-btn" 
                                                            onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                                        <i class="fas fa-check me-1"></i>Mark as Read
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if(count($notifications) == $limit): ?>
                            <nav aria-label="Notifications pagination">
                                <ul class="pagination justify-content-center">
                                    <?php if($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    <li class="page-item active">
                                        <span class="page-link">Page <?php echo $page; ?></span>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.notification-item {
    padding: 15px;
    border-radius: 8px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.notification-item:hover {
    background-color: #f8f9fa;
    transform: translateY(-2px);
}

.notification-item.unread {
    background-color: #e3f2fd;
    border-left: 4px solid #2196f3;
}

.notification-item.read {
    opacity: 0.8;
}

.notification-icon {
    flex-shrink: 0;
}

.notification-title {
    font-weight: 600;
    color: #333;
}

.notification-message {
    color: #666;
    line-height: 1.5;
}

.notification-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.mark-read-btn {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
}

.notifications-list {
    max-height: 600px;
    overflow-y: auto;
}
</style>

<script>
function markAsRead(notificationId) {
    fetch('get_notifications.php?action=mark_as_read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notification_id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            // Update UI
            const notificationItem = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if(notificationItem) {
                notificationItem.classList.remove('unread');
                notificationItem.classList.add('read');
                
                // Remove "New" badge
                const badge = notificationItem.querySelector('.badge.bg-danger');
                if(badge) badge.remove();
                
                // Remove mark as read button
                const markReadBtn = notificationItem.querySelector('.mark-read-btn');
                if(markReadBtn) markReadBtn.remove();
                
                // Update unread count
                updateUnreadCount();
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

function updateUnreadCount() {
    fetch('get_notifications.php?action=get_unread_count')
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            // Update the unread count display
            const unreadCountElement = document.querySelector('.card-title.text-warning');
            if(unreadCountElement) {
                unreadCountElement.innerHTML = `<i class="fas fa-exclamation-circle me-2"></i>${data.count}`;
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

// Auto-refresh notifications every 30 seconds
setInterval(function() {
    location.reload();
}, 30000);
</script>

<?php include 'includes/footer.php'; ?>
