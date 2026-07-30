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

    $cashOutSupplierSql = "SELECT sp.payment_date as txn_date, CONCAT('Payment to supplier - ', sp.payment_no) as description, sp.payment_no as reference, 0 as cash_in, sp.amount as cash_out
        FROM supplier_payments sp
        WHERE sp.payment_type = 'cash' AND sp.payment_date >= ? AND sp.payment_date <= ?";
    $cashOutSupplierRows = getRows($cashOutSupplierSql, 'ss', [$from_date, $to_date]);

    $cashOutChineseSql = "SELECT cp.payment_date as txn_date, CONCAT('Payment to Chinese supplier - ', cp.payment_no) as description, cp.payment_no as reference, 0 as cash_in, cp.amount_pkr as cash_out
        FROM chinese_supplier_payments cp
        WHERE cp.payment_type = 'cash' AND cp.status = 'completed' AND cp.payment_date >= ? AND cp.payment_date <= ?";
    $cashOutChineseRows = getRows($cashOutChineseSql, 'ss', [$from_date, $to_date]);

    $cashOutExpenseSql = "SELECT e.expense_date as txn_date, CONCAT('Expense - ', e.category, ' - ', e.expense_no) as description, e.expense_no as reference, 0 as cash_in, e.amount as cash_out
        FROM expenses e
        WHERE e.status = 'active' AND e.expense_date >= ? AND e.expense_date <= ?";
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

    $allTransactions = array_merge($cashInRows, $cashOutSupplierRows, $cashOutChineseRows, $cashOutExpenseRows, $cashOutEmpRows, $partyReceivedRows, $partyPaidRows);

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

$preFromOutSupplier = "SELECT COALESCE(SUM(sp.amount), 0) as total_out
    FROM supplier_payments sp
    WHERE sp.payment_type = 'cash' AND sp.payment_date < ?";
$preFromOutSupplierRow = getRow($preFromOutSupplier, 's', [$from_date]);
$totalOutSupplierBefore = $preFromOutSupplierRow ? (float)$preFromOutSupplierRow['total_out'] : 0;

$preFromOutChinese = "SELECT COALESCE(SUM(cp.amount_pkr), 0) as total_out
    FROM chinese_supplier_payments cp
    WHERE cp.payment_type = 'cash' AND cp.status = 'completed' AND cp.payment_date < ?";
$preFromOutChineseRow = getRow($preFromOutChinese, 's', [$from_date]);
$totalOutChineseBefore = $preFromOutChineseRow ? (float)$preFromOutChineseRow['total_out'] : 0;

$preFromOutExpense = "SELECT COALESCE(SUM(e.amount), 0) as total_out
    FROM expenses e
    WHERE e.status = 'active' AND e.expense_date < ?";
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

<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-book me-2"></i>Cash Book
                <span class="ms-2 badge bg-success">PKR</span></span>
                <div class="d-flex gap-2 no-print">
                    <a href="cashbook_print.php?from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>" target="_blank" class="btn btn-danger btn-sm"><i class="fas fa-print me-1"></i>Print</a>
                    <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
                </div>
            </div>
            <div class="card-body no-print">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="GET" class="row g-3 mb-0">
                    <div class="col-md-4">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button>
                        <a href="cashbook.php" class="btn btn-secondary"><i class="fas fa-undo me-1"></i>Reset</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3 no-print">
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color: #6c757d;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number" style="color: #6c757d;"><?php echo formatCurrency($openingBalance); ?></div>
                    <div class="stat-label">Opening Balance</div>
                </div>
                <div class="stat-icon" style="color: #6c757d;">
                    <i class="fas fa-flag"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color: #28a745;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number text-success"><?php echo formatCurrency($totalCashIn); ?></div>
                    <div class="stat-label">Total Cash In</div>
                </div>
                <div class="stat-icon" style="color: #28a745;">
                    <i class="fas fa-arrow-down"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color: #dc3545;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number text-danger"><?php echo formatCurrency($totalCashOut); ?></div>
                    <div class="stat-label">Total Cash Out</div>
                </div>
                <div class="stat-icon" style="color: #dc3545;">
                    <i class="fas fa-arrow-up"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color: <?php echo $closingBalance >= 0 ? '#1a2332' : '#dc3545'; ?>;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number <?php echo $closingBalance >= 0 ? '' : 'text-danger'; ?>"><?php echo formatCurrency($closingBalance); ?></div>
                    <div class="stat-label">Closing Balance</div>
                </div>
                <div class="stat-icon" style="color: <?php echo $closingBalance >= 0 ? '#1a2332' : '#dc3545'; ?>;">
                    <i class="fas fa-flag-checkered"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list me-2"></i>Cash Transactions
                <span class="ms-2 text-muted">(<?php echo formatDate($from_date); ?> to <?php echo formatDate($to_date); ?>)</span></span>
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
                                <th>Cash In (PKR)</th>
                                <th>Cash Out (PKR)</th>
                                <th>Balance (PKR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($allTransactions) > 0): ?>
                                <?php $counter = 1; ?>
                                <?php $runningBalance = $openingBalance; ?>
                                <?php foreach ($allTransactions as $txn): ?>
                                    <?php
                                        $runningBalance += (float)$txn['cash_in'] - (float)$txn['cash_out'];
                                    ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo formatDate($txn['txn_date']); ?></td>
                                        <td><?php echo htmlspecialchars($txn['description']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($txn['reference']); ?></strong></td>
                                        <td class="text-success fw-bold">
                                            <?php echo $txn['cash_in'] > 0 ? formatCurrency($txn['cash_in']) : '-'; ?>
                                        </td>
                                        <td class="text-danger fw-bold">
                                            <?php echo $txn['cash_out'] > 0 ? formatCurrency($txn['cash_out']) : '-'; ?>
                                        </td>
                                        <td class="fw-bold <?php echo $runningBalance >= 0 ? 'text-dark' : 'text-danger'; ?>">
                                            <?php echo formatCurrency($runningBalance); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-3">
                                        <i class="fas fa-info-circle me-2"></i>No cash transactions found for the selected period
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-dark">
                                <td colspan="4" class="text-end"><strong>Totals</strong></td>
                                <td class="text-success fw-bold"><?php echo formatCurrency($totalCashIn); ?></td>
                                <td class="text-danger fw-bold"><?php echo formatCurrency($totalCashOut); ?></td>
                                <td class="fw-bold"><?php echo formatCurrency($closingBalance); ?></td>
                            </tr>
                        </tfoot>
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
    $('#cashbookTable').DataTable({
        "pageLength": 25,
        "order": [[1, "asc"]],
        "language": {
            "search": "Search Transactions:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ transactions",
            "emptyTable": "No cash transactions found"
        }
    });
});
</script>

<?php
include '../includes/footer.php';
?>
