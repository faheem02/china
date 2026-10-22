<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Local Purchases';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $purchase = getRow("SELECT supplier_id, balance, paid_amount, payment_method, bank_id FROM local_purchases WHERE id = ?", 'i', [$id]);
    $items = getRows("SELECT material_id, quantity, item_type FROM local_purchase_items WHERE purchase_id = ?", 'i', [$id]);
    modifyData("DELETE FROM local_purchase_items WHERE purchase_id = ?", 'i', [$id]);
    $result = modifyData("DELETE FROM local_purchases WHERE id = ?", 'i', [$id]);
    if ($result !== false) {
        if ($purchase) {
            modifyData("UPDATE local_suppliers SET current_balance = current_balance - ? WHERE id = ?", 'di', [(float)$purchase['balance'], (int)$purchase['supplier_id']]);
            if ($purchase['payment_method'] === 'bank' && !empty($purchase['bank_id']) && $purchase['paid_amount'] > 0) {
                updateBankBalance($purchase['bank_id'], $purchase['paid_amount'], 'in');
            }
        } 
        foreach ($items as $item) {
            if ($item['item_type'] === 'finished_good') {
                modifyData("UPDATE finished_goods SET current_stock = current_stock - ? WHERE id = ?", 'di', [$item['quantity'], $item['material_id']]);
            } else {
                modifyData("UPDATE raw_materials SET current_stock = current_stock - ? WHERE id = ?", 'di', [$item['quantity'], $item['material_id']]);
            }
        }
        setFlash('Purchase order deleted successfully!', 'success');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $message = 'Error deleting purchase order!';
        $messageType = 'danger';
    }
}

$purchases = getRows("SELECT p.*, s.supplier_name, s.company_name, s.phone
        FROM local_purchases p
        LEFT JOIN local_suppliers s ON p.supplier_id = s.id
        ORDER BY p.id DESC");

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" style="background:#1a2332;color:#fff;">
                <span><i class="fas fa-list me-2"></i>Local Purchases List
                <span class="ms-2 badge bg-info">PKR</span></span>
                <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="purchaseTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Purchase No</th>
                                <th>Supplier</th>
                                <th>Date</th>
                                <th>Invoice No</th>
                                <th>Total Amount</th>
                                <th>Payment Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($purchases) > 0): ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($purchases as $purchase): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($purchase['purchase_no']); ?></strong></td>
                                        <td>
                                            <a href="supplier_detail.php?id=<?php echo $purchase['supplier_id']; ?>">
                                                <?php echo htmlspecialchars($purchase['supplier_name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo formatDate($purchase['purchase_date']); ?></td>
                                        <td><?php echo htmlspecialchars($purchase['invoice_no']); ?></td>
                                        <td><?php echo formatCurrency($purchase['total_amount']); ?></td>
                                        <td>
                                            <span class="badge badge-status badge-<?php echo strtolower($purchase['payment_status']); ?>">
                                                <?php echo ucfirst($purchase['payment_status']); ?>
                                            </span>
                                            <?php if ($purchase['payment_method'] === 'bank'): ?>
                                                <div class="mt-1"><span class="badge bg-info text-dark" style="font-size:10px;"><i class="fas fa-university me-1"></i><?php echo htmlspecialchars($purchase['bank_name'] ?: 'Bank'); ?></span></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="purchase_detail.php?type=local&id=<?php echo $purchase['id']; ?>" class="btn btn-info" title="View Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="add_local_purchase.php?id=<?php echo $purchase['id']; ?>" class="btn btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?delete=<?php echo $purchase['id']; ?>" class="btn btn-danger delete-confirm" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <a href="purchase_print.php?id=<?php echo $purchase['id']; ?>" class="btn btn-success" target="_blank" title="Print">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
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
        "language": {
            "search": "Search Purchases:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ purchases",
            "emptyTable": "No local purchases found"
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
