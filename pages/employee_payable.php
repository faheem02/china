<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Employee Payable';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

// Handle Delete Bill
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $bill = getRow("SELECT id, paid_amount FROM employee_payables WHERE id = ?", 'i', [$id]);
    if ($bill && (float)$bill['paid_amount'] == 0) {
        modifyData("DELETE FROM employee_payables WHERE id = ?", 'i', [$id]);
        setFlash('Bill deleted successfully!', 'success');
    } else {
        setFlash('Cannot delete a bill with payments!', 'danger');
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Auto-Generate Monthly Bills
if (isset($_GET['auto_generate']) && $_GET['auto_generate'] == '1') {
    $monthYear = isset($_GET['month']) ? trim($_GET['month']) : date('M-Y');
    $currentDateTime = getCurrentDateTime();

    $employees = getRows("SELECT id, employee_code, employee_name, monthly_salary FROM employees WHERE status = 'active' AND monthly_salary > 0");
    $generated = 0;
    $skipped = 0;

    foreach ($employees as $emp) {
        $exists = getRow("SELECT id FROM employee_payables WHERE employee_id = ? AND month_year = ?", 'is', [$emp['id'], $monthYear]);
        if ($exists) {
            $skipped++;
            continue;
        }
        $billNo = generateCode('EPB');
        $amount = (float)$emp['monthly_salary'];
        insertData("INSERT INTO employee_payables (bill_no, employee_id, month_year, amount, paid_amount, balance, payment_status, description, date_time) VALUES (?, ?, ?, ?, 0, ?, 'unpaid', ?, ?)",
            'sssddss', [$billNo, $emp['id'], $monthYear, $amount, $amount, 'Monthly salary - ' . $monthYear, $currentDateTime]);
        $generated++;
    }

    setFlash("Generated $generated bill(s) for $monthYear. $skipped already existed.", 'success');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Individual Bill POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_bill'])) {
    $employee_id = (int)$_POST['employee_id'];
    $month_year = trim($_POST['month_year']);
    $amount = (float)$_POST['amount'];
    $description = trim($_POST['description']);

    if (empty($employee_id)) {
        $message = 'Please select an employee!';
        $messageType = 'danger';
    } elseif (empty($month_year)) {
        $message = 'Please enter month/year!';
        $messageType = 'danger';
    } elseif ($amount <= 0) {
        $message = 'Amount must be greater than 0!';
        $messageType = 'danger';
    } else {
        $billNo = generateCode('EPB');
        $currentDateTime = getCurrentDateTime();
        $result = insertData("INSERT INTO employee_payables (bill_no, employee_id, month_year, amount, paid_amount, balance, payment_status, description, date_time) VALUES (?, ?, ?, ?, 0, ?, 'unpaid', ?, ?)",
            'sssddss', [$billNo, $employee_id, $monthYear, $amount, $amount, $description, $currentDateTime]);

        if ($result) {
            setFlash('Bill ' . $billNo . ' created successfully!', 'success');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $message = 'Error creating bill!';
            $messageType = 'danger';
        }
    }
}

// Fetch all bills
$bills = getRows("SELECT pb.*, e.employee_name, e.employee_code, e.designation
    FROM employee_payables pb
    LEFT JOIN employees e ON pb.employee_id = e.id
    ORDER BY pb.id DESC");

$activeEmployees = getRows("SELECT id, employee_name, employee_code, monthly_salary FROM employees WHERE status = 'active' ORDER BY employee_name");

// Summary
$totalBills = count($bills);
$totalAmount = 0;
$totalPaid = 0;
$totalBalance = 0;
$unpaidCount = 0;
foreach ($bills as $b) {
    $totalAmount += (float)$b['amount'];
    $totalPaid += (float)$b['paid_amount'];
    $totalBalance += (float)$b['balance'];
    if ($b['payment_status'] !== 'paid') $unpaidCount++;
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="row">
    <div class="col-12">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <a href="employees.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Employees</a>
        <button type="button" class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#billModal">
            <i class="fas fa-plus me-1"></i>Create Bill
        </button>
        <button type="button" class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#autoGenerateModal">
            <i class="fas fa-magic me-1"></i>Auto-Generate Monthly Bills
        </button>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color: #1a2332;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo $totalBills; ?></div>
                    <div class="stat-label">Total Bills</div>
                </div>
                <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color: #0d6efd;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number text-primary"><?php echo formatCurrency($totalAmount); ?></div>
                    <div class="stat-label">Total Bill Amount</div>
                </div>
                <div class="stat-icon"><i class="fas fa-money-bill text-primary"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color: #198754;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number text-success"><?php echo formatCurrency($totalPaid); ?></div>
                    <div class="stat-label">Total Paid</div>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle text-success"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color: #dc3545;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number text-danger"><?php echo formatCurrency($totalBalance); ?></div>
                    <div class="stat-label">Outstanding (<?php echo $unpaidCount; ?> unpaid)</div>
                </div>
                <div class="stat-icon"><i class="fas fa-exclamation-triangle text-danger"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header" style="background:#1a2332;color:#fff;">
                <i class="fas fa-file-invoice me-2"></i>Employee Bills / Payables
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="billsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Bill No</th>
                                <th>Employee</th>
                                <th>Month</th>
                                <th>Bill Amount</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($bills as $b): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($b['bill_no']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($b['employee_name']); ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($b['employee_code']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($b['month_year']); ?></td>
                                    <td><?php echo formatCurrency($b['amount']); ?></td>
                                    <td class="text-success"><?php echo formatCurrency($b['paid_amount']); ?></td>
                                    <td>
                                        <?php if ((float)$b['balance'] > 0): ?>
                                            <span class="text-danger fw-bold"><?php echo formatCurrency($b['balance']); ?></span>
                                        <?php else: ?>
                                            <span class="text-success">0.00</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = $b['payment_status'] === 'paid' ? 'success' : ($b['payment_status'] === 'partial' ? 'warning' : 'danger');
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass; ?>"><?php echo ucfirst($b['payment_status']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ((float)$b['paid_amount'] == 0): ?>
                                            <a href="?delete=<?php echo $b['id']; ?>" class="btn btn-sm btn-danger delete-confirm" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
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

<!-- Create Individual Bill Modal -->
<div class="modal fade" id="billModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a2332;color:#fff;">
                <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i>Create Individual Bill</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="create_bill" value="1">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Employee *</label>
                        <select class="form-select" name="employee_id" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($activeEmployees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" data-salary="<?php echo $emp['monthly_salary']; ?>">
                                    <?php echo htmlspecialchars($emp['employee_name']); ?> (<?php echo htmlspecialchars($emp['employee_code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Month/Year *</label>
                            <input type="text" class="form-control" name="month_year" placeholder="e.g. Jul-2026" value="<?php echo date('M-Y'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount (PKR) *</label>
                            <input type="number" step="0.01" class="form-control" id="billAmount" name="amount" min="0.01" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="e.g. Monthly salary for Jul-2026"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Create Bill</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Auto-Generate Monthly Bills Modal -->
<div class="modal fade" id="autoGenerateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:#198754;color:#fff;">
                <h5 class="modal-title"><i class="fas fa-magic me-2"></i>Auto-Generate Monthly Bills</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>This will generate salary bills for <strong>all active employees</strong> using their monthly salary.</p>
                <p class="text-muted"><small>If a bill already exists for that employee + month, it will be skipped.</small></p>
                <div class="mb-3">
                    <label class="form-label">Month/Year *</label>
                    <input type="text" class="form-control" id="autoMonthYear" value="<?php echo date('M-Y'); ?>" placeholder="e.g. Jul-2026">
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i>
                    <span id="autoGenCount"><?php echo count($activeEmployees); ?></span> active employees with salary will receive bills.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="autoGenBtn" class="btn btn-success"><i class="fas fa-magic me-2"></i>Generate All Bills</a>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#billsTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        language: { search: 'Search Bills:', emptyTable: 'No bills found' }
    });

    // Auto-fill amount from employee salary
    $('select[name="employee_id"]').on('change', function() {
        var salary = $(this).find(':selected').data('salary');
        if (salary) $('#billAmount').val(parseFloat(salary).toFixed(2));
    });

    // Auto-generate link
    $('#autoGenBtn').on('click', function() {
        var month = $('#autoMonthYear').val() || '<?php echo date("M-Y"); ?>';
        window.location.href = '<?php echo $_SERVER["PHP_SELF"]; ?>?auto_generate=1&month=' + encodeURIComponent(month);
    });

    $('.delete-confirm').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this bill?')) e.preventDefault();
    });
});
</script>

<?php include '../includes/footer.php'; ?>
