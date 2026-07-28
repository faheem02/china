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

$fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$toDate = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
$bankFilter = isset($_GET['bank_id']) ? (int)$_GET['bank_id'] : 0;

$banks = getRows("SELECT id, bank_name, account_number, opening_balance FROM banks WHERE status = 'active' ORDER BY bank_name");

$openingBalance = 0;
if ($bankFilter > 0) {
    $selectedBank = getRow("SELECT id, bank_name, account_number, opening_balance FROM banks WHERE id = ?", 'i', [$bankFilter]);
    if ($selectedBank) {
        $openingBalance = (float)$selectedBank['opening_balance'];
    }
} else {
    foreach ($banks as $b) {
        $openingBalance += (float)$b['opening_balance'];
    }
}

$transactions = [];
$totalBankIn = 0;
$totalBankOut = 0;

$baseSql = "SELECT payment_date as txn_date, CONCAT('Receipt - ', receipt_no) as description, receipt_no as reference, amount as bank_in, 0 as bank_out, 'in' as direction
    FROM customer_receipts WHERE payment_method IN ('bank_transfer', 'cheque')";

$baseSql .= " UNION ALL SELECT payment_date, CONCAT('Payment - ', payment_no), payment_no, 0, amount, 'out'
    FROM supplier_payments WHERE payment_type IN ('bank_transfer', 'cheque')";

$baseSql .= " UNION ALL SELECT expense_date, CONCAT('Expense - ', category, ' - ', expense_no), expense_no, 0, amount, 'out'
    FROM expenses WHERE status = 'paid' AND (paid_by LIKE '%bank%' OR paid_by LIKE '%cheque%')";

$baseSql .= " ORDER BY txn_date ASC";

$transactions = getRows($baseSql);

$closingBalance = $openingBalance;
$filteredTransactions = [];
foreach ($transactions as $txn) {
    if ($txn['txn_date'] < $fromDate || $txn['txn_date'] > $toDate) {
        continue;
    }
    $closingBalance += (float)$txn['bank_in'] - (float)$txn['bank_out'];
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

<div class="card no-print">
    <div class="card-header d-flex justify-content-between align-items-center no-print" style="background-color: #1a2332; color: #fff;">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>Filter Transactions
        </h5>
        <div class="d-flex gap-1 no-print">
            <a href="bankbook_print.php?from_date=<?php echo urlencode($fromDate); ?>&to_date=<?php echo urlencode($toDate); ?>&bank_id=<?php echo $bankFilter; ?>" target="_blank" class="btn btn-danger btn-sm"><i class="fas fa-print me-1"></i>Print</a>
            <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-control" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-control" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Bank</label>
                    <select class="form-select" name="bank_id">
                        <option value="0">All Banks</option>
                        <?php foreach ($banks as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo $bankFilter == $b['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['bank_name'] . ' (' . $b['account_number'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
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

<div class="card">
    <div class="card-header" style="background-color: #1a2332; color: #fff;">
        <h5 class="mb-0">
            <i class="fas fa-book me-2"></i>Bank Book
            <?php if ($bankFilter > 0 && isset($selectedBank)): ?>
                <span class="badge bg-info ms-2"><?php echo htmlspecialchars($selectedBank['bank_name']); ?></span>
            <?php else: ?>
                <span class="badge bg-info ms-2">All Banks</span>
            <?php endif; ?>
            <small class="ms-2">(<?php echo date('d-m-Y', strtotime($fromDate)); ?> to <?php echo date('d-m-Y', strtotime($toDate)); ?>)</small>
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
                        <th>Reference</th>
                        <th class="text-end">Bank In (PKR)</th>
                        <th class="text-end">Bank Out (PKR)</th>
                        <th class="text-end">Balance (PKR)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-secondary">
                        <td></td>
                        <td><?php echo date('d-m-Y'); ?></td>
                        <td><strong>Opening Balance</strong></td>
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
                            <td><?php echo date('d-m-Y', strtotime($txn['txn_date'])); ?></td>
                            <td><?php echo htmlspecialchars($txn['description']); ?></td>
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
});
</script>

<?php include '../includes/footer.php'; ?>
