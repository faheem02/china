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

$editId = isset($_GET['edit']) && is_numeric($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editMaterial = null;
if ($editId) {
    $editMaterial = getRow("SELECT * FROM raw_materials WHERE id = ?", 'i', [$editId]);
    if ($editMaterial) $pageTitle = 'Edit Raw Material';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $material_code = trim($_POST['material_code']);
    $material_name = trim($_POST['material_name']);
    $category = trim($_POST['category']);
    $unit = trim($_POST['unit']);
    $current_stock = (float)$_POST['current_stock'];
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
        if ($id > 0) {
            $sql = "UPDATE raw_materials SET material_code=?, material_name=?, category=?, unit=?, current_stock=?, purchase_price_pkr=?, purchase_price_cny=?, selling_price=?, supplier_type=?, supplier_id=?, status=?, date_time=? WHERE id=?";
            $result = modifyData($sql, 'ssssddddssssi', [
                $material_code, $material_name, $category, $unit,
                $current_stock, $purchase_price_pkr, $purchase_price_cny,
                $selling_price, $supplier_type, $supplier_id, $status, getCurrentDateTime(), $id
            ]);
            if ($result !== false) {
                setFlash('Raw material updated successfully!', 'success');
                header('Location: raw_materials.php');
                exit;
            } else {
                $message = 'Error updating raw material!';
                $messageType = 'danger';
            }
        } else {
            $check = getRow("SELECT id FROM raw_materials WHERE material_code = ?", 's', [$material_code]);
            if ($check) {
                $message = 'Material code already exists!';
                $messageType = 'danger';
            } else {
                $sql = "INSERT INTO raw_materials (material_code, material_name, category, unit, current_stock, purchase_price_pkr, purchase_price_cny, selling_price, supplier_type, supplier_id, status, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $result = insertData($sql, 'ssssddddssss', [
                    $material_code, $material_name, $category, $unit,
                    $current_stock, $purchase_price_pkr, $purchase_price_cny,
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
                <span><i class="fas fa-cube me-2"></i><?php echo $editMaterial ? 'Edit Raw Material' : 'Add New Raw Material'; ?></span>
                <a href="raw_materials.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php if ($editMaterial): ?>
                        <input type="hidden" name="id" value="<?php echo $editMaterial['id']; ?>">
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Material Code *</label>
                            <input type="text" class="form-control" name="material_code" value="<?php echo $editMaterial ? htmlspecialchars($editMaterial['material_code']) : generateCode('RM'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Material Name *</label>
                            <input type="text" class="form-control" name="material_name" value="<?php echo $editMaterial ? htmlspecialchars($editMaterial['material_name']) : ''; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" class="form-control" name="category" value="<?php echo $editMaterial ? htmlspecialchars($editMaterial['category']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unit *</label>
                            <input type="text" class="form-control" name="unit" value="<?php echo $editMaterial ? htmlspecialchars($editMaterial['unit']) : ''; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Current Stock</label>
                            <input type="number" step="0.01" class="form-control" name="current_stock" value="<?php echo $editMaterial ? $editMaterial['current_stock'] : '0.00'; ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Purchase Price (PKR)</label>
                            <input type="number" step="0.01" class="form-control" name="purchase_price_pkr" value="<?php echo $editMaterial ? $editMaterial['purchase_price_pkr'] : '0.00'; ?>">
                            <small class="text-muted">For local suppliers</small>
                        </div>
                        <div class="col-md-6 mb-3" id="priceCnyDiv" style="<?php echo $editMaterial && $editMaterial['supplier_type'] == 'chinese' ? '' : 'display:none;'; ?>">
                            <label class="form-label">Purchase Price (CNY)</label>
                            <input type="number" step="0.01" class="form-control" name="purchase_price_cny" value="<?php echo $editMaterial ? $editMaterial['purchase_price_cny'] : '0.00'; ?>">
                            <small class="text-muted">For Chinese suppliers</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Selling Price (PKR)</label>
                            <input type="number" step="0.01" class="form-control" name="selling_price" value="<?php echo $editMaterial ? $editMaterial['selling_price'] : '0.00'; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier Type</label>
                            <select class="form-select" name="supplier_type" id="supplier_type" onchange="toggleSupplier()">
                                <option value="local" <?php echo $editMaterial && $editMaterial['supplier_type'] == 'local' ? 'selected' : ''; ?>>Local</option>
                                <option value="chinese" <?php echo $editMaterial && $editMaterial['supplier_type'] == 'chinese' ? 'selected' : ''; ?>>Chinese</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="supplierDiv">
                            <label class="form-label">Supplier</label>
                            <select class="form-select" name="supplier_id" id="supplier_id">
                                <option value="">Select Supplier</option>
                                <?php if ($editMaterial && $editMaterial['supplier_type'] == 'chinese'): ?>
                                    <?php foreach ($chineseSuppliers as $s): ?>
                                        <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $editMaterial['supplier_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['supplier_name']); ?></option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php foreach ($localSuppliers as $s): ?>
                                        <option value="<?php echo $s['id']; ?>" <?php echo $editMaterial && $s['id'] == $editMaterial['supplier_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['supplier_name']); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" <?php echo $editMaterial && $editMaterial['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $editMaterial && $editMaterial['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <hr>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i><?php echo $editMaterial ? 'Update Material' : 'Save Material'; ?>
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
    var curVal = sel.value;
    sel.innerHTML = '<option value="">Select Supplier</option>';
    var list = type === 'chinese' ? chineseSuppliers : localSuppliers;
    list.forEach(function(s) {
        var selAttr = s.id == curVal ? ' selected' : '';
        sel.innerHTML += '<option value="' + s.id + '"' + selAttr + '>' + s.supplier_name + '</option>';
    });
    document.getElementById('priceCnyDiv').style.display = type === 'chinese' ? '' : 'none';
}
</script>

<?php include '../includes/footer.php'; ?>
