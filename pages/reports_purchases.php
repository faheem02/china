<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Purchase Reports';

$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');
$reportType = $_GET['report_type'] ?? 'all';

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';

// Local Purchases
$localSql = "SELECT lp.*, ls.supplier_name,
             (SELECT COUNT(*) FROM local_purchase_items WHERE purchase_id = lp.id) AS total_items
             FROM local_purchases lp 
             LEFT JOIN local_suppliers ls ON lp.supplier_id = ls.id 
             WHERE DATE(lp.date_time) BETWEEN ? AND ? 
             ORDER BY lp.date_time DESC";
$localPurchases = getRows($localSql, 'ss', [$fromDate, $toDate]);

// Import Purchases
$importSql = "SELECT ip.*, cs.supplier_name,
             (SELECT COUNT(*) FROM import_purchase_items WHERE purchase_id = ip.id) AS total_items
             FROM import_purchases ip 
              LEFT JOIN chinese_suppliers cs ON ip.supplier_id = cs.id 
              WHERE DATE(ip.date_time) BETWEEN ? AND ? 
              ORDER BY ip.date_time DESC";
$importPurchases = getRows($importSql, 'ss', [$fromDate, $toDate]);

// Totals
$localTotal = array_sum(array_column($localPurchases, 'total_amount'));
$importTotal = array_sum(array_column($importPurchases, 'grand_total_pkr'));
$importTotalCny = array_sum(array_column($importPurchases, 'grand_total_cny'));
?>

<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-filter me-2"></i>Filter</span>
                <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" name="from_date" value="<?php echo $fromDate; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" name="to_date" value="<?php echo $toDate; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Report Type</label>
                        <select class="form-select" name="report_type">
                            <option value="all" <?php echo $reportType == 'all' ? 'selected' : ''; ?>>All Purchases</option>
                            <option value="local" <?php echo $reportType == 'local' ? 'selected' : ''; ?>>Local Only</option>
                            <option value="import" <?php echo $reportType == 'import' ? 'selected' : ''; ?>>Import Only</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Generate Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h5>Local Purchases</h5>
                <h3><?php echo formatCurrency($localTotal); ?></h3>
                <small>PKR</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h5>Import Purchases</h5>
                <h3><?php echo formatCurrency($importTotal); ?></h3>
                <small>PKR Equivalent</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h5>Total Purchases</h5>
                <h3><?php echo formatCurrency($localTotal + $importTotal); ?></h3>
                <small>PKR</small>
            </div>
        </div>
    </div>
</div>

<?php if ($reportType == 'all' || $reportType == 'local'): ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-shopping-bag me-2"></i>Local Purchases (PKR)
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Ref #</th>
                                <th>Supplier</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($localPurchases as $lp): ?>
                            <tr>
                                <td><?php echo date('d-m-Y', strtotime($lp['date_time'])); ?></td>
                                <td><?php echo htmlspecialchars($lp['purchase_no']); ?></td>
                                <td><?php echo htmlspecialchars($lp['supplier_name']); ?></td>
                                <td class="text-center"><?php echo $lp['total_items']; ?></td>
                                <td class="text-end"><?php echo formatCurrency($lp['total_amount']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($lp['paid_amount']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($lp['balance']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $lp['payment_status'] == 'paid' ? 'success' : ($lp['payment_status'] == 'partial' ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst($lp['payment_status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-info">
                                <th colspan="4" class="text-end">Total</th>
                                <th class="text-end"><?php echo formatCurrency($localTotal); ?></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($reportType == 'all' || $reportType == 'import'): ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-ship me-2"></i>Import Purchases (CNY / PKR)
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Ref #</th>
                                <th>Supplier</th>
                                <th>Items</th>
                                <th>Total (CNY)</th>
                                <th>Rate</th>
                                <th>Total (PKR)</th>
                                <th>Paid (CNY)</th>
                                <th>Balance (CNY)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($importPurchases as $ip): ?>
                            <tr>
                                <td><?php echo date('d-m-Y', strtotime($ip['date_time'])); ?></td>
                                <td><?php echo htmlspecialchars($ip['purchase_no']); ?></td>
                                <td><?php echo htmlspecialchars($ip['supplier_name']); ?></td>
                                <td class="text-center"><?php echo $ip['total_items']; ?></td>
                                <td class="text-end"><?php echo number_format($ip['grand_total_cny'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($ip['exchange_rate'], 4); ?></td>
                                <td class="text-end"><?php echo formatCurrency($ip['grand_total_pkr']); ?></td>
                                <td class="text-end"><?php echo number_format($ip['paid_amount_cny'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($ip['balance_cny'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $ip['payment_status'] == 'paid' ? 'success' : ($ip['payment_status'] == 'partial' ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst($ip['payment_status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-info">
                                <th colspan="4" class="text-end">Total</th>
                                <th class="text-end"><?php echo number_format($importTotalCny, 2); ?> CNY</th>
                                <th></th>
                                <th class="text-end"><?php echo formatCurrency($importTotal); ?></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
