<?php
/**
 * Product Detail Page
 * Manufacturing ERP System
 */

// Include database connection
require_once '../includes/database.php';

// Require login
requireLogin();

$pageTitle = 'Product Detail';
$message = '';
$messageType = '';

// Get product ID from URL
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId <= 0) {
    header('Location: products.php');
    exit;
}

// Get product details
$sql = "SELECT * FROM finished_goods WHERE id = ?";
$product = getRow($sql, 'i', [$productId]);

if (!$product) {
    header('Location: products.php');
    exit;
}

// Get sales history for this product
$sql = "SELECT s.*, si.quantity, si.unit_price, si.total, 
        c.customer_name, c.company_name 
        FROM sale_items si 
        LEFT JOIN sales s ON si.sale_id = s.id 
        LEFT JOIN customers c ON s.customer_id = c.id 
        WHERE si.product_id = ? 
        ORDER BY s.sale_date DESC, s.id DESC 
        LIMIT 20";
$salesHistory = getRows($sql, 'i', [$productId]);

// Get production history
$sql = "SELECT p.*, fg.product_name 
        FROM production p 
        LEFT JOIN finished_goods fg ON p.finished_good_id = fg.id 
        WHERE p.finished_good_id = ? 
        ORDER BY p.production_date DESC, p.id DESC 
        LIMIT 20";
$productionHistory = getRows($sql, 'i', [$productId]);

// Calculate statistics
$totalSales = 0;
$totalRevenue = 0;
$totalQuantity = 0;

foreach ($salesHistory as $sale) {
    $totalSales++;
    $totalRevenue += $sale['total'];
    $totalQuantity += $sale['quantity'];
}

$profitMargin = 0;
if ($product['cost_price'] > 0) {
    $profitMargin = (($product['selling_price'] - $product['cost_price']) / $product['cost_price']) * 100;
}

// Stock status
$stockStatus = 'normal';
$stockStatusClass = 'success';
$stockStatusText = 'In Stock';
if ($product['current_stock'] <= 0) {
    $stockStatus = 'out';
    $stockStatusClass = 'danger';
    $stockStatusText = 'Out of Stock';
} elseif ($product['current_stock'] <= $product['minimum_stock']) {
    $stockStatus = 'low';
    $stockStatusClass = 'warning';
    $stockStatusText = 'Low Stock';
}

