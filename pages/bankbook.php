<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Bank Book';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

// Handle Add Bank Account
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_bank_account') {
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $opening_balance = (float)($_POST['opening_balance'] ?? 0);

    if (empty($bank_name)) {
        setFlash('Please enter Bank Name!', 'danger');
    } elseif (empty($account_number)) {
        setFlash('Please enter Account Number!', 'danger');
    } else {
        $now = getCurrentDateTime();
        $ins = insertData(
            "INSERT INTO bank_accounts (bank_name, account_number, opening_balance, current_balance, status, date_time) VALUES (?, ?, ?, ?, 'active', ?)",
            'ssdds',
            [$bank_name, $account_number, $opening_balance, $opening_balance, $now]
        );
        if ($ins) {
            setFlash("Bank account '{$bank_name} - {$account_number}' added successfully!", 'success');
        } else {
            setFlash('Error adding bank account!', 'danger');
        }
    }
    header('Location: bankbook.php');
    exit;
}

// Handle Edit Bank Account
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_bank_account') {
    $edit_bank_id = (int)($_POST['bank_id'] ?? 0);
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $opening_balance = isset($_POST['opening_balance']) ? (float)$_POST['opening_balance'] : null;
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

    if ($edit_bank_id > 0 && !empty($bank_name) && !empty($account_number)) {
        if ($opening_balance !== null) {
            modifyData(
                "UPDATE bank_accounts SET bank_name = ?, account_number = ?, opening_balance = ?, status = ? WHERE id = ?",
                'ssdsi',
                [$bank_name, $account_number, $opening_balance, $status, $edit_bank_id]
            );
        } else {
            modifyData(
                "UPDATE bank_accounts SET bank_name = ?, account_number = ?, status = ? WHERE id = ?",
                'sssi',
                [$bank_name, $account_number, $status, $edit_bank_id]
            );
        }
        setFlash("Bank account updated successfully!", 'success');
    } else {
        setFlash('Please fill in required bank details!', 'danger');
    }
    header('Location: bankbook.php');
    exit;
}

// Handle Delete Bank Account
if (isset($_GET['delete_bank']) && is_numeric($_GET['delete_bank'])) {
    $delBankId = (int)$_GET['delete_bank'];
    $crCnt = (int)(getRow("SELECT COUNT(*) as c FROM customer_receipts WHERE bank_id = ?", 'i', [$delBankId])['c'] ?? 0);
    $spCnt = (int)(getRow("SELECT COUNT(*) as c FROM supplier_payments WHERE bank_id = ?", 'i', [$delBankId])['c'] ?? 0);
    $cspCnt = (int)(getRow("SELECT COUNT(*) as c FROM chinese_supplier_payments WHERE bank_id = ?", 'i', [$delBankId])['c'] ?? 0);
    $expCnt = (int)(getRow("SELECT COUNT(*) as c FROM expenses WHERE bank_id = ?", 'i', [$delBankId])['c'] ?? 0);
    $slCnt = (int)(getRow("SELECT COUNT(*) as c FROM sales WHERE bank_id = ?", 'i', [$delBankId])['c'] ?? 0);
    $lpCnt = (int)(getRow("SELECT COUNT(*) as c FROM local_purchases WHERE bank_id = ?", 'i', [$delBankId])['c'] ?? 0);
    $ipCnt = (int)(getRow("SELECT COUNT(*) as c FROM import_purchases WHERE bank_id = ?", 'i', [$delBankId])['c'] ?? 0);
    $totalCnt = $crCnt + $spCnt + $cspCnt + $expCnt + $slCnt + $lpCnt + $ipCnt;
    if ($totalCnt > 0) {
        setFlash("Cannot delete bank account! $totalCnt transaction(s) are linked to it. You can mark it inactive instead.", 'danger');
    } else {
        modifyData("DELETE FROM bank_accounts WHERE id = ?", 'i', [$delBankId]);
        setFlash('Bank account deleted successfully!', 'success');
    }
    header('Location: bankbook.php');
    exit;
}

