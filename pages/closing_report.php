<?php
/**
 * Master Closing Report
 * Manufacturing ERP System
 */

require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Closing Report';

$asOnDate = isset($_GET['as_on_date']) && !empty($_GET['as_on_date']) ? $_GET['as_on_date'] : date('Y-m-d');
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'customers';

// ── 1. CUSTOMERS CLOSING DATA ──
$customers = getRows("SELECT c.*,
    COALESCE(c.current_balance, 0) as closing_balance,
    (SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE customer_id = c.id AND status = 'completed') as total_sales,
    (SELECT COALESCE(SUM(amount), 0) FROM customer_receipts WHERE customer_id = c.id) as total_received
    FROM customers c 
    WHERE c.status = 'active'
    ORDER BY c.customer_name");

$custReceivable = 0;
$custPayable = 0;
foreach ($customers as $c) {
    $bal = (float)$c['closing_balance'];
    if ($bal >= 0) {
        $custReceivable += $bal;
    } else {
        $custPayable += abs($bal);
    }
}

// ── 2. SUPPLIERS CLOSING DATA (Local & Chinese) ──
$localSuppliers = getRows("SELECT ls.*, 'local' AS stype,
    COALESCE(ls.current_balance, 0) as closing_balance,
    (SELECT COALESCE(SUM(total_amount), 0) FROM local_purchases WHERE supplier_id = ls.id) as total_purchases,
    (SELECT COALESCE(SUM(amount), 0) FROM supplier_payments WHERE supplier_id = ls.id) as total_paid
    FROM local_suppliers ls 
    WHERE ls.status = 'active'
    ORDER BY ls.supplier_name");

$chineseSuppliers = getRows("SELECT cs.*, 'chinese' AS stype, c.exchange_rate,
    COALESCE(cs.current_balance, 0) as closing_balance_cny,
    (SELECT COALESCE(SUM(total_cny), 0) FROM import_purchases WHERE supplier_id = cs.id) as total_purchases_cny,
    (SELECT COALESCE(SUM(amount_cny), 0) FROM chinese_supplier_payments WHERE supplier_id = cs.id) as total_paid_cny
    FROM chinese_suppliers cs 
    LEFT JOIN currencies c ON cs.currency_id = c.id
    WHERE cs.status = 'active'
    ORDER BY cs.supplier_name");

$localSupplierPayable = 0;
foreach ($localSuppliers as $ls) {
    $localSupplierPayable += (float)$ls['closing_balance'];
}

$chineseSupplierPayableCNY = 0;
$chineseSupplierPayablePKR = 0;
foreach ($chineseSuppliers as $cs) {
    $cnyBal = (float)$cs['closing_balance_cny'];
    $rate = (float)($cs['exchange_rate'] > 0 ? $cs['exchange_rate'] : 40.50);
    $chineseSupplierPayableCNY += $cnyBal;
    $chineseSupplierPayablePKR += ($cnyBal * $rate);
}

$totalSupplierPayablePKR = $localSupplierPayable + $chineseSupplierPayablePKR;

// ── 3. PARTIES CLOSING DATA ──
$parties = [];
$partyReceivable = 0;
$partyPayable = 0;
$hasParties = getRow("SHOW TABLES LIKE 'parties'");
if ($hasParties) {
    $parties = getRows("SELECT p.*,
        COALESCE(p.current_balance, 0) as closing_balance,
        COALESCE((SELECT SUM(amount) FROM party_transactions WHERE party_id = p.id AND type = 'payable'), 0) as total_payable,
        COALESCE((SELECT SUM(amount) FROM party_transactions WHERE party_id = p.id AND type = 'received'), 0) as total_received,
        COALESCE((SELECT SUM(amount) FROM party_transactions WHERE party_id = p.id AND type = 'paid'), 0) as total_paid_txn
        FROM parties p 
        WHERE p.status = 'active'
        ORDER BY p.party_name");

    foreach ($parties as $p) {
        $bal = (float)$p['closing_balance'];
        if ($bal >= 0) {
            $partyReceivable += $bal;
        } else {
            $partyPayable += abs($bal);
        }
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';
?>

<div class="row mb-3 d-print-none">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                <span class="fw-bold"><i class="fas fa-balance-scale text-primary me-2"></i>Closing Balances as of: <?php echo formatDate($asOnDate); ?></span>
                <div>
                    <button type="button" onclick="window.print()" class="btn btn-danger btn-sm">
                        <i class="fas fa-print me-1"></i> Print Closing Report
                    </button>
                    <a href="reports_inventory.php" class="btn btn-outline-secondary btn-sm ms-1">
                        <i class="fas fa-boxes me-1"></i> Stock Report
                    </a>
                    <a href="reports_item_sales.php" class="btn btn-outline-primary btn-sm ms-1">
                        <i class="fas fa-shopping-cart me-1"></i> Item Wise Sale
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Financial Summary KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-left: 4px solid #28a745 !important;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Customer Receivables</div>
                        <h4 class="mb-0 fw-bold text-success">Rs <?php echo formatCurrency($custReceivable); ?></h4>
                        <small class="text-muted"><?php echo count($customers); ?> Customers</small>
                    </div>
                    <div class="text-success opacity-25 display-6"><i class="fas fa-user-friends"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-left: 4px solid #dc3545 !important;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Supplier Payables (PKR)</div>
                        <h4 class="mb-0 fw-bold text-danger">Rs <?php echo formatCurrency($totalSupplierPayablePKR); ?></h4>
                        <small class="text-muted"><?php echo count($localSuppliers) + count($chineseSuppliers); ?> Suppliers</small>
                    </div>
                    <div class="text-danger opacity-25 display-6"><i class="fas fa-truck"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-left: 4px solid #17a2b8 !important;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Chinese Suppliers (CNY)</div>
                        <h4 class="mb-0 fw-bold" style="color:#17a2b8;">¥ <?php echo number_format($chineseSupplierPayableCNY, 2); ?></h4>
                        <small class="text-muted">≈ Rs <?php echo formatCurrency($chineseSupplierPayablePKR); ?></small>
                    </div>
                    <div class="opacity-25 display-6" style="color:#17a2b8;"><i class="fas fa-yen-sign"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-left: 4px solid #6f42c1 !important;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Net Debt Position</div>
                        <?php $netPosition = $custReceivable - $totalSupplierPayablePKR; ?>
                        <h4 class="mb-0 fw-bold <?php echo $netPosition >= 0 ? 'text-primary' : 'text-danger'; ?>">
                            Rs <?php echo formatCurrency(abs($netPosition)); ?> <?php echo $netPosition >= 0 ? '(Surplus)' : '(Deficit)'; ?>
                        </h4>
                        <small class="text-muted">Receivables vs Payables</small>
                    </div>
                    <div class="text-purple opacity-25 display-6" style="color:#6f42c1;"><i class="fas fa-balance-scale"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs Section -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-2">
        <ul class="nav nav-tabs card-header-tabs" id="closingTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active fw-bold" id="customers-tab" data-bs-toggle="tab" data-bs-target="#tab-customers" type="button" role="tab">
                    <i class="fas fa-user-friends me-1 text-success"></i> Customer Closing (<?php echo count($customers); ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold" id="suppliers-tab" data-bs-toggle="tab" data-bs-target="#tab-suppliers" type="button" role="tab">
                    <i class="fas fa-truck me-1 text-danger"></i> Local Suppliers Closing (<?php echo count($localSuppliers); ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold" id="chinese-tab" data-bs-toggle="tab" data-bs-target="#tab-chinese" type="button" role="tab">
                    <i class="fas fa-ship me-1 text-info"></i> Chinese Suppliers Closing (<?php echo count($chineseSuppliers); ?>)
                </button>
            </li>
            <?php if (!empty($parties)): ?>
            <li class="nav-item">
                <button class="nav-link fw-bold" id="parties-tab" data-bs-toggle="tab" data-bs-target="#tab-parties" type="button" role="tab">
                    <i class="fas fa-address-book me-1 text-purple" style="color:#6f42c1;"></i> Parties Closing (<?php echo count($parties); ?>)
                </button>
            </li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="closingTabsContent">
            
            <!-- TAB 1: CUSTOMERS CLOSING -->
            <div class="tab-pane fade show active" id="tab-customers" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle datatable" id="custClosingTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Customer Name</th>
                                <th>Phone</th>
                                <th>City</th>
                                <th class="text-end">Opening Bal</th>
                                <th class="text-end">Total Sales</th>
                                <th class="text-end">Total Received</th>
                                <th class="text-end">Closing Balance</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $i = 1;
                            foreach ($customers as $c): 
                                $bal = (float)$c['closing_balance'];
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($c['customer_name']); ?></strong>
                                    <?php if (!empty($c['company_name'])): ?>
                                        <small class="text-muted d-block"><?php echo htmlspecialchars($c['company_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($c['phone'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($c['city'] ?: '-'); ?></td>
                                <td class="text-end"><?php echo formatCurrency((float)$c['opening_balance']); ?></td>
                                <td class="text-end"><?php echo formatCurrency((float)$c['total_sales']); ?></td>
                                <td class="text-end"><?php echo formatCurrency((float)$c['total_received']); ?></td>
                                <td class="text-end fw-bold <?php echo $bal > 0 ? 'text-success' : ($bal < 0 ? 'text-danger' : ''); ?>">
                                    <?php echo formatCurrency(abs($bal)); ?> <?php echo $bal >= 0 ? 'Dr' : 'Cr'; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $bal > 0 ? 'success' : ($bal < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $bal > 0 ? 'Receivable' : ($bal < 0 ? 'Advance' : 'Settled'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <th colspan="7" class="text-end">Total Customer Receivables:</th>
                                <th class="text-end text-success">Rs <?php echo formatCurrency($custReceivable); ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- TAB 2: LOCAL SUPPLIERS CLOSING -->
            <div class="tab-pane fade" id="tab-suppliers" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle datatable" id="suppClosingTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Supplier Name</th>
                                <th>Company</th>
                                <th>Phone</th>
                                <th class="text-end">Opening Bal</th>
                                <th class="text-end">Total Purchases</th>
                                <th class="text-end">Total Paid</th>
                                <th class="text-end">Closing Balance (PKR)</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $i = 1;
                            foreach ($localSuppliers as $ls): 
                                $bal = (float)$ls['closing_balance'];
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($ls['supplier_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($ls['company_name'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($ls['phone'] ?: '-'); ?></td>
                                <td class="text-end"><?php echo formatCurrency((float)$ls['opening_balance']); ?></td>
                                <td class="text-end"><?php echo formatCurrency((float)$ls['total_purchases']); ?></td>
                                <td class="text-end"><?php echo formatCurrency((float)$ls['total_paid']); ?></td>
                                <td class="text-end fw-bold <?php echo $bal > 0 ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo formatCurrency(abs($bal)); ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $bal > 0 ? 'danger' : ($bal < 0 ? 'warning' : 'secondary'); ?>">
                                        <?php echo $bal > 0 ? 'Payable' : ($bal < 0 ? 'Advance' : 'Cleared'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <th colspan="7" class="text-end">Total Local Supplier Payables:</th>
                                <th class="text-end text-danger">Rs <?php echo formatCurrency($localSupplierPayable); ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- TAB 3: CHINESE SUPPLIERS CLOSING -->
            <div class="tab-pane fade" id="tab-chinese" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle datatable" id="chineseClosingTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Supplier Name</th>
                                <th>Company</th>
                                <th class="text-end">Exch. Rate</th>
                                <th class="text-end">Total Purchases (CNY)</th>
                                <th class="text-end">Total Paid (CNY)</th>
                                <th class="text-end">Balance (CNY ¥)</th>
                                <th class="text-end">Balance (PKR Rs)</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $i = 1;
                            foreach ($chineseSuppliers as $cs): 
                                $cnyBal = (float)$cs['closing_balance_cny'];
                                $rate = (float)($cs['exchange_rate'] > 0 ? $cs['exchange_rate'] : 40.50);
                                $pkrBal = $cnyBal * $rate;
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($cs['supplier_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($cs['company_name'] ?: '-'); ?></td>
                                <td class="text-end"><?php echo number_format($rate, 2); ?></td>
                                <td class="text-end">¥ <?php echo number_format((float)$cs['total_purchases_cny'], 2); ?></td>
                                <td class="text-end">¥ <?php echo number_format((float)$cs['total_paid_cny'], 2); ?></td>
                                <td class="text-end fw-bold text-primary">¥ <?php echo number_format($cnyBal, 2); ?></td>
                                <td class="text-end fw-bold text-danger">Rs <?php echo formatCurrency($pkrBal); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $cnyBal > 0 ? 'danger' : ($cnyBal < 0 ? 'warning' : 'secondary'); ?>">
                                        <?php echo $cnyBal > 0 ? 'Payable' : ($cnyBal < 0 ? 'Advance' : 'Cleared'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <th colspan="6" class="text-end">Total Chinese Payables:</th>
                                <th class="text-end text-primary">¥ <?php echo number_format($chineseSupplierPayableCNY, 2); ?></th>
                                <th class="text-end text-danger">Rs <?php echo formatCurrency($chineseSupplierPayablePKR); ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- TAB 4: PARTIES CLOSING -->
            <?php if (!empty($parties)): ?>
            <div class="tab-pane fade" id="tab-parties" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle datatable" id="partiesClosingTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Party Name</th>
                                <th>Contact Person</th>
                                <th>Phone</th>
                                <th class="text-end">Payable</th>
                                <th class="text-end">Received</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Closing Balance</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $i = 1;
                            foreach ($parties as $p): 
                                $bal = (float)$p['closing_balance'];
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($p['party_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($p['contact_person'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($p['phone'] ?: '-'); ?></td>
                                <td class="text-end"><?php echo formatCurrency((float)$p['total_payable']); ?></td>
                                <td class="text-end"><?php echo formatCurrency((float)$p['total_received']); ?></td>
                                <td class="text-end"><?php echo formatCurrency((float)$p['total_paid_txn']); ?></td>
                                <td class="text-end fw-bold <?php echo $bal >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo formatCurrency(abs($bal)); ?> <?php echo $bal >= 0 ? 'Dr' : 'Cr'; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $bal > 0 ? 'success' : ($bal < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $bal > 0 ? 'Receivable' : ($bal < 0 ? 'Payable' : 'Settled'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <th colspan="7" class="text-end">Net Party Balance:</th>
                                <th class="text-end text-primary">Rs <?php echo formatCurrency($partyReceivable - $partyPayable); ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#custClosingTable, #suppClosingTable, #chineseClosingTable, #partiesClosingTable').DataTable({
        pageLength: 25
    });
});
</script>

<?php include '../includes/footer.php'; ?>
