<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user role for navigation
$user_role = $_SESSION['role'];

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

$success_message = '';
$error_message = '';

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        
        // Validate input
        if (empty($username) || empty($email)) {
            $error_message = "Username and email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            try {
                // Check if username already exists (excluding current user)
                $check_username = "SELECT id FROM users WHERE username = ? AND id != ?";
                $check_stmt = $db->prepare($check_username);
                $check_stmt->execute([$username, $user_id]);
                
                if ($check_stmt->rowCount() > 0) {
                    $error_message = "Username already exists. Please choose a different one.";
                } else {
                    // Update user profile
                    $update_query = "UPDATE users SET 
                        username = ?, 
                        email = ? 
                        WHERE id = ?";
                    
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([
                        $username, $email, $user_id
                    ]);
                    
                    // Update session data
                    $_SESSION['username'] = $username;
                    $_SESSION['email'] = $email;
                    
                    $success_message = "Profile updated successfully!";
                }
            } catch (Exception $e) {
                $error_message = "Error updating profile: " . $e->getMessage();
            }
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "New password must be at least 6 characters long.";
        } else {
            try {
                // Verify current password
                $verify_query = "SELECT password FROM users WHERE id = ?";
                $verify_stmt = $db->prepare($verify_query);
                $verify_stmt->execute([$user_id]);
                $user = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($current_password, $user['password'])) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $password_query = "UPDATE users SET password = ? WHERE id = ?";
                    $password_stmt = $db->prepare($password_query);
                    $password_stmt->execute([$hashed_password, $user_id]);
                    
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "Current password is incorrect.";
                }
            } catch (Exception $e) {
                $error_message = "Error changing password: " . $e->getMessage();
            }
        }
    }
}

// Get user profile data
$profile_query = "SELECT * FROM users WHERE id = ?";
$profile_stmt = $db->prepare($profile_query);
$profile_stmt->execute([$user_id]);
$user_profile = $profile_stmt->fetch(PDO::FETCH_ASSOC);

// Get user statistics based on role
$user_stats = ['total_farms' => 0, 'total_drone_requests' => 0, 'total_listings' => 0, 'total_purchases' => 0];

