<?php
require_once '../includes/database.php';
requireLogin();
$pageTitle = 'Cash Flow Statement';

$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';

// Operating Inflows
$salesCashReceived = (float)getRow("SELECT COALESCE(SUM(amount),0) AS total FROM customer_receipts WHERE payment_method='cash' AND payment_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate])['total'];
$partyReceivedCash = (float)getRow("SELECT COALESCE(SUM(amount),0) AS total FROM party_transactions WHERE type='received' AND payment_method='cash' AND transaction_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate])['total'];

// Operating Outflows
$supplierPaidCash = (float)getRow("SELECT COALESCE(SUM(amount),0) AS total FROM supplier_payments WHERE payment_type='cash' AND payment_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate])['total'];
$expensePaid = (float)getRow("SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE expense_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate])['total'];
$empPaidCash = (float)getRow("SELECT COALESCE(SUM(amount),0) AS total FROM employee_payments WHERE payment_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate])['total'];
$partyPaidCash = (float)getRow("SELECT COALESCE(SUM(amount),0) AS total FROM party_transactions WHERE type='paid' AND payment_method='cash' AND transaction_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate])['total'];

// Bank Transfers
$salesBankReceived = (float)getRow("SELECT COALESCE(SUM(amount),0) AS total FROM customer_receipts WHERE payment_method IN ('bank','bank_transfer','cheque') AND payment_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate])['total'];
$partyReceivedBank = (float)getRow("SELECT COALESCE(SUM(amount),0) AS total FROM party_transactions WHERE type='received' AND payment_method IN ('bank','bank_transfer','cheque') AND transaction_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate])['total'];
$supplierPaidBank = (float)getRow("SELECT COALESCE(SUM(amount),0) AS total FROM supplier_payments WHERE payment_type IN ('bank','bank_transfer','cheque') AND payment_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate])['total'];
$partyPaidBank = (float)getRow("SELECT COALESCE(SUM(amount),0) AS total FROM party_transactions WHERE type='paid' AND payment_method IN ('bank','bank_transfer','cheque') AND transaction_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate])['total'];

$totalCashInflow = $salesCashReceived + $partyReceivedCash;
$totalCashOutflow = $supplierPaidCash + $expensePaid + $empPaidCash + $partyPaidCash;
$netCashFlow = $totalCashInflow - $totalCashOutflow;

$totalBankInflow = $salesBankReceived + $partyReceivedBank;
$totalBankOutflow = $supplierPaidBank + $partyPaidBank;
$netBankFlow = $totalBankInflow - $totalBankOutflow;
?>

<div class="row mb-3"><div class="col-12"><div class="card">
    <div class="card-header d-flex justify-content-between align-items-center"><span><i class="fas fa-money-bill me-2"></i>Cash Flow Statement</span><a href="reports_cashflow_print.php?from_date=<?php echo urlencode($fromDate); ?>&to_date=<?php echo urlencode($toDate); ?>" target="_blank" class="btn btn-danger btn-sm"><i class="fas fa-print me-1"></i>Print</a></div>
    <div class="card-body"><form method="GET" class="row g-3">
        <div class="col-md-4"><label class="form-label">From</label><input type="date" class="form-control" name="from_date" value="<?php echo $fromDate; ?>"></div>
        <div class="col-md-4"><label class="form-label">To</label><input type="date" class="form-control" name="to_date" value="<?php echo $toDate; ?>"></div>
        <div class="col-md-4 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Generate</button></div>
    </form></div>
</div></div></div>

<div class="row mb-3">
    <div class="col-md-4"><div class="card <?php echo $netCashFlow >= 0 ? 'bg-success' : 'bg-danger'; ?> text-white"><div class="card-body text-center"><h6>Net Cash Flow</h6><h2><?php echo formatCurrency($netCashFlow);?></h2></div></div></div>
    <div class="col-md-4"><div class="card bg-primary text-white"><div class="card-body text-center"><h6>Total Cash In</h6><h3><?php echo formatCurrency($totalCashInflow);?></h3></div></div></div>
    <div class="col-md-4"><div class="card bg-danger text-white"><div class="card-body text-center"><h6>Total Cash Out</h6><h3><?php echo formatCurrency($totalCashOutflow);?></h3></div></div></div>
</div>

<div class="row mb-3">
    <div class="col-md-4"><div class="card border-primary"><div class="card-body text-center"><h6 class="text-muted">Net Bank Flow</h6><h5 class="<?php echo $netBankFlow >= 0 ? 'text-success' : 'text-danger';?>"><?php echo formatCurrency($netBankFlow);?></h5></div></div></div>
    <div class="col-md-4"><div class="card border-info"><div class="card-body text-center"><h6 class="text-muted">Bank In</h6><h5 class="text-info"><?php echo formatCurrency($totalBankInflow);?></h5></div></div></div>
    <div class="col-md-4"><div class="card border-warning"><div class="card-body text-center"><h6 class="text-muted">Bank Out</h6><h5 class="text-warning"><?php echo formatCurrency($totalBankOutflow);?></h5></div></div></div>
</div>

<div class="row">
    <div class="col-md-6"><div class="card shadow-sm">
        <div class="card-header" style="background:#1a2332;color:#fff;"><i class="fas fa-arrow-down me-2 text-success"></i>Cash & Bank Inflows</div>
        <div class="card-body"><table class="table table-borderless mb-0">
            <tr><td colspan="2" class="text-muted fw-bold">Cash Inflows</td></tr>
            <tr><td>Customer Receipts (Cash)</td><td class="text-end fw-bold text-success"><?php echo formatCurrency($salesCashReceived);?></td></tr>
            <tr><td>Party Received (Cash)</td><td class="text-end fw-bold text-success"><?php echo formatCurrency($partyReceivedCash);?></td></tr>
            <tr class="table-success"><td><strong>Total Cash In</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalCashInflow);?></strong></td></tr>
            <tr><td colspan="2" class="text-muted fw-bold mt-2">Bank Inflows</td></tr>
            <tr><td>Customer Receipts (Bank)</td><td class="text-end fw-bold text-success"><?php echo formatCurrency($salesBankReceived);?></td></tr>
            <tr><td>Party Received (Bank)</td><td class="text-end fw-bold text-success"><?php echo formatCurrency($partyReceivedBank);?></td></tr>
            <tr class="table-success"><td><strong>Total Bank In</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalBankInflow);?></strong></td></tr>
        </table></div>
    </div></div>
    <div class="col-md-6"><div class="card shadow-sm">
        <div class="card-header" style="background:#1a2332;color:#fff;"><i class="fas fa-arrow-up me-2 text-danger"></i>Cash & Bank Outflows</div>
        <div class="card-body"><table class="table table-borderless mb-0">
            <tr><td colspan="2" class="text-muted fw-bold">Cash Outflows</td></tr>
            <tr><td>Supplier Payments (Cash)</td><td class="text-end fw-bold text-danger"><?php echo formatCurrency($supplierPaidCash);?></td></tr>
            <tr><td>Expenses Paid</td><td class="text-end fw-bold text-danger"><?php echo formatCurrency($expensePaid);?></td></tr>
            <tr><td>Employee Payments</td><td class="text-end fw-bold text-danger"><?php echo formatCurrency($empPaidCash);?></td></tr>
            <tr><td>Party Paid (Cash)</td><td class="text-end fw-bold text-danger"><?php echo formatCurrency($partyPaidCash);?></td></tr>
            <tr class="table-danger"><td><strong>Total Cash Out</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalCashOutflow);?></strong></td></tr>
            <tr><td colspan="2" class="text-muted fw-bold mt-2">Bank Outflows</td></tr>
            <tr><td>Supplier Payments (Bank)</td><td class="text-end fw-bold text-danger"><?php echo formatCurrency($supplierPaidBank);?></td></tr>
            <tr><td>Party Paid (Bank)</td><td class="text-end fw-bold text-danger"><?php echo formatCurrency($partyPaidBank);?></td></tr>
            <tr class="table-danger"><td><strong>Total Bank Out</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalBankOutflow);?></strong></td></tr>
        </table></div>
    </div></div>
</div>

<?php include '../includes/footer.php'; ?>
