<?php
require_once '../includes/database.php';
requireLogin();

$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');
$company = getRow("SELECT * FROM company_settings LIMIT 1");

$totalSales = getRow("SELECT COALESCE(SUM(total_amount),0) AS total FROM sales WHERE DATE(date_time) BETWEEN ? AND ?", 'ss', [$fromDate, $toDate]);
$totalSalePaid = getRow("SELECT COALESCE(SUM(paid_amount),0) AS total FROM sales WHERE DATE(date_time) BETWEEN ? AND ?", 'ss', [$fromDate, $toDate]);
$totalSaleBalance = (float)$totalSales['total'] - (float)$totalSalePaid['total'];
$totalLocalPurchase = getRow("SELECT COALESCE(SUM(total_amount),0) AS total FROM local_purchases WHERE DATE(date_time) BETWEEN ? AND ?", 'ss', [$fromDate, $toDate]);
$totalImportPurchasePKR = getRow("SELECT COALESCE(SUM(grand_total_pkr),0) AS total FROM import_purchases WHERE DATE(date_time) BETWEEN ? AND ?", 'ss', [$fromDate, $toDate]);
$totalPurchases = (float)$totalLocalPurchase['total'] + (float)$totalImportPurchasePKR['total'];
$totalExpenses = getRow("SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE DATE(date_time) BETWEEN ? AND ?", 'ss', [$fromDate, $toDate]);
$totalEmpPayments = getRow("SELECT COALESCE(SUM(amount),0) AS total FROM employee_payments WHERE DATE(date_time) BETWEEN ? AND ?", 'ss', [$fromDate, $toDate]);
$grossProfit = $totalSales['total'] - $totalPurchases;
$netProfit = $grossProfit - (float)$totalExpenses['total'] - (float)$totalEmpPayments['total'];
$grossMargin = $totalSales['total'] > 0 ? number_format(($grossProfit / $totalSales['total']) * 100, 1) : '0';

