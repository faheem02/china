<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Finished Goods';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $result = modifyData("DELETE FROM finished_goods WHERE id = ?", 'i', [$id]);
    
    if ($result !== false) {
        setFlash('Product deleted successfully!', 'success');
    } else {
        setFlash('Error deleting product!', 'danger');
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$fromDate = isset($_GET['from_date']) && $_GET['from_date'] !== '' ? trim($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) && $_GET['to_date'] !== '' ? trim($_GET['to_date']) : '';

// Build query with optional date filter
$where = [];
$params = [];
$types = '';

if ($fromDate !== '') {
    $where[] = "DATE(fg.date_time) >= ?";
    $params[] = $fromDate;
    $types .= 's';
}
if ($toDate !== '') {
    $where[] = "DATE(fg.date_time) <= ?";
    $params[] = $toDate;
    $types .= 's';
}

$whereSql = !empty($where) ? " WHERE " . implode(' AND ', $where) : "";
$sql = "SELECT fg.*,
        (SELECT cs.supplier_name
            FROM import_purchase_items ipi
            INNER JOIN import_purchases ip ON ip.id = ipi.purchase_id
            INNER JOIN chinese_suppliers cs ON cs.id = ip.supplier_id
            WHERE ipi.item_type = 'finished_good' AND ipi.material_id = fg.id
            ORDER BY ipi.id DESC LIMIT 1) AS chinese_supplier,
        (SELECT ls.supplier_name
            FROM local_purchase_items lpi
            INNER JOIN local_purchases lp ON lp.id = lpi.purchase_id
            INNER JOIN local_suppliers ls ON ls.id = lp.supplier_id
            WHERE lpi.item_type = 'finished_good' AND lpi.material_id = fg.id
            ORDER BY lpi.id DESC LIMIT 1) AS local_supplier,
        (SELECT COUNT(*) FROM import_purchase_items ipi
            WHERE ipi.item_type = 'finished_good' AND ipi.material_id = fg.id) AS import_count,
        (SELECT COUNT(*) FROM local_purchase_items lpi
            WHERE lpi.item_type = 'finished_good' AND lpi.material_id = fg.id) AS local_count
        FROM finished_goods fg" . $whereSql . " ORDER BY fg.id DESC";
$products = !empty($params) ? getRows($sql, $types, $params) : getRows($sql);

// Build print URL preserving active filter
$printParams = [];
if ($fromDate !== '') $printParams['from_date'] = $fromDate;
if ($toDate !== '') $printParams['to_date'] = $toDate;
$printUrl = 'finished_goods_print.php' . (!empty($printParams) ? '?' . http_build_query($printParams) : '');

// ── Summary stats for top cards ──
if ($fromDate !== '' || $toDate !== '') {
    // When date filtered, calculate values from the filtered products
    $totalStockQty = 0;
    $totalStockCostValue = 0;
    $totalStockSaleValue = 0;
    foreach ($products as $pr) {
        $st = (float)$pr['current_stock'];
        $totalStockQty += $st;
        $totalStockCostValue += ($st * (float)$pr['cost_price']);
        $totalStockSaleValue += ($st * (float)$pr['selling_price']);
    }

    // Receipts in this date range
    if ($fromDate !== '' && $toDate !== '') {
        $fgrStats = getRow("SELECT
                COALESCE(SUM(fgri.quantity), 0) AS received_qty,
                COALESCE(SUM(fgri.total), 0) AS received_value
            FROM finished_goods_receipt_items fgri
            INNER JOIN finished_goods_receipts fgr ON fgr.id = fgri.receipt_id
            WHERE fgr.receipt_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate]);
    } elseif ($fromDate !== '') {
        $fgrStats = getRow("SELECT
                COALESCE(SUM(fgri.quantity), 0) AS received_qty,
                COALESCE(SUM(fgri.total), 0) AS received_value
            FROM finished_goods_receipt_items fgri
            INNER JOIN finished_goods_receipts fgr ON fgr.id = fgri.receipt_id
            WHERE fgr.receipt_date >= ?", 's', [$fromDate]);
    } else {
        $fgrStats = getRow("SELECT
                COALESCE(SUM(fgri.quantity), 0) AS received_qty,
                COALESCE(SUM(fgri.total), 0) AS received_value
            FROM finished_goods_receipt_items fgri
            INNER JOIN finished_goods_receipts fgr ON fgr.id = fgri.receipt_id
            WHERE fgr.receipt_date <= ?", 's', [$toDate]);
    }
    $totalReceivedQty = (float)($fgrStats['received_qty'] ?? 0);
    $totalReceivedValue = (float)($fgrStats['received_value'] ?? 0);
} else {
    // All active finished goods overall
    $fgStats = getRow("SELECT
            COALESCE(SUM(current_stock), 0) AS stock_qty,
            COALESCE(SUM(current_stock * cost_price), 0) AS stock_cost_value,
            COALESCE(SUM(current_stock * selling_price), 0) AS stock_sale_value
        FROM finished_goods WHERE status = 'active'");

    $fgrStats = getRow("SELECT
            COALESCE(SUM(fgri.quantity), 0) AS received_qty,
            COALESCE(SUM(fgri.total), 0) AS received_value
        FROM finished_goods_receipt_items fgri
        INNER JOIN finished_goods_receipts fgr ON fgr.id = fgri.receipt_id");

    $totalStockQty = (float)($fgStats['stock_qty'] ?? 0);
    $totalStockCostValue = (float)($fgStats['stock_cost_value'] ?? 0);
    $totalStockSaleValue = (float)($fgStats['stock_sale_value'] ?? 0);
    $totalReceivedQty = (float)($fgrStats['received_qty'] ?? 0);
    $totalReceivedValue = (float)($fgrStats['received_value'] ?? 0);
}

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
    <div class="col-md-6 mb-2 mb-md-0">
        <a href="add_finished_goods.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Product
        </a>
    </div>
    <div class="col-md-6 text-md-end no-print">
        <a href="<?php echo $printUrl; ?>" target="_blank" class="btn btn-danger">
            <i class="fas fa-print me-2"></i>Print Report
        </a>
    </div>
</div>

<!-- Date Filter Card -->
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body py-2 px-3">
        <form method="GET" action="finished_goods.php" class="row g-2 align-items-end">
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
                <?php if ($fromDate !== '' || $toDate !== ''): ?>
                    <a href="finished_goods.php" class="btn btn-outline-secondary btn-sm" title="Clear filter">
                        <i class="fas fa-undo me-1"></i>Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-3">
    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
        <div class="card h-100" style="border-left: 4px solid #6c757d;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Finished Goods Stock Qty</div>
                        <h4 class="mb-0"><?php echo number_format($totalStockQty, 2); ?></h4>
                        <div class="text-muted small"><?php echo ($fromDate || $toDate) ? 'For filtered records' : 'Qty available in stock'; ?></div>
                    </div>
                    <i class="fas fa-boxes fa-2x text-muted"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
        <div class="card h-100" style="border-left: 4px solid #dc3545;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Stock Value (Cost)</div>
                        <h4 class="mb-0">Rs. <?php echo formatCurrency($totalStockCostValue); ?></h4>
                        <div class="text-muted small"><?php echo ($fromDate || $toDate) ? 'Cost value (filtered)' : 'Money lying in finished goods'; ?></div>
                    </div>
                    <i class="fas fa-sack-dollar fa-2x text-muted"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
        <div class="card h-100" style="border-left: 4px solid #28a745;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Stock Value (Selling)</div>
                        <h4 class="mb-0">Rs. <?php echo formatCurrency($totalStockSaleValue); ?></h4>
                        <div class="text-muted small"><?php echo ($fromDate || $toDate) ? 'Sale value (filtered)' : 'If all stock is sold'; ?></div>
                    </div>
                    <i class="fas fa-tags fa-2x text-muted"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card h-100" style="border-left: 4px solid #0d6efd;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total Received Value</div>
                        <h4 class="mb-0">Rs. <?php echo formatCurrency($totalReceivedValue); ?></h4>
                        <div class="text-muted small"><?php echo number_format($totalReceivedQty, 2); ?> qty received <?php echo ($fromDate || $toDate) ? '(in period)' : '(all time)'; ?></div>
                    </div>
                    <i class="fas fa-sign-in-alt fa-2x text-muted"></i>
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
                    <table class="table table-striped table-hover align-middle" id="productTable">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Code</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Source / Supplier</th>
                                <th>Unit</th>
                                <th class="text-center">Stock</th>
                                <th class="text-end">Selling Price</th>
                                <th class="text-end">Cost Price</th>
                                <th class="text-end">Selling Value</th>
                                <th class="text-end">Cost Value</th>
                                <th class="text-center" style="width: 130px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($products) > 0): ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?php echo $product['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($product['product_code']); ?></strong>
                                            <?php if (!empty($product['date_time'])): ?>
                                                <small class="text-muted d-block" style="font-size:11px;">
                                                    <i class="far fa-calendar-alt me-1"></i><?php echo date('d-m-Y', strtotime($product['date_time'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($product['product_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($product['category'] ?: '-'); ?></td>
                                        <td>
                                            <?php if (!empty($product['chinese_supplier'])): ?>
                                                <span class="badge bg-info text-dark" title="Received via import purchase from Chinese supplier"><i class="fas fa-plane me-1"></i>Chinese</span>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($product['chinese_supplier']); ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($product['local_supplier'])): ?>
                                                <span class="badge bg-success" title="Received via local purchase"><i class="fas fa-truck me-1"></i>Local</span>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($product['local_supplier']); ?></small>
                                            <?php endif; ?>
                                            <?php if (empty($product['chinese_supplier']) && empty($product['local_supplier'])): ?>
                                                <span class="badge bg-secondary" title="No purchase record found (added manually or received from production)"><i class="fas fa-industry me-1"></i>In-house / Not Recorded</span>
                                            <?php endif; ?>
                                            <?php if (!empty($product['import_count']) && !empty($product['local_count'])): ?>
                                                <small class="d-block text-muted">Both sources</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['unit'] ?: 'units'); ?></td>
                                        <td class="text-center fw-bold text-primary"><?php echo number_format($product['current_stock'], 2); ?></td>
                                        <td class="text-end">Rs. <?php echo formatCurrency($product['selling_price']); ?></td>
                                        <td class="text-end">Rs. <?php echo formatCurrency($product['cost_price']); ?></td>
                                        <td class="text-end text-success fw-bold">
                                            Rs. <?php echo formatCurrency($product['current_stock'] * $product['selling_price']); ?>
                                        </td>
                                        <td class="text-end text-danger fw-bold">
                                            Rs. <?php echo formatCurrency($product['current_stock'] * $product['cost_price']); ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="finished_goods_print.php?id=<?php echo $product['id']; ?>" target="_blank" class="btn btn-info text-white" title="Print Item Slip">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                                <a href="add_finished_goods.php?edit=<?php echo $product['id']; ?>" class="btn btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?delete=<?php echo $product['id']; ?>" class="btn btn-danger delete-confirm" title="Delete">
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
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#productTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]],
        "language": {
            "search": "Search Products:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ products",
            "emptyTable": "No finished goods found"
        }
    });

    $(document).on('click', '.delete-confirm', function(e) {
        if (!confirm('Are you sure you want to delete this finished good?')) {
            e.preventDefault();
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>