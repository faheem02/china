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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $check = getRow("SELECT id FROM finished_goods WHERE product_code = ?", 's', [$product_code]);
        if ($check) {
            $message = 'Product code already exists!';
            $messageType = 'danger';
        } else {
            $sql = "INSERT INTO finished_goods (product_code, product_name, category, unit, current_stock, minimum_stock, selling_price, cost_price, status, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $result = insertData($sql, 'ssssddddds', [
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
                <span><i class="fas fa-box me-2"></i>Add New Finished Good</span>
                <a href="finished_goods.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Product Code *</label>
                            <input type="text" class="form-control" name="product_code" 
                                   value="<?php echo generateCode('FG'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Product Name *</label>
                            <input type="text" class="form-control" name="product_name" required>
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
                            <label class="form-label">Selling Price (PKR)</label>
                            <input type="number" step="0.01" class="form-control" name="selling_price" value="0.00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cost Price (PKR)</label>
                            <input type="number" step="0.01" class="form-control" name="cost_price" value="0.00">
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
                        <i class="fas fa-save me-2"></i>Save Product
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
