<?php
require_once '../includes/database.php';
requireLogin();

$fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$toDate = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
$bankIdFilter = isset($_GET['bank_id']) && is_numeric($_GET['bank_id']) ? (int)$_GET['bank_id'] : 0;

$selectedBankAccount = null;
if ($bankIdFilter > 0) {
    $selectedBankAccount = getRow("SELECT * FROM bank_accounts WHERE id = ?", 'i', [$bankIdFilter]);
}

if ($bankIdFilter > 0 && $selectedBankAccount) {
    $openingBalance = (float)$selectedBankAccount['opening_balance'];
} else {
    $openingBalance = (float)(getRow("SELECT COALESCE(SUM(opening_balance), 0) as s FROM bank_accounts WHERE status = 'active'")['s'] ?? 0);
}

$totalBankIn = 0;
$totalBankOut = 0;

$baseSql = "SELECT cr.payment_date as txn_date, CONCAT('Receipt - ', cr.receipt_no) as description, cr.receipt_no as reference, cr.amount as bank_in, 0 as bank_out, 'in' as direction, cr.bank_id, COALESCE(ba.bank_name, cr.bank_name, 'Bank') as bank_display
    FROM customer_receipts cr
    LEFT JOIN bank_accounts ba ON cr.bank_id = ba.id
    WHERE cr.payment_method IN ('bank', 'bank_transfer', 'cheque')";
$baseSql .= " UNION ALL SELECT s.sale_date, CONCAT('Sale - ', s.sale_no), s.sale_no, s.paid_amount, 0, 'in', s.bank_id, COALESCE(ba.bank_name, s.bank_name, 'Bank') FROM sales s LEFT JOIN bank_accounts ba ON s.bank_id = ba.id WHERE s.payment_method = 'bank' AND s.status = 'completed' AND s.paid_amount > 0";
$baseSql .= " UNION ALL SELECT sp.payment_date, CONCAT('Payment - ', sp.payment_no), sp.payment_no, 0, sp.amount, 'out', sp.bank_id, COALESCE(ba.bank_name, sp.bank_name, 'Bank')
    FROM supplier_payments sp LEFT JOIN bank_accounts ba ON sp.bank_id = ba.id WHERE sp.payment_type IN ('bank', 'bank_transfer', 'cheque')";
$baseSql .= " UNION ALL SELECT lp.purchase_date, CONCAT('Local purchase - ', lp.purchase_no), lp.purchase_no, 0, lp.paid_amount, 'out', lp.bank_id, COALESCE(ba.bank_name, lp.bank_name, 'Bank') FROM local_purchases lp LEFT JOIN bank_accounts ba ON lp.bank_id = ba.id WHERE lp.payment_method = 'bank' AND lp.paid_amount > 0";
$baseSql .= " UNION ALL SELECT ip.purchase_date, CONCAT('Import purchase - ', ip.purchase_no), ip.purchase_no, 0, ip.paid_amount_pkr, 'out', ip.bank_id, COALESCE(ba.bank_name, ip.bank_name, 'Bank') FROM import_purchases ip LEFT JOIN bank_accounts ba ON ip.bank_id = ba.id WHERE ip.payment_method = 'bank' AND ip.paid_amount_pkr > 0";
$baseSql .= " UNION ALL SELECT e.expense_date, CONCAT('Expense - ', e.category, ' - ', e.expense_no), e.expense_no, 0, e.paid_amount, 'out', e.bank_id, COALESCE(ba.bank_name, e.bank_name, 'Bank')
    FROM expenses e LEFT JOIN bank_accounts ba ON e.bank_id = ba.id WHERE e.paid_amount > 0 AND e.paid_by IN ('bank', 'bank_transfer', 'cheque') AND e.status IN ('active', 'paid')";
$baseSql .= " UNION ALL SELECT cp.payment_date, CONCAT('Chinese Payment - ', cp.payment_no), cp.payment_no, 0, cp.amount_pkr, 'out', cp.bank_id, COALESCE(ba.bank_name, cp.bank_name, 'Bank')
    FROM chinese_supplier_payments cp LEFT JOIN bank_accounts ba ON cp.bank_id = ba.id
    WHERE cp.payment_type IN ('bank', 'bank_transfer', 'cheque', 'online', 'telegraphic_transfer') AND cp.status = 'completed'";

