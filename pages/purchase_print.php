<?php
require_once '../includes/database.php';
requireLogin();

$purchaseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($purchaseId <= 0) { header('Location: local_purchases.php'); exit; }

$purchase = getRow("SELECT p.*, s.supplier_name, s.company_name, s.phone as sup_phone, s.address as sup_address
    FROM local_purchases p LEFT JOIN local_suppliers s ON p.supplier_id = s.id WHERE p.id = ?", 'i', [$purchaseId]);
if (!$purchase) { header('Location: local_purchases.php'); exit; }

$items = getRows("SELECT li.*, 
    CASE WHEN li.item_type = 'finished_good' THEN fg.product_code WHEN li.item_type = 'raw_material' THEN rm.material_code ELSE rm.material_code END AS material_code,
    CASE WHEN li.item_type = 'finished_good' THEN fg.product_name WHEN li.item_type = 'raw_material' THEN rm.material_name ELSE rm.material_name END AS material_name,
    CASE WHEN li.item_type = 'finished_good' THEN fg.unit WHEN li.item_type = 'raw_material' THEN rm.unit ELSE rm.unit END AS unit,
    li.item_type
    FROM local_purchase_items li 
    LEFT JOIN raw_materials rm ON li.material_id = rm.id AND li.item_type = 'raw_material'
    LEFT JOIN finished_goods fg ON li.material_id = fg.id AND li.item_type = 'finished_good'
    WHERE li.purchase_id = ?", 'i', [$purchaseId]);

$company = getRow("SELECT * FROM company_settings LIMIT 1");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Purchase Invoice - <?php echo $purchase['purchase_no']; ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
* { font-family: 'Poppins', sans-serif; }
body { background: #f0f2f5; }

.action-buttons { position: fixed; top: 0; left: 0; right: 0; z-index: 9999; background: #1a2332; padding: 10px 20px; display: flex; gap: 10px; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
.action-buttons .btn { font-size: 14px; }
.bill-wrapper { margin-top: 70px; display: flex; justify-content: center; padding: 20px; }

.invoice-a4 { max-width: 800px; width: 100%; background: #fff; padding: 40px; border: 1px solid #ddd; }
.invoice-a4 .inv-header { text-align: center; border-bottom: 3px solid #1a2332; padding-bottom: 15px; margin-bottom: 20px; }
.invoice-a4 .inv-header h3 { margin: 0; color: #1a2332; font-weight: 700; }
.invoice-a4 .inv-header p { margin: 2px 0 0; color: #666; font-size: 13px; }
.invoice-a4 .inv-meta { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 13px; }
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

<div class="action-buttons">
    <a href="local_purchases.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <span class="text-white fw-bold me-2"><i class="fas fa-file-invoice me-1"></i><?php echo $purchase['purchase_no']; ?></span>
    <button class="btn btn-light btn-sm" onclick="printA4()"><i class="fas fa-file-pdf me-1"></i>Print A4</button>
    <button class="btn btn-light btn-sm" onclick="printThermal()"><i class="fas fa-print me-1"></i>Print Thermal</button>
    <a href="add_local_purchase.php?id=<?php echo $purchase['id']; ?>" class="btn btn-warning btn-sm ms-auto"><i class="fas fa-edit me-1"></i>Edit</a>
    <a href="local_purchases.php" class="btn btn-outline-light btn-sm"><i class="fas fa-list me-1"></i>All Purchases</a>
</div>

<div class="bill-wrapper">
<!-- A4 Invoice -->
<div class="invoice-a4" id="invoiceA4">
    <div class="inv-header">
        <h3>PURCHASE INVOICE</h3>
        <p><?php echo htmlspecialchars($company['company_name'] ?? 'ERP System'); ?></p>
        <?php if ($company['address'] ?? null): ?><p style="margin:2px 0 0;font-size:12px;"><?php echo htmlspecialchars($company['address']); ?></p><?php endif; ?>
        <?php if ($company['phone'] ?? null): ?><p style="margin:2px 0 0;font-size:12px;">Ph: <?php echo htmlspecialchars($company['phone']); ?></p><?php endif; ?>
    </div>
    <div class="inv-meta">
        <div>
            <strong>Invoice #:</strong> <?php echo $purchase['purchase_no']; ?><br>
            <strong>Date:</strong> <?php echo date('d-m-Y', strtotime($purchase['purchase_date'])); ?><br>
            <strong>Payment:</strong> <?php echo ucfirst($purchase['payment_method'] ?? 'Credit'); ?>
        </div>
        <div style="text-align:right">
            <strong>Supplier:</strong> <?php echo htmlspecialchars($purchase['supplier_name']); ?><br>
            <?php if ($purchase['company_name']): ?><strong>Company:</strong> <?php echo htmlspecialchars($purchase['company_name']); ?><br><?php endif; ?>
            <?php if ($purchase['sup_phone']): ?><strong>Phone:</strong> <?php echo htmlspecialchars($purchase['sup_phone']); ?><br><?php endif; ?>
            <strong>Status:</strong> <span class="badge bg-<?php echo $purchase['payment_status'] === 'paid' ? 'success' : 'warning'; ?>"><?php echo ucfirst($purchase['payment_status']); ?></span>
        </div>
    </div>
    <table>
        <thead>
            <tr><th>#</th><th>Code</th><th>Material</th><th style="text-align:center">Qty</th><th style="text-align:right">Unit Price</th><th style="text-align:right">Total</th></tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($items as $item): ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo htmlspecialchars($item['material_code']); ?></td>
                <td><?php echo htmlspecialchars($item['material_name']); ?></td>
                <td style="text-align:center"><?php echo number_format($item['quantity'], 2); ?> <?php echo $item['unit']; ?></td>
                <td style="text-align:right"><?php echo number_format($item['unit_price'], 2); ?></td>
                <td style="text-align:right"><?php echo number_format($item['total'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="inv-totals">
        <table>
            <tr><td>Subtotal:</td><td><?php echo number_format($purchase['total_amount'], 2); ?></td></tr>
            <tr><td><strong>Grand Total:</strong></td><td><strong><?php echo number_format($purchase['total_amount'], 2); ?></strong></td></tr>
            <tr><td>Paid:</td><td><?php echo number_format($purchase['paid_amount'], 2); ?></td></tr>
            <?php if ($purchase['balance'] > 0): ?>
            <tr style="color:#dc3545"><td><strong>Balance:</strong></td><td><strong><?php echo number_format($purchase['balance'], 2); ?></strong></td></tr>
            <?php endif; ?>
        </table>
    </div>
    <div class="inv-footer">
        <p>Thank you for your business!</p>
    </div>
</div>

<!-- Thermal Invoice -->
<div class="invoice-thermal" id="invoiceThermal">
    <div class="th-header">
        <h5>PURCHASE INVOICE</h5>
        <p><?php echo htmlspecialchars($company['company_name'] ?? 'ERP System'); ?></p>
        <p><?php echo $purchase['purchase_no']; ?></p>
    </div>
    <table>
        <tr><td><strong>Date:</strong></td><td><?php echo date('d-m-Y', strtotime($purchase['purchase_date'])); ?></td></tr>
        <tr><td><strong>Supplier:</strong></td><td><?php echo htmlspecialchars($purchase['supplier_name']); ?></td></tr>
        <?php if ($purchase['sup_phone']): ?>
        <tr><td><strong>Phone:</strong></td><td><?php echo htmlspecialchars($purchase['sup_phone']); ?></td></tr>
        <?php endif; ?>
        <tr><td><strong>Payment:</strong></td><td><?php echo ucfirst($purchase['payment_method'] ?? 'Credit'); ?></td></tr>
    </table>
    <div class="th-line"></div>
    <table>
        <thead>
            <tr><th>Item</th><th style="text-align:right">Qty</th><th style="text-align:right">Price</th><th style="text-align:right">Total</th></tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['material_name']); ?></td>
                <td style="text-align:right"><?php echo number_format($item['quantity'], 2); ?></td>
                <td style="text-align:right"><?php echo number_format($item['unit_price'], 2); ?></td>
                <td style="text-align:right"><?php echo number_format($item['total'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="th-line"></div>
    <table>
        <tr><td>Subtotal:</td><td style="text-align:right"><?php echo number_format($purchase['total_amount'], 2); ?></td></tr>
        <tr><td class="th-total">Grand Total:</td><td class="th-total" style="text-align:right"><?php echo number_format($purchase['total_amount'], 2); ?></td></tr>
        <tr><td>Paid:</td><td style="text-align:right"><?php echo number_format($purchase['paid_amount'], 2); ?></td></tr>
        <?php if ($purchase['balance'] > 0): ?>
        <tr><td><strong>Balance Due:</strong></td><td style="text-align:right;color:red"><strong><?php echo number_format($purchase['balance'], 2); ?></strong></td></tr>
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
</script>
</body>
</html>
