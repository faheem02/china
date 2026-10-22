<?php
/**
 * Professional Expense Management - Record Expense
 * Manufacturing ERP System
 */

require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Record Expense';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

// Auto-seed standard categories if empty
$existingCount = getRow("SELECT COUNT(*) as cnt FROM expense_categories");
if (!$existingCount || (int)$existingCount['cnt'] <= 1) {
    $standardCategories = [
        'Electricity Bill',
        'Factory / Office Rent',
        'Tea & Refreshments',
        'Fuel & Petrol',
        'Machine Repair & Maintenance',
        'Transportation & Cartage',
        'Stationery & Supplies',
        'Staff Food & Welfare',
        'Miscellaneous'
    ];
    foreach ($standardCategories as $cat) {
        $check = getRow("SELECT id FROM expense_categories WHERE category_name = ?", 's', [$cat]);
        if (!$check) {
            insertData("INSERT INTO expense_categories (category_name, description, status, date_time) VALUES (?, 'Standard expense category', 'active', NOW())", 's', [$cat]);
        }
    }
}

// Handle AJAX quick add expense category
if (isset($_POST['action']) && $_POST['action'] === 'quick_add_head') {
    header('Content-Type: application/json');
    $headName = trim($_POST['head_name'] ?? '');
    $headDesc = trim($_POST['head_desc'] ?? '');
    if (empty($headName)) {
        echo json_encode(['success' => false, 'message' => 'Expense head name is required!']);
        exit;
    }
    $exists = getRow("SELECT id FROM expense_categories WHERE category_name = ?", 's', [$headName]);
    if ($exists) {
        echo json_encode(['success' => true, 'id' => $exists['id'], 'name' => $headName, 'message' => 'Head already exists. Selected!']);
        exit;
    }
    $newId = insertData("INSERT INTO expense_categories (category_name, description, status, date_time) VALUES (?, ?, 'active', ?)", 'sss', [$headName, $headDesc, getCurrentDateTime()]);
    if ($newId !== false) {
        echo json_encode(['success' => true, 'id' => $newId, 'name' => $headName, 'message' => 'Expense head added successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding expense category!']);
    }
    exit;
}

// Handle Head-Only Edit (from expense_heads.php)
$isEditingHead = false;
$editHead = null;
if (isset($_GET['edit_head']) && is_numeric($_GET['edit_head'])) {
    $editHead = getRow("SELECT * FROM expense_categories WHERE id = ?", 'i', [(int)$_GET['edit_head']]);
    if ($editHead) {
        $isEditingHead = true;
        $pageTitle = 'Edit Expense Category';
    }
} elseif (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    // Check if ID belongs to expense_categories or expenses
    $checkHead = getRow("SELECT * FROM expense_categories WHERE id = ?", 'i', [(int)$_GET['edit']]);
    $checkExpense = getRow("SELECT * FROM expenses WHERE id = ?", 'i', [(int)$_GET['edit']]);
    if ($checkHead && !$checkExpense) {
        $isEditingHead = true;
        $editHead = $checkHead;
        $pageTitle = 'Edit Expense Category';
    }
} elseif (isset($_GET['new_head'])) {
    $isEditingHead = true;
    $editHead = null;
    $pageTitle = 'Add Expense Category';
}

// Handle Head-Only POST update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_head_only'])) {
    $headId = (int)($_POST['head_id'] ?? 0);
    $category_name = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if (empty($category_name)) {
        $message = 'Please enter expense category name!';
        $messageType = 'danger';
    } else {
        $dup = getRow("SELECT id FROM expense_categories WHERE category_name = ? AND id != ?", 'si', [$category_name, $headId]);
        if ($dup) {
            $message = 'Expense head with this name already exists!';
            $messageType = 'danger';
        } else {
            $now = getCurrentDateTime();
            if ($headId > 0) {
                modifyData("UPDATE expense_categories SET category_name=?, description=?, status=? WHERE id=?", 'sssi', [$category_name, $description, $status, $headId]);
                setFlash('Expense head updated successfully!', 'success');
            } else {
                insertData("INSERT INTO expense_categories (category_name, description, status, date_time) VALUES (?,?,?,?)", 'ssss', [$category_name, $description, $status, $now]);
                setFlash('Expense head created successfully!', 'success');
            }
            header('Location: expense_heads.php');
            exit;
        }
    }
}

