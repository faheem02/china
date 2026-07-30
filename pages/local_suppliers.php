<?php
/**
 * Local Suppliers Management
 * Manufacturing ERP System
 */

// Include database connection
require_once '../includes/database.php';

// Require login
requireLogin();

$pageTitle = 'Local Suppliers';
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
    $sql = "DELETE FROM local_suppliers WHERE id = ?";
    $result = modifyData($sql, 'i', [$id]);
    
    if ($result !== false) {
        setFlash('Supplier deleted successfully!', 'success');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $message = 'Error deleting supplier!';
        $messageType = 'danger';
    }
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $supplier_name = trim($_POST['supplier_name']);
    $company_name = trim($_POST['company_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $country = trim($_POST['country']);
    $cnic = trim($_POST['cnic']);
    $ntn = trim($_POST['ntn']);
    $opening_balance = (float)$_POST['opening_balance'];
    $opening_balance_type = $_POST['opening_balance_type'];
    $status = $_POST['status'];
    
    // Validation
    if (empty($supplier_name)) {
        $message = 'Supplier name is required!';
        $messageType = 'danger';
    } else {
        $currentDateTime = getCurrentDateTime();
        
        if ($id > 0) {
            // Update
            $sql = "UPDATE local_suppliers SET 
                    supplier_name = ?, 
                    company_name = ?, 
                    phone = ?, 
                    email = ?, 
                    address = ?, 
                    city = ?, 
                    country = ?, 
                    cnic = ?,
                    ntn = ?,
                    opening_balance = ?,
                    opening_balance_type = ?,
                    status = ?,
                    date_time = ?
                    WHERE id = ?";
            
            $params = [
                $supplier_name, 
                $company_name, 
                $phone, 
                $email, 
                $address, 
                $city, 
                $country, 
                $cnic,
                $ntn,
                $opening_balance,
                $opening_balance_type,
                $status,
                $currentDateTime,
                $id
            ];
            
            $result = modifyData($sql, 'sssssssssdsssi', $params);
            
            if ($result !== false) {
                setFlash('Supplier updated successfully!', 'success');
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $message = 'Error updating supplier!';
                $messageType = 'danger';
            }
        } else {
            // Insert
            $sql = "INSERT INTO local_suppliers (
                    supplier_name, company_name, phone, email, 
                    address, city, country, cnic, ntn, opening_balance, opening_balance_type,
                    status, date_time
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $supplier_name, 
                $company_name, 
                $phone, 
                $email, 
                $address, 
                $city, 
                $country, 
                $cnic,
                $ntn,
                $opening_balance,
                $opening_balance_type,
                $status,
                $currentDateTime
            ];
            
            $result = insertData($sql, 'ssssssssdsss', $params);
            
            if ($result !== false) {
                setFlash('Supplier added successfully!', 'success');
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $message = 'Error adding supplier!';
                $messageType = 'danger';
            }
        }
    }
}

$suppliers = getRows("SELECT s.*,
    COALESCE(SUM(lp.balance), 0) AS purchase_balance,
    COALESCE((SELECT SUM(sp.amount) FROM supplier_payments sp WHERE sp.supplier_id = s.id), 0) AS payments_made,
    (s.opening_balance + COALESCE(SUM(lp.balance), 0) - COALESCE((SELECT SUM(sp.amount) FROM supplier_payments sp WHERE sp.supplier_id = s.id), 0)) AS current_balance
    FROM local_suppliers s
    LEFT JOIN local_purchases lp ON lp.supplier_id = s.id
    GROUP BY s.id
    ORDER BY s.id DESC");

// Get single supplier for edit
$editSupplier = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $sql = "SELECT * FROM local_suppliers WHERE id = ?";
    $editSupplier = getRow($sql, 'i', [$editId]);
}

