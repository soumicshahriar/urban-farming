<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is a farmer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'farmer') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $listing_id = $_POST['listing_id'] ?? 0;
    
    if($action && $listing_id) {
        switch($action) {
            case 'delete_listing':
                // Check if listing belongs to current user
                $check_query = "SELECT id FROM seed_listings WHERE id = ? AND seller_id = ?";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute([$listing_id, $_SESSION['user_id']]);
                
                if($check_stmt->fetch()) {
                    $delete_query = "DELETE FROM seed_listings WHERE id = ?";
                    $delete_stmt = $db->prepare($delete_query);
                    if($delete_stmt->execute([$listing_id])) {
                        $success_message = "Listing deleted successfully!";
                        
                        // Log the action
                        $log_query = "INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
                        $log_stmt = $db->prepare($log_query);
                        $log_stmt->execute([$_SESSION['user_id'], 'seed_listing_deleted', "Listing ID: $listing_id"]);
                    } else {
                        $error_message = "Failed to delete listing.";
                    }
                } else {
                    $error_message = "You can only delete your own listings.";
                }
                break;
                
            // Status updates are handled automatically by the system
            // Farmers cannot manually update listing status
            case 'update_status':
                $error_message = "Listing status cannot be manually updated. Status changes are handled automatically by the system.";
                break;
        }
    }
}

// Handle filters
$status_filter = $_GET['status'] ?? '';
$seed_type_filter = $_GET['seed_type'] ?? '';
$search_term = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = ["sl.seller_id = ?"];
$params = [$_SESSION['user_id']];

if($status_filter) {
    $where_conditions[] = "sl.status = ?";
    $params[] = $status_filter;
}

if($seed_type_filter) {
    $where_conditions[] = "sl.seed_type = ?";
    $params[] = $seed_type_filter;
}