// Include header
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="products.php">Products</a></li>
                <li class="breadcrumb-item active">Product Detail</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Product Information -->
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-box me-2"></i>Product Information
                <span class="ms-2 badge badge-status badge-<?php echo $product['status']; ?>">
                    <?php echo ucfirst($product['status']); ?>
                </span>
                <span class="ms-2 badge badge-status badge-<?php echo $stockStatusClass; ?>">
                    <?php echo $stockStatusText; ?>
                </span></span>
                <a href="../pages/products.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr>
                                <th style="width: 40%;">Product Code</th>
                                <td><strong><?php echo htmlspecialchars($product['product_code']); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Product Name</th>
                                <td><strong><?php echo htmlspecialchars($product['product_name']); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Category</th>
                                <td><?php echo htmlspecialchars($product['category']) ?: 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <th>Unit</th>
                                <td><?php echo htmlspecialchars($product['unit']); ?></td>
                            </tr>
                            <tr>
                                <th>Selling Price</th>
                                <td class="text-success"><strong>PKR <?php echo formatCurrency($product['selling_price']); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Cost Price</th>
                                <td>PKR <?php echo formatCurrency($product['cost_price']); ?></td>
                            </tr>
                            <tr>
                                <th>Profit Margin</th>
                                <td>
                                    <span class="<?php echo $profitMargin > 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo number_format($profitMargin, 2); ?>%
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Date Added</th>
                                <td><?php echo date('d-m-Y H:i', strtotime($product['date_time'])); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5 class="text-muted">Current Stock</h5>
                                <h1 class="<?php echo $stockStatusClass; ?>">
                                    <?php echo number_format($product['current_stock'], 2); ?>
                                </h1>
                                <p class="text-muted"><?php echo $product['unit']; ?></p>
                                <hr>
                                <div class="row">
                                    <div class="col-6">
                                        <h6 class="text-muted">Minimum Stock</h6>
                                        <h5><?php echo number_format($product['minimum_stock'], 2); ?></h5>
                                    </div>
                                    <div class="col-6">
                                        <h6 class="text-muted">Stock Status</h6>
                                        <h5>
                                            <span class="badge badge-status badge-<?php echo $stockStatusClass; ?>">
                                                <?php echo $stockStatusText; ?>
                                            </span>
                                        </h5>
                                    </div>
                                </div>
                                <?php if ($product['current_stock'] <= $product['minimum_stock'] && $product['current_stock'] > 0): ?>
                                    <div class="alert alert-warning mt-2">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Stock is at minimum level. Please replenish.
                                    </div>
                                <?php elseif ($product['current_stock'] <= 0): ?>
                                    <div class="alert alert-danger mt-2">
                                        <i class="fas fa-times-circle me-2"></i>
                                        Product is out of stock!
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-pie me-2"></i>Quick Actions
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="add_product.php?edit=<?php echo $product['id']; ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-2"></i>Edit Product
                    </a>
                    <a href="production.php?product=<?php echo $product['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-cogs me-2"></i>Add Production
                    </a>
                    <a href="sales.php?product=<?php echo $product['id']; ?>" class="btn btn-success">
                        <i class="fas fa-shopping-cart me-2"></i>Create Sale
                    </a>
                    <a href="product_barcode.php?id=<?php echo $product['id']; ?>" class="btn btn-secondary" target="_blank">
                        <i class="fas fa-barcode me-2"></i>Print Barcode
                    </a>
                    <button onclick="window.print()" class="btn btn-info text-white">
                        <i class="fas fa-print me-2"></i>Print Detail
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Summary Stats -->
        <div class="card mt-3">
            <div class="card-header">
                <i class="fas fa-chart-bar me-2"></i>Sales Summary
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <h6 class="text-muted">Total Sales</h6>
                        <h4 class="text-primary"><?php echo $totalSales; ?></h4>
                    </div>
                    <div class="col-6">
                        <h6 class="text-muted">Total Quantity</h6>
                        <h4 class="text-success"><?php echo number_format($totalQuantity, 2); ?></h4>
                    </div>
                </div>
                <hr>
                <div class="text-center">
                    <h6 class="text-muted">Total Revenue</h6>
                    <h4 class="text-success">PKR <?php echo formatCurrency($totalRevenue); ?></h4>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sales History -->
<div class="row mt-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-shopping-cart me-2"></i>Recent Sales History
                <a href="sales.php?product=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary float-end">
                    View All
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Qty</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($salesHistory) > 0): ?>
                                <?php foreach ($salesHistory as $sale): ?>
                                    <tr>
                                        <td><?php echo date('d-m-Y', strtotime($sale['sale_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                                        <td><?php echo number_format($sale['quantity'], 2); ?></td>
                                        <td>PKR <?php echo formatCurrency($sale['total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No sales found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-cogs me-2"></i>Production History
                <a href="production.php?product=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary float-end">
                    View All
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Production No</th>
                                <th>Quantity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($productionHistory) > 0): ?>
                                <?php foreach ($productionHistory as $production): ?>
                                    <tr>
                                        <td><?php echo date('d-m-Y', strtotime($production['production_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($production['production_no']); ?></td>
                                        <td><?php echo number_format($production['quantity'], 2); ?></td>
                                        <td>
                                            <span class="badge badge-status badge-<?php echo strtolower($production['status']); ?>">
                                                <?php echo ucfirst($production['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No production found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .btn, .navbar-custom, #sidebar, .breadcrumb {
        display: none !important;
    }
    #sidebar {
        width: 0 !important;
    }
    #content {
        margin-left: 0 !important;
        padding: 20px !important;
    }
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
    }
    .table {
        font-size: 12px !important;
    }
}
</style>

<?php
include '../includes/footer.php';
?>