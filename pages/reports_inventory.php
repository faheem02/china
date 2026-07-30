<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Inventory Reports';

$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');

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

// Stock Summary
$totalRmStock = 0;
foreach ($materials as $m) { $totalRmStock += $m['current_stock'] * $m['purchase_price_pkr']; }
$totalFgStock = 0;
$lowStockFg = 0;
foreach ($products as $p) { $totalFgStock += $p['current_stock'] * $p['cost_price']; if ($p['current_stock'] <= $p['minimum_stock']) $lowStockFg++; }
$totalInventory = $totalRmStock + $totalFgStock;

// Recent stock movements (last 50 issues)
$movements = getRows("SELECT mii.*, m.material_name, mi.issue_no, mi.issue_date, mi.notes, mi.production_order_id FROM material_issue_items mii 
                      LEFT JOIN raw_materials m ON mii.material_id = m.id 
                      LEFT JOIN material_issues mi ON mii.issue_id = mi.id
                      WHERE DATE(mii.date_time) BETWEEN ? AND ?
                      ORDER BY mii.date_time DESC LIMIT 50",
                      'ss', [$fromDate, $toDate]);
?>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h5>Raw Materials Value</h5>
                <h3><?php echo formatCurrency($totalRmStock); ?></h3>
                <small>PKR</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h5>Finished Goods Value</h5>
                <h3><?php echo formatCurrency($totalFgStock); ?></h3>
                <small>PKR (Cost)</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h5>Total Inventory</h5>
                <h3><?php echo formatCurrency($totalInventory); ?></h3>
                <small>PKR</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <h5>Low Stock Items</h5>
                <h3><?php echo $lowStockFg; ?></h3>
                <small>Needs Reorder</small>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-filter me-2"></i>Filter</span>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" name="from_date" value="<?php echo $fromDate; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" name="to_date" value="<?php echo $toDate; ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Generate Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-cubes me-2"></i>Raw Materials Stock</span>
                <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Material</th>
                                <th>Unit</th>
                                <th>Stock</th>
                                <th>Price (PKR)</th>
                                <th>Value (PKR)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materials as $m): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($m['material_code']); ?></td>
                                <td><?php echo htmlspecialchars($m['material_name']); ?></td>
                                <td><?php echo htmlspecialchars($m['unit']); ?></td>
                                <td><?php echo number_format($m['current_stock'], 2); ?></td>
                                <td class="text-end"><?php echo formatCurrency($m['purchase_price_pkr']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($m['current_stock'] * $m['purchase_price_pkr']); ?></td>
                                <td>
                                    <?php if ($m['current_stock'] <= 0): ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-boxes me-2"></i>Finished Goods Stock
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
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
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
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
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-history me-2"></i>Recent Material Issues
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped datatable">
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
                            <?php foreach ($movements as $mv): ?>
                            <tr>
                                <td><?php echo date('d-m-Y H:i', strtotime($mv['date_time'])); ?></td>
                                <td><?php echo htmlspecialchars($mv['material_name']); ?></td>
                                <td class="text-end"><?php echo number_format($mv['quantity'], 2); ?></td>
                                <td><?php echo htmlspecialchars($mv['issue_no'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($mv['notes'] ?? '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
