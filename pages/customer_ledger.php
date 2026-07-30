<?php
require_once '../includes/database.php';
requireLogin();

$customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($customerId <= 0) {
    header('Location: customers.php');
    exit;
}

$customer = getRow("SELECT * FROM customers WHERE id = ?", 'i', [$customerId]);
if (!$customer) {
    header('Location: customers.php');
    exit;
}

$pageTitle = 'Customer Ledger - ' . $customer['customer_name'];

$sales = getRows("SELECT id, sale_no as ref_no, sale_date as trans_date, total_amount as debit, paid_amount as credit, 'Sale' as trans_type FROM sales WHERE customer_id = ? ORDER BY sale_date, id", 'i', [$customerId]);

$receipts = getRows("SELECT id, receipt_no as ref_no, payment_date as trans_date, 0 as debit, amount as credit, 'Payment' as trans_type FROM customer_receipts WHERE customer_id = ? ORDER BY payment_date, id", 'i', [$customerId]);

$totalSalesAmt = 0;
$totalPaidAmt = 0;

foreach ($sales as $s) {
    $totalSalesAmt += $s['debit'];
}
foreach ($receipts as $r) {
    $totalPaidAmt += $r['credit'];
}

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

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="row mb-3">
    <div class="col-12">
        <a href="customers.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Customers
        </a>
        <a href="customer_ledger_print.php?id=<?php echo $customerId; ?>" class="btn btn-primary" target="_blank">
            <i class="fas fa-print me-2"></i>Print Ledger
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-book me-2"></i>Customer Ledger
                <span class="ms-2 badge bg-info"><?php echo htmlspecialchars($customer['customer_name']); ?></span>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-label">Opening Balance</div>
                            <div class="stat-number"><?php echo formatCurrency($customer['opening_balance']); ?></div>
                            <div class="text-muted small">(<?php echo ucfirst($customer['opening_balance_type'] ?? 'receivable'); ?>)</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-label">Total Sales</div>
                            <div class="stat-number text-primary"><?php echo formatCurrency($totalSalesAmt); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-label">Total Payments</div>
                            <div class="stat-number text-success"><?php echo formatCurrency($totalPaidAmt); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-label">Closing Balance</div>
                            <div class="stat-number <?php echo $closingBalance > 0 ? 'text-danger' : 'text-success'; ?>">
                                <?php echo formatCurrency($closingBalance); ?>
                            </div>
                            <div class="text-muted small">(<?php echo $closingBalance > 0 ? 'Receivable' : ($closingBalance < 0 ? 'Payable' : 'Settled'); ?>)</div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="ledgerTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Reference #</th>
                                <th>Type</th>
                                <th>Debit (PKR)</th>
                                <th>Credit (PKR)</th>
                                <th>Balance (PKR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-secondary">
                                <td></td>
                                <td><?php echo formatDate($customer['date_time']); ?></td>
                                <td><strong>Opening Balance</strong></td>
                                <td><small class="text-muted">(<?php echo ucfirst($customer['opening_balance_type'] ?? 'receivable'); ?>)</small></td>
                                <td></td>
                                <td></td>
                                <td class="<?php echo $effectiveOpening > 0 ? 'text-danger' : ($effectiveOpening < 0 ? 'text-success' : ''); ?>">
                                    <strong><?php echo formatCurrency($effectiveOpening); ?></strong>
                                </td>
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
                                    <td><?php echo formatDate($entry['trans_date']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($entry['ref_no']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?php echo $entry['trans_type'] == 'Sale' ? 'primary' : 'success'; ?>">
                                            <?php echo $entry['trans_type']; ?>
                                        </span>
                                    </td>
                                    <td class="text-end"><?php echo $entry['debit'] > 0 ? formatCurrency($entry['debit']) : '-'; ?></td>
                                    <td class="text-end"><?php echo $entry['credit'] > 0 ? formatCurrency($entry['credit']) : '-'; ?></td>
                                    <td class="text-end <?php echo $runningBalance > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <strong><?php echo formatCurrency($runningBalance); ?></strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="table-secondary fw-bold">
                                <td></td>
                                <td></td>
                                <td><strong>Closing Balance</strong></td>
                                <td></td>
                                <td class="text-end"><strong><?php echo formatCurrency($totalSalesAmt); ?></strong></td>
                                <td class="text-end"><strong><?php echo formatCurrency($totalPaidAmt); ?></strong></td>
                                <td class="text-end <?php echo $closingBalance > 0 ? 'text-danger' : 'text-success'; ?>">
                                    <strong><?php echo formatCurrency($closingBalance); ?></strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#ledgerTable').DataTable({
        "pageLength": 50,
        "order": [],
        "paging": false,
        "searching": false,
        "info": false,
        "language": {
            "emptyTable": "No ledger entries found"
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
