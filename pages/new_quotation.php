<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'New Quotation';
$message = '';
$messageType = '';

$customers = getRows("SELECT id, customer_name, company_name, phone, address, current_balance FROM customers WHERE status = 'active' ORDER BY customer_name ASC");

// Fetch products for live search
$finishedGoods = getRows("SELECT id, product_code, product_name, unit, selling_price as price, current_stock FROM finished_goods WHERE status = 'active' ORDER BY product_name ASC");
$rawMaterials = getRows("SELECT id, material_code, material_name, unit, COALESCE(NULLIF(selling_price, 0), purchase_price_pkr) as price, current_stock FROM raw_materials WHERE status = 'active' ORDER BY material_name ASC");

$allProducts = [];
foreach ($finishedGoods as $fg) {
    $allProducts[] = [
        'id' => (int)$fg['id'],
        'type' => 'finished_good',
        'code' => $fg['product_code'],
        'name' => $fg['product_name'],
        'unit' => $fg['unit'] ?: 'unit',
        'price' => (float)$fg['price'],
        'stock' => (float)$fg['current_stock'],
        'label' => '[FG] ' . $fg['product_code'] . ' - ' . $fg['product_name'] . ' (Rs. ' . number_format($fg['price'], 2) . ')'
    ];
}
foreach ($rawMaterials as $rm) {
    $allProducts[] = [
        'id' => (int)$rm['id'],
        'type' => 'raw_material',
        'code' => $rm['material_code'],
        'name' => $rm['material_name'],
        'unit' => $rm['unit'] ?: 'unit',
        'price' => (float)$rm['price'],
        'stock' => (float)$rm['current_stock'],
        'label' => '[RM] ' . $rm['material_code'] . ' - ' . $rm['material_name'] . ' (Rs. ' . number_format($rm['price'], 2) . ')'
    ];
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_type = $_POST['customer_type'] ?? 'walkin';
    $customer_id = ($customer_type === 'credit') ? (int)($_POST['customer_id'] ?? 0) : null;
    $walkin_name = trim($_POST['walkin_name'] ?? '');
    $walkin_phone = trim($_POST['walkin_phone'] ?? '');
    $walkin_address = trim($_POST['walkin_address'] ?? '');
    
    $quotation_date = !empty($_POST['quotation_date']) ? $_POST['quotation_date'] : date('Y-m-d');
    $valid_until = !empty($_POST['valid_until']) ? $_POST['valid_until'] : date('Y-m-d', strtotime('+15 days'));
    
    $subtotal = max(0, (float)($_POST['subtotal'] ?? 0));
    $discount = max(0, (float)($_POST['discount'] ?? 0));
    $taxableBase = max(0, $subtotal - $discount);
    
    $invoice_type = ($_POST['invoice_type'] ?? 'non_tax') === 'tax' ? 'tax' : 'non_tax';
    if ($invoice_type === 'tax') {
        $tax_rate = max(0, (float)($_POST['tax_rate'] ?? 0));
        $tax_amount = max(0, (float)($_POST['tax_amount'] ?? 0));
        if ($tax_amount > 0 && $tax_rate == 0 && $taxableBase > 0) {
            $tax_rate = round(($tax_amount / $taxableBase) * 100, 2);
        } elseif ($tax_amount == 0 && $tax_rate > 0) {
            $tax_amount = round($taxableBase * ($tax_rate / 100), 2);
        }
    } else {
        $tax_rate = 0.00;
        $tax_amount = 0.00;
    }
    
    $shipping_cost = max(0, (float)($_POST['shipping_cost'] ?? 0));
    $total_amount = round($taxableBase + $tax_amount + $shipping_cost, 2);
    $notes = '';
    
    $items = isset($_POST['items']) ? $_POST['items'] : [];

    // Validation
    $validItems = [];
    foreach ($items as $item) {
        $pid = (int)($item['product_id'] ?? 0);
        $ptype = in_array($item['product_type'] ?? '', ['finished_good', 'raw_material']) ? $item['product_type'] : 'finished_good';
        $pname = trim($item['product_name'] ?? '');
        $qty = (float)($item['quantity'] ?? 0);
        $price = (float)($item['unit_price'] ?? 0);
        if ($pid > 0 && $qty > 0 && !empty($pname)) {
            $validItems[] = [
                'product_id' => $pid,
                'product_type' => $ptype,
                'product_name' => $pname,
                'quantity' => $qty,
                'unit_price' => $price,
                'total' => round($qty * $price, 2)
            ];
        }
    }

    if ($customer_type === 'credit' && empty($customer_id)) {
        $message = 'Please search and select a registered customer!';
        $messageType = 'danger';
    } elseif ($customer_type === 'walkin' && empty($walkin_name)) {
        $message = 'Please enter client / customer name!';
        $messageType = 'danger';
    } elseif (empty($validItems)) {
        $message = 'Please add at least one item to the quotation!';
        $messageType = 'danger';
    } else {
        $quotation_no = generateCode('QT');
        $now = getCurrentDateTime();
        $userId = $_SESSION['user_id'] ?? null;

        $insId = insertData(
            "INSERT INTO quotations (quotation_no, quotation_date, valid_until, customer_id, walkin_name, walkin_phone, walkin_address, subtotal, discount, invoice_type, tax_rate, tax_amount, shipping_cost, total_amount, notes, status, created_by, date_time) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)",
            'sssisssddsddddsis',
            [
                $quotation_no, $quotation_date, $valid_until, $customer_id, $walkin_name, $walkin_phone, $walkin_address,
                $subtotal, $discount, $invoice_type, $tax_rate, $tax_amount, $shipping_cost, $total_amount, $notes, $userId, $now
            ]
        );

        if ($insId) {
            foreach ($validItems as $vi) {
                insertData(
                    "INSERT INTO quotation_items (quotation_id, product_id, product_type, product_name, quantity, unit_price, total) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    'iissddd',
                    [$insId, $vi['product_id'], $vi['product_type'], $vi['product_name'], $vi['quantity'], $vi['unit_price'], $vi['total']]
                );
            }
            setFlash("Quotation $quotation_no created successfully!", 'success');
            header("Location: quotation_print.php?id=$insId");
            exit;
        } else {
            $message = 'Error creating quotation in database!';
            $messageType = 'danger';
        }
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
.search-dropdown-wrap {
    position: relative;
}
.search-dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #ffffff;
    border: 1px solid #ced4da;
    border-radius: 6px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.12);
    max-height: 280px;
    overflow-y: auto;
    z-index: 1055;
    display: none;
    margin-top: 3px;
}
.search-dropdown-menu.show {
    display: block;
}
.search-dropdown-item {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f1f3f5;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.875rem;
    transition: background-color 0.15s ease-in-out;
}
.search-dropdown-item:hover, .search-dropdown-item.active {
    background-color: #e9f2ff;
}
.search-dropdown-item:last-child {
    border-bottom: none;
}
.selected-customer-box {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-left: 4px solid #0d6efd;
    border-radius: 6px;
    padding: 10px 14px;
}
</style>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-end align-items-center mb-3">
            <a href="quotations.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-list me-1"></i>View All Quotations
            </a>
        </div>
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>
</div>

