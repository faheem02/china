<?php
require_once '../includes/database.php';
requireLogin();

$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');
$company = getRow("SELECT * FROM company_settings LIMIT 1");

$salesCashReceived = (float)getRow("SELECT COALESCE(SUM(amount),0) AS total FROM customer_receipts WHERE payment_method='cash' AND payment_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate])['total'];
$partyReceivedCash = (float)getRow("SELECT COALESCE(SUM(amount),0) AS total FROM party_transactions WHERE type='received' AND payment_method='cash' AND transaction_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate])['total'];
$supplierPaidCash = (float)getRow("SELECT COALESCE(SUM(amount),0) AS total FROM supplier_payments WHERE payment_type='cash' AND payment_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate])['total'];
$expensePaid = (float)getRow("SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE expense_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate])['total'];
$empPaidCash = (float)getRow("SELECT COALESCE(SUM(amount),0) AS total FROM employee_payments WHERE payment_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate])['total'];
$partyPaidCash = (float)getRow("SELECT COALESCE(SUM(amount),0) AS total FROM party_transactions WHERE type='paid' AND payment_method='cash' AND transaction_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate])['total'];
$salesBankReceived = (float)getRow("SELECT COALESCE(SUM(amount),0) AS total FROM customer_receipts WHERE payment_method IN ('bank_transfer','cheque') AND payment_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate])['total'];
$partyReceivedBank = (float)getRow("SELECT COALESCE(SUM(amount),0) AS total FROM party_transactions WHERE type='received' AND payment_method IN ('bank_transfer','cheque') AND transaction_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate])['total'];
$supplierPaidBank = (float)getRow("SELECT COALESCE(SUM(amount),0) AS total FROM supplier_payments WHERE payment_type IN ('bank_transfer','cheque') AND payment_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate])['total'];
$partyPaidBank = (float)getRow("SELECT COALESCE(SUM(amount),0) AS total FROM party_transactions WHERE type='paid' AND payment_method IN ('bank_transfer','cheque') AND transaction_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate])['total'];

$totalCashInflow = $salesCashReceived + $partyReceivedCash;
$totalCashOutflow = $supplierPaidCash + $expensePaid + $empPaidCash + $partyPaidCash;
$netCashFlow = $totalCashInflow - $totalCashOutflow;
$totalBankInflow = $salesBankReceived + $partyReceivedBank;
$totalBankOutflow = $supplierPaidBank + $partyPaidBank;
$netBankFlow = $totalBankInflow - $totalBankOutflow;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cash Flow Statement - Print</title>
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
.cf-section h5 { font-size: 14px; font-weight: 700; margin-bottom: 10px; padding: 8px 12px; color: #fff; border-radius: 4px; }
.cf-section.inflow h5 { background: #198754; }
.cf-section.outflow h5 { background: #dc3545; }
.cf-sub-header { font-size: 12px; font-weight: 700; color: #666; padding: 6px 12px; background: #f8f9fa; }
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
    <a href="reports_cashflow.php?from_date=<?php echo $fromDate; ?>&to_date=<?php echo $toDate; ?>" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <span class="text-white fw-bold me-2"><i class="fas fa-money-bill me-1"></i>Cash Flow Statement</span>
    <button class="btn btn-light btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
    <button class="btn btn-success btn-sm" onclick="downloadPDF()"><i class="fas fa-download me-1"></i>Download PDF</button>
</div>
<div class="ledger-wrapper">
<div class="ledger-a4" id="ledgerContent">
    <div class="inv-header">
        <h3>CASH FLOW STATEMENT</h3>
        <p><?php echo htmlspecialchars($company['company_name'] ?? 'ERP System'); ?></p>
        <?php if ($company['address'] ?? null): ?><p style="margin:2px 0 0;font-size:12px;"><?php echo htmlspecialchars($company['address']); ?></p><?php endif; ?>
        <?php if ($company['phone'] ?? null): ?><p style="margin:2px 0 0;font-size:12px;">Ph: <?php echo htmlspecialchars($company['phone']); ?></p><?php endif; ?>
    </div>
    <div class="inv-meta">
        <div><strong>Period:</strong> <?php echo date('d-m-Y', strtotime($fromDate)); ?> to <?php echo date('d-m-Y', strtotime($toDate)); ?></div>
        <div style="text-align:right"><strong>Printed:</strong> <?php echo date('d-m-Y h:i A'); ?><br><strong>User:</strong> <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin'; ?></div>
    </div>
    <div class="inv-title">CASH FLOW STATEMENT | <?php echo date('d-m-Y', strtotime($fromDate)); ?> to <?php echo date('d-m-Y', strtotime($toDate)); ?></div>
    <div class="summary-box">
        <div class="summary-item <?php echo $netCashFlow >= 0 ? 'success' : 'danger'; ?>"><div class="label">Net Cash Flow</div><div class="amount"><?php echo formatCurrency($netCashFlow); ?></div></div>
        <div class="summary-item success"><div class="label">Total Cash In</div><div class="amount"><?php echo formatCurrency($totalCashInflow); ?></div></div>
        <div class="summary-item danger"><div class="label">Total Cash Out</div><div class="amount"><?php echo formatCurrency($totalCashOutflow); ?></div></div>
        <div class="summary-item primary"><div class="label">Net Bank Flow</div><div class="amount"><?php echo formatCurrency($netBankFlow); ?></div></div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="cf-section inflow">
                <h5><i class="fas fa-arrow-down me-2"></i>Cash & Bank Inflows</h5>
                <table class="table table-sm">
                    <thead><tr><th>Source</th><th style="text-align:right">Amount (PKR)</th></tr></thead>
                    <tbody>
                        <tr><td colspan="2" class="cf-sub-header">Cash Inflows</td></tr>
                        <tr><td>Customer Receipts (Cash)</td><td class="text-end fw-bold text-success"><?php echo formatCurrency($salesCashReceived); ?></td></tr>
                        <tr><td>Party Received (Cash)</td><td class="text-end fw-bold text-success"><?php echo formatCurrency($partyReceivedCash); ?></td></tr>
                        <tr style="background:#d4edda"><td><strong>Total Cash In</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalCashInflow); ?></strong></td></tr>
                        <tr><td colspan="2" class="cf-sub-header">Bank Inflows</td></tr>
                        <tr><td>Customer Receipts (Bank)</td><td class="text-end fw-bold text-success"><?php echo formatCurrency($salesBankReceived); ?></td></tr>
                        <tr><td>Party Received (Bank)</td><td class="text-end fw-bold text-success"><?php echo formatCurrency($partyReceivedBank); ?></td></tr>
                        <tr style="background:#d4edda"><td><strong>Total Bank In</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalBankInflow); ?></strong></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-md-6">
            <div class="cf-section outflow">
                <h5><i class="fas fa-arrow-up me-2"></i>Cash & Bank Outflows</h5>
                <table class="table table-sm">
                    <thead><tr><th>Destination</th><th style="text-align:right">Amount (PKR)</th></tr></thead>
                    <tbody>
                        <tr><td colspan="2" class="cf-sub-header">Cash Outflows</td></tr>
                        <tr><td>Supplier Payments (Cash)</td><td class="text-end fw-bold text-danger"><?php echo formatCurrency($supplierPaidCash); ?></td></tr>
                        <tr><td>Expenses Paid</td><td class="text-end fw-bold text-danger"><?php echo formatCurrency($expensePaid); ?></td></tr>
                        <tr><td>Employee Payments</td><td class="text-end fw-bold text-danger"><?php echo formatCurrency($empPaidCash); ?></td></tr>
                        <tr><td>Party Paid (Cash)</td><td class="text-end fw-bold text-danger"><?php echo formatCurrency($partyPaidCash); ?></td></tr>
                        <tr style="background:#f8d7da"><td><strong>Total Cash Out</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalCashOutflow); ?></strong></td></tr>
                        <tr><td colspan="2" class="cf-sub-header">Bank Outflows</td></tr>
                        <tr><td>Supplier Payments (Bank)</td><td class="text-end fw-bold text-danger"><?php echo formatCurrency($supplierPaidBank); ?></td></tr>
                        <tr><td>Party Paid (Bank)</td><td class="text-end fw-bold text-danger"><?php echo formatCurrency($partyPaidBank); ?></td></tr>
                        <tr style="background:#f8d7da"><td><strong>Total Bank Out</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalBankOutflow); ?></strong></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="inv-footer"><p>Generated on <?php echo date('d-m-Y h:i A'); ?> | <?php echo htmlspecialchars($company['company_name'] ?? 'ERP System'); ?></p></div>
</div>
</div>
<script>
function downloadPDF() {
    var opt = { margin: 0.5, filename: 'cash_flow_<?php echo date('Y-m-d'); ?>.pdf', image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' } };
    if (typeof html2pdf !== 'undefined') { html2pdf().set(opt).from(document.getElementById('ledgerContent')).save(); } else { var s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js'; s.onload = function() { html2pdf().set(opt).from(document.getElementById('ledgerContent')).save(); }; document.head.appendChild(s); }
}
</script>
</body>
</html>
