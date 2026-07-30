<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Purchase Detail';

$type = isset($_GET['type']) ? $_GET['type'] : 'local';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: local_purchases.php');
    exit;
}

if ($type === 'local') {
    $purchase = getRow("SELECT p.*, s.supplier_name, s.company_name, s.phone as sup_phone, s.address as sup_address
        FROM local_purchases p LEFT JOIN local_suppliers s ON p.supplier_id = s.id WHERE p.id = ?", 'i', [$id]);
    if (!$purchase) { header('Location: local_purchases.php'); exit; }

    $items = getRows("SELECT li.*,
        CASE WHEN li.item_type = 'finished_good' THEN fg.product_code WHEN li.item_type = 'raw_material' THEN rm.material_code ELSE rm.material_code END AS material_code,
        CASE WHEN li.item_type = 'finished_good' THEN fg.product_name WHEN li.item_type = 'raw_material' THEN rm.material_name ELSE rm.material_name END AS material_name,
        CASE WHEN li.item_type = 'finished_good' THEN fg.unit WHEN li.item_type = 'raw_material' THEN rm.unit ELSE rm.unit END AS unit,
        li.item_type
        FROM local_purchase_items li
        LEFT JOIN raw_materials rm ON li.material_id = rm.id AND li.item_type = 'raw_material'
        LEFT JOIN finished_goods fg ON li.material_id = fg.id AND li.item_type = 'finished_good'
        WHERE li.purchase_id = ?", 'i', [$id]);

    $backPage = 'local_purchases.php';
} elseif ($type === 'import') {
    $purchase = getRow("SELECT p.*, s.supplier_name, s.company_name, s.phone as sup_phone
        FROM import_purchases p LEFT JOIN chinese_suppliers s ON p.supplier_id = s.id WHERE p.id = ?", 'i', [$id]);
    if (!$purchase) { header('Location: import_purchases.php'); exit; }

    $items = getRows("SELECT li.*,
        li.unit_price_cny as unit_price,
        li.total_cny as total,
        CASE WHEN li.item_type = 'finished_good' THEN fg.product_code WHEN li.item_type = 'raw_material' THEN rm.material_code ELSE rm.material_code END AS material_code,
        CASE WHEN li.item_type = 'finished_good' THEN fg.product_name WHEN li.item_type = 'raw_material' THEN rm.material_name ELSE rm.material_name END AS material_name,
        CASE WHEN li.item_type = 'finished_good' THEN fg.unit WHEN li.item_type = 'raw_material' THEN rm.unit ELSE rm.unit END AS unit,
        li.item_type
        FROM import_purchase_items li
        LEFT JOIN raw_materials rm ON li.material_id = rm.id AND li.item_type = 'raw_material'
        LEFT JOIN finished_goods fg ON li.material_id = fg.id AND li.item_type = 'finished_good'
        WHERE li.purchase_id = ?", 'i', [$id]);

    $backPage = 'import_purchases.php';
} else {
    header('Location: local_purchases.php');
    exit;
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="row mb-3">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo $backPage; ?>"><?php echo $type === 'import' ? 'Import Purchases' : 'Local Purchases'; ?></a></li>
                <li class="breadcrumb-item active">Purchase Detail</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <a href="<?php echo $backPage; ?>" class="btn btn-secondary btn-sm me-2"><i class="fas fa-arrow-left me-1"></i>Back to List</a>
        <a href="<?php echo $type === 'import' ? 'add_import_purchase.php?edit=' . $id : 'add_local_purchase.php?id=' . $id; ?>" class="btn btn-warning btn-sm me-2"><i class="fas fa-edit me-1"></i>Edit</a>
        <a href="<?php echo $type === 'import' ? 'import_purchase_print.php?id=' . $id : 'purchase_print.php?id=' . $id; ?>" class="btn btn-success btn-sm" target="_blank"><i class="fas fa-print me-1"></i>Print</a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header" style="background:#1a2332;color:#fff;">
                <i class="fas fa-info-circle me-2"></i>Purchase Information
            </div>
            <div class="card-body">
                <table class="table table-bordered mb-0">
                    <tr><th style="width:40%">Purchase No</th><td><strong><?php echo htmlspecialchars($purchase['purchase_no']); ?></strong></td></tr>
                    <tr><th>Supplier</th><td><?php echo htmlspecialchars($purchase['supplier_name']); ?></td></tr>
                    <?php if ($purchase['company_name']): ?>
                    <tr><th>Company</th><td><?php echo htmlspecialchars($purchase['company_name']); ?></td></tr>
                    <?php endif; ?>
                    <?php if ($purchase['sup_phone']): ?>
                    <tr><th>Phone</th><td><?php echo htmlspecialchars($purchase['sup_phone']); ?></td></tr>
                    <?php endif; ?>
                    <tr><th>Date</th><td><?php echo formatDate($purchase['purchase_date']); ?></td></tr>
                    <tr><th>Invoice No</th><td><?php echo htmlspecialchars($purchase['invoice_no']); ?></td></tr>
                    <tr><th>Payment Method</th><td><?php echo ucfirst($purchase['payment_method'] ?? 'Credit'); ?></td></tr>
                    <tr><th>Status</th>
                        <td><span class="badge badge-status badge-<?php echo strtolower($purchase['payment_status']); ?>"><?php echo ucfirst($purchase['payment_status']); ?></span></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header" style="background:#1a2332;color:#fff;">
                <i class="fas fa-calculator me-2"></i>Payment Summary
            </div>
            <div class="card-body text-center">
                <?php if ($type === 'import'): ?>
                    <h6 class="text-muted">Grand Total (CNY)</h6>
                    <h3 class="text-primary"><?php echo formatCurrency($purchase['grand_total_cny']); ?></h3>
                    <h6 class="text-muted">Grand Total (PKR)</h6>
                    <h4 class="text-secondary"><?php echo formatCurrency($purchase['grand_total_pkr']); ?></h4>
                    <hr>
                    <div class="row">
                        <div class="col-6">
                            <h6 class="text-muted">Paid (CNY)</h6>
                            <h5 class="text-success"><?php echo formatCurrency($purchase['paid_amount_cny']); ?></h5>
                        </div>
                        <div class="col-6">
                            <h6 class="text-muted">Balance (CNY)</h6>
                            <h5 class="<?php echo $purchase['balance_cny'] > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo formatCurrency($purchase['balance_cny']); ?></h5>
                        </div>
                    </div>
                <?php else: ?>
                    <h6 class="text-muted">Grand Total</h6>
                    <h3 class="text-primary"><?php echo formatCurrency($purchase['total_amount']); ?></h3>
                    <hr>
                    <div class="row">
                        <div class="col-6">
                            <h6 class="text-muted">Paid</h6>
                            <h5 class="text-success"><?php echo formatCurrency($purchase['paid_amount']); ?></h5>
                        </div>
                        <div class="col-6">
                            <h6 class="text-muted">Balance</h6>
                            <h5 class="<?php echo $purchase['balance'] > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo formatCurrency($purchase['balance']); ?></h5>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header" style="background:#1a2332;color:#fff;">
                <i class="fas fa-boxes me-2"></i>Purchase Items
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Code</th>
                                <th>Material / Product</th>
                                <th>Type</th>
                                <th style="text-align:center">Qty</th>
                                <th style="text-align:right">Unit Price</th>
                                <th style="text-align:right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; $totalQty = 0; foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($item['material_code']); ?></td>
                                <td><strong><?php echo htmlspecialchars($item['material_name']); ?></strong></td>
                                <td><span class="badge bg-<?php echo $item['item_type'] === 'finished_good' ? 'info' : 'primary'; ?>"><?php echo $item['item_type'] === 'finished_good' ? 'Finished Good' : 'Raw Material'; ?></span></td>
                                <td style="text-align:center"><?php echo number_format($item['quantity'], 2); ?> <?php echo $item['unit']; ?></td>
                                <td style="text-align:right"><?php echo formatCurrency($item['unit_price']); ?></td>
                                <td style="text-align:right"><strong><?php echo formatCurrency($item['total']); ?></strong></td>
                            </tr>
                            <?php $totalQty += $item['quantity']; endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-active">
                                <td colspan="4"><strong>Total Items: <?php echo count($items); ?></strong></td>
                                <td style="text-align:center"><strong><?php echo number_format($totalQty, 2); ?></strong></td>
                                <td style="text-align:right"><strong>Grand Total:</strong></td>
                                <td style="text-align:right"><strong><?php echo $type === 'import' ? formatCurrency($purchase['grand_total_cny']) : formatCurrency($purchase['total_amount']); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
