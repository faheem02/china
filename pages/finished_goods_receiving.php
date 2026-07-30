<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Finished Goods Receiving';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $receipt = getRow("SELECT * FROM finished_goods_receipts WHERE id = ?", 'i', [$id]);
    if ($receipt) {
        $items = getRows("SELECT finished_good_id, quantity FROM finished_goods_receipt_items WHERE receipt_id = ?", 'i', [$id]);
        foreach ($items as $item) {
            modifyData("UPDATE finished_goods SET current_stock = current_stock - ? WHERE id = ?", 'di', [$item['quantity'], $item['finished_good_id']]);
        }
        modifyData("DELETE FROM finished_goods_receipt_items WHERE receipt_id = ?", 'i', [$id]);
        modifyData("DELETE FROM finished_goods_receipts WHERE id = ?", 'i', [$id]);
        setFlash('Receipt deleted! Stock restored.', 'success');
    } else {
        setFlash('Error deleting receipt!', 'danger');
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $receipt_date = trim($_POST['receipt_date']);
    $notes = trim($_POST['notes'] ?? '');

    $materials = [];
    if (!empty($_POST['materials_json'])) {
        $decoded = json_decode($_POST['materials_json'], true);
        if (is_array($decoded)) $materials = $decoded;
    }
    if (empty($materials)) $materials = $_POST['materials'] ?? [];

    if (empty($receipt_date)) {
        $message = 'Please select receipt date!';
        $messageType = 'danger';
    } else {
        $currentDateTime = getCurrentDateTime();
        $validMaterials = [];
        foreach ($materials as $m) {
            if (empty($m['material_id'])) continue;
            $qty = (float)($m['quantity'] ?? 0);
            if ($qty <= 0) continue;
            $validMaterials[] = $m;
        }
        $materials = $validMaterials;

        if (empty($materials)) {
            $message = 'Please add at least one finished good with valid quantity!';
            $messageType = 'danger';
        } else {
            if ($id > 0) {
                $oldItems = getRows("SELECT finished_good_id, quantity FROM finished_goods_receipt_items WHERE receipt_id = ?", 'i', [$id]);
                foreach ($oldItems as $oi) {
                    modifyData("UPDATE finished_goods SET current_stock = current_stock - ? WHERE id = ?", 'di', [$oi['quantity'], $oi['finished_good_id']]);
                }
                modifyData("DELETE FROM finished_goods_receipt_items WHERE receipt_id = ?", 'i', [$id]);
                modifyData("UPDATE finished_goods_receipts SET receipt_date=?, notes=?, date_time=? WHERE id=?",
                    'sssi', [$receipt_date, $notes, $currentDateTime, $id]);

                foreach ($materials as $m) {
                    $fid = (int)$m['material_id'];
                    $qty = (float)$m['quantity'];
                    $up = (float)($m['unit_price'] ?? 0);
                    $tot = $qty * $up;
                    insertData("INSERT INTO finished_goods_receipt_items (receipt_id, finished_good_id, quantity, unit_price, total, date_time) VALUES (?,?,?,?,?,?)",
                        'iiddds', [$id, $fid, $qty, $up, $tot, $currentDateTime]);
                    modifyData("UPDATE finished_goods SET current_stock = current_stock + ? WHERE id = ?", 'di', [$qty, $fid]);
                }
                setFlash('Receipt updated! Stock adjusted.', 'success');
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $receipt_no = generateCode('FGR');
                $result = insertData("INSERT INTO finished_goods_receipts (receipt_no, receipt_date, notes, status, date_time) VALUES (?,?,?,?,?)",
                    'sssss', [$receipt_no, $receipt_date, $notes, 'completed', $currentDateTime]);

                if ($result !== false) {
                    foreach ($materials as $m) {
                        $fid = (int)$m['material_id'];
                        $qty = (float)$m['quantity'];
                        $up = (float)($m['unit_price'] ?? 0);
                        $tot = $qty * $up;
                        insertData("INSERT INTO finished_goods_receipt_items (receipt_id, finished_good_id, quantity, unit_price, total, date_time) VALUES (?,?,?,?,?,?)",
                            'iiddds', [$result, $fid, $qty, $up, $tot, $currentDateTime]);
                        modifyData("UPDATE finished_goods SET current_stock = current_stock + ? WHERE id = ?", 'di', [$qty, $fid]);
                    }
                    setFlash('Receipt created! Stock added.', 'success');
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    $message = 'Error creating receipt!';
                    $messageType = 'danger';
                }
            }
        }
    }
}

