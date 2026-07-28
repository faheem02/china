<?php
require_once '../includes/database.php';
requireLogin();
$pageTitle = 'All Suppliers Closing';

$asOnDate = $_GET['as_on_date'] ?? date('Y-m-d');
$supplierType = $_GET['type'] ?? 'all';

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';

$localSuppliers = getRows("SELECT ls.*, 'local' AS stype,
    (SELECT COALESCE(SUM(balance),0) FROM local_purchases WHERE supplier_id = ls.id AND payment_status != 'paid') AS outstanding,
    (SELECT COUNT(*) FROM local_purchases WHERE supplier_id = ls.id) AS purchase_count
    FROM local_suppliers ls ORDER BY ls.supplier_name");

$chineseSuppliers = getRows("SELECT cs.*, 'chinese' AS stype,
    (SELECT COALESCE(SUM(balance),0) FROM import_purchases WHERE supplier_id = cs.id AND payment_status != 'paid') AS outstanding,
    (SELECT COUNT(*) FROM import_purchases WHERE supplier_id = cs.id) AS purchase_count
    FROM chinese_suppliers cs ORDER BY cs.supplier_name");

$allSuppliers = array_merge($localSuppliers, $chineseSuppliers);
usort($allSuppliers, function($a,$b){ return $b['outstanding'] <=> $a['outstanding']; });

$totalOutstanding = array_sum(array_column($allSuppliers, 'outstanding'));
$localOutstanding = array_sum(array_column($localSuppliers, 'outstanding'));
$chineseOutstanding = array_sum(array_column($chineseSuppliers, 'outstanding'));
?>

<div class="row mb-3"><div class="col-12"><div class="card">
    <div class="card-header d-flex justify-content-between align-items-center"><span><i class="fas fa-filter me-2"></i>Filter</span><button onclick="window.print()" class="btn btn-danger btn-sm"><i class="fas fa-print me-1"></i>Print</button></div>
    <div class="card-body"><form method="GET" class="row g-3">
        <div class="col-md-3"><label class="form-label">As of Date</label><input type="date" class="form-control" name="as_on_date" value="<?php echo $asOnDate; ?>"></div>
        <div class="col-md-3"><label class="form-label">Type</label><select class="form-select" name="type"><option value="all" <?php echo $supplierType==='all'?'selected':'';?>>All</option><option value="local" <?php echo $supplierType==='local'?'selected':'';?>>Local</option><option value="chinese" <?php echo $supplierType==='chinese'?'selected':'';?>>Chinese</option></select></div>
        <div class="col-md-3 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Generate</button></div>
    </form></div>
</div></div></div>

<div class="row mb-3">
    <div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body text-center"><h6>Total Suppliers</h6><h3><?php echo count($allSuppliers);?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-info text-white"><div class="card-body text-center"><h6>Local Outstanding</h6><h3><?php echo formatCurrency($localOutstanding);?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-warning text-white"><div class="card-body text-center"><h6>Chinese Outstanding</h6><h3><?php echo number_format($chineseOutstanding,2).' CNY';?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-danger text-white"><div class="card-body text-center"><h6>Total Outstanding</h6><h3><?php echo formatCurrency($totalOutstanding);?></h3></div></div></div>
</div>

<div class="row"><div class="col-12"><div class="card shadow-sm">
    <div class="card-header" style="background:#1a2332;color:#fff;"><i class="fas fa-truck me-2"></i>All Suppliers Closing</div>
    <div class="card-body"><div class="table-responsive"><table class="table table-striped table-hover"><thead><tr><th>#</th><th>Type</th><th>Supplier</th><th>Contact</th><th>Purchases</th><th class="text-end">Outstanding</th><th>Status</th></tr></thead><tbody>
    <?php $i=1; foreach($allSuppliers as $s): if($supplierType !== 'all' && $s['stype'] !== $supplierType) continue;?>
    <tr><td><?php echo $i++;?></td><td><span class="badge bg-<?php echo $s['stype']==='local'?'primary':'info';?>"><?php echo ucfirst($s['stype']);?></span></td><td><strong><?php echo htmlspecialchars($s['supplier_name']);?></strong></td><td><?php echo htmlspecialchars($s['contact_person']?:'-');?></td><td><?php echo $s['purchase_count'];?></td><td class="text-end fw-bold <?php echo $s['outstanding'] > 0 ? 'text-danger' : 'text-success';?>"><?php echo $s['stype']==='local' ? formatCurrency($s['outstanding']) : number_format($s['outstanding'],2).' CNY';?></td><td><span class="badge bg-<?php echo $s['outstanding'] > 0 ? 'danger' : 'success';?>"><?php echo $s['outstanding'] > 0 ? 'Outstanding' : 'Clear';?></span></td></tr>
    <?php endforeach;?></tbody>
    </table></div></div>
</div></div></div>

<?php include '../includes/footer.php'; ?>