<form method="POST" action="" id="quotationForm">
<div class="row g-3">
    <!-- Left Column: Customer & Items -->
    <div class="col-lg-8">
        <!-- Customer Info Card -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light py-2">
                <h6 class="mb-0 fw-bold"><i class="fas fa-user me-2 text-primary"></i>Client / Customer Details</h6>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Customer Type</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="customer_type" id="typeWalkin" value="walkin" checked onchange="toggleCustomerType(this.value)">
                                <label class="form-check-label" for="typeWalkin">New / Walk-in Client</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="customer_type" id="typeCredit" value="credit" onchange="toggleCustomerType(this.value)">
                                <label class="form-check-label" for="typeCredit">Registered Customer</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Quotation Date *</label>
                        <input type="date" class="form-control form-control-sm" name="quotation_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Valid Until</label>
                        <input type="date" class="form-control form-control-sm" name="valid_until" value="<?php echo date('Y-m-d', strtotime('+15 days')); ?>">
                    </div>
                </div>

                <!-- Registered Customer Search Bar (No Select Dropdown) -->
                <div id="creditCustomerSection" style="display:none;" class="mb-2">
                    <label class="form-label small fw-bold">Search Registered Customer *</label>
                    <div id="customerPickerWrap" class="search-dropdown-wrap">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" class="form-control" id="customerSearchInput" placeholder="Type customer name, company, or phone..." autocomplete="off" onkeyup="filterCustomers(this)" onfocus="filterCustomers(this)">
                        </div>
                        <input type="hidden" name="customer_id" id="customerIdHidden" value="">
                        <div class="search-dropdown-menu" id="customerDropdown"></div>
                    </div>

                    <!-- Selected Customer Box -->
                    <div id="selectedCustomerBox" class="selected-customer-box mt-2" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 fw-bold text-dark" id="dispCustName">-</h6>
                                <small class="text-muted" id="dispCustDetails">-</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2" onclick="clearSelectedCustomer()" title="Change customer">
                                <i class="fas fa-times me-1"></i>Change
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Walk-in Customer Fields -->
                <div id="walkinCustomerSection" class="row g-2">
                    <div class="col-md-5">
                        <label class="form-label small fw-bold">Client / Company Name *</label>
                        <input type="text" class="form-control form-control-sm" name="walkin_name" placeholder="Client Name or Business">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Phone Number</label>
                        <input type="text" class="form-control form-control-sm" name="walkin_phone" placeholder="0300-xxxxxxx">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Address / City</label>
                        <input type="text" class="form-control form-control-sm" name="walkin_address" placeholder="Client address">
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Selector Card (Search Bar, No Select Dropdown) -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light py-2">
                <h6 class="mb-0 fw-bold"><i class="fas fa-search me-2 text-primary"></i>Add Items to Quotation</h6>
            </div>
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Search Product / Raw Material</label>
                        <div class="search-dropdown-wrap" id="productSearchWrap">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" class="form-control" id="productSearchInput" placeholder="Type item name or code to search..." autocomplete="off" onkeyup="filterProducts(this)" onfocus="filterProducts(this)">
                            </div>
                            <input type="hidden" id="selectedProductId" value="">
                            <input type="hidden" id="selectedProductType" value="">
                            <input type="hidden" id="selectedProductName" value="">
                            <input type="hidden" id="selectedProductUnit" value="">
                            <div class="search-dropdown-menu" id="productDropdown"></div>
                        </div>
                        <div id="selectedProductPill" class="mt-1 small text-primary fw-bold" style="display:none;"></div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Quantity</label>
                        <input type="number" step="0.01" class="form-control form-control-sm text-center" id="inputQty" value="1.00">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Unit Price (Rs)</label>
                        <input type="number" step="0.01" class="form-control form-control-sm text-end" id="inputPrice" value="0.00">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-primary btn-sm w-100" id="btnAddItem" onclick="addItemToQuotation()">
                            <i class="fas fa-plus me-1"></i>Add Item
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Table Card -->
        <div class="card shadow-sm">
            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-list me-2 text-primary"></i>Quotation Items</h6>
                <span class="badge bg-primary" id="itemCountBadge">0 Items</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 align-middle" id="itemsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Item Name</th>
                                <th class="text-center" style="width: 80px;">Unit</th>
                                <th class="text-center" style="width: 110px;">Qty</th>
                                <th class="text-end" style="width: 130px;">Price (Rs)</th>
                                <th class="text-end" style="width: 140px;">Total (Rs)</th>
                                <th class="text-center" style="width: 60px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody">
                            <tr id="emptyRow">
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fas fa-box-open fa-2x mb-2 d-block text-secondary"></i>
                                    No items added yet. Search and select products above to build your quotation.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Financials & Action -->
    <div class="col-lg-4">
        <!-- Totals & Calculations Card -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light py-2">
                <h6 class="mb-0 fw-bold"><i class="fas fa-calculator me-2 text-primary"></i>Quotation Summary</h6>
            </div>
            <div class="card-body">
                <!-- Subtotal Display -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Subtotal:</span>
                    <strong class="fs-6" id="subtotalDisplay">Rs. 0.00</strong>
                    <input type="hidden" name="subtotal" id="subtotalHidden" value="0.00">
                </div>

                <!-- Discount -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="text-muted mb-0">Discount (Rs):</label>
                    <div style="width: 130px;">
                        <input type="number" step="0.01" class="form-control form-control-sm text-end fw-bold" name="discount" id="discountInput" value="0.00" oninput="onDiscountChanged()">
                    </div>
                </div>

                <!-- Tax / Non-Tax Toggle -->
                <div class="p-2 mb-2 rounded bg-light border">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="small fw-bold text-dark mb-0">Invoice Type</label>
                        <span id="taxBadgeIndicator" class="badge bg-secondary">Non-Tax</span>
                    </div>
                    <div class="d-flex gap-3 my-1">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="radio" name="invoice_type" id="typeNonTax" value="non_tax" checked onchange="handleTaxToggle(this.value)">
                            <label class="form-check-label small" for="typeNonTax">Non-Tax</label>
                        </div>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="radio" name="invoice_type" id="typeTax" value="tax" onchange="handleTaxToggle(this.value)">
                            <label class="form-check-label small" for="typeTax">Tax Quotation</label>
                        </div>
                    </div>

                    <!-- Two-Way Editable Tax Section -->
                    <div id="taxFieldsWrap" style="display:none;" class="mt-2 pt-2 border-top">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="small text-muted mb-1 d-block">Tax Rate (%):</label>
                                <input type="number" step="0.01" class="form-control form-control-sm text-end fw-bold" name="tax_rate" id="taxRateInput" value="18.00" oninput="onTaxRateInput(this.value)">
                            </div>
                            <div class="col-6">
                                <label class="small text-muted mb-1 d-block">Tax Amount (Rs):</label>
                                <input type="number" step="0.01" class="form-control form-control-sm text-end fw-bold text-primary" name="tax_amount" id="taxAmountInput" value="0.00" oninput="onTaxAmountInput(this.value)">
                            </div>
                        </div>
                        <small class="text-muted d-block mt-1" style="font-size: 11px;">
                            <i class="fas fa-info-circle me-1"></i>Rate badlein to Amount badalti he, ya Amount badlein to Rate calculate hota he.
                        </small>
                    </div>
                </div>

                <!-- Shipping Charges -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="text-muted mb-0"><i class="fas fa-shipping-fast me-1 text-info"></i>Shipping (Rs):</label>
                    <div style="width: 130px;">
                        <input type="number" step="0.01" class="form-control form-control-sm text-end fw-bold" name="shipping_cost" id="shippingInput" value="0.00" oninput="recalculateTotals()">
                    </div>
                </div>

                <hr class="my-2">

                <!-- Grand Total -->
                <div class="d-flex justify-content-between align-items-center py-2 px-3 rounded bg-primary text-white mb-3">
                    <span class="fw-bold fs-6">Grand Total:</span>
                    <strong class="fs-4" id="grandTotalDisplay">Rs. 0.00</strong>
                    <input type="hidden" name="total_amount" id="grandTotalHidden" value="0.00">
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-success w-100 py-2 fw-bold fs-6 shadow-sm" id="btnSaveQuotation">
                    <i class="fas fa-check-circle me-2"></i>Save &amp; Print Quotation
                </button>
            </div>
        </div>
    </div>
