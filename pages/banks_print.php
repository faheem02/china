<?php
require_once '../includes/database.php';
requireLogin();

$banks = getRows("SELECT * FROM banks ORDER BY id DESC");
$totalBanks = count($banks);
$totalOpening = 0;
$totalCurrent = 0;
foreach ($banks as $b) {
    $totalOpening += (float)$b['opening_balance'];
    $totalCurrent += (float)$b['current_balance'];
}
$company = getRow("SELECT * FROM company_settings LIMIT 1");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Banks - Print</title>
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
.ledger-a4 .summary-item .amount { font-size: 20px; font-weight: 700; color: #1a2332; margin-top: 4px; }
.ledger-a4 .summary-item.primary { border-left-color: #0d6efd; } .ledger-a4 .summary-item.primary .amount { color: #0d6efd; }
.ledger-a4 table { width: 100%; font-size: 12px; border-collapse: collapse; }
.ledger-a4 table thead th { background: #1a2332; color: #fff; padding: 10px 12px; font-weight: 600; white-space: nowrap; }
.ledger-a4 table tbody td { padding: 8px 12px; border-bottom: 1px solid #eee; }
.ledger-a4 table tbody tr:nth-child(even) { background: #f9f9f9; }
.ledger-a4 table tfoot td, .ledger-a4 table tfoot th { padding: 10px 12px; font-weight: 700; background: #e9ecef; border-top: 2px solid #1a2332; }
.ledger-a4 .inv-footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 2px solid #1a2332; font-size: 12px; color: #666; }
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
    <a href="banks.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <span class="text-white fw-bold me-2"><i class="fas fa-university me-1"></i>Banks</span>
    <button class="btn btn-light btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
    <button class="btn btn-success btn-sm" onclick="downloadPDF()"><i class="fas fa-download me-1"></i>Download PDF</button>
</div>
<div class="ledger-wrapper">
<div class="ledger-a4" id="ledgerContent">
    <div class="inv-header">
        <h3>BANKS LISTING</h3>
        <p><?php echo htmlspecialchars($company['company_name'] ?? 'ERP System'); ?></p>
        <?php if ($company['address'] ?? null): ?><p style="margin:2px 0 0;font-size:12px;"><?php echo htmlspecialchars($company['address']); ?></p><?php endif; ?>
        <?php if ($company['phone'] ?? null): ?><p style="margin:2px 0 0;font-size:12px;">Ph: <?php echo htmlspecialchars($company['phone']); ?></p><?php endif; ?>
    </div>
    <div class="inv-meta">
        <div><strong>Total Banks:</strong> <?php echo $totalBanks; ?></div>
        <div style="text-align:right"><strong>Printed:</strong> <?php echo date('d-m-Y h:i A'); ?><br><strong>User:</strong> <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin'; ?></div>
    </div>
    <div class="inv-title">BANKS SUMMARY</div>
    <div class="summary-box">
        <div class="summary-item primary"><div class="label">Total Banks</div><div class="amount"><?php echo $totalBanks; ?></div></div>
        <div class="summary-item"><div class="label">Total Opening Balance</div><div class="amount"><?php echo formatCurrency($totalOpening); ?></div></div>
        <div class="summary-item <?php echo $totalCurrent >= 0 ? 'success' : 'danger'; ?>"><div class="label">Total Current Balance</div><div class="amount"><?php echo formatCurrency($totalCurrent); ?></div></div>
    </div>
    <table>
        <thead>
            <tr><th>#</th><th>Bank Name</th><th>Account No</th><th>Account Type</th><th style="text-align:right">Opening Balance</th><th style="text-align:right">Current Balance</th></tr>
        </thead>
        <tbody>
            <?php if (count($banks) > 0): foreach ($banks as $i => $bank): ?>
            <tr>
                <td><?php echo $i + 1; ?></td>
                <td><strong><?php echo htmlspecialchars($bank['bank_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($bank['account_number']); ?></td>
                <td><?php echo ucfirst($bank['account_type']); ?></td>
                <td style="text-align:right"><?php echo formatCurrency($bank['opening_balance']); ?></td>
                <td style="text-align:right; font-weight:700;" class="<?php echo $bank['current_balance'] >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatCurrency($bank['current_balance']); ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" style="text-align:center; color:#999; padding:30px;">No banks found</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr><th colspan="4" style="text-align:right">TOTAL</th><th style="text-align:right"><?php echo formatCurrency($totalOpening); ?></th><th style="text-align:right; font-weight:700;" class="<?php echo $totalCurrent >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatCurrency($totalCurrent); ?></th></tr>
        </tfoot>
    </table>
    <div class="inv-footer"><p>Generated on <?php echo date('d-m-Y h:i A'); ?> | <?php echo htmlspecialchars($company['company_name'] ?? 'ERP System'); ?></p></div>
</div>
</div>
<script>
function downloadPDF() {
    var opt = { margin: 0.5, filename: 'banks_<?php echo date('Y-m-d'); ?>.pdf', image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' } };
    if (typeof html2pdf !== 'undefined') { html2pdf().set(opt).from(document.getElementById('ledgerContent')).save(); } else { var s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js'; s.onload = function() { html2pdf().set(opt).from(document.getElementById('ledgerContent')).save(); }; document.head.appendChild(s); }
}
</script>
</body>
</html>
