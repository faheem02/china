<?php
require_once '../includes/database.php';
requireLogin();
$pageTitle = 'Customer Reports';

$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';

$customers = getRows("SELECT c.*, COALESCE(SUM(s.total_amount),0) AS total_sales, COALESCE(SUM(s.paid_amount),0) AS total_paid FROM customers c LEFT JOIN sales s ON s.customer_id = c.id GROUP BY c.id ORDER BY c.customer_name");
$totalCust = count($customers);
$totalSalesAmt = array_sum(array_column($customers, 'total_sales'));
$totalReceived = array_sum(array_column($customers, 'total_paid'));
$totalOutstanding = $totalSalesAmt - $totalReceived;

$periodSales = getRows("SELECT s.*, c.customer_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE DATE(s.date_time) BETWEEN ? AND ? ORDER BY s.date_time DESC", 'ss', [$fromDate, $toDate]);
$periodTotal = array_sum(array_column($periodSales, 'total_amount'));
$periodPaid = array_sum(array_column($periodSales, 'paid_amount'));

$topCustomers = getRows("SELECT c.customer_name, SUM(s.total_amount) AS total_sales, SUM(s.paid_amount) AS total_paid, (SUM(s.total_amount) - SUM(s.paid_amount)) AS balance FROM customers c LEFT JOIN sales s ON s.customer_id = c.id GROUP BY c.id ORDER BY total_sales DESC LIMIT 10");
?>

<div class="row mb-3"><div class="col-12"><div class="card">
    <div class="card-header d-flex justify-content-between align-items-center"><span><i class="fas fa-filter me-2"></i>Filter</span><button onclick="window.print()" class="btn btn-danger btn-sm"><i class="fas fa-print me-1"></i>Print</button></div>
    <div class="card-body"><form method="GET" class="row g-3">
        <div class="col-md-4"><label class="form-label">From</label><input type="date" class="form-control" name="from_date" value="<?php echo $fromDate; ?>"></div>
        <div class="col-md-4"><label class="form-label">To</label><input type="date" class="form-control" name="to_date" value="<?php echo $toDate; ?>"></div>
        <div class="col-md-4 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Generate</button></div>
    </form></div>
</div></div></div>

<div class="row mb-3">
    <div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body text-center"><h6>Total Customers</h6><h3><?php echo $totalCust;?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body text-center"><h6>Total Sales</h6><h3><?php echo formatCurrency($totalSalesAmt);?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-info text-white"><div class="card-body text-center"><h6>Total Received</h6><h3><?php echo formatCurrency($totalReceived);?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-danger text-white"><div class="card-body text-center"><h6>Total Outstanding</h6><h3><?php echo formatCurrency($totalOutstanding);?></h3></div></div></div>
</div>

<div class="row mb-3">
    <div class="col-md-3"><div class="card border-primary"><div class="card-body text-center"><h6 class="text-muted">Period Sales</h6><h5 class="text-primary"><?php echo formatCurrency($periodTotal);?></h5></div></div></div>
    <div class="col-md-3"><div class="card border-success"><div class="card-body text-center"><h6 class="text-muted">Period Received</h6><h5 class="text-success"><?php echo formatCurrency($periodPaid);?></h5></div></div></div>
    <div class="col-md-6"><div class="card border-danger"><div class="card-body text-center"><h6 class="text-muted">All Customers Outstanding</h6><h5 class="text-danger"><?php echo formatCurrency($totalOutstanding);?></h5></div></div></div>
</div>

<div class="row mb-3"><div class="col-md-6"><div class="card shadow-sm">
    <div class="card-header"><i class="fas fa-trophy me-2"></i>Top 10 Customers</div>
    <div class="card-body"><table class="table table-sm table-striped"><thead><tr><th>#</th><th>Customer</th><th class="text-end">Sales</th><th class="text-end">Received</th><th class="text-end">Balance</th></tr></thead><tbody>
    <?php $i=1; foreach($topCustomers as $tc):?>
    <tr><td><?php echo $i++;?></td><td><strong><?php echo htmlspecialchars($tc['customer_name']);?></strong></td><td class="text-end"><?php echo formatCurrency($tc['total_sales']);?></td><td class="text-end"><?php echo formatCurrency($tc['total_paid']);?></td><td class="text-end fw-bold text-danger"><?php echo formatCurrency($tc['balance']);?></td></tr>
    <?php endforeach;?></tbody></table></div>
</div></div>

<div class="col-md-6"><div class="card shadow-sm">
    <div class="card-header"><i class="fas fa-list me-2"></i>All Customers</div>
    <div class="card-body" style="max-height:400px;overflow-y:auto;"><table class="table table-sm table-striped"><thead><tr><th>Customer</th><th>Phone</th><th class="text-end">Sales</th><th class="text-end">Balance</th></tr></thead><tbody>
    <?php foreach($customers as $c): $bal = (float)$c['total_sales'] - (float)$c['total_paid'];?>
    <tr><td><?php echo htmlspecialchars($c['customer_name']);?></td><td><?php echo htmlspecialchars($c['phone']?:'-');?></td><td class="text-end"><?php echo formatCurrency($c['total_sales']);?></td><td class="text-end fw-bold <?php echo $bal > 0 ? 'text-danger' : 'text-success';?>"><?php echo formatCurrency($bal);?></td></tr>
    <?php endforeach;?></tbody></table></div>
</div></div></div>

<div class="row"><div class="col-12"><div class="card shadow-sm">
    <div class="card-header"><i class="fas fa-file-invoice me-2"></i>Period Sales Details</div>
    <div class="card-body"><div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Date</th><th>Invoice</th><th>Customer</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th>Status</th></tr></thead><tbody>
    <?php foreach($periodSales as $ps):?>
    <tr><td><?php echo date('d-m-Y', strtotime($ps['date_time']));?></td><td><strong><?php echo htmlspecialchars($ps['invoice_no']);?></strong></td><td><?php echo htmlspecialchars($ps['customer_name']);?></td><td class="text-end"><?php echo formatCurrency($ps['total_amount']);?></td><td class="text-end"><?php echo formatCurrency($ps['paid_amount']);?></td><td class="text-end fw-bold text-danger"><?php echo formatCurrency($ps['balance']);?></td><td><span class="badge bg-<?php echo $ps['payment_status']==='paid'?'success':($ps['payment_status']==='partial'?'warning':'danger');?>"><?php echo ucfirst($ps['payment_status']);?></span></td></tr>
    <?php endforeach;?></tbody></table></div></div>
</div></div></div>

<?php include '../includes/footer.php'; ?>