</div>
</form>

<script>
// In-memory data for instant search
const rawCustomers = <?php echo json_encode($customers); ?>;
const rawProducts = <?php echo json_encode($allProducts); ?>;

let quotationCart = [];
let lastEditedTaxField = 'rate'; // 'rate' or 'amount'

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// ── CUSTOMER TYPE TOGGLE & SEARCH ──
function toggleCustomerType(val) {
    if (val === 'credit') {
        document.getElementById('creditCustomerSection').style.display = 'block';
        document.getElementById('walkinCustomerSection').style.display = 'none';
        if (!document.getElementById('customerIdHidden').value) {
            document.getElementById('customerSearchInput').focus();
        }
    } else {
        document.getElementById('creditCustomerSection').style.display = 'none';
        document.getElementById('walkinCustomerSection').style.display = 'flex';
    }
}

function filterCustomers(input) {
    const q = input.value.trim().toLowerCase();
    const dropdown = document.getElementById('customerDropdown');

    if (!q) {
        // Show first 10 customers
        renderCustomerDropdown(rawCustomers.slice(0, 10), dropdown);
        return;
    }

    const matches = rawCustomers.filter(c => 
        (c.customer_name && c.customer_name.toLowerCase().includes(q)) ||
        (c.company_name && c.company_name.toLowerCase().includes(q)) ||
        (c.phone && c.phone.toLowerCase().includes(q))
    ).slice(0, 15);

    renderCustomerDropdown(matches, dropdown);
}

