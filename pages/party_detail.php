<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Party Ledger';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlash('Invalid party!', 'danger');
    header('Location: parties.php');
    exit;
}

$partyId = (int)$_GET['id'];
$party = getRow("SELECT * FROM parties WHERE id = ?", 'i', [$partyId]);
if (!$party) {
    setFlash('Party not found!', 'danger');
    header('Location: parties.php');
    exit;
}

// Handle Delete transaction
if (isset($_GET['del_txn']) && is_numeric($_GET['del_txn'])) {
    $txnId = (int)$_GET['del_txn'];
    $txn = getRow("SELECT * FROM party_transactions WHERE id = ? AND party_id = ?", 'ii', [$txnId, $partyId]);
    if ($txn) {
        // Reverse the effect on current_balance
        if ($txn['type'] === 'payable') {
            modifyData("UPDATE parties SET current_balance = current_balance - ? WHERE id = ?", 'di', [$txn['amount'], $partyId]);
        } else {
            modifyData("UPDATE parties SET current_balance = current_balance + ? WHERE id = ?", 'di', [$txn['amount'], $partyId]);
        }
        modifyData("DELETE FROM party_transactions WHERE id = ?", 'i', [$txnId]);
        setFlash('Transaction deleted!', 'success');
        header('Location: party_detail.php?id=' . $partyId);
        exit;
    }
}

// Fetch all transactions
$transactions = getRows("SELECT * FROM party_transactions WHERE party_id = ? ORDER BY transaction_date ASC, id ASC", 'i', [$partyId]);

// Calculate running balance
$runningBalance = (float)$party['opening_balance'];
$txnWithBalance = [];
foreach ($transactions as $txn) {
    if ($txn['type'] === 'payable') {
        $runningBalance += (float)$txn['amount'];
    } else {
        $runningBalance -= (float)$txn['amount'];
    }
    $txn['running_balance'] = $runningBalance;
    $txnWithBalance[] = $txn;
}
// Reverse to show newest first
$txnWithBalance = array_reverse($txnWithBalance);

// Summary
$totalPayable = getRow("SELECT COALESCE(SUM(amount),0) AS total FROM party_transactions WHERE party_id = ? AND type = 'payable'", 'i', [$partyId]);
$totalReceived = getRow("SELECT COALESCE(SUM(amount),0) AS total FROM party_transactions WHERE party_id = ? AND type = 'received'", 'i', [$partyId]);
$totalPaid = getRow("SELECT COALESCE(SUM(amount),0) AS total FROM party_transactions WHERE party_id = ? AND type = 'paid'", 'i', [$partyId]);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="row mb-3">
    <div class="col-12">
        <a href="parties.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Parties</a>
        <a href="add_party_payable.php?party_id=<?php echo $partyId; ?>" class="btn btn-warning btn-sm ms-2"><i class="fas fa-file-invoice me-1"></i>Add Payable</a>
        <a href="add_party_received.php?party_id=<?php echo $partyId; ?>" class="btn btn-success btn-sm ms-2"><i class="fas fa-hand-holding-usd me-1"></i>Receive</a>
        <a href="add_party_paid.php?party_id=<?php echo $partyId; ?>" class="btn btn-primary btn-sm ms-2"><i class="fas fa-money-bill me-1"></i>Pay</a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Party Info</h6>
                <h4 class="mb-1"><?php echo htmlspecialchars($party['party_name']); ?></h4>
                <small class="text-muted"><?php echo htmlspecialchars($party['party_code'] ?: ''); ?>
                    <?php if ($party['contact_person']): ?> | <?php echo htmlspecialchars($party['contact_person']); ?><?php endif; ?>
                    <?php if ($party['phone']): ?> | <?php echo htmlspecialchars($party['phone']); ?><?php endif; ?>
                </small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card shadow-sm border-primary">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Opening</h6>
                <h5 class="mb-0"><?php echo formatCurrency($party['opening_balance']); ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card shadow-sm border-warning">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Total Payable</h6>
                <h5 class="mb-0 text-warning"><?php echo formatCurrency($totalPayable['total']); ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card shadow-sm border-success">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Total Received</h6>
                <h5 class="mb-0 text-success"><?php echo formatCurrency($totalReceived['total']); ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card shadow-sm border-<?php echo (float)$party['current_balance'] >= 0 ? 'success' : 'danger'; ?>">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Current Balance</h6>
                <h5 class="mb-0 text-<?php echo (float)$party['current_balance'] >= 0 ? 'success' : 'danger'; ?>">
                    <?php echo formatCurrency(abs($party['current_balance'])); ?>
                    <?php echo (float)$party['current_balance'] >= 0 ? ' (Dr)' : ' (Cr)'; ?>
                </h5>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header" style="background:#1a2332;color:#fff;">
                <i class="fas fa-book me-2"></i>Ledger - <?php echo htmlspecialchars($party['party_name']); ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped" id="ledgerTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Voucher No</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Reference</th>
                                <th>Payable (+)</th>
                                <th>Received (-)</th>
                                <th>Paid (-)</th>
                                <th>Balance</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-active">
                                <td colspan="6"><strong>Opening Balance</strong></td>
                                <td colspan="3"></td>
                                <td><strong><?php echo formatCurrency($party['opening_balance']); ?></strong></td>
                                <td></td>
                            </tr>
                            <?php $j = 1; foreach ($txnWithBalance as $txn): ?>
                                <tr>
                                    <td><?php echo $j++; ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($txn['transaction_date'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($txn['transaction_no']); ?></strong></td>
                                    <td>
                                        <?php if ($txn['type'] === 'payable'): ?>
                                            <span class="badge bg-warning text-dark">Payable</span>
                                        <?php elseif ($txn['type'] === 'received'): ?>
                                            <span class="badge bg-success">Received</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Paid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($txn['description'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($txn['reference_no'] ?: '-'); ?></td>
                                    <td><?php echo $txn['type'] === 'payable' ? formatCurrency($txn['amount']) : '-'; ?></td>
                                    <td><?php echo $txn['type'] === 'received' ? formatCurrency($txn['amount']) : '-'; ?></td>
                                    <td><?php echo $txn['type'] === 'paid' ? formatCurrency($txn['amount']) : '-'; ?></td>
                                    <td>
                                        <strong class="<?php echo $txn['running_balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo formatCurrency(abs($txn['running_balance'])); ?>
                                            <?php echo $txn['running_balance'] >= 0 ? ' Dr' : ' Cr'; ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <a href="?id=<?php echo $partyId; ?>&del_txn=<?php echo $txn['id']; ?>" class="btn btn-sm btn-danger delete-confirm" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-active">
                                <td colspan="6"><strong>Current Balance</strong></td>
                                <td><strong><?php echo formatCurrency($totalPayable['total']); ?></strong></td>
                                <td><strong><?php echo formatCurrency($totalReceived['total']); ?></strong></td>
                                <td><strong><?php echo formatCurrency($totalPaid['total']); ?></strong></td>
                                <td><strong class="<?php echo (float)$party['current_balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo formatCurrency(abs($party['current_balance'])); ?>
                                    <?php echo (float)$party['current_balance'] >= 0 ? ' Dr' : ' Cr'; ?>
                                </strong></td>
                                <td></td>
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
    // Don't init DataTable to preserve opening/closing rows — use simple sortable
    $('.delete-confirm').on('click', function(e) { if (!confirm('Delete this transaction? Balance will be adjusted.')) e.preventDefault(); });
});
</script>

<?php include '../includes/footer.php'; ?>
