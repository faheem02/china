<?php
/**
 * All Expenses - Print View / PDF Export
 * Manufacturing ERP System
 */

require_once '../includes/database.php';
requireLogin();

$today = date('Y-m-d');
$isAll = isset($_GET['all']) && $_GET['all'] == '1';

if ($isAll) {
    $fromDate = '';
    $toDate = '';
} elseif (isset($_GET['from_date']) || isset($_GET['to_date'])) {
    $fromDate = trim($_GET['from_date'] ?? '');
    $toDate = trim($_GET['to_date'] ?? '');
} else {
    // Default to today's expenses
    $fromDate = $today;
    $toDate = $today;
}

if (!empty($fromDate) && !empty($toDate) && $fromDate > $toDate) {
    $temp = $fromDate;
    $fromDate = $toDate;
    $toDate = $temp;
}

$categoryFilter = trim($_GET['category'] ?? '');
$paidByFilter = trim($_GET['paid_by'] ?? '');

$whereClauses = [];
$params = [];
$types = '';

if (!empty($fromDate) && !empty($toDate)) {
    $whereClauses[] = "e.expense_date BETWEEN ? AND ?";
    $params[] = $fromDate;
    $params[] = $toDate;
    $types .= 'ss';
} elseif (!empty($fromDate)) {
    $whereClauses[] = "e.expense_date >= ?";
    $params[] = $fromDate;
    $types .= 's';
} elseif (!empty($toDate)) {
    $whereClauses[] = "e.expense_date <= ?";
    $params[] = $toDate;
    $types .= 's';
}

if (!empty($categoryFilter)) {
    $whereClauses[] = "e.category = ?";
    $params[] = $categoryFilter;
    $types .= 's';
}