function renderCustomerDropdown(list, dropdown) {
    if (list.length === 0) {
        dropdown.innerHTML = '<div class="p-3 text-center text-muted small">No customers found matching search</div>';
        dropdown.classList.add('show');
        return;
    }

    let html = '';
    list.forEach(c => {
        const comp = c.company_name ? ` (${escapeHtml(c.company_name)})` : '';
        const ph = c.phone ? ` - ${escapeHtml(c.phone)}` : '';
        html += `
            <div class="search-dropdown-item" onclick="selectCustomer(${c.id})">
                <div>
                    <strong class="text-dark d-block">${escapeHtml(c.customer_name)}${comp}</strong>
                    <small class="text-muted">${ph}</small>
                </div>
                <div class="text-end">
                    <span class="badge bg-light text-dark border">Select</span>
                </div>
            </div>
        `;
    });

    dropdown.innerHTML = html;
    dropdown.classList.add('show');
}

function selectCustomer(id) {
    const c = rawCustomers.find(item => item.id == id);
    if (!c) return;

    document.getElementById('customerIdHidden').value = c.id;
    document.getElementById('dispCustName').textContent = c.customer_name;
    document.getElementById('dispCustDetails').textContent = (c.company_name ? c.company_name + ' | ' : '') + (c.phone ? 'Phone: ' + c.phone + ' | ' : '') + (c.address || '');

    document.getElementById('customerPickerWrap').style.display = 'none';
    document.getElementById('selectedCustomerBox').style.display = 'block';
    document.getElementById('customerDropdown').classList.remove('show');

    // Auto-focus product search
    document.getElementById('productSearchInput').focus();
}

