<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Employee Salary Ledger';
$message = '';
$messageType = '';
$employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : (int)($_POST['employee_id'] ?? 0);

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

$employee = $employeeId > 0
    ? getRow("SELECT * FROM employees WHERE id = ?", 'i', [$employeeId])
    : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_payment'])) {
    $payableId = (int)($_POST['payable_id'] ?? 0);
    $paymentDate = trim($_POST['payment_date'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $paymentType = trim($_POST['payment_type'] ?? '');
    $monthYear = trim($_POST['month_year'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $payable = $payableId > 0
        ? getRow("SELECT id, employee_id, month_year, balance FROM employee_payables WHERE id = ?", 'i', [$payableId])
        : null;

    if (!$employee || $employee['status'] !== 'active') {
        $message = 'Please select a valid active employee.';
        $messageType = 'danger';
    } elseif (empty($paymentDate) || $amount <= 0) {
        $message = 'Payment date and an amount greater than zero are required.';
        $messageType = 'danger';
    } elseif (!in_array($paymentType, ['salary', 'advance', 'bonus', 'other'], true)) {
        $message = 'Invalid payment type.';
        $messageType = 'danger';
    } elseif ($payable && (int)$payable['employee_id'] !== $employeeId) {
        $message = 'The selected salary bill does not belong to this employee.';
        $messageType = 'danger';
    } elseif ($payable && $amount > (float)$payable['balance']) {
        $message = 'Payment cannot exceed the selected salary month balance.';
        $messageType = 'danger';
    } elseif ($paymentType === 'salary' && !$payable) {
        $message = 'Select a salary month for a salary payment. Use Advance for an advance payment.';
        $messageType = 'danger';
    } else {
        $paymentNo = generateCode('EP');
        $dateTime = getCurrentDateTime();
        $paymentMonth = $payable ? $payable['month_year'] : $monthYear;
        $payableValue = $payableId > 0 ? $payableId : null;
        $result = insertData(
            "INSERT INTO employee_payments (payment_no, employee_id, payment_date, amount, month_year, payment_type, notes, payable_id, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            'sisdsssis',
            [$paymentNo, $employeeId, $paymentDate, $amount, $paymentMonth, $paymentType, $notes, $payableValue, $dateTime]
        );

        if ($result !== false) {
            modifyData("UPDATE employees SET current_balance = current_balance - ? WHERE id = ?", 'di', [$amount, $employeeId]);
            if ($payable) {
                modifyData(
                    "UPDATE employee_payables SET paid_amount = paid_amount + ?, balance = balance - ?, payment_status = CASE WHEN paid_amount + ? >= amount THEN 'paid' ELSE 'partial' END WHERE id = ?",
                    'dddi',
                    [$amount, $amount, $amount, $payableId]
                );
            }
            setFlash('Payment of ' . formatCurrency($amount) . ' made successfully.', 'success');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?employee_id=' . $employeeId);
            exit;
        }
        $message = 'Unable to save the employee payment.';
        $messageType = 'danger';
    }
}

$allEmployees = getRows("SELECT id, employee_name, employee_code, status FROM employees ORDER BY employee_name");

if ($employee) {
    $joiningDate = $employee['joining_date'] ?: date('Y-m-d');
    $month = new DateTime(date('Y-m-01', strtotime($joiningDate)));
    $lastMonth = new DateTime(date('Y-m-01'));
    $salary = (float)$employee['monthly_salary'];

    while ($month <= $lastMonth && $salary > 0) {
        $monthYear = $month->format('M-Y');
        $exists = getRow("SELECT id FROM employee_payables WHERE employee_id = ? AND month_year = ?", 'is', [$employeeId, $monthYear]);
        if (!$exists) {
            $billNo = generateCode('EPB');
            $amount = $salary;
            insertData(
                "INSERT INTO employee_payables (bill_no, employee_id, month_year, amount, paid_amount, balance, payment_status, description, date_time) VALUES (?, ?, ?, ?, 0, ?, 'unpaid', ?, ?)",
                'sssddss',
                [$billNo, $employeeId, $monthYear, $amount, $amount, 'Monthly salary - ' . $monthYear, getCurrentDateTime()]
            );
        }
        $month->modify('+1 month');
    }

    $bills = getRows("SELECT * FROM employee_payables WHERE employee_id = ? ORDER BY id ASC", 'i', [$employeeId]);
    $payments = getRows("SELECT * FROM employee_payments WHERE employee_id = ? ORDER BY payment_date DESC, id DESC", 'i', [$employeeId]);
    $totalBilled = 0;
    $totalBillPaid = 0;
    $totalOutstanding = 0;
    foreach ($bills as $bill) {
        $totalBilled += (float)$bill['amount'];
        $totalBillPaid += (float)$bill['paid_amount'];
        $totalOutstanding += (float)$bill['balance'];
    }
    $totalPayments = 0;
    foreach ($payments as $payment) {
        $totalPayments += (float)$payment['amount'];
    }
    $openingBalance = (float)$employee['opening_balance'];
    $totalDue = $openingBalance + $totalBilled - $totalPayments;
    $unpaidBills = array_filter($bills, function ($bill) {
        return (float)$bill['balance'] > 0;
    });
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row align-items-end">
            <div class="col-md-8">
                <label class="form-label"><strong>Select Employee</strong></label>
                <select class="form-select" name="employee_id" required>
                    <option value="">Select employee to open ledger</option>
                    <?php foreach ($allEmployees as $item): ?>
                        <option value="<?php echo $item['id']; ?>" <?php echo $employeeId === (int)$item['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($item['employee_name']); ?> (<?php echo htmlspecialchars($item['employee_code'] ?: '-'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><button class="btn btn-primary w-100" type="submit"><i class="fas fa-book me-1"></i>Open Employee Ledger</button></div>
        </form>
    </div>
</div>

<?php if ($employee): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h4 class="mb-1"><?php echo htmlspecialchars($employee['employee_name']); ?> - Salary Ledger</h4><small class="text-muted">Joining Date: <?php echo formatDate($employee['joining_date']); ?> | Monthly Salary: <?php echo formatCurrency($salary); ?></small></div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#paymentModal"><i class="fas fa-money-bill-wave me-1"></i>Pay Employee</button>
    </div>

    <div class="row mb-3">
        <div class="col-md-3"><div class="stat-card" style="border-left-color:#6f42c1;"><div class="stat-number text-purple"><?php echo formatCurrency($openingBalance); ?></div><div class="stat-label">Opening Balance</div></div></div>
        <div class="col-md-3"><div class="stat-card" style="border-left-color:#0d6efd;"><div class="stat-number text-primary"><?php echo formatCurrency($totalBilled); ?></div><div class="stat-label">Salary Accrued</div></div></div>
        <div class="col-md-3"><div class="stat-card" style="border-left-color:#198754;"><div class="stat-number text-success"><?php echo formatCurrency($totalPayments); ?></div><div class="stat-label">Total Paid</div></div></div>
        <div class="col-md-3"><div class="stat-card" style="border-left-color:#dc3545;"><div class="stat-number text-danger"><?php echo formatCurrency(max(0, $totalDue)); ?></div><div class="stat-label">Total Amount Due</div></div></div>
    </div>

    <div class="card">
        <div class="card-header" style="background:#1a2332;color:#fff;"><i class="fas fa-calendar-alt me-2"></i>Monthly Salary Ledger</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="employeeLedgerTable">
                    <thead><tr><th>#</th><th>Month</th><th>Salary</th><th>Paid</th><th>Balance</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php $counter = 1; foreach ($bills as $bill): ?>
                            <tr>
                                <td><?php echo $counter++; ?></td><td><?php echo htmlspecialchars($bill['month_year']); ?></td>
                                <td><?php echo formatCurrency($bill['amount']); ?></td><td class="text-success"><?php echo formatCurrency($bill['paid_amount']); ?></td>
                                <td class="<?php echo (float)$bill['balance'] > 0 ? 'text-danger fw-bold' : 'text-success'; ?>"><?php echo formatCurrency($bill['balance']); ?></td>
                                <td><span class="badge bg-<?php echo $bill['payment_status'] === 'paid' ? 'success' : ($bill['payment_status'] === 'partial' ? 'warning text-dark' : 'danger'); ?>"><?php echo ucfirst($bill['payment_status']); ?></span></td>
                                <td><?php if ((float)$bill['balance'] > 0): ?><button type="button" class="btn btn-sm btn-primary pay-month" data-id="<?php echo $bill['id']; ?>" data-balance="<?php echo $bill['balance']; ?>"><i class="fas fa-money-bill me-1"></i>Pay</button><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
        <div class="modal-header" style="background:#1a2332;color:#fff;"><h5 class="modal-title">Pay <?php echo htmlspecialchars($employee['employee_name']); ?></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
        <form method="POST"><input type="hidden" name="make_payment" value="1"><input type="hidden" name="employee_id" value="<?php echo $employeeId; ?>">
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Salary Month</label><select class="form-select" name="payable_id" id="payableId"><option value="">No month - Advance / Bonus / Other</option><?php foreach ($unpaidBills as $bill): ?><option value="<?php echo $bill['id']; ?>" data-balance="<?php echo $bill['balance']; ?>"><?php echo htmlspecialchars($bill['month_year']); ?> - Due: <?php echo formatCurrency($bill['balance']); ?></option><?php endforeach; ?></select></div>
                <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Payment Date *</label><input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required></div><div class="col-md-6 mb-3"><label class="form-label">Amount *</label><input type="number" step="0.01" min="0.01" class="form-control" name="amount" id="paymentAmount" required></div></div>
                <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Type *</label><select class="form-select" name="payment_type" id="paymentType" required><option value="salary">Salary</option><option value="advance">Advance</option><option value="bonus">Bonus</option><option value="other">Other</option></select></div><div class="col-md-6 mb-3"><label class="form-label">Month / Year</label><input type="text" class="form-control" name="month_year" value="<?php echo date('M-Y'); ?>"></div></div>
                <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Save Payment</button></div>
        </form>
    </div></div></div>
    <script>
    $(function() {
        $('#employeeLedgerTable').DataTable({pageLength: 25, order: [[0, 'asc']]});
        $('.pay-month').on('click', function() {
            $('#payableId').val($(this).data('id')).trigger('change');
            $('#paymentAmount').val(parseFloat($(this).data('balance')).toFixed(2));
            $('#paymentType').val('salary');
            new bootstrap.Modal(document.getElementById('paymentModal')).show();
        });
        $('#payableId').on('change', function() {
            var option = $(this).find(':selected');
            if (option.val()) {
                $('#paymentAmount').val(parseFloat(option.data('balance')).toFixed(2));
                $('#paymentType').val('salary');
            } else {
                $('#paymentType').val('advance');
            }
        });
    });
    </script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
