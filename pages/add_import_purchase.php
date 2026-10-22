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

$suppliers = getRows("SELECT s.id, s.supplier_name, s.company_name, s.opening_balance,
    COALESCE(SUM(ip.balance_cny), 0) AS purchase_balance,
    COALESCE((SELECT SUM(sp.amount_cny) FROM chinese_supplier_payments sp WHERE sp.supplier_id = s.id AND sp.status = 'completed'), 0) AS payments_made,
    (s.opening_balance + COALESCE(SUM(ip.balance_cny), 0) - COALESCE((SELECT SUM(sp.amount_cny) FROM chinese_supplier_payments sp WHERE sp.supplier_id = s.id AND sp.status = 'completed'), 0)) AS current_balance
    FROM chinese_suppliers s
    LEFT JOIN import_purchases ip ON ip.supplier_id = s.id
    WHERE s.status = 'active'
    GROUP BY s.id
    ORDER BY s.supplier_name");
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

$bankAccounts = getActiveBankAccounts();

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
    $payment_method = $_POST['payment_method'] ?? 'credit';
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
    } elseif ($exchange_rate <= 0) {
        $message = 'Exchange rate must be greater than 0!';
        $messageType = 'danger';
    } elseif (empty($materialsPost)) {
        $message = 'Please add at least one material!';
        $messageType = 'danger';
    } else {
        $currentDateTime = getCurrentDateTime();
        $paid_amount_cny = (float)$_POST['paid_amount_cny'];
        $paid_amount_pkr = $paid_amount_cny * $exchange_rate;
        $total_pkr_val = $total_cny * $exchange_rate;
        $grand_total_pkr_val = $grand_total_cny * $exchange_rate;
        $balance_cny = $grand_total_cny - $paid_amount_cny;
        $balance_pkr = $grand_total_pkr_val - $paid_amount_pkr;

        if ($payment_method === 'bank' && $paid_amount_pkr > 0 && empty($bank_id)) {
            $message = 'Please select a bank account!';
            $messageType = 'danger';
        } else if ($id > 0) {
            $oldPurchase = getRow("SELECT supplier_id, balance_cny, paid_amount_pkr, payment_method, bank_id FROM import_purchases WHERE id = ?", 'i', [$id]);
            if ($oldPurchase) {
                modifyData("UPDATE chinese_suppliers SET current_balance = current_balance - ? WHERE id = ?", 'di', [(float)$oldPurchase['balance_cny'], (int)$oldPurchase['supplier_id']]);
                if ($oldPurchase['payment_method'] === 'bank' && !empty($oldPurchase['bank_id']) && $oldPurchase['paid_amount_pkr'] > 0) {
                    updateBankBalance($oldPurchase['bank_id'], $oldPurchase['paid_amount_pkr'], 'in');
                }
            }

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

            $result = modifyData("UPDATE import_purchases SET supplier_id=?, purchase_date=?, invoice_no=?, exchange_rate=?, total_cny=?, total_pkr=?, previous_amount_cny=?, tax_amount_cny=?, grand_total_cny=?, grand_total_pkr=?, paid_amount_cny=?, paid_amount_pkr=?, balance_cny=?, balance_pkr=?, payment_method=?, bank_id=?, bank_name=?, date_time=? WHERE id=?",
                'issdddddddddddsissi', [$supplier_id, $purchase_date, $invoice_no, $exchange_rate, $total_cny, $total_pkr_val, $previous_amount_cny, $tax_amount_cny, $grand_total_cny, $grand_total_pkr_val, $paid_amount_cny, $paid_amount_pkr, $balance_cny, $balance_pkr, $payment_method, $bank_id, $bank_name, $currentDateTime, $id]);

            if ($result !== false) {
                if ($payment_method === 'bank' && $bank_id > 0 && $paid_amount_pkr > 0) {
                    updateBankBalance($bank_id, $paid_amount_pkr, 'out');
                }
                modifyData("UPDATE chinese_suppliers SET current_balance = current_balance + ? WHERE id = ?", 'di', [$balance_cny, $supplier_id]);
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
            $result = insertData("INSERT INTO import_purchases (purchase_no, supplier_id, purchase_date, invoice_no, exchange_rate, total_cny, total_pkr, previous_amount_cny, tax_amount_cny, grand_total_cny, grand_total_pkr, paid_amount_cny, paid_amount_pkr, balance_cny, balance_pkr, payment_method, bank_id, bank_name, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                'sissdddddddddddsiss', [$purchase_no, $supplier_id, $purchase_date, $invoice_no, $exchange_rate, $total_cny, $total_pkr_val, $previous_amount_cny, $tax_amount_cny, $grand_total_cny, $grand_total_pkr_val, $paid_amount_cny, $paid_amount_pkr, $balance_cny, $balance_pkr, $payment_method, $bank_id, $bank_name, $currentDateTime]);

            if ($result !== false) {
                $purchase_id = $result;
                if ($payment_method === 'bank' && $bank_id > 0 && $paid_amount_pkr > 0) {
                    updateBankBalance($bank_id, $paid_amount_pkr, 'out');
                }
                modifyData("UPDATE chinese_suppliers SET current_balance = current_balance + ? WHERE id = ?", 'di', [$balance_cny, $supplier_id]);
                foreach ($materialsPost as $idx => $mat) {
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
                    if ($realId <= 0 || $qty <= 0) { continue; }
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
                                        <?php $rowIdx = 0; ?>
                                        <?php if ($editItems): ?>
                                            <?php foreach ($editItems as $item): ?>
                                                <?php
                                                    $valId = ($item['item_type'] === 'finished_good' ? 'fg_' : '') . $item['material_id'];
                                                    $prefix = ($item['item_type'] === 'finished_good') ? '[FG] ' : '[Raw] ';
                                                    $displayVal = $prefix . ($item['item_code'] ?? '') . ' - ' . ($item['item_name'] ?? '');
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
                                                    <td><input type="number" step="0.01" class="form-control form-control-sm unit-price-cny" name="materials[<?php echo $rowIdx; ?>][unit_price_cny]" value="<?php echo $item['unit_price_cny']; ?>" required oninput="calcRow(this)"></td>
                                                    <td><input type="text" class="form-control form-control-sm unit-price-pkr" value="<?php echo number_format($item['unit_price_pkr'], 2); ?>" readonly></td>
                                                    <td><input type="text" class="form-control form-control-sm row-total-cny" value="<?php echo number_format($item['total_cny'], 2); ?>" readonly></td>
                                                    <td><input type="text" class="form-control form-control-sm row-total-pkr" value="<?php echo number_format($item['total_pkr'], 2); ?>" readonly></td>
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
                                                <td><input type="number" step="0.01" class="form-control form-control-sm unit-price-cny" name="materials[0][unit_price_cny]" value="0" required oninput="calcRow(this)"></td>
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

                    <input type="hidden" name="tax_amount_cny" value="0.00">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card" style="border:2px solid #1a2332;">
                                <div class="card-header" style="background:#1a2332;color:#fff;">
                                    <i class="fas fa-calculator me-2"></i>Payment Summary
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm mb-0">
                                        <tr>
                                            <td style="width:50%;"><strong>Previous Balance (CNY)</strong></td>
                                            <td class="text-end"><strong id="prevBalanceDisplay"><?php echo $editPurchase ? number_format($editPurchase['previous_amount_cny'], 2) : '0.00'; ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Bill Amount (CNY)</strong></td>
                                            <td class="text-end"><strong id="billAmountDisplay">0.00</strong></td>
                                        </tr>
                                        <tr style="border-top:2px solid #1a2332;">
                                            <td><strong style="font-size:16px;">Grand Amount (CNY)</strong></td>
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
                                            <label class="form-label">Paid Amount (CNY)</label>
                                            <input type="number" step="0.01" class="form-control" id="paidAmountCny" name="paid_amount_cny" value="<?php echo $editPurchase ? $editPurchase['paid_amount_cny'] : '0.00'; ?>" oninput="calcSummary()">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Payment Method</label>
                                            <select class="form-select" name="payment_method" id="ip_payment_method" onchange="toggleIpBank(this.value)">
                                                <option value="credit" <?php echo (!$editPurchase || ($editPurchase['payment_method'] ?? 'credit') === 'credit') ? 'selected' : ''; ?>>Credit</option>
                                                <option value="cash" <?php echo ($editPurchase && ($editPurchase['payment_method'] ?? '') === 'cash') ? 'selected' : ''; ?>>Cash</option>
                                                <option value="bank" <?php echo ($editPurchase && ($editPurchase['payment_method'] ?? '') === 'bank') ? 'selected' : ''; ?>>Bank</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3" id="ip_bank_group" style="display: <?php echo ($editPurchase && ($editPurchase['payment_method'] ?? '') === 'bank') ? 'block' : 'none'; ?>;">
                                            <label class="form-label text-primary fw-bold"><i class="fas fa-university me-1"></i>Bank Account *</label>
                                            <select class="form-select" name="bank_id" id="ip_bank_id">
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
                        <a href="import_purchases.php" class="btn btn-secondary btn-lg ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleIpBank(method) {
    var bg = document.getElementById('ip_bank_group');
    if (bg) {
        bg.style.display = (method === 'bank') ? 'block' : 'none';
        var bSel = document.getElementById('ip_bank_id');
        if (bSel) bSel.required = (method === 'bank');
    }
}
var materialsData = <?php echo json_encode(array_values($materials)); ?>;
var materialRowIndex = <?php echo $rowIdx; ?>;

function onSupplierChange() {
    var sel = document.getElementById('supplier_id');
    var opt = sel.options[sel.selectedIndex];
    var balance = parseFloat(opt.dataset.balance) || 0;
    document.getElementById('previous_amount_cny').value = balance.toFixed(2);
    document.getElementById('previous_balance_display').value = balance.toFixed(2);
    document.getElementById('prevBalanceDisplay').textContent = balance.toFixed(2);
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
            const name = (m.material_name || m.name || '').toLowerCase();
            const code = (m.material_code || m.code || '').toLowerCase();
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

    const rate = getRate();
    let html = '';
    matches.forEach(function(m, idx) {
        const isRaw = (m.item_type === 'raw_material');
        const badgeClass = isRaw ? 'bg-primary' : 'bg-success';
        const badgeText = isRaw ? 'RAW' : 'FG';
        const name = escapeHtml(m.material_name || m.name || '');
        const code = escapeHtml(m.material_code || m.code || '');
        const unit = escapeHtml(m.unit || '');
        const stock = parseFloat(m.current_stock || m.stock || 0);
        const pricePkr = parseFloat(m.price_pkr || 0);
        const priceCny = rate > 0 ? (pricePkr / rate) : 0;

        html += '<div class="material-dropdown-item ' + (idx === 0 ? 'active' : '') + '" data-id="' + m.id + '" onmousedown="selectMaterialItem(this)">' +
            '<div>' +
                '<div><span class="badge ' + badgeClass + ' me-1">' + badgeText + '</span><strong>' + name + '</strong></div>' +
                '<small class="text-muted ps-1">Code: ' + code + ' | Stock: ' + stock + ' ' + unit + '</small>' +
            '</div>' +
            '<div class="text-end ps-2">' +
                '<span class="fw-bold text-success">' + priceCny.toFixed(2) + ' CNY</span>' +
                '<small class="text-muted d-block">Rs. ' + pricePkr.toFixed(2) + '</small>' +
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

    const pricePkr = parseFloat(m.price_pkr || 0);
    const rate = getRate();
    const priceCny = rate > 0 ? (pricePkr / rate) : 0;
    row.querySelector('.unit-price-cny').value = priceCny.toFixed(2);

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
                const priceInput = row.querySelector('.unit-price-cny');
                if (priceInput) priceInput.value = '0.00';
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
    html += '<td><input type="number" step="0.01" class="form-control form-control-sm unit-price-cny" name="materials['+idx+'][unit_price_cny]" value="0" required oninput="calcRow(this)"></td>';
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
    var grandCny = prevCny + billCny;
    var rate = getRate();

    document.getElementById('prevBalanceDisplay').textContent = prevCny.toFixed(2);
    document.getElementById('billAmountDisplay').textContent = billCny.toFixed(2);
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
        var hidden = row.querySelector('.material-id-input');
        if (!hidden || !hidden.value) valid = false;
        if ((parseFloat(row.querySelector('.qty').value) || 0) <= 0) valid = false;
        if ((parseFloat(row.querySelector('.unit-price-cny').value) || 0) <= 0) valid = false;
    });
    if (!valid) {
        alert('Please ensure all materials are selected and have valid data (qty > 0, price > 0).');
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