// Helper to compute exact dynamic current balance for any bank account
function getBankCalculatedBalance($bankId) {
    global $conn;
    $bankId = (int)$bankId;
    $bank = getRow("SELECT opening_balance FROM bank_accounts WHERE id = ?", 'i', [$bankId]);
    if (!$bank) return 0;
    
    $bal = (float)$bank['opening_balance'];
    
    // In
    $cr = (float)(getRow("SELECT COALESCE(SUM(amount), 0) as s FROM customer_receipts WHERE bank_id = ? AND payment_method IN ('bank', 'bank_transfer', 'cheque')", 'i', [$bankId])['s'] ?? 0);
    $sl = (float)(getRow("SELECT COALESCE(SUM(paid_amount), 0) as s FROM sales WHERE bank_id = ? AND payment_method = 'bank' AND status = 'completed'", 'i', [$bankId])['s'] ?? 0);
    $bal += ($cr + $sl);
    
    // Out
    $sp = (float)(getRow("SELECT COALESCE(SUM(amount), 0) as s FROM supplier_payments WHERE bank_id = ? AND payment_type IN ('bank', 'bank_transfer', 'cheque')", 'i', [$bankId])['s'] ?? 0);
    $lp = (float)(getRow("SELECT COALESCE(SUM(paid_amount), 0) as s FROM local_purchases WHERE bank_id = ? AND payment_method = 'bank'", 'i', [$bankId])['s'] ?? 0);
    $ip = (float)(getRow("SELECT COALESCE(SUM(paid_amount_pkr), 0) as s FROM import_purchases WHERE bank_id = ? AND payment_method = 'bank'", 'i', [$bankId])['s'] ?? 0);
    $exp = (float)(getRow("SELECT COALESCE(SUM(paid_amount), 0) as s FROM expenses WHERE bank_id = ? AND paid_by IN ('bank', 'bank_transfer', 'cheque') AND status IN ('active', 'paid')", 'i', [$bankId])['s'] ?? 0);
    $cp = (float)(getRow("SELECT COALESCE(SUM(amount_pkr), 0) as s FROM chinese_supplier_payments WHERE bank_id = ? AND payment_type IN ('bank', 'bank_transfer', 'cheque', 'online', 'telegraphic_transfer') AND status = 'completed'", 'i', [$bankId])['s'] ?? 0);
    $bal -= ($sp + $lp + $ip + $exp + $cp);
    
    $chkParty = $conn->query("SHOW TABLES LIKE 'party_transactions'");
    if ($chkParty && $chkParty->num_rows > 0) {
        $ptIn = (float)(getRow("SELECT COALESCE(SUM(amount), 0) as s FROM party_transactions WHERE bank_id = ? AND type = 'received' AND payment_method IN ('bank', 'bank_transfer', 'cheque')", 'i', [$bankId])['s'] ?? 0);
        $ptOut = (float)(getRow("SELECT COALESCE(SUM(amount), 0) as s FROM party_transactions WHERE bank_id = ? AND type = 'paid' AND payment_method IN ('bank', 'bank_transfer', 'cheque')", 'i', [$bankId])['s'] ?? 0);
        $bal += ($ptIn - $ptOut);
    }
    
    return $bal;
}

$allBankAccounts = getRows("SELECT * FROM bank_accounts ORDER BY status ASC, bank_name ASC, account_number ASC");