if (!empty($paidByFilter)) {
    if ($paidByFilter === 'bank') {
        $whereClauses[] = "e.paid_by IN ('bank', 'bank_transfer', 'cheque')";
    } elseif ($paidByFilter === 'cash') {
        $whereClauses[] = "(e.paid_by = 'cash' OR e.paid_by = '' OR e.paid_by IS NULL)";
    }
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

if (!empty($params)) {
    $expenses = getRows("SELECT e.* FROM expenses e $whereSql ORDER BY e.expense_date DESC, e.id DESC", $types, $params);
} else {
    $expenses = getRows("SELECT e.* FROM expenses e ORDER BY e.expense_date DESC, e.id DESC");
}

$totalAmount = array_sum(array_column($expenses, 'amount'));
$totalPaid = array_sum(array_column($expenses, 'paid_amount'));
$totalEntries = count($expenses);

$cashTotal = 0;
$bankTotal = 0;
foreach ($expenses as $ex) {
    if (in_array($ex['paid_by'], ['bank', 'bank_transfer', 'cheque'], true)) {
        $bankTotal += (float)$ex['amount'];
    } else {
        $cashTotal += (float)$ex['amount'];
    }
}

// Build back URL
$backParams = [];
if (!empty($fromDate)) $backParams['from_date'] = $fromDate;
if (!empty($toDate)) $backParams['to_date'] = $toDate;
if (!empty($categoryFilter)) $backParams['category'] = $categoryFilter;
if (!empty($paidByFilter)) $backParams['paid_by'] = $paidByFilter;
if ($isAll) $backParams['all'] = '1';
$backUrl = 'expenses.php' . (!empty($backParams) ? '?' . http_build_query($backParams) : '');

// Period display label
if ($isAll || (empty($fromDate) && empty($toDate))) {
    $periodText = 'All Time';
} elseif ($fromDate === $toDate) {
    if ($fromDate === $today) {
        $periodText = 'Today (' . formatDate($fromDate) . ')';
    } else {
        $periodText = formatDate($fromDate);
    }
} else {
    $periodText = formatDate($fromDate) . ' to ' . formatDate($toDate);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Expenses Report - <?php echo htmlspecialchars($periodText); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* { font-family: 'Poppins', sans-serif; box-sizing: border-box; }
body { background: #f0f2f5; margin: 0; padding: 0; color: #212529; }
.action-buttons { position: fixed; top: 0; left: 0; right: 0; z-index: 9999; background: #1a2332; padding: 10px 20px; display: flex; gap: 10px; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
.action-buttons .btn { font-size: 14px; }
.print-wrapper { margin-top: 70px; display: flex; justify-content: center; padding: 20px; }
.print-a4 { max-width: 1100px; width: 100%; background: #fff; padding: 35px 40px; border: 1px solid #ddd; }
.inv-title { text-align: center; background: #1a2332; color: #fff; padding: 8px 12px; margin-bottom: 15px; font-weight: 700; font-size: 15px; letter-spacing: 0.5px; border-radius: 4px; }
.inv-meta { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 12px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
.inv-meta strong { color: #1a2332; }
.summary-box { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.summary-item { flex: 1; min-width: 160px; background: #f8f9fa; border-left: 4px solid #1a2332; padding: 10px 14px; border-radius: 0 6px 6px 0; }
.summary-item .label { font-size: 11px; color: #666; text-transform: uppercase; font-weight: 600; }
.summary-item .amount { font-size: 17px; font-weight: 700; color: #1a2332; margin-top: 3px; }
.summary-item.danger { border-left-color: #dc3545; } .summary-item.danger .amount { color: #dc3545; }
.summary-item.success { border-left-color: #198754; } .summary-item.success .amount { color: #198754; }
.summary-item.info { border-left-color: #0d6efd; } .summary-item.info .amount { color: #0d6efd; }
.summary-item.primary { border-left-color: #6f42c1; } .summary-item.primary .amount { color: #6f42c1; }
table.print-table { width: 100%; font-size: 12px; border-collapse: collapse; margin-bottom: 20px; }
table.print-table thead th { background: #1a2332; color: #fff; padding: 8px 10px; font-weight: 600; border: 1px solid #1a2332; }
table.print-table tbody td { padding: 8px 10px; border: 1px solid #dee2e6; vertical-align: middle; }
table.print-table tbody tr:nth-child(even) { background: #fbfbfb; }
table.print-table tfoot td, table.print-table tfoot th { padding: 9px 10px; font-weight: 700; background: #e9ecef; border: 1px solid #ced4da; }
.badge-cat { display: inline-block; padding: 2px 8px; font-size: 10.5px; font-weight: 600; color: #fff; background-color: #0d6efd; border-radius: 4px; }
.badge-cash { display: inline-block; padding: 2px 7px; font-size: 10.5px; font-weight: 600; color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; }
.badge-bank { display: inline-block; padding: 2px 7px; font-size: 10.5px; font-weight: 600; color: #004085; background-color: #cce5ff; border: 1px solid #b8daff; border-radius: 4px; }
.sign-row { display: flex; justify-content: space-between; margin-top: 40px; padding-top: 15px; }
.sign-box { text-align: center; width: 180px; border-top: 1px solid #333; padding-top: 5px; font-size: 11px; font-weight: 600; color: #444; }
.inv-footer { text-align: center; margin-top: 25px; padding-top: 12px; border-top: 1px solid #ddd; font-size: 11px; color: #777; }
@media print {
    body { background: #fff !important; margin: 0; padding: 0; }
    .action-buttons { display: none !important; }
    .print-wrapper { margin: 0; padding: 0; }
    .print-a4 { border: none; padding: 10px; max-width: 100%; width: 100%; }
    @page { size: A4 landscape; margin: 10mm; }
}
</style>
</head>
<body>
<div class="action-buttons">
    <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-outline-light btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Back to Expenses
    </a>
    <span class="text-white fw-bold me-2"><i class="fas fa-receipt me-1"></i>Expenses Print Report</span>
    <button class="btn btn-light btn-sm" onclick="window.print()">
        <i class="fas fa-print me-1"></i>Print
    </button>
    <button class="btn btn-success btn-sm" onclick="downloadPDF()">
        <i class="fas fa-download me-1"></i>Download PDF
    </button>
</div>

<div class="print-wrapper">
<div class="print-a4" id="printContent">
    <?php include '../includes/print_branding.php'; ?>

    <div class="inv-title">
        EXPENSES REPORT | <?php echo htmlspecialchars(strtoupper($periodText)); ?>
    </div>

    <div class="inv-meta">
        <div>
            <div><strong>Filter Period:</strong> <?php echo htmlspecialchars($periodText); ?></div>
            <?php if (!empty($categoryFilter)): ?>
                <div><strong>Category:</strong> <?php echo htmlspecialchars($categoryFilter); ?></div>
            <?php endif; ?>
            <?php if (!empty($paidByFilter)): ?>
                <div><strong>Payment Method:</strong> <?php echo ucfirst(htmlspecialchars($paidByFilter)); ?></div>
            <?php endif; ?>
        </div>
        <div style="text-align: right;">
            <div><strong>Report Date:</strong> <?php echo date('d-m-Y h:i A'); ?></div>
            <div><strong>Generated By:</strong> <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin'; ?></div>
        </div>
    </div>

    <!-- Summary Box -->
    <div class="summary-box">
        <div class="summary-item danger">
            <div class="label">Total Expenses</div>
            <div class="amount"><?php echo formatCurrency($totalAmount); ?></div>
        </div>
        <div class="summary-item success">
            <div class="label">Cash Expenses</div>
            <div class="amount"><?php echo formatCurrency($cashTotal); ?></div>
        </div>
        <div class="summary-item info">
            <div class="label">Bank Expenses</div>
            <div class="amount"><?php echo formatCurrency($bankTotal); ?></div>
        </div>
        <div class="summary-item primary">
            <div class="label">Total Entries</div>
            <div class="amount"><?php echo number_format($totalEntries); ?></div>
        </div>
    </div>

    <!-- Detailed Table -->
    <table class="print-table">
        <thead>
            <tr>
                <th style="width: 40px; text-align: center;">#</th>
                <th style="width: 140px;">Expense No</th>
                <th style="width: 95px;">Date</th>
                <th style="width: 150px;">Category</th>
                <th>Description</th>
                <th style="width: 150px;">Payment Method</th>
                <th style="width: 120px; text-align: right;">Amount (PKR)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($expenses)): ?>
                <?php $idx = 1; foreach ($expenses as $exp): ?>
                    <tr>
                        <td style="text-align: center; color: #666;"><?php echo $idx++; ?></td>
                        <td><strong><?php echo htmlspecialchars($exp['expense_no']); ?></strong></td>
                        <td><?php echo formatDate($exp['expense_date']); ?></td>
                        <td><span class="badge-cat"><?php echo htmlspecialchars($exp['category']); ?></span></td>
                        <td><?php echo htmlspecialchars($exp['description'] ?: '-'); ?></td>
                        <td>
                            <?php if (in_array($exp['paid_by'], ['bank', 'bank_transfer', 'cheque'], true)): ?>
                                <span class="badge-bank"><i class="fas fa-university me-1"></i><?php echo htmlspecialchars($exp['bank_name'] ?: 'Bank'); ?></span>
                            <?php else: ?>
                                <span class="badge-cash"><i class="fas fa-money-bill-wave me-1"></i>Cash</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; font-weight: 700;">
                            <?php echo formatCurrency($exp['amount']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 25px; color: #888;">
                        <i class="fas fa-info-circle me-1"></i>No expenses found for the selected date filter.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="6" style="text-align: right;">TOTAL:</th>
                <th style="text-align: right;"><?php echo formatCurrency($totalAmount); ?></th>
            </tr>
        </tfoot>
    </table>

    <div class="sign-row">
        <div class="sign-box">Prepared By</div>
        <div class="sign-box">Accounts Department</div>
        <div class="sign-box">Authorized Signature</div>
    </div>

    <div class="inv-footer">
        <p class="mb-0">Generated on <?php echo date('d-m-Y h:i A'); ?> | <?php echo CLIENT_NAME; ?> - ERP System</p>
    </div>
</div>
</div>

<script>
function downloadPDF() {
    var opt = {
        margin: 0.3,
        filename: 'expenses_report_<?php echo date('Y-m-d'); ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    if (typeof html2pdf !== 'undefined') {
        html2pdf().set(opt).from(document.getElementById('printContent')).save();
    } else {
        var s = document.createElement('script');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
        s.onload = function() { html2pdf().set(opt).from(document.getElementById('printContent')).save(); };
        document.head.appendChild(s);
    }
}
</script>
</body>
</html>
