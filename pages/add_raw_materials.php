<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Add Raw Material';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

$chineseSuppliers = getRows("SELECT id, supplier_name FROM chinese_suppliers WHERE status = 'active' ORDER BY supplier_name");
$localSuppliers = getRows("SELECT id, supplier_name FROM local_suppliers WHERE status = 'active' ORDER BY supplier_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $material_code = trim($_POST['material_code']);
    $material_name = trim($_POST['material_name']);
    $category = trim($_POST['category']);
    $unit = trim($_POST['unit']);
    $current_stock = (float)$_POST['current_stock'];
    $minimum_stock = (float)$_POST['minimum_stock'];
    $purchase_price_pkr = (float)$_POST['purchase_price_pkr'];
    $purchase_price_cny = (float)$_POST['purchase_price_cny'];
    $selling_price = (float)$_POST['selling_price'];
    $supplier_type = $_POST['supplier_type'];
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $status = $_POST['status'];

    if (empty($material_code)) {
        $message = 'Material code is required!';
        $messageType = 'danger';
    } elseif (empty($material_name)) {
        $message = 'Material name is required!';
        $messageType = 'danger';
    } elseif (empty($unit)) {
        $message = 'Unit is required!';
        $messageType = 'danger';
    } else {
        $check = getRow("SELECT id FROM raw_materials WHERE material_code = ?", 's', [$material_code]);
        if ($check) {
            $message = 'Material code already exists!';
            $messageType = 'danger';
        } else {
            $sql = "INSERT INTO raw_materials (material_code, material_name, category, unit, current_stock, minimum_stock, purchase_price_pkr, purchase_price_cny, selling_price, supplier_type, supplier_id, status, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $result = insertData($sql, 'ssssdddddssss', [
                $material_code, $material_name, $category, $unit,
                $current_stock, $minimum_stock, $purchase_price_pkr, $purchase_price_cny,
                $selling_price, $supplier_type, $supplier_id, $status, getCurrentDateTime()
            ]);

            if ($result !== false) {
                setFlash('Raw material added successfully!', 'success');
                header('Location: raw_materials.php');
                exit;
            } else {
                $message = 'Error adding raw material!';
                $messageType = 'danger';
            }
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
    <div class="col-lg-8 offset-lg-2">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-cube me-2"></i>Add New Raw Material</span>
                <a href="raw_materials.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Material Code *</label>
                            <input type="text" class="form-control" name="material_code" value="<?php echo generateCode('RM'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Material Name *</label>
                            <input type="text" class="form-control" name="material_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" class="form-control" name="category">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unit *</label>
                            <input type="text" class="form-control" name="unit" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Current Stock</label>
                            <input type="number" step="0.01" class="form-control" name="current_stock" value="0.00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Minimum Stock</label>
                            <input type="number" step="0.01" class="form-control" name="minimum_stock" value="0.00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Purchase Price (PKR)</label>
                            <input type="number" step="0.01" class="form-control" name="purchase_price_pkr" value="0.00">
                            <small class="text-muted">For local suppliers</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Purchase Price (CNY)</label>
                            <input type="number" step="0.01" class="form-control" name="purchase_price_cny" value="0.00">
                            <small class="text-muted">For Chinese suppliers</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Selling Price (PKR)</label>
                            <input type="number" step="0.01" class="form-control" name="selling_price" value="0.00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier Type</label>
                            <select class="form-select" name="supplier_type" id="supplier_type" onchange="toggleSupplier()">
                                <option value="local">Local</option>
                                <option value="chinese">Chinese</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="supplierDiv">
                            <label class="form-label">Supplier</label>
                            <select class="form-select" name="supplier_id" id="supplier_id">
                                <option value="">Select Supplier</option>
                                <?php foreach ($localSuppliers as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['supplier_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <hr>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Material
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
var chineseSuppliers = <?php echo json_encode($chineseSuppliers); ?>;
var localSuppliers = <?php echo json_encode($localSuppliers); ?>;

function toggleSupplier() {
    var type = document.getElementById('supplier_type').value;
    var sel = document.getElementById('supplier_id');
    sel.innerHTML = '<option value="">Select Supplier</option>';
    var list = type === 'chinese' ? chineseSuppliers : localSuppliers;
    list.forEach(function(s) {
        sel.innerHTML += '<option value="' + s.id + '">' + s.supplier_name + '</option>';
    });
}
</script>

<?php include '../includes/footer.php'; ?>