$receipts = getRows("SELECT fgr.*,
    (SELECT COUNT(*) FROM finished_goods_receipt_items WHERE receipt_id = fgr.id) as item_count
    FROM finished_goods_receipts fgr ORDER BY fgr.id DESC");

$finishedGoodsList = getRows("SELECT id, product_code, product_name, unit, cost_price, current_stock
    FROM finished_goods WHERE status = 'active' ORDER BY product_name");

$editReceipt = null;
$editItems = [];
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editReceipt = getRow("SELECT * FROM finished_goods_receipts WHERE id = ?", 'i', [(int)$_GET['edit']]);
    if ($editReceipt) {
        $editItems = getRows("SELECT fgri.*, fg.product_code, fg.product_name, fg.current_stock
            FROM finished_goods_receipt_items fgri
            LEFT JOIN finished_goods fg ON fgri.finished_good_id = fg.id
            WHERE fgri.receipt_id = ?", 'i', [$editReceipt['id']]);
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
.issue-item { border: 1px solid #e8eaed; border-radius: 8px; padding: 8px; margin-bottom: 6px; background: #fff; transition: box-shadow 0.2s; }
.issue-item:hover { box-shadow: 0 1px 6px rgba(0,0,0,0.08); }
.issue-item .stock-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: #e9ecef; color: #495057; white-space: nowrap; }
.issue-item .qty { font-size: 14px; font-weight: 600; color: #1a2332; }
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
        <h5 class="mb-0"><i class="fas fa-sign-in-alt me-2"></i>Finished Goods Receiving</h5>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#receiptModal" onclick="resetModal()">
            <i class="fas fa-plus me-2"></i>New Receiving
        </button>
    </div>

    <div class="card">
        <div class="card-header" style="background:#1a2332;color:#fff;">
            <i class="fas fa-list me-2"></i>Receiving History
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="receiptTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Receipt No</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($receipts) > 0): ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($receipts as $receipt): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($receipt['receipt_no']); ?></strong></td>
                                    <td><?php echo formatDate($receipt['receipt_date']); ?></td>
                                    <td><span class="badge bg-success"><?php echo $receipt['item_count']; ?> product(s)</span></td>
                                    <td><?php echo htmlspecialchars($receipt['notes'] ?: '-'); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?edit=<?php echo $receipt['id']; ?>" class="btn btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete=<?php echo $receipt['id']; ?>" class="btn btn-danger delete-confirm" title="Delete">
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

<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a2332;color:#fff;">
                <h5 class="modal-title">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    <span id="modalTitleText">New Receiving</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" onsubmit="return validateForm()">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_id" value="0">
                    <input type="hidden" name="materials_json" id="materials_json" value="">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Receipt Date *</label>
                            <input type="date" class="form-control" id="receipt_date" name="receipt_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notes</label>
                            <input type="text" class="form-control" id="notes" name="notes" placeholder="Optional notes...">
                        </div>
                    </div>

                    <div class="card mb-0">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-box me-2"></i>Finished Goods to Receive</span>
                            <button type="button" class="btn btn-sm btn-success" onclick="addMaterialRow()">
                                <i class="fas fa-plus me-1"></i>Add Product
                            </button>
                        </div>
                        <div class="card-body p-2" id="materialBody">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check-circle me-2"></i><span id="submitBtnText">Receive Goods</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var materialsData = <?php echo json_encode($finishedGoodsList); ?>;
var rowIdx = 0;
var editItemsData = <?php echo json_encode($editItems); ?>;

function makeRow(idx, matId, qty, unitPrice) {
    var h = '<div class="issue-item">';
    h += '<div class="row g-1 mb-1">';
    h += '<div class="col"><select class="form-select form-select-sm material-select" onchange="onMaterialSelect(this)">';
    h += '<option value="">Select Finished Good...</option>';
    materialsData.forEach(function(m) {
        var sel = (m.id == matId) ? ' selected' : '';
        h += '<option value="' + m.id + '"' + sel + ' data-stock="' + m.current_stock + '" data-cost="' + m.cost_price + '">' + m.product_code + ' - ' + m.product_name + '</option>';
    });
    h += '</select></div>';
    h += '<div class="col-auto d-flex align-items-center"><button type="button" class="btn btn-sm btn-outline-danger px-1 py-0" onclick="removeRow(this)"><i class="fas fa-times"></i></button></div>';
    h += '</div>';
    h += '<div class="row g-2 align-items-end">';
    h += '<div class="col-auto"><span class="stock-badge">Stock: <span class="stock-display fw-bold">' + parseFloat(0).toFixed(2) + '</span></span></div>';
    h += '<div class="col-md-3"><label class="small text-muted mb-0">Quantity *</label>';
    h += '<input type="number" step="0.01" min="0.01" class="form-control form-control-sm qty" value="' + (qty || 1) + '"></div>';
    h += '<div class="col-md-3"><label class="small text-muted mb-0">Unit Price</label>';
    h += '<input type="number" step="0.01" min="0" class="form-control form-control-sm unit-price" value="' + (unitPrice || 0) + '"></div>';
    h += '<div class="col-md-3"><label class="small text-muted mb-0">Total</label>';
    h += '<input type="text" class="form-control form-control-sm row-total" value="0.00" readonly></div>';
    h += '</div></div>';
    return h;
}

function addMaterialRow() {
    var body = document.getElementById('materialBody');
    var div = document.createElement('div');
    div.innerHTML = makeRow(rowIdx, '', 1, 0);
    body.appendChild(div.firstElementChild);
    rowIdx++;
}

function removeRow(btn) {
    var body = document.getElementById('materialBody');
    var items = body.querySelectorAll('.issue-item');
    if (items.length <= 1) { alert('Need at least one product'); return; }
    btn.closest('.issue-item').remove();
}

function onMaterialSelect(el) {
    var block = el.closest('.issue-item');
    var opt = el.options[el.selectedIndex];
    block.querySelector('.stock-display').textContent = parseFloat(opt.dataset.stock || 0).toFixed(2);
    var cost = parseFloat(opt.dataset.cost || 0);
    block.querySelector('.unit-price').value = cost.toFixed(2);
    calcRowTotal(block);
}

function calcRowTotal(block) {
    var qty = parseFloat(block.querySelector('.qty').value) || 0;
    var price = parseFloat(block.querySelector('.unit-price').value) || 0;
    block.querySelector('.row-total').value = (qty * price).toFixed(2);
}

function resetModal() {
    document.getElementById('modalTitleText').textContent = 'New Receiving';
    document.getElementById('submitBtnText').textContent = 'Receive Goods';
    document.getElementById('edit_id').value = 0;
    document.getElementById('receipt_date').value = '<?php echo date("Y-m-d"); ?>';
    document.getElementById('notes').value = '';
    document.getElementById('materialBody').innerHTML = '';
    rowIdx = 0;
    addMaterialRow();
}

function validateForm() {
    var date = document.getElementById('receipt_date').value;
    if (!date) { alert('Please select receipt date.'); return false; }
    var blocks = document.querySelectorAll('#materialBody .issue-item');
    if (blocks.length === 0) { alert('Please add at least one product.'); return false; }
    var materialsArr = [];
    var ok = true;
    blocks.forEach(function(b) {
        var matId = b.querySelector('.material-select').value;
        var qty = parseFloat(b.querySelector('.qty').value) || 0;
        var up = parseFloat(b.querySelector('.unit-price').value) || 0;
        if (!matId) { ok = false; return; }
        if (qty <= 0) { ok = false; return; }
        materialsArr.push({material_id: parseInt(matId), quantity: qty, unit_price: up});
    });
    if (!ok || materialsArr.length === 0) { alert('Please check all product rows.'); return false; }
    document.getElementById('materials_json').value = JSON.stringify(materialsArr);
    return true;
}

$(document).ready(function() {
    <?php if ($editReceipt): ?>
    (function() {
        var ei = <?php echo json_encode($editReceipt); ?>;
        document.getElementById('modalTitleText').textContent = 'Edit Receiving';
        document.getElementById('submitBtnText').textContent = 'Update Receiving';
        document.getElementById('edit_id').value = ei.id;
        document.getElementById('receipt_date').value = ei.receipt_date;
        document.getElementById('notes').value = ei.notes || '';
        document.getElementById('materialBody').innerHTML = '';
        rowIdx = 0;
        editItemsData.forEach(function(item) {
            var body = document.getElementById('materialBody');
            var div = document.createElement('div');
            div.innerHTML = makeRow(rowIdx, item.finished_good_id, item.quantity, item.unit_price);
            var block = div.firstElementChild;
            body.appendChild(block);
            block.querySelector('.stock-display').textContent = parseFloat(item.current_stock || 0).toFixed(2);
            block.querySelector('.row-total').value = parseFloat(item.total || 0).toFixed(2);
            rowIdx++;
        });
        $('#receiptModal').modal('show');
    })();
    <?php endif; ?>

    $('#receiptTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]],
        "language": {
            "search": "Search:",
            "lengthMenu": "Show _MENU_",
            "info": "Showing _START_ to _END_ of _TOTAL_",
            "emptyTable": "No receipts found"
        }
    });
    $('.delete-confirm').on('click', function(e) {
        if (!confirm('Delete this receipt? Stock will be restored.')) e.preventDefault();
    });
});
</script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<?php include '../includes/footer.php'; ?>
