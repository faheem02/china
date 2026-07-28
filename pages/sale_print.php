<?php
require_once '../includes/database.php';
requireLogin();

$saleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($saleId <= 0) { header('Location: sales.php'); exit; }

$sale = getRow("SELECT s.*, c.customer_name, c.company_name, c.phone as cust_phone, c.address as cust_address
    FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.id = ?", 'i', [$saleId]);
if (!$sale) { header('Location: sales.php'); exit; }

$items = getRows("SELECT si.*, fg.product_code, fg.product_name, fg.unit
    FROM sale_items si LEFT JOIN finished_goods fg ON si.product_id = fg.id
    WHERE si.sale_id = ?", 'i', [$saleId]);

// ── FIXED: Properly handle customer name with null checking ──
$customerName = '';
$customerPhone = '';

// Check if customer_id exists and is not null/empty
if (isset($sale['customer_id']) && $sale['customer_id'] > 0) {
    // Customer exists - use customer name from the joined table
    $customerName = $sale['customer_name'] ?? '';
    $customerPhone = $sale['cust_phone'] ?? '';
} else {
    // Walk-in customer - use walkin fields if they exist
    $customerName = $sale['walkin_name'] ?? '';
    $customerPhone = $sale['walkin_phone'] ?? '';
}

// If still empty, try customer_name directly
if (empty($customerName) && isset($sale['customer_name'])) {
    $customerName = $sale['customer_name'];
}

// If still empty, set to 'Walk-in'
if (empty($customerName)) {
    $customerName = 'Walk-in Customer';
}

$company = getRow("SELECT * FROM company_settings LIMIT 1");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sale Invoice - <?php echo isset($sale['sale_no']) ? htmlspecialchars($sale['sale_no']) : ''; ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
* { font-family: 'Poppins', sans-serif; }
body { background: #f0f2f5; }

/* ─── Action buttons (hidden on print) ─── */
.action-buttons { position: fixed; top: 0; left: 0; right: 0; z-index: 9999; background: #1a2332; padding: 10px 20px; display: flex; gap: 10px; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
.action-buttons .btn { font-size: 14px; }
.bill-wrapper { margin-top: 70px; display: flex; justify-content: center; padding: 20px; }

/* ─── A4 Invoice ─── */
.invoice-a4 { max-width: 800px; width: 100%; background: #fff; padding: 40px; border: 1px solid #ddd; }
.invoice-a4 .inv-header { text-align: center; border-bottom: 3px solid #1a2332; padding-bottom: 15px; margin-bottom: 20px; }
.invoice-a4 .inv-header h3 { margin: 0; color: #1a2332; font-weight: 700; }
.invoice-a4 .inv-header p { margin: 2px 0 0; color: #666; font-size: 13px; }
.invoice-a4 .inv-meta { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 13px; }
.invoice-a4 .inv-meta div { }
.invoice-a4 .inv-meta strong { color: #1a2332; }
.invoice-a4 table { width: 100%; font-size: 13px; }
.invoice-a4 table thead th { background: #1a2332; color: #fff; padding: 8px 10px; font-weight: 600; }
.invoice-a4 table tbody td { padding: 7px 10px; border-bottom: 1px solid #eee; }
.invoice-a4 table tfoot td { padding: 8px 10px; font-weight: 600; }
.invoice-a4 .inv-totals { margin-top: 15px; }
.invoice-a4 .inv-totals table { width: 300px; margin-left: auto; }
.invoice-a4 .inv-totals table td { padding: 5px 10px; border-bottom: 1px solid #eee; }
.invoice-a4 .inv-totals table td:last-child { text-align: right; }
.invoice-a4 .inv-footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 2px solid #1a2332; font-size: 12px; color: #666; }

/* ─── Thermal Invoice ─── */
.invoice-thermal { display: none; width: 300px; background: #fff; padding: 15px; border: 1px solid #ddd; font-size: 12px; }
.invoice-thermal .th-header { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 8px; margin-bottom: 8px; }
.invoice-thermal .th-header h5 { margin: 0; font-size: 14px; font-weight: 700; }
.invoice-thermal .th-header p { margin: 1px 0 0; font-size: 11px; }
.invoice-thermal table { width: 100%; font-size: 11px; }
.invoice-thermal table th { text-align: left; padding: 3px 0; border-bottom: 1px dashed #000; font-weight: 700; }
.invoice-thermal table td { padding: 3px 0; }
.invoice-thermal .th-line { border-bottom: 1px dashed #000; margin: 5px 0; }
.invoice-thermal .th-total { font-weight: 700; font-size: 13px; }
.invoice-thermal .th-footer { text-align: center; margin-top: 10px; padding-top: 8px; border-top: 1px dashed #000; font-size: 10px; }

/* ─── Print styles ─── */
@media print {
    body { background: #fff !important; margin: 0; padding: 0; }
    .action-buttons { display: none !important; }
    .bill-wrapper { margin: 0; padding: 0; }
    .invoice-a4 { border: none; padding: 20px; max-width: 100%; }
    .invoice-thermal { border: none; padding: 10px; }
}
@media print and (orientation: portrait) {
    .bill-wrapper { display: block; }
    .invoice-thermal { display: block !important; }
    .invoice-a4 { display: none !important; }
}
@media print and (orientation: landscape) {
    .bill-wrapper { display: block; }
    .invoice-a4 { display: block !important; }
    .invoice-thermal { display: none !important; }
}
</style>
</head>
<body>

<!-- Action Buttons -->
<div class="action-buttons">
    <a href="sales.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <span class="text-white fw-bold me-2"><i class="fas fa-receipt me-1"></i><?php echo isset($sale['sale_no']) ? htmlspecialchars($sale['sale_no']) : ''; ?></span>
    <button class="btn btn-light btn-sm" onclick="printA4()"><i class="fas fa-file-pdf me-1"></i>Print A4</button>
    <button class="btn btn-light btn-sm" onclick="printThermal()"><i class="fas fa-print me-1"></i>Print Thermal</button>
    <button class="btn btn-success btn-sm" onclick="downloadPDF()"><i class="fas fa-download me-1"></i>Download PDF</button>
    <a href="new_sale.php" class="btn btn-warning btn-sm ms-auto"><i class="fas fa-times me-1"></i>Exit</a>
    <a href="sales.php" class="btn btn-outline-light btn-sm"><i class="fas fa-list me-1"></i>All Sales</a>
</div>

<div class="bill-wrapper">
<!-- ═══ A4 Invoice ═══ -->
<div class="invoice-a4" id="invoiceA4">
    <div class="inv-header">
        <h3>SALE INVOICE</h3>
        <p><?php echo htmlspecialchars($company['company_name'] ?? 'ERP System'); ?></p>
        <?php if (isset($company['address']) && !empty($company['address'])): ?><p style="margin:2px 0 0;font-size:12px;"><?php echo htmlspecialchars($company['address']); ?></p><?php endif; ?>
        <?php if (isset($company['phone']) && !empty($company['phone'])): ?><p style="margin:2px 0 0;font-size:12px;">Ph: <?php echo htmlspecialchars($company['phone']); ?></p><?php endif; ?>
    </div>
    <div class="inv-meta">
        <div>
            <strong>Invoice #:</strong> <?php echo isset($sale['sale_no']) ? htmlspecialchars($sale['sale_no']) : ''; ?><br>
            <strong>Date:</strong> <?php echo isset($sale['sale_date']) ? date('d-m-Y', strtotime($sale['sale_date'])) : ''; ?><br>
            <strong>Payment:</strong> <?php echo isset($sale['payment_method']) ? ucfirst($sale['payment_method']) : 'N/A'; ?>
        </div>
        <div style="text-align:right">
            <strong>Customer:</strong> <?php echo htmlspecialchars($customerName); ?><br>
            <?php if (!empty($customerPhone)): ?><strong>Phone:</strong> <?php echo htmlspecialchars($customerPhone); ?><br><?php endif; ?>
            <strong>Status:</strong> <span class="badge bg-<?php echo (isset($sale['payment_status']) && $sale['payment_status'] === 'paid') ? 'success' : 'warning'; ?>"><?php echo isset($sale['payment_status']) ? ucfirst($sale['payment_status']) : 'N/A'; ?></span>
        </div>
    </div>
    <table>
        <thead>
            <tr><th>#</th><th>Product Code</th><th>Product Name</th><th style="text-align:center">Qty</th><th style="text-align:right">Unit Price</th><th style="text-align:right">Total</th></tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($items as $item): ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo htmlspecialchars($item['product_code'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($item['product_name'] ?? ''); ?></td>
                <td style="text-align:center"><?php echo number_format($item['quantity'] ?? 0, 2); ?> <?php echo $item['unit'] ?? ''; ?></td>
                <td style="text-align:right"><?php echo number_format($item['unit_price'] ?? 0, 2); ?></td>
                <td style="text-align:right"><?php echo number_format($item['total'] ?? 0, 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="inv-totals">
        <table>
            <tr><td>Subtotal:</td><td><?php echo number_format($sale['total_amount'] ?? 0, 2); ?></td></tr>
            <?php if (isset($sale['discount']) && $sale['discount'] > 0): ?>
            <tr><td>Discount:</td><td>-<?php echo number_format($sale['discount'], 2); ?></td></tr>
            <?php endif; ?>
            <?php if (isset($sale['total_refund']) && $sale['total_refund'] > 0): ?>
            <tr><td>Refund:</td><td style="color:#dc3545">-<?php echo number_format($sale['total_refund'], 2); ?></td></tr>
            <?php endif; ?>
            <tr><td><strong>Grand Total:</strong></td><td><strong><?php echo number_format($sale['final_amount'] ?? $sale['net_amount'] ?? 0, 2); ?></strong></td></tr>
            <tr><td>Paid:</td><td><?php echo number_format($sale['paid_amount'] ?? 0, 2); ?></td></tr>
            <?php if (isset($sale['balance']) && $sale['balance'] > 0): ?>
            <tr style="color:#dc3545"><td><strong>Balance:</strong></td><td><strong><?php echo number_format($sale['balance'], 2); ?></strong></td></tr>
            <?php endif; ?>
        </table>
    </div>
    <div class="inv-footer">
        <p>Thank you for your business!</p>
    </div>
</div>

<!-- ═══ Thermal Invoice ═══ -->
<div class="invoice-thermal" id="invoiceThermal">
    <div class="th-header">
        <h5>SALE INVOICE</h5>
        <p><?php echo htmlspecialchars($company['company_name'] ?? 'ERP System'); ?></p>
        <p><?php echo isset($sale['sale_no']) ? htmlspecialchars($sale['sale_no']) : ''; ?></p>
    </div>
    <table>
        <tr><td><strong>Date:</strong></td><td><?php echo isset($sale['sale_date']) ? date('d-m-Y', strtotime($sale['sale_date'])) : ''; ?></td></tr>
        <tr><td><strong>Customer:</strong></td><td><?php echo htmlspecialchars($customerName); ?></td></tr>
        <?php if (!empty($customerPhone)): ?>
        <tr><td><strong>Phone:</strong></td><td><?php echo htmlspecialchars($customerPhone); ?></td></tr>
        <?php endif; ?>
        <tr><td><strong>Payment:</strong></td><td><?php echo isset($sale['payment_method']) ? ucfirst($sale['payment_method']) : 'N/A'; ?></td></tr>
    </table>
    <div class="th-line"></div>
    <table>
        <thead>
            <tr><th>Item</th><th style="text-align:right">Qty</th><th style="text-align:right">Price</th><th style="text-align:right">Total</th></tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['product_name'] ?? ''); ?></td>
                <td style="text-align:right"><?php echo number_format($item['quantity'] ?? 0, 2); ?></td>
                <td style="text-align:right"><?php echo number_format($item['unit_price'] ?? 0, 2); ?></td>
                <td style="text-align:right"><?php echo number_format($item['total'] ?? 0, 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="th-line"></div>
    <table>
        <tr><td>Subtotal:</td><td style="text-align:right"><?php echo number_format($sale['total_amount'] ?? 0, 2); ?></td></tr>
        <?php if (isset($sale['discount']) && $sale['discount'] > 0): ?>
        <tr><td>Discount:</td><td style="text-align:right">-<?php echo number_format($sale['discount'], 2); ?></td></tr>
        <?php endif; ?>
        <?php if (isset($sale['total_refund']) && $sale['total_refund'] > 0): ?>
        <tr><td>Refund:</td><td style="text-align:right;color:red">-<?php echo number_format($sale['total_refund'], 2); ?></td></tr>
        <?php endif; ?>
        <tr><td class="th-total">Grand Total:</td><td class="th-total" style="text-align:right"><?php echo number_format($sale['final_amount'] ?? $sale['net_amount'] ?? 0, 2); ?></td></tr>
        <tr><td>Paid:</td><td style="text-align:right"><?php echo number_format($sale['paid_amount'] ?? 0, 2); ?></td></tr>
        <?php if (isset($sale['balance']) && $sale['balance'] > 0): ?>
        <tr><td><strong>Balance Due:</strong></td><td style="text-align:right;color:red"><strong><?php echo number_format($sale['balance'], 2); ?></strong></td></tr>
        <?php endif; ?>
    </table>
    <div class="th-footer">
        <p>Thank you!</p>
    </div>
</div>
</div>

<script>
function printA4() {
    document.getElementById('invoiceA4').style.display = 'block';
    document.getElementById('invoiceThermal').style.display = 'none';
    window.print();
}
function printThermal() {
    document.getElementById('invoiceA4').style.display = 'none';
    document.getElementById('invoiceThermal').style.display = 'block';
    window.print();
}
function downloadPDF() {
    document.getElementById('invoiceA4').style.display = 'block';
    document.getElementById('invoiceThermal').style.display = 'none';
    var opt = {
        margin: 0.5,
        filename: '<?php echo isset($sale["sale_no"]) ? $sale["sale_no"] : 'invoice'; ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
    };
    if (typeof html2pdf !== 'undefined') {
        html2pdf().set(opt).from(document.getElementById('invoiceA4')).save();
    } else {
        var script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
        script.onload = function() { html2pdf().set(opt).from(document.getElementById('invoiceA4')).save(); };
        document.head.appendChild(script);
    }
}
</script>
</body>
</html>