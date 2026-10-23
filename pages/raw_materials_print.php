<?php
require_once '../includes/database.php';
requireLogin();

// Check if printing a single raw material or the filtered list
$materialId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

if ($materialId > 0) {
    // ══════════════════════════════════════════════════════════════
    // MODE 1: SINGLE RAW MATERIAL ITEM SLIP
    // ══════════════════════════════════════════════════════════════
    $material = getRow("SELECT r.*, 
        CASE 
            WHEN r.supplier_type = 'chinese' THEN (SELECT supplier_name FROM chinese_suppliers WHERE id = r.supplier_id)
            WHEN r.supplier_type = 'local' THEN (SELECT supplier_name FROM local_suppliers WHERE id = r.supplier_id)
            ELSE NULL
        END as supplier_name
        FROM raw_materials r WHERE r.id = ?", 'i', [$materialId]);

    if (!$material) {
        setFlash('Raw material not found!', 'danger');
        header('Location: raw_materials.php');
        exit;
    }

    $stock = (float)$material['current_stock'];
    $costPricePkr = (float)$material['purchase_price_pkr'];
    $costPriceCny = (float)($material['purchase_price_cny'] ?? 0);
    $sellingPrice = (float)($material['selling_price'] ?: $costPricePkr);
    $costValue = $stock * $costPricePkr;
    $sellingValue = $stock * $sellingPrice;
    $margin = $costPricePkr > 0 ? (($sellingPrice - $costPricePkr) / $costPricePkr) * 100 : 0;

    // Recent Local Purchases (last 5)
    $purchases = getRows("SELECT lpi.*, lp.purchase_no, lp.purchase_date, ls.supplier_name 
        FROM local_purchase_items lpi 
        INNER JOIN local_purchases lp ON lp.id = lpi.purchase_id 
        LEFT JOIN local_suppliers ls ON lp.supplier_id = ls.id 
        WHERE lpi.material_id = ? 
        ORDER BY lp.purchase_date DESC, lp.id DESC LIMIT 5", 'i', [$materialId]);

    // Recent Issuance History (last 5)
    $issues = getRows("SELECT mii.*, mi.issue_no, mi.issue_date, mi.notes 
        FROM material_issue_items mii 
        INNER JOIN material_issues mi ON mi.id = mii.issue_id 
        WHERE mii.material_id = ? 
        ORDER BY mi.issue_date DESC, mi.id DESC LIMIT 5", 'i', [$materialId]);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material Slip - <?php echo htmlspecialchars($material['material_code']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    * { font-family: 'Poppins', sans-serif; }
    body { background: #f0f2f5; margin: 0; padding: 0; color: #212529; }
    .action-buttons { position: fixed; top: 0; left: 0; right: 0; z-index: 9999; background: #1a2332; padding: 10px 20px; display: flex; gap: 10px; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
    .action-buttons .btn { font-size: 14px; }
    .print-wrapper { margin-top: 70px; display: flex; justify-content: center; padding: 20px; }
    .print-a4 { max-width: 900px; width: 100%; background: #fff; padding: 35px 40px; border: 1px solid #ddd; }
    .inv-title { text-align: center; background: #f0f0f0; padding: 8px; border: 1px solid #ccc; margin-bottom: 20px; font-weight: 700; font-size: 14px; color: #1a2332; letter-spacing: 0.5px; }
    .summary-box { display: flex; gap: 12px; margin-bottom: 25px; flex-wrap: wrap; }
    .summary-item { flex: 1; min-width: 150px; background: #f8f9fa; border-left: 4px solid #1a2332; padding: 10px 14px; border-radius: 0 6px 6px 0; }
    .summary-item .label { font-size: 11px; color: #666; text-transform: uppercase; font-weight: 600; }
    .summary-item .amount { font-size: 17px; font-weight: 700; color: #1a2332; margin-top: 3px; }
    .summary-item.success { border-left-color: #198754; } .summary-item.success .amount { color: #198754; }
    .summary-item.danger { border-left-color: #dc3545; } .summary-item.danger .amount { color: #dc3545; }
    .summary-item.info { border-left-color: #0d6efd; } .summary-item.info .amount { color: #0d6efd; }
    .spec-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
    .spec-table th, .spec-table td { padding: 9px 12px; border: 1px solid #dee2e6; font-size: 13px; }
    .spec-table th { background: #f8f9fa; font-weight: 600; color: #1a2332; width: 25%; }
    .history-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }
    .history-table th { background: #1a2332; color: #fff; padding: 7px 10px; font-weight: 600; }
    .history-table td { padding: 6px 10px; border-bottom: 1px solid #eee; }
    .history-table tr:nth-child(even) { background: #fcfcfc; }
    .sign-row { display: flex; justify-content: space-between; margin-top: 40px; padding-top: 15px; }
    .sign-box { text-align: center; width: 180px; border-top: 1px solid #333; padding-top: 5px; font-size: 11px; font-weight: 600; color: #444; }
    .inv-footer { text-align: center; margin-top: 25px; padding-top: 12px; border-top: 1px solid #ddd; font-size: 11px; color: #777; }
    @media print {
        body { background: #fff !important; margin: 0; padding: 0; }
        .action-buttons { display: none !important; }
        .print-wrapper { margin: 0; padding: 5px; }
        .print-a4 { border: none; padding: 10px; max-width: 100%; width: 100%; }
        @page { size: A4 portrait; margin: 10mm; }
    }
    </style>
    </head>
    <body>
    <div class="action-buttons">
        <a href="raw_materials.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        <span class="text-white fw-bold me-2"><i class="fas fa-cubes me-1"></i>Raw Material Item Slip</span>
        <button class="btn btn-light btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
        <button class="btn btn-success btn-sm" onclick="downloadPDF()"><i class="fas fa-download me-1"></i>Download PDF</button>
    </div>

    <div class="print-wrapper">
    <div class="print-a4" id="printContent">
        <?php include '../includes/print_branding.php'; ?>
        
        <div class="inv-title">RAW MATERIAL ITEM SPECIFICATION &amp; STOCK SLIP</div>

        <div class="summary-box">
            <div class="summary-item">
                <div class="label">Current Stock</div>
                <div class="amount"><?php echo number_format($stock, 2); ?> <small style="font-size:12px;color:#666;"><?php echo htmlspecialchars($material['unit']); ?></small></div>
            </div>
            <div class="summary-item danger">
                <div class="label">Stock Cost (PKR)</div>
                <div class="amount">Rs. <?php echo formatCurrency($costValue); ?></div>
            </div>
            <div class="summary-item success">
                <div class="label">Stock Sale Value</div>
                <div class="amount">Rs. <?php echo formatCurrency($sellingValue); ?></div>
            </div>
            <div class="summary-item info">
                <div class="label">Supplier Type</div>
                <div class="amount" style="font-size:15px;"><?php echo ucfirst($material['supplier_type'] ?? 'Local'); ?></div>
            </div>
        </div>

        <table class="spec-table">
            <tr>
                <th>Material Code</th>
                <td><strong class="text-primary" style="font-size:14px;"><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                <th>Status</th>
                <td>
                    <span class="badge bg-<?php echo ($material['status'] ?? 'active') === 'active' ? 'success' : 'secondary'; ?>">
                        <?php echo ucfirst($material['status'] ?? 'active'); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Material Name</th>
                <td colspan="3"><strong style="font-size:15px;"><?php echo htmlspecialchars($material['material_name']); ?></strong></td>
            </tr>
            <tr>
                <th>Category</th>
                <td><?php echo htmlspecialchars($material['category'] ?: '-'); ?></td>
                <th>Unit</th>
                <td><?php echo htmlspecialchars($material['unit'] ?: 'units'); ?></td>
            </tr>
            <tr>
                <th>Current Stock</th>
                <td><strong style="font-size:14px;"><?php echo number_format($stock, 2); ?> <?php echo htmlspecialchars($material['unit']); ?></strong></td>
                <th>Minimum Stock</th>
                <td><?php echo number_format((float)$material['minimum_stock'], 2); ?> <?php echo htmlspecialchars($material['unit']); ?></td>
            </tr>
            <tr>
                <th>Purchase Price (PKR)</th>
                <td class="text-danger fw-bold">Rs. <?php echo formatCurrency($costPricePkr); ?></td>
                <th>Price (CNY)</th>
                <td>
                    <?php if ($material['supplier_type'] === 'chinese' && $costPriceCny > 0): ?>
                        <span class="badge bg-info text-dark">¥ <?php echo number_format($costPriceCny, 2); ?></span>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Total Stock Cost (PKR)</th>
                <td class="text-danger fw-bold">Rs. <?php echo formatCurrency($costValue); ?></td>
                <th>Selling Price / Unit</th>
                <td class="text-success fw-bold">Rs. <?php echo formatCurrency($sellingPrice); ?></td>
            </tr>
            <tr>
                <th>Default Supplier</th>
                <td>
                    <span class="badge bg-<?php echo $material['supplier_type'] === 'chinese' ? 'info' : 'success'; ?> me-1">
                        <?php echo ucfirst($material['supplier_type']); ?>
                    </span>
                    <strong><?php echo htmlspecialchars($material['supplier_name'] ?: 'None specified'); ?></strong>
                </td>
                <th>Date Added / Modified</th>
                <td><?php echo !empty($material['date_time']) ? date('d-m-Y h:i A', strtotime($material['date_time'])) : '-'; ?></td>
            </tr>
        </table>

        <?php if (!empty($purchases)): ?>
            <div style="font-size:13px; font-weight:700; color:#1a2332; margin-bottom:6px;">
                <i class="fas fa-shopping-bag me-1 text-primary"></i>Recent Local Purchase Records
            </div>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Purchase No</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th style="text-align:right">Qty</th>
                        <th style="text-align:right">Rate (Rs)</th>
                        <th style="text-align:right">Total (Rs)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchases as $pc): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($pc['purchase_no']); ?></strong></td>
                            <td><?php echo formatDate($pc['purchase_date']); ?></td>
                            <td><?php echo htmlspecialchars($pc['supplier_name'] ?: '-'); ?></td>
                            <td style="text-align:right" class="fw-bold text-primary"><?php echo number_format((float)$pc['quantity'], 2); ?></td>
                            <td style="text-align:right"><?php echo formatCurrency($pc['unit_price']); ?></td>
                            <td style="text-align:right" class="fw-bold text-success"><?php echo formatCurrency($pc['total']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($issues)): ?>
            <div style="font-size:13px; font-weight:700; color:#1a2332; margin-bottom:6px; margin-top:15px;">
                <i class="fas fa-sign-out-alt me-1 text-danger"></i>Recent Issuance Records
            </div>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Issue No</th>
                        <th>Date</th>
                        <th style="text-align:right">Issued Qty</th>
                        <th style="text-align:right">Rate (Rs)</th>
                        <th style="text-align:right">Total (Rs)</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($issues as $is): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($is['issue_no']); ?></strong></td>
                            <td><?php echo formatDate($is['issue_date']); ?></td>
                            <td style="text-align:right" class="fw-bold text-danger"><?php echo number_format((float)$is['quantity'], 2); ?></td>
                            <td style="text-align:right"><?php echo formatCurrency($is['unit_price']); ?></td>
                            <td style="text-align:right" class="fw-bold text-success"><?php echo formatCurrency($is['total']); ?></td>
                            <td><?php echo htmlspecialchars($is['notes'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="sign-row">
            <div class="sign-box">Prepared By</div>
            <div class="sign-box">Store In-Charge</div>
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
            filename: 'raw_material_<?php echo htmlspecialchars($material['material_code']); ?>_<?php echo date('Y-m-d'); ?>.pdf', 
            image: { type: 'jpeg', quality: 0.98 }, 
            html2canvas: { scale: 2 }, 
            jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' } 
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
    <?php
    exit;
}

// ══════════════════════════════════════════════════════════════
// MODE 2: FILTERED RAW MATERIALS STOCK & VALUATION REPORT
// ══════════════════════════════════════════════════════════════
$fromDate = isset($_GET['from_date']) && $_GET['from_date'] !== '' ? trim($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) && $_GET['to_date'] !== '' ? trim($_GET['to_date']) : '';
$isLowStock = isset($_GET['low_stock']) && $_GET['low_stock'] == '1';

$where = [];
$params = [];
$types = '';

if ($isLowStock) {
    $where[] = "((r.current_stock <= r.minimum_stock AND r.minimum_stock > 0) OR r.current_stock <= 0)";
}

if ($fromDate !== '') {
    $where[] = "DATE(r.date_time) >= ?";
    $params[] = $fromDate;
    $types .= 's';
}
if ($toDate !== '') {
    $where[] = "DATE(r.date_time) <= ?";
    $params[] = $toDate;
    $types .= 's';
}

$whereSql = !empty($where) ? " WHERE " . implode(' AND ', $where) : "";
$sql = "SELECT r.*, 
    CASE 
        WHEN r.supplier_type = 'chinese' THEN (SELECT supplier_name FROM chinese_suppliers WHERE id = r.supplier_id)
        WHEN r.supplier_type = 'local' THEN (SELECT supplier_name FROM local_suppliers WHERE id = r.supplier_id)
        ELSE NULL
    END as supplier_name
    FROM raw_materials r " . $whereSql . " ORDER BY r.id DESC";

$materials = !empty($params) ? getRows($sql, $types, $params) : getRows($sql);

$totalCount = count($materials);
$totalStockQty = 0;
$totalCostValue = 0;
$totalSellingValue = 0;

foreach ($materials as $m) {
    $stock = (float)$m['current_stock'];
    $cost = (float)$m['purchase_price_pkr'];
    $sell = (float)($m['selling_price'] ?: $cost);
    $totalStockQty += $stock;
    $totalCostValue += ($stock * $cost);
    $totalSellingValue += ($stock * $sell);
}
$potentialProfit = $totalSellingValue - $totalCostValue;

// Filtered material issue stats
if ($fromDate !== '' && $toDate !== '') {
    $miStats = getRow("SELECT COALESCE(SUM(quantity), 0) AS qty, COALESCE(SUM(total), 0) AS val 
        FROM material_issue_items mii 
        INNER JOIN material_issues mi ON mi.id = mii.issue_id 
        WHERE mi.issue_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate]);
} elseif ($fromDate !== '') {
    $miStats = getRow("SELECT COALESCE(SUM(quantity), 0) AS qty, COALESCE(SUM(total), 0) AS val 
        FROM material_issue_items mii 
        INNER JOIN material_issues mi ON mi.id = mii.issue_id 
        WHERE mi.issue_date >= ?", 's', [$fromDate]);
} elseif ($toDate !== '') {
    $miStats = getRow("SELECT COALESCE(SUM(quantity), 0) AS qty, COALESCE(SUM(total), 0) AS val 
        FROM material_issue_items mii 
        INNER JOIN material_issues mi ON mi.id = mii.issue_id 
        WHERE mi.issue_date <= ?", 's', [$toDate]);
} else {
    $miStats = getRow("SELECT COALESCE(SUM(quantity), 0) AS qty, COALESCE(SUM(total), 0) AS val 
        FROM material_issue_items");
}
$issuedQty = (float)($miStats['qty'] ?? 0);
$issuedVal = (float)($miStats['val'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Raw Materials Report - Print</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* { font-family: 'Poppins', sans-serif; }
body { background: #f0f2f5; margin: 0; padding: 0; color: #212529; }
.action-buttons { position: fixed; top: 0; left: 0; right: 0; z-index: 9999; background: #1a2332; padding: 10px 20px; display: flex; gap: 10px; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
.action-buttons .btn { font-size: 14px; }
.print-wrapper { margin-top: 70px; display: flex; justify-content: center; padding: 20px; }
.print-a4 { max-width: 1100px; width: 100%; background: #fff; padding: 35px 40px; border: 1px solid #ddd; }
.inv-title { text-align: center; background: #f0f0f0; padding: 8px; border: 1px solid #ccc; margin-bottom: 6px; font-weight: 700; font-size: 14px; color: #1a2332; letter-spacing: 0.5px; }
.inv-subtitle { text-align: center; font-size: 12px; color: #555; margin-bottom: 20px; font-weight: 500; }
.summary-box { display: flex; gap: 12px; margin-bottom: 22px; flex-wrap: wrap; }
.summary-item { flex: 1; min-width: 170px; background: #f8f9fa; border-left: 4px solid #1a2332; padding: 10px 14px; border-radius: 0 6px 6px 0; }
.summary-item .label { font-size: 11px; color: #666; text-transform: uppercase; font-weight: 600; }
.summary-item .amount { font-size: 17px; font-weight: 700; color: #1a2332; margin-top: 3px; }
.summary-item.secondary { border-left-color: #6c757d; }
.summary-item.danger { border-left-color: #dc3545; } .summary-item.danger .amount { color: #dc3545; }
.summary-item.success { border-left-color: #28a745; } .summary-item.success .amount { color: #28a745; }
.summary-item.info { border-left-color: #0d6efd; } .summary-item.info .amount { color: #0d6efd; }

.print-a4 table { width: 100%; font-size: 11.5px; border-collapse: collapse; }
.print-a4 table thead th { background: #1a2332; color: #fff; padding: 8px 9px; font-weight: 600; white-space: nowrap; font-size: 11.5px; }
.print-a4 table tbody td { padding: 7px 9px; border-bottom: 1px solid #eee; font-size: 11.5px; vertical-align: middle; }
.print-a4 table tbody tr:nth-child(even) { background: #fcfcfc; }
.print-a4 table tfoot td, .print-a4 table tfoot th { padding: 8px 9px; font-weight: 700; background: #e9ecef; border-top: 2px solid #1a2332; font-size: 11.5px; }
.sign-row { display: flex; justify-content: space-between; margin-top: 35px; padding-top: 15px; }
.sign-box { text-align: center; width: 180px; border-top: 1px solid #333; padding-top: 5px; font-size: 11px; font-weight: 600; color: #444; }
.inv-footer { text-align: center; margin-top: 25px; padding-top: 12px; border-top: 1px solid #ddd; font-size: 11px; color: #777; }
@media print {
    body { background: #fff !important; margin: 0; padding: 0; }
    .action-buttons { display: none !important; }
    .print-wrapper { margin: 0; padding: 5px; }
    .print-a4 { border: none; padding: 10px; max-width: 100%; width: 100%; }
    @page { size: A4 landscape; margin: 8mm; }
}
</style>
</head>
<body>
<div class="action-buttons">
    <a href="raw_materials.php<?php echo ($fromDate || $toDate) ? '?from_date=' . urlencode($fromDate) . '&to_date=' . urlencode($toDate) : ''; ?>" class="btn btn-outline-light btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Back
    </a>
    <span class="text-white fw-bold me-2"><i class="fas fa-cubes me-1"></i>Raw Materials Stock Report</span>
    <button class="btn btn-light btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
    <button class="btn btn-success btn-sm" onclick="downloadPDF()"><i class="fas fa-download me-1"></i>Download PDF</button>
</div>

<div class="print-wrapper">
<div class="print-a4" id="printContent">
    <?php include '../includes/print_branding.php'; ?>

    <div class="inv-title">
        <?php echo $isLowStock ? 'RAW MATERIALS REPORT &mdash; LOW STOCK &amp; OUT OF STOCK ALERT' : 'RAW MATERIALS STOCK &amp; VALUATION REPORT'; ?>
    </div>
    <div class="inv-subtitle">
        <?php if ($isLowStock): ?>
            <span class="badge bg-warning text-dark me-2" style="font-size:12px;">Alert: Filtered for Low Stock &amp; Out of Stock Items</span>
        <?php endif; ?>
        <?php if ($fromDate !== '' && $toDate !== ''): ?>
            <strong>Filter Period:</strong> <?php echo formatDate($fromDate); ?> &mdash; <?php echo formatDate($toDate); ?>
        <?php elseif ($fromDate !== ''): ?>
            <strong>Filter Period:</strong> From <?php echo formatDate($fromDate); ?> onwards
        <?php elseif ($toDate !== ''): ?>
            <strong>Filter Period:</strong> Up to <?php echo formatDate($toDate); ?>
        <?php else: ?>
            <strong>Scope:</strong> <?php echo $isLowStock ? 'All Reorder Alert Items' : 'Complete Inventory &mdash; All Records'; ?>
        <?php endif; ?>
    </div>

    <div class="summary-box">
        <div class="summary-item secondary">
            <div class="label">Total Stock Qty</div>
            <div class="amount"><?php echo number_format($totalStockQty, 2); ?></div>
        </div>
        <div class="summary-item danger">
            <div class="label">Stock Cost Value (PKR)</div>
            <div class="amount">Rs. <?php echo formatCurrency($totalCostValue); ?></div>
        </div>
        <div class="summary-item success">
            <div class="label">Stock Sale Value</div>
            <div class="amount">Rs. <?php echo formatCurrency($totalSellingValue); ?></div>
        </div>
        <div class="summary-item info">
            <div class="label">Total Issued Value</div>
            <div class="amount">Rs. <?php echo formatCurrency($issuedVal); ?></div>
        </div>
        <div class="summary-item">
            <div class="label">Potential Margin</div>
            <div class="amount <?php echo $potentialProfit >= 0 ? 'text-success' : 'text-danger'; ?>">
                Rs. <?php echo formatCurrency($potentialProfit); ?>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 35px;">#</th>
                <th>Code</th>
                <th>Material Name</th>
                <th>Category</th>
                <th>Unit</th>
                <th>Date Added</th>
                <th style="text-align:right">Stock</th>
                <th style="text-align:right">Cost PKR</th>
                <th style="text-align:right">Price CNY</th>
                <th>Supplier</th>
                <th style="text-align:right">Cost Val (Rs)</th>
                <th style="text-align:right">Sale Val (Rs)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($materials) > 0): ?>
                <?php $counter = 1; foreach ($materials as $m): 
                    $cVal = (float)$m['current_stock'] * (float)$m['purchase_price_pkr'];
                    $sVal = (float)$m['current_stock'] * (float)($m['selling_price'] ?: $m['purchase_price_pkr']);
                ?>
                <tr>
                    <td><?php echo $counter++; ?></td>
                    <td><strong><?php echo htmlspecialchars($m['material_code']); ?></strong></td>
                    <td><strong><?php echo htmlspecialchars($m['material_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($m['category'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($m['unit'] ?: 'units'); ?></td>
                    <td><?php echo !empty($m['date_time']) ? date('d-m-Y', strtotime($m['date_time'])) : '-'; ?></td>
                    <td style="text-align:right">
                        <?php 
                        $cStock = (float)$m['current_stock'];
                        $mStock = (float)$m['minimum_stock'];
                        $isOut = ($cStock <= 0);
                        $isLow = (!$isOut && $mStock > 0 && $cStock <= $mStock);
                        ?>
                        <span class="fw-bold <?php echo $isOut ? 'text-danger' : ($isLow ? 'text-danger' : 'text-primary'); ?>">
                            <?php echo number_format($cStock, 2); ?>
                        </span>
                        <?php if ($isOut): ?>
                            <div style="font-size:9.5px;color:#dc3545;font-weight:600;">[Out of Stock]</div>
                        <?php elseif ($isLow): ?>
                            <div style="font-size:9.5px;color:#d39e00;font-weight:600;">[Low &middot; Min: <?php echo number_format($mStock, 2); ?>]</div>
                        <?php elseif ($mStock > 0): ?>
                            <div style="font-size:9px;color:#777;">Min: <?php echo number_format($mStock, 2); ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right"><?php echo formatCurrency($m['purchase_price_pkr']); ?></td>
                    <td style="text-align:right">
                        <?php if ($m['supplier_type'] === 'chinese' && !empty($m['purchase_price_cny'])): ?>
                            <span class="badge bg-info text-dark">¥ <?php echo number_format($m['purchase_price_cny'], 2); ?></span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-<?php echo $m['supplier_type'] === 'chinese' ? 'info' : 'success'; ?> me-1">
                            <?php echo ucfirst($m['supplier_type']); ?>
                        </span>
                        <small><?php echo htmlspecialchars($m['supplier_name'] ?: '-'); ?></small>
                    </td>
                    <td style="text-align:right; font-weight:700;" class="text-danger"><?php echo formatCurrency($cVal); ?></td>
                    <td style="text-align:right; font-weight:700;" class="text-success"><?php echo formatCurrency($sVal); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="12" style="text-align:center; color:#999; padding:30px;">
                        <i class="fas fa-info-circle me-1"></i>No raw materials found for the selected date range
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="6" style="text-align:right">TOTAL</th>
                <th style="text-align:right"><?php echo number_format($totalStockQty, 2); ?></th>
                <th></th>
                <th></th>
                <th></th>
                <th style="text-align:right" class="text-danger">Rs. <?php echo formatCurrency($totalCostValue); ?></th>
                <th style="text-align:right" class="text-success">Rs. <?php echo formatCurrency($totalSellingValue); ?></th>
            </tr>
        </tfoot>
    </table>

    <div class="sign-row">
        <div class="sign-box">Prepared By</div>
        <div class="sign-box">Store Manager</div>
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
        margin: 0.25, 
        filename: 'raw_materials_report_<?php echo date('Y-m-d'); ?>.pdf', 
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
