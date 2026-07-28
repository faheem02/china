<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Hold Bills';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

// Delete hold bill
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $sql = "DELETE FROM sale_items WHERE sale_id = ?";
    modifyData($sql, 'i', [$id]);
    $sql = "DELETE FROM sales WHERE id = ? AND status = 'hold'";
    $result = modifyData($sql, 'i', [$id]);
    if ($result !== false) {
        setFlash('Hold bill deleted successfully!', 'success');
    } else {
        setFlash('Error deleting hold bill!', 'danger');
    }
    header('Location: view_hold_bills.php');
    exit;
}

// Get all hold bills
$bills = getRows("SELECT s.*,
    CASE WHEN s.customer_type = 'credit' THEN c.customer_name ELSE s.walkin_name END as customer_display
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE s.status = 'hold'
    ORDER BY s.id DESC");

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="row">
    <div class="col-12">
        <?php if (isset($message)): ?>
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
        <a href="new_sale.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>New Sale
        </a>
        <a href="sales.php" class="btn btn-info text-white">
            <i class="fas fa-list me-2"></i>View Sale Invoice
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-pause-circle me-2"></i>Hold Bills</span>
                <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="holdTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Sale No</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Amount (PKR)</th>
                                <th>Discount</th>
                                <th>Final</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($bills) > 0): ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($bills as $b): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($b['sale_no']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($b['customer_display'] ?: 'N/A'); ?></td>
                                        <td><span class="badge bg-<?php echo $b['customer_type'] === 'credit' ? 'primary' : 'secondary'; ?>"><?php echo ucfirst($b['customer_type']); ?></span></td>
                                        <td><?php echo date('d-m-Y', strtotime($b['sale_date'])); ?></td>
                                        <td><?php echo number_format($b['total_amount'], 2); ?></td>
                                        <td><?php echo number_format($b['discount'], 2); ?></td>
                                        <td><strong><?php echo number_format($b['final_amount'], 2); ?></strong></td>
                                        <td>
                                            <a href="new_sale.php?hold_id=<?php echo $b['id']; ?>" class="btn btn-success btn-sm" title="Proceed">
                                                <i class="fas fa-play"></i>
                                            </a>
                                            <a href="?delete=<?php echo $b['id']; ?>" class="btn btn-danger btn-sm delete-confirm" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
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
    $('#holdTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]],
        "language": {
            "search": "Search Hold Bills:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ bills",
            "emptyTable": "No hold bills found"
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
