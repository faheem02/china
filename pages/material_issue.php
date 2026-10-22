<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Material Issuance';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $issue = getRow("SELECT * FROM material_issues WHERE id = ?", 'i', [$id]);
    if ($issue) {
        $items = getRows("SELECT material_id, quantity FROM material_issue_items WHERE issue_id = ?", 'i', [$id]);
        foreach ($items as $item) {
            modifyData("UPDATE raw_materials SET current_stock = current_stock + ? WHERE id = ?", 'di', [$item['quantity'], $item['material_id']]);
        }
        modifyData("DELETE FROM material_issue_items WHERE issue_id = ?", 'i', [$id]);
        modifyData("DELETE FROM material_issues WHERE id = ?", 'i', [$id]);
        setFlash('Issuance deleted! Stock restored.', 'success');
    } else {
        setFlash('Error deleting issuance!', 'danger');
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Add/Edit POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $issue_date = trim($_POST['issue_date']);
    $notes = trim($_POST['notes'] ?? '');

    $materials = [];
    if (!empty($_POST['materials_json'])) {
        $decoded = json_decode($_POST['materials_json'], true);
        if (is_array($decoded)) $materials = $decoded;
    }
    if (empty($materials)) $materials = $_POST['materials'] ?? [];

    if (empty($issue_date)) {
        $message = 'Please select issue date!';
        $messageType = 'danger';
    } else {
        $currentDateTime = getCurrentDateTime();
        $validMaterials = [];
        $materialsTotal = 0;
        foreach ($materials as $m) {
            if (empty($m['material_id'])) continue;
            $qty = (float)($m['quantity'] ?? 0);
            if ($qty <= 0) continue;
            $up = (float)($m['unit_price'] ?? 0);
            $m['quantity'] = $qty;
            $m['unit_price'] = $up;
            $m['total'] = $qty * $up;
            $materialsTotal += $m['total'];
            $validMaterials[] = $m;
        }
        $materials = $validMaterials;

        if (empty($materials)) {
            $message = 'Please add at least one material with valid quantity!';
            $messageType = 'danger';
        } else {
            if ($id > 0) {
                // EDIT: restore old stock
                $oldItems = getRows("SELECT material_id, quantity FROM material_issue_items WHERE issue_id = ?", 'i', [$id]);
                foreach ($oldItems as $oi) {
                    modifyData("UPDATE raw_materials SET current_stock = current_stock + ? WHERE id = ?", 'di', [$oi['quantity'], $oi['material_id']]);
                }
                modifyData("DELETE FROM material_issue_items WHERE issue_id = ?", 'i', [$id]);
                modifyData("UPDATE material_issues SET issue_date=?, notes=?, total_value=?, date_time=? WHERE id=?",
                    'ssdsi', [$issue_date, $notes, $materialsTotal, $currentDateTime, $id]);

                foreach ($materials as $m) {
                    $mid = (int)$m['material_id'];
                    $qty = (float)$m['quantity'];
                    $up = (float)$m['unit_price'];
                    $tot = (float)$m['total'];
                    insertData("INSERT INTO material_issue_items (issue_id, material_id, quantity, unit_price, total, date_time) VALUES (?,?,?,?,?,?)",
                        'iiddds', [$id, $mid, $qty, $up, $tot, $currentDateTime]);
                    modifyData("UPDATE raw_materials SET current_stock = current_stock - ? WHERE id = ?", 'di', [$qty, $mid]);
                }
                setFlash('Issuance updated! Stock adjusted.', 'success');
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } else {
                // NEW: check stock
                $insufficient = false;
                foreach ($materials as $m) {
                    $check = getRow("SELECT current_stock FROM raw_materials WHERE id = ?", 'i', [(int)$m['material_id']]);
                    if (!$check || (float)$check['current_stock'] < (float)$m['quantity']) {
                        $insufficient = true;
                        break;
                    }
                }

                if ($insufficient) {
                    $message = 'Insufficient stock for one or more materials!';
                    $messageType = 'danger';
                } else {
                    $issue_no = generateCode('ISS');
                    $result = insertData("INSERT INTO material_issues (issue_no, issue_date, customer_id, customer_name, notes, payment_method, paid_amount, total_value, status, date_time) VALUES (?,?,?,?,?,?,?,?,?,?)",
                        'ssssssddss', [$issue_no, $issue_date, null, '', $notes, 'cash', 0, $materialsTotal, 'completed', $currentDateTime]);

                    if ($result !== false) {
                        foreach ($materials as $m) {
                            $mid = (int)$m['material_id'];
                            $qty = (float)$m['quantity'];
                            $up = (float)$m['unit_price'];
                            $tot = (float)$m['total'];
                            insertData("INSERT INTO material_issue_items (issue_id, material_id, quantity, unit_price, total, date_time) VALUES (?,?,?,?,?,?)",
                                'iiddds', [$result, $mid, $qty, $up, $tot, $currentDateTime]);
                            modifyData("UPDATE raw_materials SET current_stock = current_stock - ? WHERE id = ?", 'di', [$qty, $mid]);
                        }
                        setFlash('Issuance created! Stock deducted.', 'success');
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    } else {
                        $message = 'Error creating issuance!';
                        $messageType = 'danger';
                    }
                }
            }
        }
    }
}

