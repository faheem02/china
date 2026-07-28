<?php
/**
 * Products (Finished Goods) List Page
 * Manufacturing ERP System
 */

// Include database connection
require_once '../includes/database.php';

// Require login
requireLogin();

$pageTitle = 'Products';
$message = '';
$messageType = '';

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if product has sales
    $sql = "SELECT COUNT(*) as count FROM sale_items WHERE product_id = ?";
    $check = getRow($sql, 'i', [$id]);
    
    if ($check && $check['count'] > 0) {
        $message = 'Cannot delete product! It has sales records.';
        $messageType = 'danger';
    } else {
        $sql = "DELETE FROM finished_goods WHERE id = ?";
        $result = modifyData($sql, 'i', [$id]);
        
        if ($result !== false) {
            $message = 'Product deleted successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error deleting product!';
            $messageType = 'danger';
        }
    }
}

// Get all products
$sql = "SELECT * FROM finished_goods ORDER BY id DESC";
$products = getRows($sql);

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
        <a href="add_product.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Product
        </a>
        <a href="product_stock_report.php" class="btn btn-info text-white">
            <i class="fas fa-chart-bar me-2"></i>Stock Report
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-boxes me-2"></i>Products List
                <span class="ms-2 badge bg-info">Finished Goods</span>
                <span class="ms-2 badge bg-success">Total: <?php echo count($products); ?></span></span>
                <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="productTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product Code</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Unit</th>
                                <th>Stock</th>
                                <th>Min Stock</th>
                                <th>Selling Price (PKR)</th>
                                <th>Cost Price (PKR)</th>
                                <th>Profit Margin</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($products) > 0): ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($products as $product): 
                                    $margin = 0;
                                    if ($product['cost_price'] > 0) {
                                        $margin = (($product['selling_price'] - $product['cost_price']) / $product['cost_price']) * 100;
                                    }
                                    $stockStatus = '';
                                    if ($product['current_stock'] <= $product['minimum_stock']) {
                                        $stockStatus = 'text-danger';
                                    } elseif ($product['current_stock'] <= $product['minimum_stock'] * 2) {
                                        $stockStatus = 'text-warning';
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($product['product_code']); ?></strong></td>
                                        <td>
                                            <a href="product_detail.php?id=<?php echo $product['id']; ?>">
                                                <?php echo htmlspecialchars($product['product_name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['category']); ?></td>
                                        <td><?php echo htmlspecialchars($product['unit']); ?></td>
                                        <td class="<?php echo $stockStatus; ?>">
                                            <strong><?php echo number_format($product['current_stock'], 2); ?></strong>
                                            <?php if ($product['current_stock'] <= $product['minimum_stock']): ?>
                                                <span class="badge bg-danger ms-1">Low Stock</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo number_format($product['minimum_stock'], 2); ?></td>
                                        <td><?php echo formatCurrency($product['selling_price']); ?></td>
                                        <td><?php echo formatCurrency($product['cost_price']); ?></td>
                                        <td>
                                            <span class="<?php echo $margin > 0 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo number_format($margin, 2); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-status badge-<?php echo $product['status']; ?>">
                                                <?php echo ucfirst($product['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="btn btn-info" title="View Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="add_product.php?edit=<?php echo $product['id']; ?>" class="btn btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?delete=<?php echo $product['id']; ?>" class="btn btn-danger delete-confirm" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <a href="product_barcode.php?id=<?php echo $product['id']; ?>" class="btn btn-secondary" target="_blank" title="Barcode">
                                                    <i class="fas fa-barcode"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-3">
                                        <i class="fas fa-info-circle me-2"></i>No products found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-primary">
                            <tr>
                                <th colspan="5" class="text-end">Total</th>
                                <th><?php echo number_format(array_sum(array_column($products, 'current_stock')), 2); ?></th>
                                <th colspan="6"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Low Stock Alert -->
<?php 
$lowStockProducts = array_filter($products, function($p) {
    return $p['current_stock'] <= $p['minimum_stock'] && $p['status'] == 'active';
});
if (count($lowStockProducts) > 0): 
?>
<div class="row mt-3">
    <div class="col-12">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <i class="fas fa-exclamation-triangle me-2"></i>Low Stock Alert
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Product Code</th>
                                <th>Product Name</th>
                                <th>Current Stock</th>
                                <th>Minimum Stock</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lowStockProducts as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['product_code']); ?></td>
                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                    <td class="text-danger"><strong><?php echo number_format($product['current_stock'], 2); ?></strong></td>
                                    <td><?php echo number_format($product['minimum_stock'], 2); ?></td>
                                    <td>
                                        <a href="add_product.php?edit=<?php echo $product['id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit me-1"></i>Update Stock
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#productTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]],
        "language": {
            "search": "Search Products:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ products",
            "emptyTable": "No products found"
        }
    });
});
</script>

<?php
include '../includes/footer.php';
?>