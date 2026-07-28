<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Add Employee';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

$editEmployee = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $sql = "SELECT * FROM employees WHERE id = ?";
    $editEmployee = getRow($sql, 'i', [$editId]);

    if ($editEmployee) {
        $pageTitle = 'Edit Employee';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $employee_code = trim($_POST['employee_code']);
    $employee_name = trim($_POST['employee_name']);
    $father_name = trim($_POST['father_name']);
    $cnic = trim($_POST['cnic']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city'] ?? '');
    $designation = trim($_POST['designation']);
    $department = trim($_POST['department']);
    $joining_date = trim($_POST['joining_date']);
    $monthly_salary = (float)$_POST['monthly_salary'];
    $opening_balance = (float)$_POST['opening_balance'];
    $status = $_POST['status'];

    if (empty($employee_name)) {
        $message = 'Employee name is required!';
        $messageType = 'danger';
    } else {
        if ($id > 0) {
            $oldEmployee = getRow("SELECT opening_balance, current_balance FROM employees WHERE id = ?", 'i', [$id]);

            if ($oldEmployee) {
                $old_opening = (float)$oldEmployee['opening_balance'];
                $old_current = (float)$oldEmployee['current_balance'];
                $new_current = $opening_balance + ($old_current - $old_opening);

                $sql = "UPDATE employees SET employee_code = ?, employee_name = ?, father_name = ?, cnic = ?, phone = ?, email = ?, address = ?, city = ?, designation = ?, department = ?, joining_date = ?, monthly_salary = ?, opening_balance = ?, current_balance = ?, status = ? WHERE id = ?";
                $result = modifyData($sql, 'sssssssssssdddsi', [$employee_code, $employee_name, $father_name, $cnic, $phone, $email, $address, $city, $designation, $department, $joining_date, $monthly_salary, $opening_balance, $new_current, $status, $id]);
            } else {
                $result = false;
            }
        } else {
            $sql = "INSERT INTO employees (employee_code, employee_name, father_name, cnic, phone, email, address, city, designation, department, joining_date, monthly_salary, opening_balance, current_balance, status, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $result = insertData($sql, 'ssssssssssssdds', [$employee_code, $employee_name, $father_name, $cnic, $phone, $email, $address, $city, $designation, $department, $joining_date, $monthly_salary, $opening_balance, $opening_balance, $status, getCurrentDateTime()]);
        }

        if ($result !== false) {
            if ($id > 0) {
                setFlash('Employee updated successfully!', 'success');
            } else {
                setFlash('Employee added successfully!', 'success');
            }
            header('Location: employees.php');
            exit;
        } else {
            $message = $id > 0 ? 'Error updating employee!' : 'Error adding employee!';
            $messageType = 'danger';
        }
    }
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

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" style="background:#1a2332;color:#fff;">
                <span><i class="fas fa-<?php echo $editEmployee ? 'edit' : 'user-plus'; ?> me-2"></i><?php echo $editEmployee ? 'Edit Employee' : 'Add New Employee'; ?></span>
                <a href="employees.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Employees</a>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php if ($editEmployee): ?>
                        <input type="hidden" name="id" value="<?php echo $editEmployee['id']; ?>">
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="employee_code" class="form-label">Employee Code</label>
                            <input type="text" class="form-control" id="employee_code" name="employee_code"
                                   value="<?php echo $editEmployee ? htmlspecialchars($editEmployee['employee_code']) : ''; ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="employee_name" class="form-label">Employee Name *</label>
                            <input type="text" class="form-control" id="employee_name" name="employee_name"
                                   value="<?php echo $editEmployee ? htmlspecialchars($editEmployee['employee_name']) : ''; ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="father_name" class="form-label">Father Name</label>
                            <input type="text" class="form-control" id="father_name" name="father_name"
                                   value="<?php echo $editEmployee ? htmlspecialchars($editEmployee['father_name']) : ''; ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="cnic" class="form-label">CNIC</label>
                            <input type="text" class="form-control" id="cnic" name="cnic"
                                   value="<?php echo $editEmployee ? htmlspecialchars($editEmployee['cnic']) : ''; ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone"
                                   value="<?php echo $editEmployee ? htmlspecialchars($editEmployee['phone']) : ''; ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo $editEmployee ? htmlspecialchars($editEmployee['email']) : ''; ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="designation" class="form-label">Designation</label>
                            <input type="text" class="form-control" id="designation" name="designation"
                                   value="<?php echo $editEmployee ? htmlspecialchars($editEmployee['designation']) : ''; ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label">City</label>
                            <input type="text" class="form-control" id="city" name="city"
                                   value="<?php echo $editEmployee ? htmlspecialchars($editEmployee['city']) : ''; ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="department" class="form-label">Department</label>
                            <input type="text" class="form-control" id="department" name="department"
                                   value="<?php echo $editEmployee ? htmlspecialchars($editEmployee['department']) : ''; ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="joining_date" class="form-label">Joining Date</label>
                            <input type="date" class="form-control" id="joining_date" name="joining_date"
                                   value="<?php echo $editEmployee ? htmlspecialchars($editEmployee['joining_date']) : ''; ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="monthly_salary" class="form-label">Monthly Salary (PKR)</label>
                            <input type="number" step="0.01" class="form-control" id="monthly_salary" name="monthly_salary"
                                   value="<?php echo $editEmployee ? number_format($editEmployee['monthly_salary'], 2, '.', '') : '0.00'; ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="opening_balance" class="form-label">Opening Balance (PKR)</label>
                            <input type="number" step="0.01" class="form-control" id="opening_balance" name="opening_balance"
                                   value="<?php echo $editEmployee ? number_format($editEmployee['opening_balance'], 2, '.', '') : '0.00'; ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?php echo ($editEmployee && $editEmployee['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($editEmployee && $editEmployee['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo $editEmployee ? htmlspecialchars($editEmployee['address']) : ''; ?></textarea>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i><?php echo $editEmployee ? 'Update Employee' : 'Save Employee'; ?>
                        </button>
                        <a href="employees.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