function clearSelectedCustomer() {
    document.getElementById('customerIdHidden').value = '';
    document.getElementById('customerSearchInput').value = '';
    document.getElementById('selectedCustomerBox').style.display = 'none';
    document.getElementById('customerPickerWrap').style.display = 'block';
    document.getElementById('customerSearchInput').focus();
}

// ── PRODUCT SEARCH & AUTOCOMPLETE ──
function filterProducts(input) {
    const q = input.value.trim().toLowerCase();
    const dropdown = document.getElementById('productDropdown');

    if (!q) {
        renderProductDropdown(rawProducts.slice(0, 10), dropdown);
        return;
    }

    const matches = rawProducts.filter(p => 
        (p.name && p.name.toLowerCase().includes(q)) ||
        (p.code && p.code.toLowerCase().includes(q)) ||
        (p.type && p.type.toLowerCase().includes(q))
    ).slice(0, 15);

    renderProductDropdown(matches, dropdown);
}

function renderProductDropdown(list, dropdown) {
    if (list.length === 0) {
        dropdown.innerHTML = '<div class="p-3 text-center text-muted small">No products found matching search</div>';
        dropdown.classList.add('show');
        return;
    }

    let html = '';
    list.forEach(p => {
        const badge = p.type === 'raw_material' 
            ? `<span class="badge bg-warning text-dark me-1" style="font-size:10px;">RM</span>`
            : `<span class="badge bg-primary text-white me-1" style="font-size:10px;">FG</span>`;
        const stockBadge = p.stock > 0
            ? `<span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:10px;">Stock: ${p.stock}</span>`
            : `<span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size:10px;">0 Stock</span>`;

        html += `
            <div class="search-dropdown-item" onclick="selectProduct(${p.id}, '${p.type}')">
                <div>
                    <div>${badge} <strong class="text-dark">${escapeHtml(p.name)}</strong> <small class="text-muted">(${escapeHtml(p.code)})</small></div>
                    <small class="text-muted">Unit: ${escapeHtml(p.unit)} | Price: Rs. ${p.price.toFixed(2)}</small>
                </div>
                <div class="text-end">
                    ${stockBadge}
                    <div class="fw-bold text-primary small">Rs. ${p.price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                </div>
            </div>
        `;
    });

    dropdown.innerHTML = html;
    dropdown.classList.add('show');
}

