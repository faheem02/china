<?php
require_once '../includes/database.php';
requireLogin();
$pageTitle = 'Party Ledger Reports';

$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';

$parties = getRows("SELECT p.*,
    COALESCE((SELECT SUM(amount) FROM party_transactions WHERE party_id = p.id AND type = 'payable'),0) AS total_payable,
    COALESCE((SELECT SUM(amount) FROM party_transactions WHERE party_id = p.id AND type = 'received'),0) AS total_received,
    COALESCE((SELECT SUM(amount) FROM party_transactions WHERE party_id = p.id AND type = 'paid'),0) AS total_paid_txn,
    (SELECT COUNT(*) FROM party_transactions WHERE party_id = p.id) AS txn_count
    FROM parties p WHERE p.status = 'active' ORDER BY p.party_name");

$totalParties = count($parties);
$grandPayable = array_sum(array_column($parties, 'total_payable'));
$grandReceived = array_sum(array_column($parties, 'total_received'));
$grandPaidTxn = array_sum(array_column($parties, 'total_paid_txn'));

$periodTxns = getRows("SELECT pt.*, p.party_name FROM party_transactions pt LEFT JOIN parties p ON pt.party_id = p.id WHERE pt.transaction_date BETWEEN ? AND ? ORDER BY pt.transaction_date DESC", 'ss', [$fromDate, $toDate]);
$periodPayable = 0; $periodReceived = 0; $periodPaid = 0;
foreach($periodTxns as $t) {
    if($t['type']==='payable') $periodPayable += (float)$t['amount'];
    elseif($t['type']==='received') $periodReceived += (float)$t['amount'];
    else $periodPaid += (float)$t['amount'];
}
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
    <div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body text-center"><h6>Total Parties</h6><h3><?php echo $totalParties;?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-warning text-white"><div class="card-body text-center"><h6>Total Payable</h6><h3><?php echo formatCurrency($grandPayable);?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body text-center"><h6>Total Received</h6><h3><?php echo formatCurrency($grandReceived);?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-info text-white"><div class="card-body text-center"><h6>Total Paid</h6><h3><?php echo formatCurrency($grandPaidTxn);?></h3></div></div></div>
</div>

<div class="row mb-3">
    <div class="col-md-4"><div class="card border-warning"><div class="card-body text-center"><h6 class="text-muted">Period Payable</h6><h5 class="text-warning"><?php echo formatCurrency($periodPayable);?></h5></div></div></div>
    <div class="col-md-4"><div class="card border-success"><div class="card-body text-center"><h6 class="text-muted">Period Received</h6><h5 class="text-success"><?php echo formatCurrency($periodReceived);?></h5></div></div></div>
    <div class="col-md-4"><div class="card border-primary"><div class="card-body text-center"><h6 class="text-muted">Period Paid</h6><h5 class="text-primary"><?php echo formatCurrency($periodPaid);?></h5></div></div></div>
</div>

<div class="row mb-3"><div class="col-12"><div class="card shadow-sm">
    <div class="card-header"><i class="fas fa-address-book me-2"></i>Party Balances</div>
    <div class="card-body"><div class="table-responsive"><table class="table table-striped table-hover"><thead><tr><th>Party</th><th>Contact</th><th>Phone</th><th class="text-end">Opening</th><th class="text-end">Payable</th><th class="text-end">Received</th><th class="text-end">Paid</th><th class="text-end">Current Balance</th><th>Txns</th></tr></thead><tbody>
    <?php foreach($parties as $p): $bal = (float)$p['current_balance'];?>
    <tr><td><strong><?php echo htmlspecialchars($p['party_name']);?></strong></td><td><?php echo htmlspecialchars($p['contact_person']?:'-');?></td><td><?php echo htmlspecialchars($p['phone']?:'-');?></td><td class="text-end"><?php echo formatCurrency($p['opening_balance']);?></td><td class="text-end"><?php echo formatCurrency($p['total_payable']);?></td><td class="text-end"><?php echo formatCurrency($p['total_received']);?></td><td class="text-end"><?php echo formatCurrency($p['total_paid_txn']);?></td><td class="text-end fw-bold <?php echo $bal >= 0 ? 'text-success' : 'text-danger';?>"><?php echo formatCurrency(abs($bal));?> <?php echo $bal >= 0 ? 'Dr' : 'Cr';?></td><td><?php echo $p['txn_count'];?></td></tr>
    <?php endforeach;?></tbody></table></div></div>
</div></div></div>

<div class="row"><div class="col-12"><div class="card shadow-sm">
    <div class="card-header"><i class="fas fa-exchange-alt me-2"></i>Period Transactions</div>
    <div class="card-body"><div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Date</th><th>Voucher</th><th>Party</th><th>Type</th><th class="text-end">Amount</th><th>Payment</th><th>Description</th></tr></thead><tbody>
    <?php foreach($periodTxns as $t):?>
    <tr><td><?php echo date('d-m-Y', strtotime($t['transaction_date']));?></td><td><strong><?php echo htmlspecialchars($t['transaction_no']);?></strong></td><td><?php echo htmlspecialchars($t['party_name']);?></td><td><span class="badge bg-<?php echo $t['type']==='payable'?'warning':($t['type']==='received'?'success':'primary');?>"><?php echo ucfirst($t['type']);?></span></td><td class="text-end fw-bold"><?php echo formatCurrency($t['amount']);?></td><td><?php echo ucfirst($t['payment_method']);?></td><td><?php echo htmlspecialchars($t['description']?:'-');?></td></tr>
    <?php endforeach;?></tbody></table></div></div>
</div></div></div>

<?php include '../includes/footer.php'; ?>