// Include header
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!-- Page Content -->
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
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#supplierModal">
            <i class="fas fa-plus me-2"></i>Add New Supplier
        </button>
        <a href="supplier_payments.php" class="btn btn-success">
            <i class="fas fa-money-bill-wave me-2"></i>Make Payment
        </a>
        <a href="suppliers_summary.php" class="btn btn-info text-white">
            <i class="fas fa-chart-bar me-2"></i>Suppliers Summary
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list me-2"></i>Local Suppliers List</span>
                <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="supplierTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Supplier Name</th>
                                <th>Company</th>
                                <th>Phone</th>
                                <th>CNIC</th>
                                <th>NTN</th>
                                <th>Balance (PKR)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($suppliers) > 0): ?>
                                <?php $i = 1; foreach ($suppliers as $supplier): ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td>
                                            <a href="supplier_detail.php?id=<?php echo $supplier['id']; ?>">
                                                <strong><?php echo htmlspecialchars($supplier['supplier_name']); ?></strong>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($supplier['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($supplier['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($supplier['cnic']); ?></td>
                                        <td><?php echo htmlspecialchars($supplier['ntn']); ?></td>
                                        <td>
                                            <?php echo formatCurrency($supplier['current_balance']); ?>
                                            <br><small class="badge bg-<?php echo ($supplier['opening_balance_type'] ?? 'payable') === 'payable' ? 'warning' : 'success'; ?>">
                                                <?php echo ucfirst($supplier['opening_balance_type'] ?? 'payable'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge badge-status badge-<?php echo $supplier['status']; ?>">
                                                <?php echo ucfirst($supplier['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="supplier_detail.php?id=<?php echo $supplier['id']; ?>" class="btn btn-info" title="View Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="supplier_ledger.php?id=<?php echo $supplier['id']; ?>" class="btn btn-primary" title="View Ledger">
                                                    <i class="fas fa-book"></i>
                                                </a>
                                                <a href="?edit=<?php echo $supplier['id']; ?>" class="btn btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?delete=<?php echo $supplier['id']; ?>" class="btn btn-danger delete-confirm" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-3">
                                        <i class="fas fa-info-circle me-2"></i>No local suppliers found
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

<!-- Supplier Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1" aria-labelledby="supplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="supplierModalLabel">
                    <i class="fas fa-user-plus me-2"></i>
                    <?php echo $editSupplier ? 'Edit Supplier' : 'Add New Local Supplier'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" onsubmit="return validateSupplierForm()">
                <div class="modal-body">
                    <?php if ($editSupplier): ?>
                        <input type="hidden" name="id" value="<?php echo $editSupplier['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="supplier_name" class="form-label">Supplier Name *</label>
                            <input type="text" class="form-control" id="supplier_name" name="supplier_name" 
                                   value="<?php echo $editSupplier ? htmlspecialchars($editSupplier['supplier_name']) : ''; ?>" 
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="company_name" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" 
                                   value="<?php echo $editSupplier ? htmlspecialchars($editSupplier['company_name']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone" 
                                   value="<?php echo $editSupplier ? htmlspecialchars($editSupplier['phone']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo $editSupplier ? htmlspecialchars($editSupplier['email']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="cnic" class="form-label">CNIC</label>
                            <input type="text" class="form-control" id="cnic" name="cnic" 
                                   value="<?php echo $editSupplier ? htmlspecialchars($editSupplier['cnic']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="ntn" class="form-label">NTN</label>
                            <input type="text" class="form-control" id="ntn" name="ntn" 
                                   value="<?php echo $editSupplier ? htmlspecialchars($editSupplier['ntn']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label">City</label>
                            <input type="text" class="form-control" id="city" name="city" 
                                   value="<?php echo $editSupplier ? htmlspecialchars($editSupplier['city']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="country" class="form-label">Country</label>
                            <input type="text" class="form-control" id="country" name="country" 
                                   value="<?php echo $editSupplier ? htmlspecialchars($editSupplier['country']) : 'Pakistan'; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="opening_balance" class="form-label">Opening Balance (PKR)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" class="form-control" id="opening_balance" name="opening_balance" 
                                       value="<?php echo $editSupplier ? $editSupplier['opening_balance'] : '0.00'; ?>">
                                <select class="form-select" name="opening_balance_type" style="max-width:150px">
                                    <option value="payable" <?php echo ($editSupplier && ($editSupplier['opening_balance_type'] ?? 'payable') == 'payable') ? 'selected' : 'selected'; ?>>We Pay (Payable)</option>
                                    <option value="receivable" <?php echo ($editSupplier && ($editSupplier['opening_balance_type'] ?? '') == 'receivable') ? 'selected' : ''; ?>>We Receive (Receivable)</option>
                                </select>
                            </div>
                            <small class="text-muted">Payable = we owe supplier | Receivable = supplier owes us</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?php echo ($editSupplier && $editSupplier['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($editSupplier && $editSupplier['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?php echo $editSupplier ? htmlspecialchars($editSupplier['address']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i><?php echo $editSupplier ? 'Update' : 'Save'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Open modal for edit
$(document).ready(function() {
    <?php if (isset($_GET['edit']) && $editSupplier): ?>
        $('#supplierModal').modal('show');
    <?php endif; ?>
});

// Form validation
function validateSupplierForm() {
    var supplierName = document.getElementById('supplier_name').value.trim();
    
    if (supplierName === '') {
        alert('Please enter supplier name.');
        document.getElementById('supplier_name').focus();
        return false;
    }
    
    return true;
}
</script>

<?php
include '../includes/footer.php';
?>