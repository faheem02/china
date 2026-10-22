<?php
require_once '../includes/database.php';
requireLogin();

// Fetch all local suppliers with balances
$sql = "SELECT s.*,
    COALESCE(SUM(lp.balance), 0) AS purchase_balance,
    COALESCE((SELECT SUM(sp.amount) FROM supplier_payments sp WHERE sp.supplier_id = s.id), 0) AS payments_made,
    (s.opening_balance + COALESCE(SUM(lp.balance), 0) - COALESCE((SELECT SUM(sp.amount) FROM supplier_payments sp WHERE sp.supplier_id = s.id), 0)) AS current_balance
    FROM local_suppliers s
    LEFT JOIN local_purchases lp ON lp.supplier_id = s.id
    GROUP BY s.id
    ORDER BY s.id DESC";
$suppliers = getRows($sql);

$totalSuppliers = count($suppliers);
$totalBalance = 0;
$totalPayments = 0;
$activeCount = 0;

foreach ($suppliers as $s) {
    $totalBalance += (float)$s['current_balance'];
    $totalPayments += (float)$s['payments_made'];
    if (($s['status'] ?? '') === 'active') {
        $activeCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Local Suppliers List - Print</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* { font-family: 'Poppins', sans-serif; }
body { background: #f0f2f5; margin: 0; padding: 0; color: #212529; }
.action-buttons { position: fixed; top: 0; left: 0; right: 0; z-index: 9999; background: #1a2332; padding: 10px 20px; display: flex; gap: 10px; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
.action-buttons .btn { font-size: 14px; }
.print-wrapper { margin-top: 70px; display: flex; justify-content: center; padding: 20px; }
.print-a4 { max-width: 1100px; width: 100%; background: #fff; padding: 40px; border: 1px solid #ddd; }
.print-a4 .inv-title { text-align: center; background: #f0f0f0; padding: 8px; border: 1px solid #ccc; margin-bottom: 20px; font-weight: 700; font-size: 14px; color: #1a2332; }
.print-a4 .inv-meta { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 13px; }
.print-a4 .inv-meta strong { color: #1a2332; }
.print-a4 .summary-box { display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; }
.print-a4 .summary-item { flex: 1; min-width: 180px; background: #f8f9fa; border-left: 4px solid #1a2332; padding: 12px 15px; border-radius: 0 6px 6px 0; }
.print-a4 .summary-item .label { font-size: 11px; color: #666; text-transform: uppercase; font-weight: 600; }
.print-a4 .summary-item .amount { font-size: 18px; font-weight: 700; color: #1a2332; margin-top: 4px; }
.print-a4 .summary-item.success { border-left-color: #198754; } .print-a4 .summary-item.success .amount { color: #198754; }
.print-a4 .summary-item.danger { border-left-color: #dc3545; } .print-a4 .summary-item.danger .amount { color: #dc3545; }
.print-a4 .summary-item.info { border-left-color: #0dcaf0; } .print-a4 .summary-item.info .amount { color: #087990; }
.print-a4 table { width: 100%; font-size: 12px; border-collapse: collapse; }
.print-a4 table thead th { background: #1a2332; color: #fff; padding: 9px 10px; font-weight: 600; white-space: nowrap; font-size: 12px; }
.print-a4 table tbody td { padding: 8px 10px; border-bottom: 1px solid #eee; font-size: 12px; vertical-align: middle; }
.print-a4 table tbody tr:nth-child(even) { background: #f9f9f9; }
.print-a4 table tfoot td, .print-a4 table tfoot th { padding: 9px 10px; font-weight: 700; background: #e9ecef; border-top: 2px solid #1a2332; font-size: 12px; }
.print-a4 .inv-footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 2px solid #1a2332; font-size: 12px; color: #666; }
@media print {
    body { background: #fff !important; margin: 0; padding: 0; }
    .action-buttons { display: none !important; }
    .print-wrapper { margin: 0; padding: 10px; }
    .print-a4 { border: none; padding: 10px; max-width: 100%; }
    @page { size: A4 landscape; margin: 10mm; }
}
</style>
</head>
<body>
<div class="action-buttons">
    <a href="local_suppliers.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <span class="text-white fw-bold me-2"><i class="fas fa-truck me-1"></i>Local Suppliers List</span>
    <button class="btn btn-light btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
    <button class="btn btn-success btn-sm" onclick="downloadPDF()"><i class="fas fa-download me-1"></i>Download PDF</button>
</div>

<div class="print-wrapper">
<div class="print-a4" id="printContent">
    <?php include '../includes/print_branding.php'; ?>
    <div class="inv-meta">
        <div><strong>Total Suppliers:</strong> <?php echo $totalSuppliers; ?></div>
        <div style="text-align:right"><strong>Printed:</strong> <?php echo date('d-m-Y h:i A'); ?><br><strong>User:</strong> <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin'; ?></div>
    </div>
    <div class="inv-title">LOCAL SUPPLIERS LIST & BALANCES REPORT</div>
    
    <div class="summary-box">
        <div class="summary-item">
            <div class="label">Total Suppliers</div>
            <div class="amount"><?php echo $totalSuppliers; ?></div>
        </div>
        <div class="summary-item danger">
            <div class="label">Total Payable Balance</div>
            <div class="amount">PKR <?php echo formatCurrency($totalBalance); ?></div>
        </div>
        <div class="summary-item success">
            <div class="label">Total Payments Made</div>
            <div class="amount">PKR <?php echo formatCurrency($totalPayments); ?></div>
        </div>
        <div class="summary-item info">
            <div class="label">Active Suppliers</div>
            <div class="amount"><?php echo $activeCount; ?></div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 40px;">#</th>
                <th>Supplier Name</th>
                <th>Company</th>
                <th>Phone</th>
                <th>CNIC</th>
                <th>NTN</th>
                <th>City</th>
                <th style="text-align:right">Balance (PKR)</th>
                <th style="text-align:center; width: 70px;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($suppliers) > 0): $c = 1; foreach ($suppliers as $sup): ?>
            <tr>
                <td><?php echo $c++; ?></td>
                <td><strong><?php echo htmlspecialchars($sup['supplier_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($sup['company_name'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($sup['phone'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($sup['cnic'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($sup['ntn'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($sup['city'] ?: '-'); ?></td>
                <td style="text-align:right; font-weight:700;" class="<?php echo $sup['current_balance'] > 0 ? 'text-danger' : ($sup['current_balance'] < 0 ? 'text-success' : 'text-muted'); ?>">
                    <?php echo formatCurrency($sup['current_balance']); ?>
                    <small style="font-size:10px;display:block;">
                        (<?php echo ucfirst($sup['opening_balance_type'] ?? 'payable'); ?>)
                    </small>
                </td>
                <td style="text-align:center;">
                    <span class="badge bg-<?php echo ($sup['status'] ?? 'active') === 'active' ? 'success' : 'secondary'; ?>" style="font-size:10px;">
                        <?php echo ucfirst($sup['status'] ?? 'active'); ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="9" style="text-align:center; color:#999; padding:30px;">No local suppliers found</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="7" style="text-align:right">TOTAL BALANCE</th>
                <th style="text-align:right" class="<?php echo $totalBalance > 0 ? 'text-danger' : 'text-success'; ?>">
                    PKR <?php echo formatCurrency($totalBalance); ?>
                </th>
                <th></th>
            </tr>
        </tfoot>
    </table>
    
    <div class="inv-footer"><p>Generated on <?php echo date('d-m-Y h:i A'); ?> | <?php echo CLIENT_NAME; ?></p></div>
</div>
</div>

<script>
function downloadPDF() {
    var opt = { margin: 0.4, filename: 'local_suppliers_list_<?php echo date('Y-m-d'); ?>.pdf', image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' } };
    if (typeof html2pdf !== 'undefined') { html2pdf().set(opt).from(document.getElementById('printContent')).save(); } else { var s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js'; s.onload = function() { html2pdf().set(opt).from(document.getElementById('printContent')).save(); }; document.head.appendChild(s); }
}
</script>
</body>
</html>
