<?php
require_once '../includes/database.php';
requireLogin();
$pageTitle = 'Trial Balance';

$asOfDate = $_GET['as_of_date'] ?? date('Y-m-d');

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';

// Helper
function tbVal($sql, $params = [], $types = '') {
    $row = getRow($sql, $types ?: null, $params ?: null);
    return $row ? (float)($row['total'] ?? 0) : 0.0;
}

// Assets
$totalCash = tbVal("SELECT COALESCE(SUM(amount),0) AS total FROM customer_receipts WHERE payment_method='cash'");
$totalCashOut = tbVal("SELECT COALESCE(SUM(amount),0) AS total FROM supplier_payments WHERE payment_type='cash'")
    + tbVal("SELECT COALESCE(SUM(amount),0) AS total FROM expenses")
    + tbVal("SELECT COALESCE(SUM(amount),0) AS total FROM employee_payments")
    + tbVal("SELECT COALESCE(SUM(amount),0) AS total FROM party_transactions WHERE type='paid' AND payment_method='cash'");
$netCash = $totalCash - $totalCashOut;

$bankBalance = tbVal("SELECT COALESCE(balance,0) AS total FROM accounts WHERE account_type = 'Bank' ORDER BY id DESC LIMIT 1");
$rmStock = tbVal("SELECT COALESCE(SUM(current_stock * purchase_price_pkr),0) AS total FROM raw_materials");
$fgStock = tbVal("SELECT COALESCE(SUM(current_stock * cost_price),0) AS total FROM finished_goods");
$partyReceivable = tbVal("SELECT COALESCE(SUM(current_balance),0) AS total FROM parties WHERE current_balance > 0");

// Liabilities
$partyPayable = tbVal("SELECT COALESCE(ABS(SUM(current_balance)),0) AS total FROM parties WHERE current_balance < 0");
$supplierOutstanding = tbVal("SELECT COALESCE(SUM(balance),0) AS total FROM local_purchases WHERE payment_status != 'paid'");
$importOutstanding = tbVal("SELECT COALESCE(SUM(balance_pkr),0) AS total FROM import_purchases WHERE payment_status != 'paid'");
$customerOutstanding = tbVal("SELECT COALESCE(SUM(balance),0) AS total FROM sales WHERE payment_status != 'paid'");
$empPayable = tbVal("SELECT COALESCE(SUM(balance),0) AS total FROM employee_payables WHERE payment_status != 'paid'");
$expenseOutstanding = tbVal("SELECT COALESCE(SUM(balance),0) AS total FROM expenses WHERE payment_status != 'paid'");

// Equity
$totalSalesAll = tbVal("SELECT COALESCE(SUM(total_amount),0) AS total FROM sales");
$totalPurchaseAll = tbVal("SELECT COALESCE(SUM(total_amount),0) AS total FROM local_purchases") + tbVal("SELECT COALESCE(SUM(grand_total_pkr),0) AS total FROM import_purchases");
$totalExpenseAll = tbVal("SELECT COALESCE(SUM(amount),0) AS total FROM expenses");
$totalEmpPaidAll = tbVal("SELECT COALESCE(SUM(amount),0) AS total FROM employee_payments");
$netProfit = $totalSalesAll - $totalPurchaseAll - $totalExpenseAll - $totalEmpPaidAll;

$totalAssets = $netCash + $bankBalance + $rmStock + $fgStock + $partyReceivable + $customerOutstanding;
$totalLiabilities = $partyPayable + $supplierOutstanding + $importOutstanding + $empPayable + $expenseOutstanding;
$equity = $totalAssets - $totalLiabilities;
?>

