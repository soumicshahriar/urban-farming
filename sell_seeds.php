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

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $seed_type = $_POST['seed_type'] ?? '';
    $quantity = $_POST['quantity'] ?? 0;
    $price = $_POST['price'] ?? 0;
    $location = $_POST['location'] ?? '';
    $is_organic = isset($_POST['is_organic']) ? 1 : 0;
    $is_non_gmo = isset($_POST['is_non_gmo']) ? 1 : 0;
    $description = $_POST['description'] ?? '';
    
    // Validate inputs
    $errors = [];
    if(empty($seed_type)) $errors[] = "Seed type is required";
    if($quantity <= 0) $errors[] = "Quantity must be greater than 0";
    if($price <= 0) $errors[] = "Price must be greater than 0";
    if(empty($location)) $errors[] = "Location is required";
    
    if(empty($errors)) {
        // Insert seed listing
        try {
            $insert_query = "INSERT INTO seed_listings (seller_id, seed_type, quantity, price, location, is_organic, is_non_gmo, description, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            $insert_stmt = $db->prepare($insert_query);
            $success = $insert_stmt->execute([$_SESSION['user_id'], $seed_type, $quantity, $price, $location, $is_organic, $is_non_gmo, $description]);
        } catch (PDOException $e) {
            // If description column doesn't exist, insert without description
            $insert_query = "INSERT INTO seed_listings (seller_id, seed_type, quantity, price, location, is_organic, is_non_gmo, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
            $insert_stmt = $db->prepare($insert_query);
            $success = $insert_stmt->execute([$_SESSION['user_id'], $seed_type, $quantity, $price, $location, $is_organic, $is_non_gmo]);
        }
        
        if($success) {
            $success_message = "Seed listing created successfully! It will be reviewed by admin before going live.";
            
            // Log the action
            $log_query = "INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->execute([$_SESSION['user_id'], 'seed_listing_created', "Seed type: $seed_type, Quantity: $quantity"]);
        } else {
            $error_message = "Failed to create seed listing. Please try again.";
        }
    } else {
        $error_message = implode(", ", $errors);
    }
}

