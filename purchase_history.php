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
$farmer_id = $_SESSION['user_id'];

// Get purchase history from seed_sales table
try {
    $purchase_history_query = "
        SELECT ss.*, sl.seed_type, sl.is_organic, sl.is_non_gmo, u.username as seller_name
        FROM seed_sales ss
        JOIN seed_listings sl ON ss.listing_id = sl.id
        JOIN users u ON ss.seller_id = u.id
        WHERE ss.buyer_id = ?
        ORDER BY ss.transaction_date DESC
    ";
    $purchase_history_stmt = $db->prepare($purchase_history_query);
    $purchase_history_stmt->execute([$farmer_id]);
    $purchase_history = $purchase_history_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If seed_sales table doesn't exist, show empty purchase history
    $purchase_history = [];
    error_log("Seed sales table not found: " . $e->getMessage());
}

// Calculate statistics
$total_purchases = count($purchase_history);
$total_spent = array_sum(array_column($purchase_history, 'total_price'));
$total_units = array_sum(array_column($purchase_history, 'quantity'));

// Calculate total green points based on seed types
$total_green_points = 0;
foreach ($purchase_history as $purchase) {
    $points_per_unit = ($purchase['is_organic'] || $purchase['is_non_gmo']) ? 3 : 1;
    $total_green_points += $points_per_unit * $purchase['quantity'];
}

// Get recent purchases (last 5)
$recent_purchases = array_slice($purchase_history, 0, 5);

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <div class="sidebar">
            <h5><i class="fas fa-store me-2"></i>Marketplace</h5>
            <hr>
            <div class="list-group">
                <a href="marketplace.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-shopping-cart me-2"></i>Browse Seeds
                </a>
                <a href="sell_seeds.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-plus me-2"></i>Sell Seeds
                </a>
                <a href="my_listings.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-list me-2"></i>My Listings
                </a>
                <a href="purchase_history.php" class="list-group-item list-group-item-action active">
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
                <h2><i class="fas fa-history me-2"></i>Purchase History</h2>
                <a href="marketplace.php" class="btn btn-blue-color">
                    <i class="fas fa-shopping-cart me-2"></i>Buy More Seeds
                </a>
            </div>
            
            <!-- Purchase Statistics -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card dashboard-stats h-100 shadow-sm border-0 hover-card">
                        <div class="card-body text-center">
                            <i class="fas fa-shopping-bag fa-2x mb-2 text-color"></i>
                            <h4 class="fw-bold mb-1 text-color"><?php echo $total_purchases; ?></h4>
                            <p class="mb-0 text-color">Total Purchases</p>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card dashboard-stats h-100 shadow-sm border-0 hover-card">
                        <div class="card-body text-center">
                            <i class="fas fa-seedling fa-2x mb-2 text-color"></i>
                            <h4 class="fw-bold mb-1 text-color"><?php echo $total_units; ?></h4>
                            <p class="mb-0 text-color">Units Purchased</p>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card dashboard-stats h-100 shadow-sm border-0 hover-card">
                        <div class="card-body text-center">
                            <i class="fas fa-dollar-sign fa-2x mb-2 text-color"></i>
                            <h4 class="fw-bold mb-1 text-color">$<?php echo number_format($total_spent, 2); ?></h4>
                            <p class="mb-0 text-color">Total Spent</p>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card dashboard-stats h-100 shadow-sm border-0 hover-card">
                        <div class="card-body text-center">
                            <i class="fas fa-star fa-2x mb-2 text-color"></i>
                            <h4 class="fw-bold mb-1 text-color"><?php echo $total_green_points; ?></h4>
                            <p class="mb-0 text-color">Green Points Earned</p>
                        </div>
                    </div>
                </div>
            </div>

<!-- Hover effect -->
<style>
    .hover-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .hover-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 6px 18px rgba(0,0,0,0.12);
    }
