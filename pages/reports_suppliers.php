<?php
require_once '../includes/database.php';
requireLogin();
$pageTitle = 'Supplier Reports';

$supplierType = $_GET['type'] ?? 'local';
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';

if ($supplierType === 'local') {
    $suppliers = getRows("SELECT ls.*, COALESCE(SUM(lp.paid_amount),0) AS total_paid, COALESCE(SUM(lp.balance),0) AS total_balance, COUNT(lp.id) AS purchase_count FROM local_suppliers ls LEFT JOIN local_purchases lp ON lp.supplier_id = ls.id GROUP BY ls.id ORDER BY ls.supplier_name");
    $totalBalance = getRow("SELECT COALESCE(SUM(balance),0) AS total FROM local_purchases WHERE payment_status != 'paid'");
    $title = 'Local Suppliers';
} else {
    $suppliers = getRows("SELECT cs.*, COALESCE(SUM(ip.paid_amount),0) AS total_paid_cny, COALESCE(SUM(ip.balance),0) AS total_balance_cny, COUNT(ip.id) AS purchase_count FROM chinese_suppliers cs LEFT JOIN import_purchases ip ON ip.supplier_id = cs.id GROUP BY cs.id ORDER BY cs.supplier_name");
    $totalBalance = getRow("SELECT COALESCE(SUM(balance),0) AS total FROM import_purchases WHERE payment_status != 'paid'");
    $title = 'Chinese Suppliers';
}

$grandPaid = $supplierType === 'local' ? array_sum(array_column($suppliers, 'total_paid')) : array_sum(array_column($suppliers, 'total_paid_cny'));
?>

<div class="row mb-3"><div class="col-12"><div class="card">
    <div class="card-header d-flex justify-content-between align-items-center"><span><i class="fas fa-filter me-2"></i>Filter</span><button onclick="window.print()" class="btn btn-danger btn-sm"><i class="fas fa-print me-1"></i>Print</button></div>
    <div class="card-body"><form method="GET" class="row g-3">
        <div class="col-md-4"><label class="form-label">Supplier Type</label><select class="form-select" name="type"><option value="local" <?php echo $supplierType==='local'?'selected':'';?>>Local Suppliers</option><option value="chinese" <?php echo $supplierType==='chinese'?'selected':'';?>>Chinese Suppliers</option></select></div>
        <div class="col-md-4 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Generate</button></div>
    </form></div>
</div></div></div>

<div class="row mb-3">
    <div class="col-md-4"><div class="card bg-primary text-white"><div class="card-body text-center"><h6>Total Suppliers</h6><h3><?php echo count($suppliers);?></h3></div></div></div>
    <div class="col-md-4"><div class="card bg-success text-white"><div class="card-body text-center"><h6>Total Paid</h6><h3><?php echo $supplierType==='local' ? formatCurrency($grandPaid) : number_format($grandPaid,2).' CNY';?></h3></div></div></div>
    <div class="col-md-4"><div class="card bg-danger text-white"><div class="card-body text-center"><h6>Outstanding</h6><h3><?php echo $supplierType==='local' ? formatCurrency((float)$totalBalance['total']) : number_format($totalBalance['total'],2).' CNY';?></h3></div></div></div>
</div>

<div class="row"><div class="col-12"><div class="card shadow-sm">
    <div class="card-header"><i class="fas fa-truck me-2"></i><?php echo $title;?></div>
    <div class="card-body"><div class="table-responsive"><table class="table table-striped table-hover"><thead><tr>
        <th>Name</th><th>Contact</th><th>Phone</th><th>Purchases</th>
        <?php if($supplierType==='local'):?><th class="text-end">Total Paid</th><th class="text-end">Outstanding</th>
        <?php else:?><th class="text-end">Paid (CNY)</th><th class="text-end">Balance (CNY)</th><?php endif;?>
    </tr></thead><tbody>
    <?php foreach($suppliers as $s): ?>
    <tr>
        <td><strong><?php echo htmlspecialchars($s['supplier_name']);?></strong></td>
        <td><?php echo htmlspecialchars($s['contact_person']?:'-');?></td>
        <td><?php echo htmlspecialchars($s['phone']?:'-');?></td>
        <td><?php echo $s['purchase_count'];?></td>
        <td class="text-end"><?php echo $supplierType==='local' ? formatCurrency($s['total_paid']) : number_format($s['total_paid_cny'],2);?></td>
        <td class="text-end"><?php
            $bal = $supplierType==='local' ? (float)$s['total_balance'] : (float)$s['total_balance_cny'];
            echo $supplierType==='local' ? formatCurrency($bal) : number_format($bal,2);
            if($bal > 0) echo ' <span class="badge bg-danger">Due</span>';
        ?></td>
    </tr>
    <?php endforeach;?></tbody></table></div></div>
</div></div></div>

<?php include '../includes/footer.php'; ?>
