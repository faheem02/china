<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Add Local Purchase';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

$suppliers = getRows("SELECT s.id, s.supplier_name, s.company_name, s.opening_balance,
    COALESCE(SUM(lp.balance), 0) AS purchase_balance,
    COALESCE((SELECT SUM(sp.amount) FROM supplier_payments sp WHERE sp.supplier_id = s.id), 0) AS payments_made,
    (s.opening_balance + COALESCE(SUM(lp.balance), 0) - COALESCE((SELECT SUM(sp.amount) FROM supplier_payments sp WHERE sp.supplier_id = s.id), 0)) AS current_balance
    FROM local_suppliers s
    LEFT JOIN local_purchases lp ON lp.supplier_id = s.id
    WHERE s.status = 'active'
    GROUP BY s.id
    ORDER BY s.supplier_name");

$rawMaterials = getRows("SELECT id, material_code, material_name, unit, purchase_price_pkr AS price, current_stock FROM raw_materials WHERE status = 'active' ORDER BY material_name");
$finishedGoods = getRows("SELECT id, product_code, product_name, unit, cost_price AS price, current_stock FROM finished_goods WHERE status = 'active' ORDER BY product_name");

$materials = [];
foreach ($rawMaterials as $rm) {
    $materials[] = [
        'id' => $rm['id'],
        'code' => $rm['material_code'],
        'name' => $rm['material_name'],
        'unit' => $rm['unit'],
        'price' => $rm['price'],
        'stock' => $rm['current_stock'],
        'item_type' => 'raw_material',
        'display_label' => '[Raw] ' . $rm['material_code'] . ' - ' . $rm['material_name'],
        'dropdown_text' => '[Raw] ' . $rm['material_code'] . ' - ' . $rm['material_name'] . ' (Stock: ' . $rm['current_stock'] . ' ' . $rm['unit'] . ')',
    ];
}
foreach ($finishedGoods as $fg) {
    $materials[] = [
        'id' => 'fg_' . $fg['id'],
        'code' => $fg['product_code'],
        'name' => $fg['product_name'],
        'unit' => $fg['unit'],
        'price' => $fg['price'],
        'stock' => $fg['current_stock'],
        'item_type' => 'finished_good',
        'display_label' => '[FG] ' . $fg['product_code'] . ' - ' . $fg['product_name'],
        'dropdown_text' => '[FG] ' . $fg['product_code'] . ' - ' . $fg['product_name'] . ' (Stock: ' . $fg['current_stock'] . ' ' . $fg['unit'] . ')',
    ];
}

$editPurchase = null;
$editItems = [];
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $editId = (int)$_GET['id'];
    $editPurchase = getRow("SELECT * FROM local_purchases WHERE id = ?", 'i', [$editId]);
    if ($editPurchase) {
        $editItems = getRows("SELECT lpi.*, 
            CASE WHEN lpi.item_type = 'raw_material' THEN rm.material_name WHEN lpi.item_type = 'finished_good' THEN fg.product_name ELSE 'Unknown' END AS item_name,
            CASE WHEN lpi.item_type = 'raw_material' THEN rm.material_code WHEN lpi.item_type = 'finished_good' THEN fg.product_code ELSE '' END AS item_code,
            CASE WHEN lpi.item_type = 'raw_material' THEN rm.unit WHEN lpi.item_type = 'finished_good' THEN fg.unit ELSE '' END AS item_unit,
            CASE WHEN lpi.item_type = 'raw_material' THEN rm.purchase_price_pkr WHEN lpi.item_type = 'finished_good' THEN fg.cost_price ELSE 0 END AS item_price,
            CASE WHEN lpi.item_type = 'raw_material' THEN rm.current_stock WHEN lpi.item_type = 'finished_good' THEN fg.current_stock ELSE 0 END AS item_stock,
            CASE WHEN lpi.item_type = 'raw_material' THEN CONCAT('[Raw] ', rm.material_code, ' - ', rm.material_name) WHEN lpi.item_type = 'finished_good' THEN CONCAT('[FG] ', fg.product_code, ' - ', fg.product_name) ELSE 'Unknown' END AS item_label
            FROM local_purchase_items lpi 
            LEFT JOIN raw_materials rm ON lpi.material_id = rm.id AND lpi.item_type = 'raw_material'
            LEFT JOIN finished_goods fg ON lpi.material_id = fg.id AND lpi.item_type = 'finished_good'
            WHERE lpi.purchase_id = ?", 'i', [$editId]);
        $pageTitle = 'Edit Local Purchase';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $supplier_id = (int)$_POST['supplier_id'];
    $purchase_date = $_POST['purchase_date'];
    $invoice_no = trim($_POST['invoice_no']);
    $total_amount = (float)$_POST['total_amount'];
    $paid_amount = (float)$_POST['paid_amount'];
    $payment_status = $_POST['payment_status'];
    $payment_method = $_POST['payment_method'];
    $status = $_POST['status'];
    $materialsPost = isset($_POST['materials']) ? $_POST['materials'] : [];

    if (empty($supplier_id)) {
        $message = 'Please select a supplier!';
        $messageType = 'danger';
    } elseif (empty($purchase_date)) {
        $message = 'Please select purchase date!';
        $messageType = 'danger';
    } elseif (empty($materialsPost)) {
        $message = 'Please add at least one material!';
        $messageType = 'danger';
    } else {
        $currentDateTime = getCurrentDateTime();
        $balance = $total_amount - $paid_amount;

        if ($id > 0) {
            $oldItems = getRows("SELECT material_id, quantity, item_type FROM local_purchase_items WHERE purchase_id = ?", 'i', [$id]);
            foreach ($oldItems as $oldItem) {
                if ($oldItem['item_type'] === 'finished_good') {
                    modifyData("UPDATE finished_goods SET current_stock = current_stock - ? WHERE id = ?", 'di', [$oldItem['quantity'], $oldItem['material_id']]);
                } else {
                    modifyData("UPDATE raw_materials SET current_stock = current_stock - ? WHERE id = ?", 'di', [$oldItem['quantity'], $oldItem['material_id']]);
                }
            }

            modifyData("UPDATE local_purchases SET supplier_id=?, purchase_date=?, invoice_no=?, total_amount=?, paid_amount=?, balance=?, payment_status=?, payment_method=?, status=?, date_time=? WHERE id=?",
                'isddddsssdi', [$supplier_id, $purchase_date, $invoice_no, $total_amount, $paid_amount, $balance, $payment_status, $payment_method, $status, $currentDateTime, $id]);

            modifyData("DELETE FROM local_purchase_items WHERE purchase_id = ?", 'i', [$id]);

            foreach ($materialsPost as $material) {
                $rawMaterialId = $material['material_id'];
                $isFG = strpos($rawMaterialId, 'fg_') === 0;
                $material_id = $isFG ? (int)substr($rawMaterialId, 3) : (int)$rawMaterialId;
                $item_type = $isFG ? 'finished_good' : 'raw_material';
                $quantity = (float)$material['quantity'];
                $unit_price = (float)$material['unit_price'];
                $total = $quantity * $unit_price;
                insertData("INSERT INTO local_purchase_items (purchase_id, material_id, item_type, quantity, unit_price, total, date_time) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    'iisddds', [$id, $material_id, $item_type, $quantity, $unit_price, $total, $currentDateTime]);
                if ($isFG) {
                    modifyData("UPDATE finished_goods SET current_stock = current_stock + ? WHERE id = ?", 'di', [$quantity, $material_id]);
                } else {
                    modifyData("UPDATE raw_materials SET current_stock = current_stock + ? WHERE id = ?", 'di', [$quantity, $material_id]);
                }
            }

            setFlash('Purchase order updated successfully!', 'success');
            header('Location: local_purchases.php');
            exit;
        } else {
            $purchase_no = generateCode('LP');
            $purchase_id = insertData("INSERT INTO local_purchases (purchase_no, supplier_id, purchase_date, invoice_no, total_amount, paid_amount, balance, payment_status, payment_method, status, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                'sisddddsssi', [$purchase_no, $supplier_id, $purchase_date, $invoice_no, $total_amount, $paid_amount, $balance, $payment_status, $payment_method, $status, $currentDateTime]);

            if ($purchase_id) {
                foreach ($materialsPost as $material) {
                    $rawMaterialId = $material['material_id'];
                    $isFG = strpos($rawMaterialId, 'fg_') === 0;
                    $material_id = $isFG ? (int)substr($rawMaterialId, 3) : (int)$rawMaterialId;
                    $item_type = $isFG ? 'finished_good' : 'raw_material';
                    $quantity = (float)$material['quantity'];
                    $unit_price = (float)$material['unit_price'];
                    $total = $quantity * $unit_price;
                    insertData("INSERT INTO local_purchase_items (purchase_id, material_id, item_type, quantity, unit_price, total, date_time) VALUES (?, ?, ?, ?, ?, ?, ?)",
                        'iisddds', [$purchase_id, $material_id, $item_type, $quantity, $unit_price, $total, $currentDateTime]);
                    if ($isFG) {
                        modifyData("UPDATE finished_goods SET current_stock = current_stock + ? WHERE id = ?", 'di', [$quantity, $material_id]);
                    } else {
                        modifyData("UPDATE raw_materials SET current_stock = current_stock + ? WHERE id = ?", 'di', [$quantity, $material_id]);
                    }
                }

                header('Location: purchase_print.php?id=' . $purchase_id);
                exit;
            } else {
                $message = 'Error adding purchase order!';
                $messageType = 'danger';
            }
        }
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
#supplierBalanceBox {
    background: #f8f9fa;
    border-left: 4px solid #1a2332;
    padding: 10px 15px;
    border-radius: 0 6px 6px 0;
    margin-bottom: 15px;
    display: none;
}
#supplierBalanceBox .bal-label { font-size: 12px; color: #666; }
#supplierBalanceBox .bal-amount { font-size: 20px; font-weight: 700; }
#supplierBalanceBox .bal-opening { font-size: 12px; color: #888; }
</style>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center" style="background:#1a2332;color:#fff;">
                <span><i class="fas fa-shopping-cart me-2"></i><?php echo $pageTitle; ?></span>
                <a href="local_purchases.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to List</a>
            </div>
            <div class="card-body">
                <form method="POST" onsubmit="return validateForm()">
                    <?php if ($editPurchase): ?>
                        <input type="hidden" name="id" value="<?php echo $editPurchase['id']; ?>">
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Supplier *</label>
                            <select class="form-select" id="supplierSelect" name="supplier_id" required>
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>"
                                        data-balance="<?php echo $supplier['current_balance']; ?>"
                                        data-opening="<?php echo $supplier['opening_balance']; ?>"
                                        data-payments="<?php echo $supplier['payments_made']; ?>"
                                        <?php echo ($editPurchase && $editPurchase['supplier_id'] == $supplier['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                        <?php if ($supplier['company_name']): ?> (<?php echo htmlspecialchars($supplier['company_name']); ?>)<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Purchase Date *</label>
                            <input type="date" class="form-control" name="purchase_date" value="<?php echo $editPurchase ? $editPurchase['purchase_date'] : date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Invoice No</label>
                            <input type="text" class="form-control" name="invoice_no" value="<?php echo $editPurchase ? htmlspecialchars($editPurchase['invoice_no']) : ''; ?>">
                        </div>
                    </div>

                    <div id="supplierBalanceBox">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="bal-label">Opening Balance</div>
                                <div class="bal-amount" id="balOpening">0.00</div>
                            </div>
                            <div class="col-md-4">
                                <div class="bal-label">Payments Made</div>
                                <div class="bal-amount" id="balPayments">0.00</div>
                            </div>
                            <div class="col-md-4">
                                <div class="bal-label">Current Balance (Due)</div>
                                <div class="bal-amount text-danger" id="balCurrent">0.00</div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-cubes me-2"></i>Materials</span>
                            <button type="button" class="btn btn-sm btn-success" onclick="addRow()"><i class="fas fa-plus me-1"></i>Add Material</button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0" id="materialTable">
                                    <thead style="background:#1a2332;color:#fff;">
                                        <tr>
                                            <th style="width:35%">Material</th>
                                            <th style="width:15%">Quantity</th>
                                            <th style="width:20%">Unit Price (PKR)</th>
                                            <th style="width:20%">Total (PKR)</th>
                                            <th style="width:10%" class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="materialBody">
                                        <?php if ($editItems): ?>
                                            <?php foreach ($editItems as $item): ?>
                                                <tr>
                                                    <td>
                                                        <select class="form-select material-select" name="materials[<?php echo $item['material_id']; ?>][material_id]" required data-item-type="<?php echo $item['item_type']; ?>">
                                                            <option value="">Select Material</option>
                                                            <?php foreach ($materials as $m): ?>
                                                                <option value="<?php echo $m['id']; ?>" <?php echo ($item['material_id'] == preg_replace('/^fg_/', '', $m['id']) && $item['item_type'] == $m['item_type']) ? 'selected' : ''; ?>
                                                                    data-price="<?php echo $m['price']; ?>" data-stock="<?php echo $m['stock']; ?>">
                                                                    <?php echo $m['dropdown_text']; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                    <td><input type="number" step="0.01" class="form-control qty" name="materials[][quantity]" value="<?php echo $item['quantity']; ?>" required oninput="calcRow(this)"></td>
                                                    <td><input type="number" step="0.01" class="form-control price" name="materials[][unit_price]" value="<?php echo $item['unit_price']; ?>" required oninput="calcRow(this)"></td>
                                                    <td><input type="text" class="form-control row-total" value="<?php echo number_format($item['total'], 2); ?>" readonly></td>
                                                    <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td>
                                                    <select class="form-select material-select" name="materials[][material_id]" required>
                                                        <option value="">Select Material</option>
                                                        <?php foreach ($materials as $m): ?>
                                                            <option value="<?php echo $m['id']; ?>" data-price="<?php echo $m['price']; ?>" data-stock="<?php echo $m['stock']; ?>" data-type="<?php echo $m['item_type']; ?>">
                                                                <?php echo $m['dropdown_text']; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td><input type="number" step="0.01" class="form-control qty" name="materials[][quantity]" value="1" required oninput="calcRow(this)"></td>
                                                <td><input type="number" step="0.01" class="form-control price" name="materials[][unit_price]" value="0" required oninput="calcRow(this)"></td>
                                                <td><input type="text" class="form-control row-total" value="0.00" readonly></td>
                                                <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr style="background:#e9ecef;">
                                            <th colspan="3" class="text-end">Grand Total</th>
                                            <th><input type="text" class="form-control fw-bold" id="grandTotal" name="total_amount" value="<?php echo $editPurchase ? number_format($editPurchase['total_amount'], 2) : '0.00'; ?>" readonly></th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select" name="payment_method">
                                <option value="credit" <?php echo ($editPurchase && $editPurchase['payment_method'] == 'credit') ? 'selected' : 'selected'; ?>>Credit</option>
                                <option value="cash" <?php echo ($editPurchase && $editPurchase['payment_method'] == 'cash') ? 'selected' : ''; ?>>Cash</option>
                                <option value="bank" <?php echo ($editPurchase && $editPurchase['payment_method'] == 'bank') ? 'selected' : ''; ?>>Bank Transfer</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Paid Amount (PKR)</label>
                            <input type="number" step="0.01" class="form-control" id="paidAmount" name="paid_amount" value="<?php echo $editPurchase ? $editPurchase['paid_amount'] : '0.00'; ?>" oninput="calcBalance()">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Payment Status</label>
                            <select class="form-select" id="paymentStatus" name="payment_status">
                                <option value="unpaid" <?php echo ($editPurchase && $editPurchase['payment_status'] == 'unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                                <option value="partial" <?php echo ($editPurchase && $editPurchase['payment_status'] == 'partial') ? 'selected' : ''; ?>>Partial</option>
                                <option value="paid" <?php echo ($editPurchase && $editPurchase['payment_status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Order Status</label>
                            <select class="form-select" name="status">
                                <option value="pending" <?php echo ($editPurchase && $editPurchase['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="received" <?php echo ($editPurchase && $editPurchase['status'] == 'received') ? 'selected' : ''; ?>>Received</option>
                                <option value="cancelled" <?php echo ($editPurchase && $editPurchase['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="text-end">
                        <a href="local_purchases.php" class="btn btn-secondary me-2"><i class="fas fa-times me-1"></i>Cancel</a>
                        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i><?php echo $editPurchase ? 'Update' : 'Save & Print'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
var materialsJSON = <?php echo json_encode($materials); ?>;

function addRow() {
    var tbody = document.getElementById('materialBody');
    var row = tbody.insertRow();
    var html = '<td><select class="form-select material-select" name="materials[][material_id]" required><option value="">Select Material</option>';
    materialsJSON.forEach(function(m) {
        html += '<option value="' + m.id + '" data-price="' + m.price + '" data-stock="' + m.stock + '" data-type="' + m.item_type + '">' + m.dropdown_text + '</option>';
    });
    html += '</select></td>';
    html += '<td><input type="number" step="0.01" class="form-control qty" name="materials[][quantity]" value="1" required oninput="calcRow(this)"></td>';
    html += '<td><input type="number" step="0.01" class="form-control price" name="materials[][unit_price]" value="0" required oninput="calcRow(this)"></td>';
    html += '<td><input type="text" class="form-control row-total" value="0.00" readonly></td>';
    html += '<td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>';
    row.innerHTML = html;
}

function removeRow(btn) {
    var tbody = document.getElementById('materialBody');
    if (tbody.rows.length > 1) {
        btn.closest('tr').remove();
        calcGrand();
    } else {
        alert('At least one material is required!');
    }
}

function calcRow(el) {
    var row = el.closest('tr');
    var q = parseFloat(row.querySelector('.qty').value) || 0;
    var p = parseFloat(row.querySelector('.price').value) || 0;
    row.querySelector('.row-total').value = (q * p).toFixed(2);
    calcGrand();
}

function calcGrand() {
    var total = 0;
    document.querySelectorAll('.row-total').forEach(function(el) {
        total += parseFloat(el.value) || 0;
    });
    document.getElementById('grandTotal').value = total.toFixed(2);
    calcBalance();
}

function calcBalance() {
    var total = parseFloat(document.getElementById('grandTotal').value) || 0;
    var paid = parseFloat(document.getElementById('paidAmount').value) || 0;
    var bal = total - paid;
    var ps = document.getElementById('paymentStatus');
    if (bal <= 0 && total > 0) ps.value = 'paid';
    else if (paid > 0) ps.value = 'partial';
    else ps.value = 'unpaid';
}

function validateForm() {
    var total = parseFloat(document.getElementById('grandTotal').value) || 0;
    if (total <= 0) { alert('Total amount must be greater than 0.'); return false; }
    return true;
}

function fmtNum(v) { return parseFloat(v).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

function showSupplierBalance() {
    var sel = document.getElementById('supplierSelect');
    var opt = sel.options[sel.selectedIndex];
    var box = document.getElementById('supplierBalanceBox');
    if (sel.value === '') { box.style.display = 'none'; return; }
    var opening = parseFloat(opt.getAttribute('data-opening')) || 0;
    var payments = parseFloat(opt.getAttribute('data-payments')) || 0;
    var current  = parseFloat(opt.getAttribute('data-balance')) || 0;
    document.getElementById('balOpening').textContent  = fmtNum(opening);
    document.getElementById('balPayments').textContent = fmtNum(payments);
    document.getElementById('balCurrent').textContent  = fmtNum(current);
    box.style.display = 'block';
}

$(document).ready(function() {
    $(document).on('change', '.material-select', function() {
        var price = $(this).find('option:selected').data('price');
        if (price) {
            $(this).closest('tr').find('.price').val(price);
            calcRow($(this).closest('tr').find('.qty')[0]);
        }
    });
    $('#supplierSelect').on('change', showSupplierBalance);
    <?php if ($editPurchase): ?>
        showSupplierBalance();
    <?php endif; ?>
    calcGrand();
});
</script>

<?php include '../includes/footer.php'; ?>
