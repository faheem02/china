<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Raw Materials';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $result = modifyData("DELETE FROM raw_materials WHERE id = ?", 'i', [$id]);
    
    if ($result !== false) {
        setFlash('Raw material deleted successfully!', 'success');
    } else {
        setFlash('Error deleting raw material!', 'danger');
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$materials = getRows("SELECT r.*, 
    CASE 
        WHEN r.supplier_type = 'chinese' THEN (SELECT supplier_name FROM chinese_suppliers WHERE id = r.supplier_id)
        WHEN r.supplier_type = 'local' THEN (SELECT supplier_name FROM local_suppliers WHERE id = r.supplier_id)
        ELSE NULL
    END as supplier_name
    FROM raw_materials r 
    ORDER BY r.id DESC");

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
        <a href="add_raw_materials.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Material
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list me-2"></i>Raw Materials List</span>
                <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="materialTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Code</th>
                                <th>Material Name</th>
                                <th>Category</th>
                                <th>Unit</th>
                                <th>Stock</th>
                                <th>Min Stock</th>
                                <th>Price (PKR)</th>
                                <th>Price (CNY)</th>
                                <th>Supplier</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($materials) > 0): ?>
                                <?php foreach ($materials as $material): ?>
                                    <tr>
                                        <td><?php echo $material['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($material['material_name']); ?></td>
                                        <td><?php echo htmlspecialchars($material['category']); ?></td>
                                        <td><?php echo htmlspecialchars($material['unit']); ?></td>
                                        <td><?php echo number_format($material['current_stock'], 2); ?></td>
                                        <td><?php echo number_format($material['minimum_stock'], 2); ?></td>
                                        <td><?php echo formatCurrency($material['purchase_price_pkr']); ?></td>
                                        <td>
                                            <?php if ($material['supplier_type'] == 'chinese'): ?>
                                                <span class="badge bg-info"><?php echo number_format($material['purchase_price_cny'] ?? 0, 2); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $material['supplier_type'] == 'chinese' ? 'info' : 'success'; ?>">
                                                <?php echo ucfirst($material['supplier_type']); ?>
                                            </span>
                                            <?php if ($material['supplier_name']): ?>
                                                <br><small><?php echo htmlspecialchars($material['supplier_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="?delete=<?php echo $material['id']; ?>" class="btn btn-sm btn-danger delete-confirm">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-3">
                                        <i class="fas fa-info-circle me-2"></i>No raw materials found
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
    $('#materialTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]],
        "language": {
            "search": "Search Materials:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ materials",
            "emptyTable": "No raw materials found"
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>