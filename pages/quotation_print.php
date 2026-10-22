<?php
require_once '../includes/database.php';
requireLogin();

$quotationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($quotationId <= 0) {
    header('Location: quotations.php');
    exit;
}

$quotation = getRow("SELECT q.*, c.customer_name, c.company_name, c.phone as cust_phone, c.address as cust_address
    FROM quotations q LEFT JOIN customers c ON q.customer_id = c.id WHERE q.id = ?", 'i', [$quotationId]);

if (!$quotation) {
    header('Location: quotations.php');
    exit;
}

$items = getRows("SELECT qi.*, 
    COALESCE(rm.material_code, fg.product_code) AS product_code,
    COALESCE(rm.unit, fg.unit) AS unit
    FROM quotation_items qi 
    LEFT JOIN finished_goods fg ON qi.product_id = fg.id AND (qi.product_type = 'finished_good' OR qi.product_type IS NULL)
    LEFT JOIN raw_materials rm ON qi.product_id = rm.id AND qi.product_type = 'raw_material'
    WHERE qi.quotation_id = ?", 'i', [$quotationId]);

$customerName = '';
$customerPhone = '';
$customerAddress = '';

if (!empty($quotation['customer_id'])) {
    $customerName = $quotation['customer_name'] ?? 'Customer';
    $customerPhone = $quotation['cust_phone'] ?? '';
    $customerAddress = $quotation['cust_address'] ?? '';
} else {
    $customerName = $quotation['walkin_name'] ?: 'Valued Client';
    $customerPhone = $quotation['walkin_phone'] ?? '';
    $customerAddress = $quotation['walkin_address'] ?? '';
}

$isTax = ($quotation['invoice_type'] ?? 'non_tax') === 'tax';
$subtotal = (float)($quotation['subtotal'] ?? 0);
$discount = (float)($quotation['discount'] ?? 0);
$taxRate = (float)($quotation['tax_rate'] ?? 0);
$taxAmount = (float)($quotation['tax_amount'] ?? 0);
$shippingCost = (float)($quotation['shipping_cost'] ?? 0);
$totalAmount = (float)($quotation['total_amount'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quotation - <?php echo htmlspecialchars($quotation['quotation_no']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* { font-family: 'Poppins', sans-serif; }
body { background: #f0f2f5; margin: 0; padding: 0; color: #212529; }
.action-buttons { position: fixed; top: 0; left: 0; right: 0; z-index: 9999; background: #1a2332; padding: 10px 20px; display: flex; gap: 10px; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
.action-buttons .btn { font-size: 14px; }
.bill-wrapper { margin-top: 70px; display: flex; justify-content: center; padding: 20px; }
.invoice-a4 { width: 100%; max-width: 820px; background: #fff; padding: 35px 40px; border: 1px solid #ddd; }
.inv-title { text-align: center; background: #f0f4f8; padding: 8px; border: 1px solid #cbd5e1; margin-bottom: 20px; font-weight: 700; font-size: 16px; color: #1a2332; letter-spacing: 1px; }
.inv-meta { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 13px; line-height: 1.8; }
.table-items { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px; }
.table-items th { background: #1a2332; color: #fff; padding: 9px 12px; font-weight: 600; text-align: left; }
.table-items td { padding: 9px 12px; border-bottom: 1px solid #dee2e6; }
.table-items tr:nth-child(even) { background: #fcfcfc; }
.inv-totals-wrap { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; margin-top: 15px; }
.terms-box { flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 15px; font-size: 12px; }
.terms-box h6 { font-size: 13px; font-weight: 700; color: #1a2332; margin-bottom: 6px; }
.inv-totals-table { width: 320px; border-collapse: collapse; font-size: 13px; }
.inv-totals-table td { padding: 6px 10px; }
.inv-totals-table tr td:last-child { text-align: right; }
.inv-totals-table .grand-row td { border-top: 2px solid #1a2332; border-bottom: 2px solid #1a2332; font-size: 16px; font-weight: 700; color: #1a2332; padding: 8px 10px; }
.sign-row { display: flex; justify-content: space-between; margin-top: 50px; padding-top: 15px; }
.sign-box { text-align: center; width: 180px; border-top: 1px solid #333; padding-top: 5px; font-size: 11px; font-weight: 600; color: #444; }
.inv-footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #64748b; }
@media print {
    body { background: #fff !important; margin: 0; padding: 0; }
    .action-buttons { display: none !important; }
    .bill-wrapper { margin: 0; padding: 0; }
    .invoice-a4 { border: none; padding: 10px; max-width: 100%; width: 100%; }
    @page { size: A4 portrait; margin: 10mm; }
}
</style>
</head>
<body>

<div class="action-buttons">
    <a href="quotations.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Quotations</a>
    <span class="text-white fw-bold me-2"><i class="fas fa-file-invoice me-1"></i><?php echo htmlspecialchars($quotation['quotation_no']); ?></span>
    <button class="btn btn-light btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
    <button class="btn btn-success btn-sm" onclick="downloadPDF()"><i class="fas fa-download me-1"></i>Download PDF</button>
    <a href="new_quotation.php" class="btn btn-warning btn-sm ms-auto"><i class="fas fa-plus me-1"></i>New Quotation</a>
</div>

<div class="bill-wrapper">
<div class="invoice-a4" id="quotationContent">
    <?php include '../includes/print_branding.php'; ?>

    <div class="inv-title">
        <?php echo $isTax ? 'TAX ESTIMATE / QUOTATION' : 'OFFICIAL ESTIMATE / QUOTATION'; ?>
    </div>

    <div class="inv-meta">
        <div>
            <div><strong>Quotation #:</strong> <span class="text-primary fw-bold"><?php echo htmlspecialchars($quotation['quotation_no']); ?></span></div>
            <div><strong>Date:</strong> <?php echo formatDate($quotation['quotation_date']); ?></div>
            <?php if (!empty($quotation['valid_until'])): ?>
                <div><strong>Valid Until:</strong> <span class="text-danger fw-semibold"><?php echo formatDate($quotation['valid_until']); ?></span></div>
            <?php endif; ?>
            <div><strong>Type:</strong> <span class="badge bg-<?php echo $isTax ? 'primary' : 'secondary'; ?>"><?php echo $isTax ? 'Tax Quotation (' . number_format($taxRate, 1) . '%)' : 'Non-Tax Quotation'; ?></span></div>
        </div>
        <div style="text-align:right">
            <div><strong>Customer / Client:</strong></div>
            <div style="font-size: 15px; font-weight: 700; color: #1a2332;"><?php echo htmlspecialchars($customerName); ?></div>
            <?php if (!empty($customerPhone)): ?>
                <div><i class="fas fa-phone-alt me-1 text-muted"></i><?php echo htmlspecialchars($customerPhone); ?></div>
            <?php endif; ?>
            <?php if (!empty($customerAddress)): ?>
                <div style="max-width:280px; margin-left:auto;"><i class="fas fa-map-marker-alt me-1 text-muted"></i><?php echo htmlspecialchars($customerAddress); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <table class="table-items">
        <thead>
            <tr>
                <th style="width: 40px;">#</th>
                <th>Item Description</th>
                <th>Unit</th>
                <th style="text-align: right; width: 90px;">Qty</th>
                <th style="text-align: right; width: 120px;">Unit Price (Rs)</th>
                <th style="text-align: right; width: 130px;">Total (Rs)</th>
            </tr>
        </thead>
        <tbody>
            <?php $n = 1; foreach ($items as $item): ?>
                <tr>
                    <td><?php echo $n++; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                        <?php if (!empty($item['product_code'])): ?>
                            <small class="text-muted d-block"><?php echo htmlspecialchars($item['product_code']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($item['unit'] ?: 'units'); ?></td>
                    <td style="text-align: right; font-weight: 600;"><?php echo number_format((float)$item['quantity'], 2); ?></td>
                    <td style="text-align: right;"><?php echo formatCurrency($item['unit_price']); ?></td>
                    <td style="text-align: right; font-weight: 700;"><?php echo formatCurrency($item['total']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="inv-totals-wrap">
        <div class="terms-box">
            <h6><i class="fas fa-info-circle me-1 text-primary"></i>Terms &amp; Notes</h6>
            <?php if (!empty($quotation['notes'])): ?>
                <div style="white-space: pre-line;"><?php echo htmlspecialchars($quotation['notes']); ?></div>
            <?php else: ?>
                <p class="mb-1">1. Prices are valid for 15 days from quotation date.</p>
                <p class="mb-1">2. Delivery terms and schedule will be confirmed upon order confirmation.</p>
                <p class="mb-0">3. This is an estimate / quotation and does not constitute a confirmed tax invoice.</p>
            <?php endif; ?>
        </div>

        <table class="inv-totals-table">
            <tr>
                <td>Subtotal:</td>
                <td>Rs. <?php echo formatCurrency($subtotal); ?></td>
            </tr>
            <?php if ($discount > 0): ?>
                <tr class="text-danger">
                    <td>Discount:</td>
                    <td>- Rs. <?php echo formatCurrency($discount); ?></td>
                </tr>
            <?php endif; ?>
            <?php if ($isTax && $taxAmount > 0): ?>
                <tr class="text-primary">
                    <td>Tax (<?php echo number_format($taxRate, 2); ?>%):</td>
                    <td>+ Rs. <?php echo formatCurrency($taxAmount); ?></td>
                </tr>
            <?php endif; ?>
            <?php if ($shippingCost > 0): ?>
                <tr class="text-info">
                    <td>Shipping Charges:</td>
                    <td>+ Rs. <?php echo formatCurrency($shippingCost); ?></td>
                </tr>
            <?php endif; ?>
            <tr class="grand-row">
                <td>Grand Total:</td>
                <td>Rs. <?php echo formatCurrency($totalAmount); ?></td>
            </tr>
        </table>
    </div>

    <div class="sign-row">
        <div class="sign-box">Prepared By</div>
        <div class="sign-box">Client Acceptance</div>
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
        filename: 'quotation_<?php echo htmlspecialchars($quotation['quotation_no']); ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
    };
    if (typeof html2pdf !== 'undefined') {
        html2pdf().set(opt).from(document.getElementById('quotationContent')).save();
    } else {
        var s = document.createElement('script');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
        s.onload = function() { html2pdf().set(opt).from(document.getElementById('quotationContent')).save(); };
        document.head.appendChild(s);
    }
}
</script>
</body>
</html>