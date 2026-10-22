<?php
/**
 * Customer Party Ledger
 * Standalone ledger page — pick a customer from the dropdown and view the full ledger.
 */
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Customer Party Ledger';

$customerId = (isset($_GET['id']) && is_numeric($_GET['id'])) ? (int)$_GET['id'] : 0;

$allCustomers = getRows("SELECT id, customer_name, company_name, phone, city, status
    FROM customers ORDER BY customer_name");

$customer = null;
$ledger = [];
$totalSalesAmt = 0;
$totalPaidAmt = 0;
$effectiveOpening = 0;
$closingBalance = 0;
$selectedCustomerLabel = '';

if ($customerId > 0) {
    $customer = getRow("SELECT * FROM customers WHERE id = ?", 'i', [$customerId]);
    if (!$customer) {
        $customerId = 0;
    } else {
        $selectedCustomerLabel = $customer['customer_name'];
        if (!empty($customer['company_name'])) {
            $selectedCustomerLabel .= ' (' . $customer['company_name'] . ')';
        }
        if (!empty($customer['phone'])) {
            $selectedCustomerLabel .= ' - ' . $customer['phone'];
        }
        $sales = getRows("SELECT id, sale_no as ref_no, sale_date as trans_date, total_amount as debit, paid_amount as credit, 'Sale' as trans_type
            FROM sales WHERE customer_id = ? ORDER BY sale_date, id", 'i', [$customerId]);

        $receipts = getRows("SELECT id, receipt_no as ref_no, payment_date as trans_date, 0 as debit, amount as credit, 'Payment' as trans_type
            FROM customer_receipts WHERE customer_id = ? ORDER BY payment_date, id", 'i', [$customerId]);

        foreach ($sales as $s) {
            $totalSalesAmt += (float)$s['debit'];
        }
        foreach ($receipts as $r) {
            $totalPaidAmt += (float)$r['credit'];
        }

        $effectiveOpening = (float)$customer['opening_balance'];
        if (($customer['opening_balance_type'] ?? 'receivable') === 'payable') {
            $effectiveOpening = -$effectiveOpening;
        }

        $ledger = array_merge($sales, $receipts);
        usort($ledger, function ($a, $b) {
            $cmp = strcmp($a['trans_date'], $b['trans_date']);
            if ($cmp !== 0) return $cmp;
            return $a['id'] - $b['id'];
        });

        $closingBalance = $effectiveOpening + $totalSalesAmt - $totalPaidAmt;
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
/* Customer Search Autocomplete */
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

<!-- Customer Picker -->
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="" id="customerForm">
            <input type="hidden" name="id" id="customer_id" value="<?php echo $customerId > 0 ? $customerId : ''; ?>">
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Customer *</label>
                    <div class="material-search-wrap">
                        <i class="fas fa-search material-search-icon"></i>
                        <input type="text" class="form-control material-search-input" id="customer_search"
                               placeholder="Type customer name, company or phone..." autocomplete="off"
                               value="<?php echo htmlspecialchars($selectedCustomerLabel); ?>"
                               oninput="filterCustomers(this)" onfocus="filterCustomers(this)"
                               onblur="onSearchBlur(this)">
                        <div class="material-search-dropdown shadow-sm" id="customerDropdown"></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-book me-1"></i>View Ledger
                    </button>
                </div>
                <div class="col-md-3 text-md-end">
                    <a href="customers.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Customers
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (!$customer): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-user-friends fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No customer selected</h5>
            <p class="text-muted mb-0">Please select a customer from the dropdown above to view the ledger.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-0"><?php echo htmlspecialchars($customer['customer_name']); ?></h5>
                <small class="text-muted">
                    <?php echo htmlspecialchars($customer['company_name'] ?: ''); ?>
                    <?php if (!empty($customer['phone'])): ?> | <?php echo htmlspecialchars($customer['phone']); ?><?php endif; ?>
                    <?php if (!empty($customer['city'])): ?> | <?php echo htmlspecialchars($customer['city']); ?><?php endif; ?>
                </small>
            </div>
            <div>
                <a href="customer_ledger_print.php?id=<?php echo $customerId; ?>" class="btn btn-primary" target="_blank">
                    <i class="fas fa-print me-2"></i>Print Ledger
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-book me-2"></i>Customer Ledger
                    <span class="ms-2 badge bg-info"><?php echo htmlspecialchars($customer['customer_name']); ?></span>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-label">Opening Balance</div>
                                <div class="stat-number"><?php echo formatCurrency($customer['opening_balance']); ?></div>
                                <div class="text-muted small">(<?php echo ucfirst($customer['opening_balance_type'] ?? 'receivable'); ?>)</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-label">Total Sales</div>
                                <div class="stat-number text-primary"><?php echo formatCurrency($totalSalesAmt); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-label">Total Payments</div>
                                <div class="stat-number text-success"><?php echo formatCurrency($totalPaidAmt); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-label">Closing Balance</div>
                                <div class="stat-number <?php echo $closingBalance > 0 ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo formatCurrency($closingBalance); ?>
                                </div>
                                <div class="text-muted small">(<?php echo $closingBalance > 0 ? 'Receivable' : ($closingBalance < 0 ? 'Payable' : 'Settled'); ?>)</div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="ledgerTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Reference #</th>
                                    <th>Type</th>
                                    <th>Debit (PKR)</th>
                                    <th>Credit (PKR)</th>
                                    <th>Balance (PKR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="table-secondary">
                                    <td></td>
                                    <td><?php echo formatDate($customer['date_time']); ?></td>
                                    <td><strong>Opening Balance</strong></td>
                                    <td><small class="text-muted">(<?php echo ucfirst($customer['opening_balance_type'] ?? 'receivable'); ?>)</small></td>
                                    <td></td>
                                    <td></td>
                                    <td class="<?php echo $effectiveOpening > 0 ? 'text-danger' : ($effectiveOpening < 0 ? 'text-success' : ''); ?>">
                                        <strong><?php echo formatCurrency($effectiveOpening); ?></strong>
                                    </td>
                                </tr>
                                <?php $runningBalance = $effectiveOpening; ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($ledger as $entry): ?>
                                    <?php
                                    if ($entry['trans_type'] == 'Sale') {
                                        $runningBalance += (float)$entry['debit'];
                                    } else {
                                        $runningBalance -= (float)$entry['credit'];
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo formatDate($entry['trans_date']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($entry['ref_no']); ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo $entry['trans_type'] == 'Sale' ? 'primary' : 'success'; ?>">
                                                <?php echo $entry['trans_type']; ?>
                                            </span>
                                        </td>
                                        <td class="text-end"><?php echo $entry['debit'] > 0 ? formatCurrency($entry['debit']) : '-'; ?></td>
                                        <td class="text-end"><?php echo $entry['credit'] > 0 ? formatCurrency($entry['credit']) : '-'; ?></td>
                                        <td class="text-end <?php echo $runningBalance > 0 ? 'text-danger' : 'text-success'; ?>">
                                            <strong><?php echo formatCurrency($runningBalance); ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-secondary fw-bold">
                                    <td></td>
                                    <td></td>
                                    <td><strong>Closing Balance</strong></td>
                                    <td></td>
                                    <td class="text-end"><strong><?php echo formatCurrency($totalSalesAmt); ?></strong></td>
                                    <td class="text-end"><strong><?php echo formatCurrency($totalPaidAmt); ?></strong></td>
                                    <td class="text-end <?php echo $closingBalance > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <strong><?php echo formatCurrency($closingBalance); ?></strong>
                                    </td>
                                </tr>
                            </tbody>
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
    $('#ledgerTable').DataTable({
        "pageLength": 50,
        "order": [],
        "paging": false,
        "searching": false,
        "info": false,
        "language": {
            "emptyTable": "No ledger entries found"
        }
    });
});
</script>

<script>
var customersData = <?php echo json_encode($allCustomers); ?>;

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function filterCustomers(input) {
    const wrap = input.closest('.material-search-wrap');
    const dropdown = wrap.querySelector('.material-search-dropdown');
    const q = input.value.trim().toLowerCase();

    document.getElementById('customer_id').value = '';

    let matches = [];
    if (!q) {
        matches = customersData.slice(0, 15);
    } else {
        matches = customersData.filter(function(c) {
            const name = (c.customer_name || '').toLowerCase();
            const company = (c.company_name || '').toLowerCase();
            const phone = (c.phone || '').toLowerCase();
            const city = (c.city || '').toLowerCase();
            return name.includes(q) || company.includes(q) || phone.includes(q) || city.includes(q);
        }).slice(0, 20);
    }

    if (matches.length === 0) {
        dropdown.innerHTML = '<div class="p-2 text-center text-muted small">No customers found</div>';
        dropdown.classList.add('show');
        return;
    }

    let html = '';
    matches.forEach(function(c, idx) {
        let label = escapeHtml(c.customer_name);
        if (c.company_name) label += ' <span class="text-muted">(' + escapeHtml(c.company_name) + ')</span>';
        html += '<div class="material-dropdown-item ' + (idx === 0 ? 'active' : '') + '" data-id="' + c.id + '" onmousedown="selectCustomer(this)">' +
            '<div>' +
                '<div><strong class="text-dark">' + label + '</strong></div>' +
                '<small class="text-muted">' + (c.phone ? 'Phone: ' + escapeHtml(c.phone) : '') + (c.city ? ' | City: ' + escapeHtml(c.city) : '') + '</small>' +
            '</div>' +
            '<div class="text-end ps-2"><span class="badge bg-' + ((c.status || 'active') === 'active' ? 'success' : 'secondary') + '">' + escapeHtml(c.status || 'active') + '</span></div>' +
        '</div>';
    });

    dropdown.innerHTML = html;
    dropdown.classList.add('show');
}

function selectCustomer(itemEl) {
    const id = itemEl.getAttribute('data-id');
    const c = customersData.find(function(x) { return x.id == id; });
    if (!c) return;

    const wrap = itemEl.closest('.material-search-wrap');
    const input = wrap.querySelector('.material-search-input');
    const dropdown = wrap.querySelector('.material-search-dropdown');

    let label = c.customer_name;
    if (c.company_name) label += ' (' + c.company_name + ')';
    if (c.phone) label += ' - ' + c.phone;

    input.value = label;
    dropdown.classList.remove('show');
    document.getElementById('customer_id').value = c.id;
    document.getElementById('customerForm').submit();
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