<div class="row mb-3"><div class="col-12"><div class="card">
    <div class="card-header d-flex justify-content-between align-items-center"><span><i class="fas fa-balance-scale me-2"></i>Trial Balance as of <?php echo formatDate($asOfDate);?></span><a href="trial_balance_print.php?as_of_date=<?php echo urlencode($asOfDate); ?>" target="_blank" class="btn btn-danger btn-sm"><i class="fas fa-print me-1"></i>Print</a></div>
    <div class="card-body"><form method="GET" class="row g-3">
        <div class="col-md-4"><label class="form-label">As of Date</label><input type="date" class="form-control" name="as_of_date" value="<?php echo $asOfDate; ?>"></div>
        <div class="col-md-4 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Generate</button></div>
    </form></div>
</div></div></div>

<div class="row mb-3">
    <div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body text-center"><h6>Total Assets</h6><h3><?php echo formatCurrency($totalAssets);?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-danger text-white"><div class="card-body text-center"><h6>Total Liabilities</h6><h3><?php echo formatCurrency($totalLiabilities);?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body text-center"><h6>Net Worth / Equity</h6><h3><?php echo formatCurrency($equity);?></h3></div></div></div>
    <div class="col-md-3"><div class="card <?php echo $netProfit >= 0 ? 'bg-success' : 'bg-danger'; ?> text-white"><div class="card-body text-center"><h6>Net Profit</h6><h3><?php echo formatCurrency($netProfit);?></h3></div></div></div>
</div>

<div class="row">
    <div class="col-md-6"><div class="card shadow-sm">
        <div class="card-header" style="background:#1a2332;color:#fff;"><i class="fas fa-arrow-up me-2"></i>Assets (Debit)</div>
        <div class="card-body"><table class="table table-borderless mb-0">
            <tr><td>Cash in Hand</td><td class="text-end fw-bold"><?php echo formatCurrency($netCash);?></td></tr>
            <tr><td>Bank Balances</td><td class="text-end fw-bold"><?php echo formatCurrency($bankBalance);?></td></tr>
            <tr><td>Raw Materials Stock</td><td class="text-end fw-bold"><?php echo formatCurrency($rmStock);?></td></tr>
            <tr><td>Finished Goods Stock</td><td class="text-end fw-bold"><?php echo formatCurrency($fgStock);?></td></tr>
            <tr><td>Party Receivable</td><td class="text-end fw-bold"><?php echo formatCurrency($partyReceivable);?></td></tr>
            <tr><td>Customer Outstanding</td><td class="text-end fw-bold"><?php echo formatCurrency($customerOutstanding);?></td></tr>
            <tr class="table-active"><td><strong>Total Assets</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalAssets);?></strong></td></tr>
        </table></div>
    </div></div>
    <div class="col-md-6"><div class="card shadow-sm">
        <div class="card-header" style="background:#1a2332;color:#fff;"><i class="fas fa-arrow-down me-2"></i>Liabilities (Credit)</div>
        <div class="card-body"><table class="table table-borderless mb-0">
            <tr><td>Party Payable</td><td class="text-end fw-bold"><?php echo formatCurrency($partyPayable);?></td></tr>
            <tr><td>Local Supplier Outstanding</td><td class="text-end fw-bold"><?php echo formatCurrency($supplierOutstanding);?></td></tr>
            <tr><td>Chinese Supplier Outstanding (PKR)</td><td class="text-end fw-bold"><?php echo formatCurrency($importOutstanding);?></td></tr>
            <tr><td>Employee Payable</td><td class="text-end fw-bold"><?php echo formatCurrency($empPayable);?></td></tr>
            <tr><td>Expense Outstanding</td><td class="text-end fw-bold"><?php echo formatCurrency($expenseOutstanding);?></td></tr>
            <tr class="table-warning"><td><strong>Net Profit (Equity)</strong></td><td class="text-end fw-bold"><?php echo formatCurrency($netProfit);?></td></tr>
            <tr class="table-active"><td><strong>Total Liabilities + Equity</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalLiabilities + $netProfit);?></strong></td></tr>
        </table></div>
    </div></div>
</div>

<?php include '../includes/footer.php'; ?>