// Fetch data for display
$issues = getRows("SELECT mi.*, 
    (SELECT COUNT(*) FROM material_issue_items WHERE issue_id = mi.id) as item_count
    FROM material_issues mi ORDER BY mi.id DESC");

$allIssueItems = getRows("SELECT mii.*, rm.material_code, rm.material_name, rm.unit 
    FROM material_issue_items mii 
    LEFT JOIN raw_materials rm ON mii.material_id = rm.id 
    ORDER BY mii.id ASC");

$issueItemsMap = [];
foreach ($allIssueItems as $item) {
    $issueItemsMap[$item['issue_id']][] = $item;
}

$materials = getRows("SELECT id, material_code, material_name, unit, current_stock, purchase_price_pkr, selling_price
    FROM raw_materials WHERE status = 'active' ORDER BY material_name");


// Fetch edit data
$editIssue = null;
$editItems = [];
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editIssue = getRow("SELECT * FROM material_issues WHERE id = ?", 'i', [(int)$_GET['edit']]);
    if ($editIssue) {
        $editItems = getRows("SELECT mii.*, rm.material_code, rm.material_name, rm.current_stock
            FROM material_issue_items mii 
            LEFT JOIN raw_materials rm ON mii.material_id = rm.id
            WHERE mii.issue_id = ?", 'i', [$editIssue['id']]);
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
.issue-item { border: 1px solid #e8eaed; border-radius: 8px; padding: 8px; margin-bottom: 6px; background: #fff; transition: box-shadow 0.2s; position: relative; }
.issue-item:hover { box-shadow: 0 1px 6px rgba(0,0,0,0.08); }
.issue-item.cell-dropdown-open { z-index: 1055; }
.issue-item .stock-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: #e9ecef; color: #495057; white-space: nowrap; }
.issue-item .qty { font-size: 14px; font-weight: 600; color: #1a2332; }

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
    min-width: 300px;
    background: #ffffff;
    border: 1.5px solid #94a3b8;
    border-radius: 8px;
    box-shadow: 0 14px 30px rgba(0,0,0,0.18), 0 4px 10px rgba(0,0,0,0.08);
    max-height: 220px;
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
#issueModal .modal-content, #issueModal .modal-body, #issueModal .card, #materialBody {
    overflow: visible !important;
}

@media print {
    #sidebar, .navbar-custom, .btn, .dataTables_filter, .dataTables_length, .dataTables_paginate, .dataTables_info, .d-print-none {
        display: none !important;
    }
    #content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
    .card { border: none !important; box-shadow: none !important; }
    .card-header { background: #f8f9fa !important; color: #000 !important; border-bottom: 2px solid #000 !important; }
}
</style>

<div class="container-fluid px-0">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="fas fa-sign-out-alt me-2"></i>Material Issuance</h5>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-danger" onclick="window.print()">
                <i class="fas fa-print me-1"></i>Print
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#issueModal" onclick="resetModal()">
                <i class="fas fa-plus me-2"></i>New Issuance
            </button>
        </div>
    </div>


    <div class="card shadow-sm">
        <div class="card-header" style="background:#1a2332;color:#fff;">
            <i class="fas fa-list me-2"></i>Issuance History
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle" id="issueTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Issue No</th>
                            <th>Date</th>
                            <th>Material Name(s)</th>
                            <th class="text-center">Total Qty</th>
                            <th class="text-end">Rate</th>
                            <th class="text-end">Total Value</th>
                            <th>Notes</th>
                            <th class="d-print-none text-center" style="width: 130px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($issues) > 0): ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($issues as $issue): 
                                $items = $issueItemsMap[$issue['id']] ?? [];
                                $totalQty = array_sum(array_column($items, 'quantity'));
                                $totalVal = array_sum(array_column($items, 'total'));
                                $avgRate = $totalQty > 0 ? $totalVal / $totalQty : 0;
                            ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td class="text-nowrap"><?php echo htmlspecialchars($issue['issue_no']); ?></td>
                                    <td><?php echo formatDate($issue['issue_date']); ?></td>
                                    <td>
                                        <?php if (!empty($items)): ?>
                                            <?php foreach ($items as $it): ?>
                                                <div class="mb-1">
                                                    <strong><?php echo htmlspecialchars($it['material_name'] ?? 'Material'); ?></strong>
                                                    <span class="badge bg-light text-dark border ms-1">
                                                        <?php echo number_format((float)$it['quantity'], 2); ?> <?php echo htmlspecialchars($it['unit'] ?: 'units'); ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center fw-bold text-primary">
                                        <?php echo number_format($totalQty, 2); ?>
                                    </td>
                                    <td class="text-end">
                                        Rs. <?php echo number_format((float)$avgRate, 2); ?>
                                    </td>
                                    <td class="text-end fw-bold text-success">
                                        Rs. <?php echo number_format((float)$totalVal, 2); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($issue['notes'] ?: '-'); ?></td>
                                    <td class="d-print-none text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-info text-white view-issue-btn" 
                                                    data-issue-no="<?php echo htmlspecialchars($issue['issue_no']); ?>"
                                                    data-issue-date="<?php echo formatDate($issue['issue_date']); ?>"
                                                    data-notes="<?php echo htmlspecialchars($issue['notes'] ?: '-'); ?>"
                                                    data-items='<?php echo htmlspecialchars(json_encode($items), ENT_QUOTES, 'UTF-8'); ?>'
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="?edit=<?php echo $issue['id']; ?>" class="btn btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete=<?php echo $issue['id']; ?>" class="btn btn-danger delete-confirm" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- View Issue Modal -->
<div class="modal fade" id="viewIssueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a2332;color:#fff;">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i>Material Issuance Details: <span id="viewIssueNo"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewIssuePrintArea">
                <div class="row mb-3 pb-2 border-bottom">
                    <div class="col-md-6">
                        <div><strong>Issue No:</strong> <span id="viewModalIssueNo" class="text-primary fw-bold"></span></div>
                        <div><strong>Date:</strong> <span id="viewModalIssueDate"></span></div>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div><strong>Notes:</strong> <span id="viewModalNotes" class="text-muted"></span></div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Material Code</th>
                                <th>Material Name</th>
                                <th class="text-end">Quantity Issued</th>
                                <th>Unit</th>
                                <th class="text-end">Rate</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody id="viewIssueItemsBody">
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <th colspan="3" class="text-end">Total Quantity Issued:</th>
                                <th class="text-end text-primary" id="viewModalTotalQty"></th>
                                <th></th>
                                <th class="text-end">Total Value:</th>
                                <th class="text-end text-success" id="viewModalTotalVal"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="printIssueVoucher()">
                    <i class="fas fa-print me-1"></i> Print Voucher
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Issue Modal -->
<div class="modal fade" id="issueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a2332;color:#fff;">
                <h5 class="modal-title">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    <span id="modalTitleText">New Issuance</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" onsubmit="return validateForm()">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_id" value="0">
                    <input type="hidden" name="materials_json" id="materials_json" value="">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Issue Date *</label>
                            <input type="date" class="form-control" id="issue_date" name="issue_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notes</label>
                            <input type="text" class="form-control" id="notes" name="notes" placeholder="Optional notes...">
                        </div>
                    </div>

                    <!-- Materials -->
                    <div class="card mb-0">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-cubes me-2"></i>Raw Materials to Issue</span>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success" id="modalTotalValue">Total: Rs 0.00</span>
                                <button type="button" class="btn btn-sm btn-success" onclick="addMaterialRow()">
                                    <i class="fas fa-plus me-1"></i>Add Material
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-2" id="materialBody">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check-circle me-2"></i><span id="submitBtnText">Issue Materials</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var materialsData = <?php echo json_encode($materials); ?>;
var rowIdx = 0;
var editItemsData = <?php echo json_encode($editItems); ?>;

function makeRow(idx, matId, qty, unitPrice) {
    var displayVal = '';
    var stockVal = '0.00';
    var rateVal = 0;
    if (matId) {
        var m = materialsData.find(function(item) { return item.id == matId; });
        if (m) {
            displayVal = m.material_name;
            stockVal = parseFloat(m.current_stock || 0).toFixed(2);
            rateVal = parseFloat(m.purchase_price_pkr || 0);
            if (!rateVal) rateVal = parseFloat(m.selling_price || 0);
        }
    }
    if (unitPrice === undefined || unitPrice === null || unitPrice === '') {
        unitPrice = rateVal;
    }

    var h = '<div class="issue-item">';
    h += '<div class="row g-1 mb-1">';
    h += '<div class="col">';
    h += '<div class="material-search-wrap">';
    h += '<i class="fas fa-search material-search-icon"></i>';
    h += '<input type="text" class="form-control form-control-sm material-search-input" placeholder="Type raw material name or code..." value="' + escapeHtml(displayVal) + '" autocomplete="off" oninput="filterMaterial(this)" onfocus="filterMaterial(this)" onblur="onMaterialInputBlur(this)" onkeydown="navigateMaterial(event, this)">';
    h += '<input type="hidden" class="material-id-input" value="' + (matId || '') + '">';
    h += '<div class="material-search-dropdown shadow-sm"></div>';
    h += '</div>';
    h += '</div>';
    h += '<div class="col-auto d-flex align-items-center"><button type="button" class="btn btn-sm btn-outline-danger px-1 py-0" onclick="removeRow(this)"><i class="fas fa-times"></i></button></div>';
    h += '</div>';
    h += '<div class="row g-2 align-items-end">';
    h += '<div class="col-auto"><span class="stock-badge">Stock: <span class="stock-display fw-bold">' + stockVal + '</span></span></div>';
    h += '<div class="col-md-3"><label class="small text-muted mb-0">Quantity *</label>';
    h += '<input type="number" step="0.01" min="0.01" class="form-control form-control-sm qty" value="' + (qty || 1) + '" oninput="calcRowTotal(this.closest(\'.issue-item\'))"></div>';
    h += '<div class="col-md-3"><label class="small text-muted mb-0">Rate (PKR)</label>';
    h += '<input type="number" step="0.01" min="0" class="form-control form-control-sm unit-price" value="' + (parseFloat(unitPrice || 0).toFixed(2)) + '" oninput="calcRowTotal(this.closest(\'.issue-item\'))"></div>';
    h += '<div class="col-md-3"><label class="small text-muted mb-0">Total</label>';
    h += '<input type="text" class="form-control form-control-sm row-total" value="' + ((qty || 1) * (parseFloat(unitPrice || 0))).toFixed(2) + '" readonly></div>';
    h += '</div></div>';
    return h;
}

function filterMaterial(input) {
    const q = input.value.trim().toLowerCase();
    const wrap = input.closest('.material-search-wrap');
    const dropdown = wrap.querySelector('.material-search-dropdown');
    const itemBlock = input.closest('.issue-item');

    // Close any other open dropdowns
    document.querySelectorAll('.material-search-dropdown.show').forEach(function(dd) {
        if (dd !== dropdown) {
            dd.classList.remove('show');
            if (dd.closest('.issue-item')) dd.closest('.issue-item').classList.remove('cell-dropdown-open');
        }
    });

    let matches = [];
    if (!q) {
        matches = materialsData.slice(0, 15);
    } else {
        matches = materialsData.filter(function(m) {
            const name = (m.material_name || '').toLowerCase();
            const code = (m.material_code || '').toLowerCase();
            return name.includes(q) || code.includes(q);
        }).slice(0, 20);
    }

    if (matches.length === 0) {
        dropdown.innerHTML = '<div class="p-2 text-center text-muted small">No raw materials found</div>';
        dropdown.classList.add('show');
        if (itemBlock) itemBlock.classList.add('cell-dropdown-open');
        return;
    }

    let html = '';
    matches.forEach(function(m, idx) {
        const name = escapeHtml(m.material_name || '');
        const code = escapeHtml(m.material_code || '');
        const unit = escapeHtml(m.unit || '');
        const stock = parseFloat(m.current_stock || 0);
        let rate = parseFloat(m.purchase_price_pkr || 0);
        if (!rate) rate = parseFloat(m.selling_price || 0);

        html += '<div class="material-dropdown-item ' + (idx === 0 ? 'active' : '') + '" data-id="' + m.id + '" onmousedown="selectMaterialItem(this)">' +
            '<div>' +
                '<div><strong class="text-dark">' + name + '</strong></div>' +
                '<small class="text-muted">Code: ' + code + ' | Unit: ' + unit + '</small>' +
            '</div>' +
            '<div class="text-end ps-2">' +
                '<span class="fw-bold text-primary">Rs. ' + rate.toFixed(2) + '</span>' +
                '<small class="text-muted d-block">Stock: ' + stock.toFixed(2) + ' ' + unit + '</small>' +
            '</div>' +
        '</div>';
    });

    dropdown.innerHTML = html;
    dropdown.classList.add('show');
    if (itemBlock) itemBlock.classList.add('cell-dropdown-open');
}

function selectMaterialItem(itemEl) {
    const id = itemEl.getAttribute('data-id');
    const wrap = itemEl.closest('.material-search-wrap');
    const input = wrap.querySelector('.material-search-input');
    const hidden = wrap.querySelector('.material-id-input');
    const dropdown = wrap.querySelector('.material-search-dropdown');
    const itemBlock = itemEl.closest('.issue-item');

    const m = materialsData.find(function(item) { return item.id == id; });
    if (!m) return;

    hidden.value = m.id;
    input.value = m.material_name;
    dropdown.classList.remove('show');
    if (itemBlock) itemBlock.classList.remove('cell-dropdown-open');

    const stock = parseFloat(m.current_stock || 0);
    const stockEl = itemBlock.querySelector('.stock-display');
    if (stockEl) stockEl.textContent = stock.toFixed(2);

    let rate = parseFloat(m.purchase_price_pkr || 0);
    if (!rate) rate = parseFloat(m.selling_price || 0);
    const rateEl = itemBlock.querySelector('.unit-price');
    if (rateEl) rateEl.value = rate.toFixed(2);
    calcRowTotal(itemBlock);

    const qtyInput = itemBlock.querySelector('.qty');
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
        const itemBlock = input.closest('.issue-item');

        dropdown.classList.remove('show');
        if (itemBlock) itemBlock.classList.remove('cell-dropdown-open');

        if (!input.value.trim()) {
            hidden.value = '';
            if (itemBlock) {
                const stockEl = itemBlock.querySelector('.stock-display');
                if (stockEl) stockEl.textContent = '0.00';
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
        const itemBlock = input.closest('.issue-item');
        if (itemBlock) itemBlock.classList.remove('cell-dropdown-open');
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
            const itemBlock = dd.closest('.issue-item');
            if (itemBlock) itemBlock.classList.remove('cell-dropdown-open');
        });
    }
});

function addMaterialRow() {
    var body = document.getElementById('materialBody');
    var div = document.createElement('div');
    div.innerHTML = makeRow(rowIdx, '', 1);
    body.appendChild(div.firstElementChild);
    rowIdx++;
}

function removeRow(btn) {
    var body = document.getElementById('materialBody');
    var items = body.querySelectorAll('.issue-item');
    if (items.length <= 1) { alert('Need at least one material'); return; }
    btn.closest('.issue-item').remove();
    updateModalTotal();
}

function calcRowTotal(block) {
    var qty = parseFloat(block.querySelector('.qty').value) || 0;
    var price = parseFloat(block.querySelector('.unit-price').value) || 0;
    block.querySelector('.row-total').value = (qty * price).toFixed(2);
    updateModalTotal();
}

function updateModalTotal() {
    var grand = 0;
    document.querySelectorAll('#materialBody .issue-item').forEach(function(b) {
        var qty = parseFloat(b.querySelector('.qty').value) || 0;
        var price = parseFloat(b.querySelector('.unit-price').value) || 0;
        grand += qty * price;
    });
    var el = document.getElementById('modalTotalValue');
    if (el) el.textContent = 'Total: Rs ' + grand.toLocaleString('en-US', {minimumFractionDigits: 2});
}

function resetModal() {
    document.getElementById('modalTitleText').textContent = 'New Issuance';
    document.getElementById('submitBtnText').textContent = 'Issue Materials';
    document.getElementById('edit_id').value = 0;
    document.getElementById('issue_date').value = '<?php echo date("Y-m-d"); ?>';
    document.getElementById('notes').value = '';
    document.getElementById('materialBody').innerHTML = '';
    rowIdx = 0;
    addMaterialRow();
    updateModalTotal();
}

function validateForm() {
    var date = document.getElementById('issue_date').value;
    if (!date) { alert('Please select issue date.'); return false; }
    var blocks = document.querySelectorAll('#materialBody .issue-item');
    if (blocks.length === 0) { alert('Please add at least one material.'); return false; }
    var materialsArr = [];
    var ok = true;
    blocks.forEach(function(b) {
        var matId = b.querySelector('.material-id-input').value;
        var qty = parseFloat(b.querySelector('.qty').value) || 0;
        var up = parseFloat(b.querySelector('.unit-price').value) || 0;
        var stock = parseFloat(b.querySelector('.stock-display').textContent) || 0;
        if (!matId) { ok = false; return; }
        if (qty <= 0) { ok = false; return; }
        if (qty > stock) { alert('Issue qty exceeds available stock!'); ok = false; return; }
        materialsArr.push({material_id: parseInt(matId), quantity: qty, unit_price: up});
    });
    if (!ok || materialsArr.length === 0) { alert('Please select a valid raw material for all rows.'); return false; }
    document.getElementById('materials_json').value = JSON.stringify(materialsArr);
    return true;
}

$(document).ready(function() {
    <?php if ($editIssue): ?>
    (function() {
        var ei = <?php echo json_encode($editIssue); ?>;
        document.getElementById('modalTitleText').textContent = 'Edit Issuance';
        document.getElementById('submitBtnText').textContent = 'Update Issuance';
        document.getElementById('edit_id').value = ei.id;
        document.getElementById('issue_date').value = ei.issue_date;
        document.getElementById('notes').value = ei.notes || '';
        document.getElementById('materialBody').innerHTML = '';
        rowIdx = 0;
        editItemsData.forEach(function(item) {
            var body = document.getElementById('materialBody');
            var div = document.createElement('div');
            div.innerHTML = makeRow(rowIdx, item.material_id, item.quantity, parseFloat(item.unit_price || 0));
            var block = div.firstElementChild;
            body.appendChild(block);
            block.querySelector('.stock-display').textContent = parseFloat(item.current_stock || 0).toFixed(2);
            rowIdx++;
        });
        updateModalTotal();
    })();
    <?php endif; ?>
});
</script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $(document).on('click', '.view-issue-btn', function() {
        var issueNo = $(this).data('issue-no');
        var issueDate = $(this).data('issue-date');
        var notes = $(this).data('notes');
        var items = $(this).data('items');

        $('#viewIssueNo').text(issueNo);
        $('#viewModalIssueNo').text(issueNo);
        $('#viewModalIssueDate').text(issueDate);
        $('#viewModalNotes').text(notes);

        var tbody = $('#viewIssueItemsBody');
        tbody.empty();

        var totalQty = 0;
        var totalVal = 0;

        if (Array.isArray(items) && items.length > 0) {
            items.forEach(function(item, idx) {
                var qty = parseFloat(item.quantity) || 0;
                var price = parseFloat(item.unit_price) || 0;
                var total = parseFloat(item.total) || (qty * price);

                totalQty += qty;
                totalVal += total;

                var tr = '<tr>' +
                    '<td>' + (idx + 1) + '</td>' +
                    '<td><span class="badge bg-secondary">' + (item.material_code || '-') + '</span></td>' +
                    '<td class="fw-bold">' + (item.material_name || 'Material') + '</td>' +
                    '<td class="text-end fw-bold text-primary">' + qty.toFixed(2) + '</td>' +
                    '<td>' + (item.unit || 'units') + '</td>' +
                    '<td class="text-end">' + price.toLocaleString('en-US', {minimumFractionDigits: 2}) + '</td>' +
                    '<td class="text-end fw-bold text-success">' + total.toLocaleString('en-US', {minimumFractionDigits: 2}) + '</td>' +
                    '</tr>';
                tbody.append(tr);
            });
        } else {
            tbody.append('<tr><td colspan="7" class="text-center text-muted">No items found</td></tr>');
        }

        $('#viewModalTotalQty').text(totalQty.toFixed(2));
        $('#viewModalTotalVal').text('Rs ' + totalVal.toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#viewIssueModal').modal('show');
    });

    $('#issueTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]],
        "language": {
            "search": "Search:",
            "lengthMenu": "Show _MENU_",
            "info": "Showing _START_ to _END_ of _TOTAL_",
            "emptyTable": "No issuances found"
        }
    });
    $('.delete-confirm').on('click', function(e) {
        if (!confirm('Delete this issuance? Stock will be restored.')) e.preventDefault();
    });
});

function printIssueVoucher() {
    var printContents = document.getElementById('viewIssuePrintArea').innerHTML;
    var issueNo = $('#viewIssueNo').text();
    var printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Material Issuance Voucher - ' + issueNo + '</title>');
    printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
    printWindow.document.write('<style>body{padding:25px;font-family:sans-serif;} .badge{border:1px solid #ccc;color:#000!important;background:none!important;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<div style="text-align:center;margin-bottom:20px;border-bottom:2px solid #333;padding-bottom:10px;">');
    printWindow.document.write('<h2>LONG LIFE ENTERPRISE</h2>');
    printWindow.document.write('<h4>RAW MATERIAL ISSUANCE VOUCHER</h4>');
    printWindow.document.write('</div>');
    printWindow.document.write(printContents);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    setTimeout(function() {
        printWindow.print();
        printWindow.close();
    }, 500);
}
</script>

<?php include '../includes/footer.php'; ?>