// If only 1 bank account exists, link any unassigned legacy bank transactions to that bank account
if (count($allBankAccounts) === 1) {
    $singleBankId = (int)$allBankAccounts[0]['id'];
    modifyData("UPDATE supplier_payments SET bank_id = ? WHERE (bank_id IS NULL OR bank_id = 0) AND payment_type IN ('bank', 'bank_transfer', 'cheque')", 'i', [$singleBankId]);
    modifyData("UPDATE customer_receipts SET bank_id = ? WHERE (bank_id IS NULL OR bank_id = 0) AND payment_method IN ('bank', 'bank_transfer', 'cheque')", 'i', [$singleBankId]);
    modifyData("UPDATE sales SET bank_id = ? WHERE (bank_id IS NULL OR bank_id = 0) AND payment_method = 'bank' AND status = 'completed'", 'i', [$singleBankId]);
    modifyData("UPDATE expenses SET bank_id = ? WHERE (bank_id IS NULL OR bank_id = 0) AND paid_by IN ('bank', 'bank_transfer', 'cheque') AND status IN ('active', 'paid')", 'i', [$singleBankId]);
    modifyData("UPDATE local_purchases SET bank_id = ? WHERE (bank_id IS NULL OR bank_id = 0) AND payment_method = 'bank'", 'i', [$singleBankId]);
    modifyData("UPDATE import_purchases SET bank_id = ? WHERE (bank_id IS NULL OR bank_id = 0) AND payment_method = 'bank'", 'i', [$singleBankId]);
}

// Synchronize all bank accounts' current_balance dynamically
foreach ($allBankAccounts as &$ba) {
    $realBal = getBankCalculatedBalance($ba['id']);
    if (abs((float)$ba['current_balance'] - $realBal) > 0.001) {
        modifyData("UPDATE bank_accounts SET current_balance = ? WHERE id = ?", 'di', [$realBal, $ba['id']]);
        $ba['current_balance'] = $realBal;
    }
}
unset($ba);

$fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$toDate = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
$bankIdFilter = isset($_GET['bank_id']) && is_numeric($_GET['bank_id']) ? (int)$_GET['bank_id'] : 0;

$selectedBankAccount = null;
if ($bankIdFilter > 0) {
    foreach ($allBankAccounts as $ba) {
        if ((int)$ba['id'] === $bankIdFilter) {
            $selectedBankAccount = $ba;
            break;
        }
    }
}

// Initial Opening Balance from bank_accounts table
if ($bankIdFilter > 0 && $selectedBankAccount) {
    $openingBalance = (float)$selectedBankAccount['opening_balance'];
} else {
    $openingBalance = (float)(getRow("SELECT COALESCE(SUM(opening_balance), 0) as s FROM bank_accounts WHERE status = 'active'")['s'] ?? 0);
}

// Print URL preserving active filters
$printUrl = 'bankbook_print.php?from_date=' . urlencode($fromDate) . '&to_date=' . urlencode($toDate) . ($bankIdFilter > 0 ? '&bank_id=' . $bankIdFilter : '');

$totalBankIn = 0;
$totalBankOut = 0;

// Base queries with bank tracking
$baseSql = "SELECT cr.payment_date as txn_date, CONCAT('Receipt - ', cr.receipt_no) as description, cr.receipt_no as reference, cr.amount as bank_in, 0 as bank_out, 'in' as direction, cr.bank_id, COALESCE(ba.bank_name, cr.bank_name, 'Bank') as bank_display
    FROM customer_receipts cr
    LEFT JOIN bank_accounts ba ON cr.bank_id = ba.id
    WHERE cr.payment_method IN ('bank', 'bank_transfer', 'cheque')";

$baseSql .= " UNION ALL SELECT s.sale_date, CONCAT('Sale - ', s.sale_no), s.sale_no, s.paid_amount, 0, 'in', s.bank_id, COALESCE(ba.bank_name, s.bank_name, 'Bank')
    FROM sales s
    LEFT JOIN bank_accounts ba ON s.bank_id = ba.id
    WHERE s.payment_method = 'bank' AND s.status = 'completed' AND s.paid_amount > 0";

$baseSql .= " UNION ALL SELECT sp.payment_date, CONCAT('Payment - ', sp.payment_no), sp.payment_no, 0, sp.amount, 'out', sp.bank_id, COALESCE(ba.bank_name, sp.bank_name, 'Bank')
    FROM supplier_payments sp
    LEFT JOIN bank_accounts ba ON sp.bank_id = ba.id
    WHERE sp.payment_type IN ('bank', 'bank_transfer', 'cheque')";

