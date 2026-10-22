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
    $exp = getRow("SELECT paid_by, bank_id, paid_amount FROM expenses WHERE id = ?", 'i', [$id]);
    if ($exp && in_array($exp['paid_by'], ['bank', 'bank_transfer', 'cheque'], true) && !empty($exp['bank_id']) && $exp['paid_amount'] > 0) {
        updateBankBalance($exp['bank_id'], $exp['paid_amount'], 'in');
    }
    modifyData("DELETE FROM accounts WHERE reference_type = 'Expense' AND reference_id = ?", 'i', [$id]);
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
    $paid_by = trim($_POST['paid_by'] ?? 'cash');
    if (!in_array($paid_by, ['cash', 'bank', 'credit'], true)) {
        $paid_by = 'cash';
    }
    $paid_amount = ($paid_by === 'credit') ? 0 : $amount;
    $bank_id = ($paid_by === 'bank') ? (int)($_POST['bank_id'] ?? 0) : null;
    $bank_name = null;
    if ($bank_id > 0) {
        $bRow = getRow("SELECT bank_name, account_number FROM bank_accounts WHERE id = ?", 'i', [$bank_id]);
        if ($bRow) {
            $bank_name = $bRow['bank_name'] . ' (' . $bRow['account_number'] . ')';
        }
    }
    $reference_no = trim($_POST['reference_no'] ?? '');
    $payment_status = ($paid_amount >= $amount) ? 'paid' : (($paid_amount > 0) ? 'partial' : 'unpaid');
    $status = ($payment_status === 'paid') ? 'paid' : 'pending';

    if (empty($expense_date)) {
        $message = 'Please select expense date!';
        $messageType = 'danger';
    } elseif (empty($category)) {
        $message = 'Please select an expense category!';
        $messageType = 'danger';
    } elseif ($amount <= 0) {
        $message = 'Amount must be greater than 0!';
        $messageType = 'danger';
    } elseif ($paid_by === 'bank' && empty($bank_id)) {
        $message = 'Please select a bank account!';
        $messageType = 'danger';
    } else {
        $balance = max(0, $amount - $paid_amount);
        $currentDateTime = getCurrentDateTime();

        if ($id > 0) {
            // Restore previous bank balance if old was bank
            $oldExp = getRow("SELECT paid_by, bank_id, paid_amount FROM expenses WHERE id = ?", 'i', [$id]);
            if ($oldExp && in_array($oldExp['paid_by'], ['bank', 'bank_transfer', 'cheque'], true) && !empty($oldExp['bank_id']) && $oldExp['paid_amount'] > 0) {
                updateBankBalance($oldExp['bank_id'], $oldExp['paid_amount'], 'in');
            }

            $result = modifyData("UPDATE expenses SET expense_date=?, category=?, description=?, amount=?, paid_amount=?, balance=?, paid_by=?, bank_id=?, bank_name=?, reference_no=?, payment_status=?, status=?, date_time=? WHERE id=?",
                'sssddssisssssi', [$expense_date, $category, $description, $amount, $paid_amount, $balance, $paid_by, $bank_id, $bank_name, $reference_no, $payment_status, $status, $currentDateTime, $id]);
            
            if ($paid_by === 'bank' && $bank_id > 0 && $paid_amount > 0) {
                updateBankBalance($bank_id, $paid_amount, 'out');
            }

            modifyData("DELETE FROM accounts WHERE reference_type = 'Expense' AND reference_id = ?", 'i', [$id]);
            if ($paid_amount > 0) {
                $accountType = ($paid_by === 'cash') ? 'Cash' : 'Bank';
                $lastAcc = getRow("SELECT balance FROM accounts WHERE account_type = ? ORDER BY id DESC LIMIT 1", 's', [$accountType]);
                $curBal = $lastAcc ? (float)$lastAcc['balance'] : 0;
                $newBal = $curBal - $paid_amount;
                $accDesc = "Expense - " . $category . ($bank_name ? " [$bank_name]" : "") . ($description ? ": " . $description : "");
                insertData(
                    "INSERT INTO accounts (account_date, account_type, reference_type, reference_id, description, debit, credit, balance, date_time) VALUES (?, ?, 'Expense', ?, ?, 0, ?, ?, ?)",
                    'ssisdds',
                    [$expense_date, $accountType, $id, $accDesc, $paid_amount, $newBal, $currentDateTime]
                );
            }
        } else {
            $expense_no = generateCode('EXP');
            $result = insertData("INSERT INTO expenses (expense_no, expense_date, category, description, amount, paid_amount, balance, paid_by, bank_id, bank_name, reference_no, payment_status, status, date_time) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                'ssssdddssissss', [$expense_no, $expense_date, $category, $description, $amount, $paid_amount, $balance, $paid_by, $bank_id, $bank_name, $reference_no, $payment_status, $status, $currentDateTime]);
            
            if ($result !== false && $paid_amount > 0) {
                if ($paid_by === 'bank' && $bank_id > 0) {
                    updateBankBalance($bank_id, $paid_amount, 'out');
                }
                $accountType = ($paid_by === 'cash') ? 'Cash' : 'Bank';
                $lastAcc = getRow("SELECT balance FROM accounts WHERE account_type = ? ORDER BY id DESC LIMIT 1", 's', [$accountType]);
                $curBal = $lastAcc ? (float)$lastAcc['balance'] : 0;
                $newBal = $curBal - $paid_amount;
                $accDesc = "Expense - " . $category . " (" . $expense_no . ")" . ($bank_name ? " [$bank_name]" : "") . ($description ? ": " . $description : "");
                insertData(
                    "INSERT INTO accounts (account_date, account_type, reference_type, reference_id, description, debit, credit, balance, date_time) VALUES (?, ?, 'Expense', ?, ?, 0, ?, ?, ?)",
                    'ssisdds',
                    [$expense_date, $accountType, $result, $accDesc, $paid_amount, $newBal, $currentDateTime]
                );
            }
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
$bankAccounts = getActiveBankAccounts();
$totalExpenses = getRow("SELECT COALESCE(SUM(amount),0) AS total FROM expenses");

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
    <div class="col-md-6">
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
    <div class="col-md-6">
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
    <div class="col-12 d-flex flex-wrap gap-2">
        <a href="add_expense_head.php" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>Record New Expense
        </a>
        <a href="expense_heads.php" class="btn btn-outline-primary">
            <i class="fas fa-tags me-1"></i>Expense Categories
        </a>
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
                                <th>Expense Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($expenses as $exp): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($exp['expense_no']); ?></strong></td>
                                    <td><?php echo formatDate($exp['expense_date']); ?></td>
                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($exp['category']); ?></span></td>
                                    <td><?php echo htmlspecialchars($exp['description'] ?: '-'); ?></td>
                                    <td><strong><?php echo formatCurrency($exp['amount']); ?></strong></td>
                                    <td>
                                        <?php if (in_array($exp['paid_by'], ['bank', 'bank_transfer', 'cheque'], true)): ?>
                                            <span class="badge bg-info text-dark"><i class="fas fa-university me-1"></i><?php echo htmlspecialchars($exp['bank_name'] ?: 'Bank'); ?></span>
                                        <?php elseif ($exp['paid_by'] === 'credit'): ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Credit</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><i class="fas fa-money-bill-wave me-1"></i>Cash</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="add_expense_head.php?edit_expense=<?php echo $exp['id']; ?>" class="btn btn-warning" title="Edit">
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
                            <label class="form-label">Expense Category *</label>
                            <select class="form-select" name="category" required>
                                <option value="">Select Expense Category</option>
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
                            <label class="form-label">Payment Method *</label>
                            <?php $currPaidBy = $editExpense['paid_by'] ?? 'cash'; ?>
                            <select class="form-select" name="paid_by" id="modal_paid_by" onchange="toggleModalBank(this.value)" required>
                                <option value="cash" <?php echo $currPaidBy === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                <option value="bank" <?php echo in_array($currPaidBy, ['bank', 'bank_transfer', 'cheque'], true) ? 'selected' : ''; ?>>Bank</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3" id="modal_bank_group" style="display: <?php echo in_array($currPaidBy, ['bank', 'bank_transfer', 'cheque'], true) ? 'block' : 'none'; ?>;">
                            <label class="form-label text-primary fw-bold"><i class="fas fa-university me-1"></i>Bank Account *</label>
                            <select class="form-select" name="bank_id" id="modal_bank_id">
                                <option value="">-- Select Bank Account --</option>
                                <?php foreach ($bankAccounts as $ba): ?>
                                    <option value="<?php echo $ba['id']; ?>" <?php echo ($editExpense && ($editExpense['bank_id'] ?? null) == $ba['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ba['bank_name']); ?> - <?php echo htmlspecialchars($ba['account_number']); ?>
                                    </option>
                                <?php endforeach; ?>
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
function toggleModalBank(method) {
    var bg = document.getElementById('modal_bank_group');
    if (bg) {
        bg.style.display = (method === 'bank') ? 'block' : 'none';
        var bSel = document.getElementById('modal_bank_id');
        if (bSel) bSel.required = (method === 'bank');
    }
}

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
