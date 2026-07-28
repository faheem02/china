<?php
require_once '../includes/database.php';
requireLogin();
$pageTitle = 'Production Reports';

$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';

$productions = getRows("SELECT po.*, fg.product_name, fg.product_code, fg.cost_price, fg.selling_price FROM production_orders po LEFT JOIN finished_goods fg ON po.finished_good_id = fg.id WHERE DATE(po.date_time) BETWEEN ? AND ? ORDER BY po.date_time DESC", 'ss', [$fromDate, $toDate]);

$totalProduced = array_sum(array_column($productions, 'quantity_produced'));
$totalCost = array_sum(array_column($productions, 'total_cost'));
$totalValue = 0;
foreach($productions as $pr) $totalValue += (float)$pr['quantity_produced'] * (float)$pr['selling_price'];

$materials = getRows("SELECT pom.*, rm.material_name, rm.unit FROM production_order_materials pom LEFT JOIN raw_materials rm ON pom.material_id = rm.id LEFT JOIN production_orders po ON pom.production_id = po.id WHERE DATE(po.date_time) BETWEEN ? AND ? ORDER BY rm.material_name", 'ss', [$fromDate, $toDate]);

$materialUsage = [];
foreach($materials as $m) {
    $name = $m['material_name'];
    if(!isset($materialUsage[$name])) $materialUsage[$name] = ['name'=>$name, 'unit'=>$m['unit'], 'qty'=>0, 'cost'=>0];
    $materialUsage[$name]['qty'] += (float)$m['quantity_used'];
    $materialUsage[$name]['cost'] += (float)$m['quantity_used'] * (float)$m['cost_pkr'];
}
usort($materialUsage, function($a,$b){ return $b['cost'] <=> $a['cost']; });
$totalMaterialCost = array_sum(array_column($materialUsage, 'cost'));
?>

<div class="row mb-3"><div class="col-12"><div class="card">
    <div class="card-header d-flex justify-content-between align-items-center"><span><i class="fas fa-filter me-2"></i>Filter</span><button onclick="window.print()" class="btn btn-danger btn-sm"><i class="fas fa-print me-1"></i>Print</button></div>
    <div class="card-body"><form method="GET" class="row g-3">
        <div class="col-md-4"><label class="form-label">From</label><input type="date" class="form-control" name="from_date" value="<?php echo $fromDate; ?>"></div>
        <div class="col-md-4"><label class="form-label">To</label><input type="date" class="form-control" name="to_date" value="<?php echo $toDate; ?>"></div>
        <div class="col-md-4 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Generate</button></div>
    </form></div>
</div></div></div>

<div class="row mb-3">
    <div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body text-center"><h6>Orders</h6><h3><?php echo count($productions);?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body text-center"><h6>Total Produced</h6><h3><?php echo number_format($totalProduced);?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-warning text-white"><div class="card-body text-center"><h6>Total Cost</h6><h3><?php echo formatCurrency($totalCost);?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-info text-white"><div class="card-body text-center"><h6>Sale Value</h6><h3><?php echo formatCurrency($totalValue);?></h3></div></div></div>
</div>

<div class="row mb-3"><div class="col-12"><div class="card shadow-sm">
    <div class="card-header"><i class="fas fa-cogs me-2"></i>Production Orders</div>
    <div class="card-body"><div class="table-responsive"><table class="table table-striped table-hover"><thead><tr><th>Date</th><th>Order #</th><th>Product</th><th class="text-end">Quantity</th><th class="text-end">Cost/Unit</th><th class="text-end">Total Cost</th><th>Status</th></tr></thead><tbody>
    <?php foreach($productions as $pr):?>
    <tr><td><?php echo date('d-m-Y', strtotime($pr['date_time']));?></td><td><strong><?php echo htmlspecialchars($pr['order_no']);?></strong></td><td><?php echo htmlspecialchars($pr['product_name']);?> (<?php echo htmlspecialchars($pr['product_code']);?>)</td><td class="text-end"><?php echo number_format($pr['quantity_produced']);?></td><td class="text-end"><?php echo formatCurrency($pr['cost_price']);?></td><td class="text-end fw-bold"><?php echo formatCurrency($pr['total_cost']);?></td><td><span class="badge bg-success"><?php echo ucfirst($pr['status']);?></span></td></tr>
    <?php endforeach;?></tbody>
    <tfoot><tr class="table-active"><th colspan="3">Totals</th><th class="text-end"><?php echo number_format($totalProduced);?></th><th></th><th class="text-end"><?php echo formatCurrency($totalCost);?></th><th></th></tr></tfoot>
    </table></div></div>
</div></div></div>

<div class="row"><div class="col-12"><div class="card shadow-sm">
    <div class="card-header"><i class="fas fa-cubes me-2"></i>Material Consumption</div>
    <div class="card-body"><div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Material</th><th>Unit</th><th class="text-end">Qty Used</th><th class="text-end">Cost (PKR)</th><th class="text-end">% of Total</th></tr></thead><tbody>
    <?php foreach($materialUsage as $mu):?>
    <tr><td><strong><?php echo htmlspecialchars($mu['name']);?></strong></td><td><?php echo htmlspecialchars($mu['unit']);?></td><td class="text-end"><?php echo number_format($mu['qty'],2);?></td><td class="text-end fw-bold"><?php echo formatCurrency($mu['cost']);?></td><td class="text-end"><?php echo $totalMaterialCost > 0 ? number_format(($mu['cost']/$totalMaterialCost)*100,1).'%' : '0%';?></td></tr>
    <?php endforeach;?></tbody>
    <tfoot><tr class="table-active"><th colspan="2">Total</th><th></th><th class="text-end"><?php echo formatCurrency($totalMaterialCost);?></th><th>100%</th></tr></tfoot>
    </table></div></div>
</div></div></div>

<?php include '../includes/footer.php'; ?>