$chkParty = $conn->query("SHOW TABLES LIKE 'party_transactions'");
if ($chkParty && $chkParty->num_rows > 0) {
    $baseSql .= " UNION ALL SELECT pt.transaction_date, CONCAT('Party Receipt - ', pt.transaction_no, ' (', p.party_name, ')'), pt.transaction_no, pt.amount, 0, 'in', pt.bank_id, COALESCE(ba.bank_name, pt.bank_name, 'Bank')
        FROM party_transactions pt JOIN parties p ON pt.party_id = p.id LEFT JOIN bank_accounts ba ON pt.bank_id = ba.id WHERE pt.type = 'received' AND pt.payment_method IN ('bank', 'bank_transfer', 'cheque')";
    $baseSql .= " UNION ALL SELECT pt.transaction_date, CONCAT('Party Payment - ', pt.transaction_no, ' (', p.party_name, ')'), pt.transaction_no, 0, pt.amount, 'out', pt.bank_id, COALESCE(ba.bank_name, pt.bank_name, 'Bank')
        FROM party_transactions pt JOIN parties p ON pt.party_id = p.id LEFT JOIN bank_accounts ba ON pt.bank_id = ba.id WHERE pt.type = 'paid' AND pt.payment_method IN ('bank', 'bank_transfer', 'cheque')";
}

$baseSql .= " ORDER BY txn_date ASC";

$transactions = getRows($baseSql);

$filteredTransactions = [];
foreach ($transactions as $txn) {
    if ($bankIdFilter > 0 && (int)($txn['bank_id'] ?? 0) !== $bankIdFilter) {
        continue;
    }
    if ($txn['txn_date'] < $fromDate) {
        $openingBalance += (float)$txn['bank_in'] - (float)$txn['bank_out'];
        continue;
    }
    if ($txn['txn_date'] > $toDate) {
        continue;
    }
    $totalBankIn += (float)$txn['bank_in'];
    $totalBankOut += (float)$txn['bank_out'];
    $filteredTransactions[] = $txn;
}

