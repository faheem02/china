<?php
/**
 * Chinese Suppliers Summary Page
 * Manufacturing ERP System
 */

// Include database connection
require_once '../includes/database.php';

// Require login
requireLogin();

$pageTitle = 'Chinese Suppliers Summary';
$message = '';
$messageType = '';

// Get date filters
$asOnDate = isset($_GET['as_on_date']) ? $_GET['as_on_date'] : date('Y-m-d');

$dbError = '';

// Default values in case query fails
$suppliers = [];
$exchangeRate = 40.5000;
$totalSuppliers = 0;
$totalBalanceCNY = 0;
$totalBalancePKR = 0;
$totalPurchasesCNY = 0;
$totalPurchasesPKR = 0;
$totalPaidCNY = 0;
$totalPaidPKR = 0;
$totalOutstandingCNY = 0;
$totalOutstandingPKR = 0;
$totalPaymentsCNY = 0;
$totalPaymentsPKR = 0;

// Get all Chinese suppliers with their balances and purchase history
try {
$suppliers = getRows("SELECT s.*, c.currency_code, c.currency_name, c.symbol, c.exchange_rate,
    (SELECT COUNT(*) FROM import_purchases WHERE supplier_id = s.id) as total_purchases,
    (SELECT COALESCE(SUM(total_cny), 0) FROM import_purchases WHERE supplier_id = s.id) as total_purchase_cny,
    (SELECT COALESCE(SUM(total_pkr), 0) FROM import_purchases WHERE supplier_id = s.id) as total_purchase_pkr,
    (SELECT COALESCE(SUM(paid_amount_cny), 0) FROM import_purchases WHERE supplier_id = s.id) as total_paid_cny,
    (SELECT COALESCE(SUM(paid_amount_pkr), 0) FROM import_purchases WHERE supplier_id = s.id) as total_paid_pkr,
    (SELECT COALESCE(SUM(balance_cny), 0) FROM import_purchases WHERE supplier_id = s.id) as total_balance_cny,
    (SELECT COALESCE(SUM(balance_pkr), 0) FROM import_purchases WHERE supplier_id = s.id) as total_balance_pkr,
    (SELECT COUNT(*) FROM chinese_supplier_payments WHERE supplier_id = s.id) as total_payments,
    (SELECT COALESCE(SUM(amount_cny), 0) FROM chinese_supplier_payments WHERE supplier_id = s.id) as total_payment_cny,
    (SELECT COALESCE(SUM(amount_pkr), 0) FROM chinese_supplier_payments WHERE supplier_id = s.id) as total_payment_pkr,
    COALESCE((SELECT SUM(cp.amount_cny) FROM chinese_supplier_payments cp WHERE cp.supplier_id = s.id), 0) AS payments_made_cny,
    (s.opening_balance + (SELECT COALESCE(SUM(balance_cny), 0) FROM import_purchases WHERE supplier_id = s.id) - COALESCE((SELECT SUM(cp.amount_cny) FROM chinese_supplier_payments cp WHERE cp.supplier_id = s.id), 0)) AS current_balance
    FROM chinese_suppliers s 
    LEFT JOIN currencies c ON s.currency_id = c.id 
    GROUP BY s.id
    ORDER BY s.supplier_name");

// Calculate totals
$totalSuppliers = count($suppliers);
$totalBalanceCNY = 0;
$totalBalancePKR = 0;
$totalPurchasesCNY = 0;
$totalPurchasesPKR = 0;
$totalPaidCNY = 0;
$totalPaidPKR = 0;
$totalOutstandingCNY = 0;
$totalOutstandingPKR = 0;
$totalPaymentsCNY = 0;
$totalPaymentsPKR = 0;

foreach ($suppliers as $supplier) {
    $totalBalanceCNY += $supplier['current_balance'];
    $totalBalancePKR += $supplier['current_balance'] * ($supplier['exchange_rate'] ?: 40.5000);
    $totalPurchasesCNY += $supplier['total_purchase_cny'];
    $totalPurchasesPKR += $supplier['total_purchase_pkr'];
    $totalPaidCNY += $supplier['total_paid_cny'];
    $totalPaidPKR += $supplier['total_paid_pkr'];
    $totalOutstandingCNY += $supplier['total_balance_cny'];
    $totalOutstandingPKR += $supplier['total_balance_pkr'];
    $totalPaymentsCNY += $supplier['total_payment_cny'];
    $totalPaymentsPKR += $supplier['total_payment_pkr'];
}

// Get current exchange rate
$exchangeRate = 40.5000; // Default
$rateRow = getRow("SELECT exchange_rate FROM currencies WHERE currency_code = 'CNY'");
if ($rateRow) {
    $exchangeRate = $rateRow['exchange_rate'];
}

} catch (\Throwable $e) {
    $dbError = $e->getMessage();
}

// Include header
include '../includes/header.php';
include '../includes/sidebar.php';

try {
    include '../includes/print_header.php';
} catch (\Throwable $e) {
    $dbError = 'print_header: ' . $e->getMessage();
}

$hasSuppliers = is_array($suppliers) && count($suppliers) > 0;
?>

<?php if ($dbError): ?>
<div class="alert alert-danger m-3" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Database Error:</strong> <?php echo htmlspecialchars($dbError); ?>
</div>
<?php endif; ?>
<?php if (!$hasSuppliers && !$dbError): ?>
<div class="alert alert-warning m-3" role="alert">
    <i class="fas fa-info-circle me-2"></i> No suppliers data found (query returned empty).
</div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-users me-2"></i>Chinese Suppliers Summary</h5>
            <div>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print Report
                </button>
                <a href="chinese_suppliers.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left me-2"></i>Back to Suppliers
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-3">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo $totalSuppliers; ?></div>
                    <div class="stat-label">Total Chinese Suppliers</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color: #28a745;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number">¥ <?php echo formatCurrency($totalPurchasesCNY); ?></div>
                    <div class="stat-label">Total Purchases (CNY)</div>
                    <small class="text-muted">PKR <?php echo formatCurrency($totalPurchasesPKR); ?></small>
                </div>
                <div class="stat-icon" style="color: #28a745;">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color: #17a2b8;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number">¥ <?php echo formatCurrency($totalPaidCNY); ?></div>
                    <div class="stat-label">Total Paid (CNY)</div>
                    <small class="text-muted">PKR <?php echo formatCurrency($totalPaidPKR); ?></small>
                </div>
                <div class="stat-icon" style="color: #17a2b8;">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color: #dc3545;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number">¥ <?php echo formatCurrency($totalOutstandingCNY); ?></div>
                    <div class="stat-label">Outstanding Balance (CNY)</div>
                    <small class="text-muted">PKR <?php echo formatCurrency($totalOutstandingPKR); ?></small>
                </div>
                <div class="stat-icon" style="color: #dc3545;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Exchange Rate Info -->
<div class="row mb-3">
    <div class="col-12">
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Exchange Rate:</strong> 1 CNY = <?php echo number_format($exchangeRate, 4); ?> PKR
            <span class="ms-3"><strong>Total Balance:</strong> ¥ <?php echo formatCurrency($totalBalanceCNY); ?> (PKR <?php echo formatCurrency($totalBalancePKR); ?>)</span>
        </div>
    </div>
</div>

<!-- Suppliers Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list me-2"></i>Chinese Suppliers List with Balances
                <span class="ms-2 badge bg-warning text-dark">Amounts in CNY</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0" id="supplierSummaryTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Supplier</th>
                                <th>Contact</th>
                                <th>Address</th>
                                <th class="text-nowrap">Balance (CNY)</th>
                                <th class="text-nowrap">Balance (PKR)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($suppliers) > 0): ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($suppliers as $supplier): 
                                    $pkrBalance = $supplier['current_balance'] * ($supplier['exchange_rate'] ?: 40.5000);
                                ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td>
                                            <a href="chinese_supplier_detail.php?id=<?php echo $supplier['id']; ?>" class="text-decoration-none">
                                                <strong><?php echo htmlspecialchars($supplier['supplier_name']); ?></strong>
                                            </a>
                                            <?php if ($supplier['company_name']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($supplier['company_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-nowrap"><?php echo htmlspecialchars($supplier['phone'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($supplier['address'] ?: '-'); ?></td>
                                        <td class="text-nowrap <?php echo $supplier['current_balance'] < 0 ? 'text-danger' : 'text-success'; ?>">
                                            <strong>¥ <?php echo number_format($supplier['current_balance'], 2); ?></strong>
                                        </td>
                                        <td class="text-nowrap">PKR <?php echo number_format($pkrBalance, 2); ?></td>
                                        <td>
                                            <div class="d-inline-flex gap-1">
                                                <a href="chinese_supplier_detail.php?id=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-info" title="View Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="chinese_supplier_ledger.php?id=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-primary" title="View Ledger">
                                                    <i class="fas fa-book"></i>
                                                </a>
                                                <a href="chinese_supplier_payment.php?supplier=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-success" title="Make Payment">
                                                    <i class="fas fa-money-bill-wave"></i>
                                                </a>
                                                <a href="chinese_suppliers.php?edit=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (count($suppliers) > 0): ?>
                        <tfoot class="table-primary">
                            <tr>
                                <th colspan="4" class="text-end">Total Balance</th>
                                <th class="text-nowrap">¥ <?php echo number_format($totalBalanceCNY, 2); ?></th>
                                <th class="text-nowrap">PKR <?php echo number_format($totalBalancePKR, 2); ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#supplierSummaryTable').DataTable({
        "pageLength": 25,
        "order": [[1, "asc"]],
        "columnDefs": [
            { "orderable": false, "targets": [6] }
        ],
        "language": {
            "search": "Search Suppliers:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ suppliers",
            "emptyTable": "No Chinese suppliers found"
        }
    });
});
</script>

<?php
include '../includes/footer.php';
?>