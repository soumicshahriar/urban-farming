<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is an admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $listing_id = $_POST['listing_id'] ?? 0;
    $notes = $_POST['notes'] ?? '';
    
    if($action && $listing_id) {
        switch($action) {
            case 'approve_listing':
                try {
                    $update_query = "UPDATE seed_listings SET status = 'available', approved_by = ?, approved_at = NOW(), notes = ? WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $success = $update_stmt->execute([$_SESSION['user_id'], $notes, $listing_id]);
                } catch (PDOException $e) {
                    // If approved_by, approved_at, or notes columns don't exist, update without them
                    $update_query = "UPDATE seed_listings SET status = 'available' WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $success = $update_stmt->execute([$listing_id]);
                }
                
                if($success) {
                    $success_message = "Seed listing approved successfully!";
                    
                    // Log the action
                    $log_query = "INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->execute([$_SESSION['user_id'], 'seed_listing_approved', "Listing ID: $listing_id"]);
                } else {
                    $error_message = "Failed to approve listing.";
                }
                break;
                
            case 'reject_listing':
                try {
                    $update_query = "UPDATE seed_listings SET status = 'rejected', approved_by = ?, approved_at = NOW(), notes = ? WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $success = $update_stmt->execute([$_SESSION['user_id'], $notes, $listing_id]);
                } catch (PDOException $e) {
                    // If approved_by, approved_at, or notes columns don't exist, update without them
                    $update_query = "UPDATE seed_listings SET status = 'rejected' WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $success = $update_stmt->execute([$listing_id]);
                }
                
                if($success) {
                    $success_message = "Seed listing rejected successfully!";
                    
                    // Log the action
                    $log_query = "INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->execute([$_SESSION['user_id'], 'seed_listing_rejected', "Listing ID: $listing_id"]);
                } else {
                    $error_message = "Failed to reject listing.";
                }
                break;
        }
    }
}

// Get pending seed listings
$listings_query = "SELECT sl.*, u.username, u.email 
                   FROM seed_listings sl
                   LEFT JOIN users u ON sl.seller_id = u.id
                   ORDER BY sl.created_at DESC";

$listings_stmt = $db->prepare($listings_query);
$listings_stmt->execute();
$seed_listings = $listings_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <div class="sidebar">
                <h5><i class="fas fa-seedling me-2"></i>Seed Approvals</h5>
                <hr>
                <div class="mb-3">
                    <strong>Welcome, <?php echo $_SESSION['username']; ?>!</strong>
                    <div class="green-points mt-2">
                        <i class="fas fa-star me-1"></i><?php echo $_SESSION['green_points']; ?> Green Points
                    </div>
                </div>
                
                <div class="list-group">
                    <a href="admin_dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="global_monitoring.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-line me-2"></i>Global Monitoring
                    </a>
                    <a href="user_management.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-users me-2"></i>User Management
                    </a>
                    <a href="seed_approvals.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-seedling me-2"></i>Seed Approvals
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-seedling me-2"></i>Seed Listing Approvals</h2>
                </div>
                
                <?php if(isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Seed Listings Table -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>Seed Listings</h5>
                    </div>
                    <div class="card-body">
                                                 <?php if(empty($seed_listings)): ?>
                             <div class="text-center py-4">
                                 <i class="fas fa-seedling fa-3x text-muted mb-3"></i>
                                 <p class="text-muted">No seed listings found.</p>
                                 <p class="text-muted">Farmers need to create seed listings first.</p>
                             </div>
                         <?php else: ?>
                             <?php 
                             $pending_count = 0;
                             foreach($seed_listings as $listing) {
                                 if($listing['status'] == 'pending') $pending_count++;
                             }
                             ?>
                             <?php if($pending_count == 0): ?>
                                 <div class="alert alert-info mb-4">
                                     <i class="fas fa-info-circle me-2"></i>
                                     <strong>No pending listings to review.</strong> All current listings have been processed.
                                     <br>
                                     <small class="text-muted">New seed listings created by farmers will appear here for approval.</small>
                                 </div>
                             <?php endif; ?>
                             
                             <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Listing</th>
                                            <th>Seller</th>
                                            <th>Details</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($seed_listings as $listing): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar me-3">
                                                            <i class="fas fa-seedling fa-2x text-success"></i>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($listing['seed_type']); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($listing['location']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <strong><?php echo htmlspecialchars($listing['username']); ?></strong>
                                                        <br>
                                                        <span class="text-muted"><?php echo htmlspecialchars($listing['email']); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <div><strong>Type:</strong> <?php echo ucfirst($listing['seed_type']); ?></div>
                                                        <div><strong>Price:</strong> $<?php echo number_format($listing['price'], 2); ?></div>
                                                        <div><strong>Quantity:</strong> <?php echo number_format($listing['quantity']); ?> units</div>
                                                        <div class="text-muted">
                                                            <?php if($listing['is_organic']): ?>
                                                                <span class="badge bg-success me-1">Organic</span>
                                                            <?php endif; ?>
                                                            <?php if($listing['is_non_gmo']): ?>
                                                                <span class="badge bg-warning me-1">Non-GMO</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                                                                 <td>
                                                     <?php if($listing['status'] == 'pending'): ?>
                                                         <span class="badge bg-warning">Pending Review</span>
                                                     <?php elseif($listing['status'] == 'available'): ?>
                                                         <span class="badge bg-success">Approved</span>
                                                     <?php elseif($listing['status'] == 'rejected'): ?>
                                                         <span class="badge bg-danger">Rejected</span>
                                                     <?php elseif($listing['status'] == 'sold'): ?>
                                                         <span class="badge bg-info">Sold</span>
                                                     <?php endif; ?>
                                                 </td>
                                                <td>
                                                    <div class="small">
                                                        <strong><?php echo date('M j, Y', strtotime($listing['created_at'])); ?></strong>
                                                        <br>
                                                        <span class="text-muted"><?php echo date('g:i A', strtotime($listing['created_at'])); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <?php if($listing['status'] == 'pending'): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-success" onclick="approveListing(<?php echo $listing['id']; ?>)">
                                                                <i class="fas fa-check"></i> Approve
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="rejectListing(<?php echo $listing['id']; ?>)">
                                                                <i class="fas fa-times"></i> Reject
                                                            </button>
                                                        <?php elseif($listing['status'] == 'available'): ?>
                                                            <span class="text-success"><i class="fas fa-check-circle"></i> Approved</span>
                                                        <?php elseif($listing['status'] == 'rejected'): ?>
                                                            <span class="text-danger"><i class="fas fa-times-circle"></i> Rejected</span>
                                                        <?php elseif($listing['status'] == 'sold'): ?>
                                                            <span class="text-info"><i class="fas fa-shopping-cart"></i> Sold</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function approveListing(listingId) {
    if (confirm('Are you sure you want to approve this seed listing? It will become available in the marketplace.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="approve_listing">
            <input type="hidden" name="listing_id" value="${listingId}">
            <input type="hidden" name="notes" value="Approved by admin">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function rejectListing(listingId) {
    const reason = prompt('Please provide a reason for rejection:');
    if (reason !== null) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="reject_listing">
            <input type="hidden" name="listing_id" value="${listingId}">
            <input type="hidden" name="notes" value="${reason}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