function selectProduct(id, type) {
    const p = rawProducts.find(item => item.id == id && item.type == type);
    if (!p) return;

    document.getElementById('selectedProductId').value = p.id;
    document.getElementById('selectedProductType').value = p.type;
    document.getElementById('selectedProductName').value = p.name;
    document.getElementById('selectedProductUnit').value = p.unit;
    document.getElementById('productSearchInput').value = p.name + ' (' + p.code + ')';

    document.getElementById('inputPrice').value = p.price.toFixed(2);
    document.getElementById('inputQty').value = '1.00';

    const pill = document.getElementById('selectedProductPill');
    pill.style.display = 'block';
    pill.innerHTML = `<i class="fas fa-check-circle me-1"></i>Selected: [${p.type === 'raw_material' ? 'RM' : 'FG'}] ${escapeHtml(p.name)} | Price: Rs. ${p.price.toFixed(2)} | Stock: ${p.stock} ${escapeHtml(p.unit)}`;

    document.getElementById('productDropdown').classList.remove('show');
    document.getElementById('inputQty').focus();
    document.getElementById('inputQty').select();
}

function clearProductSelection() {
    document.getElementById('selectedProductId').value = '';
    document.getElementById('selectedProductType').value = '';
    document.getElementById('selectedProductName').value = '';
    document.getElementById('selectedProductUnit').value = '';
    document.getElementById('productSearchInput').value = '';
    document.getElementById('inputPrice').value = '0.00';
    document.getElementById('inputQty').value = '1.00';
    document.getElementById('selectedProductPill').style.display = 'none';
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('#customerPickerWrap')) {
        const cDrop = document.getElementById('customerDropdown');
        if (cDrop) cDrop.classList.remove('show');
    }
    if (!e.target.closest('#productSearchWrap')) {
        const pDrop = document.getElementById('productDropdown');
        if (pDrop) pDrop.classList.remove('show');
    }
});

// Keyboard Enter shortcuts
document.getElementById('productSearchInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const first = document.querySelector('#productDropdown .search-dropdown-item');
        if (first) first.click();
    }
});
document.getElementById('inputQty').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addItemToQuotation();
    }
});
document.getElementById('inputPrice').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addItemToQuotation();
    }
});

