<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'New Sale Order';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

$holdSale = null;
$holdItems = [];
$holdId = isset($_GET['hold_id']) ? (int)$_GET['hold_id'] : 0;
if ($holdId > 0) {
    $holdSale = getRow("SELECT * FROM sales WHERE id = ? AND status = 'hold'", 'i', [$holdId]);
    if ($holdSale) {
        $holdItems = getRows("SELECT si.*, fg.product_code, fg.product_name, fg.selling_price
            FROM sale_items si
            LEFT JOIN finished_goods fg ON si.product_id = fg.id
            WHERE si.sale_id = ?", 'i', [$holdId]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'complete';
    $customer_type = $_POST['customer_type'] ?? 'credit';
    $customer_id = $customer_type === 'credit' ? (int)$_POST['customer_id'] : 0;
    $walkin_name = $customer_type === 'walkin' ? trim($_POST['walkin_name']) : '';
    $walkin_phone = $customer_type === 'walkin' ? trim($_POST['walkin_phone']) : '';
    $sale_date = $_POST['sale_date'];
    $total_amount = (float)$_POST['total_amount'];
    $discount = (float)$_POST['discount'];
    $final_amount = (float)$_POST['final_amount'];
    $payment_method = $_POST['payment_method'] ?? 'credit';
    $products = isset($_POST['products']) ? $_POST['products'] : [];
    $hold_id = (int)$_POST['hold_id'];

    if ($customer_type === 'credit' && $customer_id <= 0) {
        $message = 'Please select a customer!';
        $messageType = 'danger';
    } elseif ($customer_type === 'walkin' && empty($walkin_name)) {
        $message = 'Please enter customer name!';
        $messageType = 'danger';
    } elseif (empty($sale_date)) {
        $message = 'Please select sale date!';
        $messageType = 'danger';
    } elseif (empty($products)) {
        $message = 'Please add at least one product!';
        $messageType = 'danger';
    } else {
        $currentDateTime = getCurrentDateTime();
        $paid_amount = 0;
        $payment_status = 'unpaid';
        $balance = $total_amount - $discount;

        if ($payment_method === 'cash' || $payment_method === 'bank') {
            $paid_amount = $final_amount;
            $balance = 0;
            $payment_status = 'paid';
        }

        if ($hold_id > 0) {
            modifyData("UPDATE sales SET customer_id=?, customer_type=?, walkin_name=?, walkin_phone=?, sale_date=?, total_amount=?, discount=?, final_amount=?, paid_amount=?, balance=?, payment_status=?, payment_method=?, date_time=? WHERE id=?",
                'issssdddddsssi', [$customer_id, $customer_type, $walkin_name, $walkin_phone, $sale_date, $total_amount, $discount, $final_amount, $paid_amount, $balance, $payment_status, $payment_method, $currentDateTime, $hold_id]);
            modifyData("DELETE FROM sale_items WHERE sale_id=?", 'i', [$hold_id]);

            if ($action === 'complete') {
                modifyData("UPDATE sales SET status='completed', hold_reason=NULL WHERE id=?", 'i', [$hold_id]);
                foreach ($products as $product) {
                    $pid = (int)$product['product_id']; $qty = (float)$product['quantity']; $up = (float)$product['unit_price'];
                    insertData("INSERT INTO sale_items (sale_id,product_id,quantity,unit_price,total,date_time) VALUES (?,?,?,?,?,?)", 'iiddds', [$hold_id, $pid, $qty, $up, $qty*$up, $currentDateTime]);
                    modifyData("UPDATE finished_goods SET current_stock=current_stock-? WHERE id=?", 'di', [$qty, $pid]);
                }
                setFlash('Sale completed successfully!', 'success'); header('Location: sale_print.php?id=' . $hold_id); exit;
            } else {
                foreach ($products as $product) {
                    $pid = (int)$product['product_id']; $qty = (float)$product['quantity']; $up = (float)$product['unit_price'];
                    insertData("INSERT INTO sale_items (sale_id,product_id,quantity,unit_price,total,date_time) VALUES (?,?,?,?,?,?)", 'iiddds', [$hold_id, $pid, $qty, $up, $qty*$up, $currentDateTime]);
                }
                setFlash('Bill updated and held!', 'success'); header('Location: view_hold_bills.php'); exit;
            }
        } else {
            $sale_no = generateCode('SL');
            $newStatus = ($action === 'hold') ? 'hold' : 'completed';
            $result = insertData("INSERT INTO sales (sale_no,customer_id,customer_type,walkin_name,walkin_phone,sale_date,total_amount,discount,final_amount,paid_amount,balance,payment_status,payment_method,status,date_time) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                'sisssdddddsssss', [$sale_no, $customer_id, $customer_type, $walkin_name, $walkin_phone, $sale_date, $total_amount, $discount, $final_amount, $paid_amount, $balance, $payment_status, $payment_method, $newStatus, $currentDateTime]);

            if ($result !== false) {
                $saleId = $result;
                foreach ($products as $product) {
                    $pid = (int)$product['product_id']; $qty = (float)$product['quantity']; $up = (float)$product['unit_price'];
                    insertData("INSERT INTO sale_items (sale_id,product_id,quantity,unit_price,total,date_time) VALUES (?,?,?,?,?,?)", 'iiddds', [$saleId, $pid, $qty, $up, $qty*$up, $currentDateTime]);
                    if ($newStatus === 'completed') {
                        modifyData("UPDATE finished_goods SET current_stock=current_stock-? WHERE id=?", 'di', [$qty, $pid]);
                    }
                }
                if ($action === 'hold') {
                    setFlash('Bill placed on hold!', 'success'); header('Location: view_hold_bills.php'); exit;
                } else {
                    setFlash('Sale completed!', 'success'); header('Location: sale_print.php?id=' . $saleId); exit;
                }
            } else {
                $message = 'Error creating sale! ' . $db->error; $messageType = 'danger';
                error_log('SALE INSERT ERROR: ' . $db->error . ' | Types: sisssdddddsssss | Values: ' . json_encode([$sale_no, $customer_id, $customer_type, $walkin_name, $walkin_phone, $sale_date, $total_amount, $discount, $final_amount, $paid_amount, $balance, $payment_status, $payment_method, $newStatus, $currentDateTime]));
            }
        }
    }
}

$customers = getRows("SELECT id, customer_name, company_name FROM customers WHERE status='active' ORDER BY customer_name");
$products = getRows("SELECT id, product_code, product_name, unit, selling_price, current_stock FROM finished_goods WHERE status='active' AND current_stock>0 ORDER BY product_name");

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
body { background: #f0f2f5; }
.key-hint { font-size: 11px; color: #888; margin-left: 4px; }
.card-header .key-hint { float: right; }
.product-search-wrap { position: relative; }
.product-search-dropdown { position: fixed; z-index: 1050; max-height: 200px; overflow-y: auto; background: #fff; border: 1px solid #ddd; border-top: none; display: none; min-width: 200px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.product-search-dropdown .item { padding: 6px 10px; cursor: pointer; border-bottom: 1px solid #f0f0f0; }
.product-search-dropdown .item:hover, .product-search-dropdown .item.active { background: #0d6efd; color: #fff; }
.product-search-dropdown .item small { display: block; font-size: 11px; opacity: 0.7; }
.product-search-wrap input:focus + .product-search-dropdown, .product-search-dropdown.show { display: block; }
.customer-search-wrap { position: relative; }
.customer-search-dropdown { position: absolute; top: 100%; left: 0; right: 0; z-index: 1050; max-height: 200px; overflow-y: auto; background: #fff; border: 1px solid #ddd; display: none; }
.customer-search-dropdown .item { padding: 6px 10px; cursor: pointer; border-bottom: 1px solid #f0f0f0; }
.customer-search-dropdown .item:hover, .customer-search-dropdown .item.active { background: #0d6efd; color: #fff; }
.customer-search-dropdown.show { display: block; }
#saleForm .row { margin-bottom: 0; }
.final-amount-box { font-size: 22px; font-weight: bold; padding: 10px 15px; background: #e8f4fd; border-radius: 6px; text-align: right; }
.action-bar { position: sticky; bottom: 0; background: #fff; padding: 12px 20px; border-top: 2px solid #0d6efd; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); z-index: 1000; }
.product-item { border: 1px solid #e8eaed; border-radius: 8px; padding: 8px; margin-bottom: 6px; background: #fff; transition: box-shadow 0.2s; }
.product-item:hover { box-shadow: 0 1px 6px rgba(0,0,0,0.08); }
.product-item .product-search { font-size: 13px; font-weight: 500; }
.product-item .qty, .product-item .uprice { font-size: 14px; font-weight: 600; color: #1a2332; min-width: 0; -moz-appearance: textfield; }
.product-item .qty::-webkit-inner-spin-button, .product-item .qty::-webkit-outer-spin-button,
.product-item .uprice::-webkit-inner-spin-button, .product-item .uprice::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
.product-item .rtotal { font-size: 14px; font-weight: 600; color: #0d6efd; background-color: #f0f7ff !important; border-color: #b6d4fe !important; min-width: 0; }
.product-item .stock-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: #e9ecef; color: #495057; white-space: nowrap; }
</style>

<div class="container-fluid px-3">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show my-2 py-2"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>New Sale</h5>
        <a href="sales.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>

    <form method="POST" action="" id="saleForm" autocomplete="off">
        <input type="hidden" name="hold_id" value="<?php echo $holdId; ?>">
        <input type="hidden" name="action" id="formAction" value="complete">

        <div class="row g-2 mb-2">
            <!-- Left: Customer -->
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header py-2"><i class="fas fa-user me-1"></i>Customer <span class="key-hint">[Alt+C]</span></div>
                    <div class="card-body py-2">
                        <div class="d-flex gap-3 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="customer_type" id="typeCredit" value="credit" checked onchange="toggleType()">
                                <label class="form-check-label" for="typeCredit">Credit</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="customer_type" id="typeWalkin" value="walkin" onchange="toggleType()">
                                <label class="form-check-label" for="typeWalkin">Walk-in</label>
                            </div>
                        </div>
                        <div id="creditSection">
                            <div class="customer-search-wrap">
                                <input type="text" class="form-control form-control-sm" id="customerSearchInput" placeholder="Search customer name..." onkeyup="searchCustomer(this)" onfocus="searchCustomer(this)" onblur="setTimeout(function(){document.querySelector('.customer-search-dropdown.show')&&document.querySelector('.customer-search-dropdown').classList.remove('show')},200)">
                                <input type="hidden" name="customer_id" id="customer_id" value="<?php echo $holdSale && $holdSale['customer_id'] > 0 ? $holdSale['customer_id'] : ''; ?>">
                                <div class="customer-search-dropdown" id="customerDropdown"></div>
                            </div>
                        </div>
                        <div id="walkinSection" style="display:none">
                            <input type="text" class="form-control form-control-sm mb-1" id="walkin_name" name="walkin_name" placeholder="Full Name *" value="<?php echo $holdSale ? htmlspecialchars($holdSale['walkin_name']) : ''; ?>">
                            <input type="text" class="form-control form-control-sm" id="walkin_phone" name="walkin_phone" placeholder="Phone" value="<?php echo $holdSale ? htmlspecialchars($holdSale['walkin_phone']) : ''; ?>">
                        </div>
                        <div class="mt-2">
                            <input type="date" class="form-control form-control-sm" id="sale_date" name="sale_date" value="<?php echo $holdSale ? $holdSale['sale_date'] : date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Middle: Products quick entry -->
            <div class="col-md-5">
                <div class="card shadow-sm h-100">
                    <div class="card-header py-2"><i class="fas fa-boxes me-1"></i>Products <span class="key-hint">[F5=Add, Del=Remove]</span></div>
                    <div class="card-body p-2" style="max-height:260px; overflow-y:auto" id="productBody">
                        <?php if ($holdItems): $idx=0; foreach ($holdItems as $item): $idx++; ?>
                        <div class="product-item">
                            <div class="row g-1 mb-1">
                                <div class="col">
                                    <div class="product-search-wrap">
                                        <input type="text" class="form-control form-control-sm product-search" data-id="<?php echo $item['product_id']; ?>" value="<?php echo htmlspecialchars($item['product_code'] ? $item['product_code'].' - '.$item['product_name'] : ''); ?>" placeholder="Type product name or code..." onkeyup="productSearch(this)" onfocus="productSearch(this)">
                                        <input type="hidden" name="products[<?php echo $idx; ?>][product_id]" class="product-id" value="<?php echo $item['product_id']; ?>">
                                        <div class="product-search-dropdown"></div>
                                    </div>
                                </div>
                                <div class="col-auto d-flex align-items-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger px-1 py-0" onclick="removeRow(this)" tabindex="-1"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                            <div class="row g-2 align-items-end">
                                <div class="col-auto"><span class="stock-badge">Stock: <span class="stock-display fw-bold"><?php echo $item['current_stock'] ?? 0; ?></span></span></div>
                                <div class="col"><label class="small text-muted mb-0">Qty</label><input type="number" step="0.01" class="form-control form-control-sm qty" name="products[<?php echo $idx; ?>][quantity]" value="<?php echo $item['quantity']; ?>" onchange="calcRow(this)" onkeydown="return qtyKeydown(event,this)"></div>
                                <div class="col"><label class="small text-muted mb-0">Price</label><input type="number" step="0.01" class="form-control form-control-sm uprice" name="products[<?php echo $idx; ?>][unit_price]" value="<?php echo $item['unit_price']; ?>" onchange="calcRow(this)"></div>
                                <div class="col"><label class="small text-muted mb-0">Total</label><input type="text" class="form-control form-control-sm rtotal" value="<?php echo number_format($item['total'],2); ?>" readonly></div>
                            </div>
                        </div>
                        <?php endforeach; else: ?>
                        <div class="product-item">
                            <div class="row g-1 mb-1">
                                <div class="col">
                                    <div class="product-search-wrap">
                                        <input type="text" class="form-control form-control-sm product-search" placeholder="Type product name or code..." onkeyup="productSearch(this)" onfocus="productSearch(this)" autofocus>
                                        <input type="hidden" name="products[0][product_id]" class="product-id" value="">
                                        <div class="product-search-dropdown"></div>
                                    </div>
                                </div>
                                <div class="col-auto d-flex align-items-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger px-1 py-0" onclick="removeRow(this)" tabindex="-1"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                            <div class="row g-2 align-items-end">
                                <div class="col-auto"><span class="stock-badge">Stock: <span class="stock-display fw-bold">0</span></span></div>
                                <div class="col"><label class="small text-muted mb-0">Qty</label><input type="number" step="0.01" class="form-control form-control-sm qty" name="products[0][quantity]" value="1" onchange="calcRow(this)" onkeydown="return qtyKeydown(event,this)"></div>
                                <div class="col"><label class="small text-muted mb-0">Price</label><input type="number" step="0.01" class="form-control form-control-sm uprice" name="products[0][unit_price]" value="0" onchange="calcRow(this)"></div>
                                <div class="col"><label class="small text-muted mb-0">Total</label><input type="text" class="form-control form-control-sm rtotal" value="0.00" readonly></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Summary -->
            <div class="col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-header py-2"><i class="fas fa-calculator me-1"></i>Summary <span class="key-hint">[Enter=Next]</span></div>
                    <div class="card-body py-2" style="font-size:13px">
                        <div class="d-flex justify-content-between align-items-center mb-1" id="creditSummary">
                            <span class="text-muted text-nowrap">Previous:</span>
                            <span class="text-nowrap"><span id="prevDisplay">0.00</span> <small id="prevType" class="text-muted"></small></span>
                            <input type="hidden" id="prevAmount" value="0">
                            <input type="hidden" id="prevBalanceType" value="receivable">
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1"><span class="text-muted text-nowrap">Bill:</span><span class="text-nowrap" id="billDisplay">0.00</span></div>
                        <div class="d-flex justify-content-between align-items-center mb-1"><span class="text-muted text-nowrap">Grand:</span><span class="text-nowrap" id="grandDisplay">0.00</span></div>
                        <div class="d-flex justify-content-between align-items-center mb-1 mt-1"><span class="text-nowrap">Discount:</span><input type="number" step="0.01" class="form-control form-control-sm text-end" style="width:100px" id="discInput" name="discount" value="<?php echo $holdSale ? $holdSale['discount'] : '0'; ?>" onchange="calcFinal()" onkeyup="calcFinal()"></div>
                        <hr class="my-1">
                        <div class="d-flex justify-content-between align-items-center"><strong class="text-nowrap">Final:</strong><span class="final-amount-box py-1 px-2 text-nowrap" id="finalDisplay" style="font-size:16px">0.00</span></div>
                        <div class="d-flex justify-content-between align-items-center mt-1"><span class="text-nowrap">Pay Method:</span><select class="form-select form-select-sm" style="width:110px" id="payMethod" name="payment_method"></select></div>
                        <input type="hidden" name="total_amount" id="hidBillAmount" value="0">
                        <input type="hidden" name="final_amount" id="hidFinalAmount" value="0">
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="action-bar text-end">
            <a href="sales.php" class="btn btn-outline-secondary me-2"><i class="fas fa-times"></i> Cancel <span class="key-hint">[Esc]</span></a>
            <button type="button" class="btn btn-warning me-2" onclick="doHold()"><i class="fas fa-pause"></i> Hold Bill <span class="key-hint">[F3]</span></button>
            <button type="button" class="btn btn-primary btn-lg px-4" onclick="doComplete()"><i class="fas fa-check"></i> Complete Sale <span class="key-hint">[F2]</span></button>
        </div>
    </form>
</div>

<script>
var products = <?php echo json_encode($products); ?>;
var customers = <?php echo json_encode($customers); ?>;
var _idx = <?php echo $holdItems ? count($holdItems) : 1; ?>;

// ─── Customer type toggle ───
function toggleType() {
    var t = document.querySelector('input[name="customer_type"]:checked').value;
    document.getElementById('creditSection').style.display = t === 'credit' ? '' : 'none';
    document.getElementById('walkinSection').style.display = t === 'walkin' ? '' : 'none';
    var pm = document.getElementById('payMethod');
    if (t === 'credit') {
        document.getElementById('creditSummary').style.display = '';
        pm.innerHTML = '<option value="credit">Credit</option>';
        loadPrevBal();
    } else {
        document.getElementById('creditSummary').style.display = 'none';
        document.getElementById('prevAmount').value = '0';
        document.getElementById('prevBalanceType').value = 'receivable';
        document.getElementById('prevDisplay').textContent = '0.00';
        document.getElementById('prevType').textContent = '';
        pm.innerHTML = '<option value="cash">Cash</option><option value="bank">Bank</option>';
        pm.value = 'cash';
        calcFinal();
    }
}

// ─── Customer search ───
function searchCustomer(el) {
    var q = el.value.toLowerCase().trim();
    var dd = document.getElementById('customerDropdown');
    if (!q) { dd.innerHTML = ''; dd.classList.remove('show'); return; }
    var html = '';
    customers.forEach(function(c, i) {
        if (c.customer_name.toLowerCase().includes(q) || (c.company_name && c.company_name.toLowerCase().includes(q))) {
            html += '<div class="item" data-idx="' + i + '" data-id="' + c.id + '" data-name="' + c.customer_name.replace(/'/g, "\\'") + '" onclick="pickCustomer(' + c.id + ',\'' + c.customer_name.replace(/'/g, "\\'") + '\')">' + c.customer_name + ' <small>' + (c.company_name || '') + '</small></div>';
        }
    });
    dd.innerHTML = html;
    dd.classList.add('show');
}

function pickCustomer(id, name) {
    document.getElementById('customerSearchInput').value = name;
    document.getElementById('customer_id').value = id;
    document.getElementById('customerDropdown').innerHTML = '';
    document.getElementById('customerDropdown').classList.remove('show');
    loadPrevBal();
    var firstSearch = document.querySelector('#productBody .product-item .product-search');
    if (firstSearch) firstSearch.focus();
}

// ─── Previous balance ───
function loadPrevBal() {
    var cid = document.getElementById('customer_id').value;
    if (!cid) { document.getElementById('prevAmount').value = '0'; document.getElementById('prevBalanceType').value = 'receivable'; document.getElementById('prevDisplay').textContent = '0.00'; document.getElementById('prevType').textContent = ''; calcFinal(); return; }
    fetch('ajax_customer_balance.php?id='+cid).then(function(r){return r.json()}).then(function(d){
        document.getElementById('prevAmount').value = d.balance;
        document.getElementById('prevBalanceType').value = d.balance_type;
        document.getElementById('prevDisplay').textContent = Math.abs(d.balance).toFixed(2);
        document.getElementById('prevType').textContent = d.balance_type === 'payable' ? '(Payable)' : '(Receivable)';
        calcFinal();
    }).catch(function(){document.getElementById('prevAmount').value='0';document.getElementById('prevBalanceType').value='receivable';document.getElementById('prevDisplay').textContent='0.00';document.getElementById('prevType').textContent='';calcFinal()});
}

// ─── Product search ───
function productSearch(el) {
    var q = el.value.toLowerCase().trim();
    var dd = el.parentNode.querySelector('.product-search-dropdown');
    if (!q) { dd.innerHTML = ''; dd.classList.remove('show'); return; }
    var html = '';
    products.forEach(function(p, i) {
        if (p.product_code.toLowerCase().includes(q) || p.product_name.toLowerCase().includes(q)) {
            html += '<div class="item" data-idx="' + i + '" data-id="' + p.id + '" data-price="' + p.selling_price + '" data-stock="' + p.current_stock + '" data-code="' + p.product_code + '" data-name="' + p.product_name.replace(/'/g, "\\'") + '" onclick="pickProduct(this)">' + p.product_code + ' - ' + p.product_name + ' <small>Stock: ' + p.current_stock + '</small></div>';
        }
    });
    dd.innerHTML = html;
    dd.classList.add('show');
    var rect = el.getBoundingClientRect();
    dd.style.left = rect.left + 'px';
    dd.style.top = rect.bottom + 'px';
    dd.style.width = rect.width + 'px';
}

function pickProduct(item) {
    var block = item.closest('.product-item');
    var wrap = block.querySelector('.product-search-wrap');
    wrap.querySelector('.product-search').value = item.dataset.code + ' - ' + item.dataset.name;
    wrap.querySelector('.product-id').value = item.dataset.id;
    block.querySelector('.stock-display').textContent = item.dataset.stock;
    block.querySelector('.uprice').value = item.dataset.price;
    wrap.querySelector('.product-search-dropdown').innerHTML = '';
    wrap.querySelector('.product-search-dropdown').classList.remove('show');
    block.querySelector('.qty').focus();
    block.querySelector('.qty').select();
    calcRow(block.querySelector('.qty'));
}

// ─── Row calculations ───
function calcRow(el) {
    var block = el.closest('.product-item');
    var qty = parseFloat(block.querySelector('.qty').value) || 0;
    var price = parseFloat(block.querySelector('.uprice').value) || 0;
    block.querySelector('.rtotal').value = (qty * price).toFixed(2);
    calcGrandTotal();
}

function calcGrandTotal() {
    var blocks = document.querySelectorAll('#productBody .product-item');
    var total = 0;
    blocks.forEach(function(b) { total += parseFloat(b.querySelector('.rtotal').value) || 0; });
    document.getElementById('billDisplay').textContent = total.toFixed(2);
    document.getElementById('hidBillAmount').value = total.toFixed(2);
    calcFinal();
}

function calcFinal() {
    var t = document.querySelector('input[name="customer_type"]:checked').value;
    var bill = parseFloat(document.getElementById('billDisplay').textContent) || 0;
    var prev = parseFloat(document.getElementById('prevAmount').value) || 0;
    var disc = parseFloat(document.getElementById('discInput').value) || 0;
    var grand = bill + prev;
    document.getElementById('grandDisplay').textContent = grand.toFixed(2);
    var fin = Math.max(0, bill - disc);
    document.getElementById('finalDisplay').textContent = fin.toFixed(2);
    document.getElementById('hidFinalAmount').value = fin.toFixed(2);
}

// ─── Add / Remove rows ───
function addRow() {
    var body = document.getElementById('productBody');
    var div = document.createElement('div');
    div.className = 'product-item';
    _idx++;
    div.innerHTML = '<div class="row g-1 mb-1"><div class="col"><div class="product-search-wrap"><input type="text" class="form-control form-control-sm product-search" placeholder="Type product name or code..." onkeyup="productSearch(this)" onfocus="productSearch(this)"><input type="hidden" name="products['+_idx+'][product_id]" class="product-id" value=""><div class="product-search-dropdown"></div></div></div><div class="col-auto d-flex align-items-center"><button type="button" class="btn btn-sm btn-outline-danger px-1 py-0" onclick="removeRow(this)" tabindex="-1"><i class="fas fa-times"></i></button></div></div><div class="row g-2 align-items-end"><div class="col-auto"><span class="stock-badge">Stock: <span class="stock-display fw-bold">0</span></span></div><div class="col"><label class="small text-muted mb-0">Qty</label><input type="number" step="0.01" class="form-control form-control-sm qty" name="products['+_idx+'][quantity]" value="1" onchange="calcRow(this)" onkeydown="return qtyKeydown(event,this)"></div><div class="col"><label class="small text-muted mb-0">Price</label><input type="number" step="0.01" class="form-control form-control-sm uprice" name="products['+_idx+'][unit_price]" value="0" onchange="calcRow(this)"></div><div class="col"><label class="small text-muted mb-0">Total</label><input type="text" class="form-control form-control-sm rtotal" value="0.00" readonly></div></div>';
    body.appendChild(div);
    div.querySelector('.product-search').focus();
    body.scrollTop = body.scrollHeight;
}

function removeRow(btn) {
    var body = document.getElementById('productBody');
    var blocks = body.querySelectorAll('.product-item');
    if (blocks.length <= 1) { alert('Need at least one product'); return; }
    btn.closest('.product-item').remove();
    calcGrandTotal();
}

// ─── Keyboard navigation ───
function qtyKeydown(e, el) {
    if (e.key === 'Enter') {
        e.preventDefault();
        var block = el.closest('.product-item');
        var nextBlock = block.nextElementSibling;
        if (!nextBlock) {
            addRow();
            nextBlock = block.nextElementSibling;
        }
        if (nextBlock) {
            var inp = nextBlock.querySelector('.product-search');
            if (inp) { inp.focus(); }
        }
        return false;
    }
    return true;
}

// Global key handler
document.addEventListener('keydown', function(e) {
    // F2 = Complete
    if (e.key === 'F2') { e.preventDefault(); doComplete(); }
    // F3 = Hold
    if (e.key === 'F3') { e.preventDefault(); doHold(); }
    // F5 = Add row
    if (e.key === 'F5') { e.preventDefault(); addRow(); }
    // Esc = go back
    if (e.key === 'Escape') { window.location.href = 'sales.php'; }

    // Alt+C focus customer
    if (e.altKey && (e.key === 'c' || e.key === 'C')) { e.preventDefault(); document.getElementById('customerSearchInput').focus(); }
});

// ─── Row-level keyboard navigation for product search ───
$(document).on('keydown', '.product-search', function(e) {
    var dd = this.parentNode.querySelector('.product-search-dropdown');
    var items = dd ? dd.querySelectorAll('.item') : [];
    if (!items.length) return;
    var active = dd.querySelector('.item.active');
    var idx = active ? parseInt(active.dataset.idx) : -1;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        var next = idx + 1;
        if (next >= items.length) next = 0;
        if (active) active.classList.remove('active');
        items[next].classList.add('active');
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        var prev = idx - 1;
        if (prev < 0) prev = items.length - 1;
        if (active) active.classList.remove('active');
        items[prev].classList.add('active');
    } else if (e.key === 'Enter') {
        if (active) { e.preventDefault(); pickProduct(active); }
    }
});

// ─── Customer search keyboard nav ───
$(document).on('keydown', '#customerSearchInput', function(e) {
    var dd = document.getElementById('customerDropdown');
    var items = dd.querySelectorAll('.item');
    if (!items.length) return;
    var active = dd.querySelector('.item.active');
    var idx = active ? parseInt(active.dataset.idx) : -1;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        var next = idx + 1;
        if (next >= items.length) next = 0;
        if (active) active.classList.remove('active');
        items[next].classList.add('active');
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        var prev = idx - 1;
        if (prev < 0) prev = items.length - 1;
        if (active) active.classList.remove('active');
        items[prev].classList.add('active');
    } else if (e.key === 'Enter') {
        if (active) { e.preventDefault(); pickCustomer(active.dataset.id, active.dataset.name); }
    }
});

// ─── Actions ───
function doHold() {
    if (!validateForm()) return;
    document.getElementById('formAction').value = 'hold';
    document.getElementById('saleForm').submit();
}

function doComplete() {
    if (!validateForm()) return;
    document.getElementById('formAction').value = 'complete';
    document.getElementById('saleForm').submit();
}

function validateForm() {
    var t = document.querySelector('input[name="customer_type"]:checked').value;
    if (t === 'credit') {
        if (!document.getElementById('customer_id').value) { alert('Select a customer!'); document.getElementById('customerSearchInput').focus(); return false; }
    } else {
        if (!document.getElementById('walkin_name').value.trim()) { alert('Enter customer name!'); document.getElementById('walkin_name').focus(); return false; }
    }
    if (!document.getElementById('sale_date').value) { alert('Select date!'); return false; }
    var blocks = document.querySelectorAll('#productBody .product-item');
    if (!blocks.length) { alert('Add at least one product!'); return false; }
    var ok = true;
    blocks.forEach(function(b) {
        if (!b.querySelector('.product-id').value) { ok = false; }
        var qty = parseFloat(b.querySelector('.qty').value) || 0;
        var stock = parseFloat(b.querySelector('.stock-display').textContent) || 0;
        var price = parseFloat(b.querySelector('.uprice').value) || 0;
        if (qty <= 0) ok = false;
        if (qty > stock) { alert('Sale qty exceeds stock!'); ok = false; }
        if (price <= 0) ok = false;
    });
    if (!ok) { alert('Check product data!'); return false; }
    return true;
}

// ─── Init ───
$(document).ready(function() {
    toggleType();
    <?php if ($holdSale && $holdSale['customer_type'] === 'credit' && $holdSale['customer_id'] > 0): ?>
    var c = customers.find(function(c2){return c2.id==<?php echo $holdSale['customer_id']; ?>});
    if (c) { document.getElementById('customerSearchInput').value = c.customer_name; document.getElementById('customer_id').value = c.id; loadPrevBal(); }
    <?php elseif ($holdSale && $holdSale['customer_type'] === 'walkin'): ?>
    document.getElementById('typeWalkin').checked = true;
    toggleType();
    <?php endif; ?>
    setTimeout(calcGrandTotal, 200);
});
</script>

<?php include '../includes/footer.php'; ?>
