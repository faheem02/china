<?php
require_once '../includes/database.php';
requireLogin();
$pageTitle = 'All Parties Closing';

$asOnDate = $_GET['as_on_date'] ?? date('Y-m-d');

$parties = getRows("SELECT p.*,
    COALESCE((SELECT SUM(amount) FROM party_transactions WHERE party_id = p.id AND type = 'payable'),0) AS total_payable,
    COALESCE((SELECT SUM(amount) FROM party_transactions WHERE party_id = p.id AND type = 'received'),0) AS total_received,
    COALESCE((SELECT SUM(amount) FROM party_transactions WHERE party_id = p.id AND type = 'paid'),0) AS total_paid_txn
    FROM parties p WHERE p.status = 'active' ORDER BY p.party_name");

$totalReceivable = 0; $totalPayable = 0;
foreach($parties as $p) {
    $bal = (float)$p['current_balance'];
    if($bal > 0) $totalReceivable += $bal; else $totalPayable += abs($bal);
}

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';
?>

<div class="row mb-3"><div class="col-12"><div class="card">
    <div class="card-header d-flex justify-content-between align-items-center"><span><i class="fas fa-filter me-2"></i>As of Date: <?php echo date('d-m-Y', strtotime($asOnDate));?></span><button onclick="window.print()" class="btn btn-danger btn-sm"><i class="fas fa-print me-1"></i>Print</button></div>
    <div class="card-body"><form method="GET" class="row g-3">
        <div class="col-md-4"><label class="form-label">As of Date</label><input type="date" class="form-control" name="as_on_date" value="<?php echo $asOnDate; ?>"></div>
        <div class="col-md-4 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Generate</button></div>
    </form></div>
</div></div></div>

<div class="row mb-3">
    <div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body text-center"><h6>Total Parties</h6><h3><?php echo count($parties);?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body text-center"><h6>Total Receivable</h6><h3><?php echo formatCurrency($totalReceivable);?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-danger text-white"><div class="card-body text-center"><h6>Total Payable</h6><h3><?php echo formatCurrency($totalPayable);?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-info text-white"><div class="card-body text-center"><h6>Net Balance</h6><h3><?php echo formatCurrency($totalReceivable - $totalPayable);?></h3></div></div></div>
</div>

<div class="row"><div class="col-12"><div class="card shadow-sm">
    <div class="card-header" style="background:#1a2332;color:#fff;"><i class="fas fa-address-book me-2"></i>All Parties Closing</div>
    <div class="card-body"><div class="table-responsive"><table class="table table-striped table-hover"><thead><tr><th>#</th><th>Party</th><th>Contact</th><th>Phone</th><th class="text-end">Payable</th><th class="text-end">Received</th><th class="text-end">Paid</th><th class="text-end">Current Balance</th><th>Status</th></tr></thead><tbody>
    <?php $i=1; foreach($parties as $p): $bal = (float)$p['current_balance'];?>
    <tr><td><?php echo $i++;?></td><td><strong><?php echo htmlspecialchars($p['party_name']);?></strong></td><td><?php echo htmlspecialchars($p['contact_person']?:'-');?></td><td><?php echo htmlspecialchars($p['phone']?:'-');?></td><td class="text-end"><?php echo formatCurrency($p['total_payable']);?></td><td class="text-end"><?php echo formatCurrency($p['total_received']);?></td><td class="text-end"><?php echo formatCurrency($p['total_paid_txn']);?></td><td class="text-end fw-bold <?php echo $bal >= 0 ? 'text-success' : 'text-danger';?>"><?php echo formatCurrency(abs($bal));?> <?php echo $bal >= 0 ? 'Dr' : 'Cr';?></td><td><span class="badge bg-<?php echo $bal > 0 ? 'success' : ($bal < 0 ? 'danger' : 'secondary');?>"><?php echo $bal > 0 ? 'Receivable' : ($bal < 0 ? 'Payable' : 'Settled');?></span></td></tr>
    <?php endforeach;?></tbody>
    <tfoot><tr class="table-active"><th colspan="4">Totals</th><th class="text-end"><?php echo formatCurrency(array_sum(array_column($parties,'total_payable')));?></th><th class="text-end"><?php echo formatCurrency(array_sum(array_column($parties,'total_received')));?></th><th class="text-end"><?php echo formatCurrency(array_sum(array_column($parties,'total_paid_txn')));?></th><th class="text-end"><?php echo formatCurrency($totalReceivable - $totalPayable);?></th><th></th></tr></tfoot>
    </table></div></div>
</div></div></div>

<?php include '../includes/footer.php'; ?>
