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

$error = '';
$success = '';

// Handle purchase
if (isset($_POST['buy_seed']) && isset($_POST['listing_id'])) {
    $listing_id = (int)$_POST['listing_id'];
    $purchase_quantity = (int)($_POST['purchase_quantity'] ?? 0);
    
    try {
        $db->beginTransaction();
        
        // Get listing details
        $listing_query = "SELECT * FROM seed_listings WHERE id = ? AND status = 'available'";
        $listing_stmt = $db->prepare($listing_query);
        $listing_stmt->execute([$listing_id]);
        $listing = $listing_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($listing && $listing['seller_id'] != $_SESSION['user_id']) {
            // Validate purchase quantity
            if ($purchase_quantity <= 0) {
                $error = "Please select a valid quantity to purchase.";
            } elseif ($purchase_quantity > $listing['quantity']) {
                $error = "Purchase quantity cannot exceed available quantity.";
            } else {
                // Calculate remaining quantity
                $remaining_quantity = $listing['quantity'] - $purchase_quantity;
                
                if ($remaining_quantity == 0) {
                    // All units sold - mark as sold
                    $update_query = "UPDATE seed_listings SET status = 'sold', buyer_id = ?, quantity = 0 WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([$_SESSION['user_id'], $listing_id]);
                } else {
                    // Partial purchase - update quantity
                    $update_query = "UPDATE seed_listings SET quantity = ? WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([$remaining_quantity, $listing_id]);
                }
                
                // Award Green Points to buyer and seller (proportional to quantity)
                $buyer_points = ($listing['is_organic'] || $listing['is_non_gmo'] ? 3 : 1) * $purchase_quantity;
                $seller_points = ($listing['is_organic'] || $listing['is_non_gmo'] ? 5 : 2) * $purchase_quantity;
                
                // Update buyer points
                $buyer_update = "UPDATE users SET green_points = green_points + ? WHERE id = ?";
                $buyer_stmt = $db->prepare($buyer_update);
                $buyer_stmt->execute([$buyer_points, $_SESSION['user_id']]);
                
                // Update seller points
                $seller_update = "UPDATE users SET green_points = green_points + ? WHERE id = ?";
                $seller_stmt = $db->prepare($seller_update);
                $seller_stmt->execute([$seller_points, $listing['seller_id']]);
                
                // Update session points
                $_SESSION['green_points'] += $buyer_points;
                
                // Record the sale in seed_sales table
                $sale_query = "INSERT INTO seed_sales (listing_id, buyer_id, seller_id, quantity, unit_price, total_price, status) VALUES (?, ?, ?, ?, ?, ?, 'completed')";
                $sale_stmt = $db->prepare($sale_query);
                $total_amount = $listing['price'] * $purchase_quantity;
                $sale_stmt->execute([$listing_id, $_SESSION['user_id'], $listing['seller_id'], $purchase_quantity, $listing['price'], $total_amount]);
                
                // Log transactions
                $transaction_query = "INSERT INTO green_points_transactions (user_id, transaction_type, amount, description, related_entity_type, related_entity_id) VALUES (?, 'earned', ?, ?, 'seed_sale', ?)";
                $transaction_stmt = $db->prepare($transaction_query);
                $transaction_stmt->execute([$_SESSION['user_id'], $buyer_points, "Purchased $purchase_quantity units of eco-friendly seeds", $listing_id]);
                $transaction_stmt->execute([$listing['seller_id'], $seller_points, "Sold $purchase_quantity units of eco-friendly seeds", $listing_id]);
                
                $db->commit();
                $success = "Purchase successful! You bought $purchase_quantity units and earned $buyer_points Green Points. <a href='purchase_history.php' class='alert-link'>View Purchase History</a>";
            }
        } else {
            $error = "Invalid listing or you cannot buy your own seeds.";
        }
        
    } catch (Exception $e) {
        $db->rollback();
        $error = "Purchase failed: " . $e->getMessage();
    }
}

// Get available seed listings
$listings_query = "SELECT sl.*, u.username as seller_name 
                  FROM seed_listings sl 
                  JOIN users u ON sl.seller_id = u.id 
                  WHERE sl.status = 'available' 
                  ORDER BY sl.created_at DESC";
