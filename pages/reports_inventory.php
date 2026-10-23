<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Stock Report';

$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');
$stockType = $_GET['stock_type'] ?? 'all';
if (!in_array($stockType, ['all', 'raw_materials', 'finished_goods', 'production'], true)) {
    $stockType = 'all';
}

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';

// Raw Materials Stock
$rmSql = "SELECT r.*, 
          CASE 
              WHEN r.supplier_type = 'chinese' THEN (SELECT supplier_name FROM chinese_suppliers WHERE id = r.supplier_id)
              WHEN r.supplier_type = 'local' THEN (SELECT supplier_name FROM local_suppliers WHERE id = r.supplier_id)
              ELSE NULL
          END as supplier_name
          FROM raw_materials r ORDER BY r.material_name";
$materials = getRows($rmSql);

// Finished Goods Stock
$fgSql = "SELECT * FROM finished_goods ORDER BY product_name";
$products = getRows($fgSql);

$productions = [];
if ($stockType === 'all' || $stockType === 'production') {
    $productions = getRows("SELECT po.*, fg.product_name, fg.product_code, fg.unit
        FROM production po
        LEFT JOIN finished_goods fg ON po.finished_good_id = fg.id
        WHERE po.production_date BETWEEN ? AND ?
        ORDER BY po.production_date DESC, po.id DESC", 'ss', [$fromDate, $toDate]);
}

// Recent stock movements (last 50 issues)
$movements = getRows("SELECT mii.*, m.material_name, mi.issue_no, mi.issue_date, mi.notes, mi.production_order_id FROM material_issue_items mii 
                      LEFT JOIN raw_materials m ON mii.material_id = m.id 
                      LEFT JOIN material_issues mi ON mii.issue_id = mi.id
                      WHERE DATE(mii.date_time) BETWEEN ? AND ?
                      ORDER BY mii.date_time DESC LIMIT 50",
                      'ss', [$fromDate, $toDate]);
?>

<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-filter me-2"></i>Report Filters</span>
                <div class="d-flex gap-2">
                    <a href="reports_inventory.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo me-1"></i>Reset</a>
                    <button type="button" class="btn btn-danger btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Stock Type</label>
                        <select class="form-select" name="stock_type">
                            <option value="all" <?php echo $stockType === 'all' ? 'selected' : ''; ?>>All Stock</option>
                            <option value="raw_materials" <?php echo $stockType === 'raw_materials' ? 'selected' : ''; ?>>Raw Materials</option>
                            <option value="finished_goods" <?php echo $stockType === 'finished_goods' ? 'selected' : ''; ?>>Finished Goods</option>
                            <option value="production" <?php echo $stockType === 'production' ? 'selected' : ''; ?>>Production</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Movement From Date</label>
                        <input type="date" class="form-control" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Movement To Date</label>
                        <input type="date" class="form-control" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Generate Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($stockType === 'all' || $stockType === 'raw_materials'): ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-cubes me-2"></i>Raw Materials Stock</span>
                <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle" id="rawMaterialsTable">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Material</th>
                                <th>Unit</th>
                                <th>Stock</th>
                                <th>Cost Price (PKR)</th>
                                <th>Value (Cost)</th>
                                <th>Selling Price (PKR)</th>
                                <th>Value (Selling)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($materials) > 0): ?>
                            <?php foreach ($materials as $m): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($m['material_code']); ?></td>
                                <td><?php echo htmlspecialchars($m['material_name']); ?></td>
                                <td><?php echo htmlspecialchars($m['unit']); ?></td>
                                <td><?php echo number_format($m['current_stock'], 2); ?></td>
                                <td class="text-end"><?php echo formatCurrency($m['purchase_price_pkr']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($m['current_stock'] * $m['purchase_price_pkr']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($m['selling_price'] ?: $m['purchase_price_pkr']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($m['current_stock'] * ($m['selling_price'] ?: $m['purchase_price_pkr'])); ?></td>
                                <td>
                                    <?php if ($m['current_stock'] <= 0): ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-secondary fw-bold">
                                <td colspan="5" class="text-end">Raw Materials Total</td>
                                <td class="text-end"><?php
                                    $rawMaterialsValue = 0;
                                    $rawMaterialsSaleValue = 0;
                                    foreach ($materials as $material) {
                                        $materialCost = (float)$material['purchase_price_pkr'];
                                        $materialSell = (float)($material['selling_price'] ?: $materialCost);
                                        $rawMaterialsValue += (float)$material['current_stock'] * $materialCost;
                                        $rawMaterialsSaleValue += (float)$material['current_stock'] * $materialSell;
                                    }
                                    echo formatCurrency($rawMaterialsValue);
                                ?></td>
                                <td></td>
                                <td class="text-end"><?php echo formatCurrency($rawMaterialsSaleValue); ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($stockType === 'all' || $stockType === 'finished_goods'): ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-boxes me-2"></i>Finished Goods Stock
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle" id="finishedGoodsTable">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Product</th>
                                <th>Unit</th>
                                <th>Stock</th>
                                <th>Min Stock</th>
                                <th>Selling Price</th>
                                <th>Cost Price</th>
                                <th>Value (Cost)</th>
                                <th>Value (Selling)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($products) > 0): ?>
                            <?php foreach ($products as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['product_code']); ?></td>
                                <td><?php echo htmlspecialchars($p['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($p['unit']); ?></td>
                                <td><?php echo number_format($p['current_stock'], 2); ?></td>
                                <td><?php echo number_format($p['minimum_stock'], 2); ?></td>
                                <td class="text-end"><?php echo formatCurrency($p['selling_price']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($p['cost_price']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($p['current_stock'] * $p['cost_price']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($p['current_stock'] * $p['selling_price']); ?></td>
                                <td>
                                    <?php if ($p['current_stock'] <= 0): ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                    <?php elseif ($p['current_stock'] <= $p['minimum_stock']): ?>
                                        <span class="badge bg-warning">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-secondary fw-bold">
                                <td colspan="7" class="text-end">Finished Goods Total</td>
                                <td class="text-end"><?php
                                    $finishedGoodsValue = 0;
                                    $finishedGoodsSaleValue = 0;
                                    foreach ($products as $product) {
                                        $finishedGoodsValue += (float)$product['current_stock'] * (float)$product['cost_price'];
                                        $finishedGoodsSaleValue += (float)$product['current_stock'] * (float)$product['selling_price'];
                                    }
                                    echo formatCurrency($finishedGoodsValue);
                                ?></td>
                                <td class="text-end"><?php echo formatCurrency($finishedGoodsSaleValue); ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($stockType === 'all' || $stockType === 'production'): ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-cogs me-2"></i>Production Report</span>
                <span class="text-muted small"><?php echo count($productions); ?> production records</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle" id="productionTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Production No</th>
                                <th>Finished Good</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Total Cost</th>
                                <th class="text-end">Unit Cost</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($productions) > 0): ?>
                            <?php foreach ($productions as $production): ?>
                            <?php $productionQuantity = (float)$production['quantity']; ?>
                            <tr>
                                <td><?php echo formatDate($production['production_date']); ?></td>
                                <td><strong><?php echo htmlspecialchars($production['production_no']); ?></strong></td>
                                <td><?php echo htmlspecialchars($production['product_name'] ?? 'Unknown'); ?><?php if (!empty($production['product_code'])): ?> <small class="text-muted">(<?php echo htmlspecialchars($production['product_code']); ?>)</small><?php endif; ?></td>
                                <td class="text-end"><?php echo number_format($productionQuantity, 2); ?> <?php echo htmlspecialchars($production['unit'] ?? ''); ?></td>
                                <td class="text-end"><?php echo formatCurrency($production['total_cost']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($productionQuantity > 0 ? (float)$production['total_cost'] / $productionQuantity : 0); ?></td>
                                <td><span class="badge bg-<?php echo ($production['status'] ?? '') === 'completed' ? 'success' : 'warning'; ?>"><?php echo ucfirst($production['status'] ?? 'pending'); ?></span></td>
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
<?php endif; ?>

<?php if ($stockType === 'all' || $stockType === 'raw_materials'): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-history me-2"></i>Recent Material Issues
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle" id="stockMovementsTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Material</th>
                                <th>Quantity</th>
                                <th>Issue No</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($movements) > 0): ?>
                            <?php foreach ($movements as $mv): ?>
                            <tr>
                                <td><?php echo date('d-m-Y H:i', strtotime($mv['date_time'])); ?></td>
                                <td><?php echo htmlspecialchars($mv['material_name']); ?></td>
                                <td class="text-end"><?php echo number_format($mv['quantity'], 2); ?></td>
                                <td><?php echo htmlspecialchars($mv['issue_no'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($mv['notes'] ?? '-'); ?></td>
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
<?php endif; ?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#rawMaterialsTable, #finishedGoodsTable, #productionTable').DataTable({
        pageLength: 25,
        order: [],
        language: {
            emptyTable: 'No stock records found',
            zeroRecords: 'No matching stock records found'
        }
    });
    $('#stockMovementsTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        language: {
            emptyTable: 'No material issues found for the selected date range'
        }
    });
});
</script>

<style>
@media print {
    #sidebar, .navbar-custom, .no-print, form, .btn, .dataTables_filter, .dataTables_length,
    .dataTables_info, .dataTables_paginate {
        display: none !important;
    }
    #content {
        margin-left: 0 !important;
        width: 100% !important;
    }
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        break-inside: avoid;
    }
}
</style>

<?php include '../includes/footer.php'; ?>