$baseSql .= " UNION ALL SELECT lp.purchase_date, CONCAT('Local purchase - ', lp.purchase_no), lp.purchase_no, 0, lp.paid_amount, 'out', lp.bank_id, COALESCE(ba.bank_name, lp.bank_name, 'Bank')
    FROM local_purchases lp
    LEFT JOIN bank_accounts ba ON lp.bank_id = ba.id
    WHERE lp.payment_method = 'bank' AND lp.paid_amount > 0";

$baseSql .= " UNION ALL SELECT ip.purchase_date, CONCAT('Import purchase - ', ip.purchase_no), ip.purchase_no, 0, ip.paid_amount_pkr, 'out', ip.bank_id, COALESCE(ba.bank_name, ip.bank_name, 'Bank')
    FROM import_purchases ip
    LEFT JOIN bank_accounts ba ON ip.bank_id = ba.id
    WHERE ip.payment_method = 'bank' AND ip.paid_amount_pkr > 0";

$baseSql .= " UNION ALL SELECT e.expense_date, CONCAT('Expense - ', e.category, ' - ', e.expense_no), e.expense_no, 0, e.paid_amount, 'out', e.bank_id, COALESCE(ba.bank_name, e.bank_name, 'Bank')
    FROM expenses e
    LEFT JOIN bank_accounts ba ON e.bank_id = ba.id
    WHERE e.paid_amount > 0 AND e.paid_by IN ('bank', 'bank_transfer', 'cheque') AND e.status IN ('active', 'paid')";

$baseSql .= " UNION ALL SELECT cp.payment_date, CONCAT('Chinese Payment - ', cp.payment_no), cp.payment_no, 0, cp.amount_pkr, 'out', cp.bank_id, COALESCE(ba.bank_name, cp.bank_name, 'Bank')
    FROM chinese_supplier_payments cp
    LEFT JOIN bank_accounts ba ON cp.bank_id = ba.id
    WHERE cp.payment_type IN ('bank', 'bank_transfer', 'cheque', 'online', 'telegraphic_transfer') AND cp.status = 'completed'";

// Add party_transactions if table exists
$chkParty = $conn->query("SHOW TABLES LIKE 'party_transactions'");
if ($chkParty && $chkParty->num_rows > 0) {
    $baseSql .= " UNION ALL SELECT pt.transaction_date, CONCAT('Party Receipt - ', pt.transaction_no, ' (', p.party_name, ')'), pt.transaction_no, pt.amount, 0, 'in', pt.bank_id, COALESCE(ba.bank_name, pt.bank_name, 'Bank')
        FROM party_transactions pt
        JOIN parties p ON pt.party_id = p.id
        LEFT JOIN bank_accounts ba ON pt.bank_id = ba.id
        WHERE pt.type = 'received' AND pt.payment_method IN ('bank', 'bank_transfer', 'cheque')";

    $baseSql .= " UNION ALL SELECT pt.transaction_date, CONCAT('Party Payment - ', pt.transaction_no, ' (', p.party_name, ')'), pt.transaction_no, 0, pt.amount, 'out', pt.bank_id, COALESCE(ba.bank_name, pt.bank_name, 'Bank')
        FROM party_transactions pt
        JOIN parties p ON pt.party_id = p.id
        LEFT JOIN bank_accounts ba ON pt.bank_id = ba.id
        WHERE pt.type = 'paid' AND pt.payment_method IN ('bank', 'bank_transfer', 'cheque')";
}

$baseSql .= " ORDER BY txn_date ASC";

$transactions = getRows($baseSql);

$filteredTransactions = [];
foreach ($transactions as $txn) {
    if ($bankIdFilter > 0 && (int)($txn['bank_id'] ?? 0) !== $bankIdFilter) {
        continue;
    }
    // Calculate opening balance adjustments prior to from_date
    if ($txn['txn_date'] < $fromDate) {
        $openingBalance += (float)$txn['bank_in'] - (float)$txn['bank_out'];
        continue;
    }
    if ($txn['txn_date'] > $toDate) {
        continue;
    }
    $totalBankIn += (float)$txn['bank_in'];
    $totalBankOut += (float)$txn['bank_out'];
    $filteredTransactions[] = $txn;
}

