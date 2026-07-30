<?php
require_once '../includes/database.php';
requireLogin();

$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');
$headFilter = $_GET['head'] ?? '';
$company = getRow("SELECT * FROM company_settings LIMIT 1");

$where = "WHERE e.expense_date BETWEEN ? AND ?";
$params = [$fromDate, $toDate];
if (!empty($headFilter)) { $where .= " AND e.category = ?"; $params[] = $headFilter; }

$expenses = getRows("SELECT e.* FROM expenses e $where ORDER BY e.expense_date DESC", 'ss' . (isset($params[2]) ? 's' : ''), $params);
$totalAmount = array_sum(array_column($expenses, 'amount'));
$totalPaid = array_sum(array_column($expenses, 'paid_amount'));

$byCategory = getRows("SELECT e.category, COUNT(*) AS cnt, SUM(e.amount) AS total_amt, SUM(e.paid_amount) AS total_paid FROM expenses e $where GROUP BY e.category ORDER BY total_amt DESC", 'ss' . (isset($params[2]) ? 's' : ''), $params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Expense Report - Print</title>
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
.ledger-a4 .summary-box { display: flex; gap: 20px; margin-bottom: 25px; }
.ledger-a4 .summary-item { flex: 1; background: #f8f9fa; border-left: 4px solid #1a2332; padding: 12px 15px; border-radius: 0 6px 6px 0; }
.ledger-a4 .summary-item .label { font-size: 11px; color: #666; text-transform: uppercase; font-weight: 600; }
.ledger-a4 .summary-item .amount { font-size: 18px; font-weight: 700; color: #1a2332; margin-top: 4px; }
.ledger-a4 .summary-item.danger { border-left-color: #dc3545; } .ledger-a4 .summary-item.danger .amount { color: #dc3545; }
.ledger-a4 .summary-item.success { border-left-color: #198754; } .ledger-a4 .summary-item.success .amount { color: #198754; }
.ledger-a4 .summary-item.warning { border-left-color: #ffc107; } .ledger-a4 .summary-item.warning .amount { color: #ffc107; }
.ledger-a4 table { width: 100%; font-size: 12px; border-collapse: collapse; }
.ledger-a4 table thead th { background: #1a2332; color: #fff; padding: 10px 12px; font-weight: 600; }
.ledger-a4 table tbody td { padding: 8px 12px; border-bottom: 1px solid #eee; }
.ledger-a4 table tbody tr:nth-child(even) { background: #f9f9f9; }
.ledger-a4 table tfoot td, .ledger-a4 table tfoot th { padding: 10px 12px; font-weight: 700; background: #e9ecef; border-top: 2px solid #1a2332; }
.ledger-a4 .inv-footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 2px solid #1a2332; font-size: 12px; color: #666; }
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
    <a href="reports_expenses.php?from_date=<?php echo $fromDate; ?>&to_date=<?php echo $toDate; ?>&head=<?php echo urlencode($headFilter); ?>" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <span class="text-white fw-bold me-2"><i class="fas fa-receipt me-1"></i>Expense Report</span>
    <button class="btn btn-light btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
    <button class="btn btn-success btn-sm" onclick="downloadPDF()"><i class="fas fa-download me-1"></i>Download PDF</button>
</div>
<div class="ledger-wrapper">
<div class="ledger-a4" id="ledgerContent">
    <?php include '../includes/print_branding.php'; ?>
    <div class="inv-meta">
        <div><strong>Period:</strong> <?php echo formatDate($fromDate); ?> to <?php echo formatDate($toDate); ?><?php if (!empty($headFilter)): ?><br><strong>Category:</strong> <?php echo htmlspecialchars($headFilter); ?><?php endif; ?></div>
        <div style="text-align:right"><strong>Printed:</strong> <?php echo date('d-m-Y h:i A'); ?><br><strong>User:</strong> <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin'; ?></div>
    </div>
    <div class="inv-title">EXPENSE REPORT | <?php echo formatDate($fromDate); ?> to <?php echo formatDate($toDate); ?></div>
    <div class="summary-box">
        <div class="summary-item danger"><div class="label">Total Expenses</div><div class="amount"><?php echo formatCurrency($totalAmount); ?></div></div>
        <div class="summary-item success"><div class="label">Total Paid</div><div class="amount"><?php echo formatCurrency($totalPaid); ?></div></div>
        <div class="summary-item warning"><div class="label">Outstanding</div><div class="amount"><?php echo formatCurrency($totalAmount - $totalPaid); ?></div></div>
        <div class="summary-item"><div class="label">Entries</div><div class="amount"><?php echo count($expenses); ?></div></div>
    </div>

    <?php if (count($byCategory) > 0): ?>
    <div style="margin-bottom:25px;">
        <h5 style="font-size:14px; font-weight:700; margin-bottom:10px; padding:8px 12px; color:#fff; background:#6f42c1; border-radius:4px;"><i class="fas fa-chart-pie me-2"></i>By Expense Head</h5>
        <table class="table table-sm">
            <thead><tr><th>Category</th><th style="text-align:right">Count</th><th style="text-align:right">Amount</th><th style="text-align:right">% of Total</th></tr></thead>
            <tbody>
                <?php foreach($byCategory as $cat): ?>
                <tr>
                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($cat['category']); ?></span></td>
                    <td style="text-align:right"><?php echo $cat['cnt']; ?></td>
                    <td style="text-align:right; font-weight:700;"><?php echo formatCurrency($cat['total_amt']); ?></td>
                    <td style="text-align:right"><?php echo $totalAmount > 0 ? number_format(($cat['total_amt']/$totalAmount)*100,1).'%' : '0%'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot><tr style="background:#e9ecef; font-weight:700;"><td><strong>Total</strong></td><td style="text-align:right"><strong><?php echo array_sum(array_column($byCategory, 'cnt')); ?></strong></td><td style="text-align:right"><strong><?php echo formatCurrency($totalAmount); ?></strong></td><td style="text-align:right"><strong>100%</strong></td></tr></tfoot>
        </table>
    </div>
    <?php endif; ?>

    <div>
        <h5 style="font-size:14px; font-weight:700; margin-bottom:10px; padding:8px 12px; color:#fff; background:#1a2332; border-radius:4px;"><i class="fas fa-list me-2"></i>All Expenses</h5>
        <table class="table table-sm">
            <thead><tr><th>#</th><th>Date</th><th>Expense No</th><th>Category</th><th>Description</th><th style="text-align:right">Amount</th><th style="text-align:right">Paid</th><th>Status</th></tr></thead>
            <tbody>
                <?php if (count($expenses) > 0): $c = 1; foreach($expenses as $ex): ?>
                <tr>
                    <td><?php echo $c++; ?></td>
                    <td><?php echo formatDate($ex['expense_date']); ?></td>
                    <td><strong><?php echo htmlspecialchars($ex['expense_no']); ?></strong></td>
                    <td><?php echo htmlspecialchars($ex['category']); ?></td>
                    <td><?php echo htmlspecialchars($ex['description'] ?? ''); ?></td>
                    <td style="text-align:right; font-weight:700;"><?php echo formatCurrency($ex['amount']); ?></td>
                    <td style="text-align:right"><?php echo formatCurrency($ex['paid_amount']); ?></td>
                    <td><span class="badge bg-<?php echo $ex['payment_status']==='paid'?'success':($ex['payment_status']==='partial'?'warning':'danger'); ?>"><?php echo ucfirst($ex['payment_status']); ?></span></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="8" style="text-align:center; color:#999; padding:30px;">No expenses found for the selected period</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot><tr style="background:#e9ecef; font-weight:700;"><td colspan="5" style="text-align:right"><strong>TOTAL</strong></td><td style="text-align:right"><strong><?php echo formatCurrency($totalAmount); ?></strong></td><td style="text-align:right"><strong><?php echo formatCurrency($totalPaid); ?></strong></td><td></td></tr></tfoot>
        </table>
    </div>
    <div class="inv-footer"><p>Generated on <?php echo date('d-m-Y h:i A'); ?> | <?php echo CLIENT_NAME; ?></p></div>
</div>
</div>
<script>
function downloadPDF() {
    var opt = { margin: 0.5, filename: 'expense_report_<?php echo date('Y-m-d'); ?>.pdf', image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' } };
    if (typeof html2pdf !== 'undefined') { html2pdf().set(opt).from(document.getElementById('ledgerContent')).save(); } else { var s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js'; s.onload = function() { html2pdf().set(opt).from(document.getElementById('ledgerContent')).save(); }; document.head.appendChild(s); }
}
</script>
</body>
</html>
