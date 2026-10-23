<?php
/**
 * All Suppliers Summary
 * Manufacturing ERP System
 */

// Include database connection
require_once '../includes/database.php';

// Require login
requireLogin();

$pageTitle = 'Suppliers Summary';
$message = '';
$messageType = '';
$dbError = '';

// Default values in case query fails
$suppliers = [];
$totalBalance = 0;
$totalPurchases = 0;
$totalPaid = 0;
$totalOutstanding = 0;

// Get date filters
$asOnDate = isset($_GET['as_on_date']) ? $_GET['as_on_date'] : date('Y-m-d');

// Get all suppliers with their balances
try {
$sql = "SELECT 
            s.*,
            (SELECT COUNT(*) FROM local_purchases WHERE supplier_id = s.id) as total_purchases,
            (SELECT COALESCE(SUM(total_amount), 0) FROM local_purchases WHERE supplier_id = s.id) as total_purchase_amount,
            (SELECT COALESCE(SUM(paid_amount), 0) FROM local_purchases WHERE supplier_id = s.id) as total_paid_amount,
            (SELECT COALESCE(SUM(balance), 0) FROM local_purchases WHERE supplier_id = s.id) as total_balance,
            COALESCE((SELECT SUM(sp.amount) FROM supplier_payments sp WHERE sp.supplier_id = s.id), 0) as payments_made,
            (s.opening_balance + (SELECT COALESCE(SUM(balance), 0) FROM local_purchases WHERE supplier_id = s.id) - COALESCE((SELECT SUM(sp.amount) FROM supplier_payments sp WHERE sp.supplier_id = s.id), 0)) as current_balance
        FROM local_suppliers s 
        ORDER BY s.supplier_name";
$suppliers = getRows($sql);

// Calculate totals
$totalBalance = 0;
$totalPurchases = 0;
$totalPaid = 0;
$totalOutstanding = 0;

foreach ($suppliers as $supplier) {
    $totalBalance += $supplier['current_balance'];
    $totalPurchases += $supplier['total_purchase_amount'];
    $totalPaid += $supplier['total_paid_amount'];
    $totalOutstanding += $supplier['total_balance'];
}
} catch (\Throwable $e) {
    $dbError = $e->getMessage();
    $suppliers = [];
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
            <h5><i class="fas fa-users me-2"></i>All Suppliers Summary</h5>
            <div>
                <a href="../index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
                <button type="button" class="btn btn-primary btn-sm ms-1" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print Report
                </button>
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
                    <div class="stat-number"><?php echo count($suppliers); ?></div>
                    <div class="stat-label">Total Suppliers</div>
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
                    <div class="stat-number"><?php echo formatCurrency($totalPurchases); ?></div>
                    <div class="stat-label">Total Purchases</div>
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
                    <div class="stat-number"><?php echo formatCurrency($totalPaid); ?></div>
                    <div class="stat-label">Total Paid</div>
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
                    <div class="stat-number"><?php echo formatCurrency($totalOutstanding); ?></div>
                    <div class="stat-label">Outstanding Balance</div>
                </div>
                <div class="stat-icon" style="color: #dc3545;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Suppliers Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list me-2"></i>Suppliers List with Balances
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="supplierSummaryTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Supplier Name</th>
                                <th>Company</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Current Balance</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($suppliers) > 0): ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($supplier['supplier_name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($supplier['company_name'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($supplier['phone'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($supplier['address'] ?: '-'); ?></td>
                                        <td class="<?php echo $supplier['current_balance'] < 0 ? 'text-danger' : 'text-success'; ?>">
                                            <strong><?php echo formatCurrency($supplier['current_balance']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-status badge-<?php echo $supplier['status']; ?>">
                                                <?php echo ucfirst($supplier['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="supplier_detail.php?id=<?php echo $supplier['id']; ?>" class="btn btn-info" title="View Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="supplier_ledger.php?id=<?php echo $supplier['id']; ?>" class="btn btn-primary" title="View Ledger">
                                                    <i class="fas fa-book"></i>
                                                </a>
                                                <a href="local_suppliers.php?edit=<?php echo $supplier['id']; ?>" class="btn btn-warning" title="Edit">
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
                                <th colspan="5" class="text-end">Total Balance</th>
                                <th><?php echo formatCurrency($totalBalance); ?></th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
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
    $('#supplierSummaryTable').DataTable({
        "pageLength": 25,
        "order": [[1, "asc"]],
        "columnDefs": [
            { "orderable": false, "targets": [7] }
        ],
        "language": {
            "search": "Search Suppliers:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ suppliers",
            "emptyTable": "No suppliers found"
        }
    });
});
</script>

<?php
include '../includes/footer.php';
?>