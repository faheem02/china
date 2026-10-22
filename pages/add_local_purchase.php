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
    ];
}

$bankAccounts = getActiveBankAccounts();

$editPurchase = null;
$editItems = [];
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $editId = (int)$_GET['id'];
    $editPurchase = getRow("SELECT * FROM local_purchases WHERE id = ?", 'i', [$editId]);
    if ($editPurchase) {
        $editItems = getRows("SELECT lpi.*, 
            CASE WHEN lpi.item_type = 'raw_material' THEN rm.material_name WHEN lpi.item_type = 'finished_good' THEN fg.product_name ELSE 'Unknown' END AS item_name,
            CASE WHEN lpi.item_type = 'raw_material' THEN rm.material_code WHEN lpi.item_type = 'finished_good' THEN fg.product_code ELSE '' END AS item_code,
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
    $payment_method = $_POST['payment_method'] ?? 'credit';
    $previous_balance = (float)$_POST['previous_balance'];
    $materialsPost = isset($_POST['materials']) ? $_POST['materials'] : [];

    if (!in_array($payment_method, ['cash', 'bank', 'credit'], true)) {
        $payment_method = 'credit';
    }
    $bank_id = ($payment_method === 'bank') ? (int)($_POST['bank_id'] ?? 0) : null;
    $bank_name = null;
    if ($bank_id > 0) {
        $bRow = getRow("SELECT bank_name, account_number FROM bank_accounts WHERE id = ?", 'i', [$bank_id]);
        if ($bRow) {
            $bank_name = $bRow['bank_name'] . ' (' . $bRow['account_number'] . ')';
        }
    }

    if (empty($supplier_id)) {
        $message = 'Please select a supplier!';
        $messageType = 'danger';
    } elseif (empty($purchase_date)) {
        $message = 'Please select purchase date!';
        $messageType = 'danger';
    } elseif ($payment_method === 'bank' && $paid_amount > 0 && empty($bank_id)) {
        $message = 'Please select a bank account!';
        $messageType = 'danger';
    } elseif (empty($materialsPost)) {
        $message = 'Please add at least one material!';
        $messageType = 'danger';
    } else {
        $currentDateTime = getCurrentDateTime();
        $balance = $total_amount - $paid_amount;

        if ($id > 0) {
            $oldPurchase = getRow("SELECT supplier_id, balance, paid_amount, payment_method, bank_id FROM local_purchases WHERE id = ?", 'i', [$id]);
            if ($oldPurchase) {
                modifyData("UPDATE local_suppliers SET current_balance = current_balance - ? WHERE id = ?", 'di', [(float)$oldPurchase['balance'], (int)$oldPurchase['supplier_id']]);
                if ($oldPurchase['payment_method'] === 'bank' && !empty($oldPurchase['bank_id']) && $oldPurchase['paid_amount'] > 0) {
                    updateBankBalance($oldPurchase['bank_id'], $oldPurchase['paid_amount'], 'in');
                }
            }

            $oldItems = getRows("SELECT material_id, quantity, item_type FROM local_purchase_items WHERE purchase_id = ?", 'i', [$id]);
            foreach ($oldItems as $oldItem) {
                if ($oldItem['item_type'] === 'finished_good') {
                    modifyData("UPDATE finished_goods SET current_stock = current_stock - ? WHERE id = ?", 'di', [$oldItem['quantity'], $oldItem['material_id']]);
                } else {
                    modifyData("UPDATE raw_materials SET current_stock = current_stock - ? WHERE id = ?", 'di', [$oldItem['quantity'], $oldItem['material_id']]);
                }
            }

            modifyData("UPDATE local_purchases SET supplier_id=?, purchase_date=?, invoice_no=?, total_amount=?, paid_amount=?, balance=?, previous_balance=?, payment_method=?, bank_id=?, bank_name=?, date_time=? WHERE id=?",
                'issddddssisi', [$supplier_id, $purchase_date, $invoice_no, $total_amount, $paid_amount, $balance, $previous_balance, $payment_method, $bank_id, $bank_name, $currentDateTime, $id]);

            if ($payment_method === 'bank' && $bank_id > 0 && $paid_amount > 0) {
                updateBankBalance($bank_id, $paid_amount, 'out');
            }

            modifyData("UPDATE local_suppliers SET current_balance = current_balance + ? WHERE id = ?", 'di', [$balance, $supplier_id]);

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
            header('Location: purchase_print.php?id=' . $id);
            exit;
        } else {
            $purchase_no = generateCode('LP');
            $purchase_id = insertData("INSERT INTO local_purchases (purchase_no, supplier_id, purchase_date, invoice_no, total_amount, paid_amount, balance, previous_balance, payment_method, bank_id, bank_name, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                'sissddddssis', [$purchase_no, $supplier_id, $purchase_date, $invoice_no, $total_amount, $paid_amount, $balance, $previous_balance, $payment_method, $bank_id, $bank_name, $currentDateTime]);

            if ($purchase_id) {
                if ($payment_method === 'bank' && $bank_id > 0 && $paid_amount > 0) {
                    updateBankBalance($bank_id, $paid_amount, 'out');
                }

                modifyData("UPDATE local_suppliers SET current_balance = current_balance + ? WHERE id = ?", 'di', [$balance, $supplier_id]);

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

                setFlash('Local purchase added successfully!', 'success');
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

<style>
/* Material Search Autocomplete */
.material-search-wrap {
    position: relative;
    width: 100%;
}
.material-search-wrap .material-search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 11px;
    pointer-events: none;
    z-index: 5;
}
.material-search-wrap .material-search-input {
    padding-left: 28px;
    font-size: 13px;
    border-radius: 6px;
    background-color: #fff;
}
.material-search-wrap .material-search-input:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
}
.material-search-dropdown {
    position: absolute;
    top: calc(100% + 2px);
    left: 0;
    right: 0;
    min-width: 320px;
    background: #ffffff;
    border: 1.5px solid #94a3b8;
    border-radius: 8px;
    box-shadow: 0 14px 30px rgba(0,0,0,0.18), 0 4px 10px rgba(0,0,0,0.08);
    max-height: 250px;
    overflow-y: auto;
    z-index: 9999;
    display: none;
}
.material-search-dropdown.show {
    display: block;
}
.material-dropdown-item {
    padding: 8px 12px;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: background-color 0.15s;
    font-size: 13px;
}
.material-dropdown-item:last-child {
    border-bottom: none;
}
.material-dropdown-item:hover,
.material-dropdown-item.active {
    background-color: #eff6ff;
}
#materialTable tbody td {
    position: relative;
    vertical-align: middle;
}
#materialTable tbody td.cell-dropdown-open {
    z-index: 1050;
}
.card {
    overflow: visible !important;
}
.table-responsive {
    overflow: visible !important;
}
</style>

<div class="row mb-3">
    <div class="col-12">
        <a href="local_purchases.php" class="btn btn-light"><i class="fas fa-arrow-left me-1"></i>Back to List</a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header" style="background:#1a2332;color:#fff;">
                <i class="fas fa-shopping-cart me-2"></i><?php echo $editPurchase ? 'Edit Local Purchase' : 'New Local Purchase'; ?>
            </div>
            <div class="card-body">
                <form method="POST" action="" onsubmit="return validateForm()">
                    <?php if ($editPurchase): ?>
                        <input type="hidden" name="id" value="<?php echo $editPurchase['id']; ?>">
                    <?php endif; ?>

                    <input type="hidden" name="total_amount" id="total_amount" value="<?php echo $editPurchase ? $editPurchase['total_amount'] : '0.00'; ?>">
                    <input type="hidden" name="previous_balance" id="previous_balance">

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="supplier_id" class="form-label">Local Supplier *</label>
                            <select class="form-select" id="supplier_id" name="supplier_id" required onchange="onSupplierChange()">
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>"
                                        data-balance="<?php echo $supplier['current_balance']; ?>"
                                        data-opening="<?php echo $supplier['opening_balance']; ?>"
                                        data-payments="<?php echo $supplier['payments_made']; ?>"
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
                            <label class="form-label">Previous Balance (PKR)</label>
                            <div class="input-group">
                                <span class="input-group-text">PKR</span>
                                <input type="text" class="form-control" id="previous_balance_display" readonly value="0.00">
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
                                            <th style="width:30%;">Material</th>
                                            <th style="width:10%;">Qty</th>
                                            <th style="width:20%;">Unit Price (PKR)</th>
                                            <th style="width:20%;">Total (PKR)</th>
                                            <th style="width:10%;">Stock</th>
                                            <th style="width:10%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="materialBody">
                                        <?php $rowIdx = 0; ?>
                                        <?php if ($editItems): ?>
                                            <?php foreach ($editItems as $item): ?>
                                                <?php
                                                    $valId = ($item['item_type'] === 'finished_good' ? 'fg_' : '') . $item['material_id'];
                                                    $displayVal = $item['item_label'] ?? ($item['item_name'] ?? '');
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="material-search-wrap">
                                                            <i class="fas fa-search material-search-icon"></i>
                                                            <input type="text" class="form-control form-control-sm material-search-input" 
                                                                   placeholder="Type material name or code..." 
                                                                   value="<?php echo htmlspecialchars($displayVal); ?>" 
                                                                   autocomplete="off" 
                                                                   oninput="filterMaterial(this)" 
                                                                   onfocus="filterMaterial(this)" 
                                                                   onblur="onMaterialInputBlur(this)" 
                                                                   onkeydown="navigateMaterial(event, this)">
                                                            <input type="hidden" class="material-id-input" name="materials[<?php echo $rowIdx; ?>][material_id]" value="<?php echo htmlspecialchars($valId); ?>" required>
                                                            <div class="material-search-dropdown shadow-sm"></div>
                                                        </div>
                                                    </td>
                                                    <td><input type="number" step="0.01" class="form-control form-control-sm qty" name="materials[<?php echo $rowIdx; ?>][quantity]" value="<?php echo $item['quantity']; ?>" required oninput="calcRow(this)"></td>
                                                    <td><input type="number" step="0.01" class="form-control form-control-sm unit-price" name="materials[<?php echo $rowIdx; ?>][unit_price]" value="<?php echo $item['unit_price']; ?>" required oninput="calcRow(this)"></td>
                                                    <td><input type="text" class="form-control form-control-sm row-total" value="<?php echo number_format($item['total'], 2); ?>" readonly></td>
                                                    <td class="text-center stock-display"><?php echo number_format($item['item_stock'] ?? 0, 2); ?></td>
                                                    <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
                                                </tr>
                                            <?php $rowIdx++; ?>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td>
                                                    <div class="material-search-wrap">
                                                        <i class="fas fa-search material-search-icon"></i>
                                                        <input type="text" class="form-control form-control-sm material-search-input" 
                                                               placeholder="Type material name or code..." 
                                                               value="" 
                                                               autocomplete="off" 
                                                               oninput="filterMaterial(this)" 
                                                               onfocus="filterMaterial(this)" 
                                                               onblur="onMaterialInputBlur(this)" 
                                                               onkeydown="navigateMaterial(event, this)">
                                                        <input type="hidden" class="material-id-input" name="materials[0][material_id]" value="" required>
                                                        <div class="material-search-dropdown shadow-sm"></div>
                                                    </div>
                                                </td>
                                                <td><input type="number" step="0.01" class="form-control form-control-sm qty" name="materials[0][quantity]" value="1" required oninput="calcRow(this)"></td>
                                                <td><input type="number" step="0.01" class="form-control form-control-sm unit-price" name="materials[0][unit_price]" value="0" required oninput="calcRow(this)"></td>
                                                <td><input type="text" class="form-control form-control-sm row-total" value="0.00" readonly></td>
                                                <td class="text-center stock-display">-</td>
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
                                            <td style="width:50%;"><strong>Previous Balance (PKR)</strong></td>
                                            <td class="text-end"><strong id="prevBalanceDisplay">0.00</strong></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Bill Amount (PKR)</strong></td>
                                            <td class="text-end"><strong id="billAmountDisplay">0.00</strong></td>
                                        </tr>
                                        <tr style="border-top:2px solid #1a2332;">
                                            <td><strong style="font-size:16px;">Grand Amount (PKR)</strong></td>
                                            <td class="text-end"><strong style="font-size:16px;color:#198754;" id="grandAmountDisplay">0.00</strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-credit-card me-2"></i>Payment Details
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Paid Amount (PKR)</label>
                                            <input type="number" step="0.01" class="form-control" id="paidAmount" name="paid_amount" value="<?php echo $editPurchase ? $editPurchase['paid_amount'] : '0.00'; ?>" oninput="calcSummary()">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Payment Method</label>
                                            <select class="form-select" name="payment_method" id="lp_payment_method" onchange="toggleLpBank(this.value)">
                                                <option value="credit" <?php echo (!$editPurchase || ($editPurchase['payment_method'] ?? 'credit') === 'credit') ? 'selected' : ''; ?>>Credit</option>
                                                <option value="cash" <?php echo ($editPurchase && ($editPurchase['payment_method'] ?? '') === 'cash') ? 'selected' : ''; ?>>Cash</option>
                                                <option value="bank" <?php echo ($editPurchase && ($editPurchase['payment_method'] ?? '') === 'bank') ? 'selected' : ''; ?>>Bank</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3" id="lp_bank_group" style="display: <?php echo ($editPurchase && ($editPurchase['payment_method'] ?? '') === 'bank') ? 'block' : 'none'; ?>;">
                                            <label class="form-label text-primary fw-bold"><i class="fas fa-university me-1"></i>Bank Account *</label>
                                            <select class="form-select" name="bank_id" id="lp_bank_id">
                                                <option value="">-- Select Bank --</option>
                                                <?php foreach ($bankAccounts as $ba): ?>
                                                    <option value="<?php echo $ba['id']; ?>" <?php echo ($editPurchase && ($editPurchase['bank_id'] ?? null) == $ba['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($ba['bank_name']); ?> - <?php echo htmlspecialchars($ba['account_number']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 mb-3">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i><?php echo $editPurchase ? 'Update Purchase' : 'Save Purchase'; ?>
                        </button>
                        <a href="local_purchases.php" class="btn btn-secondary btn-lg ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleLpBank(method) {
    var bg = document.getElementById('lp_bank_group');
    if (bg) {
        bg.style.display = (method === 'bank') ? 'block' : 'none';
        var bSel = document.getElementById('lp_bank_id');
        if (bSel) bSel.required = (method === 'bank');
    }
}
var materialsData = <?php echo json_encode(array_values($materials)); ?>;
var materialRowIndex = <?php echo $rowIdx; ?>;

function onSupplierChange() {
    var sel = document.getElementById('supplier_id');
    var opt = sel.options[sel.selectedIndex];
    if (sel.value === '') {
        document.getElementById('previous_balance').value = '0.00';
        document.getElementById('previous_balance_display').value = '0.00';
        document.getElementById('prevBalanceDisplay').textContent = '0.00';
        calcSummary();
        return;
    }
    var balance = parseFloat(opt.dataset.balance) || 0;
    document.getElementById('previous_balance').value = balance.toFixed(2);
    document.getElementById('previous_balance_display').value = fmtNum(balance);
    document.getElementById('prevBalanceDisplay').textContent = fmtNum(balance);
    calcSummary();
}

function filterMaterial(input) {
    const q = input.value.trim().toLowerCase();
    const wrap = input.closest('.material-search-wrap');
    const dropdown = wrap.querySelector('.material-search-dropdown');
    const td = input.closest('td');

    // Close any other open dropdowns
    document.querySelectorAll('.material-search-dropdown.show').forEach(function(dd) {
        if (dd !== dropdown) {
            dd.classList.remove('show');
            if (dd.closest('td')) dd.closest('td').classList.remove('cell-dropdown-open');
        }
    });

    let matches = [];
    if (!q) {
        matches = materialsData.slice(0, 15);
    } else {
        matches = materialsData.filter(function(m) {
            const name = (m.name || '').toLowerCase();
            const code = (m.code || '').toLowerCase();
            const label = (m.display_label || '').toLowerCase();
            return name.includes(q) || code.includes(q) || label.includes(q);
        }).slice(0, 20);
    }

    if (matches.length === 0) {
        dropdown.innerHTML = '<div class="p-2 text-center text-muted small">No materials found</div>';
        dropdown.classList.add('show');
        td.classList.add('cell-dropdown-open');
        return;
    }

    let html = '';
    matches.forEach(function(m, idx) {
        const isRaw = (m.item_type === 'raw_material');
        const badgeClass = isRaw ? 'bg-primary' : 'bg-success';
        const badgeText = isRaw ? 'RAW' : 'FG';
        const name = escapeHtml(m.name || '');
        const code = escapeHtml(m.code || '');
        const unit = escapeHtml(m.unit || '');
        const stock = parseFloat(m.stock || 0);
        const price = parseFloat(m.price || 0);

        html += '<div class="material-dropdown-item ' + (idx === 0 ? 'active' : '') + '" data-id="' + m.id + '" onmousedown="selectMaterialItem(this)">' +
            '<div>' +
                '<div><span class="badge ' + badgeClass + ' me-1">' + badgeText + '</span><strong>' + name + '</strong></div>' +
                '<small class="text-muted ps-1">Code: ' + code + ' | Stock: ' + stock + ' ' + unit + '</small>' +
            '</div>' +
            '<div class="text-end ps-2">' +
                '<span class="fw-bold text-primary">Rs. ' + price.toFixed(2) + '</span>' +
            '</div>' +
        '</div>';
    });

    dropdown.innerHTML = html;
    dropdown.classList.add('show');
    td.classList.add('cell-dropdown-open');
}

function selectMaterialItem(itemEl) {
    const id = itemEl.getAttribute('data-id');
    const wrap = itemEl.closest('.material-search-wrap');
    const input = wrap.querySelector('.material-search-input');
    const hidden = wrap.querySelector('.material-id-input');
    const dropdown = wrap.querySelector('.material-search-dropdown');
    const row = itemEl.closest('tr');
    const td = itemEl.closest('td');

    const m = materialsData.find(function(item) { return item.id == id; });
    if (!m) return;

    hidden.value = m.id;
    input.value = m.display_label;
    dropdown.classList.remove('show');
    if (td) td.classList.remove('cell-dropdown-open');

    const price = parseFloat(m.price || 0);
    const stock = parseFloat(m.stock || 0);
    row.querySelector('.unit-price').value = price.toFixed(2);
    const stockEl = row.querySelector('.stock-display');
    if (stockEl) stockEl.textContent = stock.toFixed(2);

    calcRow(input);

    const qtyInput = row.querySelector('.qty');
    if (qtyInput) {
        qtyInput.focus();
        qtyInput.select();
    }
}

function onMaterialInputBlur(input) {
    setTimeout(function() {
        const wrap = input.closest('.material-search-wrap');
        const hidden = wrap.querySelector('.material-id-input');
        const dropdown = wrap.querySelector('.material-search-dropdown');
        const td = input.closest('td');

        dropdown.classList.remove('show');
        if (td) td.classList.remove('cell-dropdown-open');

        if (!input.value.trim()) {
            hidden.value = '';
            const row = input.closest('tr');
            if (row) {
                const priceInput = row.querySelector('.unit-price');
                if (priceInput) priceInput.value = '0.00';
                const stockEl = row.querySelector('.stock-display');
                if (stockEl) stockEl.textContent = '-';
                calcRow(input);
            }
        }
    }, 200);
}

function navigateMaterial(e, input) {
    const wrap = input.closest('.material-search-wrap');
    const dropdown = wrap.querySelector('.material-search-dropdown');
    const items = dropdown.querySelectorAll('.material-dropdown-item');
    if (!items.length || !dropdown.classList.contains('show')) {
        if (e.key === 'ArrowDown') {
            filterMaterial(input);
            e.preventDefault();
        }
        return;
    }

    let activeIndex = -1;
    items.forEach(function(item, idx) {
        if (item.classList.contains('active')) activeIndex = idx;
    });

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        const next = (activeIndex + 1) >= items.length ? 0 : activeIndex + 1;
        if (items[activeIndex]) items[activeIndex].classList.remove('active');
        items[next].classList.add('active');
        items[next].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        const prev = (activeIndex - 1) < 0 ? items.length - 1 : activeIndex - 1;
        if (items[activeIndex]) items[activeIndex].classList.remove('active');
        items[prev].classList.add('active');
        items[prev].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (activeIndex > -1 && items[activeIndex]) {
            selectMaterialItem(items[activeIndex]);
        } else if (items.length > 0) {
            selectMaterialItem(items[0]);
        }
    } else if (e.key === 'Escape') {
        dropdown.classList.remove('show');
        const td = input.closest('td');
        if (td) td.classList.remove('cell-dropdown-open');
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.material-search-wrap')) {
        document.querySelectorAll('.material-search-dropdown.show').forEach(function(dd) {
            dd.classList.remove('show');
            const td = dd.closest('td');
            if (td) td.classList.remove('cell-dropdown-open');
        });
    }
});

function addMaterialRow() {
    var tbody = document.getElementById('materialBody');
    var row = tbody.insertRow();
    var idx = materialRowIndex++;
    var html = '<td>' +
        '<div class="material-search-wrap">' +
            '<i class="fas fa-search material-search-icon"></i>' +
            '<input type="text" class="form-control form-control-sm material-search-input" placeholder="Type material name or code..." autocomplete="off" oninput="filterMaterial(this)" onfocus="filterMaterial(this)" onblur="onMaterialInputBlur(this)" onkeydown="navigateMaterial(event, this)">' +
            '<input type="hidden" class="material-id-input" name="materials['+idx+'][material_id]" value="" required>' +
            '<div class="material-search-dropdown shadow-sm"></div>' +
        '</div>' +
    '</td>';
    html += '<td><input type="number" step="0.01" class="form-control form-control-sm qty" name="materials['+idx+'][quantity]" value="1" required oninput="calcRow(this)"></td>';
    html += '<td><input type="number" step="0.01" class="form-control form-control-sm unit-price" name="materials['+idx+'][unit_price]" value="0" required oninput="calcRow(this)"></td>';
    html += '<td><input type="text" class="form-control form-control-sm row-total" value="0.00" readonly></td>';
    html += '<td class="text-center stock-display">-</td>';
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

function calcRow(el) {
    var row = el.closest('tr');
    var qty = parseFloat(row.querySelector('.qty').value) || 0;
    var price = parseFloat(row.querySelector('.unit-price').value) || 0;
    row.querySelector('.row-total').value = (qty * price).toFixed(2);
    calcSummary();
}

function calcSummary() {
    var billPkr = 0;
    document.querySelectorAll('#materialBody tr').forEach(function(row) {
        billPkr += parseFloat(row.querySelector('.row-total').value) || 0;
    });
    var prevBal = parseFloat(document.getElementById('previous_balance').value) || 0;
    var grandTotal = prevBal + billPkr;
    document.getElementById('billAmountDisplay').textContent = fmtNum(billPkr);
    document.getElementById('grandAmountDisplay').textContent = fmtNum(grandTotal);
    document.getElementById('total_amount').value = billPkr.toFixed(2);
}

function validateForm() {
    if (!document.getElementById('supplier_id').value) {
        alert('Please select a supplier.'); return false;
    }
    if (!document.getElementById('purchase_date').value) {
        alert('Please select purchase date.'); return false;
    }
    var rows = document.querySelectorAll('#materialBody tr');
    if (rows.length === 0) {
        alert('Please add at least one material.'); return false;
    }
    var valid = true;
    rows.forEach(function(row) {
        var hidden = row.querySelector('.material-id-input');
        if (!hidden || !hidden.value) valid = false;
        if ((parseFloat(row.querySelector('.qty').value) || 0) <= 0) valid = false;
        if ((parseFloat(row.querySelector('.unit-price').value) || 0) <= 0) valid = false;
    });
    if (!valid) {
        alert('Please ensure all materials are selected and have valid data (qty > 0, price > 0).');
        return false;
    }
    calcSummary();
    return true;
}

function fmtNum(v) { return parseFloat(v).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

$(document).ready(function() {
    <?php if ($editPurchase): ?>
        onSupplierChange();
        setTimeout(calcSummary, 300);
    <?php else: ?>
        calcSummary();
    <?php endif; ?>
});
</script>

<?php include '../includes/footer.php'; ?>
