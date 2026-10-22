<?php
/**
 * Cash Book
 * Manufacturing ERP System
 */

require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Cash Book';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

$allTransactions = [];

if (!empty($from_date) && !empty($to_date)) {

    $cashInSql = "SELECT cr.payment_date as txn_date, CONCAT('Receipt from customer - ', cr.receipt_no) as description, cr.receipt_no as reference, cr.amount as cash_in, 0 as cash_out
        FROM customer_receipts cr
        WHERE cr.payment_method = 'cash' AND cr.payment_date >= ? AND cr.payment_date <= ?";
    $cashInRows = getRows($cashInSql, 'ss', [$from_date, $to_date]);

    $cashInSalesSql = "SELECT sale_date as txn_date, CONCAT('Sale - ', sale_no) as description, sale_no as reference, paid_amount as cash_in, 0 as cash_out
        FROM sales WHERE payment_method = 'cash' AND status = 'completed' AND paid_amount > 0 AND sale_date >= ? AND sale_date <= ?";
    $cashInSalesRows = getRows($cashInSalesSql, 'ss', [$from_date, $to_date]);

    $cashOutSupplierSql = "SELECT sp.payment_date as txn_date, CONCAT('Payment to supplier - ', sp.payment_no) as description, sp.payment_no as reference, 0 as cash_in, sp.amount as cash_out
        FROM supplier_payments sp
        WHERE sp.payment_type = 'cash' AND sp.payment_date >= ? AND sp.payment_date <= ?";
    $cashOutSupplierRows = getRows($cashOutSupplierSql, 'ss', [$from_date, $to_date]);

    $cashOutLocalPurchaseSql = "SELECT purchase_date as txn_date, CONCAT('Local purchase - ', purchase_no) as description, purchase_no as reference, 0 as cash_in, paid_amount as cash_out
        FROM local_purchases WHERE payment_method = 'cash' AND paid_amount > 0 AND purchase_date >= ? AND purchase_date <= ?";
    $cashOutLocalPurchaseRows = getRows($cashOutLocalPurchaseSql, 'ss', [$from_date, $to_date]);

    $cashOutImportPurchaseSql = "SELECT purchase_date as txn_date, CONCAT('Import purchase - ', purchase_no) as description, purchase_no as reference, 0 as cash_in, paid_amount_pkr as cash_out
        FROM import_purchases WHERE payment_method = 'cash' AND paid_amount_pkr > 0 AND purchase_date >= ? AND purchase_date <= ?";
    $cashOutImportPurchaseRows = getRows($cashOutImportPurchaseSql, 'ss', [$from_date, $to_date]);

    $cashOutChineseSql = "SELECT cp.payment_date as txn_date, CONCAT('Payment to Chinese supplier - ', cp.payment_no) as description, cp.payment_no as reference, 0 as cash_in, cp.amount_pkr as cash_out
        FROM chinese_supplier_payments cp
        WHERE cp.payment_type = 'cash' AND cp.status = 'completed' AND cp.payment_date >= ? AND cp.payment_date <= ?";
    $cashOutChineseRows = getRows($cashOutChineseSql, 'ss', [$from_date, $to_date]);

    $cashOutExpenseSql = "SELECT e.expense_date as txn_date, CONCAT('Expense - ', e.category, ' - ', e.expense_no) as description, e.expense_no as reference, 0 as cash_in, e.paid_amount as cash_out
        FROM expenses e
        WHERE (e.paid_by = 'cash' OR e.paid_by = '' OR e.paid_by IS NULL) AND (e.status IN ('active', 'paid') OR e.payment_status IN ('paid', 'partial')) AND e.expense_date >= ? AND e.expense_date <= ?";
    $cashOutExpenseRows = getRows($cashOutExpenseSql, 'ss', [$from_date, $to_date]);

    $cashOutEmpSql = "SELECT ep.payment_date as txn_date, CONCAT('Employee payment - ', ep.payment_no) as description, ep.payment_no as reference, 0 as cash_in, ep.amount as cash_out
        FROM employee_payments ep
        WHERE ep.payment_date >= ? AND ep.payment_date <= ?";
    $cashOutEmpRows = getRows($cashOutEmpSql, 'ss', [$from_date, $to_date]);

    $partyReceivedSql = "SELECT pt.transaction_date as txn_date, CONCAT('Received from party - ', pt.transaction_no, ' - ', p.party_name) as description, pt.transaction_no as reference, pt.amount as cash_in, 0 as cash_out
        FROM party_transactions pt
        JOIN parties p ON pt.party_id = p.id
        WHERE pt.type = 'received' AND pt.payment_method = 'cash' AND pt.transaction_date >= ? AND pt.transaction_date <= ?";
    $partyReceivedRows = getRows($partyReceivedSql, 'ss', [$from_date, $to_date]);

    $partyPaidSql = "SELECT pt.transaction_date as txn_date, CONCAT('Paid to party - ', pt.transaction_no, ' - ', p.party_name) as description, pt.transaction_no as reference, 0 as cash_in, pt.amount as cash_out
        FROM party_transactions pt
        JOIN parties p ON pt.party_id = p.id
        WHERE pt.type = 'paid' AND pt.payment_method = 'cash' AND pt.transaction_date >= ? AND pt.transaction_date <= ?";
    $partyPaidRows = getRows($partyPaidSql, 'ss', [$from_date, $to_date]);

    $allTransactions = array_merge($cashInRows, $cashInSalesRows, $cashOutSupplierRows, $cashOutLocalPurchaseRows, $cashOutImportPurchaseRows, $cashOutChineseRows, $cashOutExpenseRows, $cashOutEmpRows, $partyReceivedRows, $partyPaidRows);

    usort($allTransactions, function($a, $b) {
        return strcmp($a['txn_date'], $b['txn_date']);
    });
}

