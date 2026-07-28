<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Employee Detail';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlash('Invalid employee ID!', 'danger');
    header('Location: employees.php');
    exit;
}

$empId = (int)$_GET['id'];
$emp = getRow("SELECT * FROM employees WHERE id = ?", 'i', [$empId]);
if (!$emp) {
    setFlash('Employee not found!', 'danger');
    header('Location: employees.php');
    exit;
}

$joiningDate = $emp['joining_date'] ? date('d-m-Y', strtotime($emp['joining_date'])) : 'N/A';

// Bills
$bills = getRows("SELECT * FROM employee_payables WHERE employee_id = ? ORDER BY id DESC", 'i', [$empId]);
$totalBills = 0;
$totalBillPaid = 0;
$totalBillBalance = 0;
foreach ($bills as $bill) {
    $totalBills += (float)$bill['amount'];
    $totalBillPaid += (float)$bill['paid_amount'];
    $totalBillBalance += (float)$bill['balance'];
}

// Payments
$payments = getRows("SELECT * FROM employee_payments WHERE employee_id = ? ORDER BY id DESC", 'i', [$empId]);
$totalPayments = 0;
$totalSalaryPaid = 0;
$totalAdvancePaid = 0;
$totalBonusPaid = 0;
foreach ($payments as $p) {
    $totalPayments += (float)$p['amount'];
    if ($p['payment_type'] === 'salary') $totalSalaryPaid += (float)$p['amount'];
    elseif ($p['payment_type'] === 'advance') $totalAdvancePaid += (float)$p['amount'];
    elseif ($p['payment_type'] === 'bonus') $totalBonusPaid += (float)$p['amount'];
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="row mb-3">
    <div class="col-12">
        <a href="employees.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Employees</a>
        <a href="add_employee.php?edit=<?php echo $emp['id']; ?>" class="btn btn-primary btn-sm ms-2"><i class="fas fa-edit me-1"></i>Edit Employee</a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header" style="background:#1a2332;color:#fff;">
                <i class="fas fa-user me-2"></i>Employee Information
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr><th style="width:40%">Name</th><td><strong><?php echo htmlspecialchars($emp['employee_name']); ?></strong></td></tr>
                    <tr><th>Code</th><td><?php echo htmlspecialchars($emp['employee_code'] ?: '-'); ?></td></tr>
                    <tr><th>Designation</th><td><?php echo htmlspecialchars($emp['designation'] ?: '-'); ?></td></tr>
                    <tr><th>Department</th><td><?php echo htmlspecialchars($emp['department'] ?: '-'); ?></td></tr>
                    <tr><th>Phone</th><td><?php echo htmlspecialchars($emp['phone'] ?: '-'); ?></td></tr>
                    <tr><th>Address</th><td><?php echo htmlspecialchars($emp['address'] ?: '-'); ?></td></tr>
                    <tr><th>City</th><td><?php echo htmlspecialchars($emp['city'] ?: '-'); ?></td></tr>
                    <tr><th>Joining Date</th><td><?php echo $joiningDate; ?></td></tr>
                    <tr><th>Monthly Salary</th><td><strong><?php echo formatCurrency($emp['monthly_salary']); ?></strong></td></tr>
                    <tr>
                        <th>Status</th>
                        <td><span class="badge badge-status badge-<?php echo $emp['status']; ?>"><?php echo ucfirst($emp['status']); ?></span></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card shadow-sm border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-1">Total Bills</h6>
                        <h4 class="mb-0 text-primary"><?php echo formatCurrency($totalBills); ?></h4>
                        <small class="text-muted"><?php echo count($bills); ?> bill(s)</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-1">Total Paid</h6>
                        <h4 class="mb-0 text-success"><?php echo formatCurrency($totalPayments); ?></h4>
                        <small class="text-muted"><?php echo count($payments); ?> payment(s)</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-1">Balance Due</h6>
                        <h4 class="mb-0 text-danger"><?php echo formatCurrency($totalBillBalance); ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-1">Current Balance</h6>
                        <h4 class="mb-0 text-info"><?php echo formatCurrency($emp['current_balance']); ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-1">Salary Paid</h6>
                        <h5 class="mb-0 text-primary"><?php echo formatCurrency($totalSalaryPaid); ?></h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-1">Advances Paid</h6>
                        <h5 class="mb-0 text-warning"><?php echo formatCurrency($totalAdvancePaid); ?></h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-1">Bonuses Paid</h6>
                        <h5 class="mb-0 text-success"><?php echo formatCurrency($totalBonusPaid); ?></h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header" style="background:#1a2332;color:#fff;">
                <i class="fas fa-file-invoice me-2"></i>Payable Bills
            </div>
            <div class="card-body">
                <?php if (count($bills) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr><th>Bill No</th><th>Month</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bills as $bill): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($bill['bill_no']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($bill['month_year']); ?></td>
                                        <td><?php echo formatCurrency($bill['amount']); ?></td>
                                        <td><?php echo formatCurrency($bill['paid_amount']); ?></td>
                                        <td>
                                            <?php if ((float)$bill['balance'] > 0): ?>
                                                <span class="text-danger"><?php echo formatCurrency($bill['balance']); ?></span>
                                            <?php else: ?>
                                                <span class="text-success">0.00</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($bill['payment_status'] === 'paid'): ?>
                                                <span class="badge bg-success">Paid</span>
                                            <?php elseif ($bill['payment_status'] === 'partial'): ?>
                                                <span class="badge bg-warning text-dark">Partial</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Unpaid</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center mb-0">No bills found</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header" style="background:#1a2332;color:#fff;">
                <i class="fas fa-money-bill-wave me-2"></i>Payment History
            </div>
            <div class="card-body">
                <?php if (count($payments) > 0): ?>
                    <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr><th>Date</th><th>Payment No</th><th>Type</th><th>Amount</th><th>Bill Ref</th><th>Notes</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td><?php echo date('d-m-Y', strtotime($p['payment_date'])); ?></td>
                                        <td><strong><?php echo htmlspecialchars($p['payment_no']); ?></strong></td>
                                        <td><span class="badge bg-secondary"><?php echo ucfirst($p['payment_type']); ?></span></td>
                                        <td><strong><?php echo formatCurrency($p['amount']); ?></strong></td>
                                        <td>
                                            <?php if (!empty($p['payable_id']) && $p['payable_id'] > 0): ?>
                                                <?php $bill = getRow("SELECT bill_no FROM employee_payables WHERE id = ?", 'i', [$p['payable_id']]); ?>
                                                <?php if ($bill): ?>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($bill['bill_no']); ?></span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Direct</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?php echo htmlspecialchars($p['notes'] ?: '-'); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center mb-0">No payments found</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
