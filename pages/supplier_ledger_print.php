<?php
require_once '../includes/database.php';
requireLogin();

$supplierId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($supplierId <= 0) { header('Location: local_suppliers.php'); exit; }

$supplier = getRow("SELECT s.*,
    COALESCE(SUM(lp.balance), 0) AS purchase_balance,
    COALESCE((SELECT SUM(sp.amount) FROM supplier_payments sp WHERE sp.supplier_id = s.id), 0) AS payments_made,
    (s.opening_balance + COALESCE(SUM(lp.balance), 0) - COALESCE((SELECT SUM(sp.amount) FROM supplier_payments sp WHERE sp.supplier_id = s.id), 0)) AS current_balance
    FROM local_suppliers s
    LEFT JOIN local_purchases lp ON lp.supplier_id = s.id
    WHERE s.id = ?
    GROUP BY s.id", 'i', [$supplierId]);

if (!$supplier) { header('Location: local_suppliers.php'); exit; }

$fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$toDate = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

$purchases = getRows("SELECT 
    'Purchase' as transaction_type,
    purchase_no as reference_no,
    purchase_date as transaction_date,
    total_amount as amount,
    paid_amount,
    balance,
    payment_status,
    status,
    invoice_no
    FROM local_purchases 
    WHERE supplier_id = ? 
    AND purchase_date BETWEEN ? AND ?
    ORDER BY purchase_date ASC, id ASC", 'iss', [$supplierId, $fromDate, $toDate]);

$runningBalance = $supplier['opening_balance'];

$company = getRow("SELECT * FROM company_settings LIMIT 1");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Supplier Ledger - <?php echo htmlspecialchars($supplier['supplier_name']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
* { font-family: 'Poppins', sans-serif; }
body { background: #f0f2f5; }

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
.ledger-a4 .summary-item { flex: 1; background: #f8f9fa; border-left: 4px solid #1a2332; padding: 10px 15px; border-radius: 0 6px 6px 0; }
.ledger-a4 .summary-item .label { font-size: 11px; color: #666; text-transform: uppercase; }
.ledger-a4 .summary-item .amount { font-size: 18px; font-weight: 700; color: #1a2332; }
.ledger-a4 .summary-item.danger .amount { color: #dc3545; }
.ledger-a4 .summary-item.success .amount { color: #198754; }
.ledger-a4 table { width: 100%; font-size: 12px; }
.ledger-a4 table thead th { background: #1a2332; color: #fff; padding: 8px 10px; font-weight: 600; white-space: nowrap; }
.ledger-a4 table tbody td { padding: 7px 10px; border-bottom: 1px solid #eee; }
.ledger-a4 table tfoot td, .ledger-a4 table tfoot th { padding: 8px 10px; font-weight: 700; background: #e9ecef; }
.ledger-a4 .inv-footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 2px solid #1a2332; font-size: 12px; color: #666; }

@media print {
    body { background: #fff !important; margin: 0; padding: 0; }
    .action-buttons { display: none !important; }
    .ledger-wrapper { margin: 0; padding: 10px; }
    .ledger-a4 { border: none; padding: 15px; max-width: 100%; box-shadow: none; }
    @page { size: A4 landscape; margin: 10mm; }
}
</style>
</head>
<body>

<div class="action-buttons">
    <a href="supplier_ledger.php?id=<?php echo $supplierId; ?>&from_date=<?php echo $fromDate; ?>&to_date=<?php echo $toDate; ?>" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <span class="text-white fw-bold me-2"><i class="fas fa-book me-1"></i>Supplier Ledger</span>
    <button class="btn btn-light btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
    <button class="btn btn-success btn-sm" onclick="downloadPDF()"><i class="fas fa-download me-1"></i>Download PDF</button>
    <span class="text-white ms-auto"><?php echo htmlspecialchars($supplier['supplier_name']); ?></span>
</div>

<div class="ledger-wrapper">
<div class="ledger-a4" id="ledgerContent">
    <div class="inv-header">
        <h3>SUPPLIER LEDGER</h3>
        <p><?php echo htmlspecialchars($company['company_name'] ?? 'ERP System'); ?></p>
        <?php if ($company['address'] ?? null): ?><p style="margin:2px 0 0;font-size:12px;"><?php echo htmlspecialchars($company['address']); ?></p><?php endif; ?>
        <?php if ($company['phone'] ?? null): ?><p style="margin:2px 0 0;font-size:12px;">Ph: <?php echo htmlspecialchars($company['phone']); ?></p><?php endif; ?>
    </div>

    <div class="inv-meta">
        <div>
            <strong>Supplier:</strong> <?php echo htmlspecialchars($supplier['supplier_name']); ?><br>
            <?php if ($supplier['company_name'] ?? null): ?><strong>Company:</strong> <?php echo htmlspecialchars($supplier['company_name']); ?><br><?php endif; ?>
            <?php if ($supplier['phone'] ?? null): ?><strong>Phone:</strong> <?php echo htmlspecialchars($supplier['phone']); ?><?php endif; ?>
        </div>
        <div style="text-align:right">
            <strong>Period:</strong> <?php echo date('d-m-Y', strtotime($fromDate)); ?> to <?php echo date('d-m-Y', strtotime($toDate)); ?><br>
            <strong>Printed:</strong> <?php echo date('d-m-Y h:i A'); ?>
        </div>
    </div>

    <div class="summary-box">
        <div class="summary-item">
            <div class="label">Opening Balance</div>
            <div class="amount"><?php echo formatCurrency($supplier['opening_balance']); ?></div>
        </div>
        <div class="summary-item">
            <div class="label">Current Balance</div>
            <div class="amount"><?php echo formatCurrency($supplier['current_balance']); ?></div>
        </div>
        <div class="summary-item success">
            <div class="label">Total Purchases</div>
            <div class="amount"><?php echo formatCurrency(array_sum(array_column($purchases, 'amount'))); ?></div>
        </div>
        <div class="summary-item">
            <div class="label">Total Paid</div>
            <div class="amount"><?php echo formatCurrency(array_sum(array_column($purchases, 'paid_amount'))); ?></div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Reference</th>
                <th>Invoice No</th>
                <th>Type</th>
                <th style="text-align:right">Amount</th>
                <th style="text-align:right">Paid</th>
                <th style="text-align:right">Balance</th>
                <th style="text-align:right">Running Balance</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <tr style="background:#f0f0f0;">
                <td></td>
                <td><?php echo date('d-m-Y'); ?></td>
                <td><strong>Opening Balance</strong></td>
                <td></td>
                <td></td>
                <td style="text-align:right"></td>
                <td style="text-align:right"></td>
                <td style="text-align:right"></td>
                <td style="text-align:right"><strong><?php echo formatCurrency($supplier['opening_balance']); ?></strong></td>
                <td></td>
            </tr>
            <?php if (count($purchases) > 0): ?>
                <?php $counter = 1; ?>
                <?php foreach ($purchases as $purchase): ?>
                    <?php $runningBalance += $purchase['balance']; ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
                        <td><?php echo date('d-m-Y', strtotime($purchase['transaction_date'])); ?></td>
                        <td><strong><?php echo htmlspecialchars($purchase['reference_no']); ?></strong></td>
                        <td><?php echo htmlspecialchars($purchase['invoice_no']); ?></td>
                        <td><span class="badge bg-primary">Purchase</span></td>
                        <td style="text-align:right"><?php echo formatCurrency($purchase['amount']); ?></td>
                        <td style="text-align:right"><?php echo formatCurrency($purchase['paid_amount']); ?></td>
                        <td style="text-align:right"><?php echo formatCurrency($purchase['balance']); ?></td>
                        <td style="text-align:right; color: <?php echo $runningBalance > 0 ? '#dc3545' : '#198754'; ?>; font-weight:700;">
                            <?php echo formatCurrency($runningBalance); ?>
                        </td>
                        <td>
                            <span class="badge badge-status badge-<?php echo strtolower($purchase['payment_status']); ?>">
                                <?php echo ucfirst($purchase['payment_status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10" style="text-align:center; color:#999; padding:20px;">No transactions found for this period</td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="5" style="text-align:right">TOTAL</th>
                <th style="text-align:right"><?php echo formatCurrency(array_sum(array_column($purchases, 'amount'))); ?></th>
                <th style="text-align:right"><?php echo formatCurrency(array_sum(array_column($purchases, 'paid_amount'))); ?></th>
                <th style="text-align:right"><?php echo formatCurrency(array_sum(array_column($purchases, 'balance'))); ?></th>
                <th style="text-align:right; color: <?php echo $runningBalance > 0 ? '#dc3545' : '#198754'; ?>;">
                    <?php echo formatCurrency($runningBalance); ?>
                </th>
                <th></th>
            </tr>
        </tfoot>
    </table>

    <div class="inv-footer">
        <p>Generated on <?php echo date('d-m-Y h:i A'); ?> | <?php echo htmlspecialchars($company['company_name'] ?? 'ERP System'); ?></p>
    </div>
</div>
</div>

<script>
function downloadPDF() {
    document.getElementById('ledgerContent').style.display = 'block';
    var opt = {
        margin: 0.5,
        filename: 'supplier_ledger_<?php echo htmlspecialchars($supplier["supplier_name"]); ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    if (typeof html2pdf !== 'undefined') {
        html2pdf().set(opt).from(document.getElementById('ledgerContent')).save();
    } else {
        var script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
        script.onload = function() { html2pdf().set(opt).from(document.getElementById('ledgerContent')).save(); };
        document.head.appendChild(script);
    }
}
</script>
</body>
</html>