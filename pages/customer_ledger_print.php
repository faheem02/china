<?php
require_once '../includes/database.php';
requireLogin();

$customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($customerId <= 0) { header('Location: customers.php'); exit; }

$customer = getRow("SELECT * FROM customers WHERE id = ?", 'i', [$customerId]);
if (!$customer) { header('Location: customers.php'); exit; }

$sales = getRows("SELECT id, sale_no as ref_no, sale_date as trans_date, total_amount as debit, paid_amount as credit, 'Sale' as trans_type FROM sales WHERE customer_id = ? ORDER BY sale_date, id", 'i', [$customerId]);

$receipts = getRows("SELECT id, receipt_no as ref_no, payment_date as trans_date, 0 as debit, amount as credit, 'Payment' as trans_type FROM customer_receipts WHERE customer_id = ? ORDER BY payment_date, id", 'i', [$customerId]);

$totalSalesAmt = 0;
$totalPaidAmt = 0;
foreach ($sales as $s) { $totalSalesAmt += $s['debit']; }
foreach ($receipts as $r) { $totalPaidAmt += $r['credit']; }

$effectiveOpening = $customer['opening_balance'];
if (($customer['opening_balance_type'] ?? 'receivable') === 'payable') {
    $effectiveOpening = -$customer['opening_balance'];
}

$ledger = array_merge($sales, $receipts);
usort($ledger, function($a, $b) {
    $cmp = strcmp($a['trans_date'], $b['trans_date']);
    if ($cmp !== 0) return $cmp;
    return $a['id'] - $b['id'];
});

$closingBalance = $effectiveOpening + $totalSalesAmt - $totalPaidAmt;

