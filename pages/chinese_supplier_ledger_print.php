<?php
require_once '../includes/database.php';
requireLogin();

$supplierId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($supplierId <= 0) { header('Location: chinese_suppliers.php'); exit; }

$supplier = getRow("SELECT s.*, c.currency_code, c.currency_name, c.symbol, c.exchange_rate,
    COALESCE(SUM(ip.balance_cny), 0) AS purchase_balance_cny,
    COALESCE((SELECT SUM(cp.amount_cny) FROM chinese_supplier_payments cp WHERE cp.supplier_id = s.id), 0) AS payments_made_cny,
    (s.opening_balance + COALESCE(SUM(ip.balance_cny), 0) - COALESCE((SELECT SUM(cp.amount_cny) FROM chinese_supplier_payments cp WHERE cp.supplier_id = s.id), 0)) AS current_balance
    FROM chinese_suppliers s
    LEFT JOIN currencies c ON s.currency_id = c.id
    LEFT JOIN import_purchases ip ON ip.supplier_id = s.id
    WHERE s.id = ?
    GROUP BY s.id", 'i', [$supplierId]);

if (!$supplier) { header('Location: chinese_suppliers.php'); exit; }

$fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$toDate = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

$purchases = getRows("SELECT 
    'Purchase' as transaction_type,
    purchase_no as reference_no,
    purchase_date as transaction_date,
    total_cny as amount_cny,
    total_pkr as amount_pkr,
    paid_amount_cny as paid_cny,
    paid_amount_pkr as paid_pkr,
    balance_cny as balance_cny,
    balance_pkr as balance_pkr,
    payment_status,
    status,
    invoice_no,
    exchange_rate
    FROM import_purchases 
    WHERE supplier_id = ? 
    AND purchase_date BETWEEN ? AND ?
    ORDER BY purchase_date ASC, id ASC", 'iss', [$supplierId, $fromDate, $toDate]);

$payments = getRows("SELECT 
    'Payment' as transaction_type,
    payment_no as reference_no,
    payment_date as transaction_date,
    amount_cny,
    amount_pkr,
    amount_cny as paid_cny,
    amount_pkr as paid_pkr,
    0 as balance_cny,
    0 as balance_pkr,
    'paid' as payment_status,
    'completed' as status,
    '' as invoice_no,
    exchange_rate
    FROM chinese_supplier_payments 
    WHERE supplier_id = ? 
    AND payment_date BETWEEN ? AND ?
    ORDER BY payment_date ASC, id ASC", 'iss', [$supplierId, $fromDate, $toDate]);

$transactions = array_merge($purchases, $payments);
usort($transactions, function($a, $b) {
    return strtotime($a['transaction_date']) - strtotime($b['transaction_date']);
});

$runningBalanceCNY = $supplier['opening_balance'];
$runningBalancePKR = $supplier['opening_balance'] * $supplier['exchange_rate'];
$exchangeRate = $supplier['exchange_rate'] ?: 40.5000;

$company = getRow("SELECT * FROM company_settings LIMIT 1");

$totalAmountCNY = array_sum(array_column($transactions, 'amount_cny'));
$totalAmountPKR = array_sum(array_column($transactions, 'amount_pkr'));
$totalPaidCNY = array_sum(array_column($transactions, 'paid_cny'));
$totalPaidPKR = array_sum(array_column($transactions, 'paid_pkr'));
$totalBalanceCNY = array_sum(array_column($transactions, 'balance_cny'));
$totalBalancePKR = array_sum(array_column($transactions, 'balance_pkr'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Chinese Supplier Ledger - <?php echo htmlspecialchars($supplier['supplier_name']); ?></title>
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
.ledger-a4 .inv-meta { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 13px; }
.ledger-a4 .inv-meta strong { color: #1a2332; }
.ledger-a4 .summary-box { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
.ledger-a4 .summary-item { flex: 1; min-width: 150px; background: #f8f9fa; border-left: 4px solid #1a2332; padding: 10px 15px; border-radius: 0 6px 6px 0; }
.ledger-a4 .summary-item .label { font-size: 11px; color: #666; text-transform: uppercase; }
.ledger-a4 .summary-item .amount { font-size: 16px; font-weight: 700; color: #1a2332; }
.ledger-a4 .summary-item .sub { font-size: 11px; color: #888; }
.ledger-a4 .summary-item.danger .amount { color: #dc3545; }
.ledger-a4 .summary-item.success .amount { color: #198754; }
.ledger-a4 table { width: 100%; font-size: 11px; }
.ledger-a4 table thead th { background: #1a2332; color: #fff; padding: 7px 8px; font-weight: 600; white-space: nowrap; font-size: 11px; }
.ledger-a4 table tbody td { padding: 6px 8px; border-bottom: 1px solid #eee; }
.ledger-a4 table tfoot td, .ledger-a4 table tfoot th { padding: 8px 8px; font-weight: 700; background: #e9ecef; font-size: 11px; }
.ledger-a4 .inv-footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 2px solid #1a2332; font-size: 12px; color: #666; }

@media print {
    body { background: #fff !important; margin: 0; padding: 0; }
    .action-buttons { display: none !important; }
    .ledger-wrapper { margin: 0; padding: 10px; }
    .ledger-a4 { border: none; padding: 15px; max-width: 100%; box-shadow: none; }
    @page { size: A4 landscape; margin: 8mm; }
}
</style>
</head>
<body>

<div class="action-buttons">
    <a href="chinese_supplier_ledger.php?id=<?php echo $supplierId; ?>&from_date=<?php echo $fromDate; ?>&to_date=<?php echo $toDate; ?>" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <span class="text-white fw-bold me-2"><i class="fas fa-book me-1"></i>Chinese Supplier Ledger</span>
    <button class="btn btn-light btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
    <button class="btn btn-success btn-sm" onclick="downloadPDF()"><i class="fas fa-download me-1"></i>Download PDF</button>
    <span class="text-white ms-auto"><?php echo htmlspecialchars($supplier['supplier_name']); ?> (<?php echo $supplier['currency_code']; ?>)</span>
</div>

<div class="ledger-wrapper">
<div class="ledger-a4" id="ledgerContent">
    <?php include '../includes/print_branding.php'; ?>

    <div class="inv-meta">
        <div>
            <strong>Supplier:</strong> <?php echo htmlspecialchars($supplier['supplier_name']); ?><br>
            <strong>Currency:</strong> <?php echo $supplier['currency_code']; ?> (<?php echo $supplier['currency_name']; ?>)<br>
            <strong>Exchange Rate:</strong> 1 <?php echo $supplier['currency_code']; ?> = <?php echo number_format($exchangeRate, 4); ?> PKR
        </div>
        <div style="text-align:right">
            <strong>Period:</strong> <?php echo formatDate($fromDate); ?> to <?php echo formatDate($toDate); ?><br>
            <strong>Printed:</strong> <?php echo date('d-m-Y h:i A'); ?>
        </div>
    </div>

    <div class="summary-box">
        <div class="summary-item">
            <div class="label">Opening Balance</div>
            <div class="amount"><?php echo $supplier['symbol']; ?> <?php echo formatCurrency($supplier['opening_balance']); ?></div>
            <div class="sub">PKR <?php echo formatCurrency($supplier['opening_balance'] * $exchangeRate); ?></div>
        </div>
        <div class="summary-item <?php echo $supplier['current_balance'] > 0 ? 'danger' : 'success'; ?>">
            <div class="label">Current Balance</div>
            <div class="amount"><?php echo $supplier['symbol']; ?> <?php echo formatCurrency($supplier['current_balance']); ?></div>
            <div class="sub">PKR <?php echo formatCurrency($supplier['current_balance'] * $exchangeRate); ?></div>
        </div>
        <div class="summary-item">
            <div class="label">Total Purchases</div>
            <div class="amount"><?php echo $supplier['symbol']; ?> <?php echo formatCurrency($totalAmountCNY); ?></div>
            <div class="sub">PKR <?php echo formatCurrency($totalAmountPKR); ?></div>
        </div>
        <div class="summary-item success">
            <div class="label">Total Paid</div>
            <div class="amount"><?php echo $supplier['symbol']; ?> <?php echo formatCurrency($totalPaidCNY); ?></div>
            <div class="sub">PKR <?php echo formatCurrency($totalPaidPKR); ?></div>
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
                <th style="text-align:right">Amount (<?php echo $supplier['currency_code']; ?>)</th>
                <th style="text-align:right">Amount (PKR)</th>
                <th style="text-align:right">Paid (<?php echo $supplier['currency_code']; ?>)</th>
                <th style="text-align:right">Balance (<?php echo $supplier['currency_code']; ?>)</th>
                <th style="text-align:right">Running Balance</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <tr style="background:#f0f0f0;">
                <td></td>
                <td><?php echo formatDate($supplier['date_time']); ?></td>
                <td><strong>Opening Balance</strong></td>
                <td></td>
                <td></td>
                <td style="text-align:right"></td>
                <td style="text-align:right"></td>
                <td style="text-align:right"></td>
                <td style="text-align:right"></td>
                <td style="text-align:right">
                    <strong><?php echo $supplier['symbol']; ?> <?php echo formatCurrency($supplier['opening_balance']); ?></strong>
                    <br><small style="font-weight:normal; color:#666;">PKR <?php echo formatCurrency($supplier['opening_balance'] * $exchangeRate); ?></small>
                </td>
                <td></td>
            </tr>
            <?php if (count($transactions) > 0): ?>
                <?php $counter = 1; ?>
                <?php foreach ($transactions as $transaction): ?>
                    <?php
                    if ($transaction['transaction_type'] == 'Purchase') {
                        $runningBalanceCNY += $transaction['balance_cny'];
                        $runningBalancePKR += $transaction['balance_pkr'];
                    } else {
                        $runningBalanceCNY -= $transaction['amount_cny'];
                        $runningBalancePKR -= $transaction['amount_pkr'];
                    }
                    ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
                        <td><?php echo formatDate($transaction['transaction_date']); ?></td>
                        <td><strong><?php echo htmlspecialchars($transaction['reference_no']); ?></strong></td>
                        <td><?php echo htmlspecialchars($transaction['invoice_no']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $transaction['transaction_type'] == 'Purchase' ? 'primary' : 'success'; ?>">
                                <?php echo $transaction['transaction_type']; ?>
                            </span>
                        </td>
                        <td style="text-align:right"><?php echo formatCurrency($transaction['amount_cny']); ?></td>
                        <td style="text-align:right"><?php echo formatCurrency($transaction['amount_pkr']); ?></td>
                        <td style="text-align:right"><?php echo formatCurrency($transaction['paid_cny']); ?></td>
                        <td style="text-align:right">
                            <?php echo formatCurrency($transaction['balance_cny']); ?>
                            <?php if ($transaction['balance_cny'] != 0): ?>
                                <br><small style="color:#666;">PKR <?php echo formatCurrency($transaction['balance_pkr'] ?: ($transaction['balance_cny'] * $exchangeRate)); ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right; color: <?php echo $runningBalanceCNY > 0 ? '#dc3545' : '#198754'; ?>; font-weight:700;">
                            <?php echo $supplier['symbol']; ?> <?php echo formatCurrency($runningBalanceCNY); ?>
                            <br><small style="font-weight:normal; color:#666;">PKR <?php echo formatCurrency($runningBalancePKR); ?></small>
                        </td>
                        <td>
                            <span class="badge badge-status badge-<?php echo strtolower($transaction['payment_status']); ?>">
                                <?php echo ucfirst($transaction['payment_status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="11" style="text-align:center; color:#999; padding:20px;">No transactions found for this period</td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="5" style="text-align:right">TOTAL</th>
                <th style="text-align:right"><?php echo $supplier['symbol']; ?> <?php echo formatCurrency($totalAmountCNY); ?></th>
                <th style="text-align:right">PKR <?php echo formatCurrency($totalAmountPKR); ?></th>
                <th style="text-align:right"><?php echo $supplier['symbol']; ?> <?php echo formatCurrency($totalPaidCNY); ?></th>
                <th style="text-align:right">
                    <?php echo $supplier['symbol']; ?> <?php echo formatCurrency($totalBalanceCNY); ?>
                    <br><small style="font-weight:normal;">PKR <?php echo formatCurrency($totalBalancePKR); ?></small>
                </th>
                <th style="text-align:right; color: <?php echo $runningBalanceCNY > 0 ? '#dc3545' : '#198754'; ?>;">
                    <?php echo $supplier['symbol']; ?> <?php echo formatCurrency($runningBalanceCNY); ?>
                    <br><small style="font-weight:normal; color:#555;">PKR <?php echo formatCurrency($runningBalancePKR); ?></small>
                </th>
                <th></th>
            </tr>
        </tfoot>
    </table>

    <div class="inv-footer">
        <p>Generated on <?php echo date('d-m-Y h:i A'); ?> | Exchange Rate: 1 <?php echo $supplier['currency_code']; ?> = <?php echo number_format($exchangeRate, 4); ?> PKR | <?php echo CLIENT_NAME; ?></p>
    </div>
</div>
</div>

<script>
function downloadPDF() {
    document.getElementById('ledgerContent').style.display = 'block';
    var opt = {
        margin: 0.4,
        filename: 'chinese_supplier_ledger_<?php echo htmlspecialchars($supplier["supplier_name"]); ?>.pdf',
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