$totalCashIn = 0;
$totalCashOut = 0;
foreach ($allTransactions as $txn) {
    $totalCashIn += (float)$txn['cash_in'];
    $totalCashOut += (float)$txn['cash_out'];
}

$openingBalance = 0;

$preFromSql = "SELECT
    COALESCE(SUM(cr.amount), 0) as total_in
    FROM customer_receipts cr
    WHERE cr.payment_method = 'cash' AND cr.payment_date < ?";
$preFromRow = getRow($preFromSql, 's', [$from_date]);
$totalCashInBefore = $preFromRow ? (float)$preFromRow['total_in'] : 0;
$preCashSales = getRow("SELECT COALESCE(SUM(paid_amount), 0) AS total_in FROM sales WHERE payment_method = 'cash' AND status = 'completed' AND sale_date < ?", 's', [$from_date]);
$totalCashInBefore += $preCashSales ? (float)$preCashSales['total_in'] : 0;

$preFromOutSupplier = "SELECT COALESCE(SUM(sp.amount), 0) as total_out
    FROM supplier_payments sp
    WHERE sp.payment_type = 'cash' AND sp.payment_date < ?";
$preFromOutSupplierRow = getRow($preFromOutSupplier, 's', [$from_date]);
$totalOutSupplierBefore = $preFromOutSupplierRow ? (float)$preFromOutSupplierRow['total_out'] : 0;
$preLocalPurchase = getRow("SELECT COALESCE(SUM(paid_amount), 0) AS total_out FROM local_purchases WHERE payment_method = 'cash' AND purchase_date < ?", 's', [$from_date]);
$totalOutSupplierBefore += $preLocalPurchase ? (float)$preLocalPurchase['total_out'] : 0;
$preImportPurchase = getRow("SELECT COALESCE(SUM(paid_amount_pkr), 0) AS total_out FROM import_purchases WHERE payment_method = 'cash' AND purchase_date < ?", 's', [$from_date]);
$totalOutSupplierBefore += $preImportPurchase ? (float)$preImportPurchase['total_out'] : 0;

$preFromOutChinese = "SELECT COALESCE(SUM(cp.amount_pkr), 0) as total_out
    FROM chinese_supplier_payments cp
    WHERE cp.payment_type = 'cash' AND cp.status = 'completed' AND cp.payment_date < ?";
$preFromOutChineseRow = getRow($preFromOutChinese, 's', [$from_date]);
$totalOutChineseBefore = $preFromOutChineseRow ? (float)$preFromOutChineseRow['total_out'] : 0;

$preFromOutExpense = "SELECT COALESCE(SUM(e.paid_amount), 0) as total_out
    FROM expenses e
    WHERE e.paid_amount > 0 AND (e.paid_by = 'cash' OR e.paid_by = '' OR e.paid_by IS NULL) AND e.status IN ('active', 'paid') AND e.expense_date < ?";
$preFromOutExpenseRow = getRow($preFromOutExpense, 's', [$from_date]);
$totalOutExpenseBefore = $preFromOutExpenseRow ? (float)$preFromOutExpenseRow['total_out'] : 0;

