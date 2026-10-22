<?php
/**
 * General Party Ledger
 * Standalone ledger page — pick a party from the dropdown and view the full ledger.
 */
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'General Party Ledger';

$partyId = (isset($_GET['id']) && is_numeric($_GET['id'])) ? (int)$_GET['id'] : 0;

$allParties = getRows("SELECT id, party_name, party_code, contact_person, phone, status
    FROM parties ORDER BY party_name");

$party = null;
$txnWithBalance = [];
$totalPayable = 0;
$totalReceived = 0;
$totalPaid = 0;
$selectedPartyLabel = '';

if ($partyId > 0) {
    $party = getRow("SELECT * FROM parties WHERE id = ?", 'i', [$partyId]);
    if (!$party) {
        $partyId = 0;
    } else {
        $selectedPartyLabel = $party['party_name'];
        if (!empty($party['party_code'])) {
            $selectedPartyLabel .= ' (' . $party['party_code'] . ')';
        }
        if (!empty($party['phone'])) {
            $selectedPartyLabel .= ' - ' . $party['phone'];
        }
    }
}

// Handle Delete transaction
if ($party && isset($_GET['del_txn']) && is_numeric($_GET['del_txn'])) {
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
        header('Location: general_party_ledger.php?id=' . $partyId);
        exit;
    }
}

if ($party) {
    // Fetch all transactions
    $transactions = getRows("SELECT * FROM party_transactions WHERE party_id = ? ORDER BY transaction_date ASC, id ASC", 'i', [$partyId]);

    // Calculate running balance
    $runningBalance = (float)$party['opening_balance'];
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
    $tp = getRow("SELECT COALESCE(SUM(amount),0) AS total FROM party_transactions WHERE party_id = ? AND type = 'payable'", 'i', [$partyId]);
    $tr = getRow("SELECT COALESCE(SUM(amount),0) AS total FROM party_transactions WHERE party_id = ? AND type = 'received'", 'i', [$partyId]);
    $tpd = getRow("SELECT COALESCE(SUM(amount),0) AS total FROM party_transactions WHERE party_id = ? AND type = 'paid'", 'i', [$partyId]);
    $totalPayable = (float)($tp['total'] ?? 0);
    $totalReceived = (float)($tr['total'] ?? 0);
    $totalPaid = (float)($tpd['total'] ?? 0);
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
/* Party Search Autocomplete */
.material-search-wrap { position: relative; width: 100%; }
.material-search-wrap .material-search-icon {
    position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
    color: #94a3b8; font-size: 11px; pointer-events: none; z-index: 5;
}
.material-search-wrap .material-search-input {
    padding-left: 28px; font-size: 13px; border-radius: 6px; background-color: #fff;
}
.material-search-wrap .material-search-input:focus {
    border-color: #0d6efd; box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
}
.material-search-dropdown {
    position: absolute; top: calc(100% + 2px); left: 0; right: 0; min-width: 320px;
    background: #ffffff; border: 1.5px solid #94a3b8; border-radius: 8px;
    box-shadow: 0 14px 30px rgba(0,0,0,0.18), 0 4px 10px rgba(0,0,0,0.08);
    max-height: 240px; overflow-y: auto; z-index: 9999; display: none;
}
.material-search-dropdown.show { display: block; }
.material-dropdown-item {
    padding: 8px 12px; border-bottom: 1px solid #f1f5f9; cursor: pointer;
    display: flex; justify-content: space-between; align-items: center;
    transition: background-color 0.15s; font-size: 13px;
}
.material-dropdown-item:last-child { border-bottom: none; }
.material-dropdown-item:hover, .material-dropdown-item.active { background-color: #eff6ff; }
</style>

<!-- Party Picker -->
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="" id="partyForm">
            <input type="hidden" name="id" id="party_id" value="<?php echo $partyId > 0 ? $partyId : ''; ?>">
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Party *</label>
                    <div class="material-search-wrap">
                        <i class="fas fa-search material-search-icon"></i>
                        <input type="text" class="form-control material-search-input" id="party_search"
                               placeholder="Type party name, code or phone..." autocomplete="off"
                               value="<?php echo htmlspecialchars($selectedPartyLabel); ?>"
                               oninput="filterParties(this)" onfocus="filterParties(this)"
                               onblur="onSearchBlur(this)">
                        <div class="material-search-dropdown shadow-sm" id="partyDropdown"></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-book me-1"></i>View Ledger
                    </button>
                </div>
                <div class="col-md-3 text-md-end">
                    <a href="parties.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Parties
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (!$party): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-address-book fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No party selected</h5>
            <p class="text-muted mb-0">Please select a party from the dropdown above to view the ledger.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-0"><?php echo htmlspecialchars($party['party_name']); ?></h5>
                <small class="text-muted">
                    <?php echo htmlspecialchars($party['party_code'] ?: ''); ?>
                    <?php if (!empty($party['contact_person'])): ?> | <?php echo htmlspecialchars($party['contact_person']); ?><?php endif; ?>
                    <?php if (!empty($party['phone'])): ?> | <?php echo htmlspecialchars($party['phone']); ?><?php endif; ?>
                </small>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="add_party_payable.php?party_id=<?php echo $partyId; ?>" class="btn btn-warning btn-sm"><i class="fas fa-file-invoice me-1"></i>Add Payable</a>
                <a href="add_party_received.php?party_id=<?php echo $partyId; ?>" class="btn btn-success btn-sm"><i class="fas fa-hand-holding-usd me-1"></i>Receive</a>
                <a href="add_party_paid.php?party_id=<?php echo $partyId; ?>" class="btn btn-primary btn-sm"><i class="fas fa-money-bill me-1"></i>Pay</a>
            </div>
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
                    <h5 class="mb-0 text-warning"><?php echo formatCurrency($totalPayable); ?></h5>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Total Received</h6>
                    <h5 class="mb-0 text-success"><?php echo formatCurrency($totalReceived); ?></h5>
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
                                        <td><?php echo formatDate($txn['transaction_date']); ?></td>
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
                                            <a href="general_party_ledger.php?id=<?php echo $partyId; ?>&del_txn=<?php echo $txn['id']; ?>" class="btn btn-sm btn-danger delete-confirm" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-active">
                                    <td colspan="6"><strong>Current Balance</strong></td>
                                    <td><strong><?php echo formatCurrency($totalPayable); ?></strong></td>
                                    <td><strong><?php echo formatCurrency($totalReceived); ?></strong></td>
                                    <td><strong><?php echo formatCurrency($totalPaid); ?></strong></td>
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
<?php endif; ?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    // Don't init DataTable to preserve opening/closing rows — use simple sortable
    $('.delete-confirm').on('click', function(e) { if (!confirm('Delete this transaction? Balance will be adjusted.')) e.preventDefault(); });
});
</script>

