<?php
require_once '../includes/database.php';
requireLogin();

$saleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($saleId <= 0) { header('Location: sales.php'); exit; }

$sale = getRow("SELECT s.*, c.customer_name, c.company_name, c.phone as cust_phone, c.address as cust_address
    FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.id = ?", 'i', [$saleId]);
if (!$sale) { header('Location: sales.php'); exit; }

$customerName = $sale['customer_type'] === 'walkin' ? $sale['walkin_name'] : ($sale['customer_name'] ?? '');
$customerPhone = $sale['customer_type'] === 'walkin' ? $sale['walkin_phone'] : ($sale['cust_phone'] ?? '');

$items = getRows("SELECT si.*, fg.product_code, fg.product_name, fg.unit
    FROM sale_items si LEFT JOIN finished_goods fg ON si.product_id = fg.id
    WHERE si.sale_id = ?", 'i', [$saleId]);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
.view-card { max-width: 900px; margin: 0 auto; }
.info-row { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
.info-row .label { font-size: 12px; color: #6c757d; text-transform: uppercase; }
.info-row .value { font-size: 15px; font-weight: 600; }
.table-items thead th { background: #1a2332; color: #fff; font-size: 13px; }
.table-items tbody td { font-size: 13px; vertical-align: middle; }
.summary-box { background: #f8f9fa; border-radius: 8px; padding: 15px; }
.summary-box table td { padding: 5px 10px; border-bottom: 1px solid #eee; }
.summary-box table td:last-child { text-align: right; font-weight: 600; }
.refund-row { background: #fff3cd; }
.total-row { background: #d4edda; }
</style>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card shadow-sm view-card">
            <div class="card-header d-flex justify-content-between align-items-center" style="background:#1a2332;color:#fff;">
                <span><i class="fas fa-eye me-2"></i>Sale Details — <?php echo htmlspecialchars($sale['sale_no']); ?></span>
                <a href="sales.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">

                <div class="info-row">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <div class="label">Invoice</div>
                            <div class="value"><?php echo htmlspecialchars($sale['sale_no']); ?></div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="label">Date</div>
                            <div class="value"><?php echo date('d-m-Y', strtotime($sale['sale_date'])); ?></div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="label">Customer</div>
                            <div class="value"><?php echo htmlspecialchars($customerName ?: 'Walk-in'); ?></div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="label">Phone</div>
                            <div class="value"><?php echo htmlspecialchars($customerPhone ?: '-'); ?></div>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-3 mb-2">
                            <div class="label">Payment Method</div>
                            <div class="value"><span class="badge bg-<?php echo $sale['payment_method'] === 'cash' || $sale['payment_method'] === 'bank' ? 'success' : 'primary'; ?>"><?php echo ucfirst($sale['payment_method']); ?></span></div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="label">Payment Status</div>
                            <div class="value"><span class="badge bg-<?php echo $sale['payment_status'] === 'paid' ? 'success' : ($sale['payment_status'] === 'partial' ? 'warning' : 'danger'); ?>"><?php echo ucfirst($sale['payment_status']); ?></span></div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="label">Status</div>
                            <div class="value"><span class="badge bg-<?php echo $sale['status'] === 'completed' ? 'success' : 'warning'; ?>"><?php echo ucfirst($sale['status']); ?></span></div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="label">Sale Type</div>
                            <div class="value"><?php echo ucfirst($sale['customer_type']); ?></div>
                        </div>
                    </div>
                </div>

                <h6 class="mb-3"><i class="fas fa-boxes me-2"></i>Products Sold</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-sm table-items">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Code</th>
                                <th>Product</th>
                                <th class="text-center">Qty</th>
                                <th class="text-right">Unit Price</th>
                                <th class="text-right">Total</th>
                                <th class="text-center">Refund Qty</th>
                                <th class="text-right">Refund Amt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; $totalRefundItems = 0; ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><?php echo htmlspecialchars($item['product_code']); ?></td>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td class="text-center"><?php echo number_format($item['quantity'], 2); ?></td>
                                    <td class="text-right"><?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td class="text-right"><?php echo number_format($item['total'], 2); ?></td>
                                    <td class="text-center">
                                        <?php if (($item['refund_qty'] ?? 0) > 0): ?>
                                            <span class="badge bg-danger"><?php echo number_format($item['refund_qty'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right">
                                        <?php if (($item['refund_amount'] ?? 0) > 0): ?>
                                            <span class="text-danger fw-bold"><?php echo number_format($item['refund_amount'], 2); ?></span>
                                            <?php $totalRefundItems += $item['refund_amount']; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="row">
                    <div class="col-md-7">
                        <?php if ($sale['status'] === 'hold' && !empty($sale['hold_reason'])): ?>
                            <div class="alert alert-warning">
                                <strong>Hold Reason:</strong> <?php echo htmlspecialchars($sale['hold_reason']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-5">
                        <div class="summary-box">
                            <table style="width:100%">
                                <tr>
                                    <td>Subtotal:</td>
                                    <td>PKR <?php echo number_format($sale['total_amount'], 2); ?></td>
                                </tr>
                                <?php if (($sale['discount'] ?? 0) > 0): ?>
                                <tr>
                                    <td>Discount:</td>
                                    <td class="text-danger">-PKR <?php echo number_format($sale['discount'], 2); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr class="fw-bold">
                                    <td>Grand Total:</td>
                                    <td>PKR <?php echo number_format($sale['final_amount'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td>Paid:</td>
                                    <td>PKR <?php echo number_format($sale['paid_amount'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td>Balance:</td>
                                    <td class="<?php echo ($sale['balance'] ?? 0) > 0 ? 'text-danger' : ''; ?>">
                                        PKR <?php echo number_format($sale['balance'] ?? 0, 2); ?>
                                    </td>
                                </tr>
                                <?php if (($sale['total_refund'] ?? 0) > 0): ?>
                                <tr class="refund-row">
                                    <td><strong>Total Refund:</strong></td>
                                    <td class="text-danger fw-bold">-PKR <?php echo number_format($sale['total_refund'], 2); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