</style>

            
            <!-- Recent Purchases -->
            <?php if(!empty($recent_purchases)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-clock me-2"></i>Recent Purchases</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Seed Type</th>
                                        <th>Seller</th>
                                        <th>Quantity</th>
                                        <th>Price/Unit</th>
                                        <th>Total</th>
                                        <th>Green Points</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recent_purchases as $purchase): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="me-2"><?php echo htmlspecialchars($purchase['seed_type']); ?></span>
                                                    <div>
                                                        <?php if($purchase['is_organic']): ?>
                                                            <span class="badge bg-success badge-sm">Organic</span>
                                                        <?php endif; ?>
                                                        <?php if($purchase['is_non_gmo']): ?>
                                                            <span class="badge bg-info badge-sm">Non-GMO</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($purchase['seller_name']); ?></td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $purchase['quantity']; ?> units</span>
                                            </td>
                                            <td>$<?php echo number_format($purchase['unit_price'], 2); ?></td>
                                            <td>
                                                <strong>$<?php echo number_format($purchase['total_price'], 2); ?></strong>
                                            </td>
                                            <td>
                                                <span class="text-success">
                                                    <i class="fas fa-star me-1"></i><?php echo ($purchase['is_organic'] || $purchase['is_non_gmo'] ? 3 : 1) * $purchase['quantity']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($purchase['transaction_date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Complete Purchase History -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list me-2"></i>Complete Purchase History</h5>
                </div>
                <div class="card-body">
                    <?php if(empty($purchase_history)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                            <h4>No purchases yet</h4>
                            <p class="text-muted">You haven't made any seed purchases yet.</p>
                            <a href="marketplace.php" class="btn btn-blue-color">
                                <i class="fas fa-shopping-cart me-2"></i>Browse Seeds
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="purchaseHistoryTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Seed Type</th>
                                        <th>Seller</th>
                                        <th>Quantity</th>
                                        <th>Price/Unit</th>
                                        <th>Total Amount</th>
                                        <th>Green Points</th>
                                        <th>Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($purchase_history as $purchase): ?>
                                        <tr>
                                                                                         <td>
                                                 <div>
                                                     <div><?php echo date('M j, Y', strtotime($purchase['transaction_date'])); ?></div>
                                                     <small class="text-muted"><?php echo date('g:i A', strtotime($purchase['transaction_date'])); ?></small>
                                                 </div>
                                             </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="me-2"><?php echo htmlspecialchars($purchase['seed_type']); ?></span>
                                                    <div>
                                                        <?php if($purchase['is_organic']): ?>
                                                            <span class="badge bg-success badge-sm">Organic</span>
                                                        <?php endif; ?>
                                                        <?php if($purchase['is_non_gmo']): ?>
                                                            <span class="badge bg-info badge-sm">Non-GMO</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($purchase['seller_name']); ?></td>
                                                                                         <td>
                                                 <span class="badge bg-primary"><?php echo $purchase['quantity']; ?> units</span>
                                             </td>
                                             <td>$<?php echo number_format($purchase['unit_price'], 2); ?></td>
                                             <td>
                                                 <strong>$<?php echo number_format($purchase['total_price'], 2); ?></strong>
                                             </td>
                                             <td>
                                                 <span class="text-success">
                                                     <i class="fas fa-star me-1"></i><?php echo ($purchase['is_organic'] || $purchase['is_non_gmo'] ? 3 : 1) * $purchase['quantity']; ?>
                                                 </span>
                                             </td>
                                            <td>
                                                <?php if($purchase['is_organic'] && $purchase['is_non_gmo']): ?>
                                                    <span class="badge bg-success">Organic + Non-GMO</span>
                                                <?php elseif($purchase['is_organic']): ?>
                                                    <span class="badge bg-success">Organic</span>
                                                <?php elseif($purchase['is_non_gmo']): ?>
                                                    <span class="badge bg-info">Non-GMO</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Regular</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Export Options -->
                        <div class="mt-3">
                            <button class="btn btn-outline-primary btn-sm" onclick="exportToCSV()">
                                <i class="fas fa-download me-1"></i>Export to CSV
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="printHistory()">
                                <i class="fas fa-print me-1"></i>Print History
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Export to CSV functionality
function exportToCSV() {
    const table = document.getElementById('purchaseHistoryTable');
    const rows = table.querySelectorAll('tbody tr');
    
    let csv = 'Date,Seed Type,Seller,Quantity,Price/Unit,Total Amount,Green Points,Type\n';
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const rowData = [];
        
        cells.forEach((cell, index) => {
            let text = cell.textContent.trim();
            // Clean up the text and handle commas
            text = text.replace(/,/g, ';').replace(/\n/g, ' ');
            rowData.push('"' + text + '"');
        });
        
        csv += rowData.join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'purchase_history_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Print functionality
function printHistory() {
    window.print();
}

// Add search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.className = 'form-control mb-3';
    searchInput.placeholder = 'Search purchase history...';
    searchInput.id = 'searchPurchases';
    
    const table = document.getElementById('purchaseHistoryTable');
    if (table) {
        table.parentNode.insertBefore(searchInput, table);
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
});
</script>

<style>
@media print {
    .sidebar, .btn, .card-header {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
    }
}

.badge-sm {
    font-size: 0.7em;
}
</style>

<?php include 'includes/footer.php'; ?>
