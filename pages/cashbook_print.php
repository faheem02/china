<?php
require_once '../includes/database.php';
requireLogin();

$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

$allTransactions = [];

if (!empty($from_date) && !empty($to_date)) {
    $cashInSql = "SELECT cr.payment_date as txn_date, CONCAT('Receipt from customer - ', cr.receipt_no) as description, cr.receipt_no as reference, cr.amount as cash_in, 0 as cash_out
        FROM customer_receipts cr WHERE cr.payment_method = 'cash' AND cr.payment_date >= ? AND cr.payment_date <= ?";
    $cashInRows = getRows($cashInSql, 'ss', [$from_date, $to_date]);

    $cashInSalesRows = getRows("SELECT sale_date as txn_date, CONCAT('Sale - ', sale_no) as description, sale_no as reference, paid_amount as cash_in, 0 as cash_out FROM sales WHERE payment_method = 'cash' AND status = 'completed' AND paid_amount > 0 AND sale_date >= ? AND sale_date <= ?", 'ss', [$from_date, $to_date]);

    $cashOutSupplierSql = "SELECT sp.payment_date as txn_date, CONCAT('Payment to supplier - ', sp.payment_no) as description, sp.payment_no as reference, 0 as cash_in, sp.amount as cash_out
        FROM supplier_payments sp WHERE sp.payment_type = 'cash' AND sp.payment_date >= ? AND sp.payment_date <= ?";
    $cashOutSupplierRows = getRows($cashOutSupplierSql, 'ss', [$from_date, $to_date]);
    $cashOutLocalPurchaseRows = getRows("SELECT purchase_date as txn_date, CONCAT('Local purchase - ', purchase_no) as description, purchase_no as reference, 0 as cash_in, paid_amount as cash_out FROM local_purchases WHERE payment_method = 'cash' AND paid_amount > 0 AND purchase_date >= ? AND purchase_date <= ?", 'ss', [$from_date, $to_date]);
    $cashOutImportPurchaseRows = getRows("SELECT purchase_date as txn_date, CONCAT('Import purchase - ', purchase_no) as description, purchase_no as reference, 0 as cash_in, paid_amount_pkr as cash_out FROM import_purchases WHERE payment_method = 'cash' AND paid_amount_pkr > 0 AND purchase_date >= ? AND purchase_date <= ?", 'ss', [$from_date, $to_date]);

    $cashOutExpenseSql = "SELECT e.expense_date as txn_date, CONCAT('Expense - ', e.category, ' - ', e.expense_no) as description, e.expense_no as reference, 0 as cash_in, e.paid_amount as cash_out
        FROM expenses e WHERE e.paid_amount > 0 AND (e.paid_by = 'cash' OR e.paid_by = '' OR e.paid_by IS NULL) AND e.status IN ('active', 'paid') AND e.expense_date >= ? AND e.expense_date <= ?";
    $cashOutExpenseRows = getRows($cashOutExpenseSql, 'ss', [$from_date, $to_date]);

    $cashOutEmpSql = "SELECT ep.payment_date as txn_date, CONCAT('Employee payment - ', ep.payment_no) as description, ep.payment_no as reference, 0 as cash_in, ep.amount as cash_out
        FROM employee_payments ep WHERE ep.payment_date >= ? AND ep.payment_date <= ?";
    $cashOutEmpRows = getRows($cashOutEmpSql, 'ss', [$from_date, $to_date]);

    $partyReceivedSql = "SELECT pt.transaction_date as txn_date, CONCAT('Received from party - ', pt.transaction_no, ' - ', p.party_name) as description, pt.transaction_no as reference, pt.amount as cash_in, 0 as cash_out
        FROM party_transactions pt JOIN parties p ON pt.party_id = p.id
        WHERE pt.type = 'received' AND pt.payment_method = 'cash' AND pt.transaction_date >= ? AND pt.transaction_date <= ?";
    $partyReceivedRows = getRows($partyReceivedSql, 'ss', [$from_date, $to_date]);

    $partyPaidSql = "SELECT pt.transaction_date as txn_date, CONCAT('Paid to party - ', pt.transaction_no, ' - ', p.party_name) as description, pt.transaction_no as reference, 0 as cash_in, pt.amount as cash_out
        FROM party_transactions pt JOIN parties p ON pt.party_id = p.id
        WHERE pt.type = 'paid' AND pt.payment_method = 'cash' AND pt.transaction_date >= ? AND pt.transaction_date <= ?";
    $partyPaidRows = getRows($partyPaidSql, 'ss', [$from_date, $to_date]);

    $allTransactions = array_merge($cashInRows, $cashInSalesRows, $cashOutSupplierRows, $cashOutLocalPurchaseRows, $cashOutImportPurchaseRows, $cashOutExpenseRows, $cashOutEmpRows, $partyReceivedRows, $partyPaidRows);
    usort($allTransactions, function($a, $b) { return strcmp($a['txn_date'], $b['txn_date']); });
}