$closingBalance = $openingBalance + $totalBankIn - $totalBankOut;

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';
?>

<?php if ($message): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Bank Accounts Overview Section -->
<div class="card mb-3 no-print">
    <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #1a2332; color: #fff;">
        <h6 class="mb-0">
            <i class="fas fa-university me-2"></i>Registered Bank Accounts
            <span class="badge bg-light text-dark ms-2"><?php echo count($allBankAccounts); ?></span>
        </h6>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addBankModal">
                <i class="fas fa-plus-circle me-1"></i>Add Bank Account
            </button>
            <?php if (!empty($allBankAccounts)): ?>
                <button type="button" class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#manageBanksModal">
                    <i class="fas fa-cog me-1"></i>Manage Banks
                </button>
            <?php endif; ?>
            <a href="<?php echo $printUrl; ?>" target="_blank" class="btn btn-danger btn-sm">
                <i class="fas fa-print me-1"></i>Print
            </a>
            <a href="../index.php" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Back
            </a>
        </div>
    </div>
    <div class="card-body py-2">
        <?php if (empty($allBankAccounts)): ?>
            <div class="text-muted text-center py-2">
                <i class="fas fa-info-circle me-1"></i>No bank accounts added yet. Click <strong>Add Bank Account</strong> above to add your first bank.
            </div>
        <?php else: ?>
            <div class="row g-2">
                <?php foreach ($allBankAccounts as $ba): ?>
                    <div class="col-md-4 col-sm-6">
                        <div class="p-2 border rounded d-flex justify-content-between align-items-center <?php echo $bankIdFilter == $ba['id'] ? 'bg-primary text-white border-primary' : 'bg-light'; ?>">
                            <div>
                                <div class="fw-bold <?php echo $bankIdFilter == $ba['id'] ? 'text-white' : 'text-dark'; ?>">
                                    <i class="fas fa-building-columns me-1"></i><?php echo htmlspecialchars($ba['bank_name']); ?>
                                </div>
                                <div class="small <?php echo $bankIdFilter == $ba['id'] ? 'text-white-50' : 'text-muted'; ?>">
                                    A/C: <?php echo htmlspecialchars($ba['account_number']); ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold fs-6 <?php echo $bankIdFilter == $ba['id'] ? 'text-white' : ($ba['current_balance'] >= 0 ? 'text-success' : 'text-danger'); ?>">
                                    <?php echo formatCurrency($ba['current_balance']); ?>
                                </div>
                                <a href="bankbook.php?bank_id=<?php echo $ba['id']; ?>&from_date=<?php echo urlencode($fromDate); ?>&to_date=<?php echo urlencode($toDate); ?>" 
                                   class="badge <?php echo $bankIdFilter == $ba['id'] ? 'bg-light text-primary' : 'bg-secondary text-white text-decoration-none'; ?>" style="font-size: 11px;">
                                    <?php echo $bankIdFilter == $ba['id'] ? 'Selected' : 'Filter'; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Balance Summary Cards -->
