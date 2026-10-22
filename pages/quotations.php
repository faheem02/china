<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Quotations';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    modifyData("DELETE FROM quotation_items WHERE quotation_id = ?", 'i', [$delId]);
    $res = modifyData("DELETE FROM quotations WHERE id = ?", 'i', [$delId]);
    if ($res !== false) {
        setFlash('Quotation deleted successfully!', 'success');
    } else {
        setFlash('Error deleting quotation!', 'danger');
    }
    header('Location: quotations.php');
    exit;
}

$fromDate = isset($_GET['from_date']) && $_GET['from_date'] !== '' ? trim($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) && $_GET['to_date'] !== '' ? trim($_GET['to_date']) : '';

$where = [];
$params = [];
$types = '';

if ($fromDate !== '') {
    $where[] = "q.quotation_date >= ?";
    $params[] = $fromDate;
    $types .= 's';
}
if ($toDate !== '') {
    $where[] = "q.quotation_date <= ?";
    $params[] = $toDate;
    $types .= 's';
}

$whereSql = !empty($where) ? " WHERE " . implode(' AND ', $where) : "";
$sql = "SELECT q.*, 
    COALESCE(c.customer_name, q.walkin_name, 'Client') AS client_name,
    c.phone AS client_phone,
    (SELECT COUNT(*) FROM quotation_items WHERE quotation_id = q.id) AS items_count
    FROM quotations q
    LEFT JOIN customers c ON q.customer_id = c.id
    " . $whereSql . " ORDER BY q.id DESC";

$quotations = !empty($params) ? getRows($sql, $types, $params) : getRows($sql);

// Summary Stats
$totalCount = count($quotations);
$totalValue = 0;
$taxQuotationsValue = 0;
$nonTaxQuotationsValue = 0;

foreach ($quotations as $q) {
    $val = (float)$q['total_amount'];
    $totalValue += $val;
    if (($q['invoice_type'] ?? 'non_tax') === 'tax') {
        $taxQuotationsValue += $val;
    } else {
        $nonTaxQuotationsValue += $val;
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<?php if ($message): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Top Action Header -->
<div class="row mb-3 align-items-center">
    <div class="col-md-6 mb-2 mb-md-0">
        <a href="new_quotation.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>New Quotation
        </a>
    </div>
    <div class="col-md-6 text-md-end no-print">
        <a href="../index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>
</div>

<!-- Date Filter Card -->
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body py-2 px-3">
        <form method="GET" action="quotations.php" class="row g-2 align-items-end">
            <div class="col-md-4 col-sm-6">
                <label class="form-label small mb-1 fw-semibold text-muted">From Date</label>
                <input type="date" class="form-control form-control-sm" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
            </div>
            <div class="col-md-4 col-sm-6">
                <label class="form-label small mb-1 fw-semibold text-muted">To Date</label>
                <input type="date" class="form-control form-control-sm" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
            </div>
            <div class="col-md-4 col-sm-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                    <i class="fas fa-search me-1"></i>Filter
                </button>
                <?php if ($fromDate !== '' || $toDate !== ''): ?>
                    <a href="quotations.php" class="btn btn-outline-secondary btn-sm" title="Reset filter">
                        <i class="fas fa-undo me-1"></i>Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-3">
    <div class="col-md-4 col-sm-6 mb-3 mb-md-0">
        <div class="card h-100" style="border-left: 4px solid #1a2332;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total Quotations</div>
                        <h4 class="mb-0"><?php echo number_format($totalCount); ?></h4>
                        <div class="text-muted small"><?php echo ($fromDate || $toDate) ? 'In selected range' : 'All created quotations'; ?></div>
                    </div>
                    <i class="fas fa-file-invoice fa-2x text-muted"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-sm-6 mb-3 mb-md-0">
        <div class="card h-100" style="border-left: 4px solid #0d6efd;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total Value (PKR)</div>
                        <h4 class="mb-0 text-primary">Rs. <?php echo formatCurrency($totalValue); ?></h4>
                        <div class="text-muted small">Combined quotations value</div>
                    </div>
                    <i class="fas fa-money-bill-wave fa-2x text-primary"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-sm-12">
        <div class="card h-100" style="border-left: 4px solid #198754;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Tax vs Non-Tax Value</div>
                        <h5 class="mb-0 text-success">Tax: Rs. <?php echo formatCurrency($taxQuotationsValue); ?></h5>
                        <div class="text-muted small">Non-Tax: Rs. <?php echo formatCurrency($nonTaxQuotationsValue); ?></div>
                    </div>
                    <i class="fas fa-percent fa-2x text-success"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quotations Table Card -->
<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle" id="quotationsTable">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Quotation No</th>
                        <th>Date</th>
                        <th>Valid Until</th>
                        <th>Client / Customer</th>
                        <th class="text-center">Items</th>
                        <th class="text-center">Type</th>
                        <th class="text-end">Shipping</th>
                        <th class="text-end">Total Amount</th>
                        <th class="text-center" style="width: 110px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($quotations) > 0): ?>
                        <?php $cIdx = 1; foreach ($quotations as $row): ?>
                            <tr>
                                <td><?php echo $cIdx++; ?></td>
                                <td>
                                    <strong class="text-primary"><?php echo htmlspecialchars($row['quotation_no']); ?></strong>
                                </td>
                                <td><?php echo formatDate($row['quotation_date']); ?></td>
                                <td>
                                    <?php if (!empty($row['valid_until'])): ?>
                                        <span class="<?php echo $row['valid_until'] < date('Y-m-d') ? 'text-danger fw-semibold' : ''; ?>">
                                            <?php echo formatDate($row['valid_until']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['client_name']); ?></strong>
                                    <?php if (!empty($row['client_phone'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($row['client_phone']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><?php echo (int)$row['items_count']; ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if (($row['invoice_type'] ?? 'non_tax') === 'tax'): ?>
                                        <span class="badge bg-primary">Tax (<?php echo number_format((float)$row['tax_rate'], 1); ?>%)</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Non-Tax</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ((float)$row['shipping_cost'] > 0): ?>
                                        Rs. <?php echo formatCurrency($row['shipping_cost']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold text-success fs-6">
                                    Rs. <?php echo formatCurrency($row['total_amount']); ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="quotation_print.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-info text-white" title="Print Quotation">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this quotation?');" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#quotationsTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        language: {
            search: "Search Quotations:",
            emptyTable: "No quotations found"
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>