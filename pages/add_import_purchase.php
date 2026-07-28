<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Add Import Purchase';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

$cnyCurrency = getRow("SELECT id, currency_code, exchange_rate FROM currencies WHERE currency_code = 'CNY'");
$cnyId = $cnyCurrency ? $cnyCurrency['id'] : 2;
$exchangeRate = $cnyCurrency ? (float)$cnyCurrency['exchange_rate'] : 1.00;

$suppliers = getRows("SELECT id, supplier_name, company_name, current_balance FROM chinese_suppliers WHERE status = 'active' ORDER BY supplier_name");
$rawMaterials = getRows("SELECT id, material_code, material_name, unit, purchase_price_pkr, selling_price, current_stock FROM raw_materials WHERE status = 'active' ORDER BY material_name");
$finishedGoods = getRows("SELECT id, product_code, product_name, unit, cost_price, selling_price, current_stock FROM finished_goods WHERE status = 'active' ORDER BY product_name");

$materials = [];
foreach ($rawMaterials as $m) {
    $m['item_type'] = 'raw_material';
    $m['display_label'] = '[Raw] ' . $m['material_code'] . ' - ' . $m['material_name'];
    $m['price_pkr'] = (float)$m['purchase_price_pkr'];
    $materials[] = $m;
}
foreach ($finishedGoods as $g) {
    $g['item_type'] = 'finished_good';
    $g['display_label'] = '[FG] ' . $g['product_code'] . ' - ' . $g['product_name'];
    $g['id'] = 'fg_' . $g['id'];
    $g['price_pkr'] = (float)$g['cost_price'];
    $g['material_code'] = $g['product_code'];
    $g['material_name'] = $g['product_name'];
    $materials[] = $g;
}