function addItemToQuotation() {
    const pid = document.getElementById('selectedProductId').value;
    const ptype = document.getElementById('selectedProductType').value;
    const pname = document.getElementById('selectedProductName').value;
    const punit = document.getElementById('selectedProductUnit').value || 'unit';
    const qty = parseFloat(document.getElementById('inputQty').value) || 0;
    const price = parseFloat(document.getElementById('inputPrice').value) || 0;

    if (!pid || !pname) {
        alert('Please search and select a product first!');
        document.getElementById('productSearchInput').focus();
        return;
    }

    if (qty <= 0) {
        alert('Quantity must be greater than 0!');
        document.getElementById('inputQty').focus();
        return;
    }

    const existingIdx = quotationCart.findIndex(i => i.product_id == pid && i.product_type == ptype);
    if (existingIdx >= 0) {
        quotationCart[existingIdx].quantity += qty;
        quotationCart[existingIdx].unit_price = price;
        quotationCart[existingIdx].total = quotationCart[existingIdx].quantity * price;
    } else {
        quotationCart.push({
            product_id: pid,
            product_type: ptype,
            product_name: pname,
            unit: punit,
            quantity: qty,
            unit_price: price,
            total: qty * price
        });
    }

    // Reset product picker
    clearProductSelection();
    renderQuotationCart();
    document.getElementById('productSearchInput').focus();
}

function removeQuotationItem(idx) {
    quotationCart.splice(idx, 1);
    renderQuotationCart();
}

function updateItemQty(idx, newQty) {
    newQty = parseFloat(newQty) || 0;
    if (newQty <= 0) {
        removeQuotationItem(idx);
        return;
    }
    quotationCart[idx].quantity = newQty;
    quotationCart[idx].total = newQty * quotationCart[idx].unit_price;
    renderQuotationCart();
}

function updateItemPrice(idx, newPrice) {
    newPrice = parseFloat(newPrice) || 0;
    quotationCart[idx].unit_price = newPrice;
    quotationCart[idx].total = quotationCart[idx].quantity * newPrice;
    renderQuotationCart();
}

function renderQuotationCart() {
    const tbody = document.getElementById('itemsTableBody');
    const badge = document.getElementById('itemCountBadge');

    if (quotationCart.length === 0) {
        tbody.innerHTML = `<tr id="emptyRow">
            <td colspan="7" class="text-center py-4 text-muted">
                <i class="fas fa-box-open fa-2x mb-2 d-block text-secondary"></i>
                No items added yet. Search and select products above to build your quotation.
            </td>
        </tr>`;
        badge.textContent = '0 Items';
        recalculateTotals();
        return;
    }

    badge.textContent = quotationCart.length + (quotationCart.length === 1 ? ' Item' : ' Items');
    let html = '';

    quotationCart.forEach((item, idx) => {
        html += `<tr>
            <td class="text-center">${idx + 1}</td>
            <td>
                <strong>${escapeHtml(item.product_name)}</strong>
                <span class="badge bg-light text-dark border ms-1">${item.product_type === 'raw_material' ? 'RM' : 'FG'}</span>
                <input type="hidden" name="items[${idx}][product_id]" value="${item.product_id}">
                <input type="hidden" name="items[${idx}][product_type]" value="${item.product_type}">
                <input type="hidden" name="items[${idx}][product_name]" value="${escapeHtml(item.product_name)}">
            </td>
            <td class="text-center">${escapeHtml(item.unit)}</td>
            <td class="text-center">
                <input type="number" step="0.01" class="form-control form-control-sm text-center" 
                       name="items[${idx}][quantity]" value="${item.quantity.toFixed(2)}" 
                       onchange="updateItemQty(${idx}, this.value)">
            </td>
            <td class="text-end">
                <input type="number" step="0.01" class="form-control form-control-sm text-end" 
                       name="items[${idx}][unit_price]" value="${item.unit_price.toFixed(2)}" 
                       onchange="updateItemPrice(${idx}, this.value)">
            </td>
            <td class="text-end fw-bold">
                Rs. ${item.total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="removeQuotationItem(${idx})">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        </tr>`;
    });

    tbody.innerHTML = html;
    recalculateTotals();
}

