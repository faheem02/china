<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Finished Goods';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $result = modifyData("DELETE FROM finished_goods WHERE id = ?", 'i', [$id]);
    
    if ($result !== false) {
        setFlash('Product deleted successfully!', 'success');
    } else {
        setFlash('Error deleting product!', 'danger');
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$products = getRows("SELECT * FROM finished_goods ORDER BY id DESC");

include '../includes/header.php';
include '../includes/sidebar.php';
?>

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
        <a href="add_finished_goods.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Product
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list me-2"></i>Finished Goods List</span>
                <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="productTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Code</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Unit</th>
                                <th>Stock</th>
                                <th>Min Stock</th>
                                <th>Selling Price</th>
                                <th>Cost Price</th>
                                <th>Profit Margin</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($products) > 0): ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?php echo $product['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($product['product_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['category']); ?></td>
                                        <td><?php echo htmlspecialchars($product['unit']); ?></td>
                                        <td><?php echo number_format($product['current_stock'], 2); ?></td>
                                        <td><?php echo number_format($product['minimum_stock'], 2); ?></td>
                                        <td><?php echo formatCurrency($product['selling_price']); ?></td>
                                        <td><?php echo formatCurrency($product['cost_price']); ?></td>
                                        <td>
                                            <?php 
                                            $margin = 0;
                                            if ($product['cost_price'] > 0) {
                                                $margin = (($product['selling_price'] - $product['cost_price']) / $product['cost_price']) * 100;
                                            }
                                            echo number_format($margin, 2) . '%';
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-status badge-<?php echo $product['status']; ?>">
                                                <?php echo ucfirst($product['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?delete=<?php echo $product['id']; ?>" class="btn btn-sm btn-danger delete-confirm">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-3">
                                        <i class="fas fa-info-circle me-2"></i>No finished goods found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#productTable').DataTable({
        "pageLength": 25,
        "language": {
            "search": "Search Products:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ products",
            "emptyTable": "No finished goods found"
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>