$editPurchase = null;
$editItems = [];
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editPurchase = getRow("SELECT * FROM import_purchases WHERE id = ?", 'i', [$editId]);
    if ($editPurchase) {
        $editItems = getRows("SELECT ipi.*, 
            COALESCE(rm.material_name, fg.product_name) as item_name,
            COALESCE(rm.material_code, fg.product_code) as item_code
            FROM import_purchase_items ipi 
            LEFT JOIN raw_materials rm ON ipi.material_id = rm.id AND ipi.item_type = 'raw_material'
            LEFT JOIN finished_goods fg ON ipi.material_id = fg.id AND ipi.item_type = 'finished_good'
            WHERE ipi.purchase_id = ?", 'i', [$editId]);
        $pageTitle = 'Edit Import Purchase';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $supplier_id = (int)$_POST['supplier_id'];
    $purchase_date = $_POST['purchase_date'];
    $invoice_no = trim($_POST['invoice_no']);
    $exchange_rate = (float)$_POST['exchange_rate'];
    $previous_amount_cny = (float)$_POST['previous_amount_cny'];
    $total_cny = (float)$_POST['total_cny'];
    $total_pkr = (float)$_POST['total_pkr'];
    $tax_amount_cny = (float)$_POST['tax_amount_cny'];
    $grand_total_cny = (float)$_POST['grand_total_cny'];
    $grand_total_pkr = (float)$_POST['grand_total_pkr'];
    $materialsPost = isset($_POST['materials']) ? $_POST['materials'] : [];

    if (empty($supplier_id)) {
        $message = 'Please select a supplier!';
        $messageType = 'danger';
    } elseif (empty($purchase_date)) {
        $message = 'Please select purchase date!';
        $messageType = 'danger';
    } elseif ($exchange_rate <= 0) {
        $message = 'Exchange rate must be greater than 0!';
        $messageType = 'danger';
    } elseif (empty($materialsPost)) {
        $message = 'Please add at least one material!';
        $messageType = 'danger';
    } else {
        $currentDateTime = getCurrentDateTime();
        $total_pkr_val = $total_cny * $exchange_rate;
        $grand_total_pkr_val = $grand_total_cny * $exchange_rate;

        if ($id > 0) {
            $oldItems = getRows("SELECT material_id, quantity, item_type FROM import_purchase_items WHERE purchase_id = ?", 'i', [$id]);
            foreach ($oldItems as $oldItem) {
                $matId = (int)$oldItem['material_id'];
                $qty = (float)$oldItem['quantity'];
                if ($oldItem['item_type'] == 'finished_good') {
                    modifyData("UPDATE finished_goods SET current_stock = current_stock - ? WHERE id = ?", 'di', [$qty, $matId]);
                } else {
                    modifyData("UPDATE raw_materials SET current_stock = current_stock - ? WHERE id = ?", 'di', [$qty, $matId]);
                }
            }

            $result = modifyData("UPDATE import_purchases SET supplier_id=?, purchase_date=?, invoice_no=?, exchange_rate=?, total_cny=?, total_pkr=?, previous_amount_cny=?, tax_amount_cny=?, grand_total_cny=?, grand_total_pkr=?, balance_cny=?, balance_pkr=?, date_time=? WHERE id=?",
                'issdddddddddssi', [$supplier_id, $purchase_date, $invoice_no, $exchange_rate, $total_cny, $total_pkr_val, $previous_amount_cny, $tax_amount_cny, $grand_total_cny, $grand_total_pkr_val, $grand_total_cny, $grand_total_pkr_val, $currentDateTime, $id]);

            if ($result !== false) {
                modifyData("DELETE FROM import_purchase_items WHERE purchase_id = ?", 'i', [$id]);
                foreach ($materialsPost as $mat) {
                    $rawId = intval($mat['material_id'] ?? 0);
                    $qty = (float)($mat['quantity'] ?? 0);
                    $upCny = (float)($mat['unit_price_cny'] ?? 0);
                    $itemType = $mat['item_type'] ?? 'raw_material';
                    $realId = 0;
                    if (is_string($mat['material_id'] ?? '') && strpos($mat['material_id'], 'fg_') === 0) {
                        $itemType = 'finished_good';
                        $realId = (int)substr($mat['material_id'], 3);
                    } else {
                        $realId = $rawId;
                    }
                    if ($realId <= 0 || $qty <= 0) continue;
                    $upPkr = $upCny * $exchange_rate;
                    $totCny = $qty * $upCny;
                    $totPkr = $qty * $upPkr;
                    insertData("INSERT INTO import_purchase_items (purchase_id, material_id, item_type, quantity, unit_price_cny, unit_price_pkr, total_cny, total_pkr, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        'iisddddds', [$id, $realId, $itemType, $qty, $upCny, $upPkr, $totCny, $totPkr, $currentDateTime]);
                    if ($itemType == 'finished_good') {
                        modifyData("UPDATE finished_goods SET current_stock = current_stock + ? WHERE id = ?", 'di', [$qty, $realId]);
                    } else {
                        modifyData("UPDATE raw_materials SET current_stock = current_stock + ? WHERE id = ?", 'di', [$qty, $realId]);
                    }
                }
                setFlash('Import purchase updated successfully!', 'success');
                header('Location: import_purchase_print.php?id=' . $id);
                exit;
            } else {
                $message = 'Error updating import purchase!';
                $messageType = 'danger';
            }
        } else {
            $purchase_no = generateCode('IP');
            $result = insertData("INSERT INTO import_purchases (purchase_no, supplier_id, purchase_date, invoice_no, exchange_rate, total_cny, total_pkr, previous_amount_cny, tax_amount_cny, grand_total_cny, grand_total_pkr, balance_cny, balance_pkr, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                'sissddddddddds', [$purchase_no, $supplier_id, $purchase_date, $invoice_no, $exchange_rate, $total_cny, $total_pkr_val, $previous_amount_cny, $tax_amount_cny, $grand_total_cny, $grand_total_pkr_val, $grand_total_cny, $grand_total_pkr_val, $currentDateTime]);

            if ($result !== false) {
                $purchase_id = $result;
                foreach ($materialsPost as $mat) {
                    $rawId = intval($mat['material_id'] ?? 0);
                    $qty = (float)($mat['quantity'] ?? 0);
                    $upCny = (float)($mat['unit_price_cny'] ?? 0);
                    $itemType = 'raw_material';
                    $realId = 0;
                    if (is_string($mat['material_id'] ?? '') && strpos($mat['material_id'], 'fg_') === 0) {
                        $itemType = 'finished_good';
                        $realId = (int)substr($mat['material_id'], 3);
                    } else {
                        $realId = $rawId;
                    }
                    if ($realId <= 0 || $qty <= 0) continue;
                    $upPkr = $upCny * $exchange_rate;
                    $totCny = $qty * $upCny;
                    $totPkr = $qty * $upPkr;
                    insertData("INSERT INTO import_purchase_items (purchase_id, material_id, item_type, quantity, unit_price_cny, unit_price_pkr, total_cny, total_pkr, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        'iisddddds', [$purchase_id, $realId, $itemType, $qty, $upCny, $upPkr, $totCny, $totPkr, $currentDateTime]);
                    if ($itemType == 'finished_good') {
                        modifyData("UPDATE finished_goods SET current_stock = current_stock + ? WHERE id = ?", 'di', [$qty, $realId]);
                    } else {
                        modifyData("UPDATE raw_materials SET current_stock = current_stock + ? WHERE id = ?", 'di', [$qty, $realId]);
                    }
                }
                setFlash('Import purchase added successfully!', 'success');
                header('Location: import_purchase_print.php?id=' . $purchase_id);
                exit;
            } else {
                $message = 'Error adding import purchase!';
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

<div class="row mb-3">
    <div class="col-12">
        <a href="import_purchases.php" class="btn btn-light"><i class="fas fa-arrow-left me-1"></i>Back to List</a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header" style="background:#1a2332;color:#fff;">
                <i class="fas fa-ship me-2"></i><?php echo $editPurchase ? 'Edit Import Purchase' : 'New Import Purchase'; ?>
            </div>
            <div class="card-body">
                <form method="POST" action="" onsubmit="return validateForm()">
                    <?php if ($editPurchase): ?>
                        <input type="hidden" name="id" value="<?php echo $editPurchase['id']; ?>">
                    <?php endif; ?>

                    <input type="hidden" name="total_cny" id="total_cny" value="<?php echo $editPurchase ? $editPurchase['total_cny'] : '0.00'; ?>">
                    <input type="hidden" name="total_pkr" id="total_pkr" value="<?php echo $editPurchase ? $editPurchase['total_pkr'] : '0.00'; ?>">
                    <input type="hidden" name="grand_total_cny" id="grand_total_cny" value="<?php echo $editPurchase ? $editPurchase['grand_total_cny'] : '0.00'; ?>">
                    <input type="hidden" name="grand_total_pkr" id="grand_total_pkr" value="<?php echo $editPurchase ? $editPurchase['grand_total_pkr'] : '0.00'; ?>">

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="supplier_id" class="form-label">Chinese Supplier *</label>
                            <select class="form-select" id="supplier_id" name="supplier_id" required onchange="onSupplierChange()">
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>"
                                        data-balance="<?php echo $supplier['current_balance']; ?>"
                                        <?php echo ($editPurchase && $editPurchase['supplier_id'] == $supplier['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                        <?php if ($supplier['company_name']): ?>
                                            (<?php echo htmlspecialchars($supplier['company_name']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="purchase_date" class="form-label">Purchase Date *</label>
                            <input type="date" class="form-control" id="purchase_date" name="purchase_date"
                                   value="<?php echo $editPurchase ? $editPurchase['purchase_date'] : date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="invoice_no" class="form-label">Invoice No</label>
                            <input type="text" class="form-control" id="invoice_no" name="invoice_no"
                                   value="<?php echo $editPurchase ? htmlspecialchars($editPurchase['invoice_no']) : ''; ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="exchange_rate" class="form-label">Exchange Rate (1 CNY = ? PKR) *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-exchange-alt"></i></span>
                                <input type="number" step="0.01" class="form-control" id="exchange_rate" name="exchange_rate"
                                       value="<?php echo $editPurchase ? $editPurchase['exchange_rate'] : $exchangeRate; ?>"
                                       required onchange="recalcAll()" onkeyup="recalcAll()">
                                <span class="input-group-text">PKR</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Previous Balance (CNY)</label>
                            <div class="input-group">
                                <span class="input-group-text">CNY</span>
                                <input type="text" class="form-control" id="previous_balance_display" readonly
                                       value="<?php echo $editPurchase ? number_format($editPurchase['previous_amount_cny'], 2) : '0.00'; ?>">
                                <input type="hidden" name="previous_amount_cny" id="previous_amount_cny"
                                       value="<?php echo $editPurchase ? $editPurchase['previous_amount_cny'] : '0.00'; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-cubes me-2"></i>Materials</span>
                            <button type="button" class="btn btn-sm btn-success" onclick="addMaterialRow()">
                                <i class="fas fa-plus me-1"></i>Add Material
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0" id="materialTable">
                                    <thead>
                                        <tr>
                                            <th style="width:25%;">Material</th>
                                            <th style="width:10%;">Qty</th>
                                            <th style="width:15%;">Unit Price (CNY)</th>
                                            <th style="width:15%;">Unit Price (PKR)</th>
                                            <th style="width:15%;">Total (CNY)</th>
                                            <th style="width:15%;">Total (PKR)</th>
                                            <th style="width:5%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="materialBody">
                                        <?php if ($editItems): ?>
                                            <?php foreach ($editItems as $item): ?>
                                                <tr>
                                                    <td>
                                                        <select class="form-select form-select-sm material-select" name="materials[][material_id]" required onchange="onMaterialSelect(this)">
                                                            <option value="">Select</option>
                                                            <?php foreach ($materials as $m): ?>
                                                                <option value="<?php echo $m['id']; ?>"
                                                                    data-price-pkr="<?php echo $m['price_pkr']; ?>"
                                                                    data-stock="<?php echo $m['current_stock']; ?>"
                                                                    data-type="<?php echo $m['item_type']; ?>"
                                                                    <?php echo ($item['material_id'] == $item['id'] && $item['item_type'] == $m['item_type']) ? 'selected' : ((!empty($item['material_id']) && $item['material_id'] == $m['id']) ? 'selected' : ''); ?>>
                                                                    <?php echo $m['display_label']; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                    <td><input type="number" step="0.01" class="form-control form-control-sm qty" name="materials[][quantity]" value="<?php echo $item['quantity']; ?>" required oninput="calcRow(this)"></td>
                                                    <td><input type="number" step="0.01" class="form-control form-control-sm unit-price-cny" name="materials[][unit_price_cny]" value="<?php echo $item['unit_price_cny']; ?>" required oninput="calcRow(this)"></td>
                                                    <td><input type="text" class="form-control form-control-sm unit-price-pkr" value="<?php echo number_format($item['unit_price_pkr'], 2); ?>" readonly></td>
                                                    <td><input type="text" class="form-control form-control-sm row-total-cny" value="<?php echo number_format($item['total_cny'], 2); ?>" readonly></td>
                                                    <td><input type="text" class="form-control form-control-sm row-total-pkr" value="<?php echo number_format($item['total_pkr'], 2); ?>" readonly></td>
                                                    <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td>
                                                    <select class="form-select form-select-sm material-select" name="materials[][material_id]" required onchange="onMaterialSelect(this)">
                                                        <option value="">Select</option>
                                                        <?php foreach ($materials as $m): ?>
                                                            <option value="<?php echo $m['id']; ?>"
                                                                data-price-pkr="<?php echo $m['price_pkr']; ?>"
                                                                data-stock="<?php echo $m['current_stock']; ?>"
                                                                data-type="<?php echo $m['item_type']; ?>">
                                                                <?php echo $m['display_label']; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td><input type="number" step="0.01" class="form-control form-control-sm qty" name="materials[][quantity]" value="1" required oninput="calcRow(this)"></td>
                                                <td><input type="number" step="0.01" class="form-control form-control-sm unit-price-cny" name="materials[][unit_price_cny]" value="0" required oninput="calcRow(this)"></td>
                                                <td><input type="text" class="form-control form-control-sm unit-price-pkr" value="0.00" readonly></td>
                                                <td><input type="text" class="form-control form-control-sm row-total-cny" value="0.00" readonly></td>
                                                <td><input type="text" class="form-control form-control-sm row-total-pkr" value="0.00" readonly></td>
                                                <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card" style="border:2px solid #1a2332;">
                                <div class="card-header" style="background:#1a2332;color:#fff;">
                                    <i class="fas fa-calculator me-2"></i>Payment Summary
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm mb-0">
                                        <tr>
                                            <td style="width:50%;"><strong>Previous Amount (CNY)</strong></td>
                                            <td class="text-end"><strong id="prevAmountDisplay"><?php echo $editPurchase ? number_format($editPurchase['previous_amount_cny'], 2) : '0.00'; ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Bill Amount (CNY)</strong></td>
                                            <td class="text-end"><strong id="billAmountDisplay">0.00</strong></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Total Amount (CNY)</strong></td>
                                            <td class="text-end"><strong id="totalAmountDisplay" style="color:#0d6efd;">0.00</strong></td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>S. Tax Amount (CNY)</strong>
                                            </td>
                                            <td class="text-end">
                                                <input type="number" step="0.01" class="form-control form-control-sm text-end" id="taxInput" name="tax_amount_cny"
                                                       value="<?php echo $editPurchase ? $editPurchase['tax_amount_cny'] : '0.00'; ?>"
                                                       style="width:140px;display:inline-block;font-weight:bold;"
                                                       oninput="calcSummary()">
                                            </td>
                                        </tr>
                                        <tr style="border-top:2px solid #1a2332;">
                                            <td><strong style="font-size:16px;">Grand Amount (CNY)</strong></td>
                                            <td class="text-end"><strong style="font-size:16px;color:#198754;" id="grandAmountDisplay">0.00</strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 mb-3">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i><?php echo $editPurchase ? 'Update Purchase' : 'Save Purchase'; ?>
                        </button>
                        <a href="import_purchases.php" class="btn btn-secondary btn-lg ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
var materialsData = <?php echo json_encode(array_values($materials)); ?>;

function onSupplierChange() {
    var sel = document.getElementById('supplier_id');
    var opt = sel.options[sel.selectedIndex];
    var balance = parseFloat(opt.dataset.balance) || 0;
    document.getElementById('previous_amount_cny').value = balance.toFixed(2);
    document.getElementById('previous_balance_display').value = balance.toFixed(2);
    calcSummary();
}

function onMaterialSelect(el) {
    var row = el.closest('tr');
    var opt = el.options[el.selectedIndex];
    var pricePkr = parseFloat(opt.dataset.pricePkr) || 0;
    var rate = getRate();
    var priceCny = rate > 0 ? pricePkr / rate : 0;
    row.querySelector('.unit-price-cny').value = priceCny.toFixed(2);
    var stockEl = row.querySelector('.stock-display');
    if (stockEl) stockEl.textContent = parseFloat(opt.dataset.stock || 0).toFixed(2);
    calcRow(el);
}

function buildMaterialOptions() {
    var html = '<option value="">Select</option>';
    materialsData.forEach(function(m) {
        html += '<option value="'+m.id+'" data-price-pkr="'+m.price_pkr+'" data-stock="'+m.current_stock+'" data-type="'+m.item_type+'">'+m.display_label+'</option>';
    });
    return html;
}

function addMaterialRow() {
    var tbody = document.getElementById('materialBody');
    var row = tbody.insertRow();
    var html = '<td><select class="form-select form-select-sm material-select" name="materials[][material_id]" required onchange="onMaterialSelect(this)">' + buildMaterialOptions() + '</select></td>';
    html += '<td><input type="number" step="0.01" class="form-control form-control-sm qty" name="materials[][quantity]" value="1" required oninput="calcRow(this)"></td>';
    html += '<td><input type="number" step="0.01" class="form-control form-control-sm unit-price-cny" name="materials[][unit_price_cny]" value="0" required oninput="calcRow(this)"></td>';
    html += '<td><input type="text" class="form-control form-control-sm unit-price-pkr" value="0.00" readonly></td>';
    html += '<td><input type="text" class="form-control form-control-sm row-total-cny" value="0.00" readonly></td>';
    html += '<td><input type="text" class="form-control form-control-sm row-total-pkr" value="0.00" readonly></td>';
    html += '<td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>';
    row.innerHTML = html;
}

function removeRow(btn) {
    var tbody = document.getElementById('materialBody');
    if (tbody.rows.length > 1) {
        btn.closest('tr').remove();
        calcSummary();
    } else {
        alert('At least one material is required!');
    }
}

function getRate() {
    return parseFloat(document.getElementById('exchange_rate').value) || 0;
}

function calcRow(el) {
    var row = el.closest('tr');
    var qty = parseFloat(row.querySelector('.qty').value) || 0;
    var priceCny = parseFloat(row.querySelector('.unit-price-cny').value) || 0;
    var rate = getRate();
    var pricePkr = priceCny * rate;
    row.querySelector('.unit-price-pkr').value = pricePkr.toFixed(2);
    row.querySelector('.row-total-cny').value = (qty * priceCny).toFixed(2);
    row.querySelector('.row-total-pkr').value = (qty * pricePkr).toFixed(2);
    calcSummary();
}

function calcSummary() {
    var billCny = 0;
    document.querySelectorAll('#materialBody tr').forEach(function(row) {
        billCny += parseFloat(row.querySelector('.row-total-cny').value) || 0;
    });
    var prevCny = parseFloat(document.getElementById('previous_amount_cny').value) || 0;
    var taxCny = parseFloat(document.getElementById('taxInput').value) || 0;
    var totalCny = prevCny + billCny;
    var grandCny = totalCny + taxCny;
    var rate = getRate();

    document.getElementById('billAmountDisplay').value = billCny.toFixed(2);
    document.getElementById('billAmountDisplay').textContent = billCny.toFixed(2);
    document.getElementById('totalAmountDisplay').textContent = totalCny.toFixed(2);
    document.getElementById('grandAmountDisplay').textContent = grandCny.toFixed(2);

    document.getElementById('total_cny').value = billCny.toFixed(2);
    document.getElementById('total_pkr').value = (billCny * rate).toFixed(2);
    document.getElementById('grand_total_cny').value = grandCny.toFixed(2);
    document.getElementById('grand_total_pkr').value = (grandCny * rate).toFixed(2);
}

function recalcAll() {
    var rate = getRate();
    document.querySelectorAll('#materialBody tr').forEach(function(row) {
        var priceCny = parseFloat(row.querySelector('.unit-price-cny').value) || 0;
        var qty = parseFloat(row.querySelector('.qty').value) || 0;
        row.querySelector('.unit-price-pkr').value = (priceCny * rate).toFixed(2);
        row.querySelector('.row-total-cny').value = (qty * priceCny).toFixed(2);
        row.querySelector('.row-total-pkr').value = (qty * priceCny * rate).toFixed(2);
    });
    calcSummary();
}

function validateForm() {
    if (!document.getElementById('supplier_id').value) {
        alert('Please select a Chinese supplier.'); return false;
    }
    if (!document.getElementById('purchase_date').value) {
        alert('Please select purchase date.'); return false;
    }
    if (getRate() <= 0) {
        alert('Please enter a valid exchange rate.'); return false;
    }
    var rows = document.querySelectorAll('#materialBody tr');
    if (rows.length === 0) {
        alert('Please add at least one material.'); return false;
    }
    var valid = true;
    rows.forEach(function(row) {
        if (!row.querySelector('.material-select').value) valid = false;
        if ((parseFloat(row.querySelector('.qty').value) || 0) <= 0) valid = false;
        if ((parseFloat(row.querySelector('.unit-price-cny').value) || 0) <= 0) valid = false;
    });
    if (!valid) {
        alert('Please ensure all materials have valid data (material, qty > 0, price > 0).');
        return false;
    }
    calcSummary();
    return true;
}

$(document).ready(function() {
    <?php if ($editPurchase): ?>
        setTimeout(calcSummary, 300);
    <?php else: ?>
        calcSummary();
    <?php endif; ?>
});
</script>

<?php include '../includes/footer.php'; ?>
