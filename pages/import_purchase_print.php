<?php
require_once '../includes/database.php';
requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: import_purchases.php');
    exit;
}

$purchase = getRow("SELECT p.*, s.supplier_name, s.company_name, s.contact_person, s.phone, s.email, s.address, s.city, s.country
    FROM import_purchases p
    LEFT JOIN chinese_suppliers s ON p.supplier_id = s.id
    WHERE p.id = ?", 'i', [$id]);

if (!$purchase) {
    header('Location: import_purchases.php');
    exit;
}

$items = getRows("SELECT i.*, rm.material_code, rm.material_name, rm.unit
    FROM import_purchase_items i
    LEFT JOIN raw_materials rm ON i.material_id = rm.id
    WHERE i.purchase_id = ?
    ORDER BY i.id", 'i', [$id]);

$company = getRow("SELECT * FROM company_settings LIMIT 1");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Purchase #<?php echo $purchase['purchase_no']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .no-print { padding: 20px; background: #f8f9fa; border-bottom: 2px solid #dee2e6; }
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 0; }
        }

        /* A4 Styles */
        .a4-preview { background: #fff; max-width: 210mm; margin: 20px auto; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .a4-preview .header-section { border-bottom: 3px solid #1a2332; padding-bottom: 15px; margin-bottom: 20px; }
        .a4-preview .company-name { font-size: 22px; font-weight: 700; color: #1a2332; }
        .a4-preview .invoice-title { font-size: 18px; font-weight: 600; color: #1a2332; text-align: right; }
        .a4-preview table { font-size: 13px; }
        .a4-preview table th { background: #1a2332; color: #fff; padding: 8px 10px; font-weight: 600; }
        .a4-preview table td { padding: 7px 10px; vertical-align: middle; }
        .a4-preview .summary-table td { padding: 5px 10px; }
        .a4-preview .footer-section { border-top: 2px solid #dee2e6; padding-top: 15px; margin-top: 30px; font-size: 12px; color: #666; }

        @media print {
            .a4-preview { box-shadow: none; margin: 0; padding: 15mm; max-width: none; }
        }

        /* Thermal Styles */
        .thermal-preview { background: #fff; width: 80mm; margin: 20px auto; padding: 10mm; box-shadow: 0 2px 10px rgba(0,0,0,0.1); font-family: 'Courier New', monospace; font-size: 12px; }
        .thermal-preview .text-center { text-align: center; }
        .thermal-preview .text-right { text-align: right; }
        .thermal-preview .fw-bold { font-weight: bold; }
        .thermal-preview .border-top { border-top: 1px dashed #000; margin: 5px 0; padding-top: 5px; }
        .thermal-preview .border-double { border-top: 3px double #000; margin: 5px 0; padding-top: 5px; }
        .thermal-preview table { width: 100%; font-size: 11px; }
        .thermal-preview table td { padding: 2px 0; }
        .thermal-preview .small { font-size: 10px; }
        .thermal-preview .line { border-top: 1px dashed #000; margin: 3px 0; }

        @media print {
            .thermal-preview { box-shadow: none; margin: 0; padding: 5mm; width: 80mm; }
            @page { size: 80mm auto; margin: 0; }
        }

        .preview-section { display: none; }
        .preview-section.active { display: block; }
    </style>
</head>
<body>

<div class="no-print">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4><i class="fas fa-file-invoice me-2"></i>Import Purchase Invoice</h4>
                <p class="text-muted mb-0">Purchase #<?php echo $purchase['purchase_no']; ?></p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" onclick="showA4()">
                    <i class="fas fa-file-pdf me-1"></i>A4 Print
                </button>
                <button class="btn btn-success" onclick="showThermal()">
                    <i class="fas fa-receipt me-1"></i>Thermal Print
                </button>
                <button class="btn btn-warning" onclick="printPreview()">
                    <i class="fas fa-print me-1"></i>Print
                </button>
                <a href="import_purchases.php" class="btn btn-light">
                    <i class="fas fa-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>
    </div>
</div>

<!-- A4 Preview -->
<div class="preview-section active" id="a4Preview">
    <div class="a4-preview">
        <div class="header-section">
            <div class="row">
                <div class="col-6">
                    <div class="company-name"><?php echo htmlspecialchars($company['company_name'] ?? 'ERP System'); ?></div>
                    <div class="small text-muted"><?php echo htmlspecialchars($company['company_tagline'] ?? 'Manufacturing Enterprise Resource Planning'); ?></div>
                    <?php if ($company['address'] ?? null): ?><div class="small text-muted"><?php echo htmlspecialchars($company['address']); ?></div><?php endif; ?>
                    <?php if ($company['phone'] ?? null): ?><div class="small text-muted">Ph: <?php echo htmlspecialchars($company['phone']); ?></div><?php endif; ?>
                </div>
                <div class="col-6 text-end">
                    <div class="invoice-title">IMPORT PURCHASE INVOICE</div>
                    <div class="small">Date: <strong><?php echo date('d-m-Y', strtotime($purchase['purchase_date'])); ?></strong></div>
                    <div class="small">Invoice #: <strong><?php echo htmlspecialchars($purchase['invoice_no'] ?? '-'); ?></strong></div>
                    <div class="small">Purchase No: <strong><?php echo htmlspecialchars($purchase['purchase_no']); ?></strong></div>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-6">
                <strong class="text-muted small">SUPPLIER DETAILS</strong>
                <div class="mt-1">
                    <strong><?php echo htmlspecialchars($purchase['supplier_name']); ?></strong><br>
                    <?php if ($purchase['company_name']): ?><?php echo htmlspecialchars($purchase['company_name']); ?><br><?php endif; ?>
                    <?php if ($purchase['contact_person']): ?><?php echo htmlspecialchars($purchase['contact_person']); ?><br><?php endif; ?>
                    <?php if ($purchase['phone']): ?>Phone: <?php echo htmlspecialchars($purchase['phone']); ?><br><?php endif; ?>
                    <?php if ($purchase['email']): ?>Email: <?php echo htmlspecialchars($purchase['email']); ?><br><?php endif; ?>
                    <?php if ($purchase['city']): ?><?php echo htmlspecialchars($purchase['city']); ?>, <?php endif; ?>
                    <?php echo htmlspecialchars($purchase['country'] ?? 'China'); ?>
                </div>
            </div>
            <div class="col-6">
                <strong class="text-muted small">EXCHANGE RATE</strong>
                <div class="mt-1">
                    1 CNY = <strong><?php echo number_format($purchase['exchange_rate'], 2); ?></strong> PKR
                </div>
            </div>
        </div>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Material Code</th>
                    <th>Material Name</th>
                    <th>Unit</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Price (CNY)</th>
                    <th class="text-end">Price (PKR)</th>
                    <th class="text-end">Total (CNY)</th>
                    <th class="text-end">Total (PKR)</th>
                </tr>
            </thead>
            <tbody>
                <?php $counter = 1; ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
                        <td><?php echo htmlspecialchars($item['material_code']); ?></td>
                        <td><?php echo htmlspecialchars($item['material_name']); ?></td>
                        <td><?php echo htmlspecialchars($item['unit'] ?? '-'); ?></td>
                        <td class="text-end"><?php echo number_format($item['quantity'], 2); ?></td>
                        <td class="text-end"><?php echo number_format($item['unit_price_cny'], 2); ?></td>
                        <td class="text-end"><?php echo number_format($item['unit_price_pkr'], 2); ?></td>
                        <td class="text-end fw-bold"><?php echo number_format($item['total_cny'], 2); ?></td>
                        <td class="text-end"><?php echo number_format($item['total_pkr'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="row">
            <div class="col-6">
                <div class="footer-section">
                    <strong>Notes:</strong><br>
                    This is a computer generated invoice.<br>
                    Exchange Rate: 1 CNY = <?php echo number_format($purchase['exchange_rate'], 2); ?> PKR
                </div>
            </div>
            <div class="col-6">
                <table class="table table-sm summary-table" style="width:250px;float:right;">
                    <tr>
                        <td><strong>Previous Amount (CNY)</strong></td>
                        <td class="text-end"><?php echo number_format($purchase['previous_amount_cny'] ?? 0, 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Bill Amount (CNY)</strong></td>
                        <td class="text-end"><?php echo number_format($purchase['total_cny'], 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Total Amount (CNY)</strong></td>
                        <td class="text-end"><?php echo number_format(($purchase['previous_amount_cny'] ?? 0) + $purchase['total_cny'], 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>S. Tax (CNY)</strong></td>
                        <td class="text-end"><?php echo number_format($purchase['tax_amount_cny'] ?? 0, 2); ?></td>
                    </tr>
                    <tr style="border-top:2px solid #1a2332;">
                        <td><strong style="font-size:15px;">Grand Amount (CNY)</strong></td>
                        <td class="text-end fw-bold" style="font-size:15px;color:#198754;"><?php echo number_format($purchase['grand_total_cny'], 2); ?></td>
                    </tr>
                    <tr>
                        <td class="small text-muted">Grand Amount (PKR)</td>
                        <td class="text-end small"><?php echo number_format($purchase['grand_total_pkr'], 2); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Thermal Preview -->
<div class="preview-section" id="thermalPreview">
    <div class="thermal-preview" id="thermalContent">
        <div class="text-center">
            <div class="fw-bold" style="font-size:16px;"><?php echo strtoupper(htmlspecialchars($company['company_name'] ?? 'ERP SYSTEM')); ?></div>
            <div class="small"><?php echo htmlspecialchars($company['company_tagline'] ?? 'Manufacturing ERP'); ?></div>
            <div class="small">Import Purchase Invoice</div>
        </div>
        <div class="line"></div>
        <table>
            <tr><td>Date:</td><td class="text-right fw-bold"><?php echo date('d-m-Y', strtotime($purchase['purchase_date'])); ?></td></tr>
            <tr><td>Inv #:</td><td class="text-right"><?php echo htmlspecialchars($purchase['invoice_no'] ?? '-'); ?></td></tr>
            <tr><td>Pur No:</td><td class="text-right fw-bold"><?php echo htmlspecialchars($purchase['purchase_no']); ?></td></tr>
        </table>
        <div class="line"></div>
        <div class="fw-bold small">SUPPLIER</div>
        <div class="small"><?php echo htmlspecialchars($purchase['supplier_name']); ?></div>
        <?php if ($purchase['company_name']): ?><div class="small"><?php echo htmlspecialchars($purchase['company_name']); ?></div><?php endif; ?>
        <div class="line"></div>
        <table>
            <thead>
                <tr><td class="fw-bold small">ITEM</td><td class="fw-bold small text-right">QTY</td><td class="fw-bold small text-right">CNY</td><td class="fw-bold small text-right">TOTAL</td></tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="small"><?php echo htmlspecialchars($item['material_name']); ?></td>
                        <td class="small text-right"><?php echo number_format($item['quantity'], 0); ?></td>
                        <td class="small text-right"><?php echo number_format($item['unit_price_cny'], 2); ?></td>
                        <td class="small text-right fw-bold"><?php echo number_format($item['total_cny'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="line"></div>
        <table>
            <tr><td>Previous:</td><td class="text-right"><?php echo number_format($purchase['previous_amount_cny'] ?? 0, 2); ?></td></tr>
            <tr><td>Bill Amt:</td><td class="text-right"><?php echo number_format($purchase['total_cny'], 2); ?></td></tr>
            <tr><td>S.Tax:</td><td class="text-right"><?php echo number_format($purchase['tax_amount_cny'] ?? 0, 2); ?></td></tr>
        </table>
        <div class="border-double">
            <table>
                <tr><td class="fw-bold" style="font-size:14px;">GRAND TOTAL:</td><td class="text-right fw-bold" style="font-size:14px;"><?php echo number_format($purchase['grand_total_cny'], 2); ?> CNY</td></tr>
            </table>
            <div class="small text-center text-muted">(PKR <?php echo number_format($purchase['grand_total_pkr'], 2); ?>)</div>
        </div>
        <div class="line"></div>
        <div class="text-center small">Thank you for your business!</div>
    </div>
</div>

<script>
function showA4() {
    document.getElementById('a4Preview').classList.add('active');
    document.getElementById('thermalPreview').classList.remove('active');
}

function showThermal() {
    document.getElementById('thermalPreview').classList.add('active');
    document.getElementById('a4Preview').classList.remove('active');
}

function printPreview() {
    window.print();
}
</script>

</body>
</html>
