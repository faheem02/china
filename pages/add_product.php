<?php
/**
 * Add/Edit Product Page
 * Manufacturing ERP System
 */

// Include database connection
require_once '../includes/database.php';

// Require login
requireLogin();

$pageTitle = 'Add Product';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

// Get product for edit
$editProduct = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $sql = "SELECT * FROM finished_goods WHERE id = ?";
    $editProduct = getRow($sql, 'i', [$editId]);
    
    if ($editProduct) {
        $pageTitle = 'Edit Product';
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $product_code = trim($_POST['product_code']);
    $product_name = trim($_POST['product_name']);
    $category = trim($_POST['category']);
    $unit = trim($_POST['unit']);
    $opening_stock = (float)$_POST['opening_stock'];
    $minimum_stock = (float)$_POST['minimum_stock'];
    $selling_price = (float)$_POST['selling_price'];
    $cost_price = (float)$_POST['cost_price'];
    $status = $_POST['status'];
    
    // Validation
    $errors = [];
    
    if (empty($product_code)) {
        $errors[] = 'Product code is required.';
    }
    if (empty($product_name)) {
        $errors[] = 'Product name is required.';
    }
    if (empty($unit)) {
        $errors[] = 'Unit is required.';
    }
    if ($opening_stock < 0) {
        $errors[] = 'Opening stock cannot be negative.';
    }
    if ($minimum_stock < 0) {
        $errors[] = 'Minimum stock cannot be negative.';
    }
    if ($selling_price < 0) {
        $errors[] = 'Selling price cannot be negative.';
    }
    if ($cost_price < 0) {
        $errors[] = 'Cost price cannot be negative.';
    }
    
    if (empty($errors)) {
        $currentDateTime = getCurrentDateTime();
        
        // Check if product code exists
        $checkSql = "SELECT id FROM finished_goods WHERE product_code = ? AND id != ?";
        $check = getRow($checkSql, 'si', [$product_code, $id]);
        
        if ($check) {
            $message = 'Product code already exists!';
            $messageType = 'danger';
        } else {
            if ($id > 0) {
                // Update - Keep existing stock, update other fields
                $sql = "UPDATE finished_goods SET 
                        product_code = ?,
                        product_name = ?,
                        category = ?,
                        unit = ?,
                        minimum_stock = ?,
                        selling_price = ?,
                        cost_price = ?,
                        status = ?,
                        date_time = ?
                        WHERE id = ?";
                
                $params = [
                    $product_code,
                    $product_name,
                    $category,
                    $unit,
                    $minimum_stock,
                    $selling_price,
                    $cost_price,
                    $status,
                    $currentDateTime,
                    $id
                ];
                
                $result = modifyData($sql, 'ssssdddsi', $params);
                
                if ($result !== false) {
                    setFlash('Product updated successfully!', 'success');
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    $message = 'Error updating product!';
                    $messageType = 'danger';
                }
            } else {
                // Insert new product with opening stock
                $sql = "INSERT INTO finished_goods (
                        product_code, product_name, category, unit, 
                        current_stock, minimum_stock, selling_price, cost_price, 
                        status, date_time
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $params = [
                    $product_code,
                    $product_name,
                    $category,
                    $unit,
                    $opening_stock,
                    $minimum_stock,
                    $selling_price,
                    $cost_price,
                    $status,
                    $currentDateTime
                ];
                
                $result = insertData($sql, 'ssssddddss', $params);
                
                if ($result !== false) {
                    setFlash('Product added successfully!', 'success');
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    $message = 'Error adding product!';
                    $messageType = 'danger';
                }
            }
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'danger';
    }
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

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-<?php echo $editProduct ? 'edit' : 'plus'; ?> me-2"></i>
                <?php echo $editProduct ? 'Edit Product' : 'Add New Product'; ?></span>
                <a href="products.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <form method="POST" action="" onsubmit="return validateProductForm()">
                    <?php if ($editProduct): ?>
                        <input type="hidden" name="id" value="<?php echo $editProduct['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="product_code" class="form-label">Product Code *</label>
                            <input type="text" class="form-control" id="product_code" name="product_code" 
                                   value="<?php echo $editProduct ? htmlspecialchars($editProduct['product_code']) : generateCode('FG'); ?>" 
                                   required <?php echo $editProduct ? 'readonly' : ''; ?>>
                            <?php if ($editProduct): ?>
                                <small class="text-muted">Product code cannot be changed</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="product_name" class="form-label">Product Name *</label>
                            <input type="text" class="form-control" id="product_name" name="product_name" 
                                   value="<?php echo $editProduct ? htmlspecialchars($editProduct['product_name']) : ''; ?>" 
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="category" class="form-label">Category</label>
                            <input type="text" class="form-control" id="category" name="category" 
                                   value="<?php echo $editProduct ? htmlspecialchars($editProduct['category']) : ''; ?>"
                                   placeholder="e.g., Electronics, Furniture, etc.">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="unit" class="form-label">Unit *</label>
                            <input type="text" class="form-control" id="unit" name="unit" 
                                   value="<?php echo $editProduct ? htmlspecialchars($editProduct['unit']) : ''; ?>" 
                                   placeholder="e.g., Pcs, Kg, Meter, etc." required>
                        </div>
                        
                        <?php if (!$editProduct): ?>
                        <div class="col-md-6 mb-3">
                            <label for="opening_stock" class="form-label">Opening Stock *</label>
                            <input type="number" step="0.01" class="form-control" id="opening_stock" name="opening_stock" 
                                   value="0" min="0" required>
                            <small class="text-muted">Initial stock quantity for this product</small>
                        </div>
                        <?php else: ?>
                        <div class="col-md-6 mb-3">
                            <label for="current_stock" class="form-label">Current Stock</label>
                            <input type="text" class="form-control" id="current_stock" name="current_stock" 
                                   value="<?php echo number_format($editProduct['current_stock'], 2); ?>" readonly>
                            <small class="text-muted">Current stock cannot be changed here. Use production or sales.</small>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-6 mb-3">
                            <label for="minimum_stock" class="form-label">Minimum Stock Level</label>
                            <input type="number" step="0.01" class="form-control" id="minimum_stock" name="minimum_stock" 
                                   value="<?php echo $editProduct ? $editProduct['minimum_stock'] : '0'; ?>" min="0">
                            <small class="text-muted">Alert will be shown when stock falls below this level</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="selling_price" class="form-label">Selling Price (PKR)</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <input type="number" step="0.01" class="form-control" id="selling_price" name="selling_price" 
                                       value="<?php echo $editProduct ? $editProduct['selling_price'] : '0'; ?>" min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="cost_price" class="form-label">Cost Price (PKR)</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <input type="number" step="0.01" class="form-control" id="cost_price" name="cost_price" 
                                       value="<?php echo $editProduct ? $editProduct['cost_price'] : '0'; ?>" min="0">
                            </div>
                            <small class="text-muted">Production cost per unit</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?php echo ($editProduct && $editProduct['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($editProduct && $editProduct['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Profit Margin Preview -->
                    <div class="alert alert-info" id="profitPreview" style="display: none;">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Profit Margin Preview:</strong>
                        <span id="profitMarginText">0.00%</span>
                        <br><small>Based on Selling Price vs Cost Price</small>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="products.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            <?php echo $editProduct ? 'Update Product' : 'Save Product'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Calculate and show profit margin preview
$(document).ready(function() {
    function calculateProfitMargin() {
        var sellingPrice = parseFloat($('#selling_price').val()) || 0;
        var costPrice = parseFloat($('#cost_price').val()) || 0;
        
        if (sellingPrice > 0 && costPrice > 0) {
            var margin = ((sellingPrice - costPrice) / costPrice) * 100;
            $('#profitMarginText').html(margin.toFixed(2) + '%');
            $('#profitPreview').show();
            
            if (margin > 0) {
                $('#profitMarginText').css('color', 'green');
            } else if (margin < 0) {
                $('#profitMarginText').css('color', 'red');
            } else {
                $('#profitMarginText').css('color', 'orange');
            }
        } else {
            $('#profitPreview').hide();
        }
    }
    
    $('#selling_price, #cost_price').on('keyup change', calculateProfitMargin);
    calculateProfitMargin();
});

// Form validation
function validateProductForm() {
    var code = document.getElementById('product_code').value.trim();
    var name = document.getElementById('product_name').value.trim();
    var unit = document.getElementById('unit').value.trim();
    var sellingPrice = parseFloat(document.getElementById('selling_price').value) || 0;
    var costPrice = parseFloat(document.getElementById('cost_price').value) || 0;
    
    if (code === '') {
        alert('Please enter product code.');
        document.getElementById('product_code').focus();
        return false;
    }
    
    if (name === '') {
        alert('Please enter product name.');
        document.getElementById('product_name').focus();
        return false;
    }
    
    if (unit === '') {
        alert('Please enter unit.');
        document.getElementById('unit').focus();
        return false;
    }
    
    // Check opening stock for new product
    <?php if (!$editProduct): ?>
        var openingStock = parseFloat(document.getElementById('opening_stock').value) || 0;
        if (openingStock < 0) {
            alert('Opening stock cannot be negative.');
            document.getElementById('opening_stock').focus();
            return false;
        }
    <?php endif; ?>
    
    // Check minimum stock
    var minStock = parseFloat(document.getElementById('minimum_stock').value) || 0;
    if (minStock < 0) {
        alert('Minimum stock cannot be negative.');
        document.getElementById('minimum_stock').focus();
        return false;
    }
    
    if (sellingPrice < 0) {
        alert('Selling price cannot be negative.');
        document.getElementById('selling_price').focus();
        return false;
    }
    
    if (costPrice < 0) {
        alert('Cost price cannot be negative.');
        document.getElementById('cost_price').focus();
        return false;
    }
    
    return true;
}
</script>

<?php
include '../includes/footer.php';
?>