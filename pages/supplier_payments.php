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
    $payment = getRow("SELECT id, supplier_id, amount, payment_type, bank_id FROM supplier_payments WHERE id = ?", 'i', [$id]);
    if ($payment) {
        // If bank payment, restore bank balance
        if ($payment['payment_type'] === 'bank' && !empty($payment['bank_id'])) {
            updateBankBalance($payment['bank_id'], $payment['amount'], 'in');
        }
        modifyData("DELETE FROM accounts WHERE reference_type = 'Supplier Payment' AND reference_id = ?", 'i', [$id]);
        modifyData("UPDATE local_suppliers SET current_balance = current_balance + ? WHERE id = ?", 'di', [$payment['amount'], $payment['supplier_id']]);
        modifyData("DELETE FROM supplier_payments WHERE id = ?", 'i', [$id]);
        setFlash('Payment deleted successfully!', 'success');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $supplier_id = (int)$_POST['supplier_id'];
    $payment_date = $_POST['payment_date'];
    $amount = (float)$_POST['amount'];
    $payment_type = $_POST['payment_type'];
    $reference_no = trim($_POST['reference_no']);
    $notes = trim($_POST['notes']);
    $bank_id = ($payment_type === 'bank') ? (int)($_POST['bank_id'] ?? 0) : null;
    $bank_name = null;
    if ($bank_id > 0) {
        $bRow = getRow("SELECT bank_name, account_number FROM bank_accounts WHERE id = ?", 'i', [$bank_id]);
        if ($bRow) {
            $bank_name = $bRow['bank_name'] . ' (' . $bRow['account_number'] . ')';
        }
    }

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

        if ($id > 0) {
            // Update existing payment
            $oldPayment = getRow("SELECT supplier_id, amount, payment_type, bank_id FROM supplier_payments WHERE id = ?", 'i', [$id]);
            if ($oldPayment) {
                // Restore old bank balance
                if ($oldPayment['payment_type'] === 'bank' && !empty($oldPayment['bank_id'])) {
                    updateBankBalance($oldPayment['bank_id'], $oldPayment['amount'], 'in');
                }
                // Restore old supplier balance and accounts first
                modifyData("UPDATE local_suppliers SET current_balance = current_balance + ? WHERE id = ?", 'di', [$oldPayment['amount'], $oldPayment['supplier_id']]);
                modifyData("DELETE FROM accounts WHERE reference_type = 'Supplier Payment' AND reference_id = ?", 'i', [$id]);
            }

            modifyData("UPDATE supplier_payments SET supplier_id=?, payment_date=?, amount=?, payment_type=?, bank_id=?, bank_name=?, reference_no=?, notes=?, date_time=? WHERE id=?",
                'isdsissssi', [$supplier_id, $payment_date, $amount, $payment_type, $bank_id, $bank_name, $reference_no, $notes, $currentDateTime, $id]);

            if ($payment_type === 'bank' && $bank_id > 0) {
                updateBankBalance($bank_id, $amount, 'out');
            }

            // Re-insert accounts entry
            $accType = ($payment_type === 'cash') ? 'Cash' : 'Bank';
            $lastEntry = getRow("SELECT balance FROM accounts WHERE account_type = ? ORDER BY id DESC LIMIT 1", 's', [$accType]);
            $lastBalance = $lastEntry ? (float)$lastEntry['balance'] : 0;
            $supplierRow = getRow("SELECT supplier_name FROM local_suppliers WHERE id = ?", 'i', [$supplier_id]);
            $supplierName = $supplierRow ? $supplierRow['supplier_name'] : 'Supplier';
            $desc = 'Payment to supplier - ' . $supplierName . ' (Updated)' . ($bank_name ? " [$bank_name]" : "");
            insertData(
                "INSERT INTO accounts (account_date, account_type, reference_type, reference_id, description, debit, credit, balance, date_time) VALUES (?, ?, 'Supplier Payment', ?, ?, 0, ?, ?, ?)",
                'ssisdds', [$payment_date, $accType, $id, $desc, $amount, $lastBalance - $amount, $currentDateTime]
            );

            // Update new supplier balance
            modifyData("UPDATE local_suppliers SET current_balance = current_balance - ? WHERE id = ?", 'di', [$amount, $supplier_id]);

            setFlash('Payment updated successfully!', 'success');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $payment_no = generateCode('PAY');

            $result = insertData("INSERT INTO supplier_payments (payment_no, supplier_id, payment_date, amount, payment_type, bank_id, bank_name, reference_no, notes, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                'sisdsissss', [$payment_no, $supplier_id, $payment_date, $amount, $payment_type, $bank_id, $bank_name, $reference_no, $notes, $currentDateTime]);

            if ($result) {
                if ($payment_type === 'bank' && $bank_id > 0) {
                    updateBankBalance($bank_id, $amount, 'out');
                }

                // Insert into accounts ledger
                $accType = ($payment_type === 'cash') ? 'Cash' : 'Bank';
                $lastEntry = getRow("SELECT balance FROM accounts WHERE account_type = ? ORDER BY id DESC LIMIT 1", 's', [$accType]);
                $lastBalance = $lastEntry ? (float)$lastEntry['balance'] : 0;
                $supplierRow = getRow("SELECT supplier_name FROM local_suppliers WHERE id = ?", 'i', [$supplier_id]);
                $supplierName = $supplierRow ? $supplierRow['supplier_name'] : 'Supplier';
                $desc = 'Payment to supplier - ' . $supplierName . ' (' . $payment_no . ')' . ($bank_name ? " [$bank_name]" : "");
                insertData(
                    "INSERT INTO accounts (account_date, account_type, reference_type, reference_id, description, debit, credit, balance, date_time) VALUES (?, ?, 'Supplier Payment', ?, ?, 0, ?, ?, ?)",
                    'ssisdds', [$payment_date, $accType, $result, $desc, $amount, $lastBalance - $amount, $currentDateTime]
                );

                // Update supplier current balance
                modifyData("UPDATE local_suppliers SET current_balance = current_balance - ? WHERE id = ?", 'di', [$amount, $supplier_id]);

                setFlash('Payment of ' . formatCurrency($amount) . ' made successfully!', 'success');
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $message = 'Error adding payment!';
                $messageType = 'danger';
            }
        }
    }
}