$closingBalance = $openingBalance + $totalBankIn - $totalBankOut;
$company = getRow("SELECT * FROM company_settings LIMIT 1");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bank Book<?php echo $selectedBankAccount ? ' - ' . htmlspecialchars($selectedBankAccount['bank_name']) : ''; ?></title>
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
.ledger-a4 .summary-box { display: flex; gap: 20px; margin-bottom: 20px; }
.ledger-a4 .summary-item { flex: 1; background: #f8f9fa; border-left: 4px solid #1a2332; padding: 12px 15px; border-radius: 0 6px 6px 0; }
.ledger-a4 .summary-item .label { font-size: 11px; color: #666; text-transform: uppercase; font-weight: 600; }
.ledger-a4 .summary-item .amount { font-size: 20px; font-weight: 700; color: #1a2332; margin-top: 4px; }
.ledger-a4 .summary-item.success { border-left-color: #198754; }
.ledger-a4 .summary-item.success .amount { color: #198754; }
.ledger-a4 .summary-item.danger { border-left-color: #dc3545; }
.ledger-a4 .summary-item.danger .amount { color: #dc3545; }
.ledger-a4 .summary-item.info { border-left-color: #0dcaf0; }
.ledger-a4 .summary-item.info .amount { color: #1a2332; }

.ledger-a4 table { width: 100%; font-size: 12px; border-collapse: collapse; }
.ledger-a4 table thead th { background: #1a2332; color: #fff; padding: 10px 12px; font-weight: 600; white-space: nowrap; }
.ledger-a4 table tbody td { padding: 8px 12px; border-bottom: 1px solid #eee; }
.ledger-a4 table tbody tr:nth-child(even) { background: #f9f9f9; }
.ledger-a4 table tbody tr:hover { background: #f0f4ff; }
.ledger-a4 table tfoot td, .ledger-a4 table tfoot th { padding: 10px 12px; font-weight: 700; background: #e9ecef; border-top: 2px solid #1a2332; }
.ledger-a4 .inv-footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 2px solid #1a2332; font-size: 12px; color: #666; }

.text-bank-in { color: #198754; font-weight: 700; }
.text-bank-out { color: #dc3545; font-weight: 700; }
.text-balance-pos { color: #198754; font-weight: 700; }
.text-balance-neg { color: #dc3545; font-weight: 700; }

.opening-row { background: #e8f4fd !important; }
.closing-row { background: #e9ecef !important; font-weight: 700; }

@media print {
    body { background: #fff !important; margin: 0; padding: 0; }
    .action-buttons { display: none !important; }
    .ledger-wrapper { margin: 0; padding: 10px; }
    .ledger-a4 { border: none; padding: 15px; max-width: 100%; box-shadow: none; }
    .ledger-a4 table tbody tr:hover { background: transparent; }
    @page { size: A4 landscape; margin: 10mm; }
}
</style>
</head>
<body>

<div class="action-buttons">
    <button onclick="window.print()" class="btn btn-danger btn-sm">
        <i class="fas fa-print me-1"></i>Print
    </button>
    <button onclick="window.close()" class="btn btn-secondary btn-sm">
        <i class="fas fa-times me-1"></i>Close
    </button>
</div>

<div class="ledger-wrapper">
    <div class="ledger-a4">
        <div class="inv-header">
            <h3><?php echo htmlspecialchars($company['company_name'] ?? CLIENT_NAME); ?></h3>
            <p><?php echo htmlspecialchars($company['address'] ?? CLIENT_ADDRESS); ?></p>
            <p>Phone: <?php echo htmlspecialchars($company['phone'] ?? CLIENT_PHONE); ?></p>
        </div>

        <div class="inv-meta">
            <div>
                <strong>Report:</strong> Bank Book Statement<br>
                <strong>Period:</strong> <?php echo formatDate($fromDate); ?> to <?php echo formatDate($toDate); ?>
            </div>
            <div style="text-align:right">
                <strong>Bank:</strong> <?php echo $selectedBankAccount ? htmlspecialchars($selectedBankAccount['bank_name'] . ' (' . $selectedBankAccount['account_number'] . ')') : 'All Bank Accounts'; ?><br>
                <strong>Printed:</strong> <?php echo date('d-m-Y h:i A'); ?><br>
                <strong>User:</strong> <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin'; ?>
            </div>
        </div>

        <div class="inv-title">
            BANK BOOK STATEMENT | <?php echo formatDate($fromDate); ?> to <?php echo formatDate($toDate); ?>
            <?php if ($selectedBankAccount): ?>
                (<?php echo htmlspecialchars($selectedBankAccount['bank_name'] . ' - ' . $selectedBankAccount['account_number']); ?>)
            <?php endif; ?>
        </div>

        <div class="summary-box">
            <div class="summary-item">
                <div class="label">Opening Balance</div>
                <div class="amount"><?php echo formatCurrency($openingBalance); ?></div>
            </div>
            <div class="summary-item success">
                <div class="label">Total Bank In</div>
                <div class="amount"><?php echo formatCurrency($totalBankIn); ?></div>
            </div>
            <div class="summary-item danger">
                <div class="label">Total Bank Out</div>
                <div class="amount"><?php echo formatCurrency($totalBankOut); ?></div>
            </div>
            <div class="summary-item <?php echo $closingBalance >= 0 ? 'success' : 'danger'; ?>">
                <div class="label">Closing Balance</div>
                <div class="amount"><?php echo formatCurrency($closingBalance); ?></div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Bank Account</th>
                    <th>Reference</th>
                    <th style="text-align:right">Bank In (PKR)</th>
                    <th style="text-align:right">Bank Out (PKR)</th>
                    <th style="text-align:right">Balance (PKR)</th>
                </tr>
            </thead>
            <tbody>
                <tr class="opening-row">
                    <td></td>
                    <td><?php echo formatDate($fromDate); ?></td>
                    <td><strong>Opening Balance</strong></td>
                    <td>-</td>
                    <td></td>
                    <td style="text-align:right"></td>
                    <td style="text-align:right"></td>
                    <td style="text-align:right" class="<?php echo $openingBalance >= 0 ? 'text-balance-pos' : 'text-balance-neg'; ?>">
                        <strong><?php echo formatCurrency($openingBalance); ?></strong>
                    </td>
                </tr>
                <?php $runningBalance = $openingBalance; ?>
                <?php $counter = 1; ?>
                <?php if (count($filteredTransactions) > 0): ?>
                    <?php foreach ($filteredTransactions as $txn): ?>
                        <?php $runningBalance += (float)$txn['bank_in'] - (float)$txn['bank_out']; ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td><?php echo formatDate($txn['txn_date']); ?></td>
                            <td><?php echo htmlspecialchars($txn['description']); ?></td>
                            <td><?php echo htmlspecialchars($txn['bank_display'] ?? 'Bank'); ?></td>
                            <td><strong><?php echo htmlspecialchars($txn['reference']); ?></strong></td>
                            <td style="text-align:right">
                                <?php if ((float)$txn['bank_in'] > 0): ?>
                                    <span class="text-bank-in"><?php echo formatCurrency($txn['bank_in']); ?></span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right">
                                <?php if ((float)$txn['bank_out'] > 0): ?>
                                    <span class="text-bank-out"><?php echo formatCurrency($txn['bank_out']); ?></span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right" class="<?php echo $runningBalance >= 0 ? 'text-balance-pos' : 'text-balance-neg'; ?>">
                                <strong><?php echo formatCurrency($runningBalance); ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align:center; color:#999; padding:30px;">
                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                            No bank transactions found for the selected period
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="closing-row">
                    <th colspan="5" style="text-align:right">TOTAL</th>
                    <th style="text-align:right" class="text-bank-in"><?php echo formatCurrency($totalBankIn); ?></th>
                    <th style="text-align:right" class="text-bank-out"><?php echo formatCurrency($totalBankOut); ?></th>
                    <th style="text-align:right" class="<?php echo $closingBalance >= 0 ? 'text-balance-pos' : 'text-balance-neg'; ?>">
                        <?php echo formatCurrency($closingBalance); ?>
                    </th>
                </tr>
            </tfoot>
        </table>

        <div class="inv-footer">
            <p>Generated on <?php echo date('d-m-Y h:i A'); ?> | <?php echo CLIENT_NAME; ?></p>
        </div>
    </div>
</div>

</body>
</html>
