<?php
/**
 * Customers Management
 * Manufacturing ERP System
 */

require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Customers';
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
    
    $sql = "SELECT COUNT(*) as count FROM sales WHERE customer_id = ?";
    $check = getRow($sql, 'i', [$id]);
    
    if ($check && $check['count'] > 0) {
        $message = 'Cannot delete customer! They have sales records.';
        $messageType = 'danger';
    } else {
        $sql = "DELETE FROM customers WHERE id = ?";
        $result = modifyData($sql, 'i', [$id]);
        
        if ($result !== false) {
            setFlash('Customer deleted successfully!', 'success');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            setFlash('Error deleting customer!', 'danger');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
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
        $currentDateTime = getCurrentDateTime();
        
        if ($id > 0) {
            $sql = "UPDATE customers SET customer_name = ?, company_name = ?, contact_person = ?, phone = ?, email = ?, address = ?, city = ?, opening_balance = ?, opening_balance_type = ?, status = ?, date_time = ? WHERE id = ?";
            $result = modifyData($sql, 'sssssssdssi', [$customer_name, $company_name, $contact_person, $phone, $email, $address, $city, $opening_balance, $opening_balance_type, $status, $currentDateTime, $id]);
            
            if ($result !== false) {
                setFlash('Customer updated successfully!', 'success');
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $message = 'Error updating customer!';
                $messageType = 'danger';
            }
        } else {
            $sql = "INSERT INTO customers (customer_name, company_name, contact_person, phone, email, address, city, opening_balance, opening_balance_type, status, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $result = insertData($sql, 'sssssssdsss', [$customer_name, $company_name, $contact_person, $phone, $email, $address, $city, $opening_balance, $opening_balance_type, $status, $currentDateTime]);
            
            if ($result !== false) {
                setFlash('Customer added successfully!', 'success');
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $message = 'Error adding customer!';
                $messageType = 'danger';
            }
        }
    }
}

// Get all customers with closing balance
$sql = "SELECT c.*,
        (SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE customer_id = c.id AND status='completed') as total_sales,
        (SELECT COALESCE(SUM(paid_amount), 0) FROM sales WHERE customer_id = c.id AND status='completed') as total_paid,
        (SELECT COALESCE(SUM(amount), 0) FROM customer_receipts WHERE customer_id = c.id) as total_receipts
        FROM customers c ORDER BY c.id DESC";
$customers = getRows($sql);

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
        <a href="add_customer.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Customer
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-user-friends me-2"></i>Customers List</span>
                <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="customerTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Customer Name</th>
                                <th>Company</th>
                                <th>Phone</th>
                                <th>City</th>
                                <th>Closing Balance (PKR)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($customers) > 0): ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($customers as $customer): ?>
                                    <?php
                                    $effOpening = $customer['opening_balance'];
                                    if (($customer['opening_balance_type'] ?? 'receivable') === 'payable') {
                                        $effOpening = -$customer['opening_balance'];
                                    }
                                    $closingBalance = $effOpening + $customer['total_sales'] - $customer['total_paid'] - $customer['total_receipts'];
                                    ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($customer['customer_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($customer['company_name'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($customer['phone'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($customer['city'] ?: '-'); ?></td>
                                        <td class="<?php echo $closingBalance > 0 ? 'text-danger' : 'text-success'; ?>">
                                            <strong><?php echo formatCurrency($closingBalance); ?></strong>
                                            <small class="text-muted">(<?php echo $closingBalance > 0 ? 'Receivable' : ($closingBalance < 0 ? 'Payable' : 'Settled'); ?>)</small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="customer_ledger.php?id=<?php echo $customer['id']; ?>" class="btn btn-info" title="Ledger">
                                                    <i class="fas fa-book"></i>
                                                </a>
                                                <button type="button" class="btn btn-warning" title="Edit"
                                                    data-bs-toggle="modal" data-bs-target="#customerModal"
                                                    data-id="<?php echo $customer['id']; ?>"
                                                    data-customer_name="<?php echo htmlspecialchars($customer['customer_name']); ?>"
                                                    data-company_name="<?php echo htmlspecialchars($customer['company_name']); ?>"
                                                    data-contact_person="<?php echo htmlspecialchars($customer['contact_person']); ?>"
                                                    data-phone="<?php echo htmlspecialchars($customer['phone']); ?>"
                                                    data-email="<?php echo htmlspecialchars($customer['email']); ?>"
                                                    data-address="<?php echo htmlspecialchars($customer['address']); ?>"
                                                    data-city="<?php echo htmlspecialchars($customer['city']); ?>"
                                                    data-opening_balance="<?php echo $customer['opening_balance']; ?>"
                                                    data-opening_balance_type="<?php echo $customer['opening_balance_type'] ?? 'receivable'; ?>"
                                                    data-status="<?php echo $customer['status']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="?delete=<?php echo $customer['id']; ?>" class="btn btn-danger delete-confirm" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-3">
                                        <i class="fas fa-info-circle me-2"></i>No customers found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Customer Modal -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customerModalLabel">
                    <i class="fas fa-user-friends me-2"></i>
                    <span id="modalTitle">Add New Customer</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="id" id="customer_id" value="0">
                    
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="modalSubmitBtn">
                        <i class="fas fa-save me-2"></i><span id="submitBtnText">Save</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Populate modal on edit
    $('#customerModal').on('show.bs.modal', function(e) {
        var btn = $(e.relatedTarget);
        var id = btn.data('id');
        
        if (id) {
            $('#modalTitle').text('Edit Customer');
            $('#submitBtnText').text('Update');
            $('#customer_id').val(id);
            $('#customer_name').val(btn.data('customer_name'));
            $('#company_name').val(btn.data('company_name') || '');
            $('#contact_person').val(btn.data('contact_person') || '');
            $('#phone').val(btn.data('phone') || '');
            $('#email').val(btn.data('email') || '');
            $('#address').val(btn.data('address') || '');
            $('#city').val(btn.data('city') || '');
            $('#opening_balance').val(btn.data('opening_balance'));
            $('#opening_balance_type').val(btn.data('opening_balance_type') || 'receivable');
            $('#status').val(btn.data('status') || 'active');
        }
    });
    
    // Reset modal on hide
    $('#customerModal').on('hidden.bs.modal', function() {
        if (!$('#customer_id').val()) return;
        $('#modalTitle').text('Add New Customer');
        $('#submitBtnText').text('Save');
        $('#customer_id').val(0);
        $('#customer_name').val('');
        $('#company_name').val('');
        $('#contact_person').val('');
        $('#phone').val('');
        $('#email').val('');
        $('#address').val('');
        $('#city').val('');
        $('#opening_balance').val('0.00');
        $('#opening_balance_type').val('receivable');
        $('#status').val('active');
    });
    
});
</script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#customerTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]],
        "language": {
            "search": "Search Customers:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ customers",
            "emptyTable": "No customers found"
        }
    });
});
</script>

<?php
include '../includes/footer.php';
?>