$preFromOutEmp = "SELECT COALESCE(SUM(ep.amount), 0) as total_out
    FROM employee_payments ep
    WHERE ep.payment_date < ?";
$preFromOutEmpRow = getRow($preFromOutEmp, 's', [$from_date]);
$totalOutEmpBefore = $preFromOutEmpRow ? (float)$preFromOutEmpRow['total_out'] : 0;

$prePartyReceived = "SELECT COALESCE(SUM(pt.amount), 0) as total_in
    FROM party_transactions pt
    WHERE pt.type = 'received' AND pt.payment_method = 'cash' AND pt.transaction_date < ?";
$prePartyReceivedRow = getRow($prePartyReceived, 's', [$from_date]);
$totalPartyReceivedBefore = $prePartyReceivedRow ? (float)$prePartyReceivedRow['total_in'] : 0;

$prePartyPaid = "SELECT COALESCE(SUM(pt.amount), 0) as total_out
    FROM party_transactions pt
    WHERE pt.type = 'paid' AND pt.payment_method = 'cash' AND pt.transaction_date < ?";
$prePartyPaidRow = getRow($prePartyPaid, 's', [$from_date]);
$totalPartyPaidBefore = $prePartyPaidRow ? (float)$prePartyPaidRow['total_out'] : 0;

$openingBalance = $totalCashInBefore - $totalOutSupplierBefore - $totalOutChineseBefore - $totalOutExpenseBefore - $totalOutEmpBefore + $totalPartyReceivedBefore - $totalPartyPaidBefore;
$closingBalance = $openingBalance + $totalCashIn - $totalCashOut;

include '../includes/header.php';
include '../includes/sidebar.php';
$fromDate = $from_date;
$toDate = $to_date;
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
                    <i class="fas fa-wallet fa-2x text-muted"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card" style="border-left: 4px solid #28a745;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total Cash In</div>
                        <h4 class="mb-0 text-success"><?php echo formatCurrency($totalCashIn); ?></h4>
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
                        <div class="text-muted small">Total Cash Out</div>
                        <h4 class="mb-0 text-danger"><?php echo formatCurrency($totalCashOut); ?></h4>
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
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Transactions</h5>
        <div class="d-flex gap-1 no-print">
            <a href="cashbook_print.php?from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>" target="_blank" class="btn btn-danger btn-sm"><i class="fas fa-print me-1"></i>Print</a>
            <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-control" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-control" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>">
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search me-1"></i>Filter</button>
                    <a href="cashbook.php" class="btn btn-outline-secondary"><i class="fas fa-undo me-1"></i>Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header" style="background-color: #1a2332; color: #fff;">
        <h5 class="mb-0"><i class="fas fa-book me-2"></i>Cash Book
            <small class="ms-2">(<?php echo formatDate($from_date); ?> to <?php echo formatDate($to_date); ?>)</small>
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="cashbookTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Reference</th>
                        <th class="text-end">Cash In (PKR)</th>
                        <th class="text-end">Cash Out (PKR)</th>
                        <th class="text-end">Balance (PKR)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-secondary">
                        <td></td>
                        <td><?php echo formatDate($from_date); ?></td>
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
                    <?php foreach ($allTransactions as $txn): ?>
                        <?php $runningBalance += (float)$txn['cash_in'] - (float)$txn['cash_out']; ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td><?php echo formatDate($txn['txn_date']); ?></td>
                            <td><?php echo htmlspecialchars($txn['description']); ?></td>
                            <td><strong><?php echo htmlspecialchars($txn['reference']); ?></strong></td>
                            <td class="text-end">
                                <?php echo (float)$txn['cash_in'] > 0 ? '<span class="text-success fw-bold">' . formatCurrency($txn['cash_in']) . '</span>' : '-'; ?>
                            </td>
                            <td class="text-end">
                                <?php echo (float)$txn['cash_out'] > 0 ? '<span class="text-danger fw-bold">' . formatCurrency($txn['cash_out']) . '</span>' : '-'; ?>
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
                        <td class="text-end"><strong><?php echo formatCurrency($totalCashIn); ?></strong></td>
                        <td class="text-end"><strong><?php echo formatCurrency($totalCashOut); ?></strong></td>
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
    $('#cashbookTable').DataTable({
        "pageLength": 50,
        "order": [],
        "paging": false,
        "searching": false,
        "info": false,
        "language": {
            "emptyTable": "No cash transactions found for the selected period"
        }
    });
});
</script>

<?php
include '../includes/footer.php';
?>
