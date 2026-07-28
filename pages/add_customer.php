<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Add Customer';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name']);
    $company_name = trim($_POST['company_name']);
    $contact_person = trim($_POST['contact_person']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $opening_balance = (float)$_POST['opening_balance'];
    $opening_balance_type = $_POST['opening_balance_type'] ?? 'receivable';
    $status = $_POST['status'];

    if (empty($customer_name)) {
        $message = 'Customer name is required!';
        $messageType = 'danger';
    } else {
        $sql = "INSERT INTO customers (customer_name, company_name, contact_person, phone, email, address, city, opening_balance, opening_balance_type, status, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $result = insertData($sql, 'sssssssdsss', [$customer_name, $company_name, $contact_person, $phone, $email, $address, $city, $opening_balance, $opening_balance_type, $status, getCurrentDateTime()]);

        if ($result !== false) {
            setFlash('Customer added successfully!', 'success');
            header('Location: customers.php');
            exit;
        } else {
            $message = 'Error adding customer!';
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
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-user-plus me-2"></i>Add New Customer</span>
                <a href="customers.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="customer_name" class="form-label">Customer Name *</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="company_name" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="company_name" name="company_name">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="contact_person" class="form-label">Contact Person</label>
                            <input type="text" class="form-control" id="contact_person" name="contact_person">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label">City</label>
                            <input type="text" class="form-control" id="city" name="city">
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="opening_balance" class="form-label">Opening Balance (PKR)</label>
                            <input type="number" step="0.01" class="form-control" id="opening_balance" name="opening_balance" value="0.00">
                        </div>

                        <div class="col-md-2 mb-3">
                            <label for="opening_balance_type" class="form-label">Type</label>
                            <select class="form-select" id="opening_balance_type" name="opening_balance_type">
                                <option value="receivable">Receivable</option>
                                <option value="payable">Payable</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Customer
                        </button>
                        <a href="customers.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
