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

// Get user's current green points
$user_query = "SELECT green_points FROM users WHERE id = ?";
$user_stmt = $db->prepare($user_query);
$user_stmt->execute([$farmer_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
$current_points = $user['green_points'];

// Get recent green points transactions
$transactions_query = "
    SELECT * FROM green_points_transactions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 20
";
$transactions_stmt = $db->prepare($transactions_query);
$transactions_stmt->execute([$farmer_id]);
$transactions = $transactions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_earned = 0;
$total_spent = 0;
$total_transactions = count($transactions);

foreach ($transactions as $transaction) {
    if ($transaction['transaction_type'] == 'earned') {
        $total_earned += $transaction['amount'];
    } elseif ($transaction['transaction_type'] == 'spent') {
        $total_spent += $transaction['amount'];
    }
}

// Get points breakdown by activity type
$activity_breakdown_query = "
    SELECT 
        related_entity_type,
        SUM(CASE WHEN transaction_type = 'earned' THEN amount ELSE 0 END) as earned,
        SUM(CASE WHEN transaction_type = 'spent' THEN amount ELSE 0 END) as spent,
        COUNT(*) as transaction_count
    FROM green_points_transactions 
    WHERE user_id = ? 
    GROUP BY related_entity_type
    ORDER BY earned DESC
";
$breakdown_stmt = $db->prepare($activity_breakdown_query);
$breakdown_stmt->execute([$farmer_id]);
$activity_breakdown = $breakdown_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent purchases for points calculation
$recent_purchases_query = "
    SELECT ss.*, sl.seed_type, sl.is_organic, sl.is_non_gmo
    FROM seed_sales ss
    JOIN seed_listings sl ON ss.listing_id = sl.id
    WHERE ss.buyer_id = ?
    ORDER BY ss.transaction_date DESC
    LIMIT 5
";
$purchases_stmt = $db->prepare($recent_purchases_query);
$purchases_stmt->execute([$farmer_id]);
$recent_purchases = $purchases_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <div class="sidebar">
            <h5><i class="fas fa-star me-2"></i>Green Points</h5>
            <hr>
            <div class="list-group">
                <a href="green_points.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-chart-line me-2"></i>Points Overview
                </a>
                <a href="purchase_history.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-history me-2"></i>Purchase History
                </a>
                <a href="marketplace.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-store me-2"></i>Marketplace
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
                <h2><i class="fas fa-star me-2"></i>Green Points Dashboard</h2>
                <a href="marketplace.php" class="btn btn-success">
                    <i class="fas fa-shopping-cart me-2"></i>Earn More Points
                </a>
            </div>
            
            <!-- Current Points Display -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card bg-gradient-primary text-white">
                        <div class="card-body text-center py-4">
                            <i class="fas fa-star fa-4x mb-3"></i>
                            <h1 class="display-4 mb-2"><?php echo number_format($current_points); ?></h1>
                            <h4 class="mb-0">Current Green Points</h4>
                            <p class="mb-0 mt-2">Keep earning points for sustainable farming practices!</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Points Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-arrow-up fa-2x mb-2 text-success"></i>
                            <h4><?php echo number_format($total_earned); ?></h4>
                            <p class="mb-0">Total Earned</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-arrow-down fa-2x mb-2 text-danger"></i>
                            <h4><?php echo number_format($total_spent); ?></h4>
                            <p class="mb-0">Total Spent</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-exchange-alt fa-2x mb-2 text-info"></i>
                            <h4><?php echo $total_transactions; ?></h4>
                            <p class="mb-0">Transactions</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card dashboard-stats">
                        <div class="card-body text-center">
                            <i class="fas fa-trophy fa-2x mb-2 text-warning"></i>
                            <h4><?php echo $current_points >= 100 ? 'Gold' : ($current_points >= 50 ? 'Silver' : 'Bronze'); ?></h4>
                            <p class="mb-0">Level</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Activity Breakdown -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-pie me-2"></i>Points by Activity</h5>
                        </div>
                        <div class="card-body">
                            <?php if(empty($activity_breakdown)): ?>
                                <p class="text-muted">No activity data available yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Activity</th>
                                                <th>Earned</th>
                                                <th>Spent</th>
                                                <th>Count</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($activity_breakdown as $activity): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-primary">
                                                            <?php echo ucfirst(str_replace('_', ' ', $activity['related_entity_type'])); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-success">
                                                        <i class="fas fa-plus me-1"></i><?php echo $activity['earned']; ?>
                                                    </td>
                                                    <td class="text-danger">
                                                        <i class="fas fa-minus me-1"></i><?php echo $activity['spent']; ?>
                                                    </td>
                                                    <td><?php echo $activity['transaction_count']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-seedling me-2"></i>Recent Eco-Friendly Purchases</h5>
                        </div>
                        <div class="card-body">
                            <?php if(empty($recent_purchases)): ?>
                                <p class="text-muted">No purchases yet. Start buying eco-friendly seeds to earn points!</p>
                                <a href="marketplace.php" class="btn btn-success btn-sm">
                                    <i class="fas fa-shopping-cart me-1"></i>Browse Seeds
                                </a>
                            <?php else: ?>
                                <?php foreach($recent_purchases as $purchase): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <strong><?php echo htmlspecialchars($purchase['seed_type']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo $purchase['quantity']; ?> units
                                                <?php if($purchase['is_organic']): ?>
                                                    <span class="badge bg-success badge-sm">Organic</span>
                                                <?php endif; ?>
                                                <?php if($purchase['is_non_gmo']): ?>
                                                    <span class="badge bg-info badge-sm">Non-GMO</span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="text-success">
                                                <i class="fas fa-star me-1"></i>
                                                +<?php echo ($purchase['is_organic'] || $purchase['is_non_gmo'] ? 3 : 1) * $purchase['quantity']; ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('M j', strtotime($purchase['transaction_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Transactions -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list me-2"></i>Recent Transactions</h5>
                </div>
                <div class="card-body">
                    <?php if(empty($transactions)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-star fa-3x text-muted mb-3"></i>
                            <h4>No transactions yet</h4>
                            <p class="text-muted">Start earning Green Points by participating in eco-friendly activities!</p>
                            <a href="marketplace.php" class="btn btn-success">
                                <i class="fas fa-shopping-cart me-2"></i>Buy Eco-Friendly Seeds
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="transactionsTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $running_balance = $current_points;
                                    foreach($transactions as $transaction): 
                                        if($transaction['transaction_type'] == 'earned') {
                                            $running_balance -= $transaction['amount'];
                                        } else {
                                            $running_balance += $transaction['amount'];
                                        }
                                    ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <div><?php echo date('M j, Y', strtotime($transaction['created_at'])); ?></div>
                                                    <small class="text-muted"><?php echo date('g:i A', strtotime($transaction['created_at'])); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if($transaction['transaction_type'] == 'earned'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-plus me-1"></i>Earned
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-minus me-1"></i>Spent
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                            <td>
                                                <span class="<?php echo $transaction['transaction_type'] == 'earned' ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo $transaction['transaction_type'] == 'earned' ? '+' : '-'; ?>
                                                    <?php echo $transaction['amount']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo $running_balance; ?></strong>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Export Options -->
                        <div class="mt-3">
                            <button class="btn btn-outline-primary btn-sm" onclick="exportTransactions()">
                                <i class="fas fa-download me-1"></i>Export Transactions
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="printTransactions()">
                                <i class="fas fa-print me-1"></i>Print Report
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- How to Earn Points -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle me-2"></i>How to Earn Green Points</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <i class="fas fa-shopping-cart fa-2x text-success mb-2"></i>
                                <h6>Buy Eco-Friendly Seeds</h6>
                                <p class="small text-muted">
                                    Earn 3 points per unit for organic/non-GMO seeds<br>
                                    Earn 1 point per unit for regular seeds
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <i class="fas fa-seedling fa-2x text-success mb-2"></i>
                                <h6>Sell Eco-Friendly Seeds</h6>
                                <p class="small text-muted">
                                    Earn 5 points per unit for organic/non-GMO seeds<br>
                                    Earn 2 points per unit for regular seeds
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <i class="fas fa-brain fa-2x text-success mb-2"></i>
                                <h6>Follow AI Recommendations</h6>
                                <p class="small text-muted">
                                    Earn points for implementing sustainable farming practices<br>
                                    Points vary based on recommendation type
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Export transactions to CSV
function exportTransactions() {
    const table = document.getElementById('transactionsTable');
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    
    let csv = 'Date,Type,Description,Amount,Balance\n';
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const rowData = [];
        
        cells.forEach((cell, index) => {
            let text = cell.textContent.trim();
            text = text.replace(/,/g, ';').replace(/\n/g, ' ');
            rowData.push('"' + text + '"');
        });
        
        csv += rowData.join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'green_points_transactions_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Print transactions
function printTransactions() {
    window.print();
}

// Add search functionality
document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('transactionsTable');
    if (table) {
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'form-control mb-3';
        searchInput.placeholder = 'Search transactions...';
        searchInput.id = 'searchTransactions';
        
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
.bg-gradient-primary {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

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
