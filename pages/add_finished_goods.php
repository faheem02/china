<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Add Finished Good';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

$editId = isset($_GET['edit']) && is_numeric($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editProduct = null;
if ($editId) {
    $editProduct = getRow("SELECT * FROM finished_goods WHERE id = ?", 'i', [$editId]);
    if ($editProduct) $pageTitle = 'Edit Finished Good';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $product_code = trim($_POST['product_code']);
    $product_name = trim($_POST['product_name']);
    $category = trim($_POST['category']);
    $unit = trim($_POST['unit']);
    $current_stock = (float)$_POST['current_stock'];
    $minimum_stock = (float)$_POST['minimum_stock'];
    $selling_price = (float)$_POST['selling_price'];
    $cost_price = (float)$_POST['cost_price'];
    $status = $_POST['status'];

    if (empty($product_code)) {
        $message = 'Product code is required!';
        $messageType = 'danger';
    } elseif (empty($product_name)) {
        $message = 'Product name is required!';
        $messageType = 'danger';
    } elseif (empty($unit)) {
        $message = 'Unit is required!';
        $messageType = 'danger';
    } else {
        if ($id > 0) {
            $sql = "UPDATE finished_goods SET product_code=?, product_name=?, category=?, unit=?, current_stock=?, minimum_stock=?, selling_price=?, cost_price=?, status=?, date_time=? WHERE id=?";
            $result = modifyData($sql, 'ssssddddssi', [
                $product_code, $product_name, $category, $unit,
                $current_stock, $minimum_stock, $selling_price, $cost_price,
                $status, getCurrentDateTime(), $id
            ]);
            if ($result !== false) {
                setFlash('Product updated successfully!', 'success');
                header('Location: finished_goods.php');
                exit;
            } else {
                $message = 'Error updating product!';
                $messageType = 'danger';
            }
        } else {
            $check = getRow("SELECT id FROM finished_goods WHERE product_code = ?", 's', [$product_code]);
            if ($check) {
                $message = 'Product code already exists!';
                $messageType = 'danger';
            } else {
                $sql = "INSERT INTO finished_goods (product_code, product_name, category, unit, current_stock, minimum_stock, selling_price, cost_price, status, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $result = insertData($sql, 'ssssddddss', [
                    $product_code, $product_name, $category, $unit,
                    $current_stock, $minimum_stock, $selling_price, $cost_price,
                    $status, getCurrentDateTime()
                ]);

                if ($result !== false) {
                    setFlash('Product added successfully!', 'success');
                    header('Location: finished_goods.php');
                    exit;
                } else {
                    $message = 'Error adding product!';
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
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-box me-2"></i><?php echo $editProduct ? 'Edit Finished Good' : 'Add New Finished Good'; ?></span>
                <a href="finished_goods.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php if ($editProduct): ?>
                        <input type="hidden" name="id" value="<?php echo $editProduct['id']; ?>">
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Product Code *</label>
                            <input type="text" class="form-control" name="product_code" 
                                   value="<?php echo $editProduct ? htmlspecialchars($editProduct['product_code']) : generateCode('FG'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Product Name *</label>
                            <input type="text" class="form-control" name="product_name" value="<?php echo $editProduct ? htmlspecialchars($editProduct['product_name']) : ''; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" class="form-control" name="category" value="<?php echo $editProduct ? htmlspecialchars($editProduct['category']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unit *</label>
                            <input type="text" class="form-control" name="unit" value="<?php echo $editProduct ? htmlspecialchars($editProduct['unit']) : ''; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Current Stock</label>
                            <input type="number" step="0.01" class="form-control" name="current_stock" value="<?php echo $editProduct ? $editProduct['current_stock'] : '0.00'; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Minimum Stock</label>
                            <input type="number" step="0.01" class="form-control" name="minimum_stock" value="<?php echo $editProduct ? $editProduct['minimum_stock'] : '0.00'; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Selling Price (PKR)</label>
                            <input type="number" step="0.01" class="form-control" name="selling_price" value="<?php echo $editProduct ? $editProduct['selling_price'] : '0.00'; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cost Price (PKR)</label>
                            <input type="number" step="0.01" class="form-control" name="cost_price" value="<?php echo $editProduct ? $editProduct['cost_price'] : '0.00'; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" <?php echo $editProduct && $editProduct['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $editProduct && $editProduct['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <hr>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i><?php echo $editProduct ? 'Update Product' : 'Save Product'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
