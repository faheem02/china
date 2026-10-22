<?php
require_once '../includes/database.php';
requireLogin();

// Check if printing a single product or the filtered list
$productId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId > 0) {
    // ══════════════════════════════════════════════════════════════
    // MODE 1: SINGLE FINISHED GOOD ITEM SLIP
    // ══════════════════════════════════════════════════════════════
    $product = getRow("SELECT * FROM finished_goods WHERE id = ?", 'i', [$productId]);
    if (!$product) {
        setFlash('Product not found!', 'danger');
        header('Location: finished_goods.php');
        exit;
    }

    $stock = (float)$product['current_stock'];
    $costPrice = (float)$product['cost_price'];
    $sellingPrice = (float)$product['selling_price'];
    $costValue = $stock * $costPrice;
    $sellingValue = $stock * $sellingPrice;
    $margin = $costPrice > 0 ? (($sellingPrice - $costPrice) / $costPrice) * 100 : 0;
    $profitPerUnit = $sellingPrice - $costPrice;

    // Recent Receiving History (last 5)
    $receipts = getRows("SELECT fgri.*, fgr.receipt_no, fgr.receipt_date, fgr.notes 
        FROM finished_goods_receipt_items fgri 
        INNER JOIN finished_goods_receipts fgr ON fgr.id = fgri.receipt_id 
        WHERE fgri.finished_good_id = ? 
        ORDER BY fgr.receipt_date DESC, fgr.id DESC LIMIT 5", 'i', [$productId]);

    // Recent Sales History (last 5)
    $sales = getRows("SELECT si.*, s.sale_no, s.sale_date, COALESCE(c.customer_name, s.walkin_name, 'Cash Customer') AS customer_name 
        FROM sale_items si 
        INNER JOIN sales s ON s.id = si.sale_id 
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE si.product_id = ? AND (si.product_type = 'finished_good' OR si.product_type IS NULL) 
        ORDER BY s.sale_date DESC, s.id DESC LIMIT 5", 'i', [$productId]);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Slip - <?php echo htmlspecialchars($product['product_code']); ?></title>
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
        <a href="finished_goods.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        <span class="text-white fw-bold me-2"><i class="fas fa-box me-1"></i>Finished Good Item Slip</span>
        <button class="btn btn-light btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
        <button class="btn btn-success btn-sm" onclick="downloadPDF()"><i class="fas fa-download me-1"></i>Download PDF</button>
    </div>

    <div class="print-wrapper">
    <div class="print-a4" id="printContent">
        <?php include '../includes/print_branding.php'; ?>
        
        <div class="inv-title">FINISHED GOOD ITEM SPECIFICATION &amp; STOCK SLIP</div>

        <div class="summary-box">
            <div class="summary-item">
                <div class="label">Current Stock</div>
                <div class="amount"><?php echo number_format($stock, 2); ?> <small style="font-size:12px;color:#666;"><?php echo htmlspecialchars($product['unit']); ?></small></div>
            </div>
            <div class="summary-item danger">
                <div class="label">Cost Value</div>
                <div class="amount">Rs. <?php echo formatCurrency($costValue); ?></div>
            </div>
            <div class="summary-item success">
                <div class="label">Selling Value</div>
                <div class="amount">Rs. <?php echo formatCurrency($sellingValue); ?></div>
            </div>
            <div class="summary-item info">
                <div class="label">Profit Margin</div>
                <div class="amount"><?php echo number_format($margin, 1); ?>%</div>
            </div>
        </div>

        <table class="spec-table">
            <tr>
                <th>Product Code</th>
                <td><strong class="text-primary" style="font-size:14px;"><?php echo htmlspecialchars($product['product_code']); ?></strong></td>
                <th>Status</th>
                <td>
                    <span class="badge bg-<?php echo ($product['status'] ?? 'active') === 'active' ? 'success' : 'secondary'; ?>">
                        <?php echo ucfirst($product['status'] ?? 'active'); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Product Name</th>
                <td colspan="3"><strong style="font-size:15px;"><?php echo htmlspecialchars($product['product_name']); ?></strong></td>
            </tr>
            <tr>
                <th>Category</th>
                <td><?php echo htmlspecialchars($product['category'] ?: '-'); ?></td>
                <th>Unit</th>
                <td><?php echo htmlspecialchars($product['unit'] ?: 'units'); ?></td>
            </tr>
            <tr>
                <th>Current Stock</th>
                <td><strong style="font-size:14px;"><?php echo number_format($stock, 2); ?> <?php echo htmlspecialchars($product['unit']); ?></strong></td>
                <th>Minimum Stock</th>
                <td><?php echo number_format((float)$product['minimum_stock'], 2); ?> <?php echo htmlspecialchars($product['unit']); ?></td>
            </tr>
            <tr>
                <th>Cost Price (Rate)</th>
                <td class="text-danger fw-bold">Rs. <?php echo formatCurrency($costPrice); ?></td>
                <th>Selling Price (Rate)</th>
                <td class="text-success fw-bold">Rs. <?php echo formatCurrency($sellingPrice); ?></td>
            </tr>
            <tr>
                <th>Total Stock Cost</th>
                <td class="text-danger fw-bold">Rs. <?php echo formatCurrency($costValue); ?></td>
                <th>Total Stock Value (Sale)</th>
                <td class="text-success fw-bold">Rs. <?php echo formatCurrency($sellingValue); ?></td>
            </tr>
            <tr>
                <th>Profit Per Unit</th>
                <td class="<?php echo $profitPerUnit >= 0 ? 'text-success' : 'text-danger'; ?> fw-bold">
                    Rs. <?php echo formatCurrency($profitPerUnit); ?>
                </td>
                <th>Date Added / Modified</th>
                <td><?php echo !empty($product['date_time']) ? date('d-m-Y h:i A', strtotime($product['date_time'])) : '-'; ?></td>
            </tr>
        </table>

        <?php if (!empty($receipts)): ?>
            <div style="font-size:13px; font-weight:700; color:#1a2332; margin-bottom:6px;">
                <i class="fas fa-sign-in-alt me-1 text-primary"></i>Recent Goods Receiving Records
            </div>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Receipt No</th>
                        <th>Date</th>
                        <th style="text-align:right">Qty</th>
                        <th style="text-align:right">Rate (Rs)</th>
                        <th style="text-align:right">Total (Rs)</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receipts as $rc): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($rc['receipt_no']); ?></strong></td>
                            <td><?php echo formatDate($rc['receipt_date']); ?></td>
                            <td style="text-align:right" class="fw-bold text-primary"><?php echo number_format((float)$rc['quantity'], 2); ?></td>
                            <td style="text-align:right"><?php echo formatCurrency($rc['unit_price']); ?></td>
                            <td style="text-align:right" class="fw-bold text-success"><?php echo formatCurrency($rc['total']); ?></td>
                            <td><?php echo htmlspecialchars($rc['notes'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($sales)): ?>
            <div style="font-size:13px; font-weight:700; color:#1a2332; margin-bottom:6px; margin-top:15px;">
                <i class="fas fa-shopping-cart me-1 text-success"></i>Recent Sales Records
            </div>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th style="text-align:right">Qty</th>
                        <th style="text-align:right">Rate (Rs)</th>
                        <th style="text-align:right">Total (Rs)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $sl): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($sl['sale_no']); ?></strong></td>
                            <td><?php echo formatDate($sl['sale_date']); ?></td>
                            <td><?php echo htmlspecialchars($sl['customer_name'] ?: 'Cash Customer'); ?></td>
                            <td style="text-align:right" class="fw-bold text-primary"><?php echo number_format((float)$sl['quantity'], 2); ?></td>
                            <td style="text-align:right"><?php echo formatCurrency($sl['unit_price']); ?></td>
                            <td style="text-align:right" class="fw-bold text-success"><?php echo formatCurrency($sl['total']); ?></td>
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
            filename: 'product_<?php echo htmlspecialchars($product['product_code']); ?>_<?php echo date('Y-m-d'); ?>.pdf', 
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
// MODE 2: FILTERED FINISHED GOODS STOCK & VALUATION REPORT
// ══════════════════════════════════════════════════════════════
$fromDate = isset($_GET['from_date']) && $_GET['from_date'] !== '' ? trim($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) && $_GET['to_date'] !== '' ? trim($_GET['to_date']) : '';

$where = [];
$params = [];
$types = '';

if ($fromDate !== '') {
    $where[] = "DATE(date_time) >= ?";
    $params[] = $fromDate;
    $types .= 's';
}
if ($toDate !== '') {
    $where[] = "DATE(date_time) <= ?";
    $params[] = $toDate;
    $types .= 's';
}

$whereSql = !empty($where) ? " WHERE " . implode(' AND ', $where) : "";
$sql = "SELECT * FROM finished_goods" . $whereSql . " ORDER BY id DESC";
$products = !empty($params) ? getRows($sql, $types, $params) : getRows($sql);

$totalCount = count($products);
$totalStockQty = 0;
$totalCostValue = 0;
$totalSellingValue = 0;

foreach ($products as $p) {
    $stock = (float)$p['current_stock'];
    $cost = (float)$p['cost_price'];
    $sell = (float)$p['selling_price'];
    $totalStockQty += $stock;
    $totalCostValue += ($stock * $cost);
    $totalSellingValue += ($stock * $sell);
}
$potentialProfit = $totalSellingValue - $totalCostValue;

// Filtered received stats
if ($fromDate !== '' && $toDate !== '') {
    $recRow = getRow("SELECT COALESCE(SUM(quantity), 0) AS qty, COALESCE(SUM(total), 0) AS val 
        FROM finished_goods_receipt_items fgri 
        INNER JOIN finished_goods_receipts fgr ON fgr.id = fgri.receipt_id 
        WHERE fgr.receipt_date BETWEEN ? AND ?", 'ss', [$fromDate, $toDate]);
} elseif ($fromDate !== '') {
    $recRow = getRow("SELECT COALESCE(SUM(quantity), 0) AS qty, COALESCE(SUM(total), 0) AS val 
        FROM finished_goods_receipt_items fgri 
        INNER JOIN finished_goods_receipts fgr ON fgr.id = fgri.receipt_id 
        WHERE fgr.receipt_date >= ?", 's', [$fromDate]);
} elseif ($toDate !== '') {
    $recRow = getRow("SELECT COALESCE(SUM(quantity), 0) AS qty, COALESCE(SUM(total), 0) AS val 
        FROM finished_goods_receipt_items fgri 
        INNER JOIN finished_goods_receipts fgr ON fgr.id = fgri.receipt_id 
        WHERE fgr.receipt_date <= ?", 's', [$toDate]);
} else {
    $recRow = getRow("SELECT COALESCE(SUM(quantity), 0) AS qty, COALESCE(SUM(total), 0) AS val 
        FROM finished_goods_receipt_items");
}
$receivedQty = (float)($recRow['qty'] ?? 0);
$receivedVal = (float)($recRow['val'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Finished Goods Report - Print</title>
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
.inv-meta { display: flex; justify-content: space-between; margin-bottom: 18px; font-size: 12px; }
.inv-meta strong { color: #1a2332; }
.summary-box { display: flex; gap: 12px; margin-bottom: 22px; flex-wrap: wrap; }
.summary-item { flex: 1; min-width: 170px; background: #f8f9fa; border-left: 4px solid #1a2332; padding: 10px 14px; border-radius: 0 6px 6px 0; }
.summary-item .label { font-size: 11px; color: #666; text-transform: uppercase; font-weight: 600; }
.summary-item .amount { font-size: 17px; font-weight: 700; color: #1a2332; margin-top: 3px; }
.summary-item.secondary { border-left-color: #6c757d; }
.summary-item.danger { border-left-color: #dc3545; } .summary-item.danger .amount { color: #dc3545; }
.summary-item.success { border-left-color: #198754; } .summary-item.success .amount { color: #198754; }
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
    <a href="finished_goods.php<?php echo ($fromDate || $toDate) ? '?from_date=' . urlencode($fromDate) . '&to_date=' . urlencode($toDate) : ''; ?>" class="btn btn-outline-light btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Back
    </a>
    <span class="text-white fw-bold me-2"><i class="fas fa-boxes me-1"></i>Finished Goods Stock Report</span>
    <button class="btn btn-light btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
    <button class="btn btn-success btn-sm" onclick="downloadPDF()"><i class="fas fa-download me-1"></i>Download PDF</button>
</div>

<div class="print-wrapper">
<div class="print-a4" id="printContent">
    <?php include '../includes/print_branding.php'; ?>

    <div class="inv-title">FINISHED GOODS STOCK &amp; VALUATION REPORT</div>
    <div class="inv-subtitle">
        <?php if ($fromDate !== '' && $toDate !== ''): ?>
            <strong>Filter Period:</strong> <?php echo formatDate($fromDate); ?> &mdash; <?php echo formatDate($toDate); ?>
        <?php elseif ($fromDate !== ''): ?>
            <strong>Filter Period:</strong> From <?php echo formatDate($fromDate); ?> onwards
        <?php elseif ($toDate !== ''): ?>
            <strong>Filter Period:</strong> Up to <?php echo formatDate($toDate); ?>
        <?php else: ?>
            <strong>Scope:</strong> Complete Inventory &mdash; All Records
        <?php endif; ?>
    </div>

    <div class="summary-box">
        <div class="summary-item secondary">
            <div class="label">Total Stock Qty</div>
            <div class="amount"><?php echo number_format($totalStockQty, 2); ?></div>
        </div>
        <div class="summary-item danger">
            <div class="label">Stock Cost Value</div>
            <div class="amount">Rs. <?php echo formatCurrency($totalCostValue); ?></div>
        </div>
        <div class="summary-item success">
            <div class="label">Stock Sale Value</div>
            <div class="amount">Rs. <?php echo formatCurrency($totalSellingValue); ?></div>
        </div>
        <div class="summary-item info">
            <div class="label">Total Received Value</div>
            <div class="amount">Rs. <?php echo formatCurrency($receivedVal); ?></div>
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
                <th>Product Name</th>
                <th>Category</th>
                <th>Unit</th>
                <th>Date Added</th>
                <th style="text-align:right">Stock</th>
                <th style="text-align:right">Cost (Rs)</th>
                <th style="text-align:right">Sale (Rs)</th>
                <th style="text-align:right">Cost Val (Rs)</th>
                <th style="text-align:right">Sale Val (Rs)</th>
                <th style="text-align:center; width: 65px;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($products) > 0): ?>
                <?php $counter = 1; foreach ($products as $pr): 
                    $cVal = (float)$pr['current_stock'] * (float)$pr['cost_price'];
                    $sVal = (float)$pr['current_stock'] * (float)$pr['selling_price'];
                ?>
                <tr>
                    <td><?php echo $counter++; ?></td>
                    <td><strong><?php echo htmlspecialchars($pr['product_code']); ?></strong></td>
                    <td><strong><?php echo htmlspecialchars($pr['product_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($pr['category'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($pr['unit'] ?: 'units'); ?></td>
                    <td><?php echo !empty($pr['date_time']) ? date('d-m-Y', strtotime($pr['date_time'])) : '-'; ?></td>
                    <td style="text-align:right" class="fw-bold text-primary"><?php echo number_format((float)$pr['current_stock'], 2); ?></td>
                    <td style="text-align:right"><?php echo formatCurrency($pr['cost_price']); ?></td>
                    <td style="text-align:right"><?php echo formatCurrency($pr['selling_price']); ?></td>
                    <td style="text-align:right; font-weight:700;" class="text-danger"><?php echo formatCurrency($cVal); ?></td>
                    <td style="text-align:right; font-weight:700;" class="text-success"><?php echo formatCurrency($sVal); ?></td>
                    <td style="text-align:center;">
                        <span class="badge bg-<?php echo ($pr['status'] ?? 'active') === 'active' ? 'success' : 'secondary'; ?>" style="font-size:10px;">
                            <?php echo ucfirst($pr['status'] ?? 'active'); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="12" style="text-align:center; color:#999; padding:30px;">
                        <i class="fas fa-info-circle me-1"></i>No finished goods found for the selected date range
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
                <th style="text-align:right" class="text-danger">Rs. <?php echo formatCurrency($totalCostValue); ?></th>
                <th style="text-align:right" class="text-success">Rs. <?php echo formatCurrency($totalSellingValue); ?></th>
                <th></th>
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
        filename: 'finished_goods_report_<?php echo date('Y-m-d'); ?>.pdf', 
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
