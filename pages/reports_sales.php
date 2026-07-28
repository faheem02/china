<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Sales Reports';

$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';

$salesSql = "SELECT s.*, c.customer_name,
             (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) AS total_items
             FROM sales s 
             LEFT JOIN customers c ON s.customer_id = c.id 
             WHERE DATE(s.date_time) BETWEEN ? AND ? 
             ORDER BY s.date_time DESC";
$sales = getRows($salesSql, 'ss', [$fromDate, $toDate]);

$totalSales = array_sum(array_column($sales, 'total_amount'));
$totalPaid = array_sum(array_column($sales, 'paid_amount'));
$totalBalance = array_sum(array_column($sales, 'balance'));
$totalItems = array_sum(array_column($sales, 'total_items'));
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
                    <div class="col-md-4">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" name="from_date" value="<?php echo $fromDate; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" name="to_date" value="<?php echo $toDate; ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
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
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h5>Total Sales</h5>
                <h3><?php echo formatCurrency($totalSales); ?></h3>
                <small>PKR</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h5>Total Items</h5>
                <h3><?php echo $totalItems; ?></h3>
                <small>Units Sold</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h5>Total Received</h5>
                <h3><?php echo formatCurrency($totalPaid); ?></h3>
                <small>PKR</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <h5>Outstanding</h5>
                <h3><?php echo formatCurrency($totalBalance); ?></h3>
                <small>PKR</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-line me-2"></i>Sales Details
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td><?php echo date('d-m-Y', strtotime($sale['date_time'])); ?></td>
                                <td><?php echo htmlspecialchars($sale['invoice_no']); ?></td>
                                <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                                <td class="text-center"><?php echo $sale['total_items']; ?></td>
                                <td class="text-end"><?php echo formatCurrency($sale['total_amount']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($sale['paid_amount']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($sale['balance']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $sale['payment_status'] == 'paid' ? 'success' : ($sale['payment_status'] == 'partial' ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst($sale['payment_status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-info">
                                <th colspan="4" class="text-end">Total</th>
                                <th class="text-end"><?php echo formatCurrency($totalSales); ?></th>
                                <th class="text-end"><?php echo formatCurrency($totalPaid); ?></th>
                                <th class="text-end"><?php echo formatCurrency($totalBalance); ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
