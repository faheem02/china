<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Employees';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $hasPayments = getRow("SELECT COUNT(*) as cnt FROM employee_payments WHERE employee_id = ?", 'i', [$id]);
    if ($hasPayments && $hasPayments['cnt'] > 0) {
        $message = 'Cannot delete employee with existing payment records!';
        $messageType = 'danger';
    } else {
        $result = modifyData("DELETE FROM employees WHERE id = ?", 'i', [$id]);
        if ($result !== false) {
            setFlash('Employee deleted successfully!', 'success');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $message = 'Error deleting employee!';
            $messageType = 'danger';
        }
    }
}

$employees = getRows("SELECT e.*,
    COALESCE((SELECT SUM(ep.amount) FROM employee_payments ep WHERE ep.employee_id = e.id), 0) as total_paid,
    COALESCE((SELECT SUM(pb.amount) FROM employee_payables pb WHERE pb.employee_id = e.id), 0) as total_billed
    FROM employees e
    ORDER BY e.id DESC");

$totalEmployees = count($employees);
$totalSalary = 0;
$totalPaid = 0;
$totalPayable = 0;
foreach ($employees as $emp) {
    $totalSalary += (float)$emp['monthly_salary'];
    $totalPaid += (float)$emp['total_paid'];
    $totalPayable += (float)$emp['opening_balance'] + (float)$emp['total_billed'] - (float)$emp['total_paid'];
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
        <a href="add_employee.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Add New Employee</a>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color: #1a2332;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo $totalEmployees; ?></div>
                    <div class="stat-label">Total Employees</div>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color: #0d6efd;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number text-primary"><?php echo formatCurrency($totalSalary); ?></div>
                    <div class="stat-label">Total Monthly Salaries</div>
                </div>
                <div class="stat-icon"><i class="fas fa-money-bill-wave text-primary"></i></div>
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
                    <div class="stat-number text-danger"><?php echo formatCurrency($totalPayable); ?></div>
                    <div class="stat-label">Total Payable</div>
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
                <i class="fas fa-users me-2"></i>Employees List
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="employeeTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Code</th>
                                <th>Employee Name</th>
                                <th>Designation</th>
                                <th>Department</th>
                                <th>Phone</th>
                                <th>Opening Balance</th>
                                <th>Salary (PKR)</th>
                                <th>Total Paid</th>
                                <th>Payable</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($employees) > 0): ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($employees as $emp): ?>
                                    <?php $payable = (float)$emp['opening_balance'] + (float)$emp['total_billed'] - (float)$emp['total_paid']; ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo htmlspecialchars($emp['employee_code'] ?: '-'); ?></td>
                                        <td><strong><?php echo htmlspecialchars($emp['employee_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($emp['designation'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($emp['department'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($emp['phone'] ?: '-'); ?></td>
                                        <td class="text-primary fw-bold"><?php echo formatCurrency($emp['opening_balance']); ?></td>
                                        <td><?php echo formatCurrency($emp['monthly_salary']); ?></td>
                                        <td><?php echo formatCurrency($emp['total_paid']); ?></td>
                                        <td>
                                            <?php if ($payable > 0): ?>
                                                <span class="badge bg-danger"><?php echo formatCurrency($payable); ?></span>
                                            <?php elseif ($payable < 0): ?>
                                                <span class="badge bg-info"><?php echo formatCurrency(abs($payable)); ?> advance</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Paid</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="employee_detail.php?id=<?php echo $emp['id']; ?>" class="btn btn-info" title="Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="add_employee.php?edit=<?php echo $emp['id']; ?>" class="btn btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?delete=<?php echo $emp['id']; ?>" class="btn btn-danger delete-confirm" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#employeeTable').DataTable({
        "pageLength": 25,
        "order": [],
        "columnDefs": [
            { "orderable": false, "targets": [9] }
        ],
        "language": {
            "search": "Search Employees:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ employees",
            "emptyTable": "No employees found"
        }
    });
    $('.delete-confirm').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this employee?')) {
            e.preventDefault();
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
