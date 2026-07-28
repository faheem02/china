<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'All Customers Closing';

$sql = "SELECT c.*,
        (SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE customer_id = c.id) as total_sales,
        (SELECT COALESCE(SUM(paid_amount), 0) FROM sales WHERE customer_id = c.id) as total_paid
        FROM customers c ORDER BY c.customer_name";
$customers = getRows($sql);

$totalReceivable = 0;
$totalPayable = 0;

foreach ($customers as $c) {
    $bal = $c['opening_balance'] + $c['total_sales'] - $c['total_paid'];
    if ($bal >= 0) {
        $totalReceivable += $bal;
    } else {
        $totalPayable += abs($bal);
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';
?>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon float-end"><i class="fas fa-users"></i></div>
            <div class="stat-label">Total Customers</div>
            <div class="stat-number"><?php echo count($customers); ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="border-left-color: #dc3545;">
            <div class="stat-icon float-end"><i class="fas fa-hand-holding-usd"></i></div>
            <div class="stat-label">Total Receivable</div>
            <div class="stat-number text-danger"><?php echo formatCurrency($totalReceivable); ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="border-left-color: #28a745;">
            <div class="stat-icon float-end"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-label">Total Payable</div>
            <div class="stat-number text-success"><?php echo formatCurrency($totalPayable); ?></div>
            <div class="text-muted small">Net: <?php echo formatCurrency($totalReceivable - $totalPayable); ?></div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-balance-scale me-2"></i>All Customers Closing Balance</span>
                <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="closingTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Customer Name</th>
                                <th>Company</th>
                                <th>City</th>
                                <th>Closing Balance (PKR)</th>
                                <th>Status</th>
                                <th>Ledger</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($customers) > 0): ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($customers as $c): ?>
                                    <?php $bal = $c['opening_balance'] + $c['total_sales'] - $c['total_paid']; ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($c['customer_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($c['company_name'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($c['city'] ?: '-'); ?></td>
                                        <td class="<?php echo $bal > 0 ? 'text-danger' : 'text-success'; ?>">
                                            <strong><?php echo formatCurrency($bal); ?></strong>
                                            <small class="text-muted">(<?php echo $bal >= 0 ? 'Receivable' : 'Payable'; ?>)</small>
                                        </td>
                                        <td>
                                            <span class="badge badge-status badge-<?php echo $c['status']; ?>">
                                                <?php echo ucfirst($c['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="customer_ledger.php?id=<?php echo $c['id']; ?>" class="btn btn-info btn-sm" title="View Ledger">
                                                <i class="fas fa-book"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-3">
                                        <i class="fas fa-info-circle me-2"></i>No customers found
                                    </td>
                                </tr>
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
    $('#closingTable').DataTable({
        "pageLength": 25,
        "order": [],
"language": {
    "search": "Search Customers:",
    "lengthMenu": "Show _MENU_ entries",
    "info": "Showing _START_ to _END_ of _TOTAL_ customers",
    "emptyTable": "No customer data found"
}
    });
});
</script>

<?php include '../includes/footer.php'; ?>
