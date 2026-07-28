<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Parties';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $hasTxn = getRow("SELECT COUNT(*) AS cnt FROM party_transactions WHERE party_id = ?", 'i', [$id]);
    if ($hasTxn['cnt'] > 0) {
        $message = 'Cannot delete party with existing transactions!';
        $messageType = 'danger';
    } else {
        modifyData("DELETE FROM parties WHERE id = ?", 'i', [$id]);
        setFlash('Party deleted!', 'success');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

$parties = getRows("SELECT p.*,
    (SELECT COUNT(*) FROM party_transactions WHERE party_id = p.id) AS txn_count
    FROM parties p ORDER BY p.id DESC");

$totalParties = count($parties);
$totalReceivable = 0;
$totalPayable = 0;
foreach ($parties as $pt) {
    if ((float)$pt['current_balance'] > 0) $totalReceivable += (float)$pt['current_balance'];
    else $totalPayable += abs((float)$pt['current_balance']);
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="row">
    <div class="col-12">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <a href="add_party.php" class="btn btn-primary me-2"><i class="fas fa-plus me-1"></i>Add New Party</a>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color:#1a2332;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo $totalParties; ?></div>
                    <div class="stat-label">Total Parties</div>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color:#198754;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number text-success"><?php echo formatCurrency($totalReceivable); ?></div>
                    <div class="stat-label">Total Receivable</div>
                </div>
                <div class="stat-icon" style="color:#198754;"><i class="fas fa-arrow-down"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color:#dc3545;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number text-danger"><?php echo formatCurrency($totalPayable); ?></div>
                    <div class="stat-label">Total Payable</div>
                </div>
                <div class="stat-icon" style="color:#dc3545;"><i class="fas fa-arrow-up"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header" style="background:#1a2332;color:#fff;">
                <i class="fas fa-users me-2"></i>All Parties
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="partiesTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Code</th>
                                <th>Party Name</th>
                                <th>Contact</th>
                                <th>Phone</th>
                                <th>City</th>
                                <th>Opening Bal</th>
                                <th>Current Bal</th>
                                <th>Txns</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($parties as $pt): ?>
                                <?php $bal = (float)$pt['current_balance']; ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><?php echo htmlspecialchars($pt['party_code'] ?: '-'); ?></td>
                                    <td><strong><?php echo htmlspecialchars($pt['party_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($pt['contact_person'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($pt['phone'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($pt['city'] ?: '-'); ?></td>
                                    <td><?php echo formatCurrency($pt['opening_balance']); ?></td>
                                    <td>
                                        <?php if ($bal > 0): ?>
                                            <span class="badge bg-success"><?php echo formatCurrency($bal); ?></span>
                                        <?php elseif ($bal < 0): ?>
                                            <span class="badge bg-danger"><?php echo formatCurrency(abs($bal)); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">0.00</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-info"><?php echo $pt['txn_count']; ?></span></td>
                                    <td><span class="badge badge-status badge-<?php echo $pt['status']; ?>"><?php echo ucfirst($pt['status']); ?></span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="party_detail.php?id=<?php echo $pt['id']; ?>" class="btn btn-info" title="Ledger"><i class="fas fa-book"></i></a>
                                            <a href="add_party.php?edit=<?php echo $pt['id']; ?>" class="btn btn-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                            <a href="?delete=<?php echo $pt['id']; ?>" class="btn btn-danger delete-confirm" title="Delete"><i class="fas fa-trash"></i></a>
                                        </div>
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

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#partiesTable').DataTable({ pageLength:25, order:[[0,'desc']], language:{ search:'Search Parties:', emptyTable:'No parties found' } });
    $('.delete-confirm').on('click', function(e) { if (!confirm('Delete this party?')) e.preventDefault(); });
});
</script>

<?php include '../includes/footer.php'; ?>
