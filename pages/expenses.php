<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Expenses';
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
    $result = modifyData("DELETE FROM expenses WHERE id = ?", 'i', [$id]);
    if ($result !== false) {
        setFlash('Expense deleted successfully!', 'success');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $message = 'Error deleting expense!';
        $messageType = 'danger';
    }
}

// Handle Add/Edit POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $expense_date = $_POST['expense_date'];
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $amount = (float)($_POST['amount'] ?? 0);
    $paid_amount = (float)($_POST['paid_amount'] ?? 0);
    $paid_by = trim($_POST['paid_by'] ?? '');
    $reference_no = trim($_POST['reference_no'] ?? '');
    $payment_status = $_POST['payment_status'] ?? 'unpaid';
    $status = $_POST['status'] ?? 'pending';

    if (empty($expense_date)) {
        $message = 'Please select expense date!';
        $messageType = 'danger';
    } elseif (empty($category)) {
        $message = 'Please select an expense head!';
        $messageType = 'danger';
    } elseif ($amount <= 0) {
        $message = 'Amount must be greater than 0!';
        $messageType = 'danger';
    } else {
        $balance = $amount - $paid_amount;
        $currentDateTime = getCurrentDateTime();

        if ($id > 0) {
            $result = modifyData("UPDATE expenses SET expense_date=?, category=?, description=?, amount=?, paid_amount=?, balance=?, paid_by=?, reference_no=?, payment_status=?, status=?, date_time=? WHERE id=?",
                'sssddssssssi', [$expense_date, $category, $description, $amount, $paid_amount, $balance, $paid_by, $reference_no, $payment_status, $status, $currentDateTime, $id]);
        } else {
            $expense_no = generateCode('EXP');
            $result = insertData("INSERT INTO expenses (expense_no, expense_date, category, description, amount, paid_amount, balance, paid_by, reference_no, payment_status, status, date_time) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                'ssssdddsssss', [$expense_no, $expense_date, $category, $description, $amount, $paid_amount, $balance, $paid_by, $reference_no, $payment_status, $status, $currentDateTime]);
        }

        if ($result !== false) {
            setFlash($id > 0 ? 'Expense updated successfully!' : 'Expense added successfully!', 'success');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $message = 'Error saving expense!';
            $messageType = 'danger';
        }
    }
}

// Fetch data
$expenses = getRows("SELECT * FROM expenses ORDER BY id DESC");
$expenseHeads = getRows("SELECT * FROM expense_categories WHERE status = 'active' ORDER BY category_name");
$totalExpenses = getRow("SELECT COALESCE(SUM(amount),0) AS total, COALESCE(SUM(paid_amount),0) AS paid FROM expenses");

// Edit data
$editExpense = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editExpense = getRow("SELECT * FROM expenses WHERE id = ?", 'i', [(int)$_GET['edit']]);
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
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color:#dc3545;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number text-danger"><?php echo formatCurrency($totalExpenses['total']); ?></div>
                    <div class="stat-label">Total Expenses</div>
                </div>
                <div class="stat-icon" style="color:#dc3545;"><i class="fas fa-money-bill-wave"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color:#28a745;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number text-success"><?php echo formatCurrency($totalExpenses['paid']); ?></div>
                    <div class="stat-label">Paid</div>
                </div>
                <div class="stat-icon" style="color:#28a745;"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color:#ffc107;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number text-warning"><?php echo formatCurrency($totalExpenses['total'] - $totalExpenses['paid']); ?></div>
                    <div class="stat-label">Outstanding</div>
                </div>
                <div class="stat-icon" style="color:#ffc107;"><i class="fas fa-clock"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color:#0d6efd;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number text-primary"><?php echo count($expenses); ?></div>
                    <div class="stat-label">Total Entries</div>
                </div>
                <div class="stat-icon" style="color:#0d6efd;"><i class="fas fa-receipt"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <a href="expense_heads.php" class="btn btn-outline-primary btn-sm me-2"><i class="fas fa-tags me-1"></i>Expense Heads</a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#expenseModal">
            <i class="fas fa-plus me-2"></i>Add New Expense
        </button>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header" style="background:#1a2332;color:#fff;">
                <i class="fas fa-money-bill-wave me-2"></i>Expenses List
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="expenseTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Expense No</th>
                                <th>Date</th>
                                <th>Expense Head</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Paid By</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($expenses as $exp): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($exp['expense_no']); ?></strong></td>
                                    <td><?php echo date('d-m-Y', strtotime($exp['expense_date'])); ?></td>
                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($exp['category']); ?></span></td>
                                    <td><?php echo htmlspecialchars($exp['description'] ?: '-'); ?></td>
                                    <td><strong><?php echo formatCurrency($exp['amount']); ?></strong></td>
                                    <td><?php echo formatCurrency($exp['paid_amount']); ?></td>
                                    <td class="<?php echo $exp['balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo formatCurrency($exp['balance']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($exp['paid_by'] ?: '-'); ?></td>
                                    <td>
                                        <?php if ($exp['payment_status'] === 'paid'): ?>
                                            <span class="badge bg-success">Paid</span>
                                        <?php elseif ($exp['payment_status'] === 'partial'): ?>
                                            <span class="badge bg-warning text-dark">Partial</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Unpaid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?edit=<?php echo $exp['id']; ?>" class="btn btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete=<?php echo $exp['id']; ?>" class="btn btn-danger delete-confirm" title="Delete">
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

