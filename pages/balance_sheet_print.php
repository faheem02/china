<?php
require_once '../includes/database.php';
requireLogin();

$company = getRow("SELECT * FROM company_settings LIMIT 1");

function safeGetValue($result, $key = 'total', $default = 0) {
    if ($result && is_array($result) && isset($result[$key])) return (float)$result[$key];
    return $default;
}

$rmValue = getRow("SELECT COALESCE(SUM(current_stock * purchase_price_pkr), 0) as total FROM raw_materials WHERE status = 'active'");
$rawMaterialTotal = safeGetValue($rmValue);
$fgValue = getRow("SELECT COALESCE(SUM(current_stock * cost_price), 0) as total FROM finished_goods WHERE status = 'active'");
$finishedGoodsTotal = safeGetValue($fgValue);
$totalSales = getRow("SELECT COALESCE(SUM(total_amount), 0) as total, COALESCE(SUM(balance), 0) as outstanding FROM sales");
$totalRevenue = safeGetValue($totalSales, 'total');
$totalOutstanding = safeGetValue($totalSales, 'outstanding');
$localPurchases = getRow("SELECT COALESCE(SUM(total_amount), 0) as total FROM local_purchases");
$localPurchasesTotal = safeGetValue($localPurchases);
$importPurchases = getRow("SELECT COALESCE(SUM(total_amount_pkr), 0) as total FROM import_purchases");
$importPurchasesTotal = safeGetValue($importPurchases);
$totalExpenses = getRow("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE status = 'active'");
$totalExpensesTotal = safeGetValue($totalExpenses);
$custOutstanding = getRow("SELECT COALESCE(SUM(balance), 0) as total FROM sales WHERE payment_status != 'paid'");
$customerOutstandingTotal = safeGetValue($custOutstanding);
$localPayable = getRow("SELECT COALESCE(SUM(balance), 0) as total FROM local_purchases WHERE payment_status != 'paid'");
$localPayableTotal = safeGetValue($localPayable);
$chinesePayable = getRow("SELECT COALESCE(SUM(balance_pkr), 0) as total FROM import_purchases WHERE payment_status != 'paid'");
$chinesePayableTotal = safeGetValue($chinesePayable);
$empSalary = getRow("SELECT COALESCE(SUM(monthly_salary), 0) as total FROM employees WHERE status = 'active'");
$empSalaryTotal = safeGetValue($empSalary);
$totalInventory = $rawMaterialTotal + $finishedGoodsTotal;
$totalPurchases = $localPurchasesTotal + $importPurchasesTotal;
$totalPayable = $localPayableTotal + $chinesePayableTotal;
$totalReceivable = $customerOutstandingTotal;
$totalLiabilities = $totalPayable + $empSalaryTotal;
$totalAssets = $totalInventory + $totalReceivable;
$totalCosts = $totalPurchases + $totalExpensesTotal;
$netBalance = $totalRevenue - $totalCosts;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Balance Sheet - Print</title>
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
.bs-section { margin-bottom: 20px; }
.bs-section h5 { font-size: 14px; font-weight: 700; margin-bottom: 10px; padding: 8px 12px; color: #fff; border-radius: 4px; }
.bs-section.assets h5 { background: #198754; }
.bs-section.liabilities h5 { background: #dc3545; }
.bs-row-total { background: #f0f0f0 !important; font-weight: 700; }
.bs-grand-total { background: #1a2332 !important; color: #fff !important; }
.bs-grand-total td { background: #1a2332 !important; color: #fff !important; font-weight: 700; }
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
    <a href="balance_sheet.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <span class="text-white fw-bold me-2"><i class="fas fa-balance-scale me-1"></i>Balance Sheet</span>
    <button class="btn btn-light btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
    <button class="btn btn-success btn-sm" onclick="downloadPDF()"><i class="fas fa-download me-1"></i>Download PDF</button>
</div>
<div class="ledger-wrapper">
<div class="ledger-a4" id="ledgerContent">
    <div class="inv-header">
        <h3>BALANCE SHEET</h3>
        <p><?php echo htmlspecialchars($company['company_name'] ?? 'ERP System'); ?></p>
        <?php if ($company['address'] ?? null): ?><p style="margin:2px 0 0;font-size:12px;"><?php echo htmlspecialchars($company['address']); ?></p><?php endif; ?>
        <?php if ($company['phone'] ?? null): ?><p style="margin:2px 0 0;font-size:12px;">Ph: <?php echo htmlspecialchars($company['phone']); ?></p><?php endif; ?>
    </div>
    <div class="inv-meta">
        <div><strong>As of:</strong> <?php echo date('d-m-Y'); ?></div>
        <div style="text-align:right"><strong>Printed:</strong> <?php echo date('d-m-Y h:i A'); ?><br><strong>User:</strong> <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin'; ?></div>
    </div>
    <div class="inv-title">BALANCE SHEET AS OF <?php echo date('d-m-Y'); ?></div>
    <div class="summary-box">
        <div class="summary-item primary"><div class="label">Total Assets</div><div class="amount"><?php echo formatCurrency($totalAssets); ?></div></div>
        <div class="summary-item danger"><div class="label">Total Liabilities</div><div class="amount"><?php echo formatCurrency($totalLiabilities); ?></div></div>
        <div class="summary-item success"><div class="label">Net Balance</div><div class="amount"><?php echo formatCurrency($netBalance); ?></div></div>
        <div class="summary-item"><div class="label">Revenue</div><div class="amount"><?php echo formatCurrency($totalRevenue); ?></div></div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="bs-section assets">
                <h5><i class="fas fa-plus-circle me-2"></i>Assets</h5>
                <table class="table table-sm">
                    <thead><tr><th>Item</th><th style="text-align:right">Amount (PKR)</th></tr></thead>
                    <tbody>
                        <tr><td colspan="2" class="fw-bold text-muted">Current Assets</td></tr>
                        <tr><td class="ps-4">Raw Materials Inventory</td><td class="text-end"><?php echo formatCurrency($rawMaterialTotal); ?></td></tr>
                        <tr><td class="ps-4">Finished Goods Inventory</td><td class="text-end"><?php echo formatCurrency($finishedGoodsTotal); ?></td></tr>
                        <tr class="bs-row-total"><td class="ps-4"><strong>Total Inventory</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalInventory); ?></strong></td></tr>
                        <tr><td class="ps-4">Accounts Receivable (Customers)</td><td class="text-end"><?php echo formatCurrency($totalReceivable); ?></td></tr>
                    </tbody>
                    <tfoot><tr class="bs-grand-total"><td><strong>TOTAL ASSETS</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalAssets); ?></strong></td></tr></tfoot>
                </table>
            </div>
        </div>
        <div class="col-md-6">
            <div class="bs-section liabilities">
                <h5><i class="fas fa-minus-circle me-2"></i>Liabilities</h5>
                <table class="table table-sm">
                    <thead><tr><th>Item</th><th style="text-align:right">Amount (PKR)</th></tr></thead>
                    <tbody>
                        <tr><td colspan="2" class="fw-bold text-muted">Current Liabilities</td></tr>
                        <tr><td class="ps-4">Accounts Payable (Local Suppliers)</td><td class="text-end"><?php echo formatCurrency($localPayableTotal); ?></td></tr>
                        <tr><td class="ps-4">Accounts Payable (Chinese Suppliers)</td><td class="text-end"><?php echo formatCurrency($chinesePayableTotal); ?></td></tr>
                        <tr class="bs-row-total"><td class="ps-4"><strong>Total Payable</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalPayable); ?></strong></td></tr>
                        <tr><td class="ps-4">Estimated Salary Payable</td><td class="text-end"><?php echo formatCurrency($empSalaryTotal); ?></td></tr>
                    </tbody>
                    <tfoot><tr class="bs-grand-total"><td><strong>TOTAL LIABILITIES</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalLiabilities); ?></strong></td></tr></tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="inv-footer"><p>Generated on <?php echo date('d-m-Y h:i A'); ?> | <?php echo htmlspecialchars($company['company_name'] ?? 'ERP System'); ?></p></div>
</div>
</div>
<script>
function downloadPDF() {
    var opt = { margin: 0.5, filename: 'balance_sheet_<?php echo date('Y-m-d'); ?>.pdf', image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' } };
    if (typeof html2pdf !== 'undefined') { html2pdf().set(opt).from(document.getElementById('ledgerContent')).save(); } else { var s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js'; s.onload = function() { html2pdf().set(opt).from(document.getElementById('ledgerContent')).save(); }; document.head.appendChild(s); }
}
</script>
</body>
</html>