// Check if editing an actual expense
$editExpense = null;
if (isset($_GET['edit_expense']) && is_numeric($_GET['edit_expense'])) {
    $editExpense = getRow("SELECT * FROM expenses WHERE id = ?", 'i', [(int)$_GET['edit_expense']]);
    if ($editExpense) {
        $pageTitle = 'Edit Expense: ' . $editExpense['expense_no'];
    }
} elseif (isset($_GET['edit']) && is_numeric($_GET['edit']) && !$isEditingHead) {
    $editExpense = getRow("SELECT * FROM expenses WHERE id = ?", 'i', [(int)$_GET['edit']]);
    if ($editExpense) {
        $pageTitle = 'Edit Expense: ' . $editExpense['expense_no'];
    }
}

// Handle Record Expense POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_expense']) || !isset($_POST['save_head_only']))) {
    $expense_id = isset($_POST['expense_id']) ? (int)$_POST['expense_id'] : 0;
    $expense_date = trim($_POST['expense_date'] ?? date('Y-m-d'));
    $category = trim($_POST['category'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    if (!in_array($payment_method, ['cash', 'bank', 'credit'], true)) {
        $payment_method = 'cash';
    }
    $bank_id = ($payment_method === 'bank') ? (int)($_POST['bank_id'] ?? 0) : null;
    $bank_name = null;
    if ($bank_id > 0) {
        $bRow = getRow("SELECT bank_name, account_number FROM bank_accounts WHERE id = ?", 'i', [$bank_id]);
        if ($bRow) {
            $bank_name = $bRow['bank_name'] . ' (' . $bRow['account_number'] . ')';
        }
    }
    $paid_amount = $payment_method === 'credit' ? 0 : $amount;
    $paid_by = $payment_method;
    $reference_no = '';
    $description = trim($_POST['description'] ?? '');

    $qty = 1.00;
    $unit = 'Qty';
    $rate = $amount;

    if (empty($expense_date)) {
        $message = 'Please select an expense date!';
        $messageType = 'danger';
    } elseif (empty($category)) {
        $message = 'Please select an Expense Category!';
        $messageType = 'danger';
    } elseif ($amount <= 0) {
        $message = 'Price must be greater than 0!';
        $messageType = 'danger';
    } elseif ($payment_method === 'bank' && empty($bank_id)) {
        $message = 'Please select a bank account!';
        $messageType = 'danger';
    } else {
        $balance = max(0, $amount - $paid_amount);
        $payment_status = ($paid_amount >= $amount) ? 'paid' : (($paid_amount > 0) ? 'partial' : 'unpaid');
        $status = ($payment_status === 'paid') ? 'paid' : 'pending';
        $currentDateTime = getCurrentDateTime();

        // Ensure category exists in expense_categories
        $catCheck = getRow("SELECT id FROM expense_categories WHERE category_name = ?", 's', [$category]);
        if (!$catCheck) {
            insertData("INSERT INTO expense_categories (category_name, description, status, date_time) VALUES (?, 'Auto-created from expense', 'active', ?)", 'ss', [$category, $currentDateTime]);
        }

        if ($expense_id > 0) {
            // Restore old bank balance if it was paid via bank
            $oldExp = getRow("SELECT paid_by, bank_id, paid_amount FROM expenses WHERE id = ?", 'i', [$expense_id]);
            if ($oldExp && in_array($oldExp['paid_by'], ['bank', 'bank_transfer', 'cheque'], true) && !empty($oldExp['bank_id']) && $oldExp['paid_amount'] > 0) {
                updateBankBalance($oldExp['bank_id'], $oldExp['paid_amount'], 'in');
            }

            // Update existing expense
            $sql = "UPDATE expenses SET expense_date=?, category=?, description=?, qty=?, unit=?, rate=?, amount=?, paid_amount=?, balance=?, payment_status=?, paid_by=?, bank_id=?, bank_name=?, reference_no=?, status=?, date_time=? WHERE id=?";
            $params = [$expense_date, $category, $description, $qty, $unit, $rate, $amount, $paid_amount, $balance, $payment_status, $paid_by, $bank_id, $bank_name, $reference_no, $status, $currentDateTime, $expense_id];
            $result = modifyData($sql, 'ssssdsdddsisssssi', $params);

            // Deduct from new bank balance
            if ($paid_by === 'bank' && $bank_id > 0 && $paid_amount > 0) {
                updateBankBalance($bank_id, $paid_amount, 'out');
            }

            // Re-sync accounts ledger entry
            modifyData("DELETE FROM accounts WHERE reference_type = 'Expense' AND reference_id = ?", 'i', [$expense_id]);
            if ($paid_amount > 0) {
                $accountType = ($paid_by === 'cash') ? 'Cash' : 'Bank';
                $lastAcc = getRow("SELECT balance FROM accounts WHERE account_type = ? ORDER BY id DESC LIMIT 1", 's', [$accountType]);
                $curBal = $lastAcc ? (float)$lastAcc['balance'] : 0;
                $newBal = $curBal - $paid_amount;
                $accDesc = "Expense - " . $category . ($bank_name ? " [$bank_name]" : "") . ($description ? ": " . $description : "");
                insertData(
                    "INSERT INTO accounts (account_date, account_type, reference_type, reference_id, description, debit, credit, balance, date_time) VALUES (?, ?, 'Expense', ?, ?, 0, ?, ?, ?)",
                    'ssisdds',
                    [$expense_date, $accountType, $expense_id, $accDesc, $paid_amount, $newBal, $currentDateTime]
                );
            }

            setFlash('Expense updated successfully!', 'success');
            header('Location: expenses.php');
            exit;
        } else {
            // Insert new expense
            $expense_no = generateCode('EXP');
            $sql = "INSERT INTO expenses (expense_no, expense_date, category, description, qty, unit, rate, amount, paid_amount, balance, payment_status, paid_by, bank_id, bank_name, reference_no, status, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [$expense_no, $expense_date, $category, $description, $qty, $unit, $rate, $amount, $paid_amount, $balance, $payment_status, $paid_by, $bank_id, $bank_name, $reference_no, $status, $currentDateTime];
            $result = insertData($sql, 'ssssdsdddsisssss', $params);

            if ($result !== false) {
                // Deduct from bank balance
                if ($paid_by === 'bank' && $bank_id > 0 && $paid_amount > 0) {
                    updateBankBalance($bank_id, $paid_amount, 'out');
                }

                // Insert into accounts (General Ledger) as credit (cash/bank deduction)
                if ($paid_amount > 0) {
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

                setFlash("Expense $expense_no of PKR " . number_format($amount, 2) . " recorded successfully!", 'success');
                header('Location: expenses.php');
                exit;
            } else {
                $message = 'Error recording expense!';
                $messageType = 'danger';
            }
        }
    }
}

// Fetch categories for dropdown
$categories = getRows("SELECT * FROM expense_categories WHERE status = 'active' ORDER BY category_name ASC");
$bankAccounts = getActiveBankAccounts();

// Quick Stats
$todayDate = date('Y-m-d');
$monthStart = date('Y-m-01');
$todayStats = getRow("SELECT COALESCE(SUM(amount), 0) as total, COALESCE(SUM(paid_amount), 0) as paid FROM expenses WHERE expense_date = ?", 's', [$todayDate]);
$monthStats = getRow("SELECT COALESCE(SUM(amount), 0) as total, COALESCE(SUM(paid_amount), 0) as paid FROM expenses WHERE expense_date >= ?", 's', [$monthStart]);
$recentExpenses = getRows("SELECT * FROM expenses ORDER BY id DESC LIMIT 5");

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="row mb-3">
    <div class="col-md-4">
        <div class="stat-card" style="border-left-color:#dc3545;">
            <div class="stat-number text-danger"><?php echo formatCurrency($todayStats['total']); ?></div>
            <div class="stat-label">Today's Expenses</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="border-left-color:#0d6efd;">
            <div class="stat-number text-primary"><?php echo formatCurrency($monthStats['total']); ?></div>
            <div class="stat-label">This Month's Expenses</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="border-left-color:#198754;">
            <div class="stat-number text-success"><?php echo count($recentExpenses); ?></div>
            <div class="stat-label">Recent Entries</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($isEditingHead): ?>
<!-- EDIT EXPENSE HEAD ONLY FORM (When accessed via edit_head) -->
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card shadow-sm border-0">
            <div class="card-header text-white" style="background:#1a2332;">
                <h5 class="mb-0"><i class="fas fa-tag me-2"></i><?php echo $editHead ? 'Edit Expense Category' : 'Add New Expense Category'; ?></h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <input type="hidden" name="save_head_only" value="1">
                    <input type="hidden" name="head_id" value="<?php echo $editHead ? (int)$editHead['id'] : 0; ?>">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Expense Category Name *</label>
                        <input type="text" class="form-control" name="category_name" value="<?php echo $editHead ? htmlspecialchars($editHead['category_name']) : ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"><?php echo $editHead ? htmlspecialchars($editHead['description']) : ''; ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active" <?php echo (!$editHead || ($editHead['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($editHead && ($editHead['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i><?php echo $editHead ? 'Update Expense Category' : 'Save Expense Category'; ?></button>
                        <a href="expense_heads.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php else: ?>

<!-- PROFESSIONAL RECORD EXPENSE FORM -->
<div class="row">
    <!-- Main Expense Form -->
    <div class="col-12 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header text-white d-flex justify-content-between align-items-center" style="background:#1a2332;">
                <div>
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-receipt me-2 text-warning"></i>
                        <?php echo $editExpense ? 'Edit Expense Record (' . htmlspecialchars($editExpense['expense_no']) . ')' : 'Record New Expense'; ?>
                    </h5>
                    <small class="text-white-50">Enter expense details</small>
                </div>
                <?php if ($editExpense): ?>
                    <a href="add_expense_head.php" class="btn btn-light btn-sm"><i class="fas fa-plus me-1"></i>New Expense</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="" id="expenseForm">
                    <input type="hidden" name="save_expense" value="1">
                    <input type="hidden" name="expense_id" value="<?php echo $editExpense ? $editExpense['id'] : 0; ?>">

                    <!-- Section 1: Expense Basic Info -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Expense Date *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-calendar-alt text-primary"></i></span>
                                <input type="date" class="form-control" name="expense_date" id="expense_date" 
                                       value="<?php echo $editExpense ? $editExpense['expense_date'] : date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Expense Category *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-tags text-success"></i></span>
                                <select class="form-select" name="category" id="category_select" required>
                                    <option value="">Select Expense Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['category_name']); ?>" 
                                            <?php echo ($editExpense && $editExpense['category'] === $cat['category_name']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#newHeadModal" title="Add New Category">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Price *</label>
                                <input type="number" step="0.01" min="0.01" class="form-control" name="amount" id="amount_input" placeholder="0.00" value="<?php echo $editExpense ? $editExpense['amount'] : ''; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Payment Method *</label>
                        <?php $currentPaymentMethod = $editExpense['paid_by'] ?? 'cash'; ?>
                        <select class="form-select" name="payment_method" id="expense_payment_method" required onchange="toggleExpenseBank(this.value)">
                            <option value="cash" <?php echo $currentPaymentMethod === 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="bank" <?php echo in_array($currentPaymentMethod, ['bank', 'bank_transfer', 'cheque'], true) ? 'selected' : ''; ?>>Bank</option>
                            <option value="credit" <?php echo $currentPaymentMethod === 'credit' ? 'selected' : ''; ?>>Credit</option>
                        </select>
                    </div>

                    <div class="mb-3" id="expense_bank_group" style="display: <?php echo in_array($currentPaymentMethod, ['bank', 'bank_transfer', 'cheque'], true) ? 'block' : 'none'; ?>;">
                        <label class="form-label fw-bold text-primary"><i class="fas fa-university me-1"></i>Bank Account *</label>
                        <select class="form-select" name="bank_id" id="expense_bank_id">
                            <option value="">-- Select Bank Account --</option>
                            <?php foreach ($bankAccounts as $ba): ?>
                                <option value="<?php echo $ba['id']; ?>" <?php echo ($editExpense && ($editExpense['bank_id'] ?? null) == $ba['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ba['bank_name']); ?> - <?php echo htmlspecialchars($ba['account_number']); ?> (Bal: Rs. <?php echo number_format($ba['current_balance'], 2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Section 4: Description / Reason -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Description</label>
                        <textarea class="form-control" name="description" rows="3" 
                                  placeholder="Enter expense details..."><?php echo $editExpense ? htmlspecialchars($editExpense['description']) : ''; ?></textarea>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="d-flex gap-2 align-items-center pt-2 border-top">
                        <button type="submit" name="save_expense" class="btn btn-primary btn-lg px-4 shadow-sm">
                            <?php echo $editExpense ? 'Save' : 'Save'; ?>
                        </button>
                        <a href="expenses.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Financial Overview & Recent Expenses -->
    <div class="col-lg-4 d-none">
        <!-- Today & Month Overview Cards -->
        <div class="card shadow-sm border-0 mb-3" style="border-left: 4px solid #dc3545 !important;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Today's Expenses</div>
                        <div class="fs-4 fw-bold text-danger"><?php echo formatCurrency($todayStats['total']); ?></div>
                        <div class="small text-muted">Paid: <?php echo formatCurrency($todayStats['paid']); ?></div>
                    </div>
                    <div class="text-danger opacity-50 fs-1"><i class="fas fa-calendar-day"></i></div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3" style="border-left: 4px solid #0d6efd !important;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">This Month's Expenses</div>
                        <div class="fs-4 fw-bold text-primary"><?php echo formatCurrency($monthStats['total']); ?></div>
                        <div class="small text-muted">Paid: <?php echo formatCurrency($monthStats['paid']); ?></div>
                    </div>
                    <div class="text-primary opacity-50 fs-1"><i class="fas fa-chart-pie"></i></div>
                </div>
            </div>
        </div>

        <!-- Recent Expenses Widget -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="fas fa-history me-1 text-primary"></i>Recent Expenses</span>
                <a href="expenses.php" class="small text-decoration-none">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (count($recentExpenses) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentExpenses as $re): ?>
                            <div class="list-group-item px-3 py-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong class="d-block text-dark"><?php echo htmlspecialchars($re['category']); ?></strong>
                                        <small class="text-muted"><?php echo formatDate($re['expense_date']); ?> | <?php echo htmlspecialchars($re['expense_no']); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="fw-bold text-danger"><?php echo formatCurrency($re['amount']); ?></span>
                                        <div>
                                            <span class="badge bg-<?php echo $re['paid_by'] === 'cash' ? 'success' : 'info'; ?>" style="font-size:10px;">
                                                <?php echo ucfirst($re['paid_by'] ?: 'cash'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-3 text-center text-muted small">No expenses recorded yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- Quick Add Expense Category Modal -->
<div class="modal fade" id="newHeadModal" tabindex="-1" aria-labelledby="newHeadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content shadow">
            <div class="modal-header text-white" style="background:#1a2332;">
                <h5 class="modal-title" id="newHeadModalLabel"><i class="fas fa-tags me-2"></i>Add New Expense Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="quickHeadAlert" class="alert d-none" role="alert"></div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Expense Category Name *</label>
                    <input type="text" class="form-control" id="quickHeadName" placeholder="e.g. Factory Generator Fuel, Labour Welfare" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description (Optional)</label>
                    <textarea class="form-control" id="quickHeadDesc" rows="2" placeholder="Brief note about this category..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btnSaveQuickHead">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
// Auto calculation logic
document.addEventListener('DOMContentLoaded', function() {
    var amountInput = document.getElementById('amount_input');
    var paidAmountInput = document.getElementById('paid_amount_input');
    var balanceText = document.getElementById('balanceText');
    var badge = document.getElementById('paymentStatusBadge');
    var qtyInput = document.getElementById('qty_input');
    var rateInput = document.getElementById('rate_input');

    function updateCalculations(userChangedPaid) {
        var amt = parseFloat(amountInput.value) || 0;
        
        if (!userChangedPaid && (!paidAmountInput.value || parseFloat(paidAmountInput.value) === amt || paidAmountInput.dataset.autoFilled === 'true')) {
            paidAmountInput.value = amt.toFixed(2);
            paidAmountInput.dataset.autoFilled = 'true';
        }

        var paid = parseFloat(paidAmountInput.value) || 0;
        var bal = Math.max(0, amt - paid);
        balanceText.textContent = 'PKR ' + bal.toLocaleString('en-PK', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        if (bal > 0) {
            balanceText.className = 'fs-6 text-danger fw-bold';
        } else {
            balanceText.className = 'fs-6 text-success fw-bold';
        }

        if (amt > 0 && paid >= amt) {
            badge.className = 'badge bg-success fs-6';
            badge.textContent = 'Paid';
        } else if (paid > 0 && paid < amt) {
            badge.className = 'badge bg-warning text-dark fs-6';
            badge.textContent = 'Partial';
        } else {
            badge.className = 'badge bg-danger fs-6';
            badge.textContent = 'Unpaid';
        }
    }

    if (amountInput) {
        amountInput.addEventListener('input', function() {
            updateCalculations(false);
        });
    }

    if (paidAmountInput) {
        paidAmountInput.addEventListener('input', function() {
            paidAmountInput.dataset.autoFilled = 'false';
            updateCalculations(true);
        });
    }

    // Qty x Rate breakdown calculator
    function calcBreakdown() {
        var q = parseFloat(qtyInput.value) || 0;
        var r = parseFloat(rateInput.value) || 0;
        if (q > 0 && r > 0) {
            var total = q * r;
            amountInput.value = total.toFixed(2);
            updateCalculations(false);
        }
    }

    if (qtyInput && rateInput) {
        qtyInput.addEventListener('input', calcBreakdown);
        rateInput.addEventListener('input', calcBreakdown);
    }

    // Quick helper buttons
    var btnFull = document.getElementById('btnFullPaid');
    var btnHalf = document.getElementById('btnHalfPaid');
    var btnUnpaid = document.getElementById('btnUnpaid');

    if (btnFull) {
        btnFull.addEventListener('click', function() {
            var amt = parseFloat(amountInput.value) || 0;
            paidAmountInput.value = amt.toFixed(2);
            paidAmountInput.dataset.autoFilled = 'false';
            updateCalculations(true);
        });
    }

    if (btnHalf) {
        btnHalf.addEventListener('click', function() {
            var amt = parseFloat(amountInput.value) || 0;
            paidAmountInput.value = (amt / 2).toFixed(2);
            paidAmountInput.dataset.autoFilled = 'false';
            updateCalculations(true);
        });
    }

    if (btnUnpaid) {
        btnUnpaid.addEventListener('click', function() {
            paidAmountInput.value = '0.00';
            paidAmountInput.dataset.autoFilled = 'false';
            updateCalculations(true);
        });
    }

    // Initial calculation on load
    if (amountInput && amountInput.value) {
        updateCalculations(true);
    }

    // AJAX Quick Add Head
    var btnSaveHead = document.getElementById('btnSaveQuickHead');
    if (btnSaveHead) {
        btnSaveHead.addEventListener('click', function() {
            var name = document.getElementById('quickHeadName').value.trim();
            var desc = document.getElementById('quickHeadDesc').value.trim();
            var alertBox = document.getElementById('quickHeadAlert');

            if (!name) {
                alertBox.className = 'alert alert-danger';
                alertBox.textContent = 'Please enter a head name!';
                alertBox.classList.remove('d-none');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'quick_add_head');
            formData.append('head_name', name);
            formData.append('head_desc', desc);

            fetch('add_expense_head.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    var select = document.getElementById('category_select');
                    // Check if already in select
                    var exists = false;
                    for (var i = 0; i < select.options.length; i++) {
                        if (select.options[i].value.toLowerCase() === data.name.toLowerCase()) {
                            select.selectedIndex = i;
                            exists = true;
                            break;
                        }
                    }
                    if (!exists) {
                        var opt = document.createElement('option');
                        opt.value = data.name;
                        opt.textContent = data.name;
                        opt.selected = true;
                        select.appendChild(opt);
                    }
                    var modal = bootstrap.Modal.getInstance(document.getElementById('newHeadModal'));
                    if (modal) modal.hide();
                    document.getElementById('quickHeadName').value = '';
                    document.getElementById('quickHeadDesc').value = '';
                    alertBox.classList.add('d-none');
                } else {
                    alertBox.className = 'alert alert-danger';
                    alertBox.textContent = data.message;
                    alertBox.classList.remove('d-none');
                }
            })
            .catch(function(err) {
                alertBox.className = 'alert alert-danger';
                alertBox.textContent = 'Error saving category!';
                alertBox.classList.remove('d-none');
            });
        });
    }
});

function toggleExpenseBank(method) {
    var bg = document.getElementById('expense_bank_group');
    if (bg) {
        bg.style.display = (method === 'bank') ? 'block' : 'none';
        var bSel = document.getElementById('expense_bank_id');
        if (bSel) {
            bSel.required = (method === 'bank');
        }
    }
}
</script>

<?php include '../includes/footer.php'; ?>
