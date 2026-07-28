<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Employee Payments';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $payment = getRow("SELECT id, employee_id, amount, payable_id FROM employee_payments WHERE id = ?", 'i', [$id]);
    if ($payment) {
        modifyData("UPDATE employees SET current_balance = current_balance + ? WHERE id = ?", 'di', [$payment['amount'], $payment['employee_id']]);
        if ($payment['payable_id'] && $payment['payable_id'] > 0) {
            modifyData("UPDATE employee_payables SET paid_amount = paid_amount - ?, balance = balance + ?, payment_status = CASE WHEN paid_amount - ? <= 0 THEN 'unpaid' WHEN paid_amount - ? < amount THEN 'partial' ELSE payment_status END WHERE id = ?",
                'dddddi', [$payment['amount'], $payment['amount'], $payment['amount'], $payment['amount'], $payment['payable_id']]);
        }
        modifyData("DELETE FROM employee_payments WHERE id = ?", 'i', [$id]);
        setFlash('Payment deleted successfully!', 'success');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle POST - New Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = (int)$_POST['employee_id'];
    $payable_id = isset($_POST['payable_id']) ? (int)$_POST['payable_id'] : 0;
    $payment_date = $_POST['payment_date'];
    $amount = (float)$_POST['amount'];
    $month_year = trim($_POST['month_year']);
    $payment_type = $_POST['payment_type'];
    $notes = trim($_POST['notes']);

    if (empty($employee_id)) {
        $message = 'Please select an employee!';
        $messageType = 'danger';
    } elseif (empty($payment_date)) {
        $message = 'Please select payment date!';
        $messageType = 'danger';
    } elseif ($amount <= 0) {
        $message = 'Amount must be greater than 0!';
        $messageType = 'danger';
    } else {
        $currentDateTime = getCurrentDateTime();
        $payment_no = generateCode('EP');

        $result = insertData("INSERT INTO employee_payments (payment_no, employee_id, payment_date, amount, month_year, payment_type, notes, payable_id, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            'sisdsssis', [$payment_no, $employee_id, $payment_date, $amount, $month_year, $payment_type, $notes, $payable_id > 0 ? $payable_id : null, $currentDateTime]);

        if ($result) {
            modifyData("UPDATE employees SET current_balance = current_balance - ? WHERE id = ?", 'di', [$amount, $employee_id]);

            // Update payable bill
            if ($payable_id > 0) {
                modifyData("UPDATE employee_payables SET paid_amount = paid_amount + ?, balance = balance - ?, payment_status = CASE WHEN paid_amount + ? >= amount THEN 'paid' ELSE 'partial' END WHERE id = ?",
                    'ddddi', [$amount, $amount, $amount, $payable_id]);
            }

            setFlash('Payment of ' . formatCurrency($amount) . ' made successfully!', 'success');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $message = 'Error adding payment!';
            $messageType = 'danger';
        }
    }
}

// Summary data
$totalPayments = getRow("SELECT COALESCE(SUM(amount), 0) AS total FROM employee_payments");
$totalSalary = getRow("SELECT COALESCE(SUM(amount), 0) AS total FROM employee_payments WHERE payment_type = 'salary'");
$totalAdvances = getRow("SELECT COALESCE(SUM(amount), 0) AS total FROM employee_payments WHERE payment_type = 'advance'");
$totalBonuses = getRow("SELECT COALESCE(SUM(amount), 0) AS total FROM employee_payments WHERE payment_type = 'bonus'");

$employees = getRows("SELECT id, employee_name, employee_code, current_balance FROM employees WHERE status = 'active' ORDER BY employee_name");

// Unpaid/partial bills for dropdown
$unpaidBills = getRows("SELECT pb.*, e.employee_name, e.employee_code
    FROM employee_payables pb
    LEFT JOIN employees e ON pb.employee_id = e.id
    WHERE pb.payment_status IN ('unpaid','partial')
    ORDER BY pb.employee_id, pb.id DESC");

$payments = getRows("SELECT p.*, e.employee_name, e.employee_code
    FROM employee_payments p
    LEFT JOIN employees e ON p.employee_id = e.id
    ORDER BY p.id DESC");

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="row mb-3">
    <div class="col-12">
        <a href="employees.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Employees</a>
        <a href="employee_payable.php" class="btn btn-outline-primary btn-sm ms-2"><i class="fas fa-file-invoice me-1"></i>View Payable</a>
    </div>
</div>

<?php if ($message): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Total Payments Made</h6>
                <h3 class="mb-0"><?php echo formatCurrency($totalPayments['total']); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Total Salary Paid</h6>
                <h3 class="mb-0 text-primary"><?php echo formatCurrency($totalSalary['total']); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Total Advances</h6>
                <h3 class="mb-0 text-warning"><?php echo formatCurrency($totalAdvances['total']); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Total Bonuses</h6>
                <h3 class="mb-0 text-success"><?php echo formatCurrency($totalBonuses['total']); ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header" style="background:#1a2332;color:#fff;">
                <i class="fas fa-money-bill-wave me-2"></i>Employee Payment History
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="paymentTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Payment No</th>
                                <th>Employee</th>
                                <th>Date</th>
                                <th>Month</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Bill Ref</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($payments as $p): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($p['payment_no']); ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($p['employee_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($p['employee_code']); ?></small>
                                    </td>
                                    <td><?php echo date('d-m-Y', strtotime($p['payment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($p['month_year']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo ucfirst($p['payment_type']); ?></span></td>
                                    <td><strong><?php echo formatCurrency($p['amount']); ?></strong></td>
                                    <td>
                                        <?php if (!empty($p['payable_id']) && $p['payable_id'] > 0): ?>
                                            <?php $bill = getRow("SELECT bill_no FROM employee_payables WHERE id = ?", 'i', [$p['payable_id']]); ?>
                                            <?php if ($bill): ?>
                                                <a href="employee_payable.php" class="badge bg-info"><?php echo htmlspecialchars($bill['bill_no']); ?></a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($p['notes'] ?: '-'); ?></td>
                                    <td>
                                        <a href="?delete=<?php echo $p['id']; ?>" class="btn btn-sm btn-danger delete-confirm" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a2332;color:#fff;">
                <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i>Make New Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Pay Against Bill (Optional)</label>
                        <select class="form-select" id="payableSelect" name="payable_id">
                            <option value="">Direct Payment (no bill)</option>
                            <?php foreach ($unpaidBills as $bill): ?>
                                <option value="<?php echo $bill['id']; ?>"
                                    data-employee="<?php echo $bill['employee_id']; ?>"
                                    data-balance="<?php echo $bill['balance']; ?>"
                                    data-employee-name="<?php echo htmlspecialchars($bill['employee_name']); ?>">
                                    <?php echo htmlspecialchars($bill['bill_no']); ?> - <?php echo htmlspecialchars($bill['employee_name']); ?> - <?php echo htmlspecialchars($bill['month_year']); ?> - Bal: <?php echo formatCurrency($bill['balance']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Employee *</label>
                        <select class="form-select" id="empSelect" name="employee_id" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" data-balance="<?php echo $emp['current_balance']; ?>">
                                    <?php echo htmlspecialchars($emp['employee_name']); ?> (<?php echo htmlspecialchars($emp['employee_code']); ?>) - Bal: <?php echo formatCurrency($emp['current_balance']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Date *</label>
                            <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount (PKR) *</label>
                            <input type="number" step="0.01" class="form-control" id="paymentAmount" name="amount" min="0.01" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Month/Year</label>
                            <input type="text" class="form-control" name="month_year" placeholder="e.g. Jul-2026" value="<?php echo date('M-Y'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Type *</label>
                            <select class="form-select" name="payment_type" required>
                                <option value="salary">Salary</option>
                                <option value="advance">Advance</option>
                                <option value="bonus">Bonus</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Submit Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#paymentTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        language: { search: 'Search Payments:', emptyTable: 'No payments found' }
    });

    // When bill is selected, auto-fill employee and amount
    $('#payableSelect').on('change', function() {
        var opt = $(this).find(':selected');
        var empId = opt.data('employee');
        var balance = opt.data('balance');
        if (empId) {
            $('#empSelect').val(empId);
            if (balance) $('#paymentAmount').val(parseFloat(balance).toFixed(2));
        }
    });

    $('.delete-confirm').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this payment?')) e.preventDefault();
    });
});
</script>

<?php include '../includes/footer.php'; ?>