$company = getRow("SELECT * FROM company_settings LIMIT 1");
$fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$toDate = isset($_GET['to_date']) ? $_GET['to_date'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Customer Ledger - <?php echo htmlspecialchars($customer['customer_name']); ?></title>
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
    <a href="customer_ledger.php?id=<?php echo $customerId; ?>" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <span class="text-white fw-bold me-2"><i class="fas fa-book me-1"></i>Customer Ledger</span>
    <button class="btn btn-light btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
    <button class="btn btn-success btn-sm" onclick="downloadPDF()"><i class="fas fa-download me-1"></i>Download PDF</button>
    <span class="text-white ms-auto"><?php echo htmlspecialchars($customer['customer_name']); ?></span>
</div>

<div class="ledger-wrapper">
<div class="ledger-a4" id="ledgerContent">
    <div class="inv-header">
        <h3>CUSTOMER LEDGER</h3>
        <p><?php echo htmlspecialchars($company['company_name'] ?? 'ERP System'); ?></p>
        <?php if ($company['address'] ?? null): ?><p style="margin:2px 0 0;font-size:12px;"><?php echo htmlspecialchars($company['address']); ?></p><?php endif; ?>
        <?php if ($company['phone'] ?? null): ?><p style="margin:2px 0 0;font-size:12px;">Ph: <?php echo htmlspecialchars($company['phone']); ?></p><?php endif; ?>
    </div>

    <div class="inv-meta">
        <div>
            <strong>Customer:</strong> <?php echo htmlspecialchars($customer['customer_name']); ?><br>
            <?php if ($customer['phone'] ?? null): ?><strong>Phone:</strong> <?php echo htmlspecialchars($customer['phone']); ?><br><?php endif; ?>
            <?php if ($customer['company_name'] ?? null): ?><strong>Company:</strong> <?php echo htmlspecialchars($customer['company_name']); ?><?php endif; ?>
        </div>
        <div style="text-align:right">
            <strong>Date:</strong> <?php echo date('d-m-Y'); ?><br>
            <?php if ($fromDate || $toDate): ?>
                <strong>Period:</strong> <?php echo $fromDate ? date('d-m-Y', strtotime($fromDate)) : 'Start'; ?> to <?php echo $toDate ? date('d-m-Y', strtotime($toDate)) : 'End'; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="summary-box">
        <div class="summary-item">
            <div class="label">Opening Balance</div>
            <div class="amount"><?php echo formatCurrency($effectiveOpening); ?></div>
            <small class="text-muted">(<?php echo ucfirst($customer['opening_balance_type'] ?? 'receivable'); ?>)</small>
        </div>
        <div class="summary-item">
            <div class="label">Total Sales</div>
            <div class="amount"><?php echo formatCurrency($totalSalesAmt); ?></div>
        </div>
        <div class="summary-item success">
            <div class="label">Total Payments</div>
            <div class="amount"><?php echo formatCurrency($totalPaidAmt); ?></div>
        </div>
        <div class="summary-item <?php echo $closingBalance > 0 ? 'danger' : ($closingBalance < 0 ? 'success' : ''); ?>">
            <div class="label">Closing Balance</div>
            <div class="amount"><?php echo formatCurrency($closingBalance); ?></div>
            <small class="text-muted">(<?php echo $closingBalance > 0 ? 'Receivable' : ($closingBalance < 0 ? 'Payable' : 'Settled'); ?>)</small>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Reference #</th>
                <th>Type</th>
                <th style="text-align:right">Debit (PKR)</th>
                <th style="text-align:right">Credit (PKR)</th>
                <th style="text-align:right">Balance (PKR)</th>
            </tr>
        </thead>
        <tbody>
            <tr style="background:#f0f0f0;">
                <td></td>
                <td><?php echo date('d-m-Y'); ?></td>
                <td><strong>Opening Balance</strong></td>
                <td><small>(<?php echo ucfirst($customer['opening_balance_type'] ?? 'receivable'); ?>)</small></td>
                <td style="text-align:right"></td>
                <td style="text-align:right"></td>
                <td style="text-align:right"><strong><?php echo formatCurrency($effectiveOpening); ?></strong></td>
            </tr>
            <?php $runningBalance = $effectiveOpening; ?>
            <?php $counter = 1; ?>
            <?php foreach ($ledger as $entry): ?>
                <?php
                if ($entry['trans_type'] == 'Sale') {
                    $runningBalance += $entry['debit'];
                } else {
                    $runningBalance -= $entry['credit'];
                }
                ?>
                <tr>
                    <td><?php echo $counter++; ?></td>
                    <td><?php echo date('d-m-Y', strtotime($entry['trans_date'])); ?></td>
                    <td><strong><?php echo htmlspecialchars($entry['ref_no']); ?></strong></td>
                    <td>
                        <span class="badge bg-<?php echo $entry['trans_type'] == 'Sale' ? 'primary' : 'success'; ?>">
                            <?php echo $entry['trans_type']; ?>
                        </span>
                    </td>
                    <td style="text-align:right"><?php echo $entry['debit'] > 0 ? formatCurrency($entry['debit']) : '-'; ?></td>
                    <td style="text-align:right"><?php echo $entry['credit'] > 0 ? formatCurrency($entry['credit']) : '-'; ?></td>
                    <td style="text-align:right; color: <?php echo $runningBalance > 0 ? '#dc3545' : '#198754'; ?>; font-weight:700;">
                        <?php echo formatCurrency($runningBalance); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align:right"><strong>CLOSING BALANCE</strong></td>
                <td style="text-align:right"><strong><?php echo formatCurrency($totalSalesAmt); ?></strong></td>
                <td style="text-align:right"><strong><?php echo formatCurrency($totalPaidAmt); ?></strong></td>
                <td style="text-align:right; color: <?php echo $closingBalance > 0 ? '#dc3545' : '#198754'; ?>;">
                    <strong><?php echo formatCurrency($closingBalance); ?></strong>
                </td>
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
        filename: 'customer_ledger_<?php echo htmlspecialchars($customer["customer_name"]); ?>.pdf',
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