<div class="row mb-3">
    <div class="col-md-3">
        <div class="card" style="border-left: 4px solid #6c757d;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Opening Balance</div>
                        <h4 class="mb-0"><?php echo formatCurrency($openingBalance); ?></h4>
                    </div>
                    <i class="fas fa-landmark fa-2x text-muted"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card" style="border-left: 4px solid #28a745;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total Bank In</div>
                        <h4 class="mb-0 text-success"><?php echo formatCurrency($totalBankIn); ?></h4>
                    </div>
                    <i class="fas fa-arrow-down fa-2x text-success"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card" style="border-left: 4px solid #dc3545;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total Bank Out</div>
                        <h4 class="mb-0 text-danger"><?php echo formatCurrency($totalBankOut); ?></h4>
                    </div>
                    <i class="fas fa-arrow-up fa-2x text-danger"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card" style="border-left: 4px solid #1a2332;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Closing Balance</div>
                        <h4 class="mb-0 <?php echo $closingBalance >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatCurrency($closingBalance); ?></h4>
                    </div>
                    <i class="fas fa-calculator fa-2x text-dark"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="card no-print mb-3 shadow-sm">
    <div class="card-body">
        <form method="GET" action="">
            <div class="row align-items-end g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">From Date</label>
                    <input type="date" class="form-control" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">To Date</label>
                    <input type="date" class="form-control" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Bank Account</label>
                    <select class="form-select" name="bank_id">
                        <option value="">-- All Bank Accounts --</option>
                        <?php foreach ($allBankAccounts as $ba): ?>
                            <option value="<?php echo $ba['id']; ?>" <?php echo $bankIdFilter == $ba['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ba['bank_name'] . ' - ' . $ba['account_number']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fas fa-search me-1"></i>Filter
                    </button>
                    <a href="bankbook.php" class="btn btn-outline-secondary">
                        <i class="fas fa-undo me-1"></i>Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Table -->
<div class="card">
    <div class="card-header" style="background-color: #1a2332; color: #fff;">
        <h5 class="mb-0">
            <i class="fas fa-book me-2"></i>Bank Book Ledger
            <small class="ms-2">(<?php echo formatDate($fromDate); ?> to <?php echo formatDate($toDate); ?>)</small>
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="bankBookTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Bank Account</th>
                        <th>Reference</th>
                        <th class="text-end">Bank In (PKR)</th>
                        <th class="text-end">Bank Out (PKR)</th>
                        <th class="text-end">Balance (PKR)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-secondary">
                        <td></td>
                        <td><?php echo formatDate($fromDate); ?></td>
                        <td><strong>Opening Balance</strong></td>
                        <td>-</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td class="text-end <?php echo $openingBalance >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <strong><?php echo formatCurrency($openingBalance); ?></strong>
                        </td>
                    </tr>
                    <?php $runningBalance = $openingBalance; ?>
                    <?php $counter = 1; ?>
                    <?php foreach ($filteredTransactions as $txn): ?>
                        <?php
                        $runningBalance += (float)$txn['bank_in'] - (float)$txn['bank_out'];
                        ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td><?php echo formatDate($txn['txn_date']); ?></td>
                            <td><?php echo htmlspecialchars($txn['description']); ?></td>
                            <td>
                                <span class="badge bg-info text-dark">
                                    <i class="fas fa-university me-1"></i><?php echo htmlspecialchars($txn['bank_display'] ?? 'Bank'); ?>
                                </span>
                            </td>
                            <td><strong><?php echo htmlspecialchars($txn['reference']); ?></strong></td>
                            <td class="text-end">
                                <?php if ((float)$txn['bank_in'] > 0): ?>
                                    <span class="text-success fw-bold"><?php echo formatCurrency($txn['bank_in']); ?></span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ((float)$txn['bank_out'] > 0): ?>
                                    <span class="text-danger fw-bold"><?php echo formatCurrency($txn['bank_out']); ?></span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="text-end <?php echo $runningBalance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <strong><?php echo formatCurrency($runningBalance); ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="table-secondary fw-bold">
                        <td></td>
                        <td></td>
                        <td><strong>Closing Balance</strong></td>
                        <td></td>
                        <td></td>
                        <td class="text-end"><strong><?php echo formatCurrency($totalBankIn); ?></strong></td>
                        <td class="text-end"><strong><?php echo formatCurrency($totalBankOut); ?></strong></td>
                        <td class="text-end <?php echo $closingBalance >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <strong><?php echo formatCurrency($closingBalance); ?></strong>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Bank Account -->
<div class="modal fade" id="addBankModal" tabindex="-1" aria-labelledby="addBankModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #1a2332; color: #fff;">
                <h5 class="modal-title" id="addBankModalLabel">
                    <i class="fas fa-plus-circle me-2"></i>Add New Bank Account
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_bank_account">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Bank Name *</label>
                        <input type="text" class="form-control" name="bank_name" placeholder="e.g. Meezan Bank, HBL, MCB" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Account Number *</label>
                        <input type="text" class="form-control" name="account_number" placeholder="e.g. 01020304050601" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Opening Balance (PKR) *</label>
                        <input type="number" step="0.01" class="form-control" name="opening_balance" value="0.00" required>
                        <small class="text-muted">Enter current balance or initial opening balance in PKR</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fas fa-check-circle me-1"></i>Save Bank Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Manage Bank Accounts -->
<?php if (!empty($allBankAccounts)): ?>
<div class="modal fade" id="manageBanksModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #1a2332; color: #fff;">
                <h5 class="modal-title">
                    <i class="fas fa-cog me-2"></i>Manage Bank Accounts
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Bank Name</th>
                                <th>Account Number</th>
                                <th class="text-end">Opening Bal</th>
                                <th class="text-end">Current Bal</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $mIdx = 1; foreach ($allBankAccounts as $ba): ?>
                                <tr>
                                    <td><?php echo $mIdx++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($ba['bank_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($ba['account_number']); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($ba['opening_balance']); ?></td>
                                    <td class="text-end fw-bold <?php echo $ba['current_balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo formatCurrency($ba['current_balance']); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $ba['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($ba['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" 
                                                    class="btn btn-outline-primary btn-edit-bank"
                                                    data-id="<?php echo $ba['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($ba['bank_name']); ?>"
                                                    data-account="<?php echo htmlspecialchars($ba['account_number']); ?>"
                                                    data-opening="<?php echo $ba['opening_balance']; ?>"
                                                    data-status="<?php echo $ba['status']; ?>"
                                                    title="Edit Account">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="bankbook.php?delete_bank=<?php echo $ba['id']; ?>" 
                                               class="btn btn-outline-danger"
                                               onclick="return confirm('Are you sure you want to delete this bank account?');"
                                               title="Delete Account">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Edit Bank Account -->
<div class="modal fade" id="editBankModal" tabindex="-1" aria-labelledby="editBankModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #1a2332; color: #fff;">
                <h5 class="modal-title" id="editBankModalLabel">
                    <i class="fas fa-edit me-2"></i>Edit Bank Account
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_bank_account">
                <input type="hidden" name="bank_id" id="edit_bank_id" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Bank Name *</label>
                        <input type="text" class="form-control" name="bank_name" id="edit_bank_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Account Number *</label>
                        <input type="text" class="form-control" name="account_number" id="edit_account_number" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Opening Balance (PKR) *</label>
                        <input type="number" step="0.01" class="form-control" name="opening_balance" id="edit_opening_balance" required>
                        <small class="text-muted">Initial balance when account was registered</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Status *</label>
                        <select class="form-select" name="status" id="edit_status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save me-1"></i>Update Bank Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#bankBookTable').DataTable({
        "pageLength": 50,
        "order": [],
        "paging": false,
        "searching": false,
        "info": false,
        "language": {
            "emptyTable": "No bank transactions found for the selected period"
        }
    });

    $(document).on('click', '.btn-edit-bank', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var account = $(this).data('account');
        var opening = $(this).data('opening');
        var status = $(this).data('status');

        $('#edit_bank_id').val(id);
        $('#edit_bank_name').val(name);
        $('#edit_account_number').val(account);
        $('#edit_opening_balance').val(opening);
        $('#edit_status').val(status);

        var manageModalEl = document.getElementById('manageBanksModal');
        if (manageModalEl) {
            var manageModal = bootstrap.Modal.getInstance(manageModalEl);
            if (manageModal) {
                manageModal.hide();
            }
        }
        var editModal = new bootstrap.Modal(document.getElementById('editBankModal'));
        editModal.show();
    });
});
</script>

<?php include '../includes/footer.php'; ?>