<script>
var partiesData = <?php echo json_encode($allParties); ?>;

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function filterParties(input) {
    const wrap = input.closest('.material-search-wrap');
    const dropdown = wrap.querySelector('.material-search-dropdown');
    const q = input.value.trim().toLowerCase();

    document.getElementById('party_id').value = '';

    let matches = [];
    if (!q) {
        matches = partiesData.slice(0, 15);
    } else {
        matches = partiesData.filter(function(p) {
            const name = (p.party_name || '').toLowerCase();
            const code = (p.party_code || '').toLowerCase();
            const contact = (p.contact_person || '').toLowerCase();
            const phone = (p.phone || '').toLowerCase();
            return name.includes(q) || code.includes(q) || contact.includes(q) || phone.includes(q);
        }).slice(0, 20);
    }

    if (matches.length === 0) {
        dropdown.innerHTML = '<div class="p-2 text-center text-muted small">No parties found</div>';
        dropdown.classList.add('show');
        return;
    }

    let html = '';
    matches.forEach(function(p, idx) {
        let label = escapeHtml(p.party_name);
        if (p.party_code) label += ' <span class="text-muted">(' + escapeHtml(p.party_code) + ')</span>';
        html += '<div class="material-dropdown-item ' + (idx === 0 ? 'active' : '') + '" data-id="' + p.id + '" onmousedown="selectParty(this)">' +
            '<div>' +
                '<div><strong class="text-dark">' + label + '</strong></div>' +
                '<small class="text-muted">' + (p.contact_person ? 'Contact: ' + escapeHtml(p.contact_person) : '') + (p.phone ? ' | Phone: ' + escapeHtml(p.phone) : '') + '</small>' +
            '</div>' +
            '<div class="text-end ps-2"><span class="badge bg-' + ((p.status || 'active') === 'active' ? 'success' : 'secondary') + '">' + escapeHtml(p.status || 'active') + '</span></div>' +
        '</div>';
    });

    dropdown.innerHTML = html;
    dropdown.classList.add('show');
}

function selectParty(itemEl) {
    const id = itemEl.getAttribute('data-id');
    const p = partiesData.find(function(x) { return x.id == id; });
    if (!p) return;

    const wrap = itemEl.closest('.material-search-wrap');
    const input = wrap.querySelector('.material-search-input');
    const dropdown = wrap.querySelector('.material-search-dropdown');

    let label = p.party_name;
    if (p.party_code) label += ' (' + p.party_code + ')';
    if (p.phone) label += ' - ' + p.phone;

    input.value = label;
    dropdown.classList.remove('show');
    document.getElementById('party_id').value = p.id;
    document.getElementById('partyForm').submit();
}

function onSearchBlur(input) {
    setTimeout(function() {
        const wrap = input.closest('.material-search-wrap');
        wrap.querySelector('.material-search-dropdown').classList.remove('show');
    }, 180);
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.material-search-wrap')) {
        document.querySelectorAll('.material-search-dropdown.show').forEach(function(dd) {
            dd.classList.remove('show');
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
