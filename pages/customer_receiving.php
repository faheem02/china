<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Customer Payment Receiving';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $receipt = getRow("SELECT id FROM customer_receipts WHERE id = ?", 'i', [$id]);
    if ($receipt) {
        $r = getRow("SELECT customer_id, sale_id, amount FROM customer_receipts WHERE id = ?", 'i', [$id]);
        if ($r) {
            // Restore customer current balance
            modifyData("UPDATE customers SET current_balance = current_balance + ? WHERE id = ?", 'di', [$r['amount'], $r['customer_id']]);
            $sale = getRow("SELECT paid_amount, total_amount, discount FROM sales WHERE id = ?", 'i', [$r['sale_id']]);
            if ($sale) {
                $newPaid = max(0, $sale['paid_amount'] - $r['amount']);
                $newBalance = ($sale['total_amount'] - ($sale['discount'] ?? 0)) - $newPaid;
                $newStatus = $newPaid >= ($sale['total_amount'] - ($sale['discount'] ?? 0)) ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');
                modifyData("UPDATE sales SET paid_amount = ?, balance = ?, payment_status = ? WHERE id = ?", 'ddsi', [$newPaid, $newBalance, $newStatus, $r['sale_id']]);
            }
        }
        modifyData("DELETE FROM customer_receipts WHERE id = ?", 'i', [$id]);
        setFlash('Payment deleted successfully!', 'success');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $editId = (int)$_POST['edit_id'];
    $editCustomerId = (int)$_POST['edit_customer_id'];
    $editDate = $_POST['edit_date'];
    $editAmount = (float)($_POST['edit_amount'] ?? 0);
    $editMethod = $_POST['edit_method'] ?? '';
    $editNotes = trim($_POST['edit_notes'] ?? '');

    $old = getRow("SELECT * FROM customer_receipts WHERE id = ?", 'i', [$editId]);
    if (!$old) {
        setFlash('Payment not found!', 'danger');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($editCustomerId <= 0 || empty($editDate) || $editAmount <= 0) {
        setFlash('Please fill all required fields!', 'danger');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    modifyData("UPDATE customer_receipts SET customer_id = ?, amount = ?, payment_method = ?, payment_date = ?, notes = ? WHERE id = ?",
        'isdssi', [$editCustomerId, $editAmount, $editMethod, $editDate, $editNotes, $editId]);

    setFlash('Payment updated successfully!', 'success');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['customer_id'])) {
    $customer_id = (int)$_POST['customer_id'];
    $payment_date = $_POST['payment_date'];
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_type = $_POST['payment_type'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    if (empty($customer_id)) {
        $message = 'Please select a customer!';
        $messageType = 'danger';
    } elseif (empty($payment_date)) {
        $message = 'Please select payment date!';
        $messageType = 'danger';
    } elseif ($amount <= 0) {
        $message = 'Amount must be greater than 0!';
        $messageType = 'danger';
    } else {
        $currentDateTime = getCurrentDateTime();
        $receiptNo = generateCode('RCT');

        $result = insertData("INSERT INTO customer_receipts (customer_id, receipt_no, amount, payment_method, payment_date, notes, date_time) VALUES (?, ?, ?, ?, ?, ?, ?)",
            'isdssss', [$customer_id, $receiptNo, $amount, $payment_type, $payment_date, $notes, $currentDateTime]);

        if ($result) {
            $customerRow = getRow("SELECT customer_name FROM customers WHERE id = ?", 'i', [$customer_id]);
            $customerName = $customerRow ? $customerRow['customer_name'] : 'Customer';

            if ($payment_type === 'cash') {
                $lastEntry = getRow("SELECT balance FROM accounts WHERE account_type = 'Cash' ORDER BY id DESC LIMIT 1");
                $lastBalance = $lastEntry ? (float)$lastEntry['balance'] : 0;
                insertData(
                    "INSERT INTO accounts (account_date, account_type, reference_type, reference_id, description, debit, credit, balance, date_time) VALUES (?, 'Cash', 'receipt', ?, ?, 0, ?, ?, ?)",
                    'sisdds', [$payment_date, $customer_id, 'Payment received from ' . $customerName . ' (Receipt: ' . $receiptNo . ')', $amount, $lastBalance + $amount, $currentDateTime]
                );
            } else {
                $lastEntry = getRow("SELECT balance FROM accounts WHERE account_type = 'Bank' ORDER BY id DESC LIMIT 1");
                $lastBalance = $lastEntry ? (float)$lastEntry['balance'] : 0;
                $desc = 'Payment received from ' . $customerName . ' (Receipt: ' . $receiptNo . ')';
                insertData(
                    "INSERT INTO accounts (account_date, account_type, reference_type, reference_id, description, debit, credit, balance, date_time) VALUES (?, 'Bank', 'receipt', ?, ?, 0, ?, ?, ?)",
                    'sisdds', [$payment_date, $customer_id, $desc, $amount, $lastBalance + $amount, $currentDateTime]
                );
            }

            // Update customer current balance
            modifyData("UPDATE customers SET current_balance = current_balance - ? WHERE id = ?", 'di', [$amount, $customer_id]);

            setFlash('Payment received! Receipt #' . $receiptNo, 'success');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $message = 'Error recording payment!';
            $messageType = 'danger';
        }
    }
}

$customers = getRows("SELECT c.id, c.customer_name, c.company_name, c.opening_balance,
    COALESCE(SUM(s.balance), 0) AS outstanding_sales,
    COALESCE((SELECT SUM(cr.amount) FROM customer_receipts cr WHERE cr.customer_id = c.id), 0) AS payments_received,
    (c.opening_balance + COALESCE(SUM(s.balance), 0) - COALESCE((SELECT SUM(cr.amount) FROM customer_receipts cr WHERE cr.customer_id = c.id), 0)) AS current_balance
    FROM customers c
    LEFT JOIN sales s ON s.customer_id = c.id AND s.payment_status != 'paid'
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY c.customer_name");

$receipts = getRows("SELECT cr.*, c.customer_name, c.company_name
    FROM customer_receipts cr
    LEFT JOIN customers c ON cr.customer_id = c.id
    ORDER BY cr.id DESC LIMIT 100");

$company = getRow("SELECT * FROM company_settings LIMIT 1");

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
#balanceBox { background: #f8f9fa; border-left: 4px solid #1a2332; padding: 12px 18px; border-radius: 0 6px 6px 0; margin-top: 10px; display: none; }
#balanceBox .bal-label { font-size: 12px; color: #666; }
#balanceBox .bal-amount { font-size: 18px; font-weight: 700; }
</style>

<?php if ($message): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:#1a2332;color:#fff;">
        <span><i class="fas fa-history me-2"></i>Customer Payments</span>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#paymentModal">
                <i class="fas fa-plus me-1"></i>Receive Payment
            </button>
            <button type="button" class="btn btn-danger btn-sm" onclick="printVisible()"><i class="fas fa-print me-1"></i>Print</button>
            <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="paymentTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Receipt No</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th class="text-end">Amount</th>
                        <th>Type</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($receipts as $r): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo htmlspecialchars($r['receipt_no']); ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($r['customer_name']); ?>
                                <?php if ($r['company_name']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($r['company_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatDate($r['payment_date']); ?></td>
                            <td class="text-end text-success"><strong><?php echo formatCurrency($r['amount']); ?></strong></td>
                            <td><span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $r['payment_method'])); ?></span></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-btn" title="Edit"
                                    data-id="<?php echo $r['id']; ?>"
                                    data-customer="<?php echo $r['customer_id']; ?>"
                                    data-date="<?php echo $r['payment_date']; ?>"
                                    data-amount="<?php echo $r['amount']; ?>"
                                    data-method="<?php echo $r['payment_method']; ?>"
                                    data-notes="<?php echo htmlspecialchars($r['notes'] ?? ''); ?>"
                                    onclick="openEdit(this)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete=<?php echo $r['id']; ?>" class="btn btn-sm btn-danger delete-confirm" title="Delete">
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

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a2332;color:#fff;">
                <h5 class="modal-title"><i class="fas fa-hand-holding-usd me-2"></i>Receive Customer Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="paymentForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer *</label>
                            <select class="form-select" id="customerSelect" name="customer_id" required>
                                <option value="">Select Customer</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"
                                        data-balance="<?php echo $c['current_balance']; ?>"
                                        data-opening="<?php echo $c['opening_balance']; ?>"
                                        data-payments="<?php echo $c['payments_received']; ?>">
                                        <?php echo htmlspecialchars($c['customer_name']); ?>
                                        <?php if ($c['company_name']): ?> (<?php echo htmlspecialchars($c['company_name']); ?>)<?php endif; ?>
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
                                        <div class="bal-label">Received</div>
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
                                <option value="bank">Bank</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Reference No</label>
                            <input type="text" class="form-control" name="reference_no">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
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

<!-- Edit Payment Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:#e67e22;color:#fff;">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="edit_id" id="editId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Customer *</label>
                        <select class="form-select" name="edit_customer_id" id="editCustomer" required>
                            <option value="">Select Customer</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['customer_name']); ?><?php if ($c['company_name']): ?> (<?php echo htmlspecialchars($c['company_name']); ?>)<?php endif; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Date *</label>
                            <input type="date" class="form-control" name="edit_date" id="editDate" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount (PKR) *</label>
                            <input type="number" step="0.01" class="form-control" name="edit_amount" id="editAmount" min="0.01" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Type *</label>
                        <select class="form-select" name="edit_method" id="editMethod" required>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <input type="text" class="form-control" name="edit_notes" id="editNotes">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning px-4"><i class="fas fa-save me-2"></i>Update Payment</button>
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
    $('#customerSelect').on('change', function() {
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

    $('#paymentTable').DataTable({ pageLength: 25, order: [[0, 'desc']], language: { emptyTable: 'No payments found' } });

    $('#paymentModal').on('hidden.bs.modal', function() {
        $(this).find('form')[0].reset();
        $('#balanceBox').hide();
    });

    $('#editModal').on('hidden.bs.modal', function() {
        $(this).find('form')[0].reset();
    });
});

function openEdit(btn) {
    $('#editId').val(btn.dataset.id);
    $('#editCustomer').val(btn.dataset.customer);
    $('#editDate').val(btn.dataset.date);
    $('#editAmount').val(btn.dataset.amount);
    $('#editMethod').val(btn.dataset.method);
    $('#editNotes').val(btn.dataset.notes);
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function printVisible() {
    var table = $('#paymentTable').DataTable();
    var visibleRows = table.rows({ search: 'applied' }).nodes();
    var companyName = '<?php echo addslashes($company["company_name"] ?? "ERP System"); ?>';
    var companyAddress = '<?php echo addslashes($company["address"] ?? ""); ?>';
    var companyPhone = '<?php echo addslashes($company["phone"] ?? ""); ?>';

    var totalAmount = 0;
    var rowsHtml = '';
    var sno = 1;

    for (var i = 0; i < visibleRows.length; i++) {
        var cells = visibleRows[i].querySelectorAll('td');
        var receiptNo = cells[1].innerText.trim();
        var customer = cells[2].innerText.trim();
        var date = cells[3].innerText.trim();
        var amountText = cells[4].innerText.trim();
        var amount = parseFloat(amountText.replace(/[^0-9.-]/g, '')) || 0;
        var type = cells[5].innerText.trim();

        totalAmount += amount;
        rowsHtml += '<tr>' +
            '<td>' + sno++ + '</td>' +
            '<td><strong>' + receiptNo + '</strong></td>' +
            '<td>' + customer + '</td>' +
            '<td>' + date + '</td>' +
            '<td style="text-align:right;color:#198754;font-weight:700;">' + amountText + '</td>' +
            '<td>' + type + '</td>' +
        '</tr>';
    }

    var totalRows = visibleRows.length;
    var now = new Date();
    var printed = now.toLocaleDateString('en-GB', {day:'2-digit',month:'2-digit',year:'numeric'}) + ' ' + now.toLocaleTimeString('en-GB', {hour:'2-digit',minute:'2-digit'});

    var html = '<!DOCTYPE html><html><head><meta charset="UTF-8">' +
        '<title>Customer Payments</title>' +
        '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">' +
        '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">' +
        '<style>' +
        '* { font-family: Poppins, sans-serif; }' +
        'body { background: #fff; margin: 0; padding: 0; }' +
        '.action-buttons { position: fixed; top: 0; left: 0; right: 0; z-index: 9999; background: #1a2332; padding: 10px 20px; display: flex; gap: 10px; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }' +
        '.action-buttons .btn { font-size: 14px; }' +
        '.print-wrapper { margin-top: 70px; display: flex; justify-content: center; padding: 20px; }' +
        '.print-a4 { max-width: 1000px; width: 100%; background: #fff; padding: 40px; border: 1px solid #ddd; }' +
        '.print-a4 .inv-header { text-align: center; border-bottom: 3px solid #1a2332; padding-bottom: 15px; margin-bottom: 20px; }' +
        '.print-a4 .inv-header h3 { margin: 0; color: #1a2332; font-weight: 700; }' +
        '.print-a4 .inv-header p { margin: 2px 0 0; color: #666; font-size: 13px; }' +
        '.print-a4 .inv-title { text-align: center; background: #f0f0f0; padding: 8px; border: 1px solid #ccc; margin-bottom: 20px; font-weight: 700; font-size: 14px; color: #1a2332; }' +
        '.print-a4 .inv-meta { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 13px; }' +
        '.print-a4 .inv-meta strong { color: #1a2332; }' +
        '.print-a4 .summary-box { display: flex; gap: 20px; margin-bottom: 25px; }' +
        '.print-a4 .summary-item { flex: 1; background: #f8f9fa; border-left: 4px solid #1a2332; padding: 12px 15px; border-radius: 0 6px 6px 0; }' +
        '.print-a4 .summary-item .label { font-size: 11px; color: #666; text-transform: uppercase; font-weight: 600; }' +
        '.print-a4 .summary-item .amount { font-size: 20px; font-weight: 700; color: #1a2332; margin-top: 4px; }' +
        '.print-a4 .summary-item.success { border-left-color: #198754; } .print-a4 .summary-item.success .amount { color: #198754; }' +
        '.print-a4 table { width: 100%; font-size: 12px; border-collapse: collapse; }' +
        '.print-a4 table thead th { background: #1a2332; color: #fff; padding: 10px 12px; font-weight: 600; }' +
        '.print-a4 table tbody td { padding: 8px 12px; border-bottom: 1px solid #eee; }' +
        '.print-a4 table tbody tr:nth-child(even) { background: #f9f9f9; }' +
        '.print-a4 table tfoot td, .print-a4 table tfoot th { padding: 10px 12px; font-weight: 700; background: #e9ecef; border-top: 2px solid #1a2332; }' +
        '.print-a4 .inv-footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 2px solid #1a2332; font-size: 12px; color: #666; }' +
        '@media print { body { background: #fff !important; margin: 0; padding: 0; } .action-buttons { display: none !important; } .print-wrapper { margin: 0; padding: 10px; } .print-a4 { border: none; padding: 15px; max-width: 100%; } @page { size: A4 landscape; margin: 10mm; } }' +
        '</style></head><body>' +
        '<div class="action-buttons">' +
        '<span class="text-white fw-bold me-2"><i class="fas fa-history me-1"></i>Customer Payments</span>' +
        '<button class="btn btn-light btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>' +
        '<button class="btn btn-success btn-sm" onclick="downloadPDF()"><i class="fas fa-download me-1"></i>Download PDF</button>' +
        '</div>' +
        '<div class="print-wrapper"><div class="print-a4" id="printContent">' +
        '<div class="inv-header"><h3>CUSTOMER PAYMENTS</h3>' +
        '<p>' + companyName + '</p>' +
        (companyAddress ? '<p style="margin:2px 0 0;font-size:12px;">' + companyAddress + '</p>' : '') +
        (companyPhone ? '<p style="margin:2px 0 0;font-size:12px;">Ph: ' + companyPhone + '</p>' : '') +
        '</div>' +
        '<div class="inv-meta"><div><strong>Total Records:</strong> ' + totalRows + '</div>' +
        '<div style="text-align:right"><strong>Printed:</strong> ' + printed + '</div></div>' +
        '<div class="inv-title">CUSTOMER PAYMENTS REPORT</div>' +
        '<div class="summary-box"><div class="summary-item success"><div class="label">Total Amount</div><div class="amount">PKR ' + totalAmount.toLocaleString('en-PK', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</div></div>' +
        '<div class="summary-item"><div class="label">Total Records</div><div class="amount">' + totalRows + '</div></div></div>' +
        '<table><thead><tr><th>#</th><th>Receipt No</th><th>Customer</th><th>Date</th><th style="text-align:right">Amount</th><th>Type</th></tr></thead>' +
        '<tbody>' + rowsHtml + '</tbody>' +
        '<tfoot><tr><th colspan="4" style="text-align:right">TOTAL</th><th style="text-align:right;color:#198754;">PKR ' + totalAmount.toLocaleString('en-PK', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</th><th></th></tr></tfoot>' +
        '</table>' +
        '<div class="inv-footer"><p>Generated on ' + printed + ' | ' + companyName + '</p></div>' +
        '</div></div>' +
        '<script>function downloadPDF(){var o={margin:0.5,filename:"customer_payments.pdf",image:{type:"jpeg",quality:0.98},html2canvas:{scale:2},jsPDF:{unit:"in",format:"a4",orientation:"landscape"}};if(typeof html2pdf!=="undefined"){html2pdf().set(o).from(document.getElementById("printContent")).save()}else{var s=document.createElement("script");s.src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js";s.onload=function(){html2pdf().set(o).from(document.getElementById("printContent")).save()};document.head.appendChild(s)}}' +
        '<\/script></body></html>';

    var w = window.open('', '_blank');
    w.document.write(html);
    w.document.close();
}
</script>

<?php include '../includes/footer.php'; ?>
