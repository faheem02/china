<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Sale Refund';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

$saleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($saleId <= 0) { header('Location: sales.php'); exit; }

$sale = getRow("SELECT s.*, c.customer_name, c.company_name, c.phone as cust_phone
    FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.id = ?", 'i', [$saleId]);
if (!$sale) { header('Location: sales.php'); exit; }

$customerName = $sale['customer_type'] === 'walkin' ? $sale['walkin_name'] : ($sale['customer_name'] ?? '');

$items = getRows("SELECT si.*, fg.product_code, fg.product_name, fg.unit
    FROM sale_items si LEFT JOIN finished_goods fg ON si.product_id = fg.id
    WHERE si.sale_id = ?", 'i', [$saleId]);

$hasRefundCols = true;
$testCol = getRow("SHOW COLUMNS FROM sale_items LIKE 'refund_qty'");
if (!$testCol) { $hasRefundCols = false; }
$testCol2 = getRow("SHOW COLUMNS FROM sales LIKE 'total_refund'");
if (!$testCol2) { $hasRefundCols = false; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$hasRefundCols) {
        $message = 'Refund columns not found in database! Run database_update.sql in phpMyAdmin.';
        $messageType = 'danger';
    } else {
    $refundDate = $_POST['refund_date'];
    $reason = trim($_POST['reason']);
    $refundItems = $_POST['refund'] ?? [];

    if (empty($refundDate)) {
        $message = 'Please select refund date!';
        $messageType = 'danger';
    } else {
        $hasRefund = false;
        foreach ($refundItems as $data) {
            if ((float)($data['qty'] ?? 0) > 0) { $hasRefund = true; break; }
        }

        if (!$hasRefund) {
            $message = 'Enter refund quantity for at least one item!';
            $messageType = 'danger';
        } else {
            $hasError = false;
            $totalRefund = 0;

            foreach ($refundItems as $itemId => $data) {
                $refundQty = (float)($data['qty'] ?? 0);
                if ($refundQty <= 0) continue;

                $item = getRow("SELECT * FROM sale_items WHERE id = ? AND sale_id = ?", 'ii', [$itemId, $saleId]);
                if (!$item) continue;

                $newRefundQty = ($item['refund_qty'] ?? 0) + $refundQty;
                if ($newRefundQty > $item['quantity']) {
                    $message = 'Refund qty exceeds original qty for "' . $item['product_id'] . '"!';
                    $messageType = 'danger';
                    $hasError = true;
                    break;
                }

                $refundAmount = $refundQty * $item['unit_price'];
                $totalRefund += $refundAmount;

                modifyData("UPDATE sale_items SET refund_qty = ?, refund_amount = ? WHERE id = ?",
                    'ddi', [$newRefundQty, ($item['refund_amount'] ?? 0) + $refundAmount, $itemId]);

                modifyData("UPDATE finished_goods SET current_stock = current_stock + ? WHERE id = ?",
                    'di', [$refundQty, $item['product_id']]);
            }

            if (!$hasError) {
                $newTotalRefund = ($sale['total_refund'] ?? 0) + $totalRefund;
                $newFinalAmount = $sale['final_amount'] - $totalRefund;
                modifyData("UPDATE sales SET total_refund = ?, final_amount = ? WHERE id = ?", 'ddi', [$newTotalRefund, $newFinalAmount, $saleId]);

                if (in_array($sale['payment_method'], ['cash', 'bank'])) {
                    $expenseNo = generateCode('EXP');
                    insertData("INSERT INTO expenses (expense_no, expense_date, expense_category, description, amount, paid_amount, balance, payment_status, status, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        'ssssdddsss', [$expenseNo, $refundDate, 'Sales Refund', 'Refund for ' . $sale['sale_no'] . ' - ' . $reason, $totalRefund, $totalRefund, 0, 'paid', 'active', getCurrentDateTime()]);
                } else {
                    $newBalance = max(0, $sale['balance'] - $totalRefund);
                    $paymentStatus = $newBalance <= 0 ? 'paid' : ($sale['paid_amount'] > 0 ? 'partial' : 'unpaid');
                    modifyData("UPDATE sales SET balance = ?, payment_status = ?, date_time = ? WHERE id = ?",
                        'dssi', [$newBalance, $paymentStatus, getCurrentDateTime(), $saleId]);
                }

                setFlash('Refund of PKR ' . number_format($totalRefund, 2) . ' processed!', 'success');
                header('Location: sales.php');
                exit;
            }
        }
    }
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center" style="background:#dc3545;color:#fff;">
                <span><i class="fas fa-undo me-2"></i>Refund — <?php echo htmlspecialchars($sale['sale_no']); ?></span>
                <a href="sales.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <small class="text-muted">Customer</small><br>
                        <strong><?php echo htmlspecialchars($customerName ?: 'Walk-in'); ?></strong>
                        <span class="badge bg-<?php echo in_array($sale['payment_method'], ['cash', 'bank']) ? 'success' : 'primary'; ?>"><?php echo ucfirst($sale['payment_method']); ?></span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Invoice Total</small><br>
                        <strong>PKR <?php echo number_format($sale['final_amount'], 2); ?></strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Already Refunded</small><br>
                        <strong class="text-danger">PKR <?php echo number_format($sale['total_refund'] ?? 0, 2); ?></strong>
                    </div>
                </div>

                <form method="POST">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Refund Date</label>
                            <input type="date" class="form-control" name="refund_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Reason</label>
                            <input type="text" class="form-control" name="reason" placeholder="Enter reason..." required>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead style="background:#1a2332;color:#fff;">
                                <tr>
                                    <th>Product</th>
                                    <th class="text-center" style="width:100px">Sold Qty</th>
                                    <th class="text-center" style="width:100px">Refund Qty</th>
                                    <th class="text-right" style="width:120px">Unit Price</th>
                                    <th class="text-center" style="width:80px">Action</th>
                                    <th class="text-right" style="width:130px">Refund Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item):
                                    $already = $item['refund_qty'] ?? 0;
                                    $max = $item['quantity'] - $already;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($item['product_code']); ?></small>
                                    </td>
                                    <td class="text-center"><?php echo number_format($item['quantity'], 2); ?></td>
                                    <td>
                                        <?php if ($max > 0): ?>
                                            <input type="number" name="refund[<?php echo $item['id']; ?>][qty]"
                                                   class="form-control form-control-sm text-center refund-qty"
                                                   min="0" max="<?php echo $max; ?>" step="any" value="0"
                                                   data-price="<?php echo $item['unit_price']; ?>"
                                                   data-id="<?php echo $item['id']; ?>"
                                                   oninput="calc()">
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Fully Refunded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right"><?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td class="text-center">
                                        <?php if ($max > 0): ?>
                                            <button type="button" class="btn btn-outline-danger btn-sm"
                                                    onclick="refundAll(<?php echo $item['id']; ?>, <?php echo $max; ?>)">
                                                All
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right fw-bold text-danger" id="amt_<?php echo $item['id']; ?>">0.00</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 p-3" style="background:#fff3cd;border-radius:8px;">
                        <div>
                            <span class="fw-bold">Total Refund: </span>
                            <span class="fs-4 fw-bold text-danger" id="totalDisplay">PKR 0.00</span>
                            <input type="hidden" name="total_refund" id="totalInput" value="0">
                        </div>
                        <button type="submit" class="btn btn-danger btn-lg px-5">
                            <i class="fas fa-undo me-2"></i>Process Refund
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function refundAll(itemId, max) {
    var input = document.querySelector('[data-id="' + itemId + '"]');
    if (input) {
        input.value = max;
        input.dispatchEvent(new Event('input'));
    }
}
function calc() {
    var total = 0;
    var inputs = document.querySelectorAll('.refund-qty');
    for (var i = 0; i < inputs.length; i++) {
        var q = parseFloat(inputs[i].value) || 0;
        var p = parseFloat(inputs[i].getAttribute('data-price')) || 0;
        var id = inputs[i].getAttribute('data-id');
        var amt = q * p;
        var el = document.getElementById('amt_' + id);
        if (el) el.textContent = amt.toFixed(2);
        total += amt;
    }
    var td = document.getElementById('totalDisplay');
    var ti = document.getElementById('totalInput');
    if (td) td.textContent = 'PKR ' + total.toFixed(2);
    if (ti) ti.value = total;
}
</script>

<?php include '../includes/footer.php'; ?>