// ── TAX & TOTAL CALCULATIONS (TWO-WAY EDITABLE) ──
function handleTaxToggle(val) {
    const isTax = (val === 'tax');
    document.getElementById('taxFieldsWrap').style.display = isTax ? 'block' : 'none';
    const badge = document.getElementById('taxBadgeIndicator');
    if (isTax) {
        badge.className = 'badge bg-primary';
        badge.textContent = 'Tax Invoice';
    } else {
        badge.className = 'badge bg-secondary';
        badge.textContent = 'Non-Tax';
    }
    recalculateTotals();
}

function onTaxRateInput(rateVal) {
    lastEditedTaxField = 'rate';
    const rate = parseFloat(rateVal) || 0;
    const subtotal = getCartSubtotal();
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const taxableBase = Math.max(0, subtotal - discount);

    const taxAmount = (taxableBase > 0) ? Math.round((taxableBase * (rate / 100)) * 100) / 100 : 0;
    document.getElementById('taxAmountInput').value = taxAmount.toFixed(2);

    updateGrandTotal(taxableBase, taxAmount);
}

function onTaxAmountInput(amountVal) {
    lastEditedTaxField = 'amount';
    const taxAmount = parseFloat(amountVal) || 0;
    const subtotal = getCartSubtotal();
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const taxableBase = Math.max(0, subtotal - discount);

    let taxRate = 0;
    if (taxableBase > 0) {
        taxRate = Math.round(((taxAmount / taxableBase) * 100) * 100) / 100;
    }
    document.getElementById('taxRateInput').value = taxRate.toFixed(2);

    updateGrandTotal(taxableBase, taxAmount);
}

function onDiscountChanged() {
    recalculateTotals();
}

function getCartSubtotal() {
    let subtotal = 0;
    quotationCart.forEach(i => { subtotal += i.total; });
    return subtotal;
}

function recalculateTotals() {
    const subtotal = getCartSubtotal();
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const taxableBase = Math.max(0, subtotal - discount);

    const isTax = document.querySelector('input[name="invoice_type"]:checked')?.value === 'tax';
    let taxAmount = 0;

    if (isTax) {
        if (lastEditedTaxField === 'amount') {
            taxAmount = parseFloat(document.getElementById('taxAmountInput').value) || 0;
            const taxRate = (taxableBase > 0) ? Math.round(((taxAmount / taxableBase) * 100) * 100) / 100 : 0;
            document.getElementById('taxRateInput').value = taxRate.toFixed(2);
        } else {
            const taxRate = parseFloat(document.getElementById('taxRateInput').value) || 0;
            taxAmount = (taxableBase > 0) ? Math.round((taxableBase * (taxRate / 100)) * 100) / 100 : 0;
            document.getElementById('taxAmountInput').value = taxAmount.toFixed(2);
        }
    } else {
        document.getElementById('taxAmountInput').value = '0.00';
    }

    document.getElementById('subtotalDisplay').textContent = 'Rs. ' + subtotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('subtotalHidden').value = subtotal.toFixed(2);

    updateGrandTotal(taxableBase, taxAmount);
}

function updateGrandTotal(taxableBase, taxAmount) {
    const isTax = document.querySelector('input[name="invoice_type"]:checked')?.value === 'tax';
    const effectiveTax = isTax ? taxAmount : 0;
    const shipping = parseFloat(document.getElementById('shippingInput').value) || 0;
    const grandTotal = Math.max(0, taxableBase + effectiveTax + shipping);

    document.getElementById('grandTotalDisplay').textContent = 'Rs. ' + grandTotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('grandTotalHidden').value = grandTotal.toFixed(2);
}
</script>

<?php include '../includes/footer.php'; ?>