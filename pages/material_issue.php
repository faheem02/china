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
        foreach ($materials as $m) {
            if (empty($m['material_id'])) continue;
            $qty = (float)($m['quantity'] ?? 0);
            if ($qty <= 0) continue;
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
                modifyData("UPDATE material_issues SET issue_date=?, notes=?, date_time=? WHERE id=?",
                    'sssi', [$issue_date, $notes, $currentDateTime, $id]);

                foreach ($materials as $m) {
                    $mid = (int)$m['material_id'];
                    $qty = (float)$m['quantity'];
                    insertData("INSERT INTO material_issue_items (issue_id, material_id, quantity, unit_price, total, date_time) VALUES (?,?,?,?,?,?)",
                        'iiddds', [$id, $mid, $qty, 0, 0, $currentDateTime]);
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
                        'ssssssddss', [$issue_no, $issue_date, null, '', $notes, 'cash', 0, 0, 'completed', $currentDateTime]);

                    if ($result !== false) {
                        foreach ($materials as $m) {
                            $mid = (int)$m['material_id'];
                            $qty = (float)$m['quantity'];
                            insertData("INSERT INTO material_issue_items (issue_id, material_id, quantity, unit_price, total, date_time) VALUES (?,?,?,?,?,?)",
                                'iiddds', [$result, $mid, $qty, 0, 0, $currentDateTime]);
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

$materials = getRows("SELECT id, material_code, material_name, unit, current_stock 
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
        <h5 class="mb-0"><i class="fas fa-sign-out-alt me-2"></i>Material Issuance</h5>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#issueModal" onclick="resetModal()">
            <i class="fas fa-plus me-2"></i>New Issuance
        </button>
    </div>

    <div class="card">
        <div class="card-header" style="background:#1a2332;color:#fff;">
            <i class="fas fa-list me-2"></i>Issuance History
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="issueTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Issue No</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($issues) > 0): ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($issues as $issue): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($issue['issue_no']); ?></strong></td>
                                    <td><?php echo formatDate($issue['issue_date']); ?></td>
                                    <td><span class="badge bg-primary"><?php echo $issue['item_count']; ?> material(s)</span></td>
                                    <td><?php echo htmlspecialchars($issue['notes'] ?: '-'); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
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
                            <button type="button" class="btn btn-sm btn-success" onclick="addMaterialRow()">
                                <i class="fas fa-plus me-1"></i>Add Material
                            </button>
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

function makeRow(idx, matId, qty) {
    var h = '<div class="issue-item">';
    h += '<div class="row g-1 mb-1">';
    h += '<div class="col"><select class="form-select form-select-sm material-select" onchange="onMaterialSelect(this)">';
    h += '<option value="">Select Material...</option>';
    materialsData.forEach(function(m) {
        var sel = (m.id == matId) ? ' selected' : '';
        h += '<option value="' + m.id + '"' + sel + ' data-stock="' + m.current_stock + '">' + m.material_code + ' - ' + m.material_name + '</option>';
    });
    h += '</select></div>';
    h += '<div class="col-auto d-flex align-items-center"><button type="button" class="btn btn-sm btn-outline-danger px-1 py-0" onclick="removeRow(this)"><i class="fas fa-times"></i></button></div>';
    h += '</div>';
    h += '<div class="row g-2 align-items-end">';
    h += '<div class="col-auto"><span class="stock-badge">Stock: <span class="stock-display fw-bold">' + parseFloat(0).toFixed(2) + '</span></span></div>';
    h += '<div class="col-md-4"><label class="small text-muted mb-0">Quantity *</label>';
    h += '<input type="number" step="0.01" min="0.01" class="form-control form-control-sm qty" value="' + (qty || 1) + '"></div>';
    h += '</div></div>';
    return h;
}

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
}

function onMaterialSelect(el) {
    var block = el.closest('.issue-item');
    var opt = el.options[el.selectedIndex];
    block.querySelector('.stock-display').textContent = parseFloat(opt.dataset.stock || 0).toFixed(2);
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
}

function validateForm() {
    var date = document.getElementById('issue_date').value;
    if (!date) { alert('Please select issue date.'); return false; }
    var blocks = document.querySelectorAll('#materialBody .issue-item');
    if (blocks.length === 0) { alert('Please add at least one material.'); return false; }
    var materialsArr = [];
    var ok = true;
    blocks.forEach(function(b) {
        var matId = b.querySelector('.material-select').value;
        var qty = parseFloat(b.querySelector('.qty').value) || 0;
        var stock = parseFloat(b.querySelector('.stock-display').textContent) || 0;
        if (!matId) { ok = false; return; }
        if (qty <= 0) { ok = false; return; }
        if (qty > stock) { alert('Issue qty exceeds available stock!'); ok = false; return; }
        materialsArr.push({material_id: parseInt(matId), quantity: qty});
    });
    if (!ok || materialsArr.length === 0) { alert('Please check all material rows.'); return false; }
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
            div.innerHTML = makeRow(rowIdx, item.material_id, item.quantity);
            var block = div.firstElementChild;
            body.appendChild(block);
            block.querySelector('.stock-display').textContent = parseFloat(item.current_stock || 0).toFixed(2);
            rowIdx++;
        });
    })();
    <?php endif; ?>
});
</script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
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
</script>

<?php include '../includes/footer.php'; ?>