if ($user_role == 'farmer') {
    $stats_query = "SELECT 
        (SELECT COUNT(*) FROM farms WHERE farmer_id = ?) as total_farms,
        (SELECT COUNT(*) FROM drone_requests WHERE farmer_id = ?) as total_drone_requests,
        (SELECT COUNT(*) FROM seed_listings WHERE seller_id = ?) as total_listings,
        (SELECT COUNT(*) FROM seed_sales WHERE buyer_id = ?) as total_purchases";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute([$user_id, $user_id, $user_id, $user_id]);
    $user_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($user_role == 'planner') {
    $stats_query = "SELECT 
        (SELECT COUNT(*) FROM farms WHERE status = 'approved') as total_farms_approved,
        (SELECT COUNT(*) FROM drone_requests WHERE approved_by = ?) as total_drone_requests_approved,
        0 as total_listings,
        0 as total_purchases";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute([$user_id]);
    $user_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($user_role == 'admin') {
    $stats_query = "SELECT 
        (SELECT COUNT(*) FROM farms) as total_farms,
        (SELECT COUNT(*) FROM drone_requests) as total_drone_requests,
        (SELECT COUNT(*) FROM seed_listings) as total_listings,
        (SELECT COUNT(*) FROM seed_sales) as total_purchases";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $user_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
}

// Get recent activity based on role
$recent_activity = [];

if ($user_role == 'farmer') {
    $recent_activity_query = "
        (SELECT 'farm_created' as type, name as title, created_at, 'Created new farm' as description
         FROM farms WHERE farmer_id = ? ORDER BY created_at DESC LIMIT 3)
        UNION ALL
        (SELECT 'drone_request' as type, purpose as title, created_at, 'Requested drone service' as description
         FROM drone_requests WHERE farmer_id = ? ORDER BY created_at DESC LIMIT 3)
        UNION ALL
        (SELECT 'seed_listing' as type, seed_type as title, created_at, 'Listed seeds for sale' as description
         FROM seed_listings WHERE seller_id = ? ORDER BY created_at DESC LIMIT 3)
        ORDER BY created_at DESC LIMIT 5";
    $activity_stmt = $db->prepare($recent_activity_query);
    $activity_stmt->execute([$user_id, $user_id, $user_id]);
    $recent_activity = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($user_role == 'planner') {
    $recent_activity_query = "
        (SELECT 'drone_approved' as type, purpose as title, approved_at as created_at, 'Approved drone request' as description
         FROM drone_requests WHERE approved_by = ? AND approved_at IS NOT NULL ORDER BY approved_at DESC LIMIT 5)";
    $activity_stmt = $db->prepare($recent_activity_query);
    $activity_stmt->execute([$user_id]);
    $recent_activity = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($user_role == 'admin') {
    $recent_activity_query = "
        (SELECT 'system_admin' as type, 'System Management' as title, created_at, 'Admin activity' as description
         FROM system_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5)";
    $activity_stmt = $db->prepare($recent_activity_query);
    $activity_stmt->execute([$user_id]);
    $recent_activity = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <div class="sidebar">
            <h5><i class="fas fa-user me-2"></i>Profile</h5>
            <hr>
            <div class="list-group">
                <a href="profile.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-user-edit me-2"></i>Edit Profile
                </a>
                <?php if($user_role == 'farmer'): ?>
                    <a href="farmer_dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="green_points.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-star me-2"></i>Green Points
                    </a>
                    <a href="marketplace.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-store me-2"></i>Marketplace
                    </a>
                <?php elseif($user_role == 'planner'): ?>
                    <a href="planner_dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="drone_approvals.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-drone me-2"></i>Drone Approvals
                    </a>
                    <a href="farm_approvals.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-farm me-2"></i>Farm Approvals
                    </a>
                <?php elseif($user_role == 'admin'): ?>
                    <a href="admin_dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="admin_green_points.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-star me-2"></i>Green Points Admin
                    </a>
                    <a href="seed_approvals.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-seedling me-2"></i>Seed Approvals
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
                <h2><i class="fas fa-user me-2"></i>My Profile</h2>
                <div>
                    <span class="badge bg-success fs-6">
                        <i class="fas fa-star me-1"></i><?php echo $user_profile['green_points']; ?> Green Points
                    </span>
                </div>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <!-- Profile Overview -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <div class="mb-3">
                                <i class="fas fa-user-circle fa-5x text-primary"></i>
                            </div>
                                                         <h5 class="card-title"><?php echo htmlspecialchars($user_profile['username']); ?></h5>
                            <p class="card-text text-muted"><?php echo ucfirst($user_profile['role']); ?></p>
                            <div class="mt-3">
                                <span class="badge bg-primary">Member since <?php echo date('M Y', strtotime($user_profile['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="row">
                                                 <div class="col-md-3">
                             <div class="card dashboard-stats">
                                 <div class="card-body text-center">
                                     <i class="fas fa-farm fa-2x mb-2 text-primary"></i>
                                     <h4><?php echo $user_role == 'planner' ? $user_stats['total_farms_approved'] : $user_stats['total_farms']; ?></h4>
                                     <p class="mb-0"><?php echo $user_role == 'admin' ? 'Total Farms' : ($user_role == 'planner' ? 'Farms Approved' : 'Farms'); ?></p>
                                 </div>
                             </div>
                         </div>
                         <div class="col-md-3">
                             <div class="card dashboard-stats">
                                 <div class="card-body text-center">
                                     <i class="fas fa-drone fa-2x mb-2 text-info"></i>
                                     <h4><?php echo $user_role == 'planner' ? $user_stats['total_drone_requests_approved'] : $user_stats['total_drone_requests']; ?></h4>
                                     <p class="mb-0"><?php echo $user_role == 'admin' ? 'Total Requests' : ($user_role == 'planner' ? 'Requests Approved' : 'Drone Requests'); ?></p>
                                 </div>
                             </div>
                         </div>
                         <div class="col-md-3">
                             <div class="card dashboard-stats">
                                 <div class="card-body text-center">
                                     <i class="fas fa-seedling fa-2x mb-2 text-success"></i>
                                     <h4><?php echo $user_stats['total_listings']; ?></h4>
                                     <p class="mb-0"><?php echo $user_role == 'admin' ? 'Total Listings' : 'Listings'; ?></p>
                                 </div>
                             </div>
                         </div>
                         <div class="col-md-3">
                             <div class="card dashboard-stats">
                                 <div class="card-body text-center">
                                     <i class="fas fa-shopping-cart fa-2x mb-2 text-warning"></i>
                                     <h4><?php echo $user_stats['total_purchases']; ?></h4>
                                     <p class="mb-0"><?php echo $user_role == 'admin' ? 'Total Sales' : 'Purchases'; ?></p>
                                 </div>
                             </div>
                         </div>
                    </div>
                </div>
            </div>
            
                         <!-- Profile Forms -->
             <div class="row">
                 <!-- Personal Information -->
                 <div class="col-md-6">
                     <div class="card">
                         <div class="card-header">
                             <h5><i class="fas fa-user-edit me-2"></i>Personal Information</h5>
                         </div>
                         <div class="card-body">
                             <form method="POST">
                                 <div class="mb-3">
                                     <label for="username" class="form-label">Username</label>
                                     <input type="text" class="form-control" id="username" name="username" 
                                            value="<?php echo htmlspecialchars($user_profile['username']); ?>" required>
                                 </div>
                                 
                                 <div class="mb-3">
                                     <label for="email" class="form-label">Email</label>
                                     <input type="email" class="form-control" id="email" name="email" 
                                            value="<?php echo htmlspecialchars($user_profile['email']); ?>" required>
                                 </div>
                                 
                                 <button type="submit" name="update_profile" class="btn btn-blue-color">
                                     <i class="fas fa-save me-2"></i>Update Profile
                                 </button>
                             </form>
                         </div>
                     </div>
                 </div>
                 
                 <!-- Account Information -->
                 <div class="col-md-6">
                     <div class="card">
                         <div class="card-header">
                             <h5><i class="fas fa-info-circle me-2"></i>Account Information</h5>
                         </div>
                         <div class="card-body">
                             <div class="row">
                                 <div class="col-md-6">
                                     <table class="table table-borderless">
                                         <tr>
                                             <td><strong>User ID:</strong></td>
                                             <td><?php echo $user_profile['id']; ?></td>
                                         </tr>
                                         <tr>
                                             <td><strong>Role:</strong></td>
                                             <td><span class="badge bg-primary"><?php echo ucfirst($user_profile['role']); ?></span></td>
                                         </tr>
                                         <tr>
                                             <td><strong>Account Created:</strong></td>
                                             <td><?php echo date('F j, Y g:i A', strtotime($user_profile['created_at'])); ?></td>
                                         </tr>
                                         <tr>
                                             <td><strong>Last Updated:</strong></td>
                                             <td><?php echo $user_profile['updated_at'] ? date('F j, Y g:i A', strtotime($user_profile['updated_at'])) : 'Never'; ?></td>
                                         </tr>
                                     </table>
                                 </div>
                                 <div class="col-md-6">
                                     <table class="table table-borderless">
                                         <tr>
                                             <td><strong>Green Points:</strong></td>
                                             <td><span class="badge bg-success fs-6"><?php echo $user_profile['green_points']; ?> points</span></td>
                                         </tr>
                                         <tr>
                                             <td><strong>Status:</strong></td>
                                             <td><span class="badge bg-success">Active</span></td>
                                         </tr>
                                         <tr>
                                             <td><strong>Email Verified:</strong></td>
                                             <td><span class="badge bg-success">Yes</span></td>
                                         </tr>
                                     </table>
                                 </div>
                             </div>
                         </div>
                     </div>
                 </div>
             </div>
            
            <!-- Change Password -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-lock me-2"></i>Change Password</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           minlength="6" required>
                                    <div class="form-text">Password must be at least 6 characters long.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-warning">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-history me-2"></i>Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <?php if(empty($recent_activity)): ?>
                                <p class="text-muted">No recent activity.</p>
                            <?php else: ?>
                                <?php foreach($recent_activity as $activity): ?>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="flex-shrink-0">
                                            <?php if($activity['type'] == 'farm_created'): ?>
                                                <i class="fas fa-farm fa-2x text-primary"></i>
                                            <?php elseif($activity['type'] == 'drone_request'): ?>
                                                <i class="fas fa-drone fa-2x text-info"></i>
                                            <?php elseif($activity['type'] == 'seed_listing'): ?>
                                                <i class="fas fa-seedling fa-2x text-success"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($activity['title']); ?></h6>
                                            <p class="mb-1 text-muted"><?php echo $activity['description']; ?></p>
                                            <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

document.getElementById('new_password').addEventListener('input', function() {
    const confirmPassword = document.getElementById('confirm_password');
    if (confirmPassword.value) {
        if (this.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
});
</script>

<style>
.progress {
    background-color: #e9ecef;
}

.progress-bar {
    background-color: #28a745;
}
</style>

<?php include 'includes/footer.php'; ?>