// Get user's existing seed listings
$listings_query = "SELECT * FROM seed_listings WHERE seller_id = ? ORDER BY created_at DESC";
$listings_stmt = $db->prepare($listings_query);
$listings_stmt->execute([$_SESSION['user_id']]);
$user_listings = $listings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's farms for location suggestions
$farms_query = "SELECT name, location FROM farms WHERE farmer_id = ? AND status = 'approved'";
$farms_stmt = $db->prepare($farms_query);
$farms_stmt->execute([$_SESSION['user_id']]);
$user_farms = $farms_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <div class="sidebar">
                <h5><i class="fas fa-store me-2"></i>Seed Marketplace</h5>
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
                        <i class="fas fa-store me-2"></i>Browse Marketplace
                    </a>
                    <a href="sell_seeds.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-plus me-2"></i>Sell Seeds
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-seedling me-2"></i>Sell Seeds</h2>
                    <a href="marketplace.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Marketplace
                    </a>
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
                
                <!-- Create New Listing -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-plus me-2"></i>Create New Seed Listing</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="seed_type" class="form-label">Seed Type *</label>
                                        <select class="form-select" id="seed_type" name="seed_type" required>
                                            <option value="">Select Seed Type</option>
                                            <option value="rice">Rice</option>
                                            <option value="wheat">Wheat</option>
                                            <option value="corn">Corn</option>
                                            <option value="soybeans">Soybeans</option>
                                            <option value="tomatoes">Tomatoes</option>
                                            <option value="peppers">Peppers</option>
                                            <option value="lettuce">Lettuce</option>
                                            <option value="carrots">Carrots</option>
                                            <option value="potatoes">Potatoes</option>
                                            <option value="onions">Onions</option>
                                            <option value="cucumbers">Cucumbers</option>
                                            <option value="beans">Beans</option>
                                            <option value="peas">Peas</option>
                                            <option value="spinach">Spinach</option>
                                            <option value="kale">Kale</option>
                                            <option value="herbs">Herbs</option>
                                            <option value="flowers">Flowers</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="quantity" class="form-label">Quantity *</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                                            <select class="form-select" id="quantity_unit" name="quantity_unit" style="max-width: 120px;">
                                                <option value="grams">Grams</option>
                                                <option value="kg">Kilograms</option>
                                                <option value="packets">Packets</option>
                                                <option value="seeds">Seeds</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="price" class="form-label">Price *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control" id="price" name="price" min="0.01" step="0.01" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="location" class="form-label">Location *</label>
                                        <input type="text" class="form-control" id="location" name="location" required 
                                               placeholder="City, State or Farm Location"
                                               list="farm-locations">
                                        <datalist id="farm-locations">
                                            <?php foreach($user_farms as $farm): ?>
                                                <option value="<?php echo htmlspecialchars($farm['location']); ?>">
                                                    <?php echo htmlspecialchars($farm['name']); ?> - <?php echo htmlspecialchars($farm['location']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </datalist>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Eco-Friendly Options</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_organic" name="is_organic">
                                            <label class="form-check-label" for="is_organic">
                                                <i class="fas fa-leaf text-success me-1"></i>Organic Seeds
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_non_gmo" name="is_non_gmo">
                                            <label class="form-check-label" for="is_non_gmo">
                                                <i class="fas fa-shield-alt text-info me-1"></i>Non-GMO Seeds
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3" 
                                                  placeholder="Describe your seeds, growing conditions, harvest time, etc."></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Green Points Bonus:</strong> Organic and Non-GMO seeds earn extra Green Points when sold!
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-blue-color">
                                    <i class="fas fa-plus me-2"></i>Create Listing
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- My Listings -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>My Seed Listings</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($user_listings)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-seedling fa-3x text-muted mb-3"></i>
                                <p class="text-muted">You haven't created any seed listings yet.</p>
                                <p class="text-muted">Create your first listing above to start earning Green Points!</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Seed Type</th>
                                            <th>Quantity</th>
                                            <th>Price</th>
                                            <th>Location</th>
                                            <th>Eco-Friendly</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($user_listings as $listing): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo ucfirst(htmlspecialchars($listing['seed_type'])); ?></strong>
                                                    <?php if(isset($listing['description']) && $listing['description']): ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars(substr($listing['description'], 0, 50)); ?>...</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo number_format($listing['quantity']); ?></td>
                                                <td>$<?php echo number_format($listing['price'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($listing['location']); ?></td>
                                                <td>
                                                    <?php if($listing['is_organic']): ?>
                                                        <span class="badge bg-success me-1">Organic</span>
                                                    <?php endif; ?>
                                                    <?php if($listing['is_non_gmo']): ?>
                                                        <span class="badge bg-info">Non-GMO</span>
                                                    <?php endif; ?>
                                                    <?php if(!$listing['is_organic'] && !$listing['is_non_gmo']): ?>
                                                        <span class="text-muted">Standard</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if($listing['status'] == 'pending'): ?>
                                                        <span class="badge bg-warning">Pending Review</span>
                                                    <?php elseif($listing['status'] == 'available'): ?>
                                                        <span class="badge bg-success">Available</span>
                                                    <?php elseif($listing['status'] == 'sold'): ?>
                                                        <span class="badge bg-secondary">Sold</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($listing['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#listingModal" 
                                                                data-listing-data='<?php echo json_encode($listing); ?>'>
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if($listing['status'] == 'pending'): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteListing(<?php echo $listing['id']; ?>)">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
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

<!-- Listing Details Modal -->
<div class="modal fade" id="listingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Seed Listing Details</h5>
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
                        <p><strong>Seed Type:</strong> ${listingData.seed_type.charAt(0).toUpperCase() + listingData.seed_type.slice(1)}</p>
                        <p><strong>Quantity:</strong> ${listingData.quantity}</p>
                        <p><strong>Price:</strong> $${parseFloat(listingData.price).toFixed(2)}</p>
                        <p><strong>Location:</strong> ${listingData.location}</p>
                        <p><strong>Status:</strong> 
                            <span class="badge bg-${getStatusColor(listingData.status)}">${getStatusLabel(listingData.status)}</span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6>Eco-Friendly Features</h6>
                        <p><strong>Organic:</strong> ${listingData.is_organic ? '<span class="badge bg-success">Yes</span>' : '<span class="text-muted">No</span>'}</p>
                        <p><strong>Non-GMO:</strong> ${listingData.is_non_gmo ? '<span class="badge bg-info">Yes</span>' : '<span class="text-muted">No</span>'}</p>
                        <p><strong>Created:</strong> ${new Date(listingData.created_at).toLocaleString()}</p>
                        ${listingData.description && listingData.description !== null ? `<p><strong>Description:</strong> ${listingData.description}</p>` : ''}
                        ${listingData.green_points_earned_seller > 0 ? `<p><strong>Green Points Earned:</strong> <span class="text-success">+${listingData.green_points_earned_seller}</span></p>` : ''}
                    </div>
                </div>
            `;
        });
    }
});

function getStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'available': 'success',
        'sold': 'secondary'
    };
    return colors[status] || 'secondary';
}

function getStatusLabel(status) {
    const labels = {
        'pending': 'Pending Review',
        'available': 'Available',
        'sold': 'Sold'
    };
    return labels[status] || status;
}

function deleteListing(listingId) {
    if (confirm('Are you sure you want to delete this seed listing?')) {
        // Here you would typically make an AJAX call to delete the listing
        alert('Listing deletion functionality would be implemented here.');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