$totalCashIn = 0;
$totalCashOut = 0;
foreach ($allTransactions as $txn) {
    $totalCashIn += (float)$txn['cash_in'];
    $totalCashOut += (float)$txn['cash_out'];
}

$openingBalance = 0;
$preFromRow = getRow("SELECT COALESCE(SUM(cr.amount), 0) as total_in FROM customer_receipts cr WHERE cr.payment_method = 'cash' AND cr.payment_date < ?", 's', [$from_date]);
$totalCashInBefore = $preFromRow ? (float)$preFromRow['total_in'] : 0;
$preCashSales = getRow("SELECT COALESCE(SUM(paid_amount), 0) AS total_in FROM sales WHERE payment_method = 'cash' AND status = 'completed' AND sale_date < ?", 's', [$from_date]);
$totalCashInBefore += $preCashSales ? (float)$preCashSales['total_in'] : 0;
$preFromOutSupplierRow = getRow("SELECT COALESCE(SUM(sp.amount), 0) as total_out FROM supplier_payments sp WHERE sp.payment_type = 'cash' AND sp.payment_date < ?", 's', [$from_date]);
$totalOutSupplierBefore = $preFromOutSupplierRow ? (float)$preFromOutSupplierRow['total_out'] : 0;
$preLocalPurchase = getRow("SELECT COALESCE(SUM(paid_amount), 0) AS total_out FROM local_purchases WHERE payment_method = 'cash' AND purchase_date < ?", 's', [$from_date]);
$totalOutSupplierBefore += $preLocalPurchase ? (float)$preLocalPurchase['total_out'] : 0;
$preImportPurchase = getRow("SELECT COALESCE(SUM(paid_amount_pkr), 0) AS total_out FROM import_purchases WHERE payment_method = 'cash' AND purchase_date < ?", 's', [$from_date]);
$totalOutSupplierBefore += $preImportPurchase ? (float)$preImportPurchase['total_out'] : 0;
$preFromOutExpenseRow = getRow("SELECT COALESCE(SUM(e.paid_amount), 0) as total_out FROM expenses e WHERE e.paid_amount > 0 AND (e.paid_by = 'cash' OR e.paid_by = '' OR e.paid_by IS NULL) AND e.status IN ('active', 'paid') AND e.expense_date < ?", 's', [$from_date]);
$totalOutExpenseBefore = $preFromOutExpenseRow ? (float)$preFromOutExpenseRow['total_out'] : 0;
$preFromOutEmpRow = getRow("SELECT COALESCE(SUM(ep.amount), 0) as total_out FROM employee_payments ep WHERE ep.payment_date < ?", 's', [$from_date]);
$totalOutEmpBefore = $preFromOutEmpRow ? (float)$preFromOutEmpRow['total_out'] : 0;
$prePartyReceivedRow = getRow("SELECT COALESCE(SUM(pt.amount), 0) as total_in FROM party_transactions pt WHERE pt.type = 'received' AND pt.payment_method = 'cash' AND pt.transaction_date < ?", 's', [$from_date]);
$totalPartyReceivedBefore = $prePartyReceivedRow ? (float)$prePartyReceivedRow['total_in'] : 0;
$prePartyPaidRow = getRow("SELECT COALESCE(SUM(pt.amount), 0) as total_out FROM party_transactions pt WHERE pt.type = 'paid' AND pt.payment_method = 'cash' AND pt.transaction_date < ?", 's', [$from_date]);
$totalPartyPaidBefore = $prePartyPaidRow ? (float)$prePartyPaidRow['total_out'] : 0;

