<?php
/**
 * Modern POS & New Sale Order
 * Manufacturing & Import ERP
 */

require_once '../includes/database.php';
requireLogin();

$pageTitle = 'POS / New Sale Order';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

// ── Hold Bill Retrieval ──
$holdSale = null;
$holdItems = [];
$holdId = isset($_GET['hold_id']) ? (int)$_GET['hold_id'] : 0;
if ($holdId > 0) {
    $holdSale = getRow("SELECT * FROM sales WHERE id = ? AND status = 'hold'", 'i', [$holdId]);
    if ($holdSale) {
        $holdItems = getRows("SELECT si.*, 
            COALESCE(rm.material_code, fg.product_code) AS product_code,
            COALESCE(rm.material_name, fg.product_name) AS product_name,
            COALESCE(rm.selling_price, fg.selling_price) AS selling_price,
            COALESCE(rm.unit, fg.unit) AS unit,
            COALESCE(rm.current_stock, fg.current_stock) AS current_stock
            FROM sale_items si
            LEFT JOIN finished_goods fg ON si.product_id = fg.id AND (si.product_type = 'finished_good' OR si.product_type IS NULL)
            LEFT JOIN raw_materials rm ON si.product_id = rm.id AND si.product_type = 'raw_material'
            WHERE si.sale_id = ?", 'i', [$holdId]);
    }
}

// ── Count of Active Held Bills ──
$holdCount = (getRow("SELECT COUNT(*) as t FROM sales WHERE status = 'hold'")['t'] ?? 0);

// ── Handle Form Submission ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'complete';
    $customer_type = $_POST['customer_type'] ?? 'credit';
    $customer_id = $customer_type === 'credit' ? (int)$_POST['customer_id'] : null;
    $walkin_name = $customer_type === 'walkin' ? trim($_POST['walkin_name']) : '';
    $walkin_phone = $customer_type === 'walkin' ? trim($_POST['walkin_phone']) : '';
    $sale_date = !empty($_POST['sale_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['sale_date']) ? $_POST['sale_date'] : date('Y-m-d');
    $total_amount = (float)($_POST['total_amount'] ?? 0);
    $discount = (float)($_POST['discount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? ($customer_type === 'credit' ? 'credit' : 'cash');
    $bank_id = ($payment_method === 'bank') ? (int)($_POST['bank_id'] ?? 0) : null;
    $bank_name = null;
    if ($bank_id > 0) {
        $bRow = getRow("SELECT bank_name, account_number FROM bank_accounts WHERE id = ?", 'i', [$bank_id]);
        if ($bRow) {
            $bank_name = $bRow['bank_name'] . ' (' . $bRow['account_number'] . ')';
        }
    }
    $products = isset($_POST['products']) ? $_POST['products'] : [];
    $hold_id = (int)($_POST['hold_id'] ?? 0);

    // Extract previous balance
    $previous_balance = isset($_POST['previous_balance']) ? (float)$_POST['previous_balance'] : 0;
    if ($customer_type === 'credit' && $customer_id > 0 && empty($previous_balance)) {
        $cRow = getRow("SELECT opening_balance, opening_balance_type FROM customers WHERE id = ?", 'i', [$customer_id]);
        if ($cRow) {
            $sData = getRow("SELECT COALESCE(SUM(total_amount), 0) as total_sales,
                                    COALESCE(SUM(paid_amount), 0) as total_paid
                             FROM sales WHERE customer_id = ? AND status = 'completed' AND id != ?", 'ii', [$customer_id, $hold_id]);
            $rData = getRow("SELECT COALESCE(SUM(amount), 0) as total_receipts FROM customer_receipts WHERE customer_id = ?", 'i', [$customer_id]);
            $op = (float)$cRow['opening_balance'];
            if (($cRow['opening_balance_type'] ?? 'receivable') === 'payable') {
                $op = -$op;
            }
            $previous_balance = $op + (float)($sData['total_sales'] ?? 0) - (float)($sData['total_paid'] ?? 0) - (float)($rData['total_receipts'] ?? 0);
        }
    }

    // Filter valid products (must have product_id, qty > 0)
    $validProducts = [];
    foreach ($products as $p) {
        $pid = (int)($p['product_id'] ?? 0);
        $ptype = ($p['product_type'] ?? 'finished_good') === 'raw_material' ? 'raw_material' : 'finished_good';
        $qty = (float)($p['quantity'] ?? 0);
        $price = (float)($p['unit_price'] ?? 0);
        if ($pid > 0 && $qty > 0) {
            $validProducts[] = [
                'product_id' => $pid,
                'product_type' => $ptype,
                'quantity' => $qty,
                'unit_price' => $price,
                'total' => round($qty * $price, 2)
            ];
        }
    }

    if ($customer_type === 'credit' && $customer_id <= 0) {
        $message = 'Please select a registered customer for credit sale!';
        $messageType = 'danger';
    } elseif ($customer_type === 'walkin' && empty($walkin_name)) {
        $message = 'Please enter walk-in customer name!';
        $messageType = 'danger';
    } elseif (empty($sale_date)) {
        $message = 'Please select a valid sale date!';
        $messageType = 'danger';
    } elseif (empty($validProducts)) {
        $message = 'Please add at least one product with valid quantity!';
        $messageType = 'danger';
    } else {
        $currentDateTime = getCurrentDateTime();
        $paid_amount = 0;
        $payment_status = 'unpaid';
        $balance = $final_amount;

        if ($payment_method === 'cash' || $payment_method === 'bank') {
            $paid_amount = $final_amount;
            $balance = 0;
            $payment_status = 'paid';
        }

        if ($hold_id > 0) {
            // Update existing held bill
            modifyData("UPDATE sales SET customer_id=?, customer_type=?, walkin_name=?, walkin_phone=?, sale_date=?, total_amount=?, discount=?, final_amount=?, paid_amount=?, balance=?, previous_balance=?, payment_status=?, payment_method=?, bank_id=?, bank_name=?, date_time=? WHERE id=?",
                'isssssddddddssissi', [$customer_id, $customer_type, $walkin_name, $walkin_phone, $sale_date, $total_amount, $discount, $final_amount, $paid_amount, $balance, $previous_balance, $payment_status, $payment_method, $bank_id, $bank_name, $currentDateTime, $hold_id]);
            
            modifyData("DELETE FROM sale_items WHERE sale_id=?", 'i', [$hold_id]);

            if ($action === 'complete') {
                modifyData("UPDATE sales SET status='completed', hold_reason=NULL WHERE id=?", 'i', [$hold_id]);
                
                foreach ($validProducts as $vp) {
                    insertData("INSERT INTO sale_items (sale_id, product_id, product_type, quantity, unit_price, total, date_time) VALUES (?,?,?,?,?,?,?)",
                        'iisddds', [$hold_id, $vp['product_id'], $vp['product_type'], $vp['quantity'], $vp['unit_price'], $vp['total'], $currentDateTime]);
                    if ($vp['product_type'] === 'raw_material') {
                        modifyData("UPDATE raw_materials SET current_stock = current_stock - ? WHERE id = ?", 'di', [$vp['quantity'], $vp['product_id']]);
                    } else {
                        modifyData("UPDATE finished_goods SET current_stock = current_stock - ? WHERE id = ?", 'di', [$vp['quantity'], $vp['product_id']]);
                    }
                }

                // If customer is credit and has remaining balance
                if ($customer_type === 'credit' && $customer_id > 0 && $balance > 0) {
                    modifyData("UPDATE customers SET current_balance = current_balance + ? WHERE id = ?", 'di', [$balance, $customer_id]);
                }

                // Cash/Bank entry in general ledger
                if ($paid_amount > 0 && ($payment_method === 'cash' || $payment_method === 'bank')) {
                    $accType = ($payment_method === 'cash') ? 'Cash' : 'Bank';
                    if ($payment_method === 'bank' && $bank_id > 0) {
                        updateBankBalance($bank_id, $paid_amount, 'in');
                    }
                    $lastEntry = getRow("SELECT balance FROM accounts WHERE account_type = ? ORDER BY id DESC LIMIT 1", 's', [$accType]);
                    $lastBalance = $lastEntry ? (float)$lastEntry['balance'] : 0;
                    $custName = ($customer_type === 'walkin') ? $walkin_name : (getRow("SELECT customer_name FROM customers WHERE id = ?", 'i', [$customer_id])['customer_name'] ?? 'Customer');
                    $saleNoRow = getRow("SELECT sale_no FROM sales WHERE id = ?", 'i', [$hold_id]);
                    $desc = 'Counter sale payment from ' . $custName . ' (' . ($saleNoRow['sale_no'] ?? '') . ')' . ($bank_name ? " [$bank_name]" : "");
                    insertData(
                        "INSERT INTO accounts (account_date, account_type, reference_type, reference_id, description, debit, credit, balance, date_time) VALUES (?, ?, 'Sale', ?, ?, ?, 0, ?, ?)",
                        'ssisdds', [$sale_date, $accType, $hold_id, $desc, $paid_amount, $lastBalance + $paid_amount, $currentDateTime]
                    );
                }

                setFlash('Sale completed successfully!', 'success');
                header('Location: sale_print.php?id=' . $hold_id);
                exit;
            } else {
                // Keep as hold
                foreach ($validProducts as $vp) {
                    insertData("INSERT INTO sale_items (sale_id, product_id, product_type, quantity, unit_price, total, date_time) VALUES (?,?,?,?,?,?,?)",
                        'iisddds', [$hold_id, $vp['product_id'], $vp['product_type'], $vp['quantity'], $vp['unit_price'], $vp['total'], $currentDateTime]);
                }
                setFlash('Bill updated and held successfully!', 'success');
                header('Location: view_hold_bills.php');
                exit;
            }
        } else {
            // New Sale
            $sale_no = generateCode('SL');
            $newStatus = ($action === 'hold') ? 'hold' : 'completed';
            
            $result = insertData("INSERT INTO sales (sale_no, customer_id, customer_type, walkin_name, walkin_phone, sale_date, total_amount, discount, final_amount, paid_amount, balance, previous_balance, payment_status, payment_method, bank_id, bank_name, status, date_time) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                'sissssddddddssisss', [$sale_no, $customer_id, $customer_type, $walkin_name, $walkin_phone, $sale_date, $total_amount, $discount, $final_amount, $paid_amount, $balance, $previous_balance, $payment_status, $payment_method, $bank_id, $bank_name, $newStatus, $currentDateTime]);

            if ($result !== false) {
                $saleId = $result;

                foreach ($validProducts as $vp) {
                    insertData("INSERT INTO sale_items (sale_id, product_id, product_type, quantity, unit_price, total, date_time) VALUES (?,?,?,?,?,?,?)",
                        'iisddds', [$saleId, $vp['product_id'], $vp['product_type'], $vp['quantity'], $vp['unit_price'], $vp['total'], $currentDateTime]);
                    
                    if ($newStatus === 'completed') {
                        if ($vp['product_type'] === 'raw_material') {
                            modifyData("UPDATE raw_materials SET current_stock = current_stock - ? WHERE id = ?", 'di', [$vp['quantity'], $vp['product_id']]);
                        } else {
                            modifyData("UPDATE finished_goods SET current_stock = current_stock - ? WHERE id = ?", 'di', [$vp['quantity'], $vp['product_id']]);
                        }
                    }
                }

                if ($newStatus === 'completed') {
                    if ($customer_type === 'credit' && $customer_id > 0 && $balance > 0) {
                        modifyData("UPDATE customers SET current_balance = current_balance + ? WHERE id = ?", 'di', [$balance, $customer_id]);
                    }

                    if ($paid_amount > 0 && ($payment_method === 'cash' || $payment_method === 'bank')) {
                        $accType = ($payment_method === 'cash') ? 'Cash' : 'Bank';
                        if ($payment_method === 'bank' && $bank_id > 0) {
                            updateBankBalance($bank_id, $paid_amount, 'in');
                        }
                        $lastEntry = getRow("SELECT balance FROM accounts WHERE account_type = ? ORDER BY id DESC LIMIT 1", 's', [$accType]);
                        $lastBalance = $lastEntry ? (float)$lastEntry['balance'] : 0;
                        $custName = ($customer_type === 'walkin') ? $walkin_name : (getRow("SELECT customer_name FROM customers WHERE id = ?", 'i', [$customer_id])['customer_name'] ?? 'Customer');
                        $desc = 'Counter sale payment from ' . $custName . ' (' . $sale_no . ')' . ($bank_name ? " [$bank_name]" : "");
                        insertData(
                            "INSERT INTO accounts (account_date, account_type, reference_type, reference_id, description, debit, credit, balance, date_time) VALUES (?, ?, 'Sale', ?, ?, ?, 0, ?, ?)",
                            'ssisdds', [$sale_date, $accType, $saleId, $desc, $paid_amount, $lastBalance + $paid_amount, $currentDateTime]
                        );
                    }

                    setFlash('Sale completed successfully! Invoice #' . $sale_no, 'success');
                    header('Location: sale_print.php?id=' . $saleId);
                    exit;
                } else {
                    setFlash('Bill #' . $sale_no . ' placed on hold!', 'warning');
                    header('Location: view_hold_bills.php');
                    exit;
                }
            } else {
                global $conn;
                $message = 'Error creating sale: ' . ($conn->error ?? 'Database error');
                $messageType = 'danger';
            }
        }
    }
}

// ── Fetch Master Data ──
$customers = getRows("SELECT id, customer_name, company_name, phone, current_balance FROM customers WHERE status='active' ORDER BY customer_name");
$finished_goods = getRows("SELECT id, product_code, product_name, category, unit, selling_price, current_stock, 'finished_good' as item_type FROM finished_goods WHERE status='active' ORDER BY product_name");
$raw_materials = getRows("SELECT id, material_code as product_code, material_name as product_name, category, unit, COALESCE(NULLIF(selling_price, 0), purchase_price_pkr, 0) as selling_price, current_stock, 'raw_material' as item_type FROM raw_materials WHERE status='active' ORDER BY material_name");
$products = array_merge($finished_goods, $raw_materials);
$bankAccounts = getActiveBankAccounts();

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
:root {
    --pos-primary: #1e293b;
    --pos-accent: #2563eb;
    --pos-accent-hover: #1d4ed8;
    --pos-success: #10b981;
    --pos-warning: #f59e0b;
    --pos-danger: #ef4444;
    --pos-bg: #f8fafc;
    --pos-card-border: #e2e8f0;
}

body {
    background-color: var(--pos-bg);
}

.pos-container {
    max-width: 100%;
    margin-bottom: 30px;
}

/* Card Styling */
.pos-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid var(--pos-card-border);
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.05);
    margin-bottom: 1rem;
    position: relative;
    overflow: visible; /* Prevents clipping autocomplete suggestion dropdowns */
}

.pos-card-header {
    background: #ffffff;
    border-bottom: 1px solid var(--pos-card-border);
    border-top-left-radius: 11px;
    border-top-right-radius: 11px;
    padding: 0.85rem 1.25rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pos-card-body {
    padding: 1.25rem;
    position: relative;
    overflow: visible;
}

/* Specific layered stacking context so dropdowns float over lower cards & table */
.pos-card-customer {
    position: relative;
    z-index: 60;
}

.pos-card-product {
    position: relative;
    z-index: 50;
}

/* Segmented Toggle Pills */
.pos-pill-toggle {
    display: inline-flex;
    background: #f1f5f9;
    padding: 3px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.pos-pill-toggle input[type="radio"] {
    display: none;
}

.pos-pill-toggle label {
    padding: 6px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    color: #64748b;
    transition: all 0.2s ease;
    margin: 0;
    user-select: none;
}

.pos-pill-toggle input[type="radio"]:checked + label {
    background: #ffffff;
    color: var(--pos-accent);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* Search Dropdowns (Position Relative Container to never break or drift) */
.pos-search-wrap {
    position: relative;
    z-index: 100;
}

.pos-search-input {
    border-radius: 8px;
    padding-left: 2.5rem;
    border: 1.5px solid #cbd5e1;
    font-size: 14px;
    height: 42px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.pos-search-input:focus {
    border-color: var(--pos-accent);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

.pos-search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
}

.pos-dropdown-menu {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: #ffffff;
    border: 1.5px solid #94a3b8;
    border-radius: 8px;
    box-shadow: 0 14px 30px rgba(0, 0, 0, 0.16), 0 4px 10px rgba(0, 0, 0, 0.08);
    max-height: 290px;
    overflow-y: auto;
    z-index: 9999;
    display: none;
}

.pos-dropdown-menu.show {
    display: block;
}

.pos-dropdown-item {
    padding: 10px 14px;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: background-color 0.15s ease;
}

.pos-dropdown-item:last-child {
    border-bottom: none;
}

.pos-dropdown-item:hover,
.pos-dropdown-item.active {
    background-color: #eff6ff;
}

/* Items Table */
.pos-table-wrap {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid var(--pos-card-border);
    overflow: hidden;
    position: relative;
    z-index: 10;
}

.pos-table {
    margin-bottom: 0;
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.pos-table thead th {
    background: #f8fafc;
    color: #475569;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 12px 14px;
    border-bottom: 1px solid var(--pos-card-border);
}

.pos-table tbody td {
    padding: 12px 14px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}

.pos-table tbody tr:hover {
    background-color: #fafbfc;
}

.pos-table .qty-btn-group {
    display: inline-flex;
    align-items: center;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    overflow: hidden;
    background: #ffffff;
}

.pos-table .qty-btn {
    border: none;
    background: #f8fafc;
    color: #475569;
    width: 28px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.15s;
}

.pos-table .qty-btn:hover {
    background: #e2e8f0;
}

.pos-table .qty-input {
    width: 55px;
    height: 32px;
    border: none;
    text-align: center;
    font-weight: 600;
    font-size: 14px;
    outline: none;
    padding: 0 4px;
}

.pos-table .price-input {
    width: 110px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 4px 8px;
    font-weight: 600;
    text-align: right;
    font-size: 14px;
}

/* Empty State */
.pos-empty-cart {
    text-align: center;
    padding: 3rem 1.5rem;
    color: #94a3b8;
}

.pos-empty-cart i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #cbd5e1;
}

/* Right Checkout Card */
.checkout-panel {
    position: sticky;
    top: 20px;
}

.net-payable-hero {
    background: #ffffff;
    border: 2px solid #2563eb;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    text-align: center;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.08);
    margin-bottom: 1.25rem;
    position: relative;
    overflow: hidden;
}

.net-payable-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: #2563eb;
}

.net-payable-hero .label {
    font-size: 13px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #475569;
    margin-bottom: 6px;
    font-weight: 700;
}

.net-payable-hero .amount {
    font-size: 2.3rem;
    font-weight: 800;
    color: #1d4ed8;
    line-height: 1.1;
    letter-spacing: -0.02em;
}

.net-payable-hero .items-count {
    color: #64748b;
    font-size: 12px;
    font-weight: 500;
    margin-top: 4px;
}

/* Payment Method Cards */
.pay-method-group {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 1rem;
}

.pay-method-card {
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #ffffff;
    user-select: none;
}

.pay-method-card input[type="radio"] {
    display: none;
}

.pay-method-card:hover {
    border-color: #cbd5e1;
}

.pay-method-card.active {
    border-color: var(--pos-accent);
    background: #eff6ff;
    color: var(--pos-accent);
    font-weight: 600;
}

.pay-method-card i {
    font-size: 16px;
    display: block;
    margin-bottom: 4px;
}

.pay-method-card span {
    font-size: 12px;
}

/* Hotkey Hints */
.hotkey-badge {
    font-size: 10px;
    background: rgba(0,0,0,0.06);
    border: 1px solid rgba(0,0,0,0.1);
    padding: 2px 6px;
    border-radius: 4px;
    font-family: monospace;
    font-weight: 600;
    color: #475569;
}

/* Selected Customer Card */
.selected-customer-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
</style>

<div class="container-fluid px-3 pos-container">
    <!-- Top Action Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center my-3 gap-2">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="fas fa-cash-register me-2 text-primary"></i>Point of Sale / New Sale
            </h4>
            <small class="text-muted">Create POS bills, invoice sales, and manage held orders</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if ($holdId > 0): ?>
                <span class="badge bg-warning text-dark px-3 py-2 fs-6">
                    <i class="fas fa-clock me-1"></i> Resuming Hold Bill #<?php echo htmlspecialchars($holdSale['sale_no']); ?>
                </span>
            <?php endif; ?>
            <a href="view_hold_bills.php" class="btn btn-outline-warning position-relative btn-sm px-3">
                <i class="fas fa-pause-circle me-1"></i> Hold Bills
                <?php if ($holdCount > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-1"><?php echo $holdCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="sales.php" class="btn btn-outline-secondary btn-sm px-3">
                <i class="fas fa-list me-1"></i> View All Sales
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show mb-3 shadow-sm" role="alert">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="saleForm" autocomplete="off">
        <input type="hidden" name="hold_id" value="<?php echo $holdId; ?>">
        <input type="hidden" name="action" id="formAction" value="complete">
        <input type="hidden" name="total_amount" id="hidBillAmount" value="0">
        <input type="hidden" name="final_amount" id="hidFinalAmount" value="0">

        <div class="row g-3">
            <!-- ── LEFT MAIN WORKSPACE (8 Columns) ── -->
            <div class="col-lg-8">
                <!-- Customer Details Card -->
                <div class="pos-card pos-card-customer">
                    <div class="pos-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <span><i class="fas fa-user-circle me-2 text-primary"></i>Customer Information</span>
                            <!-- Pill Toggle: Credit vs Walk-in -->
                            <div class="pos-pill-toggle">
                                <input type="radio" name="customer_type" id="typeCredit" value="credit" <?php echo (!$holdSale || $holdSale['customer_type'] === 'credit') ? 'checked' : ''; ?> onchange="toggleCustomerType()">
                                <label for="typeCredit"><i class="fas fa-address-book me-1"></i>Registered (Credit)</label>

                                <input type="radio" name="customer_type" id="typeWalkin" value="walkin" <?php echo ($holdSale && $holdSale['customer_type'] === 'walkin') ? 'checked' : ''; ?> onchange="toggleCustomerType()">
                                <label for="typeWalkin"><i class="fas fa-walking me-1"></i>Walk-in (Cash)</label>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small">Sale Date:</span>
                            <input type="date" class="form-control form-control-sm" style="width: 140px;" id="sale_date" name="sale_date" value="<?php echo $holdSale ? $holdSale['sale_date'] : date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="pos-card-body">
                        <!-- Credit Customer Select Section -->
                        <div id="creditCustomerSection">
                            <div id="customerPickerWrap">
                                <div class="pos-search-wrap">
                                    <i class="fas fa-search pos-search-icon"></i>
                                    <input type="text" class="form-control pos-search-input" id="customerSearchInput" placeholder="Search customer by name or company..." onkeyup="searchCustomer(this)" onfocus="searchCustomer(this)">
                                    <input type="hidden" name="customer_id" id="customer_id" value="<?php echo $holdSale ? $holdSale['customer_id'] : ''; ?>">
                                    <div class="pos-dropdown-menu" id="customerDropdown"></div>
                                </div>
                            </div>

                            <div id="selectedCustomerDisplay" class="selected-customer-box mt-2" style="display:none;">
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark" id="dispCustomerName">-</h6>
                                    <small class="text-muted" id="dispCustomerCompany">-</small>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="text-end">
                                        <div class="small text-muted">Current Ledger Balance</div>
                                        <span class="badge fs-6" id="dispCustomerBalanceBadge">Rs. 0.00</span>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearSelectedCustomer()" title="Change customer">
                                        <i class="fas fa-times"></i> Change
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Walk-in Customer Form -->
                        <div id="walkinCustomerSection" style="display:none;">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label small text-muted mb-1">Customer Name *</label>
                                    <input type="text" class="form-control form-control-sm" id="walkin_name" name="walkin_name" placeholder="Walk-in Client Name" value="<?php echo $holdSale ? htmlspecialchars($holdSale['walkin_name']) : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted mb-1">Contact Phone (Optional)</label>
                                    <input type="text" class="form-control form-control-sm" id="walkin_phone" name="walkin_phone" placeholder="03xx-xxxxxxx" value="<?php echo $holdSale ? htmlspecialchars($holdSale['walkin_phone']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Quick Search & Add Bar -->
                <div class="pos-card pos-card-product">
                    <div class="pos-card-header d-flex flex-wrap justify-content-between align-items-center py-2 gap-2">
                        <span><i class="fas fa-boxes me-2 text-primary"></i>Product & Raw Material Selection</span>
                        <div class="pos-pill-toggle">
                            <input type="radio" name="item_filter_type" id="filterAll" value="all" checked onchange="setItemFilter(this.value)">
                            <label for="filterAll">All Items</label>

                            <input type="radio" name="item_filter_type" id="filterFG" value="finished_good" onchange="setItemFilter(this.value)">
                            <label for="filterFG"><i class="fas fa-box me-1"></i>Finished Goods</label>

                            <input type="radio" name="item_filter_type" id="filterRM" value="raw_material" onchange="setItemFilter(this.value)">
                            <label for="filterRM"><i class="fas fa-cubes me-1"></i>Raw Materials</label>
                        </div>
                    </div>
                    <div class="pos-card-body p-3">
                        <div class="pos-search-wrap">
                            <i class="fas fa-search pos-search-icon fs-5"></i>
                            <input type="text" class="form-control pos-search-input" id="itemSearchInput" placeholder="Scan barcode or search products / raw materials..." onkeyup="filterProducts(this)" onfocus="filterProducts(this)">
                            <div class="pos-dropdown-menu" id="productDropdown"></div>
                        </div>
                    </div>
                </div>

                <!-- Cart / Line Items Table -->
                <div class="pos-table-wrap shadow-sm">
                    <table class="table pos-table" id="cartTable">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Product Details</th>
                                <th style="width: 140px;" class="text-end">Unit Price</th>
                                <th style="width: 140px;" class="text-center">Quantity</th>
                                <th style="width: 150px;" class="text-end">Subtotal (PKR)</th>
                                <th style="width: 60px;" class="text-center"><i class="fas fa-cog"></i></th>
                            </tr>
                        </thead>
                        <tbody id="cartItemsTbody">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>

                    <div id="emptyCartDisplay" class="pos-empty-cart">
                        <i class="fas fa-cart-arrow-down"></i>
                        <h6 class="fw-bold mb-1">Your sale cart is empty</h6>
                        <p class="small text-muted mb-0">Search products in the search bar above</p>
                    </div>
                </div>
            </div>

            <!-- ── RIGHT CHECKOUT & SUMMARY PANEL (4 Columns) ── -->
            <div class="col-lg-4">
                <div class="checkout-panel">
                    <!-- Net Payable Hero Card -->
                    <div class="net-payable-hero">
                        <div class="label"><i class="fas fa-coins text-primary me-1"></i> Net Payable Amount</div>
                        <div class="amount" id="heroFinalDisplay">Rs. 0.00</div>
                        <div class="items-count" id="heroItemsCountDisplay">0 items in bill</div>
                    </div>

                    <!-- Order Summary Card -->
                    <div class="pos-card">
                        <div class="pos-card-header">
                            <span><i class="fas fa-calculator me-2 text-primary"></i>Bill Breakdown</span>
                        </div>

                        <div class="pos-card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Total Cart Items:</span>
                                <span class="fw-bold" id="summaryTotalItems">0</span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Bill Amount:</span>
                                <span class="fw-bold text-dark fs-6" id="summaryBillAmount">Rs. 0.00</span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2" id="summaryPrevBalanceRow" style="display:none;">
                                <span class="text-muted">Previous Balance:</span>
                                <div>
                                    <span class="fw-bold" id="summaryPrevBalanceAmount">Rs. 0.00</span>
                                    <small id="summaryPrevBalanceType" class="text-muted"></small>
                                </div>
                                <input type="hidden" id="prevAmountVal" name="previous_balance" value="0">
                                <input type="hidden" id="prevBalanceTypeVal" value="receivable">
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2" id="summaryTotalDueRow" style="display:none;">
                                <span class="text-muted fw-bold">Total Balance Due:</span>
                                <div>
                                    <span class="fw-bold fs-6 text-danger" id="summaryTotalDueAmount">Rs. 0.00</span>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <label for="discInput" class="text-muted mb-0">Discount (PKR):</label>
                                <div style="width: 130px;">
                                    <input type="number" step="0.01" class="form-control form-control-sm text-end fw-bold" id="discInput" name="discount" value="<?php echo $holdSale ? $holdSale['discount'] : '0'; ?>" oninput="recalcTotals()">
                                </div>
                            </div>

                            <hr class="my-2">

                            <!-- Payment Method Selector -->
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted mb-2">Payment Method</label>
                                <div class="pay-method-group" id="payMethodGroup">
                                    <label class="pay-method-card <?php echo (!$holdSale || $holdSale['payment_method'] === 'cash') ? 'active' : ''; ?>" id="payCardCash">
                                        <input type="radio" name="payment_method" value="cash" <?php echo (!$holdSale || $holdSale['payment_method'] === 'cash') ? 'checked' : ''; ?> onchange="handlePaymentMethodChange(this.value)">
                                        <i class="fas fa-money-bill-wave"></i>
                                        <span>Cash</span>
                                    </label>
                                    <label class="pay-method-card <?php echo ($holdSale && $holdSale['payment_method'] === 'bank') ? 'active' : ''; ?>" id="payCardBank">
                                        <input type="radio" name="payment_method" value="bank" <?php echo ($holdSale && $holdSale['payment_method'] === 'bank') ? 'checked' : ''; ?> onchange="handlePaymentMethodChange(this.value)">
                                        <i class="fas fa-university"></i>
                                        <span>Bank</span>
                                    </label>
                                    <label class="pay-method-card <?php echo ($holdSale && $holdSale['payment_method'] === 'credit') ? 'active' : ''; ?>" id="payCardCredit">
                                        <input type="radio" name="payment_method" value="credit" <?php echo ($holdSale && $holdSale['payment_method'] === 'credit') ? 'checked' : ''; ?> onchange="handlePaymentMethodChange(this.value)">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                        <span>Credit</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Bank Account Selector (for Bank payments) -->
                            <div id="bankAccountSection" class="p-2 mb-3 rounded bg-light border" style="display: <?php echo ($holdSale && $holdSale['payment_method'] === 'bank') ? 'block' : 'none'; ?>;">
                                <label for="saleBankSelect" class="form-label small fw-bold text-primary mb-1">
                                    <i class="fas fa-university me-1"></i> Deposit To Bank Account <span class="text-danger">*</span>
                                </label>
                                <select class="form-select form-select-sm" name="bank_id" id="saleBankSelect">
                                    <option value="">-- Select Bank Account --</option>
                                    <?php foreach ($bankAccounts as $ba): ?>
                                        <option value="<?php echo $ba['id']; ?>" <?php echo ($holdSale && ($holdSale['bank_id'] ?? null) == $ba['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($ba['bank_name']); ?> - <?php echo htmlspecialchars($ba['account_number']); ?> (Bal: Rs. <?php echo number_format($ba['current_balance'], 2); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Cash Received & Change Return (for Cash payments) -->
                            <div id="cashTenderedSection" class="p-2 mb-3 rounded bg-light border" style="display: <?php echo (!$holdSale || $holdSale['payment_method'] === 'cash') ? 'block' : 'none'; ?>;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small text-muted">Cash Received:</span>
                                    <input type="number" step="0.01" class="form-control form-control-sm text-end" style="width: 120px;" id="cashReceivedInput" placeholder="0.00" oninput="recalcChangeReturn()">
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="small text-muted">Change to Return:</span>
                                    <span class="fw-bold text-success" id="changeReturnDisplay">Rs. 0.00</span>
                                </div>
                            </div>

                            <!-- Checkout Primary Actions -->
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-primary btn-lg py-2 fw-bold" onclick="doComplete()" id="btnCompleteSale">
                                    <i class="fas fa-check-circle me-2"></i> Complete & Print
                                </button>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-warning w-50 py-2 fw-semibold" onclick="doHold()">
                                        <i class="fas fa-pause-circle me-1"></i> Hold Bill
                                    </button>
                                    <a href="sales.php" class="btn btn-outline-secondary w-50 py-2 fw-semibold">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// ── Data from PHP ──
const rawProducts = <?php echo json_encode($products); ?>;
const rawCustomers = <?php echo json_encode($customers); ?>;
const holdItems = <?php echo json_encode($holdItems); ?>;

// In-memory cart array
let cart = [];

// ── Initialize Cart from Hold Bill if available ──
if (holdItems && holdItems.length > 0) {
    holdItems.forEach(item => {
        const itemType = item.product_type || 'finished_good';
        cart.push({
            product_id: parseInt(item.product_id),
            item_type: itemType,
            product_code: item.product_code || '',
            product_name: item.product_name || 'Item',
            unit: item.unit || 'Pcs',
            current_stock: parseFloat(item.current_stock || 0),
            unit_price: parseFloat(item.unit_price || 0),
            quantity: parseFloat(item.quantity || 1)
        });
    });
}

// ── Customer Toggle (Credit vs Walk-in) ──
function toggleCustomerType() {
    const isCredit = document.getElementById('typeCredit').checked;
    const creditSec = document.getElementById('creditCustomerSection');
    const walkinSec = document.getElementById('walkinCustomerSection');
    const payCreditCard = document.getElementById('payCardCredit');
    const prevRow = document.getElementById('summaryPrevBalanceRow');
    const totalDueRow = document.getElementById('summaryTotalDueRow');

    if (isCredit) {
        creditSec.style.display = 'block';
        walkinSec.style.display = 'none';
        payCreditCard.style.display = 'block';
        prevRow.style.display = 'flex';
        if (totalDueRow) totalDueRow.style.display = 'flex';
        
        // Default to credit payment method for credit customer
        setPaymentMethod('credit');
        loadCustomerBalance();
    } else {
        creditSec.style.display = 'none';
        walkinSec.style.display = 'block';
        payCreditCard.style.display = 'none';
        prevRow.style.display = 'none';
        if (totalDueRow) totalDueRow.style.display = 'none';
        
        document.getElementById('prevAmountVal').value = '0';
        document.getElementById('prevBalanceTypeVal').value = 'receivable';
        document.getElementById('summaryPrevBalanceAmount').textContent = 'Rs. 0.00';
        
        // Default to cash for walk-in
        setPaymentMethod('cash');
        recalcTotals();
    }
}

// ── Customer Auto Search ──
function searchCustomer(input) {
    const q = input.value.trim().toLowerCase();
    const dropdown = document.getElementById('customerDropdown');
    
    if (!q) {
        dropdown.innerHTML = '';
        dropdown.classList.remove('show');
        return;
    }

    const matches = rawCustomers.filter(c => 
        (c.customer_name && c.customer_name.toLowerCase().includes(q)) ||
        (c.company_name && c.company_name.toLowerCase().includes(q)) ||
        (c.phone && c.phone.includes(q))
    );

    if (matches.length === 0) {
        dropdown.innerHTML = '<div class="p-3 text-center text-muted small">No customers found</div>';
        dropdown.classList.add('show');
        return;
    }

    let html = '';
    matches.forEach((c, idx) => {
        const bal = parseFloat(c.current_balance || 0);
        const balColor = bal > 0 ? 'text-danger' : (bal < 0 ? 'text-primary' : 'text-success');
        const balText = bal > 0 ? 'Receivable: Rs. ' + bal.toLocaleString() : (bal < 0 ? 'Payable: Rs. ' + Math.abs(bal).toLocaleString() : 'Clear');

        html += `
            <div class="pos-dropdown-item ${idx === 0 ? 'active' : ''}" data-id="${c.id}" onclick="selectCustomerById(${c.id})">
                <div>
                    <strong class="d-block text-dark">${escapeHtml(c.customer_name)}</strong>
                    <small class="text-muted">${escapeHtml(c.company_name || c.phone || 'Customer')}</small>
                </div>
                <div class="text-end">
                    <small class="fw-bold ${balColor}">${balText}</small>
                </div>
            </div>
        `;
    });

    dropdown.innerHTML = html;
    dropdown.classList.add('show');
}

function selectCustomerById(id) {
    const cust = rawCustomers.find(c => c.id == id);
    if (!cust) return;
    selectCustomer(cust.id, cust.customer_name, cust.company_name || '');
}

function selectCustomer(id, name, company) {
    document.getElementById('customer_id').value = id;
    document.getElementById('customerSearchInput').value = '';
    document.getElementById('customerDropdown').classList.remove('show');

    // Update Banner
    document.getElementById('dispCustomerName').textContent = name;
    document.getElementById('dispCustomerCompany').textContent = company || 'Registered Customer';
    document.getElementById('customerPickerWrap').style.display = 'none';
    document.getElementById('selectedCustomerDisplay').style.display = 'flex';

    loadCustomerBalance();
    
    // Auto focus item search
    document.getElementById('itemSearchInput').focus();
}

function clearSelectedCustomer() {
    document.getElementById('customer_id').value = '';
    document.getElementById('customerSearchInput').value = '';
    document.getElementById('customerPickerWrap').style.display = 'block';
    document.getElementById('selectedCustomerDisplay').style.display = 'none';
    document.getElementById('prevAmountVal').value = '0';
    document.getElementById('summaryPrevBalanceAmount').textContent = 'Rs. 0.00';
    document.getElementById('dispCustomerBalanceBadge').textContent = 'Rs. 0.00';
    recalcTotals();
    document.getElementById('customerSearchInput').focus();
}

function loadCustomerBalance() {
    const cid = document.getElementById('customer_id').value;
    if (!cid) return;

    fetch('ajax_customer_balance.php?id=' + cid)
        .then(res => res.json())
        .then(data => {
            const bal = parseFloat(data.balance || 0);
            const isPayable = (data.balance_type === 'payable');
            
            document.getElementById('prevAmountVal').value = bal;
            document.getElementById('prevBalanceTypeVal').value = data.balance_type;
            
            const badge = document.getElementById('dispCustomerBalanceBadge');
            const summaryAmt = document.getElementById('summaryPrevBalanceAmount');
            const summaryType = document.getElementById('summaryPrevBalanceType');

            const formatted = 'Rs. ' + Math.abs(bal).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            
            summaryAmt.textContent = formatted;
            summaryType.textContent = isPayable ? '(Payable)' : '(Receivable)';
            
            if (bal > 0) {
                badge.className = 'badge bg-danger fs-6';
                badge.textContent = 'Receivable: ' + formatted;
                summaryAmt.className = 'fw-bold text-danger';
            } else if (bal < 0) {
                badge.className = 'badge bg-info text-dark fs-6';
                badge.textContent = 'Payable: ' + formatted;
                summaryAmt.className = 'fw-bold text-info';
            } else {
                badge.className = 'badge bg-success fs-6';
                badge.textContent = 'Clear: Rs. 0.00';
                summaryAmt.className = 'fw-bold text-success';
            }

            recalcTotals();
        })
        .catch(err => {
            console.error('Error fetching customer balance', err);
        });
}

// ── Category Filter for Items (All, Finished Goods, Raw Materials) ──
let currentItemFilter = 'all';

function setItemFilter(filterType) {
    currentItemFilter = filterType;
    const searchInput = document.getElementById('itemSearchInput');
    filterProducts(searchInput);
}

// ── Product Search & Autocomplete ──
function filterProducts(input) {
    const q = input.value.trim().toLowerCase();
    const dropdown = document.getElementById('productDropdown');

    let pool = rawProducts;
    if (currentItemFilter !== 'all') {
        pool = pool.filter(p => p.item_type === currentItemFilter);
    }

    if (!q && currentItemFilter === 'all') {
        dropdown.innerHTML = '';
        dropdown.classList.remove('show');
        return;
    }

    let matches = pool;
    if (q) {
        matches = pool.filter(p => 
            (p.product_code && p.product_code.toLowerCase().includes(q)) ||
            (p.product_name && p.product_name.toLowerCase().includes(q)) ||
            (p.category && p.category.toLowerCase().includes(q))
        );
    }

    matches = matches.slice(0, 15); // Show max 15

    if (matches.length === 0) {
        dropdown.innerHTML = '<div class="p-3 text-center text-muted small">No items match search</div>';
        dropdown.classList.add('show');
        return;
    }

    let html = '';
    matches.forEach((p, idx) => {
        const stock = parseFloat(p.current_stock || 0);
        const price = parseFloat(p.selling_price || 0);
        const stockBadge = stock > 0 
            ? `<span class="badge bg-success-subtle text-success border border-success-subtle">Stock: ${stock} ${escapeHtml(p.unit || '')}</span>`
            : `<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Out of Stock</span>`;
        const typeBadge = p.item_type === 'raw_material'
            ? `<span class="badge bg-warning text-dark me-1" style="font-size:10px;"><i class="fas fa-cubes me-1"></i>Raw Material</span>`
            : `<span class="badge bg-primary text-white me-1" style="font-size:10px;"><i class="fas fa-box me-1"></i>Finished Good</span>`;

        html += `
            <div class="pos-dropdown-item ${idx === 0 ? 'active' : ''}" onclick="addToCart('${p.item_type}', ${p.id})">
                <div>
                    <div class="d-flex align-items-center mb-1">
                        ${typeBadge}
                        <strong class="text-dark">${escapeHtml(p.product_name)}</strong>
                    </div>
                    <small class="text-muted">Code: ${escapeHtml(p.product_code)} | Unit: ${escapeHtml(p.unit || 'Pcs')}</small>
                </div>
                <div class="text-end">
                    <div class="fw-bold text-primary mb-1">Rs. ${price.toFixed(2)}</div>
                    ${stockBadge}
                </div>
            </div>
        `;
    });

    dropdown.innerHTML = html;
    dropdown.classList.add('show');
}

// ── Cart Manipulation ──
function addToCart(itemType, id) {
    const prod = rawProducts.find(p => p.item_type === itemType && p.id == id);
    if (!prod) return;

    // Check if already in cart by composite type & id
    const existingIndex = cart.findIndex(item => item.item_type === itemType && item.product_id == id);
    if (existingIndex > -1) {
        cart[existingIndex].quantity += 1;
    } else {
        cart.push({
            product_id: parseInt(prod.id),
            item_type: prod.item_type,
            product_code: prod.product_code || '',
            product_name: prod.product_name || 'Item',
            unit: prod.unit || 'Pcs',
            current_stock: parseFloat(prod.current_stock || 0),
            unit_price: parseFloat(prod.selling_price || 0),
            quantity: 1
        });
    }

    // Reset Search
    const searchInput = document.getElementById('itemSearchInput');
    searchInput.value = '';
    document.getElementById('productDropdown').classList.remove('show');
    searchInput.focus();

    renderCart();
}

function addToCartById(productId) {
    addToCart('finished_good', productId);
}

function updateQty(index, delta) {
    if (!cart[index]) return;
    const newQty = cart[index].quantity + delta;
    if (newQty > 0) {
        cart[index].quantity = newQty;
        renderCart();
    } else {
        removeFromCart(index);
    }
}

function setQty(index, value) {
    if (!cart[index]) return;
    const val = parseFloat(value);
    if (!isNaN(val) && val > 0) {
        cart[index].quantity = val;
    }
    renderCart();
}

function setPrice(index, value) {
    if (!cart[index]) return;
    const val = parseFloat(value);
    if (!isNaN(val) && val >= 0) {
        cart[index].unit_price = val;
    }
    renderCart();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    renderCart();
}

// ── Render Cart UI ──
function renderCart() {
    const tbody = document.getElementById('cartItemsTbody');
    const emptyDiv = document.getElementById('emptyCartDisplay');

    if (cart.length === 0) {
        tbody.innerHTML = '';
        emptyDiv.style.display = 'block';
        recalcTotals();
        return;
    }

    emptyDiv.style.display = 'none';
    let html = '';

    cart.forEach((item, idx) => {
        const lineTotal = (item.quantity * item.unit_price).toFixed(2);
        const isOutOfStock = item.quantity > item.current_stock;
        const typeBadge = item.item_type === 'raw_material'
            ? `<span class="badge bg-warning text-dark me-1" style="font-size:10px;">RM</span>`
            : `<span class="badge bg-primary text-white me-1" style="font-size:10px;">FG</span>`;

        html += `
            <tr class="${isOutOfStock ? 'table-warning' : ''}">
                <td class="text-muted fw-bold">${idx + 1}</td>
                <td>
                    <div class="fw-bold text-dark d-flex align-items-center">
                        ${typeBadge}
                        <span>${escapeHtml(item.product_name)}</span>
                    </div>
                    <small class="text-muted me-2">${escapeHtml(item.product_code)}</small>
                    <span class="badge ${item.current_stock > 0 ? 'bg-light text-secondary' : 'bg-danger'} border">
                        Available: ${item.current_stock} ${escapeHtml(item.unit)}
                    </span>
                    <input type="hidden" name="products[${idx}][product_id]" value="${item.product_id}">
                    <input type="hidden" name="products[${idx}][product_type]" value="${item.item_type}">
                </td>
                <td class="text-end">
                    <input type="number" step="0.01" class="form-control form-control-sm text-end fw-semibold d-inline-block" style="width: 110px;" name="products[${idx}][unit_price]" value="${item.unit_price}" oninput="setPrice(${idx}, this.value)">
                </td>
                <td class="text-center">
                    <div class="qty-btn-group">
                        <button type="button" class="qty-btn" onclick="updateQty(${idx}, -1)">-</button>
                        <input type="number" step="0.01" class="qty-input" name="products[${idx}][quantity]" value="${item.quantity}" onchange="setQty(${idx}, this.value)">
                        <button type="button" class="qty-btn" onclick="updateQty(${idx}, 1)">+</button>
                    </div>
                </td>
                <td class="text-end fw-bold fs-6 text-primary">
                    Rs. ${parseFloat(lineTotal).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="removeFromCart(${idx})" title="Remove item">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    recalcTotals();
}

// ── Financial Recalculations ──
function recalcTotals() {
    let billSubtotal = 0;
    let totalItemsCount = 0;

    cart.forEach(item => {
        billSubtotal += (item.quantity * item.unit_price);
        totalItemsCount += item.quantity;
    });

    const discount = parseFloat(document.getElementById('discInput').value) || 0;
    const finalAmount = Math.max(0, billSubtotal - discount);

    // Update Displays
    document.getElementById('summaryTotalItems').textContent = totalItemsCount;
    document.getElementById('summaryBillAmount').textContent = 'Rs. ' + billSubtotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    document.getElementById('heroFinalDisplay').textContent = 'Rs. ' + finalAmount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('heroItemsCountDisplay').textContent = totalItemsCount + ' item(s) in cart';

    // Update Hidden Form Inputs
    document.getElementById('hidBillAmount').value = billSubtotal.toFixed(2);
    document.getElementById('hidFinalAmount').value = finalAmount.toFixed(2);

    // Update Total Balance Due with Previous Balance
    const isCredit = document.getElementById('typeCredit').checked;
    const prevBal = parseFloat(document.getElementById('prevAmountVal').value) || 0;
    const totalDueRow = document.getElementById('summaryTotalDueRow');
    const totalDueAmt = document.getElementById('summaryTotalDueAmount');
    
    if (isCredit && totalDueRow) {
        totalDueRow.style.display = 'flex';
        const activeMethod = document.querySelector('input[name="payment_method"]:checked')?.value || 'credit';
        let billBal = finalAmount;
        if (activeMethod === 'cash' || activeMethod === 'bank') {
            billBal = 0;
        }
        const netDue = prevBal + billBal;
        totalDueAmt.textContent = 'Rs. ' + Math.abs(netDue).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + (netDue < 0 ? ' (Adv)' : '');
        totalDueAmt.className = 'fw-bold fs-6 ' + (netDue > 0 ? 'text-danger' : (netDue < 0 ? 'text-info' : 'text-success'));
    } else if (totalDueRow) {
        totalDueRow.style.display = 'none';
    }

    recalcChangeReturn();
}

function recalcChangeReturn() {
    const finalAmount = parseFloat(document.getElementById('hidFinalAmount').value) || 0;
    const cashReceived = parseFloat(document.getElementById('cashReceivedInput').value) || 0;
    const changeDisplay = document.getElementById('changeReturnDisplay');

    if (cashReceived > finalAmount) {
        const change = cashReceived - finalAmount;
        changeDisplay.textContent = 'Rs. ' + change.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        changeDisplay.className = 'fw-bold text-success fs-6';
    } else {
        changeDisplay.textContent = 'Rs. 0.00';
        changeDisplay.className = 'fw-bold text-muted';
    }
}

// ── Payment Method Handling ──
function setPaymentMethod(method) {
    const cards = document.querySelectorAll('.pay-method-card');
    cards.forEach(card => card.classList.remove('active'));

    const activeRadio = document.querySelector(`input[name="payment_method"][value="${method}"]`);
    if (activeRadio) {
        activeRadio.checked = true;
        activeRadio.closest('.pay-method-card').classList.add('active');
    }

    const cashSec = document.getElementById('cashTenderedSection');
    if (cashSec) cashSec.style.display = (method === 'cash') ? 'block' : 'none';

    const bankSec = document.getElementById('bankAccountSection');
    if (bankSec) bankSec.style.display = (method === 'bank') ? 'block' : 'none';

    recalcTotals();
}

function handlePaymentMethodChange(method) {
    setPaymentMethod(method);
}

// ── Form Submissions ──
function doComplete() {
    if (!validateSaleForm()) return;
    document.getElementById('formAction').value = 'complete';
    document.getElementById('saleForm').submit();
}

function doHold() {
    if (!validateSaleForm()) return;
    document.getElementById('formAction').value = 'hold';
    document.getElementById('saleForm').submit();
}

function validateSaleForm() {
    const isCredit = document.getElementById('typeCredit').checked;
    
    if (isCredit) {
        const customerId = document.getElementById('customer_id').value;
        if (!customerId) {
            alert('Please select a registered customer!');
            document.getElementById('customerSearchInput').focus();
            return false;
        }
    } else {
        const walkinName = document.getElementById('walkin_name').value.trim();
        if (!walkinName) {
            alert('Please enter walk-in customer name!');
            document.getElementById('walkin_name').focus();
            return false;
        }
    }

    const activeRadio = document.querySelector('input[name="payment_method"]:checked');
    const selectedMethod = activeRadio ? activeRadio.value : 'cash';
    if (selectedMethod === 'bank') {
        const bankSelect = document.getElementById('saleBankSelect');
        if (!bankSelect || !bankSelect.value) {
            alert('Please select a bank account!');
            bankSelect?.focus();
            return false;
        }
    }

    if (cart.length === 0) {
        alert('Please add at least one item to the cart!');
        document.getElementById('itemSearchInput').focus();
        return false;
    }

    return true;
}

// ── Global Keyboard Shortcuts ──
document.addEventListener('keydown', function(e) {
    // F2: Complete Sale
    if (e.key === 'F2') {
        e.preventDefault();
        doComplete();
    }
    // F3: Hold Bill
    if (e.key === 'F3') {
        e.preventDefault();
        doHold();
    }
    // F4 or Slash (/): Focus Item Search
    if (e.key === 'F4' || (e.key === '/' && document.activeElement.tagName !== 'INPUT')) {
        e.preventDefault();
        document.getElementById('itemSearchInput').focus();
        document.getElementById('itemSearchInput').select();
    }
    // Alt + C: Focus Customer Search
    if (e.altKey && (e.key === 'c' || e.key === 'C')) {
        e.preventDefault();
        if (document.getElementById('typeCredit').checked) {
            document.getElementById('customerSearchInput').focus();
        } else {
            document.getElementById('walkin_name').focus();
        }
    }
    // Escape: Exit or Clear dropdown
    if (e.key === 'Escape') {
        document.getElementById('productDropdown').classList.remove('show');
        document.getElementById('customerDropdown').classList.remove('show');
    }
});

// Arrow key navigation for customer dropdown
document.getElementById('customerSearchInput').addEventListener('keydown', function(e) {
    const dropdown = document.getElementById('customerDropdown');
    const items = dropdown.querySelectorAll('.pos-dropdown-item');
    if (!items.length || !dropdown.classList.contains('show')) return;

    let activeIndex = -1;
    items.forEach((item, idx) => {
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
            const id = items[activeIndex].getAttribute('data-id');
            selectCustomerById(id);
        } else if (items.length > 0) {
            const id = items[0].getAttribute('data-id');
            selectCustomerById(id);
        }
    }
});

// Arrow key navigation for product dropdown
document.getElementById('itemSearchInput').addEventListener('keydown', function(e) {
    const dropdown = document.getElementById('productDropdown');
    const items = dropdown.querySelectorAll('.pos-dropdown-item');
    if (!items.length || !dropdown.classList.contains('show')) return;

    let activeIndex = -1;
    items.forEach((item, idx) => {
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
            const id = items[activeIndex].getAttribute('data-id');
            addToCartById(id);
        }
    }
});

// Helper: Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Close dropdowns on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('#itemSearchInput') && !e.target.closest('#productDropdown')) {
        document.getElementById('productDropdown').classList.remove('show');
    }
    if (!e.target.closest('#customerSearchInput') && !e.target.closest('#customerDropdown')) {
        document.getElementById('customerDropdown').classList.remove('show');
    }
});

// ── Document Ready Initialization ──
$(document).ready(function() {
    toggleCustomerType();
    renderCart();

    // If editing a hold bill with credit customer
    <?php if ($holdSale && $holdSale['customer_type'] === 'credit' && $holdSale['customer_id'] > 0): ?>
        const presetCust = rawCustomers.find(c => c.id == <?php echo $holdSale['customer_id']; ?>);
        if (presetCust) {
            selectCustomer(presetCust.id, presetCust.customer_name, presetCust.company_name);
        }
    <?php endif; ?>

    // Auto-focus Item Search
    setTimeout(function() {
        document.getElementById('itemSearchInput').focus();
    }, 300);
});
</script>

<?php include '../includes/footer.php'; ?>
