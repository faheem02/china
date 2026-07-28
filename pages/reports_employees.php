<?php
require_once '../includes/database.php';
requireLogin();
$pageTitle = 'Employee Reports';

$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';

$employees = getRows("SELECT e.*,
    COALESCE((SELECT SUM(ep.amount) FROM employee_payments ep WHERE ep.employee_id = e.id), 0) AS total_paid
    FROM employees e
    ORDER BY e.employee_name");
$totalEmp = count($employees);
$totalSalary = 0; $totalPaid = 0;
foreach($employees as $e) { $totalSalary += (float)$e['monthly_salary']; $totalPaid += (float)$e['total_paid']; }

$periodPayments = getRows("SELECT ep.*, e.employee_name, e.employee_code FROM employee_payments ep LEFT JOIN employees e ON ep.employee_id = e.id WHERE ep.payment_date BETWEEN ? AND ? ORDER BY ep.payment_date DESC", 'ss', [$fromDate, $toDate]);
$periodTotal = array_sum(array_column($periodPayments, 'amount'));

$salaryPayments = getRows("SELECT ep.*, e.employee_name FROM employee_payments ep LEFT JOIN employees e ON ep.employee_id = e.id WHERE ep.payment_type='salary' AND ep.payment_date BETWEEN ? AND ? ORDER BY ep.payment_date DESC", 'ss', [$fromDate, $toDate]);
$advancePayments = getRows("SELECT ep.*, e.employee_name FROM employee_payments ep LEFT JOIN employees e ON ep.employee_id = e.id WHERE ep.payment_type='advance' AND ep.payment_date BETWEEN ? AND ? ORDER BY ep.payment_date DESC", 'ss', [$fromDate, $toDate]);
$bonusPayments = getRows("SELECT ep.*, e.employee_name FROM employee_payments ep LEFT JOIN employees e ON ep.employee_id = e.id WHERE ep.payment_type='bonus' AND ep.payment_date BETWEEN ? AND ? ORDER BY ep.payment_date DESC", 'ss', [$fromDate, $toDate]);

$totalSalaryPaid = array_sum(array_column($salaryPayments, 'amount'));
$totalAdvancePaid = array_sum(array_column($advancePayments, 'amount'));
$totalBonusPaid = array_sum(array_column($bonusPayments, 'amount'));

$unpaidBills = getRows("SELECT pb.*, e.employee_name FROM employee_payables pb LEFT JOIN employees e ON pb.employee_id = e.id WHERE pb.payment_status != 'paid' ORDER BY pb.id DESC");
$totalUnpaidBills = array_sum(array_column($unpaidBills, 'balance'));
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
    <div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body text-center"><h6>Total Employees</h6><h3><?php echo $totalEmp;?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-info text-white"><div class="card-body text-center"><h6>Monthly Salaries</h6><h3><?php echo formatCurrency($totalSalary);?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body text-center"><h6>Paid (Period)</h6><h3><?php echo formatCurrency($periodTotal);?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-danger text-white"><div class="card-body text-center"><h6>Pending Bills</h6><h3><?php echo formatCurrency($totalUnpaidBills);?></h3></div></div></div>
</div>

<div class="row mb-3">
    <div class="col-md-3"><div class="card border-primary"><div class="card-body text-center"><h6 class="text-muted">Salary Paid</h6><h5 class="text-primary"><?php echo formatCurrency($totalSalaryPaid);?></h5></div></div></div>
    <div class="col-md-3"><div class="card border-warning"><div class="card-body text-center"><h6 class="text-muted">Advances</h6><h5 class="text-warning"><?php echo formatCurrency($totalAdvancePaid);?></h5></div></div></div>
    <div class="col-md-3"><div class="card border-success"><div class="card-body text-center"><h6 class="text-muted">Bonuses</h6><h5 class="text-success"><?php echo formatCurrency($totalBonusPaid);?></h5></div></div></div>
    <div class="col-md-3"><div class="card border-danger"><div class="card-body text-center"><h6 class="text-muted">Total Paid All Time</h6><h5 class="text-danger"><?php echo formatCurrency($totalPaid);?></h5></div></div></div>
</div>

<div class="row mb-3"><div class="col-12"><div class="card shadow-sm">
    <div class="card-header"><i class="fas fa-users me-2"></i>Employee Summary</div>
    <div class="card-body"><div class="table-responsive"><table class="table table-striped table-hover"><thead><tr><th>Code</th><th>Name</th><th>Designation</th><th>Department</th><th class="text-end">Salary</th><th class="text-end">Total Paid</th><th class="text-end">Payable</th><th>Status</th></tr></thead><tbody>
    <?php foreach($employees as $e): $payable = (float)$e['monthly_salary'] - (float)$e['total_paid'];?>
    <tr><td><?php echo htmlspecialchars($e['employee_code']?:'-');?></td><td><strong><?php echo htmlspecialchars($e['employee_name']);?></strong></td><td><?php echo htmlspecialchars($e['designation']?:'-');?></td><td><?php echo htmlspecialchars($e['department']?:'-');?></td><td class="text-end"><?php echo formatCurrency($e['monthly_salary']);?></td><td class="text-end"><?php echo formatCurrency($e['total_paid']);?></td><td class="text-end"><?php if($payable > 0):?><span class="badge bg-danger"><?php echo formatCurrency($payable);?></span><?php elseif($payable < 0):?><span class="badge bg-info"><?php echo formatCurrency(abs($payable));?> adv</span><?php else:?><span class="badge bg-success">Paid</span><?php endif;?></td><td><span class="badge badge-status badge-<?php echo $e['status'];?>"><?php echo ucfirst($e['status']);?></span></td></tr>
    <?php endforeach;?></tbody></table></div></div>
</div></div></div>

<div class="row"><div class="col-12"><div class="card shadow-sm">
    <div class="card-header"><i class="fas fa-money-bill-wave me-2"></i>Payment History (Period)</div>
    <div class="card-body"><div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Date</th><th>Voucher</th><th>Employee</th><th>Type</th><th class="text-end">Amount</th><th>Notes</th></tr></thead><tbody>
    <?php foreach($periodPayments as $pp):?>
    <tr><td><?php echo date('d-m-Y', strtotime($pp['payment_date']));?></td><td><strong><?php echo htmlspecialchars($pp['payment_no']);?></strong></td><td><?php echo htmlspecialchars($pp['employee_name']);?></td><td><span class="badge bg-secondary"><?php echo ucfirst($pp['payment_type']);?></span></td><td class="text-end fw-bold"><?php echo formatCurrency($pp['amount']);?></td><td><?php echo htmlspecialchars($pp['notes']?:'-');?></td></tr>
    <?php endforeach;?></tbody></table></div></div>
</div></div></div>

<?php include '../includes/footer.php'; ?>