$monthlyData = getRows("SELECT DATE_FORMAT(date_time, '%Y-%m') AS month_label,
    SUM(total_amount) AS sales,
    (SELECT COALESCE(SUM(total_amount),0) FROM local_purchases WHERE DATE_FORMAT(date_time, '%Y-%m') = month_label) AS local_purchase,
    (SELECT COALESCE(SUM(grand_total_pkr),0) FROM import_purchases WHERE DATE_FORMAT(date_time, '%Y-%m') = month_label) AS import_purchase,
    (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE DATE_FORMAT(date_time, '%Y-%m') = month_label) AS expenses
    FROM sales WHERE DATE(date_time) BETWEEN ? AND ? GROUP BY month_label ORDER BY month_label", 'ss', [$fromDate, $toDate]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Profit & Loss - Print</title>
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
.ledger-a4 .summary-item.info { border-left-color: #0dcaf0; } .ledger-a4 .summary-item.info .amount { color: #0dcaf0; }
.ledger-a4 table { width: 100%; font-size: 12px; border-collapse: collapse; }
.ledger-a4 table thead th { background: #1a2332; color: #fff; padding: 10px 12px; font-weight: 600; }
.ledger-a4 table tbody td { padding: 8px 12px; border-bottom: 1px solid #eee; }
.ledger-a4 table tfoot td, .ledger-a4 table tfoot th { padding: 10px 12px; font-weight: 700; background: #e9ecef; border-top: 2px solid #1a2332; }
.ledger-a4 .inv-footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 2px solid #1a2332; font-size: 12px; color: #666; }
.pl-section h5 { font-size: 14px; font-weight: 700; margin-bottom: 10px; padding: 8px 12px; color: #fff; border-radius: 4px; }
.pl-section.income h5 { background: #198754; }
.pl-section.expense h5 { background: #dc3545; }
.pl-total-row { background: #e9ecef !important; font-weight: 700; }
@media print {
    body { background: #fff !important; margin: 0; padding: 0; }
    .action-buttons { display: none !important; }
    .ledger-wrapper { margin: 0; padding: 10px; }
    .ledger-a4 { border: none; padding: 15px; max-width: 100%; }
    @page { size: A4 portrait; margin: 10mm; }
}
</style>
</head>
<body>
<div class="action-buttons">
    <a href="reports_profit_loss.php?from_date=<?php echo $fromDate; ?>&to_date=<?php echo $toDate; ?>" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <span class="text-white fw-bold me-2"><i class="fas fa-chart-line me-1"></i>Profit & Loss</span>
    <button class="btn btn-light btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
    <button class="btn btn-success btn-sm" onclick="downloadPDF()"><i class="fas fa-download me-1"></i>Download PDF</button>
</div>
<div class="ledger-wrapper">
<div class="ledger-a4" id="ledgerContent">
    <?php include '../includes/print_branding.php'; ?>
    <div class="inv-meta">
        <div><strong>Period:</strong> <?php echo formatDate($fromDate); ?> to <?php echo formatDate($toDate); ?></div>
        <div style="text-align:right"><strong>Printed:</strong> <?php echo date('d-m-Y h:i A'); ?><br><strong>User:</strong> <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin'; ?></div>
    </div>
    <div class="inv-title">PROFIT & LOSS STATEMENT | <?php echo formatDate($fromDate); ?> to <?php echo formatDate($toDate); ?></div>
    <div class="summary-box">
        <div class="summary-item <?php echo $netProfit >= 0 ? 'success' : 'danger'; ?>"><div class="label">Net Profit</div><div class="amount"><?php echo formatCurrency($netProfit); ?></div></div>
        <div class="summary-item primary"><div class="label">Gross Profit</div><div class="amount"><?php echo formatCurrency($grossProfit); ?></div></div>
        <div class="summary-item info"><div class="label">Gross Margin</div><div class="amount"><?php echo $grossMargin; ?>%</div></div>
        <div class="summary-item"><div class="label">Total Revenue</div><div class="amount"><?php echo formatCurrency($totalSales['total']); ?></div></div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="pl-section income">
                <h5><i class="fas fa-arrow-up me-2"></i>Income</h5>
                <table class="table table-sm">
                    <thead><tr><th>Item</th><th style="text-align:right">Amount (PKR)</th></tr></thead>
                    <tbody>
                        <tr><td>Sales Revenue</td><td class="text-end fw-bold text-success"><?php echo formatCurrency($totalSales['total']); ?></td></tr>
                        <tr><td>Cash Received</td><td class="text-end"><?php echo formatCurrency($totalSalePaid['total']); ?></td></tr>
                        <tr><td>Outstanding Receivable</td><td class="text-end text-warning"><?php echo formatCurrency($totalSaleBalance); ?></td></tr>
                    </tbody>
                    <tfoot><tr class="pl-total-row"><td><strong>Total Income</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalSales['total']); ?></strong></td></tr></tfoot>
                </table>
            </div>
        </div>
        <div class="col-md-6">
            <div class="pl-section expense">
                <h5><i class="fas fa-arrow-down me-2"></i>Expenses & Cost of Goods</h5>
                <table class="table table-sm">
                    <thead><tr><th>Item</th><th style="text-align:right">Amount (PKR)</th></tr></thead>
                    <tbody>
                        <tr><td>Local Purchases</td><td class="text-end"><?php echo formatCurrency($totalLocalPurchase['total']); ?></td></tr>
                        <tr><td>Import Purchases (PKR)</td><td class="text-end"><?php echo formatCurrency($totalImportPurchasePKR['total']); ?></td></tr>
                        <tr class="table-warning"><td><strong>Cost of Goods Sold</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalPurchases); ?></strong></td></tr>
                        <tr><td>Operating Expenses</td><td class="text-end"><?php echo formatCurrency($totalExpenses['total']); ?></td></tr>
                        <tr><td>Employee Payments</td><td class="text-end"><?php echo formatCurrency($totalEmpPayments['total']); ?></td></tr>
                    </tbody>
                    <tfoot><tr class="pl-total-row"><td><strong>Total Expenses</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalPurchases + (float)$totalExpenses['total'] + (float)$totalEmpPayments['total']); ?></strong></td></tr></tfoot>
                </table>
            </div>
        </div>
    </div>
    <?php if (count($monthlyData) > 0): ?>
    <div style="margin-top:25px;">
        <h5 style="font-size:14px; font-weight:700; margin-bottom:10px; padding:8px 12px; color:#fff; background:#1a2332; border-radius:4px;"><i class="fas fa-calendar me-2"></i>Monthly Breakdown</h5>
        <table class="table table-sm">
            <thead><tr><th>Month</th><th style="text-align:right">Sales</th><th style="text-align:right">Purchases</th><th style="text-align:right">Expenses</th><th style="text-align:right">Gross Profit</th></tr></thead>
            <tbody>
                <?php foreach ($monthlyData as $m): $gp = (float)$m['sales'] - (float)$m['local_purchase'] - (float)$m['import_purchase']; ?>
                <tr>
                    <td><strong><?php echo date('M-Y', strtotime($m['month_label'] . '-01')); ?></strong></td>
                    <td style="text-align:right" class="text-success"><?php echo formatCurrency($m['sales']); ?></td>
                    <td style="text-align:right"><?php echo formatCurrency((float)$m['local_purchase'] + (float)$m['import_purchase']); ?></td>
                    <td style="text-align:right" class="text-danger"><?php echo formatCurrency($m['expenses']); ?></td>
                    <td style="text-align:right; font-weight:700;" class="<?php echo $gp >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatCurrency($gp); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <div class="inv-footer"><p>Generated on <?php echo date('d-m-Y h:i A'); ?> | <?php echo CLIENT_NAME; ?></p></div>
</div>
</div>
<script>
function downloadPDF() {
    var opt = { margin: 0.5, filename: 'profit_loss_<?php echo date('Y-m-d'); ?>.pdf', image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' } };
    if (typeof html2pdf !== 'undefined') { html2pdf().set(opt).from(document.getElementById('ledgerContent')).save(); } else { var s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js'; s.onload = function() { html2pdf().set(opt).from(document.getElementById('ledgerContent')).save(); }; document.head.appendChild(s); }
}
</script>
</body>
</html>
