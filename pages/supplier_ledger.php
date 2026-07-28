<?php
/**
 * Supplier Ledger Page
 * Manufacturing ERP System
 */

// Include database connection
require_once '../includes/database.php';

// Require login
requireLogin();

$pageTitle = 'Supplier Ledger';
$message = '';
$messageType = '';

// Get supplier ID from URL
$supplierId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($supplierId <= 0) {
    header('Location: local_suppliers.php');
    exit;
}

// Get supplier details with calculated current_balance
$supplier = getRow("SELECT s.*,
    COALESCE(SUM(lp.balance), 0) AS purchase_balance,
    COALESCE((SELECT SUM(sp.amount) FROM supplier_payments sp WHERE sp.supplier_id = s.id), 0) AS payments_made,
    (s.opening_balance + COALESCE(SUM(lp.balance), 0) - COALESCE((SELECT SUM(sp.amount) FROM supplier_payments sp WHERE sp.supplier_id = s.id), 0)) AS current_balance
    FROM local_suppliers s
    LEFT JOIN local_purchases lp ON lp.supplier_id = s.id
    WHERE s.id = ?
    GROUP BY s.id", 'i', [$supplierId]);

if (!$supplier) {
    header('Location: local_suppliers.php');
    exit;
}

// Get date filters
$fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$toDate = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

// Get supplier transactions
$sql = "SELECT 
            'Purchase' as transaction_type,
            purchase_no as reference_no,
            purchase_date as transaction_date,
            total_amount as amount,
            paid_amount,
            balance,
            payment_status,
            status,
            invoice_no
        FROM local_purchases 
        WHERE supplier_id = ? 
        AND purchase_date BETWEEN ? AND ?
        ORDER BY purchase_date ASC, id ASC";
$purchases = getRows($sql, 'iss', [$supplierId, $fromDate, $toDate]);

// Calculate running balance
$runningBalance = $supplier['opening_balance'];

// Include header
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';
?>

<!-- Page Content -->
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
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="local_suppliers.php">Local Suppliers</a></li>
                <li class="breadcrumb-item"><a href="supplier_detail.php?id=<?php echo $supplierId; ?>">Supplier Detail</a></li>
                <li class="breadcrumb-item active">Supplier Ledger</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Supplier Header -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-book me-2"></i>Ledger - <?php echo htmlspecialchars($supplier['supplier_name']); ?>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Opening Balance:</strong>
                        <span class="text-primary"><?php echo formatCurrency($supplier['opening_balance']); ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Current Balance:</strong>
                        <span class="<?php echo $supplier['current_balance'] < 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo formatCurrency($supplier['current_balance']); ?>
                        </span>
                    </div>
                    <div class="col-md-6 text-end">
                        <a href="supplier_ledger_print.php?id=<?php echo $supplierId; ?>&from_date=<?php echo $fromDate; ?>&to_date=<?php echo $toDate; ?>" class="btn btn-primary btn-sm" target="_blank">
                            <i class="fas fa-print me-2"></i>Print Ledger
                        </a>
                        <a href="supplier_detail.php?id=<?php echo $supplierId; ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left me-2"></i>Back to Detail
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <input type="hidden" name="id" value="<?php echo $supplierId; ?>">
                    <div class="col-md-4">
                        <label for="from_date" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="from_date" name="from_date" value="<?php echo $fromDate; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="to_date" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="to_date" name="to_date" value="<?php echo $toDate; ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>Filter
                        </button>
                        <a href="supplier_ledger.php?id=<?php echo $supplierId; ?>" class="btn btn-secondary ms-2">
                            <i class="fas fa-undo me-2"></i>Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Ledger Table -->
<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list me-2"></i>Transaction History
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="ledgerTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Invoice No</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Running Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($purchases) > 0): ?>
                                <?php 
                                $counter = 1;
                                foreach ($purchases as $purchase): 
                                    $runningBalance += $purchase['balance'];
                                ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo date('d-m-Y', strtotime($purchase['transaction_date'])); ?></td>
                                        <td>
                                            <a href="purchase_detail.php?type=local&id=<?php echo $purchase['reference_no']; ?>">
                                                <?php echo htmlspecialchars($purchase['reference_no']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($purchase['invoice_no']); ?></td>
                                        <td>
                                            <span class="badge bg-primary">Purchase</span>
                                        </td>
                                        <td><?php echo formatCurrency($purchase['amount']); ?></td>
                                        <td><?php echo formatCurrency($purchase['paid_amount']); ?></td>
                                        <td><?php echo formatCurrency($purchase['balance']); ?></td>
                                        <td><?php echo formatCurrency($runningBalance); ?></td>
                                        <td>
                                            <span class="badge badge-status badge-<?php echo strtolower($purchase['payment_status']); ?>">
                                                <?php echo ucfirst($purchase['payment_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-3">
                                        <i class="fas fa-info-circle me-2"></i>No transactions found for this period
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-primary">
                                <th colspan="5" class="text-end">Total</th>
                                <th><?php echo formatCurrency(array_sum(array_column($purchases, 'amount'))); ?></th>
                                <th><?php echo formatCurrency(array_sum(array_column($purchases, 'paid_amount'))); ?></th>
                                <th><?php echo formatCurrency(array_sum(array_column($purchases, 'balance'))); ?></th>
                                <th><?php echo formatCurrency($runningBalance); ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include '../includes/footer.php';
?>