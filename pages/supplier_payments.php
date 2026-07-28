<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Supplier Payments';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $payment = getRow("SELECT id FROM supplier_payments WHERE id = ?", 'i', [$id]);
    if ($payment) {
        modifyData("DELETE FROM supplier_payments WHERE id = ?", 'i', [$id]);
        setFlash('Payment deleted successfully!', 'success');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = (int)$_POST['supplier_id'];
    $payment_date = $_POST['payment_date'];
    $amount = (float)$_POST['amount'];
    $payment_type = $_POST['payment_type'];
    $reference_no = trim($_POST['reference_no']);
    $bank_name = trim($_POST['bank_name']);
    $cheque_no = trim($_POST['cheque_no']);
    $notes = trim($_POST['notes']);

    if (empty($supplier_id)) {
        $message = 'Please select a supplier!';
        $messageType = 'danger';
    } elseif (empty($payment_date)) {
        $message = 'Please select payment date!';
        $messageType = 'danger';
    } elseif ($amount <= 0) {
        $message = 'Amount must be greater than 0!';
        $messageType = 'danger';
    } else {
        $currentDateTime = getCurrentDateTime();
        $payment_no = generateCode('PAY');

        $result = insertData("INSERT INTO supplier_payments (payment_no, supplier_id, payment_date, amount, payment_type, reference_no, bank_name, cheque_no, notes, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            'sisdssssss', [$payment_no, $supplier_id, $payment_date, $amount, $payment_type, $reference_no, $bank_name, $cheque_no, $notes, $currentDateTime]);

        if ($result) {
            setFlash('Payment of ' . formatCurrency($amount) . ' made successfully!', 'success');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $message = 'Error adding payment!';
            $messageType = 'danger';
        }
    }
}

$suppliers = getRows("SELECT s.id, s.supplier_name, s.company_name, s.opening_balance,
    COALESCE(SUM(lp.balance), 0) AS purchase_balance,
    COALESCE((SELECT SUM(sp.amount) FROM supplier_payments sp WHERE sp.supplier_id = s.id), 0) AS payments_made,
    (s.opening_balance + COALESCE(SUM(lp.balance), 0) - COALESCE((SELECT SUM(sp.amount) FROM supplier_payments sp WHERE sp.supplier_id = s.id), 0)) AS current_balance
    FROM local_suppliers s
    LEFT JOIN local_purchases lp ON lp.supplier_id = s.id
    WHERE s.status = 'active'
    GROUP BY s.id
    ORDER BY s.supplier_name");

$payments = getRows("SELECT p.*, s.supplier_name, s.company_name
    FROM supplier_payments p
    LEFT JOIN local_suppliers s ON p.supplier_id = s.id
    ORDER BY p.id DESC");

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
#balanceBox { background: #f8f9fa; border-left: 4px solid #1a2332; padding: 12px 18px; border-radius: 0 6px 6px 0; margin-top: 10px; display: none; }
#balanceBox .bal-label { font-size: 12px; color: #666; }
#balanceBox .bal-amount { font-size: 18px; font-weight: 700; }
</style>

<div class="container-fluid px-0">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Supplier Payments</h5>
        <div class="d-flex gap-2">
            <a href="suppliers_summary.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-chart-bar me-1"></i>Summary</a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#paymentModal">
                <i class="fas fa-plus me-2"></i>Make Payment
            </button>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0" id="paymentTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Payment No</th>
                            <th>Supplier</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($payments as $p): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($p['payment_no']); ?></strong></td>
                                <td>
                                    <a href="supplier_detail.php?id=<?php echo $p['supplier_id']; ?>">
                                        <?php echo htmlspecialchars($p['supplier_name']); ?>
                                    </a>
                                    <?php if ($p['company_name']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($p['company_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d-m-Y', strtotime($p['payment_date'])); ?></td>
                                <td class="text-success"><strong><?php echo formatCurrency($p['amount']); ?></strong></td>
                                <td><span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $p['payment_type'])); ?></span></td>
                                <td><?php echo htmlspecialchars($p['notes'] ?: '-'); ?></td>
                                <td>
                                    <a href="?delete=<?php echo $p['id']; ?>" class="btn btn-sm btn-danger delete-confirm" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Make Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a2332;color:#fff;">
                <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i>Make Supplier Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier *</label>
                            <select class="form-select" id="supplierSelect" name="supplier_id" required>
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"
                                        data-balance="<?php echo $s['current_balance']; ?>"
                                        data-opening="<?php echo $s['opening_balance']; ?>"
                                        data-payments="<?php echo $s['payments_made']; ?>">
                                        <?php echo htmlspecialchars($s['supplier_name']); ?>
                                        <?php if ($s['company_name']): ?> (<?php echo htmlspecialchars($s['company_name']); ?>)<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="balanceBox">
                                <div class="row">
                                    <div class="col-4">
                                        <div class="bal-label">Opening Balance</div>
                                        <div class="bal-amount" id="balOpening">0.00</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="bal-label">Payments Made</div>
                                        <div class="bal-amount text-success" id="balPayments">0.00</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="bal-label">Balance Due</div>
                                        <div class="bal-amount text-danger" id="balCurrent">0.00</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Date *</label>
                            <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Amount (PKR) *</label>
                            <input type="number" step="0.01" class="form-control" id="payAmount" name="amount" min="0.01" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Payment Type *</label>
                            <select class="form-select" id="payType" name="payment_type" required>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                                <option value="online">Online</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Reference No</label>
                            <input type="text" class="form-control" name="reference_no">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3" id="bankDiv" style="display:none">
                            <label class="form-label">Bank Name</label>
                            <input type="text" class="form-control" name="bank_name">
                        </div>
                        <div class="col-md-4 mb-3" id="chequeDiv" style="display:none">
                            <label class="form-label">Cheque No</label>
                            <input type="text" class="form-control" name="cheque_no">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Notes</label>
                            <input type="text" class="form-control" name="notes">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-paper-plane me-2"></i>Submit Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
function fmtNum(v) { return parseFloat(v).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

$(document).ready(function() {
    $('#supplierSelect').on('change', function() {
        var opt = $(this).find('option:selected');
        var box = $('#balanceBox');
        if (!$(this).val()) { box.hide(); return; }
        var opening = parseFloat(opt.data('opening')) || 0;
        var payments = parseFloat(opt.data('payments')) || 0;
        var current  = parseFloat(opt.data('balance')) || 0;
        $('#balOpening').text(fmtNum(opening));
        $('#balPayments').text(fmtNum(payments));
        $('#balCurrent').text(fmtNum(current));
        $('#payAmount').attr('max', current).attr('placeholder', 'Max: ' + fmtNum(current));
        box.show();
    });

    $('#payType').on('change', function() {
        var t = $(this).val();
        $('#bankDiv').toggle(t === 'bank_transfer' || t === 'cheque');
        $('#chequeDiv').toggle(t === 'cheque');
    });

    $('#paymentTable').DataTable({ pageLength: 25, order: [[0, 'desc']], language: { emptyTable: 'No payments found' } });

    $('.delete-confirm').on('click', function(e) {
        if (!confirm('Delete this payment?')) e.preventDefault();
    });
});
</script>

<?php include '../includes/footer.php'; ?>
