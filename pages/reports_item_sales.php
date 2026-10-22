<?php
/**
 * Item Wise Sale Report
 * Manufacturing ERP System
 */

require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Item Wise Sale Report';

$fromDate = isset($_GET['from_date']) && !empty($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$toDate = isset($_GET['to_date']) && !empty($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

// Fetch products & customers for filter dropdowns
$allProducts = getRows("SELECT id, product_code, product_name FROM finished_goods WHERE status = 'active' ORDER BY product_name");
$allCustomers = getRows("SELECT id, customer_name FROM customers WHERE status = 'active' ORDER BY customer_name");

// Build where clause
$whereClause = "s.status = 'completed' AND s.sale_date BETWEEN ? AND ?";
$params = [$fromDate, $toDate];
$types = "ss";

if ($productId > 0) {
    $whereClause .= " AND si.product_id = ?";
    $params[] = $productId;
    $types .= "i";
}

if ($customerId > 0) {
    $whereClause .= " AND s.customer_id = ?";
    $params[] = $customerId;
    $types .= "i";
}

// 1. Grouped by Item / Product
$itemSql = "SELECT 
        si.product_id,
        si.product_type,
        COALESCE(rm.material_code, fg.product_code) AS product_code,
        COALESCE(rm.material_name, fg.product_name) AS product_name,
        COALESCE(rm.unit, fg.unit) AS unit,
        COALESCE(rm.purchase_price_pkr, fg.cost_price, 0) AS cost_price,
        COUNT(DISTINCT s.id) as total_invoices,
        SUM(si.quantity) as total_quantity,
        SUM(si.total) as total_revenue,
        (SUM(si.total) / NULLIF(SUM(si.quantity), 0)) as avg_price,
        SUM(si.quantity * COALESCE(rm.purchase_price_pkr, fg.cost_price, 0)) as total_cost,
        (SUM(si.total) - SUM(si.quantity * COALESCE(rm.purchase_price_pkr, fg.cost_price, 0))) as gross_profit
    FROM sale_items si
    INNER JOIN sales s ON si.sale_id = s.id
    LEFT JOIN finished_goods fg ON si.product_id = fg.id AND (si.product_type = 'finished_good' OR si.product_type IS NULL)
    LEFT JOIN raw_materials rm ON si.product_id = rm.id AND si.product_type = 'raw_material'
    WHERE $whereClause
    GROUP BY si.product_id, si.product_type, product_code, product_name, unit, cost_price
    ORDER BY total_revenue DESC";

$itemRows = getRows($itemSql, $types, $params);

// 2. Individual Line-Item Transactions
$detailsSql = "SELECT 
        s.sale_date,
        s.sale_no,
        s.customer_type,
        s.walkin_name,
        c.customer_name,
        COALESCE(rm.material_code, fg.product_code) AS product_code,
        COALESCE(rm.material_name, fg.product_name) AS product_name,
        COALESCE(rm.unit, fg.unit) AS unit,
        si.quantity,
        si.unit_price,
        si.total
    FROM sale_items si
    INNER JOIN sales s ON si.sale_id = s.id
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN finished_goods fg ON si.product_id = fg.id AND (si.product_type = 'finished_good' OR si.product_type IS NULL)
    LEFT JOIN raw_materials rm ON si.product_id = rm.id AND si.product_type = 'raw_material'
    WHERE $whereClause
    ORDER BY s.sale_date DESC, s.id DESC";

$detailRows = getRows($detailsSql, $types, $params);

// Summary metrics
$totalQtySold = 0;
$totalRevenue = 0;
$totalCost = 0;
$totalGrossProfit = 0;
$topSellingItem = '-';
$topSellingQty = 0;

foreach ($itemRows as $item) {
    $qty = (float)$item['total_quantity'];
    $rev = (float)$item['total_revenue'];
    $cost = (float)$item['total_cost'];
    $profit = (float)$item['gross_profit'];

    $totalQtySold += $qty;
    $totalRevenue += $rev;
    $totalCost += $cost;
    $totalGrossProfit += $profit;

    if ($qty > $topSellingQty) {
        $topSellingQty = $qty;
        $topSellingItem = $item['product_name'] . ' (' . number_format($qty) . ')';
    }
}

$profitMargin = $totalRevenue > 0 ? round(($totalGrossProfit / $totalRevenue) * 100, 1) : 0;

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';
?>

<div class="row mb-3 d-print-none">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                <span class="fw-bold"><i class="fas fa-filter text-primary me-2"></i>Filter Item Sales</span>
                <div>
                    <button type="button" onclick="window.print()" class="btn btn-danger btn-sm">
                        <i class="fas fa-print me-1"></i> Print Report
                    </button>
                    <a href="reports_inventory.php" class="btn btn-outline-secondary btn-sm ms-1">
                        <i class="fas fa-boxes me-1"></i> Stock Report
                    </a>
                </div>
            </div>
            <div class="card-body py-3">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">From Date</label>
                        <input type="date" class="form-control form-control-sm" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">To Date</label>
                        <input type="date" class="form-control form-control-sm" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Item / Product</label>
                        <select class="form-select form-select-sm" name="product_id">
                            <option value="">All Products</option>
                            <?php foreach ($allProducts as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $productId == $p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['product_code'] . ' - ' . $p['product_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Customer</label>
                        <select class="form-select form-select-sm" name="customer_id">
                            <option value="">All Customers</option>
                            <?php foreach ($allCustomers as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $customerId == $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['customer_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-left: 4px solid #0d6efd !important;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total Units Sold</div>
                        <h4 class="mb-0 fw-bold text-primary"><?php echo number_format($totalQtySold, 2); ?></h4>
                    </div>
                    <div class="text-primary opacity-25 display-6"><i class="fas fa-boxes"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-left: 4px solid #198754 !important;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total Sales Revenue</div>
                        <h4 class="mb-0 fw-bold text-success">Rs <?php echo formatCurrency($totalRevenue); ?></h4>
                    </div>
                    <div class="text-success opacity-25 display-6"><i class="fas fa-cash-register"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-left: 4px solid #ffc107 !important;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Gross Profit (Est.)</div>
                        <h4 class="mb-0 fw-bold text-dark">Rs <?php echo formatCurrency($totalGrossProfit); ?> <small class="fs-6 text-muted">(<?php echo $profitMargin; ?>%)</small></h4>
                    </div>
                    <div class="text-warning opacity-25 display-6"><i class="fas fa-chart-line"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-left: 4px solid #6f42c1 !important;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Top Selling Product</div>
                        <div class="fw-bold text-truncate" style="max-width: 170px;" title="<?php echo htmlspecialchars($topSellingItem); ?>">
                            <?php echo htmlspecialchars($topSellingItem); ?>
                        </div>
                    </div>
                    <div class="text-purple opacity-25 display-6" style="color:#6f42c1;"><i class="fas fa-crown"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs: Summary vs Detailed -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-2">
        <ul class="nav nav-tabs card-header-tabs" id="itemReportTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active fw-bold" id="item-summary-tab" data-bs-toggle="tab" data-bs-target="#item-summary" type="button" role="tab">
                    <i class="fas fa-table me-1 text-primary"></i> Product Summary (<?php echo count($itemRows); ?> Items)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold" id="item-details-tab" data-bs-toggle="tab" data-bs-target="#item-details" type="button" role="tab">
                    <i class="fas fa-list me-1 text-info"></i> Detailed Invoices (<?php echo count($detailRows); ?> Transactions)
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="itemReportTabsContent">
            <!-- TAB 1: Product Summary -->
            <div class="tab-pane fade show active" id="item-summary" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle datatable" id="itemSummaryTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Product Code</th>
                                <th>Product Name</th>
                                <th>Unit</th>
                                <th class="text-center">Invoices</th>
                                <th class="text-end">Qty Sold</th>
                                <th class="text-end">Avg Sale Price</th>
                                <th class="text-end">Total Amount</th>
                                <th class="text-end">Est. Profit</th>
                                <th class="text-center">% of Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $i = 1;
                            foreach ($itemRows as $row): 
                                $pct = $totalRevenue > 0 ? round(((float)$row['total_revenue'] / $totalRevenue) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['product_code'] ?? '-'); ?></span></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['product_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($row['unit'] ?? 'unit'); ?></td>
                                <td class="text-center"><?php echo (int)$row['total_invoices']; ?></td>
                                <td class="text-end fw-bold text-primary"><?php echo number_format((float)$row['total_quantity'], 2); ?></td>
                                <td class="text-end"><?php echo formatCurrency((float)$row['avg_price']); ?></td>
                                <td class="text-end fw-bold text-success"><?php echo formatCurrency((float)$row['total_revenue']); ?></td>
                                <td class="text-end <?php echo (float)$row['gross_profit'] >= 0 ? 'text-dark' : 'text-danger'; ?>">
                                    <?php echo formatCurrency((float)$row['gross_profit']); ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><?php echo $pct; ?>%</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <th colspan="4" class="text-end">Totals:</th>
                                <th></th>
                                <th class="text-end text-primary"><?php echo number_format($totalQtySold, 2); ?></th>
                                <th></th>
                                <th class="text-end text-success"><?php echo formatCurrency($totalRevenue); ?></th>
                                <th class="text-end"><?php echo formatCurrency($totalGrossProfit); ?></th>
                                <th class="text-center">100%</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- TAB 2: Detailed Line-Item Sales -->
            <div class="tab-pane fade" id="item-details" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle datatable" id="itemDetailsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Sale #</th>
                                <th>Customer</th>
                                <th>Product</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total (PKR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detailRows as $d): 
                                $cName = ($d['customer_type'] === 'walkin') ? ($d['walkin_name'] ?: 'Walk-in Customer') : ($d['customer_name'] ?: 'Customer');
                            ?>
                            <tr>
                                <td><?php echo formatDate($d['sale_date']); ?></td>
                                <td><span class="badge bg-dark"><?php echo htmlspecialchars($d['sale_no'] ?? '-'); ?></span></td>
                                <td><?php echo htmlspecialchars($cName); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($d['product_name'] ?? ''); ?></strong>
                                    <small class="text-muted d-block"><?php echo htmlspecialchars($d['product_code'] ?? ''); ?></small>
                                </td>
                                <td class="text-end fw-bold"><?php echo number_format((float)$d['quantity'], 2); ?> <?php echo htmlspecialchars($d['unit'] ?? ''); ?></td>
                                <td class="text-end"><?php echo formatCurrency((float)$d['unit_price']); ?></td>
                                <td class="text-end fw-bold text-success"><?php echo formatCurrency((float)$d['total']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <th colspan="4" class="text-end">Total Amount:</th>
                                <th class="text-end"><?php echo number_format($totalQtySold, 2); ?></th>
                                <th></th>
                                <th class="text-end text-success"><?php echo formatCurrency($totalRevenue); ?></th>
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
    $('#itemSummaryTable').DataTable({
        pageLength: 25,
        order: [[7, 'desc']]
    });
    $('#itemDetailsTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']]
    });
});
</script>

<?php include '../includes/footer.php'; ?>