if($search_term) {
    $where_conditions[] = "(sl.title LIKE ? OR sl.description LIKE ? OR sl.location LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get user's seed listings
$listings_query = "SELECT sl.*, 
                          COUNT(DISTINCT s.id) as total_sales,
                          SUM(s.quantity) as total_quantity_sold,
                          SUM(s.total_price) as total_revenue
                   FROM seed_listings sl
                   LEFT JOIN seed_sales s ON sl.id = s.listing_id
                   $where_clause
                   GROUP BY sl.id
                   ORDER BY sl.created_at DESC";

$listings_stmt = $db->prepare($listings_query);
$listings_stmt->execute($params);
$my_listings = $listings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get listing statistics
$stats_query = "SELECT 
                    COUNT(*) as total_listings,
                    COUNT(CASE WHEN status = 'available' THEN 1 END) as available_listings,
                    COUNT(CASE WHEN status = 'sold' THEN 1 END) as sold_listings,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_listings,
                    SUM(CASE WHEN is_organic = 1 THEN 1 ELSE 0 END) as organic_listings,
                    SUM(CASE WHEN is_non_gmo = 1 THEN 1 ELSE 0 END) as non_gmo_listings
                FROM seed_listings 
                WHERE seller_id = ?";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$_SESSION['user_id']]);
$listing_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get total earnings from seed sales
$earnings_query = "SELECT 
                       SUM(ss.total_price) as total_earnings,
                       COUNT(ss.id) as total_sales_count
                   FROM seed_sales ss
                   JOIN seed_listings sl ON ss.listing_id = sl.id
                   WHERE sl.seller_id = ?";

$earnings_stmt = $db->prepare($earnings_query);
$earnings_stmt->execute([$_SESSION['user_id']]);
$earnings_stats = $earnings_stmt->fetch(PDO::FETCH_ASSOC);

// Get unique seed types for filter
$seed_types_query = "SELECT DISTINCT seed_type FROM seed_listings WHERE seller_id = ? ORDER BY seed_type";
$seed_types_stmt = $db->prepare($seed_types_query);
$seed_types_stmt->execute([$_SESSION['user_id']]);
$seed_types = $seed_types_stmt->fetchAll(PDO::FETCH_COLUMN);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <div class="sidebar">
                <h5><i class="fas fa-seedling me-2"></i>My Listings</h5>
                <hr>
                <div class="mb-3">
                    <strong>Welcome, <?php echo $_SESSION['username']; ?>!</strong>
                    <div class="green-points mt-2">
                        <i class="fas fa-star me-1"></i><?php echo $_SESSION['green_points']; ?> Green Points
                    </div>
                </div>
                
                <div class="list-group">
                    <a href="farmer_dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="create_farm.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-plus me-2"></i>Create Farm
                    </a>
                    <a href="farm_requests.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-farm me-2"></i>My Farms
                    </a>
                    <a href="request_drone.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-drone me-2"></i>Request Drone
                    </a>
                    <a href="iot_monitoring.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-sensor me-2"></i>IoT Monitoring
                    </a>
                    <a href="marketplace.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-store me-2"></i>Marketplace
                    </a>
                    <a href="sell_seeds.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-plus-circle me-2"></i>Sell Seeds
                    </a>
                    <a href="my_listings.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-list me-2"></i>My Listings
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-seedling me-2"></i>My Seed Listings</h2>
                    <div class="text-end">
                        <a href="sell_seeds.php" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i>Add New Listing
                        </a>
                    </div>
                </div>
                
                <!-- Status Information Alert -->
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Automatic Status Management:</strong> Listing statuses are managed automatically by the system.
                    <ul class="mb-0 mt-2">
                        <li><strong>Pending:</strong> New listings awaiting admin approval</li>
                        <li><strong>Available:</strong> Approved listings visible in marketplace</li>
                        <li><strong>Sold:</strong> Listings purchased by other farmers</li>
                    </ul>
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
                
                <div class="row g-3 mb-4">
                    <!-- Total Listings -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="card dashboard-stats h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-list fa-2x mb-2 text-color"></i>
                                <h4 class="text-color"><?php echo $listing_stats['total_listings']; ?></h4>
                                <p class="mb-0 text-color">Total Listings</p>
                            </div>
                        </div>
                    </div>

                    <!-- Available -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="card dashboard-stats h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-2x mb-2 text-color"></i>
                                <h4 class="text-color"><?php echo $listing_stats['available_listings']; ?></h4>
                                <p class="mb-0 text-color">Available</p>
                            </div>
                        </div>
                    </div>

                    <!-- Sold -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="card dashboard-stats h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-shopping-cart fa-2x mb-2 text-color"></i>
                                <h4 class="text-color"><?php echo $listing_stats['sold_listings']; ?></h4>
                                <p class="mb-0 text-color">Sold</p>
                            </div>
                        </div>
                    </div>

                    <!-- Total Earnings -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="card dashboard-stats h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-dollar-sign fa-2x mb-2 text-color"></i>
                                <h4 class="text-color">
                                    $<?php echo number_format($earnings_stats['total_earnings'] ?? 0, 2); ?>
                                </h4>
                                <p class="mb-0 text-color">Total Earnings</p>
                            </div>
                        </div>
                    </div>

                    <!-- Organic -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="card dashboard-stats h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-leaf fa-2x mb-2 text-color"></i>
                                <h4 class="text-color"><?php echo $listing_stats['organic_listings']; ?></h4>
                                <p class="mb-0 text-color">Organic</p>
                            </div>
                        </div>
                    </div>

                    <!-- Non-GMO -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="card dashboard-stats h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-seedling fa-2x mb-2 text-color"></i>
                                <h4 class="text-color"><?php echo $listing_stats['non_gmo_listings']; ?></h4>
                                <p class="mb-0 text-color">Non-GMO</p>
                            </div>
                        </div>
                    </div>
                </div>


                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-filter me-2"></i>Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search_term); ?>" 
                                       placeholder="Title, description, location">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="available" <?php echo $status_filter == 'available' ? 'selected' : ''; ?>>Available</option>
                                    <option value="sold" <?php echo $status_filter == 'sold' ? 'selected' : ''; ?>>Sold</option>
                                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="seed_type" class="form-label">Seed Type</label>
                                <select class="form-select" id="seed_type" name="seed_type">
                                    <option value="">All Types</option>
                                    <?php foreach($seed_types as $type): ?>
                                        <option value="<?php echo $type; ?>" <?php echo $seed_type_filter == $type ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($type); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-blue-color me-2">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                                <a href="my_listings.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- My Listings Table -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>My Seed Listings</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($my_listings)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-seedling fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No seed listings found matching your criteria.</p>
                                <a href="sell_seeds.php" class="btn btn-success">
                                    <i class="fas fa-plus me-1"></i>Create Your First Listing
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Listing</th>
                                            <th>Type & Details</th>
                                            <th>Price & Quantity</th>
                                            <th>Status</th>
                                            <th>Sales</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($my_listings as $listing): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar me-3">
                                                            <i class="fas fa-seedling fa-2x text-success"></i>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($listing['title']); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($listing['location']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <div><strong>Type:</strong> <?php echo ucfirst($listing['seed_type']); ?></div>
                                                        <div class="text-muted">
                                                            <?php if($listing['is_organic']): ?>
                                                                <span class="badge bg-success me-1">Organic</span>
                                                            <?php endif; ?>
                                                            <?php if($listing['is_non_gmo']): ?>
                                                                <span class="badge bg-warning me-1">Non-GMO</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if($listing['description']): ?>
                                                            <div class="text-truncate" style="max-width: 200px;">
                                                                <?php echo htmlspecialchars($listing['description']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <div><strong>Price:</strong> $<?php echo number_format($listing['price'], 2); ?></div>
                                                        <div><strong>Quantity:</strong> <?php echo number_format($listing['quantity']); ?> units</div>
                                                        <div><strong>Total Value:</strong> $<?php echo number_format($listing['price'] * $listing['quantity'], 2); ?></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if($listing['status'] == 'available'): ?>
                                                        <span class="badge bg-success">Available</span>
                                                    <?php elseif($listing['status'] == 'sold'): ?>
                                                        <span class="badge bg-warning">Sold</span>
                                                    <?php elseif($listing['status'] == 'pending'): ?>
                                                        <span class="badge bg-info">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <div><strong>Sales:</strong> <?php echo $listing['total_sales'] ?? 0; ?></div>
                                                        <div><strong>Sold:</strong> <?php echo number_format($listing['total_quantity_sold'] ?? 0); ?> units</div>
                                                        <div><strong>Revenue:</strong> $<?php echo number_format($listing['total_revenue'] ?? 0, 2); ?></div>
                                                    </div>
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
                                                        <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#listingModal" 
                                                                data-listing-data='<?php echo json_encode($listing); ?>'>
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteListing(<?php echo $listing['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Summary Info -->
                            <div class="mt-3">
                                <small class="text-muted">
                                    Showing <?php echo count($my_listings); ?> listings. 
                                    Total earnings: $<?php echo number_format($earnings_stats['total_earnings'] ?? 0, 2); ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Listing Details Modal -->
<div class="modal fade" id="listingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Listing Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="listingModalBody">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>



<script>
document.addEventListener('DOMContentLoaded', function() {
    // Listing details modal
    const listingModal = document.getElementById('listingModal');
    if (listingModal) {
        listingModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const listingData = JSON.parse(button.getAttribute('data-listing-data'));
            
            const modalBody = document.getElementById('listingModalBody');
            modalBody.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Listing Information</h6>
                        <p><strong>Title:</strong> ${listingData.title}</p>
                        <p><strong>Seed Type:</strong> ${listingData.seed_type.charAt(0).toUpperCase() + listingData.seed_type.slice(1)}</p>
                        <p><strong>Location:</strong> ${listingData.location}</p>
                        <p><strong>Status:</strong> 
                            <span class="badge bg-${getStatusColor(listingData.status)}">${listingData.status.charAt(0).toUpperCase() + listingData.status.slice(1)}</span>
                        </p>
                        <p><strong>Created:</strong> ${new Date(listingData.created_at).toLocaleDateString()}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Pricing & Quantity</h6>
                        <p><strong>Price per Unit:</strong> $${parseFloat(listingData.price).toFixed(2)}</p>
                        <p><strong>Available Quantity:</strong> ${parseInt(listingData.quantity).toLocaleString()} units</p>
                        <p><strong>Total Value:</strong> $${(parseFloat(listingData.price) * parseInt(listingData.quantity)).toFixed(2)}</p>
                        <p><strong>Total Sales:</strong> ${listingData.total_sales || 0}</p>
                        <p><strong>Total Revenue:</strong> $${parseFloat(listingData.total_revenue || 0).toFixed(2)}</p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Certifications</h6>
                        <p>
                            ${listingData.is_organic ? '<span class="badge bg-success me-2">Organic</span>' : ''}
                            ${listingData.is_non_gmo ? '<span class="badge bg-warning me-2">Non-GMO</span>' : ''}
                            ${!listingData.is_organic && !listingData.is_non_gmo ? '<span class="text-muted">No special certifications</span>' : ''}
                        </p>
                    </div>
                </div>
                ${listingData.description ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Description</h6>
                        <p>${listingData.description}</p>
                    </div>
                </div>
                ` : ''}
            `;
        });
    }
    

});

function getStatusColor(status) {
    const colors = {
        'available': 'success',
        'sold': 'warning',
        'pending': 'info'
    };
    return colors[status] || 'secondary';
}

function deleteListing(listingId) {
    if (confirm('Are you sure you want to delete this listing? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_listing">
            <input type="hidden" name="listing_id" value="${listingId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
