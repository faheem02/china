<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Raw Materials';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    $checkSales = getRow("SELECT COUNT(*) as count FROM sale_items WHERE product_id = ? AND product_type = 'raw_material'", 'i', [$id]);
    if ($checkSales && $checkSales['count'] > 0) {
        setFlash('Cannot delete raw material! It has sales records.', 'danger');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $result = modifyData("DELETE FROM raw_materials WHERE id = ?", 'i', [$id]);
    
    if ($result !== false) {
        setFlash('Raw material deleted successfully!', 'success');
    } else {
        setFlash('Error deleting raw material!', 'danger');
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$fromDate = isset($_GET['from_date']) && $_GET['from_date'] !== '' ? trim($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) && $_GET['to_date'] !== '' ? trim($_GET['to_date']) : '';
$isLowStock = isset($_GET['low_stock']) && $_GET['low_stock'] == '1';

// Build query with optional date and low stock filter
$where = [];
$params = [];
$types = '';

if ($isLowStock) {
    $where[] = "((r.current_stock <= r.minimum_stock AND r.minimum_stock > 0) OR r.current_stock <= 0)";
}

if ($fromDate !== '') {
    $where[] = "DATE(r.date_time) >= ?";
    $params[] = $fromDate;
    $types .= 's';
}
if ($toDate !== '') {
    $where[] = "DATE(r.date_time) <= ?";
    $params[] = $toDate;
    $types .= 's';
}

$whereSql = !empty($where) ? " WHERE " . implode(' AND ', $where) : "";
$sql = "SELECT r.*, 
    CASE 
        WHEN r.supplier_type = 'chinese' THEN (SELECT supplier_name FROM chinese_suppliers WHERE id = r.supplier_id)
        WHEN r.supplier_type = 'local' THEN (SELECT supplier_name FROM local_suppliers WHERE id = r.supplier_id)
        ELSE NULL
    END as supplier_name
    FROM raw_materials r " . $whereSql . " ORDER BY r.id DESC";

$materials = !empty($params) ? getRows($sql, $types, $params) : getRows($sql);

// Build print URL preserving active filter
$printParams = [];
if ($isLowStock) $printParams['low_stock'] = '1';
if ($fromDate !== '') $printParams['from_date'] = $fromDate;
if ($toDate !== '') $printParams['to_date'] = $toDate;
$printUrl = 'raw_materials_print.php' . (!empty($printParams) ? '?' . http_build_query($printParams) : '');

// ── Summary stats for top cards ──
if ($fromDate !== '' || $toDate !== '') {
    $totalStockQty = 0;
    $totalStockCostValue = 0;
    $totalStockSaleValue = 0;
    foreach ($materials as $m) {
        $st = (float)$m['current_stock'];
        $cost = (float)$m['purchase_price_pkr'];
        $sell = (float)($m['selling_price'] ?: $cost);
        $totalStockQty += $st;
        $totalStockCostValue += ($st * $cost);
        $totalStockSaleValue += ($st * $sell);
    }

    if ($fromDate !== '' && $toDate !== '') {
        $miStats = getRow("SELECT
                COALESCE(SUM(mii.quantity), 0) AS issued_qty,
                COALESCE(SUM(mii.total), 0) AS issued_value
            FROM material_issue_items mii
            INNER JOIN material_issues mi ON mi.id = mii.issue_id
            WHERE mi.issue_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate]);
    } elseif ($fromDate !== '') {
        $miStats = getRow("SELECT
                COALESCE(SUM(mii.quantity), 0) AS issued_qty,
                COALESCE(SUM(mii.total), 0) AS issued_value
            FROM material_issue_items mii
            INNER JOIN material_issues mi ON mi.id = mii.issue_id
            WHERE mi.issue_date >= ?", 's', [$fromDate]);
    } else {
        $miStats = getRow("SELECT
                COALESCE(SUM(mii.quantity), 0) AS issued_qty,
                COALESCE(SUM(mii.total), 0) AS issued_value
            FROM material_issue_items mii
            INNER JOIN material_issues mi ON mi.id = mii.issue_id
            WHERE mi.issue_date <= ?", 's', [$toDate]);
    }
    $totalIssuedQty = (float)($miStats['issued_qty'] ?? 0);
    $totalIssuedValue = (float)($miStats['issued_value'] ?? 0);
} else {
    $rmStats = getRow("SELECT
            COALESCE(SUM(current_stock), 0) AS stock_qty,
            COALESCE(SUM(current_stock * purchase_price_pkr), 0) AS stock_cost_value,
            COALESCE(SUM(current_stock * COALESCE(NULLIF(selling_price, 0), purchase_price_pkr)), 0) AS stock_sale_value
        FROM raw_materials WHERE status = 'active'");

    $miStats = getRow("SELECT
            COALESCE(SUM(mii.quantity), 0) AS issued_qty,
            COALESCE(SUM(mii.total), 0) AS issued_value
        FROM material_issue_items mii
        INNER JOIN material_issues mi ON mi.id = mii.issue_id");

    $totalStockQty = (float)($rmStats['stock_qty'] ?? 0);
    $totalStockCostValue = (float)($rmStats['stock_cost_value'] ?? 0);
    $totalStockSaleValue = (float)($rmStats['stock_sale_value'] ?? 0);
    $totalIssuedQty = (float)($miStats['issued_qty'] ?? 0);
    $totalIssuedValue = (float)($miStats['issued_value'] ?? 0);
}

// Low stock statistics across all active raw materials
$lowStockStats = getRow("SELECT 
        COUNT(CASE WHEN (current_stock <= minimum_stock AND minimum_stock > 0) OR current_stock <= 0 THEN 1 END) AS total_low,
        COUNT(CASE WHEN current_stock <= 0 THEN 1 END) AS out_of_stock,
        COUNT(CASE WHEN current_stock > 0 AND current_stock <= minimum_stock AND minimum_stock > 0 THEN 1 END) AS low_stock
    FROM raw_materials WHERE status = 'active'");
$totalLowAlerts = (int)($lowStockStats['total_low'] ?? 0);
$outOfStockCount = (int)($lowStockStats['out_of_stock'] ?? 0);
$lowStockCount = (int)($lowStockStats['low_stock'] ?? 0);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
@media print {
    #sidebar, .navbar-custom, .btn, .dataTables_filter, .dataTables_length, .dataTables_paginate, .dataTables_info, .no-print {
        display: none !important;
    }
    #content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
    .card { border: none !important; box-shadow: none !important; }
    .card-header { background: #f8f9fa !important; color: #000 !important; border-bottom: 2px solid #000 !important; }
}
</style>

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

<div class="row mb-3 align-items-center">
    <div class="col-md-7 mb-2 mb-md-0 d-flex flex-wrap gap-2 align-items-center">
        <a href="add_raw_materials.php" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>Add New Material
        </a>
        <a href="raw_materials.php?low_stock=1<?php echo ($fromDate ? '&from_date=' . urlencode($fromDate) : '') . ($toDate ? '&to_date=' . urlencode($toDate) : ''); ?>" 
           class="btn <?php echo $isLowStock ? 'btn-warning text-dark fw-bold' : 'btn-outline-warning text-dark'; ?>">
            <i class="fas fa-exclamation-triangle me-1"></i>Low Stock Items
            <?php if ($totalLowAlerts > 0): ?>
                <span class="badge bg-danger text-white ms-1"><?php echo $totalLowAlerts; ?></span>
            <?php endif; ?>
        </a>
        <?php if ($isLowStock || $fromDate !== '' || $toDate !== ''): ?>
            <a href="raw_materials.php" class="btn btn-outline-secondary btn-sm" title="Show All Materials">
                <i class="fas fa-undo me-1"></i>Show All
            </a>
        <?php endif; ?>
    </div>
    <div class="col-md-5 text-md-end no-print">
        <a href="<?php echo $printUrl; ?>" target="_blank" class="btn btn-danger">
            <i class="fas fa-print me-2"></i>Print Report
        </a>
    </div>
</div>

<!-- Date Filter Card -->
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body py-2 px-3">
        <form method="GET" action="raw_materials.php" class="row g-2 align-items-end">
            <?php if ($isLowStock): ?>
                <input type="hidden" name="low_stock" value="1">
            <?php endif; ?>
            <div class="col-md-4 col-sm-6">
                <label class="form-label small mb-1 fw-semibold text-muted">From Date</label>
                <input type="date" class="form-control form-control-sm" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
            </div>
            <div class="col-md-4 col-sm-6">
                <label class="form-label small mb-1 fw-semibold text-muted">To Date</label>
                <input type="date" class="form-control form-control-sm" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
            </div>
            <div class="col-md-4 col-sm-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                    <i class="fas fa-search me-1"></i>Filter
                </button>
                <?php if ($fromDate !== '' || $toDate !== '' || $isLowStock): ?>
                    <a href="raw_materials.php" class="btn btn-outline-secondary btn-sm" title="Clear filter">
                        <i class="fas fa-undo me-1"></i>Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-2 mb-3">
    <div class="col-xl col-md-4 col-sm-6 mb-2">
        <div class="card h-100 shadow-sm" style="border-left: 4px solid #6c757d;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total Stock Qty</div>
                        <h4 class="mb-0 fw-bold"><?php echo number_format($totalStockQty, 2); ?></h4>
                        <div class="text-muted small" style="font-size:11px;"><?php echo ($fromDate || $toDate || $isLowStock) ? 'Filtered records' : 'Available in stock'; ?></div>
                    </div>
                    <i class="fas fa-cubes fa-2x text-muted opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-sm-6 mb-2">
        <a href="raw_materials.php?low_stock=1" class="text-decoration-none">
            <div class="card h-100 shadow-sm <?php echo $isLowStock ? 'bg-warning bg-opacity-10' : ''; ?>" style="border-left: 4px solid <?php echo $totalLowAlerts > 0 ? '#ffc107' : '#198754'; ?>;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Low / Out Stock</div>
                            <h4 class="mb-0 fw-bold <?php echo $totalLowAlerts > 0 ? 'text-warning text-dark' : 'text-success'; ?>">
                                <?php echo $totalLowAlerts; ?> Items
                            </h4>
                            <div class="text-muted small" style="font-size:11px;">
                                <?php if ($outOfStockCount > 0): ?>
                                    <span class="text-danger fw-semibold"><?php echo $outOfStockCount; ?> Out</span> &middot; 
                                <?php endif; ?>
                                <span><?php echo $lowStockCount; ?> Low Stock</span>
                            </div>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-2x <?php echo $totalLowAlerts > 0 ? 'text-warning' : 'text-success'; ?>"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl col-md-4 col-sm-6 mb-2">
        <div class="card h-100 shadow-sm" style="border-left: 4px solid #dc3545;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Stock Value (Cost)</div>
                        <h4 class="mb-0 fw-bold text-danger">Rs. <?php echo formatCurrency($totalStockCostValue); ?></h4>
                        <div class="text-muted small" style="font-size:11px;"><?php echo ($fromDate || $toDate || $isLowStock) ? 'Cost value (filtered)' : 'At purchase cost'; ?></div>
                    </div>
                    <i class="fas fa-sack-dollar fa-2x text-muted opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-sm-6 mb-2">
        <div class="card h-100 shadow-sm" style="border-left: 4px solid #28a745;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Stock Value (Selling)</div>
                        <h4 class="mb-0 fw-bold text-success">Rs. <?php echo formatCurrency($totalStockSaleValue); ?></h4>
                        <div class="text-muted small" style="font-size:11px;"><?php echo ($fromDate || $toDate || $isLowStock) ? 'Sale value (filtered)' : 'Estimated sale value'; ?></div>
                    </div>
                    <i class="fas fa-tags fa-2x text-muted opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-sm-6 mb-2">
        <div class="card h-100 shadow-sm" style="border-left: 4px solid #0d6efd;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Issued Value</div>
                        <h4 class="mb-0 fw-bold text-primary">Rs. <?php echo formatCurrency($totalIssuedValue); ?></h4>
                        <div class="text-muted small" style="font-size:11px;"><?php echo number_format($totalIssuedQty, 2); ?> qty issued</div>
                    </div>
                    <i class="fas fa-sign-out-alt fa-2x text-muted opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle" id="materialTable">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Code</th>
                                <th>Material Name</th>
                                <th>Category</th>
                                <th>Unit</th>
                                <th class="text-center">Stock</th>
                                <th class="text-end">Price (PKR)</th>
                                <th class="text-end">Price (CNY)</th>
                                <th class="text-end">Cost Value</th>
                                <th class="text-end">Selling Value</th>
                                <th>Supplier</th>
                                <th class="text-center" style="width: 130px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($materials) > 0): ?>
                                <?php foreach ($materials as $material): ?>
                                    <tr>
                                        <td><?php echo $material['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($material['material_code']); ?></strong>
                                            <?php if (!empty($material['date_time'])): ?>
                                                <small class="text-muted d-block" style="font-size:11px;">
                                                    <i class="far fa-calendar-alt me-1"></i><?php echo date('d-m-Y', strtotime($material['date_time'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($material['material_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($material['category'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($material['unit'] ?: 'units'); ?></td>
                                        <td class="text-center">
                                            <?php 
                                            $cStock = (float)$material['current_stock'];
                                            $mStock = (float)$material['minimum_stock'];
                                            $isOut = ($cStock <= 0);
                                            $isLow = (!$isOut && $mStock > 0 && $cStock <= $mStock);
                                            ?>
                                            <span class="fw-bold <?php echo $isOut ? 'text-danger' : ($isLow ? 'text-danger' : 'text-primary'); ?>">
                                                <?php echo number_format($cStock, 2); ?>
                                            </span>
                                            <?php if ($isOut): ?>
                                                <div class="mt-1"><span class="badge bg-danger" style="font-size:10px;">Out of Stock</span></div>
                                            <?php elseif ($isLow): ?>
                                                <div class="mt-1"><span class="badge bg-warning text-dark" style="font-size:10px;"><i class="fas fa-exclamation-triangle me-1"></i>Low Stock</span></div>
                                                <small class="text-muted d-block" style="font-size:10px;">Min: <?php echo number_format($mStock, 2); ?></small>
                                            <?php elseif ($mStock > 0): ?>
                                                <small class="text-muted d-block" style="font-size:10px;">Min: <?php echo number_format($mStock, 2); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-semibold">Rs. <?php echo formatCurrency($material['purchase_price_pkr']); ?></td>
                                        <td class="text-end">
                                            <?php if ($material['supplier_type'] == 'chinese' && !empty($material['purchase_price_cny'])): ?>
                                                <span class="badge bg-info text-dark">¥ <?php echo number_format($material['purchase_price_cny'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">Rs. <?php echo formatCurrency($material['current_stock'] * $material['purchase_price_pkr']); ?></td>
                                        <td class="text-end">Rs. <?php echo formatCurrency($material['current_stock'] * ($material['selling_price'] ?: $material['purchase_price_pkr'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $material['supplier_type'] == 'chinese' ? 'info' : 'success'; ?>">
                                                <?php echo ucfirst($material['supplier_type']); ?>
                                            </span>
                                            <?php if ($material['supplier_name']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($material['supplier_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="raw_materials_print.php?id=<?php echo $material['id']; ?>" target="_blank" class="btn btn-info text-white" title="Print Item Slip">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                                <a href="add_raw_materials.php?edit=<?php echo $material['id']; ?>" class="btn btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?delete=<?php echo $material['id']; ?>" class="btn btn-danger delete-confirm" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-bold">
                                <td colspan="8" class="text-end">Total</td>
                                <td class="text-end">Rs. <?php echo formatCurrency($totalStockCostValue); ?></td>
                                <td class="text-end">Rs. <?php echo formatCurrency($totalStockSaleValue); ?></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#materialTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]],
        "language": {
            "search": "Search Materials:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ materials",
            "emptyTable": "No raw materials found"
        }
    });

    $(document).on('click', '.delete-confirm', function(e) {
        if (!confirm('Are you sure you want to delete this raw material?')) {
            e.preventDefault();
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>