<?php
require_once '../includes/database.php';
requireLogin();

$asOfDate = $_GET['as_of_date'] ?? date('Y-m-d');
$company = getRow("SELECT * FROM company_settings LIMIT 1");

function tbVal($sql, $params = [], $types = '') {
    $row = getRow($sql, $types ?: null, $params ?: null);
    return $row ? (float)($row['total'] ?? 0) : 0.0;
}

$totalCash = tbVal("SELECT COALESCE(SUM(amount),0) AS total FROM customer_receipts WHERE payment_method='cash'");
$totalCashOut = tbVal("SELECT COALESCE(SUM(amount),0) AS total FROM supplier_payments WHERE payment_type='cash'")
    + tbVal("SELECT COALESCE(SUM(amount),0) AS total FROM expenses")
    + tbVal("SELECT COALESCE(SUM(amount),0) AS total FROM employee_payments")
    + tbVal("SELECT COALESCE(SUM(amount),0) AS total FROM party_transactions WHERE type='paid' AND payment_method='cash'");
$netCash = $totalCash - $totalCashOut;
$bankBalance = tbVal("SELECT COALESCE(balance,0) AS total FROM accounts WHERE account_type = 'Bank' ORDER BY id DESC LIMIT 1");
$rmStock = tbVal("SELECT COALESCE(SUM(current_stock * purchase_price_pkr),0) AS total FROM raw_materials");
$fgStock = tbVal("SELECT COALESCE(SUM(current_stock * cost_price),0) AS total FROM finished_goods");
$partyReceivable = tbVal("SELECT COALESCE(SUM(current_balance),0) AS total FROM parties WHERE current_balance > 0");
$partyPayable = tbVal("SELECT COALESCE(ABS(SUM(current_balance)),0) AS total FROM parties WHERE current_balance < 0");
$supplierOutstanding = tbVal("SELECT COALESCE(SUM(balance),0) AS total FROM local_purchases WHERE payment_status != 'paid'");
$importOutstanding = tbVal("SELECT COALESCE(SUM(balance_pkr),0) AS total FROM import_purchases WHERE payment_status != 'paid'");
$customerOutstanding = tbVal("SELECT COALESCE(SUM(balance),0) AS total FROM sales WHERE payment_status != 'paid'");
$empPayable = tbVal("SELECT COALESCE(SUM(balance),0) AS total FROM employee_payables WHERE payment_status != 'paid'");
$expenseOutstanding = tbVal("SELECT COALESCE(SUM(balance),0) AS total FROM expenses WHERE payment_status != 'paid'");
$totalSalesAll = tbVal("SELECT COALESCE(SUM(total_amount),0) AS total FROM sales");
$totalPurchaseAll = tbVal("SELECT COALESCE(SUM(total_amount),0) AS total FROM local_purchases") + tbVal("SELECT COALESCE(SUM(grand_total_pkr),0) AS total FROM import_purchases");
$totalExpenseAll = tbVal("SELECT COALESCE(SUM(amount),0) AS total FROM expenses");
$totalEmpPaidAll = tbVal("SELECT COALESCE(SUM(amount),0) AS total FROM employee_payments");
$netProfit = $totalSalesAll - $totalPurchaseAll - $totalExpenseAll - $totalEmpPaidAll;
$totalAssets = $netCash + $bankBalance + $rmStock + $fgStock + $partyReceivable + $customerOutstanding;
$totalLiabilities = $partyPayable + $supplierOutstanding + $importOutstanding + $empPayable + $expenseOutstanding;
$equity = $totalAssets - $totalLiabilities;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Trial Balance - Print</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
* { font-family: 'Poppins', sans-serif; }
body { background: #f0f2f5; margin: 0; padding: 0; }
.action-buttons { position: fixed; top: 0; left: 0; right: 0; z-index: 9999; background: #1a2332; padding: 10px 20px; display: flex; gap: 10px; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
.action-buttons .btn { font-size: 14px; }
.ledger-wrapper { margin-top: 70px; display: flex; justify-content: center; padding: 20px; }
.ledger-a4 { max-width: 1000px; width: 100%; background: #fff; padding: 40px; border: 1px solid #ddd; }
.ledger-a4 .inv-header { text-align: center; border-bottom: 3px solid #1a2332; padding-bottom: 15px; margin-bottom: 20px; }
.ledger-a4 .inv-header h3 { margin: 0; color: #1a2332; font-weight: 700; }
.ledger-a4 .inv-header p { margin: 2px 0 0; color: #666; font-size: 13px; }
.ledger-a4 .inv-title { text-align: center; background: #f0f0f0; padding: 8px; border: 1px solid #ccc; margin-bottom: 20px; font-weight: 700; font-size: 14px; color: #1a2332; }
.ledger-a4 .inv-meta { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 13px; }
.ledger-a4 .inv-meta strong { color: #1a2332; }
.ledger-a4 .summary-box { display: flex; gap: 20px; margin-bottom: 25px; }
.ledger-a4 .summary-item { flex: 1; background: #f8f9fa; border-left: 4px solid #1a2332; padding: 12px 15px; border-radius: 0 6px 6px 0; }
.ledger-a4 .summary-item .label { font-size: 11px; color: #666; text-transform: uppercase; font-weight: 600; }
.ledger-a4 .summary-item .amount { font-size: 18px; font-weight: 700; color: #1a2332; margin-top: 4px; }
.ledger-a4 .summary-item.success { border-left-color: #198754; } .ledger-a4 .summary-item.success .amount { color: #198754; }
.ledger-a4 .summary-item.danger { border-left-color: #dc3545; } .ledger-a4 .summary-item.danger .amount { color: #dc3545; }
.ledger-a4 .summary-item.primary { border-left-color: #0d6efd; } .ledger-a4 .summary-item.primary .amount { color: #0d6efd; }
.ledger-a4 table { width: 100%; font-size: 12px; border-collapse: collapse; }
.ledger-a4 table thead th { background: #1a2332; color: #fff; padding: 10px 12px; font-weight: 600; }
.ledger-a4 table tbody td { padding: 8px 12px; border-bottom: 1px solid #eee; }
.ledger-a4 table tfoot td, .ledger-a4 table tfoot th { padding: 10px 12px; font-weight: 700; background: #e9ecef; border-top: 2px solid #1a2332; }
.ledger-a4 .inv-footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 2px solid #1a2332; font-size: 12px; color: #666; }
.tb-section h5 { font-size: 14px; font-weight: 700; margin-bottom: 10px; padding: 8px 12px; color: #fff; border-radius: 4px; }
.tb-section.debit h5 { background: #0d6efd; }
.tb-section.credit h5 { background: #dc3545; }
.tb-total-row { background: #e9ecef !important; font-weight: 700; }
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
    <a href="reports_trial_balance.php?as_of_date=<?php echo $asOfDate; ?>" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <span class="text-white fw-bold me-2"><i class="fas fa-balance-scale me-1"></i>Trial Balance</span>
    <button class="btn btn-light btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
    <button class="btn btn-success btn-sm" onclick="downloadPDF()"><i class="fas fa-download me-1"></i>Download PDF</button>
</div>
<div class="ledger-wrapper">
<div class="ledger-a4" id="ledgerContent">
    <?php include '../includes/print_branding.php'; ?>
    <div class="inv-meta">
        <div><strong>As of:</strong> <?php echo formatDate($asOfDate); ?></div>
        <div style="text-align:right"><strong>Printed:</strong> <?php echo date('d-m-Y h:i A'); ?><br><strong>User:</strong> <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin'; ?></div>
    </div>
    <div class="inv-title">TRIAL BALANCE AS OF <?php echo formatDate($asOfDate); ?></div>
    <div class="summary-box">
        <div class="summary-item primary"><div class="label">Total Assets</div><div class="amount"><?php echo formatCurrency($totalAssets); ?></div></div>
        <div class="summary-item danger"><div class="label">Total Liabilities</div><div class="amount"><?php echo formatCurrency($totalLiabilities); ?></div></div>
        <div class="summary-item success"><div class="label">Net Worth / Equity</div><div class="amount"><?php echo formatCurrency($equity); ?></div></div>
        <div class="summary-item <?php echo $netProfit >= 0 ? 'success' : 'danger'; ?>"><div class="label">Net Profit</div><div class="amount"><?php echo formatCurrency($netProfit); ?></div></div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="tb-section debit">
                <h5><i class="fas fa-arrow-up me-2"></i>Assets (Debit)</h5>
                <table class="table table-sm">
                    <thead><tr><th>Account</th><th style="text-align:right">Amount (PKR)</th></tr></thead>
                    <tbody>
                        <tr><td>Cash in Hand</td><td class="text-end fw-bold"><?php echo formatCurrency($netCash); ?></td></tr>
                        <tr><td>Bank Balances</td><td class="text-end fw-bold"><?php echo formatCurrency($bankBalance); ?></td></tr>
                        <tr><td>Raw Materials Stock</td><td class="text-end fw-bold"><?php echo formatCurrency($rmStock); ?></td></tr>
                        <tr><td>Finished Goods Stock</td><td class="text-end fw-bold"><?php echo formatCurrency($fgStock); ?></td></tr>
                        <tr><td>Party Receivable</td><td class="text-end fw-bold"><?php echo formatCurrency($partyReceivable); ?></td></tr>
                        <tr><td>Customer Outstanding</td><td class="text-end fw-bold"><?php echo formatCurrency($customerOutstanding); ?></td></tr>
                    </tbody>
                    <tfoot><tr class="tb-total-row"><td><strong>Total Assets</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalAssets); ?></strong></td></tr></tfoot>
                </table>
            </div>
        </div>
        <div class="col-md-6">
            <div class="tb-section credit">
                <h5><i class="fas fa-arrow-down me-2"></i>Liabilities (Credit)</h5>
                <table class="table table-sm">
                    <thead><tr><th>Account</th><th style="text-align:right">Amount (PKR)</th></tr></thead>
                    <tbody>
                        <tr><td>Party Payable</td><td class="text-end fw-bold"><?php echo formatCurrency($partyPayable); ?></td></tr>
                        <tr><td>Local Supplier Outstanding</td><td class="text-end fw-bold"><?php echo formatCurrency($supplierOutstanding); ?></td></tr>
                        <tr><td>Chinese Supplier Outstanding</td><td class="text-end fw-bold"><?php echo formatCurrency($importOutstanding); ?></td></tr>
                        <tr><td>Employee Payable</td><td class="text-end fw-bold"><?php echo formatCurrency($empPayable); ?></td></tr>
                        <tr><td>Expense Outstanding</td><td class="text-end fw-bold"><?php echo formatCurrency($expenseOutstanding); ?></td></tr>
                        <tr style="background:#fff3cd"><td><strong>Net Profit (Equity)</strong></td><td class="text-end fw-bold"><?php echo formatCurrency($netProfit); ?></td></tr>
                    </tbody>
                    <tfoot><tr class="tb-total-row"><td><strong>Total Liabilities + Equity</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalLiabilities + $netProfit); ?></strong></td></tr></tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="inv-footer"><p>Generated on <?php echo date('d-m-Y h:i A'); ?> | <?php echo CLIENT_NAME; ?></p></div>
</div>
</div>
<script>
function downloadPDF() {
    var opt = { margin: 0.5, filename: 'trial_balance_<?php echo date('Y-m-d'); ?>.pdf', image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' } };
    if (typeof html2pdf !== 'undefined') { html2pdf().set(opt).from(document.getElementById('ledgerContent')).save(); } else { var s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js'; s.onload = function() { html2pdf().set(opt).from(document.getElementById('ledgerContent')).save(); }; document.head.appendChild(s); }
}
</script>
</body>
</html>
