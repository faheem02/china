<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Import Purchases';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $items = getRows("SELECT material_id, quantity FROM import_purchase_items WHERE purchase_id = ?", 'i', [$id]);
    $result = modifyData("DELETE FROM import_purchases WHERE id = ?", 'i', [$id]);
    if ($result !== false) {
        modifyData("DELETE FROM import_purchase_items WHERE purchase_id = ?", 'i', [$id]);
        foreach ($items as $item) {
            modifyData("UPDATE raw_materials SET current_stock = current_stock - ? WHERE id = ?", 'di', [$item['quantity'], $item['material_id']]);
        }
        setFlash('Import purchase deleted successfully!', 'success');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $message = 'Error deleting import purchase!';
        $messageType = 'danger';
    }
}

$purchases = getRows("SELECT p.*, s.supplier_name, s.company_name, s.phone
        FROM import_purchases p
        LEFT JOIN chinese_suppliers s ON p.supplier_id = s.id
        ORDER BY p.id DESC");

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

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" style="background:#1a2332;color:#fff;">
                <span><i class="fas fa-ship me-2"></i>Import Purchases List
                <span class="ms-2 badge bg-warning text-dark">CNY</span>
                <span class="ms-2 badge bg-info">PKR</span></span>
                <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <a href="add_import_purchase.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>New Import Purchase
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="purchaseTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Purchase No</th>
                                <th>Supplier</th>
                                <th>Date</th>
                                <th>Invoice No</th>
                                <th>Bill (CNY)</th>
                                <th>S.Tax (CNY)</th>
                                <th>Grand (CNY)</th>
                                <th>Grand (PKR)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($purchases) > 0): ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($purchases as $purchase): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($purchase['purchase_no'] ?? ''); ?></strong></td>
                                        <td>
                                            <?php if (isset($purchase['supplier_id']) && $purchase['supplier_id']): ?>
                                                <a href="chinese_supplier_detail.php?id=<?php echo $purchase['supplier_id']; ?>">
                                                    <?php echo htmlspecialchars($purchase['supplier_name'] ?? ''); ?>
                                                </a>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($purchase['supplier_name'] ?? 'N/A'); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo isset($purchase['purchase_date']) ? date('d-m-Y', strtotime($purchase['purchase_date'])) : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($purchase['invoice_no'] ?? '-'); ?></td>
                                        <td><strong><?php echo formatCurrency($purchase['total_cny'] ?? 0); ?></strong></td>
                                        <td><?php echo formatCurrency($purchase['tax_amount_cny'] ?? 0); ?></td>
                                        <td class="fw-bold text-success"><?php echo formatCurrency($purchase['grand_total_cny'] ?? 0); ?></td>
                                        <td><?php echo formatCurrency($purchase['grand_total_pkr'] ?? 0); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="add_import_purchase.php?edit=<?php echo $purchase['id']; ?>" class="btn btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="import_purchase_print.php?id=<?php echo $purchase['id']; ?>" class="btn btn-info" title="Print" target="_blank">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                                <a href="?delete=<?php echo $purchase['id']; ?>" class="btn btn-danger delete-confirm" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-3">
                                        <i class="fas fa-info-circle me-2"></i>No import purchases found
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
    $('#purchaseTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]],
        "columnDefs": [{ "orderable": false, "targets": 9 }],
        "language": {
            "search": "Search Import Purchases:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ import purchases",
            "emptyTable": "No import purchases found"
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
