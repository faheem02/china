<?php
require_once '../includes/database.php';
requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: import_purchases.php'); exit; }

$purchase = getRow("SELECT p.*, s.supplier_name, s.company_name, s.contact_person, s.phone, s.email, s.address, s.city, s.country
    FROM import_purchases p
    LEFT JOIN chinese_suppliers s ON p.supplier_id = s.id
    WHERE p.id = ?", 'i', [$id]);
if (!$purchase) { header('Location: import_purchases.php'); exit; }

$items = getRows("SELECT i.*,
    CASE WHEN i.item_type = 'finished_good' THEN fg.product_code ELSE rm.material_code END AS material_code,
    CASE WHEN i.item_type = 'finished_good' THEN fg.product_name ELSE rm.material_name END AS material_name,
    CASE WHEN i.item_type = 'finished_good' THEN fg.unit ELSE rm.unit END AS unit
    FROM import_purchase_items i
    LEFT JOIN raw_materials rm ON i.material_id = rm.id AND i.item_type = 'raw_material'
    LEFT JOIN finished_goods fg ON i.material_id = fg.id AND i.item_type = 'finished_good'
    WHERE i.purchase_id = ?
    ORDER BY i.id", 'i', [$id]);

$company = getRow("SELECT * FROM company_settings LIMIT 1");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Import Purchase Invoice - <?php echo $purchase['purchase_no']; ?></title>
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
.invoice-a4 .inv-totals { margin-top: 15px; }
.invoice-a4 .inv-totals table { width: 300px; margin-left: auto; }
.invoice-a4 .inv-totals table td { padding: 5px 10px; border-bottom: 1px solid #eee; }
.invoice-a4 .inv-totals table td:last-child { text-align: right; }
.invoice-a4 .inv-footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 2px solid #1a2332; font-size: 12px; color: #666; }
.empty-state { text-align: center; padding: 40px; color: #999; }

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
    <a href="import_purchases.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <span class="text-white fw-bold me-2"><i class="fas fa-file-invoice me-1"></i><?php echo $purchase['purchase_no']; ?></span>
    <button class="btn btn-light btn-sm" onclick="printA4()"><i class="fas fa-file-pdf me-1"></i>Print A4</button>
    <button class="btn btn-light btn-sm" onclick="printThermal()"><i class="fas fa-print me-1"></i>Print Thermal</button>
    <a href="add_import_purchase.php?edit=<?php echo $purchase['id']; ?>" class="btn btn-warning btn-sm ms-auto"><i class="fas fa-edit me-1"></i>Edit</a>
    <a href="import_purchases.php" class="btn btn-outline-light btn-sm"><i class="fas fa-list me-1"></i>All Purchases</a>
</div>

<div class="bill-wrapper">

<!-- A4 Invoice -->
<div class="invoice-a4" id="invoiceA4">
    <?php include '../includes/print_branding.php'; ?>

    <div class="inv-meta">
        <div>
            <strong>Purchase No:</strong> <?php echo htmlspecialchars($purchase['purchase_no']); ?><br>
            <strong>Date:</strong> <?php echo formatDate($purchase['purchase_date']); ?><br>
            <strong>Invoice #:</strong> <?php echo htmlspecialchars($purchase['invoice_no'] ?? '-'); ?><br>
            <strong>Exchange Rate:</strong> 1 CNY = <?php echo number_format($purchase['exchange_rate'], 2); ?> PKR
        </div>
        <div style="text-align:right">
            <strong>Supplier:</strong> <?php echo htmlspecialchars($purchase['supplier_name']); ?><br>
            <?php if ($purchase['company_name']): ?><strong>Company:</strong> <?php echo htmlspecialchars($purchase['company_name']); ?><br><?php endif; ?>
            <?php if ($purchase['contact_person']): ?><strong>Contact:</strong> <?php echo htmlspecialchars($purchase['contact_person']); ?><br><?php endif; ?>
            <?php if ($purchase['phone']): ?><strong>Phone:</strong> <?php echo htmlspecialchars($purchase['phone']); ?><br><?php endif; ?>
            <?php if ($purchase['email']): ?><strong>Email:</strong> <?php echo htmlspecialchars($purchase['email']); ?><br><?php endif; ?>
            <?php if ($purchase['city']): ?><strong>City:</strong> <?php echo htmlspecialchars($purchase['city']); ?>, <?php endif; ?>
            <?php echo htmlspecialchars($purchase['country'] ?? 'China'); ?>
        </div>
    </div>

    <?php if (count($items) > 0): ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Code</th>
                <th>Material</th>
                <th>Unit</th>
                <th style="text-align:right">Qty</th>
                <th style="text-align:right">Price (CNY)</th>
                <th style="text-align:right">Price (PKR)</th>
                <th style="text-align:right">Total (CNY)</th>
                <th style="text-align:right">Total (PKR)</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($items as $item): ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo htmlspecialchars($item['material_code'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($item['material_name'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($item['unit'] ?? '-'); ?></td>
                <td style="text-align:right"><?php echo number_format($item['quantity'], 2); ?></td>
                <td style="text-align:right"><?php echo number_format($item['unit_price_cny'], 2); ?></td>
                <td style="text-align:right"><?php echo number_format($item['unit_price_pkr'], 2); ?></td>
                <td style="text-align:right;font-weight:700;"><?php echo number_format($item['total_cny'], 2); ?></td>
                <td style="text-align:right"><?php echo number_format($item['total_pkr'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="inv-totals">
        <table>
            <?php if (($purchase['previous_amount_cny'] ?? 0) > 0): ?>
            <tr><td>Previous Balance (CNY):</td><td><?php echo number_format($purchase['previous_amount_cny'], 2); ?></td></tr>
            <?php endif; ?>
            <tr><td>Bill Amount (CNY):</td><td><?php echo number_format($purchase['total_cny'], 2); ?></td></tr>
            <?php if (($purchase['tax_amount_cny'] ?? 0) > 0): ?>
            <tr><td>S. Tax (CNY):</td><td><?php echo number_format($purchase['tax_amount_cny'], 2); ?></td></tr>
            <?php endif; ?>
            <tr><td><strong>Grand Total (CNY):</strong></td><td><strong><?php echo number_format($purchase['grand_total_cny'], 2); ?></strong></td></tr>
            <tr><td class="small text-muted">Grand Total (PKR):</td><td class="small"><?php echo number_format($purchase['grand_total_pkr'], 2); ?></td></tr>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-inbox fa-3x mb-3"></i>
        <h5>No Items Found</h5>
        <p>This purchase has no items recorded. <a href="add_import_purchase.php?edit=<?php echo $purchase['id']; ?>">Edit purchase</a> to add items.</p>
    </div>
    <?php endif; ?>

    <div class="inv-footer">
        <p>Thank you for your business!</p>
    </div>
</div>

<!-- Thermal Invoice -->
<div class="invoice-thermal" id="invoiceThermal">
    <div class="th-header">
        <h5><?php echo CLIENT_NAME; ?></h5>
        <p><?php echo CLIENT_ADDRESS; ?> | Ph: <?php echo CLIENT_PHONE; ?></p>
    </div>
    <table>
        <tr><td><strong>Date:</strong></td><td><?php echo formatDate($purchase['purchase_date']); ?></td></tr>
        <tr><td><strong>Inv #:</strong></td><td><?php echo htmlspecialchars($purchase['invoice_no'] ?? '-'); ?></td></tr>
        <tr><td><strong>Supplier:</strong></td><td><?php echo htmlspecialchars($purchase['supplier_name']); ?></td></tr>
        <?php if ($purchase['phone']): ?>
        <tr><td><strong>Phone:</strong></td><td><?php echo htmlspecialchars($purchase['phone']); ?></td></tr>
        <?php endif; ?>
        <tr><td><strong>Rate:</strong></td><td>1 CNY = <?php echo number_format($purchase['exchange_rate'], 2); ?> PKR</td></tr>
    </table>
    <div class="th-line"></div>
    <?php if (count($items) > 0): ?>
    <table>
        <thead>
            <tr><th>Item</th><th style="text-align:right">Qty</th><th style="text-align:right">CNY</th><th style="text-align:right">Total</th></tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['material_name'] ?? '-'); ?></td>
                <td style="text-align:right"><?php echo number_format($item['quantity'], 0); ?></td>
                <td style="text-align:right"><?php echo number_format($item['unit_price_cny'], 2); ?></td>
                <td style="text-align:right;font-weight:700;"><?php echo number_format($item['total_cny'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="text-center">No items</p>
    <?php endif; ?>
    <div class="th-line"></div>
    <table>
        <?php if (($purchase['previous_amount_cny'] ?? 0) > 0): ?>
        <tr><td>Previous:</td><td style="text-align:right"><?php echo number_format($purchase['previous_amount_cny'], 2); ?></td></tr>
        <?php endif; ?>
        <tr><td>Bill Amt:</td><td style="text-align:right"><?php echo number_format($purchase['total_cny'], 2); ?></td></tr>
        <tr><td>S.Tax:</td><td style="text-align:right"><?php echo number_format($purchase['tax_amount_cny'] ?? 0, 2); ?></td></tr>
    </table>
    <div class="th-line"></div>
    <table>
        <tr><td class="th-total">GRAND TOTAL:</td><td class="th-total" style="text-align:right"><?php echo number_format($purchase['grand_total_cny'], 2); ?> CNY</td></tr>
        <tr><td class="small text-muted">(PKR)</td><td style="text-align:right" class="small"><?php echo number_format($purchase['grand_total_pkr'], 2); ?></td></tr>
    </table>
    <div class="th-line"></div>
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