<!-- Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a2332;color:#fff;">
                <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i><?php echo $editExpense ? 'Edit Expense' : 'Add New Expense'; ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <?php if ($editExpense): ?>
                        <input type="hidden" name="id" value="<?php echo $editExpense['id']; ?>">
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expense Date *</label>
                            <input type="date" class="form-control" name="expense_date" value="<?php echo $editExpense ? $editExpense['expense_date'] : date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expense Head *</label>
                            <select class="form-select" name="category" required>
                                <option value="">Select Expense Head</option>
                                <?php foreach ($expenseHeads as $head): ?>
                                    <option value="<?php echo htmlspecialchars($head['category_name']); ?>" <?php echo ($editExpense && $editExpense['category'] === $head['category_name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($head['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2"><?php echo $editExpense ? htmlspecialchars($editExpense['description']) : ''; ?></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Amount (PKR) *</label>
                            <input type="number" step="0.01" class="form-control" name="amount" value="<?php echo $editExpense ? $editExpense['amount'] : '0.00'; ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Paid Amount (PKR)</label>
                            <input type="number" step="0.01" class="form-control" name="paid_amount" value="<?php echo $editExpense ? $editExpense['paid_amount'] : '0.00'; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Paid By</label>
                            <input type="text" class="form-control" name="paid_by" value="<?php echo $editExpense ? htmlspecialchars($editExpense['paid_by'] ?? '') : ''; ?>" placeholder="e.g. Kashif Raza">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Reference No</label>
                            <input type="text" class="form-control" name="reference_no" value="<?php echo $editExpense ? htmlspecialchars($editExpense['reference_no'] ?? '') : ''; ?>" placeholder="e.g. Bill #123">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Payment Status</label>
                            <select class="form-select" name="payment_status">
                                <option value="unpaid" <?php echo ($editExpense && ($editExpense['payment_status'] ?? '') == 'unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                                <option value="partial" <?php echo ($editExpense && ($editExpense['payment_status'] ?? '') == 'partial') ? 'selected' : ''; ?>>Partial</option>
                                <option value="paid" <?php echo ($editExpense && ($editExpense['payment_status'] ?? '') == 'paid') ? 'selected' : ''; ?>>Paid</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="pending" <?php echo ($editExpense && $editExpense['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="paid" <?php echo ($editExpense && $editExpense['status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                                <option value="cancelled" <?php echo ($editExpense && $editExpense['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i><?php echo $editExpense ? 'Update' : 'Save'; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    <?php if ($editExpense): ?>
        var modal = new bootstrap.Modal(document.getElementById('expenseModal'));
        modal.show();
    <?php endif; ?>
    $('#expenseTable').DataTable({ pageLength:25, order:[[0,'desc']], language:{ search:'Search Expenses:', emptyTable:'No expenses found' } });
    $('.delete-confirm').on('click', function(e) { if (!confirm('Delete this expense?')) e.preventDefault(); });
});
</script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<?php include '../includes/footer.php'; ?>
