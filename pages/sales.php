<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Sales Orders';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    $sale = getRow("SELECT customer_id, customer_type, balance, paid_amount, payment_method, bank_id FROM sales WHERE id = ?", 'i', [$id]);
    
    $sql = "SELECT product_id, product_type, quantity FROM sale_items WHERE sale_id = ?";
    $items = getRows($sql, 'i', [$id]);
    
    $sql = "DELETE FROM sale_items WHERE sale_id = ?";
    modifyData($sql, 'i', [$id]);
    
    $sql = "DELETE FROM sales WHERE id = ?";
    $result = modifyData($sql, 'i', [$id]);
    
    if ($result) {
        foreach ($items as $item) {
            if (($item['product_type'] ?? 'finished_good') === 'raw_material') {
                $sql = "UPDATE raw_materials SET current_stock = current_stock + ? WHERE id = ?";
            } else {
                $sql = "UPDATE finished_goods SET current_stock = current_stock + ? WHERE id = ?";
            }
            modifyData($sql, 'di', [$item['quantity'], $item['product_id']]);
        }

        if ($sale && $sale['customer_type'] === 'credit' && $sale['customer_id'] > 0 && $sale['balance'] > 0) {
            modifyData("UPDATE customers SET current_balance = current_balance - ? WHERE id = ?", 'di', [$sale['balance'], $sale['customer_id']]);
        }
        
        if ($sale && ($sale['payment_method'] === 'cash' || $sale['payment_method'] === 'bank') && $sale['paid_amount'] > 0) {
            if ($sale['payment_method'] === 'bank' && !empty($sale['bank_id'])) {
                updateBankBalance($sale['bank_id'], $sale['paid_amount'], 'out');
            }
            modifyData("DELETE FROM accounts WHERE reference_type = 'Sale' AND reference_id = ?", 'i', [$id]);
        }
        
        setFlash('Sale deleted successfully! Stock restored.', 'success');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        setFlash('Error deleting sale!', 'danger');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Get all completed/pending sales
$sql = "SELECT s.*, c.customer_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.status IN ('completed', 'pending')
        ORDER BY s.id DESC";
$sales = getRows($sql);

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
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-handshake me-2"></i>Sales Orders List
                <span class="ms-2 badge bg-success">PKR</span></span>
                <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="saleTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Sale No</th>
                                <th>Customer</th>
                                <th style="white-space:nowrap;min-width:100px;">Date</th>
                                <th>Total (PKR)</th>
                                <th>Discount</th>
                                <th>Net Amount</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($sales) > 0): ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($sales as $sale): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($sale['sale_no']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($sale['customer_name'] ?: 'N/A'); ?></td>
                                        <td><?php echo formatDate($sale['sale_date']); ?></td>
                                        <td><?php echo number_format($sale['total_amount'], 2); ?></td>
                                        <td><?php echo number_format($sale['discount'] ?? 0, 2); ?></td>
                                        <td><strong><?php echo number_format($sale['net_amount'], 2); ?></strong></td>
                                        <td>
                                            <span class="badge badge-status badge-<?php echo strtolower($sale['payment_status']); ?>">
                                                <?php echo ucfirst($sale['payment_status']); ?>
                                            </span>
                                            <?php if ($sale['payment_method'] === 'bank'): ?>
                                                <div class="mt-1"><span class="badge bg-info text-dark" style="font-size:10px;"><i class="fas fa-university me-1"></i><?php echo htmlspecialchars($sale['bank_name'] ?: 'Bank'); ?></span></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="sale_view.php?id=<?php echo $sale['id']; ?>" class="btn btn-info" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="sale_print.php?id=<?php echo $sale['id']; ?>" class="btn btn-success" title="Print Invoice">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                                <a href="sale_refund.php?id=<?php echo $sale['id']; ?>" class="btn btn-warning" title="Refund">
                                                    <i class="fas fa-undo"></i>
                                                </a>
                                                <a href="?delete=<?php echo $sale['id']; ?>" class="btn btn-danger delete-confirm" title="Delete">
                                                    <i class="fas fa-trash"></i>
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
    $('#saleTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]],
        "language": {
            "search": "Search Sales:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ sales",
            "emptyTable": "No sales found"
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
