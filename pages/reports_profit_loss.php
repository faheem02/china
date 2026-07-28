<?php
require_once '../includes/database.php';
requireLogin();
$pageTitle = 'Profit & Loss Statement';

$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';

$totalSales = getRow("SELECT COALESCE(SUM(total_amount),0) AS total FROM sales WHERE DATE(date_time) BETWEEN ? AND ?", 'ss', [$fromDate, $toDate]);
$totalSalePaid = getRow("SELECT COALESCE(SUM(paid_amount),0) AS total FROM sales WHERE DATE(date_time) BETWEEN ? AND ?", 'ss', [$fromDate, $toDate]);
$totalSaleBalance = (float)$totalSales['total'] - (float)$totalSalePaid['total'];

$totalLocalPurchase = getRow("SELECT COALESCE(SUM(total_amount),0) AS total FROM local_purchases WHERE DATE(date_time) BETWEEN ? AND ?", 'ss', [$fromDate, $toDate]);
$totalImportPurchasePKR = getRow("SELECT COALESCE(SUM(grand_total_pkr),0) AS total FROM import_purchases WHERE DATE(date_time) BETWEEN ? AND ?", 'ss', [$fromDate, $toDate]);
$totalPurchases = (float)$totalLocalPurchase['total'] + (float)$totalImportPurchasePKR['total'];

$totalExpenses = getRow("SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE DATE(date_time) BETWEEN ? AND ?", 'ss', [$fromDate, $toDate]);
$totalEmpPayments = getRow("SELECT COALESCE(SUM(amount),0) AS total FROM employee_payments WHERE DATE(date_time) BETWEEN ? AND ?", 'ss', [$fromDate, $toDate]);

$grossProfit = $totalSales['total'] - $totalPurchases;
$netProfit = $grossProfit - (float)$totalExpenses['total'] - (float)$totalEmpPayments['total'];

// Monthly breakdown
$monthlyData = getRows("SELECT DATE_FORMAT(date_time, '%Y-%m') AS month_label,
    SUM(total_amount) AS sales,
    (SELECT COALESCE(SUM(total_amount),0) FROM local_purchases WHERE DATE_FORMAT(date_time, '%Y-%m') = month_label) AS local_purchase,
    (SELECT COALESCE(SUM(grand_total_pkr),0) FROM import_purchases WHERE DATE_FORMAT(date_time, '%Y-%m') = month_label) AS import_purchase,
    (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE DATE_FORMAT(date_time, '%Y-%m') = month_label) AS expenses
    FROM sales WHERE DATE(date_time) BETWEEN ? AND ? GROUP BY month_label ORDER BY month_label", 'ss', [$fromDate, $toDate]);
?>

<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-filter me-2"></i>Filter</span>
                <a href="profit_loss_print.php?from_date=<?php echo urlencode($fromDate); ?>&to_date=<?php echo urlencode($toDate); ?>" target="_blank" class="btn btn-danger btn-sm"><i class="fas fa-print me-1"></i>Print</a>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4"><label class="form-label">From Date</label><input type="date" class="form-control" name="from_date" value="<?php echo $fromDate; ?>"></div>
                    <div class="col-md-4"><label class="form-label">To Date</label><input type="date" class="form-control" name="to_date" value="<?php echo $toDate; ?>"></div>
                    <div class="col-md-4 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Generate</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-4"><div class="card <?php echo $netProfit >= 0 ? 'bg-success' : 'bg-danger'; ?> text-white"><div class="card-body text-center"><h5>Net Profit</h5><h2><?php echo formatCurrency($netProfit); ?></h2></div></div></div>
    <div class="col-md-4"><div class="card bg-primary text-white"><div class="card-body text-center"><h5>Gross Profit</h5><h2><?php echo formatCurrency($grossProfit); ?></h2></div></div></div>
    <div class="col-md-4"><div class="card bg-info text-white"><div class="card-body text-center"><h5>Gross Margin</h5><h2><?php echo $totalSales['total'] > 0 ? number_format(($grossProfit / $totalSales['total']) * 100, 1) . '%' : '0%'; ?></h2></div></div></div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header" style="background:#1a2332;color:#fff;"><i class="fas fa-arrow-up me-2 text-success"></i>Income</div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr><td>Sales Revenue</td><td class="text-end fw-bold text-success"><?php echo formatCurrency($totalSales['total']); ?></td></tr>
                    <tr><td>Cash Received</td><td class="text-end"><?php echo formatCurrency($totalSalePaid['total']); ?></td></tr>
                    <tr><td>Outstanding Receivable</td><td class="text-end text-warning"><?php echo formatCurrency($totalSaleBalance); ?></td></tr>
                    <tr class="table-active"><td><strong>Total Income</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalSales['total']); ?></strong></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header" style="background:#1a2332;color:#fff;"><i class="fas fa-arrow-down me-2 text-danger"></i>Expenses & Cost of Goods</div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr><td>Local Purchases</td><td class="text-end"><?php echo formatCurrency($totalLocalPurchase['total']); ?></td></tr>
                    <tr><td>Import Purchases (PKR)</td><td class="text-end"><?php echo formatCurrency($totalImportPurchasePKR['total']); ?></td></tr>
                    <tr class="table-warning"><td><strong>Cost of Goods Sold</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalPurchases); ?></strong></td></tr>
                    <tr><td>Operating Expenses</td><td class="text-end"><?php echo formatCurrency($totalExpenses['total']); ?></td></tr>
                    <tr><td>Employee Payments</td><td class="text-end"><?php echo formatCurrency($totalEmpPayments['total']); ?></td></tr>
                    <tr class="table-active"><td><strong>Total Expenses</strong></td><td class="text-end"><strong><?php echo formatCurrency($totalPurchases + (float)$totalExpenses['total'] + (float)$totalEmpPayments['total']); ?></strong></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (count($monthlyData) > 0): ?>
<div class="row mt-3">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header"><i class="fas fa-calendar me-2"></i>Monthly Breakdown</div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead><tr><th>Month</th><th class="text-end">Sales</th><th class="text-end">Purchases</th><th class="text-end">Expenses</th><th class="text-end">Gross Profit</th></tr></thead>
                    <tbody>
                        <?php foreach ($monthlyData as $m): $gp = (float)$m['sales'] - (float)$m['local_purchase'] - (float)$m['import_purchase']; ?>
                        <tr>
                            <td><strong><?php echo date('M-Y', strtotime($m['month_label'] . '-01')); ?></strong></td>
                            <td class="text-end text-success"><?php echo formatCurrency($m['sales']); ?></td>
                            <td class="text-end"><?php echo formatCurrency((float)$m['local_purchase'] + (float)$m['import_purchase']); ?></td>
                            <td class="text-end text-danger"><?php echo formatCurrency($m['expenses']); ?></td>
                            <td class="text-end fw-bold <?php echo $gp >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatCurrency($gp); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