$openingBalance = $totalCashInBefore - $totalOutSupplierBefore - $totalOutExpenseBefore - $totalOutEmpBefore + $totalPartyReceivedBefore - $totalPartyPaidBefore;
$closingBalance = $openingBalance + $totalCashIn - $totalCashOut;
$company = getRow("SELECT * FROM company_settings LIMIT 1");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cash Book - Print</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
* { font-family: 'Poppins', sans-serif; }
body { background: #f0f2f5; margin: 0; padding: 0; }
.action-buttons { position: fixed; top: 0; left: 0; right: 0; z-index: 9999; background: #1a2332; padding: 10px 20px; display: flex; gap: 10px; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
.action-buttons .btn { font-size: 14px; }
.ledger-wrapper { margin-top: 70px; display: flex; justify-content: center; padding: 20px; }
.ledger-a4 { max-width: 1100px; width: 100%; background: #fff; padding: 40px; border: 1px solid #ddd; }
.ledger-a4 .inv-header { text-align: center; border-bottom: 3px solid #1a2332; padding-bottom: 15px; margin-bottom: 20px; }
.ledger-a4 .inv-header h3 { margin: 0; color: #1a2332; font-weight: 700; }
.ledger-a4 .inv-header p { margin: 2px 0 0; color: #666; font-size: 13px; }
.ledger-a4 .inv-title { text-align: center; background: #f0f0f0; padding: 8px; border: 1px solid #ccc; margin-bottom: 20px; font-weight: 700; font-size: 14px; color: #1a2332; }
.ledger-a4 .inv-meta { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 13px; }
.ledger-a4 .inv-meta strong { color: #1a2332; }
.ledger-a4 .summary-box { display: flex; gap: 20px; margin-bottom: 20px; }
.ledger-a4 .summary-item { flex: 1; background: #f8f9fa; border-left: 4px solid #1a2332; padding: 12px 15px; border-radius: 0 6px 6px 0; }
.ledger-a4 .summary-item .label { font-size: 11px; color: #666; text-transform: uppercase; font-weight: 600; }
.ledger-a4 .summary-item .amount { font-size: 20px; font-weight: 700; color: #1a2332; margin-top: 4px; }
.ledger-a4 .summary-item.success { border-left-color: #198754; }
.ledger-a4 .summary-item.success .amount { color: #198754; }
.ledger-a4 .summary-item.danger { border-left-color: #dc3545; }
.ledger-a4 .summary-item.danger .amount { color: #dc3545; }
.ledger-a4 table { width: 100%; font-size: 12px; border-collapse: collapse; }
.ledger-a4 table thead th { background: #1a2332; color: #fff; padding: 10px 12px; font-weight: 600; white-space: nowrap; }
.ledger-a4 table tbody td { padding: 8px 12px; border-bottom: 1px solid #eee; }
.ledger-a4 table tbody tr:nth-child(even) { background: #f9f9f9; }
.ledger-a4 table tfoot td, .ledger-a4 table tfoot th { padding: 10px 12px; font-weight: 700; background: #e9ecef; border-top: 2px solid #1a2332; }
.ledger-a4 .inv-footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 2px solid #1a2332; font-size: 12px; color: #666; }
.text-cash-in { color: #198754; font-weight: 700; }
.text-cash-out { color: #dc3545; font-weight: 700; }
.text-balance-pos { color: #198754; font-weight: 700; }
.text-balance-neg { color: #dc3545; font-weight: 700; }
.opening-row { background: #e8f4fd !important; }
.closing-row { background: #e9ecef !important; font-weight: 700; }
@media print {
    body { background: #fff !important; margin: 0; padding: 0; }
    .action-buttons { display: none !important; }
    .ledger-wrapper { margin: 0; padding: 10px; }
    .ledger-a4 { border: none; padding: 15px; max-width: 100%; }
    @page { size: A4 landscape; margin: 10mm; }
}
</style>
</head>
<body>
<div class="action-buttons">
    <a href="cashbook.php?from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <span class="text-white fw-bold me-2"><i class="fas fa-money-bill me-1"></i>Cash Book</span>
    <button class="btn btn-light btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
    <button class="btn btn-success btn-sm" onclick="downloadPDF()"><i class="fas fa-download me-1"></i>Download PDF</button>
</div>
<div class="ledger-wrapper">
<div class="ledger-a4" id="ledgerContent">
    <?php include '../includes/print_branding.php'; ?>
    <div class="inv-meta">
        <div><strong>Period:</strong> <?php echo formatDate($from_date); ?> to <?php echo formatDate($to_date); ?></div>
        <div style="text-align:right"><strong>Printed:</strong> <?php echo date('d-m-Y h:i A'); ?><br><strong>User:</strong> <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin'; ?></div>
    </div>
    <div class="inv-title">CASH BOOK STATEMENT | <?php echo formatDate($from_date); ?> to <?php echo formatDate($to_date); ?></div>
    <div class="summary-box">
        <div class="summary-item"><div class="label">Opening Balance</div><div class="amount"><?php echo formatCurrency($openingBalance); ?></div></div>
        <div class="summary-item success"><div class="label">Total Cash In</div><div class="amount"><?php echo formatCurrency($totalCashIn); ?></div></div>
        <div class="summary-item danger"><div class="label">Total Cash Out</div><div class="amount"><?php echo formatCurrency($totalCashOut); ?></div></div>
        <div class="summary-item <?php echo $closingBalance >= 0 ? 'success' : 'danger'; ?>"><div class="label">Closing Balance</div><div class="amount"><?php echo formatCurrency($closingBalance); ?></div></div>
    </div>
    <table>
        <thead><tr><th>#</th><th>Date</th><th>Description</th><th>Reference</th><th style="text-align:right">Cash In (PKR)</th><th style="text-align:right">Cash Out (PKR)</th><th style="text-align:right">Balance (PKR)</th></tr></thead>
        <tbody>
            <tr class="opening-row"><td></td><td><?php echo formatDate($from_date); ?></td><td><strong>Opening Balance</strong></td><td></td><td style="text-align:right"></td><td style="text-align:right"></td><td style="text-align:right" class="<?php echo $openingBalance >= 0 ? 'text-balance-pos' : 'text-balance-neg'; ?>"><strong><?php echo formatCurrency($openingBalance); ?></strong></td></tr>
            <?php $runningBalance = $openingBalance; $counter = 1; ?>
            <?php if (count($allTransactions) > 0): ?>
                <?php foreach ($allTransactions as $txn): ?>
                    <?php $runningBalance += (float)$txn['cash_in'] - (float)$txn['cash_out']; ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
                        <td><?php echo formatDate($txn['txn_date']); ?></td>
                        <td><?php echo htmlspecialchars($txn['description']); ?></td>
                        <td><strong><?php echo htmlspecialchars($txn['reference']); ?></strong></td>
                        <td style="text-align:right"><?php if ((float)$txn['cash_in'] > 0): ?><span class="text-cash-in"><?php echo formatCurrency($txn['cash_in']); ?></span><?php else: ?>-<?php endif; ?></td>
                        <td style="text-align:right"><?php if ((float)$txn['cash_out'] > 0): ?><span class="text-cash-out"><?php echo formatCurrency($txn['cash_out']); ?></span><?php else: ?>-<?php endif; ?></td>
                        <td style="text-align:right" class="<?php echo $runningBalance >= 0 ? 'text-balance-pos' : 'text-balance-neg'; ?>"><strong><?php echo formatCurrency($runningBalance); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center; color:#999; padding:30px;">No cash transactions found for the selected period</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot><tr class="closing-row"><th colspan="4" style="text-align:right">TOTAL</th><th style="text-align:right" class="text-cash-in"><?php echo formatCurrency($totalCashIn); ?></th><th style="text-align:right" class="text-cash-out"><?php echo formatCurrency($totalCashOut); ?></th><th style="text-align:right" class="<?php echo $closingBalance >= 0 ? 'text-balance-pos' : 'text-balance-neg'; ?>"><?php echo formatCurrency($closingBalance); ?></th></tr></tfoot>
    </table>
    <div class="inv-footer"><p>Generated on <?php echo date('d-m-Y h:i A'); ?> | <?php echo CLIENT_NAME; ?></p></div>
</div>
</div>
<script>
function downloadPDF() {
    var opt = { margin: 0.5, filename: 'cash_book_<?php echo date('Y-m-d'); ?>.pdf', image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' } };
    if (typeof html2pdf !== 'undefined') { html2pdf().set(opt).from(document.getElementById('ledgerContent')).save(); } else { var s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js'; s.onload = function() { html2pdf().set(opt).from(document.getElementById('ledgerContent')).save(); }; document.head.appendChild(s); }
}
</script>
</body>
</html>
