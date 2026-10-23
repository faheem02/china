<?php
/**
 * Chinese Supplier Detail Page
 * Manufacturing ERP System
 */

// Include database connection
require_once '../includes/database.php';

// Require login
requireLogin();

$pageTitle = 'Chinese Supplier Detail';
$message = '';
$messageType = '';

// Get supplier ID from URL
$supplierId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($supplierId <= 0) {
    header('Location: chinese_suppliers.php');
    exit;
}

// Get supplier details with currency info and calculated current_balance
$supplier = getRow("SELECT s.*, c.currency_code, c.currency_name, c.symbol, c.exchange_rate,
    COALESCE(SUM(ip.balance_cny), 0) AS purchase_balance_cny,
    COALESCE((SELECT SUM(cp.amount_cny) FROM chinese_supplier_payments cp WHERE cp.supplier_id = s.id), 0) AS payments_made_cny,
    (s.opening_balance + COALESCE(SUM(ip.balance_cny), 0) - COALESCE((SELECT SUM(cp.amount_cny) FROM chinese_supplier_payments cp WHERE cp.supplier_id = s.id), 0)) AS current_balance
    FROM chinese_suppliers s
    LEFT JOIN currencies c ON s.currency_id = c.id
    LEFT JOIN import_purchases ip ON ip.supplier_id = s.id
    WHERE s.id = ?
    GROUP BY s.id", 'i', [$supplierId]);

if (!$supplier) {
    header('Location: chinese_suppliers.php');
    exit;
}

// Get supplier transactions (purchases)
$sql = "SELECT 
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
        ORDER BY purchase_date DESC, id DESC";
$purchases = getRows($sql, 'i', [$supplierId]);

// Get supplier payments
$sql = "SELECT 
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
        ORDER BY payment_date DESC, id DESC";
$payments = getRows($sql, 'i', [$supplierId]);

// Merge transactions
$transactions = array_merge($purchases, $payments);

// Sort ASC for running balance calculation
usort($transactions, function($a, $b) {
    return strtotime($a['transaction_date']) - strtotime($b['transaction_date']);
});

// Calculate running balance starting from opening_balance
$runningBalanceCNY = $supplier['opening_balance'];
foreach ($transactions as &$t) {
    if ($t['transaction_type'] === 'Purchase') {
        $runningBalanceCNY += $t['amount_cny'] - $t['paid_cny'];
    } else {
        $runningBalanceCNY -= $t['amount_cny'];
    }
    $t['running_balance_cny'] = $runningBalanceCNY;
}
unset($t);

// Sort back DESC for display
usort($transactions, function($a, $b) {
    return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
});

// Calculate summary
$totalPurchasesCNY = 0;
$totalPurchasesPKR = 0;
$totalPaidCNY = 0;
$totalPaidPKR = 0;
$totalBalanceCNY = 0;
$totalBalancePKR = 0;

foreach ($purchases as $purchase) {
    $totalPurchasesCNY += $purchase['amount_cny'];
    $totalPurchasesPKR += $purchase['amount_pkr'];
    $totalPaidCNY += $purchase['paid_cny'];
    $totalPaidPKR += $purchase['paid_pkr'];
    $totalBalanceCNY += $purchase['balance_cny'];
    $totalBalancePKR += $purchase['balance_pkr'];
}

// Get current exchange rate
$exchangeRate = $supplier['exchange_rate'] ?: 40.5000;

// Include header
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="chinese_suppliers.php">Chinese Suppliers</a></li>
                <li class="breadcrumb-item active">Supplier Detail</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Supplier Information -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-user me-2"></i>Chinese Supplier Information</span>
                <a href="chinese_suppliers.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered mb-0">
                            <tr>
                                <th style="width: 35%;">Supplier Name</th>
                                <td><strong><?php echo htmlspecialchars($supplier['supplier_name']); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Company Name</th>
                                <td><?php echo htmlspecialchars($supplier['company_name'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Phone</th>
                                <td><?php echo htmlspecialchars($supplier['phone'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td><?php echo htmlspecialchars($supplier['email'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <span class="badge badge-status badge-<?php echo $supplier['status']; ?>">
                                        <?php echo ucfirst($supplier['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered mb-0">
                            <tr>
                                <th style="width: 35%;">Currency</th>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo $supplier['currency_code'] . ' (' . $supplier['symbol'] . ')'; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Exchange Rate</th>
                                <td><strong>1 <?php echo $supplier['currency_code']; ?> = <?php echo number_format($exchangeRate, 4); ?> PKR</strong></td>
                            </tr>
                            <tr>
                                <th>City</th>
                                <td><?php echo htmlspecialchars($supplier['city'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Country</th>
                                <td><?php echo htmlspecialchars($supplier['country'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Address</th>
                                <td><?php echo htmlspecialchars($supplier['address'] ?: '-'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transactions -->
<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list me-2"></i>Recent Transactions</span>
                <a href="chinese_supplier_ledger.php?id=<?php echo $supplierId; ?>" class="btn btn-sm btn-primary">
                    View Full Ledger
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Type</th>
                                <th>Invoice No</th>
                                <th>Amount (CNY)</th>
                                <th>Amount (PKR)</th>
                                <th>Paid (CNY)</th>
                                <th>Balance (CNY)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($transactions) > 0): ?>
                                <tr style="background:#e8f0fe;">
                                    <td>-</td>
                                    <td><strong><?php echo htmlspecialchars($supplier['supplier_name']); ?></strong></td>
                                    <td><span class="badge bg-secondary">Opening Balance</span></td>
                                    <td>-</td>
                                    <td class="fw-bold"><?php echo formatCurrency($supplier['opening_balance']); ?></td>
                                    <td class="fw-bold">PKR <?php echo formatCurrency($supplier['opening_balance'] * $exchangeRate); ?></td>
                                    <td>-</td>
                                    <td class="fw-bold">
                                        <?php echo formatCurrency($supplier['opening_balance']); ?>
                                        <br><small class="text-muted">PKR <?php echo formatCurrency($supplier['opening_balance'] * $exchangeRate); ?></small>
                                    </td>
                                    <td><span class="badge bg-info">Active</span></td>
                                </tr>
                                <?php foreach (array_slice($transactions, 0, 10) as $transaction): ?>
                                    <tr>
                                        <td><?php echo formatDate($transaction['transaction_date']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['reference_no']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $transaction['transaction_type'] == 'Purchase' ? 'primary' : 'success'; ?>">
                                                <?php echo $transaction['transaction_type']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($transaction['invoice_no']); ?></td>
                                        <td><?php echo formatCurrency($transaction['amount_cny']); ?></td>
                                        <td><?php echo formatCurrency($transaction['amount_pkr']); ?></td>
                                        <td><?php echo formatCurrency($transaction['paid_cny']); ?></td>
                                        <td class="fw-bold">
                                            <?php echo formatCurrency($transaction['running_balance_cny']); ?>
                                            <br><small class="text-muted">PKR <?php echo formatCurrency($transaction['running_balance_cny'] * $exchangeRate); ?></small>
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
                                    <td colspan="9" class="text-center text-muted py-3">
                                        <i class="fas fa-info-circle me-2"></i>No transactions found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include '../includes/footer.php';
?>