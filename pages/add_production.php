<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Add Production Order';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

$finishedGoods = getRows("SELECT id, product_code, product_name, unit, current_stock FROM finished_goods WHERE status = 'active' ORDER BY product_name");
$rawMaterials = getRows("SELECT id, material_code, material_name, unit, purchase_price_pkr, current_stock FROM raw_materials WHERE status = 'active' ORDER BY material_name");

foreach ($rawMaterials as &$m) {
    $m['pkr_price'] = (float)$m['purchase_price_pkr'];
}
unset($m);

$editOrder = null;
$editMaterials = [];
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editOrder = getRow("SELECT * FROM production WHERE id = ?", 'i', [$editId]);
    if ($editOrder) {
        $editMaterials = getRows("SELECT * FROM production_raw_materials WHERE production_id = ?", 'i', [$editId]);
        $pageTitle = 'Edit Production Order';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $production_date = $_POST['production_date'];
    $finished_good_id = (int)$_POST['finished_good_id'];
    $quantity = (float)$_POST['quantity'];
    $materials = isset($_POST['materials']) ? $_POST['materials'] : [];

    if (empty($production_date)) {
        $message = 'Please select production date!';
        $messageType = 'danger';
    } elseif (empty($finished_good_id)) {
        $message = 'Please select a finished good!';
        $messageType = 'danger';
    } elseif ($quantity <= 0) {
        $message = 'Quantity must be greater than 0!';
        $messageType = 'danger';
    } else {
        $currentDateTime = getCurrentDateTime();
        $production_no = generateCode('PRD');
        $totalMaterialCost = 0;

        foreach ($materials as $material) {
            if (!isset($material['quantity']) || !isset($material['unit_price'])) continue;
            $totalMaterialCost += (float)$material['quantity'] * (float)$material['unit_price'];
        }

        $unit_cost = $quantity > 0 ? round($totalMaterialCost / $quantity, 2) : 0;

        if ($id > 0) {
            $oldMats = getRows("SELECT material_id, quantity_used FROM production_raw_materials WHERE production_id = ?", 'i', [$id]);
            foreach ($oldMats as $om) {
                modifyData("UPDATE raw_materials SET current_stock = current_stock + ? WHERE id = ?", 'di', [$om['quantity_used'], $om['material_id']]);
            }

            modifyData("UPDATE production SET production_date = ?, finished_good_id = ?, quantity = ?, total_cost = ?, date_time = ? WHERE id = ?",
                'siddsi', [$production_date, $finished_good_id, $quantity, $totalMaterialCost, $currentDateTime, $id]);

            modifyData("DELETE FROM production_raw_materials WHERE production_id = ?", 'i', [$id]);

            foreach ($materials as $material) {
                if (!isset($material['material_id'], $material['quantity'], $material['unit_price'])) continue;
                $material_id = (int)$material['material_id'];
                $qty = (float)$material['quantity'];
                $price = (float)$material['unit_price'];
                $total = $qty * $price;

                insertData("INSERT INTO production_raw_materials (production_id, material_id, quantity_used, cost_per_unit, total_cost, date_time) VALUES (?, ?, ?, ?, ?, ?)",
                    'iiddds', [$id, $material_id, $qty, $price, $total, $currentDateTime]);

                modifyData("UPDATE raw_materials SET current_stock = current_stock - ?, date_time = ? WHERE id = ?",
                    'dsi', [$qty, $currentDateTime, $material_id]);
            }

            setFlash('Production order updated! Stock adjusted.', 'success');
            header('Location: view_production.php');
            exit;
        } else {
            $insufficientStock = false;
            foreach ($materials as $material) {
                if (!isset($material['material_id'], $material['quantity'])) continue;
                $check = getRow("SELECT current_stock FROM raw_materials WHERE id = ?", 'i', [(int)$material['material_id']]);
                if (!$check || (float)$check['current_stock'] < (float)$material['quantity']) {
                    $insufficientStock = true;
                    break;
                }
            }

            if ($insufficientStock) {
                $message = 'Insufficient stock for one or more materials!';
                $messageType = 'danger';
            } else {
                $result = insertData("INSERT INTO production (production_no, production_date, finished_good_id, quantity, total_cost, status, date_time) VALUES (?, ?, ?, ?, ?, 'completed', ?)",
                    'ssidds', [$production_no, $production_date, $finished_good_id, $quantity, $totalMaterialCost, $currentDateTime]);

                if ($result !== false) {
                    $productionId = $result;

                    foreach ($materials as $material) {
                        if (!isset($material['material_id'], $material['quantity'], $material['unit_price'])) continue;
                        $material_id = (int)$material['material_id'];
                        $qty = (float)$material['quantity'];
                        $price = (float)$material['unit_price'];
                        $total = $qty * $price;

                        insertData("INSERT INTO production_raw_materials (production_id, material_id, quantity_used, cost_per_unit, total_cost, date_time) VALUES (?, ?, ?, ?, ?, ?)",
                            'iiddds', [$productionId, $material_id, $qty, $price, $total, $currentDateTime]);

                        modifyData("UPDATE raw_materials SET current_stock = current_stock - ?, date_time = ? WHERE id = ?",
                            'dsi', [$qty, $currentDateTime, $material_id]);
                    }

                    $fg = getRow("SELECT current_stock, cost_price FROM finished_goods WHERE id = ?", 'i', [$finished_good_id]);
                    $oldStock = (float)$fg['current_stock'];
                    $oldCost = (float)$fg['cost_price'];
                    $avgCost = $oldStock > 0
                        ? round((($oldStock * $oldCost) + ($quantity * $unit_cost)) / ($oldStock + $quantity), 2)
                        : $unit_cost;

                    modifyData("UPDATE finished_goods SET current_stock = current_stock + ?, cost_price = ?, date_time = ? WHERE id = ?",
                        'ddsi', [$quantity, $avgCost, $currentDateTime, $finished_good_id]);

                    setFlash('Production completed! ' . number_format($quantity, 2) . ' units received. Unit cost: ' . formatCurrency($avgCost), 'success');
                    header('Location: view_production.php');
                    exit;
                } else {
                    $message = 'Error creating production order!';
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

<div class="row mb-3">
    <div class="col-12">
        <a href="view_production.php" class="btn btn-light"><i class="fas fa-arrow-left me-1"></i>Back to List</a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header" style="background:#1a2332;color:#fff;">
                <i class="fas fa-cogs me-2"></i><?php echo $editOrder ? 'Edit Production Order' : 'New Production Order'; ?>
            </div>
            <div class="card-body">
                <form method="POST" action="" onsubmit="return validateProductionForm()">
                    <?php if ($editOrder): ?>
                        <input type="hidden" name="id" value="<?php echo $editOrder['id']; ?>">
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="production_date" class="form-label">Production Date *</label>
                            <input type="date" class="form-control" id="production_date" name="production_date"
                                   value="<?php echo $editOrder ? $editOrder['production_date'] : date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="finished_good_id" class="form-label">Finished Good *</label>
                            <select class="form-select" id="finished_good_id" name="finished_good_id" required>
                                <option value="">Select Product</option>
                                <?php foreach ($finishedGoods as $fg): ?>
                                    <option value="<?php echo $fg['id']; ?>"
                                        <?php echo ($editOrder && $editOrder['finished_good_id'] == $fg['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($fg['product_code'] . ' - ' . $fg['product_name']); ?> (Stock: <?php echo number_format($fg['current_stock'], 2); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="quantity" class="form-label">Production Quantity *</label>
                            <input type="number" step="0.01" class="form-control" id="quantity" name="quantity"
                                   value="<?php echo $editOrder ? $editOrder['quantity'] : '0'; ?>" required
                                   onchange="updateCostSummary()" onkeyup="updateCostSummary()">
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-cubes me-2"></i>Materials Required</span>
                            <button type="button" class="btn btn-sm btn-success" onclick="addMaterialRow()">
                                <i class="fas fa-plus me-1"></i>Add Material
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0" id="materialTable">
                                    <thead>
                                        <tr>
                                            <th style="width:30%;">Material</th>
                                            <th style="width:12%;">Available Stock</th>
                                            <th style="width:15%;">Quantity Used</th>
                                            <th style="width:15%;">Cost Per Unit (PKR)</th>
                                            <th style="width:18%;">Total (PKR)</th>
                                            <th style="width:10%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="materialBody">
                                        <?php if ($editMaterials): ?>
                                            <?php foreach ($editMaterials as $item): ?>
                                                <tr>
                                                    <td>
                                                        <select class="form-select form-select-sm material-select" name="materials[][material_id]" required onchange="onMaterialSelect(this)">
                                                            <option value="">Select Material</option>
                                                            <?php foreach ($rawMaterials as $mat): ?>
                                                                <option value="<?php echo $mat['id']; ?>"
                                                                        <?php echo ($item['material_id'] == $mat['id']) ? 'selected' : ''; ?>
                                                                        data-price="<?php echo $mat['pkr_price']; ?>"
                                                                        data-stock="<?php echo $mat['current_stock']; ?>">
                                                                    <?php echo htmlspecialchars($mat['material_code'] . ' - ' . $mat['material_name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                    <td class="text-center stock-display"><?php echo number_format($item['quantity_used'], 2); ?></td>
                                                    <td>
                                                        <input type="number" step="0.01" class="form-control form-control-sm quantity" name="materials[][quantity]"
                                                               value="<?php echo $item['quantity_used']; ?>" required onchange="calculateRowTotal(this)" onkeyup="calculateRowTotal(this)">
                                                    </td>
                                                    <td>
                                                        <input type="number" step="0.01" class="form-control form-control-sm unit-price" name="materials[][unit_price]"
                                                               value="<?php echo $item['cost_per_unit']; ?>" required onchange="calculateRowTotal(this)" onkeyup="calculateRowTotal(this)">
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm row-total" value="<?php echo formatCurrency($item['total_cost']); ?>" readonly>
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td>
                                                    <select class="form-select form-select-sm material-select" name="materials[][material_id]" required onchange="onMaterialSelect(this)">
                                                        <option value="">Select Material</option>
                                                        <?php foreach ($rawMaterials as $mat): ?>
                                                            <option value="<?php echo $mat['id']; ?>"
                                                                    data-price="<?php echo $mat['pkr_price']; ?>"
                                                                    data-stock="<?php echo $mat['current_stock']; ?>">
                                                                <?php echo htmlspecialchars($mat['material_code'] . ' - ' . $mat['material_name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td class="text-center stock-display">0</td>
                                                <td>
                                                    <input type="number" step="0.01" class="form-control form-control-sm quantity" name="materials[][quantity]"
                                                           value="1" required onchange="calculateRowTotal(this)" onkeyup="calculateRowTotal(this)">
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" class="form-control form-control-sm unit-price" name="materials[][unit_price]"
                                                           value="0" required onchange="calculateRowTotal(this)" onkeyup="calculateRowTotal(this)">
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm row-total" value="0.00" readonly>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-primary">
                                            <th colspan="4" class="text-end">Total Material Cost</th>
                                            <th>
                                                <input type="text" class="form-control form-control-sm" id="total_material_cost" value="0.00" readonly>
                                            </th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card bg-light mb-3">
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-4 border-end">
                                    <small class="text-muted">Total Material Cost</small>
                                    <h4 class="mb-0 text-primary" id="summary_material_cost">PKR 0.00</h4>
                                </div>
                                <div class="col-md-4 border-end">
                                    <small class="text-muted">Production Quantity</small>
                                    <h4 class="mb-0 text-success" id="summary_quantity">0</h4>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Unit Production Cost</small>
                                    <h4 class="mb-0 text-warning" id="summary_unit_cost">PKR 0.00</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 mb-3">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i><?php echo $editOrder ? 'Update Order' : 'Create Order'; ?>
                        </button>
                        <a href="view_production.php" class="btn btn-secondary btn-lg ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
var materialsData = <?php echo json_encode($rawMaterials); ?>;

function onMaterialSelect(el) {
    var row = el.closest('tr');
    var opt = el.options[el.selectedIndex];
    var price = parseFloat(opt.dataset.price) || 0;
    var stock = parseFloat(opt.dataset.stock) || 0;
    row.querySelector('.unit-price').value = price.toFixed(2);
    row.querySelector('.stock-display').textContent = stock.toFixed(2);
    calculateRowTotal(row.querySelector('.quantity'));
}

function addMaterialRow() {
    var tbody = document.getElementById('materialBody');
    var row = tbody.insertRow();
    var html = '<td><select class="form-select form-select-sm material-select" name="materials[][material_id]" required onchange="onMaterialSelect(this)"><option value="">Select Material</option>';
    materialsData.forEach(function(m) {
        html += '<option value="' + m.id + '" data-price="' + (m.pkr_price || 0) + '" data-stock="' + m.current_stock + '">' + m.material_code + ' - ' + m.material_name + '</option>';
    });
    html += '</select></td>';
    html += '<td class="text-center stock-display">0</td>';
    html += '<td><input type="number" step="0.01" class="form-control form-control-sm quantity" name="materials[][quantity]" value="1" required onchange="calculateRowTotal(this)" onkeyup="calculateRowTotal(this)"></td>';
    html += '<td><input type="number" step="0.01" class="form-control form-control-sm unit-price" name="materials[][unit_price]" value="0" required onchange="calculateRowTotal(this)" onkeyup="calculateRowTotal(this)"></td>';
    html += '<td><input type="text" class="form-control form-control-sm row-total" value="0.00" readonly></td>';
    html += '<td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>';
    row.innerHTML = html;
}

function removeRow(btn) {
    var tbody = document.getElementById('materialBody');
    if (tbody.rows.length > 1) {
        btn.closest('tr').remove();
        calculateGrandTotal();
    } else {
        alert('At least one material is required!');
    }
}

function calculateRowTotal(el) {
    var row = el.closest('tr');
    var qty = parseFloat(row.querySelector('.quantity').value) || 0;
    var price = parseFloat(row.querySelector('.unit-price').value) || 0;
    var total = qty * price;
    row.querySelector('.row-total').value = total.toFixed(2);
    calculateGrandTotal();
}

function calculateGrandTotal() {
    var grandTotal = 0;
    document.querySelectorAll('#materialBody tr').forEach(function(row) {
        grandTotal += parseFloat(row.querySelector('.row-total').value) || 0;
    });
    document.getElementById('total_material_cost').value = grandTotal.toFixed(2);
    updateCostSummary();
}

function updateCostSummary() {
    var totalCost = parseFloat(document.getElementById('total_material_cost').value) || 0;
    var qty = parseFloat(document.getElementById('quantity').value) || 0;
    var unitCost = qty > 0 ? totalCost / qty : 0;

    document.getElementById('summary_material_cost').textContent = 'PKR ' + totalCost.toFixed(2);
    document.getElementById('summary_quantity').textContent = qty;
    document.getElementById('summary_unit_cost').textContent = 'PKR ' + unitCost.toFixed(2);
}

function validateProductionForm() {
    var date = document.getElementById('production_date').value;
    var fg = document.getElementById('finished_good_id').value;
    var qty = parseFloat(document.getElementById('quantity').value) || 0;
    var rows = document.querySelectorAll('#materialBody tr');

    if (date === '') {
        alert('Please select production date.');
        return false;
    }
    if (fg === '') {
        alert('Please select a finished good.');
        return false;
    }
    if (qty <= 0) {
        alert('Please enter a valid quantity.');
        return false;
    }
    if (rows.length === 0) {
        alert('Please add at least one material.');
        return false;
    }
    var valid = true;
    rows.forEach(function(row) {
        var material = row.querySelector('.material-select').value;
        var qtyVal = parseFloat(row.querySelector('.quantity').value) || 0;
        if (material === '' || qtyVal <= 0) valid = false;
    });
    if (!valid) {
        alert('Please ensure all materials have valid data.');
        return false;
    }
    return true;
}

$(document).ready(function() {
    <?php if ($editOrder): ?>
        setTimeout(calculateGrandTotal, 300);
    <?php else: ?>
        calculateGrandTotal();
    <?php endif; ?>
});
</script>

<?php include '../includes/footer.php'; ?>
