<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Expense Heads';
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
    $head = getRow("SELECT category_name FROM expense_categories WHERE id = ?", 'i', [$id]);
    if ($head) {
        $hasExpenses = getRow("SELECT COUNT(*) AS cnt FROM expenses WHERE category = ?", 's', [$head['category_name']]);
        if ($hasExpenses['cnt'] > 0) {
            $message = 'Cannot delete expense head with existing expenses! (' . $hasExpenses['cnt'] . ' expenses found)';
            $messageType = 'danger';
        } else {
            modifyData("DELETE FROM expense_categories WHERE id = ?", 'i', [$id]);
            setFlash('Expense head deleted!', 'success');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// Fetch all heads with expense summary
$heads = getRows("SELECT ec.*,
    COUNT(e.id) AS expense_count,
    COALESCE(SUM(e.amount),0) AS total_amount,
    COALESCE(SUM(e.paid_amount),0) AS total_paid
    FROM expense_categories ec
    LEFT JOIN expenses e ON e.category = ec.category_name
    GROUP BY ec.id
    ORDER BY ec.id DESC");

$grandTotal = 0;
$grandPaid = 0;
foreach ($heads as $h) {
    $grandTotal += (float)$h['total_amount'];
    $grandPaid += (float)$h['total_paid'];
}

// View expenses for specific head
$viewHead = null;
$viewExpenses = [];
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $viewHead = getRow("SELECT * FROM expense_categories WHERE id = ?", 'i', [(int)$_GET['view']]);
    if ($viewHead) {
        $viewExpenses = getRows("SELECT * FROM expenses WHERE category = ? ORDER BY expense_date DESC", 's', [$viewHead['category_name']]);
    }
}

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
        <a href="expenses.php" class="btn btn-secondary btn-sm me-2"><i class="fas fa-arrow-left me-1"></i>All Expenses</a>
        <a href="add_expense_head.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Add New Head</a>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-4">
        <div class="stat-card" style="border-left-color:#6f42c1;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number" style="color:#6f42c1;"><?php echo count($heads); ?></div>
                    <div class="stat-label">Total Heads</div>
                </div>
                <div class="stat-icon" style="color:#6f42c1;"><i class="fas fa-tags"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="border-left-color:#dc3545;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number text-danger"><?php echo formatCurrency($grandTotal); ?></div>
                    <div class="stat-label">Grand Total</div>
                </div>
                <div class="stat-icon" style="color:#dc3545;"><i class="fas fa-money-bill-wave"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="border-left-color:#28a745;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number text-success"><?php echo formatCurrency($grandPaid); ?></div>
                    <div class="stat-label">Total Paid</div>
                </div>
                <div class="stat-icon" style="color:#28a745;"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header" style="background:#1a2332;color:#fff;">
                <i class="fas fa-tags me-2"></i>Expense Heads
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="headsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Head Name</th>
                                <th>Description</th>
                                <th>Total Expenses</th>
                                <th>Total Paid</th>
                                <th>Balance</th>
                                <th>Entries</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($heads as $h): ?>
                                <?php $bal = (float)$h['total_amount'] - (float)$h['total_paid']; ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($h['category_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($h['description'] ?: '-'); ?></td>
                                    <td><strong><?php echo formatCurrency($h['total_amount']); ?></strong></td>
                                    <td><?php echo formatCurrency($h['total_paid']); ?></td>
                                    <td class="<?php echo $bal > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo formatCurrency($bal); ?></td>
                                    <td><span class="badge bg-info"><?php echo $h['expense_count']; ?></span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?view=<?php echo $h['id']; ?>" class="btn btn-info" title="View Expenses">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="add_expense_head.php?edit=<?php echo $h['id']; ?>" class="btn btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete=<?php echo $h['id']; ?>" class="btn btn-danger delete-confirm" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
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

<!-- View Expenses Modal for specific head -->
<?php if ($viewHead): ?>
<div class="modal fade" id="viewExpensesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a2332;color:#fff;">
                <h5 class="modal-title"><i class="fas fa-receipt me-2"></i>Expenses: <?php echo htmlspecialchars($viewHead['category_name']); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (count($viewExpenses) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Expense No</th>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Paid By</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $j = 1; $tAmt = 0; $tPaid = 0; foreach ($viewExpenses as $ve): ?>
                                    <?php $tAmt += (float)$ve['amount']; $tPaid += (float)$ve['paid_amount']; ?>
                                    <tr>
                                        <td><?php echo $j++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($ve['expense_no']); ?></strong></td>
                                        <td><?php echo date('d-m-Y', strtotime($ve['expense_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($ve['description'] ?: '-'); ?></td>
                                        <td><?php echo formatCurrency($ve['amount']); ?></td>
                                        <td><?php echo formatCurrency($ve['paid_amount']); ?></td>
                                        <td class="<?php echo $ve['balance'] > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo formatCurrency($ve['balance']); ?></td>
                                        <td><?php echo htmlspecialchars($ve['paid_by'] ?: '-'); ?></td>
                                        <td>
                                            <?php if ($ve['payment_status'] === 'paid'): ?>
                                                <span class="badge bg-success">Paid</span>
                                            <?php elseif ($ve['payment_status'] === 'partial'): ?>
                                                <span class="badge bg-warning text-dark">Partial</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Unpaid</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-active">
                                    <td colspan="4"><strong>Totals</strong></td>
                                    <td><strong><?php echo formatCurrency($tAmt); ?></strong></td>
                                    <td><strong><?php echo formatCurrency($tPaid); ?></strong></td>
                                    <td><strong><?php echo formatCurrency($tAmt - $tPaid); ?></strong></td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">No expenses found for this head.</p>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="expenses.php" class="btn btn-primary">Add Expense</a>
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
    $('#headsTable').DataTable({ pageLength:25, order:[[0,'desc']], language:{ search:'Search Heads:', emptyTable:'No expense heads found' } });
    <?php if ($viewHead): ?>
        var modal = new bootstrap.Modal(document.getElementById('viewExpensesModal'));
        modal.show();
    <?php endif; ?>
    $('.delete-confirm').on('click', function(e) { if (!confirm('Delete this expense head?')) e.preventDefault(); });
});
</script>

<?php include '../includes/footer.php'; ?>