$bankAccounts = getActiveBankAccounts();

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

$editPayment = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editPayment = getRow("SELECT * FROM supplier_payments WHERE id = ?", 'i', [$editId]);
}

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
                                <td><?php echo formatDate($p['payment_date']); ?></td>
                                <td class="text-success"><strong><?php echo formatCurrency($p['amount']); ?></strong></td>
                                <td>
                                    <?php if ($p['payment_type'] === 'bank'): ?>
                                        <span class="badge bg-info text-dark">
                                            <i class="fas fa-university me-1"></i>Bank<?php echo !empty($p['bank_name']) ? ': ' . htmlspecialchars($p['bank_name']) : ''; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Cash</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($p['notes'] ?: '-'); ?></td>
                                <td>
                                    <a href="?edit=<?php echo $p['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
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
                <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i><span id="modalTitle">Make Supplier Payment</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php if ($editPayment): ?>
                        <input type="hidden" name="id" value="<?php echo $editPayment['id']; ?>">
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier *</label>
                            <select class="form-select" id="supplierSelect" name="supplier_id" required>
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"
                                        data-balance="<?php echo $s['current_balance']; ?>"
                                        data-opening="<?php echo $s['opening_balance']; ?>"
                                        data-payments="<?php echo $s['payments_made']; ?>"
                                        <?php echo ($editPayment && $editPayment['supplier_id'] == $s['id']) ? 'selected' : ''; ?>>
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
                            <input type="date" class="form-control" id="payDate" name="payment_date" value="<?php echo $editPayment ? $editPayment['payment_date'] : date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Amount (PKR) *</label>
                            <input type="number" step="0.01" class="form-control" id="payAmount" name="amount" min="0.01" value="<?php echo $editPayment ? $editPayment['amount'] : ''; ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Payment Type *</label>
                            <select class="form-select" id="payType" name="payment_type" required>
                                <option value="cash" <?php echo ($editPayment && $editPayment['payment_type'] == 'cash') ? 'selected' : ''; ?>>Cash</option>
                                <option value="bank" <?php echo ($editPayment && $editPayment['payment_type'] == 'bank') ? 'selected' : ''; ?>>Bank</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3" id="bankSelectGroup" style="<?php echo ($editPayment && $editPayment['payment_type'] == 'bank') ? '' : 'display:none;'; ?>">
                            <label class="form-label fw-bold"><i class="fas fa-university me-1"></i>Select Bank *</label>
                            <select class="form-select" id="bankSelect" name="bank_id">
                                <option value="">-- Choose Bank Account --</option>
                                <?php foreach ($bankAccounts as $ba): ?>
                                    <option value="<?php echo $ba['id']; ?>" <?php echo ($editPayment && ($editPayment['bank_id'] ?? 0) == $ba['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ba['bank_name'] . ' (' . $ba['account_number'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Reference No</label>
                            <input type="text" class="form-control" name="reference_no" value="<?php echo $editPayment ? htmlspecialchars($editPayment['reference_no']) : ''; ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Notes</label>
                            <input type="text" class="form-control" name="notes" value="<?php echo $editPayment ? htmlspecialchars($editPayment['notes']) : ''; ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-paper-plane me-2"></i><span id="submitBtnText"><?php echo $editPayment ? 'Update Payment' : 'Submit Payment'; ?></span></button>
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
        if ($(this).val() === 'bank') {
            $('#bankSelectGroup').show();
            $('#bankSelect').prop('required', true);
        } else {
            $('#bankSelectGroup').hide();
            $('#bankSelect').prop('required', false);
        }
    });

    $('#paymentTable').DataTable({ pageLength: 25, order: [[0, 'desc']], language: { emptyTable: 'No payments found' } });

    $('.delete-confirm').on('click', function(e) {
        if (!confirm('Delete this payment?')) e.preventDefault();
    });

    // Reset modal to new-payment mode when closed
    $('#paymentModal').on('hidden.bs.modal', function() {
        $('#modalTitle').text('Make Supplier Payment');
        $('#submitBtnText').text('Submit Payment');
        $('#paymentModal input[name="id"]').remove();
        $('#bankSelectGroup').hide();
        $('#bankSelect').prop('required', false);
    });

    <?php if (isset($_GET['edit']) && $editPayment): ?>
        $('#paymentModal').modal('show');
        $('#modalTitle').text('Edit Supplier Payment');
        $('#submitBtnText').text('Update Payment');
        $('#supplierSelect').trigger('change');
        if ($('#payType').val() === 'bank') {
            $('#bankSelectGroup').show();
            $('#bankSelect').prop('required', true);
        }
    <?php endif; ?>
});
</script>

<?php include '../includes/footer.php'; ?>