$listings_stmt = $db->prepare($listings_query);
$listings_stmt->execute();
$listings = $listings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's own listings
$my_listings_query = "SELECT * FROM seed_listings WHERE seller_id = ? ORDER BY created_at DESC";
$my_listings_stmt = $db->prepare($my_listings_query);
$my_listings_stmt->execute([$_SESSION['user_id']]);
$my_listings = $my_listings_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <div class="sidebar">
            <h5><i class="fas fa-store me-2"></i>Marketplace</h5>
            <hr>
            <div class="list-group">
                <a href="marketplace.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-shopping-cart me-2"></i>Browse Seeds
                </a>
                <a href="sell_seeds.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-plus me-2"></i>Sell Seeds
                </a>
                <a href="my_listings.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-list me-2"></i>My Listings
                </a>
                <a href="purchase_history.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-history me-2"></i>Purchase History
                </a>
                <a href="farmer_dashboard.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-store me-2"></i>Seed Marketplace</h2>
                <a href="sell_seeds.php" class="btn btn-blue-color">
                    <i class="fas fa-plus me-2"></i>Sell Seeds
                </a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Marketplace Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-seedling fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo count($listings); ?></h4>
                            <p class="mb-0 text-color">Available Seeds</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-leaf fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo count(array_filter($listings, function($l) { return $l['is_organic']; })); ?></h4>
                            <p class="mb-0 text-color">Organic Seeds</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-store fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo count($my_listings); ?></h4>
                            <p class="mb-0 text-color">My Listings</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-star fa-2x mb-2 text-color"></i>
                            <h4 class="text-color"><?php echo $_SESSION['green_points']; ?></h4>
                            <p class="mb-0 text-color">Green Points</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Search and Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <input type="text" class="form-control" id="searchSeeds" placeholder="Search seeds...">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="filterType">
                                <option value="">All Types</option>
                                <option value="organic">Organic Only</option>
                                <option value="non_gmo">Non-GMO Only</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="sortBy">
                                <option value="newest">Newest First</option>
                                <option value="price_low">Price: Low to High</option>
                                <option value="price_high">Price: High to Low</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-primary w-100" onclick="applyFilters()">
                                <i class="fas fa-filter me-1"></i>Filter
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Seed Listings -->
            <div class="row">
                <?php if(empty($listings)): ?>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-seedling fa-3x text-muted mb-3"></i>
                                <h4>No seeds available</h4>
                                <p class="text-muted">Be the first to list seeds in the marketplace!</p>
                                <a href="sell_seeds.php" class="btn btn-blue-color">
                                    <i class="fas fa-plus me-2"></i>List Seeds
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach($listings as $listing): ?>
                        <div class="col-md-4 mb-4 seed-listing" 
                             data-type="<?php echo $listing['is_organic'] ? 'organic' : ($listing['is_non_gmo'] ? 'non_gmo' : 'regular'); ?>"
                             data-price="<?php echo $listing['price']; ?>"
                             data-date="<?php echo $listing['created_at']; ?>">
                            <div class="card h-100 marketplace-card">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($listing['seed_type']); ?></h6>
                                        <div>
                                            <?php if($listing['is_organic']): ?>
                                                <span class="badge bg-success">Organic</span>
                                            <?php endif; ?>
                                            <?php if($listing['is_non_gmo']): ?>
                                                <span class="badge bg-info">Non-GMO</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <strong>Quantity:</strong> <?php echo $listing['quantity']; ?> units
                                    </div>
                                    <div class="mb-3">
                                        <strong>Price:</strong> $<?php echo number_format($listing['price'], 2); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Location:</strong> <?php echo htmlspecialchars($listing['location']); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Seller:</strong> <?php echo htmlspecialchars($listing['seller_name']); ?>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-muted">
                                            Listed: <?php echo date('M j, Y', strtotime($listing['created_at'])); ?>
                                        </small>
                                    </div>
                                    
                                    <?php if($listing['is_organic'] || $listing['is_non_gmo']): ?>
                                        <div class="alert alert-success py-2">
                                            <small>
                                                <i class="fas fa-star me-1"></i>
                                                Earn Green Points for eco-friendly seeds!
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer">
                                    <?php if($listing['seller_id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                            <div class="mb-3">
                                                <label for="quantity_<?php echo $listing['id']; ?>" class="form-label">
                                                    <strong>Select Quantity:</strong>
                                                </label>
                                                <select name="purchase_quantity" id="quantity_<?php echo $listing['id']; ?>" class="form-select mb-2" required>
                                                    <option value="">Choose quantity...</option>
                                                    <?php for($i = 1; $i <= min(10, $listing['quantity']); $i++): ?>
                                                        <option value="<?php echo $i; ?>"><?php echo $i; ?> unit<?php echo $i != 1 ? 's' : ''; ?></option>
                                                    <?php endfor; ?>
                                                    <?php if($listing['quantity'] > 10): ?>
                                                        <option value="<?php echo $listing['quantity']; ?>">All <?php echo $listing['quantity']; ?> units</option>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                            <button type="submit" name="buy_seed" class="btn btn-success w-100" 
                                                    onclick="return validatePurchase(<?php echo $listing['id']; ?>)">
                                                <i class="fas fa-shopping-cart me-2"></i>Buy Seeds
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-secondary w-100" disabled>
                                            <i class="fas fa-user me-2"></i>Your Listing
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function applyFilters() {
    const searchTerm = document.getElementById('searchSeeds').value.toLowerCase();
    const filterType = document.getElementById('filterType').value;
    const sortBy = document.getElementById('sortBy').value;
    
    const listings = document.querySelectorAll('.seed-listing');
    
    listings.forEach(listing => {
        const seedType = listing.querySelector('h6').textContent.toLowerCase();
        const listingType = listing.dataset.type;
        
        let show = true;
        
        // Search filter
        if (searchTerm && !seedType.includes(searchTerm)) {
            show = false;
        }
        
        // Type filter
        if (filterType && listingType !== filterType) {
            show = false;
        }
        
        listing.style.display = show ? 'block' : 'none';
    });
    
    // Sort functionality can be added here
}

function validatePurchase(listingId) {
    const quantitySelect = document.getElementById('quantity_' + listingId);
    const selectedQuantity = quantitySelect.value;
    
    if (!selectedQuantity || selectedQuantity === '') {
        alert('Please select a quantity to purchase.');
        return false;
    }
    
    return confirm('Are you sure you want to purchase ' + selectedQuantity + ' unit(s) of these seeds?');
}

// Real-time search
document.getElementById('searchSeeds').addEventListener('input', applyFilters);
document.getElementById('filterType').addEventListener('change', applyFilters);
</script>

<?php include 'includes/footer.php'; ?>
