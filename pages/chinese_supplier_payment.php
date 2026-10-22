<?php
/**
 * Chinese Supplier Payments Management
 * Manufacturing ERP System
 */

// Include database connection
require_once '../includes/database.php';

// Require login
requireLogin();

$pageTitle = 'Chinese Supplier Payments';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

// Get current exchange rate
$sql = "SELECT exchange_rate FROM currencies WHERE currency_code = 'CNY'";
$rateRow = getRow($sql);
$defaultRate = $rateRow ? $rateRow['exchange_rate'] : 40.5000;

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Get payment details before deleting
    $sql = "SELECT supplier_id, amount_cny, amount_pkr, payment_type, bank_id FROM chinese_supplier_payments WHERE id = ?";
    $payment = getRow($sql, 'i', [$id]);
    
    if ($payment) {
        if ($payment['payment_type'] === 'bank' && !empty($payment['bank_id'])) {
            updateBankBalance($payment['bank_id'], $payment['amount_pkr'], 'in');
        }
        // Restore Chinese supplier current balance
        modifyData("UPDATE chinese_suppliers SET current_balance = current_balance + ? WHERE id = ?", 'di', [$payment['amount_cny'], $payment['supplier_id']]);
        // Delete from accounts first
        modifyData("DELETE FROM accounts WHERE reference_type = 'Chinese Payment' AND reference_id = ?", 'i', [$id]);
        // Delete payment
        $sql = "DELETE FROM chinese_supplier_payments WHERE id = ?";
        $result = modifyData($sql, 'i', [$id]);
        
        if ($result !== false) {
            setFlash('Payment deleted successfully!', 'success');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $message = 'Error deleting payment!';
            $messageType = 'danger';
        }
    }
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $supplier_id = (int)$_POST['supplier_id'];
    $payment_date = $_POST['payment_date'];
    $amount_cny = (float)$_POST['amount_cny'];
    $exchange_rate = (float)$_POST['exchange_rate'];
    $payment_type = $_POST['payment_type'];
    $reference_no = trim($_POST['reference_no']);
    $notes = trim($_POST['notes']);
    $status = $_POST['status'];
    $bank_id = ($payment_type === 'bank') ? (int)($_POST['bank_id'] ?? 0) : null;
    $bank_name = null;
    if ($bank_id > 0) {
        $bRow = getRow("SELECT bank_name, account_number FROM bank_accounts WHERE id = ?", 'i', [$bank_id]);
        if ($bRow) {
            $bank_name = $bRow['bank_name'] . ' (' . $bRow['account_number'] . ')';
        }
    }
    
    // Calculate PKR amount
    $amount_pkr = $amount_cny * $exchange_rate;
    
    // Validation
    if (empty($supplier_id)) {
        $message = 'Please select a supplier!';
        $messageType = 'danger';
    } elseif (empty($payment_date)) {
        $message = 'Please select payment date!';
        $messageType = 'danger';
    } elseif ($amount_cny <= 0) {
        $message = 'Amount must be greater than 0!';
        $messageType = 'danger';
    } elseif ($exchange_rate <= 0) {
        $message = 'Exchange rate must be greater than 0!';
        $messageType = 'danger';
    } else {
        $currentDateTime = getCurrentDateTime();
        $payment_no = generateCode('CPAY');
        
        if ($id > 0) {
            // Get old payment details
            $sql = "SELECT supplier_id, amount_cny, amount_pkr, payment_type, bank_id FROM chinese_supplier_payments WHERE id = ?";
            $oldPayment = getRow($sql, 'i', [$id]);
            if ($oldPayment) {
                if ($oldPayment['payment_type'] === 'bank' && !empty($oldPayment['bank_id'])) {
                    updateBankBalance($oldPayment['bank_id'], $oldPayment['amount_pkr'], 'in');
                }
                modifyData("UPDATE chinese_suppliers SET current_balance = current_balance + ? WHERE id = ?", 'di', [(float)$oldPayment['amount_cny'], (int)$oldPayment['supplier_id']]);
                modifyData("DELETE FROM accounts WHERE reference_type = 'Chinese Payment' AND reference_id = ?", 'i', [$id]);
            }
            
            // Update
            $sql = "UPDATE chinese_supplier_payments SET 
                    supplier_id = ?,
                    payment_date = ?,
                    amount_cny = ?,
                    amount_pkr = ?,
                    exchange_rate = ?,
                    payment_type = ?,
                    bank_id = ?,
                    bank_name = ?,
                    reference_no = ?,
                    notes = ?,
                    status = ?,
                    date_time = ?
                    WHERE id = ?";
            
            $params = [
                $supplier_id,
                $payment_date,
                $amount_cny,
                $amount_pkr,
                $exchange_rate,
                $payment_type,
                $bank_id,
                $bank_name,
                $reference_no,
                $notes,
                $status,
                $currentDateTime,
                $id
            ];
            
            $result = modifyData($sql, 'isddssisssssi', $params);
            
            if ($result !== false) {
                if ($payment_type === 'bank' && $bank_id > 0) {
                    updateBankBalance($bank_id, $amount_pkr, 'out');
                }

                // Re-insert into accounts ledger
                $accType = ($payment_type === 'cash') ? 'Cash' : 'Bank';
                $lastEntry = getRow("SELECT balance FROM accounts WHERE account_type = ? ORDER BY id DESC LIMIT 1", 's', [$accType]);
                $lastBalance = $lastEntry ? (float)$lastEntry['balance'] : 0;
                $supplierRow = getRow("SELECT supplier_name FROM chinese_suppliers WHERE id = ?", 'i', [$supplier_id]);
                $supplierName = $supplierRow ? $supplierRow['supplier_name'] : 'Chinese Supplier';
                $desc = 'Payment to Chinese supplier - ' . $supplierName . ' (CNY ' . number_format($amount_cny, 2) . ' @ ' . number_format($exchange_rate, 4) . ') [Updated]' . ($bank_name ? " [$bank_name]" : "");
                insertData(
                    "INSERT INTO accounts (account_date, account_type, reference_type, reference_id, description, debit, credit, balance, date_time) VALUES (?, ?, 'Chinese Payment', ?, ?, 0, ?, ?, ?)",
                    'ssisdds', [$payment_date, $accType, $id, $desc, $amount_pkr, $lastBalance - $amount_pkr, $currentDateTime]
                );

                // Update Chinese supplier current balance (in CNY)
                modifyData("UPDATE chinese_suppliers SET current_balance = current_balance - ? WHERE id = ?", 'di', [$amount_cny, $supplier_id]);

                setFlash('Payment updated successfully!', 'success');
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $message = 'Error updating payment!';
                $messageType = 'danger';
            }
        } else {
            // Insert
            $sql = "INSERT INTO chinese_supplier_payments (
                    payment_no, supplier_id, payment_date, amount_cny, amount_pkr,
                    exchange_rate, payment_type, bank_id, bank_name, reference_no,
                    notes, status, date_time
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $payment_no,
                $supplier_id,
                $payment_date,
                $amount_cny,
                $amount_pkr,
                $exchange_rate,
                $payment_type,
                $bank_id,
                $bank_name,
                $reference_no,
                $notes,
                $status,
                $currentDateTime
            ];
            
            $result = insertData($sql, 'sisdddssissss', $params);
            
            if ($result) {
                if ($payment_type === 'bank' && $bank_id > 0) {
                    updateBankBalance($bank_id, $amount_pkr, 'out');
                }

                // Insert into accounts ledger
                $accType = ($payment_type === 'cash') ? 'Cash' : 'Bank';
                $lastEntry = getRow("SELECT balance FROM accounts WHERE account_type = ? ORDER BY id DESC LIMIT 1", 's', [$accType]);
                $lastBalance = $lastEntry ? (float)$lastEntry['balance'] : 0;
                $supplierRow = getRow("SELECT supplier_name FROM chinese_suppliers WHERE id = ?", 'i', [$supplier_id]);
                $supplierName = $supplierRow ? $supplierRow['supplier_name'] : 'Chinese Supplier';
                $desc = 'Payment to Chinese supplier - ' . $supplierName . ' (CNY ' . number_format($amount_cny, 2) . ' @ ' . number_format($exchange_rate, 4) . ') [' . $payment_no . ']' . ($bank_name ? " [$bank_name]" : "");
                insertData(
                    "INSERT INTO accounts (account_date, account_type, reference_type, reference_id, description, debit, credit, balance, date_time) VALUES (?, ?, 'Chinese Payment', ?, ?, 0, ?, ?, ?)",
                    'ssisdds', [$payment_date, $accType, $result, $desc, $amount_pkr, $lastBalance - $amount_pkr, $currentDateTime]
                );

                // Update Chinese supplier current balance (in CNY)
                modifyData("UPDATE chinese_suppliers SET current_balance = current_balance - ? WHERE id = ?", 'di', [$amount_cny, $supplier_id]);

                setFlash('Payment of ¥ ' . number_format($amount_cny, 2) . ' made successfully!', 'success');
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

// Get all payments with supplier details
$sql = "SELECT p.*, s.supplier_name, s.company_name, c.currency_code, c.symbol 
        FROM chinese_supplier_payments p 
        LEFT JOIN chinese_suppliers s ON p.supplier_id = s.id 
        LEFT JOIN currencies c ON s.currency_id = c.id 
        ORDER BY p.id DESC";
$payments = getRows($sql);

// Get all suppliers for dropdown
$suppliers = getRows("SELECT s.*, c.currency_code, c.symbol, c.exchange_rate,
    COALESCE(SUM(ip.balance_cny), 0) AS purchase_balance_cny,
    COALESCE((SELECT SUM(cp.amount_cny) FROM chinese_supplier_payments cp WHERE cp.supplier_id = s.id), 0) AS payments_made_cny,
    (s.opening_balance + COALESCE(SUM(ip.balance_cny), 0) - COALESCE((SELECT SUM(cp.amount_cny) FROM chinese_supplier_payments cp WHERE cp.supplier_id = s.id), 0)) AS current_balance
    FROM chinese_suppliers s
    LEFT JOIN currencies c ON s.currency_id = c.id
    LEFT JOIN import_purchases ip ON ip.supplier_id = s.id
    WHERE s.status = 'active'
    GROUP BY s.id
    ORDER BY s.supplier_name");

// Get single payment for edit
$editPayment = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $sql = "SELECT * FROM chinese_supplier_payments WHERE id = ?";
    $editPayment = getRow($sql, 'i', [$editId]);
}

// Include header
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#paymentModal">
            <i class="fas fa-plus me-2"></i>New Payment (CNY)
        </button>
        <a href="chinese_suppliers_summary.php" class="btn btn-info text-white">
            <i class="fas fa-users me-2"></i>Chinese Suppliers Summary
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list me-2"></i>Chinese Supplier Payments List
                <span class="ms-2 badge bg-warning text-dark">Amounts in CNY</span></span>
                <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0" id="paymentTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Payment No</th>
                                <th>Supplier</th>
                                <th>Date</th>
                                <th>Amount (CNY)</th>
                                <th>Amount (PKR)</th>
                                <th>Rate</th>
                                <th>Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($payments) > 0): ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($payment['payment_no']); ?></strong></td>
                                        <td>
                                            <a href="chinese_supplier_detail.php?id=<?php echo $payment['supplier_id']; ?>" class="text-decoration-none d-block text-truncate" style="max-width:180px;" title="<?php echo htmlspecialchars($payment['supplier_name']); ?>">
                                                <?php echo htmlspecialchars($payment['supplier_name']); ?>
                                            </a>
                                            <?php if ($payment['company_name']): ?>
                                                <small class="text-muted d-block text-truncate" style="max-width:180px;" title="<?php echo htmlspecialchars($payment['company_name']); ?>"><?php echo htmlspecialchars($payment['company_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-nowrap"><?php echo formatDate($payment['payment_date']); ?></td>
                                        <td class="text-nowrap text-success fw-bold">¥ <?php echo number_format($payment['amount_cny'], 2); ?></td>
                                        <td class="text-nowrap">PKR <?php echo number_format($payment['amount_pkr'], 2); ?></td>
                                        <td class="text-nowrap"><?php echo number_format($payment['exchange_rate'], 4); ?></td>
                                        <td>
                                            <?php if ($payment['payment_type'] === 'bank'): ?>
                                                <span class="badge bg-info text-dark">
                                                    <i class="fas fa-university me-1"></i>Bank<?php echo !empty($payment['bank_name']) ? ': ' . htmlspecialchars($payment['bank_name']) : ''; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Cash</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-inline-flex gap-1">
                                                <a href="?edit=<?php echo $payment['id']; ?>" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#paymentModal" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?delete=<?php echo $payment['id']; ?>" class="btn btn-sm btn-danger delete-confirm" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <a href="chinese_payment_receipt.php?id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-info" target="_blank" title="Print">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-primary">
                            <tr>
                                <th colspan="4" class="text-end">Total</th>
                                <th class="text-nowrap">¥ <?php echo number_format(array_sum(array_column($payments, 'amount_cny')), 2); ?></th>
                                <th class="text-nowrap">PKR <?php echo number_format(array_sum(array_column($payments, 'amount_pkr')), 2); ?></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentModalLabel">
                    <i class="fas fa-money-bill-wave me-2"></i>
                    <?php echo $editPayment ? 'Edit Payment (CNY)' : 'New Chinese Supplier Payment (CNY)'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" onsubmit="return validatePaymentForm()">
                <div class="modal-body">
                    <?php if ($editPayment): ?>
                        <input type="hidden" name="id" value="<?php echo $editPayment['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="supplier_id" class="form-label">Chinese Supplier *</label>
                            <select class="form-select" id="supplier_id" name="supplier_id" required>
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>" 
                                        <?php echo ($editPayment && $editPayment['supplier_id'] == $supplier['id']) ? 'selected' : ''; ?>
                                        data-rate="<?php echo $supplier['exchange_rate']; ?>"
                                        data-balance="<?php echo $supplier['current_balance']; ?>"
                                        data-symbol="<?php echo $supplier['symbol']; ?>">
                                        <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                        <?php if ($supplier['company_name']): ?>
                                            (<?php echo htmlspecialchars($supplier['company_name']); ?>)
                                        <?php endif; ?>
                                        - Balance: <?php echo $supplier['symbol']; ?> <?php echo formatCurrency($supplier['current_balance']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="payment_date" class="form-label">Payment Date *</label>
                            <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                   value="<?php echo $editPayment ? $editPayment['payment_date'] : date('Y-m-d'); ?>" 
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="amount_cny" class="form-label">Amount (CNY) *</label>
                            <input type="number" step="0.01" class="form-control" id="amount_cny" name="amount_cny" 
                                   value="<?php echo $editPayment ? $editPayment['amount_cny'] : '0.00'; ?>" 
                                   min="0.01" required onchange="calculatePKR()" onkeyup="calculatePKR()">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="exchange_rate" class="form-label">Exchange Rate *</label>
                            <input type="number" step="0.0001" class="form-control" id="exchange_rate" name="exchange_rate" 
                                   value="<?php echo $editPayment ? $editPayment['exchange_rate'] : $defaultRate; ?>" 
                                   min="0.0001" required onchange="calculatePKR()" onkeyup="calculatePKR()">
                            <small class="text-muted">1 CNY = ? PKR</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="amount_pkr" class="form-label">Amount (PKR) - Auto Calculated</label>
                            <input type="text" class="form-control bg-light" id="amount_pkr" name="amount_pkr" 
                                   value="<?php echo $editPayment ? formatCurrency($editPayment['amount_pkr']) : '0.00'; ?>" 
                                   readonly>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="payment_type" class="form-label">Payment Type *</label>
                            <select class="form-select" id="payment_type" name="payment_type" required>
                                <option value="cash" <?php echo ($editPayment && $editPayment['payment_type'] == 'cash') ? 'selected' : ''; ?>>Cash</option>
                                <option value="bank" <?php echo ($editPayment && $editPayment['payment_type'] == 'bank') ? 'selected' : ''; ?>>Bank</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3" id="bankSelectGroup" style="<?php echo ($editPayment && $editPayment['payment_type'] == 'bank') ? '' : 'display:none;'; ?>">
                            <label for="bank_id" class="form-label fw-bold"><i class="fas fa-university me-1"></i>Select Bank *</label>
                            <select class="form-select" id="bank_id" name="bank_id">
                                <option value="">-- Choose Bank Account --</option>
                                <?php foreach ($bankAccounts as $ba): ?>
                                    <option value="<?php echo $ba['id']; ?>" <?php echo ($editPayment && ($editPayment['bank_id'] ?? 0) == $ba['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ba['bank_name'] . ' (' . $ba['account_number'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="reference_no" class="form-label">Reference No</label>
                            <input type="text" class="form-control" id="reference_no" name="reference_no" 
                                   value="<?php echo $editPayment ? htmlspecialchars($editPayment['reference_no']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="pending" <?php echo ($editPayment && $editPayment['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo ($editPayment && $editPayment['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo ($editPayment && $editPayment['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"><?php echo $editPayment ? htmlspecialchars($editPayment['notes']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Currency Info Alert -->
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Payment will reduce the supplier's outstanding balance in CNY. 
                        The PKR amount is calculated automatically using the exchange rate.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i><?php echo $editPayment ? 'Update Payment' : 'Make Payment'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Calculate PKR amount
function calculatePKR() {
    var amountCNY = parseFloat(document.getElementById('amount_cny').value) || 0;
    var rate = parseFloat(document.getElementById('exchange_rate').value) || 0;
    var amountPKR = amountCNY * rate;
    document.getElementById('amount_pkr').value = amountPKR.toFixed(2);
}

// Auto-fill exchange rate when supplier is selected
$(document).ready(function() {
    $('#supplier_id').on('change', function() {
        var selected = $(this).find('option:selected');
        var rate = selected.data('rate');
        var symbol = selected.data('symbol');
        var balance = selected.data('balance');
        
        if (rate) {
            $('#exchange_rate').val(rate);
            calculatePKR();
        }
        
        if (balance !== undefined) {
            $('#amount_cny').attr('max', balance);
            $('#amount_cny').attr('placeholder', 'Max: ' + symbol + ' ' + parseFloat(balance).toFixed(2));
        }
    });

    $('#payment_type').on('change', function() {
        if ($(this).val() === 'bank') {
            $('#bankSelectGroup').show();
            $('#bank_id').prop('required', true);
        } else {
            $('#bankSelectGroup').hide();
            $('#bank_id').prop('required', false);
        }
    });

    $('#paymentModal').on('hidden.bs.modal', function() {
        $('#bankSelectGroup').hide();
        $('#bank_id').prop('required', false);
    });
    
    <?php if (isset($_GET['edit']) && $editPayment): ?>
        $('#paymentModal').modal('show');
        if ($('#payment_type').val() === 'bank') {
            $('#bankSelectGroup').show();
            $('#bank_id').prop('required', true);
        }
    <?php endif; ?>
});

// Form validation
function validatePaymentForm() {
    var supplier = document.getElementById('supplier_id').value;
    var date = document.getElementById('payment_date').value;
    var amountCNY = parseFloat(document.getElementById('amount_cny').value);
    var rate = parseFloat(document.getElementById('exchange_rate').value);
    var paymentType = document.getElementById('payment_type').value;
    
    if (supplier === '') {
        alert('Please select a supplier.');
        document.getElementById('supplier_id').focus();
        return false;
    }
    
    if (date === '') {
        alert('Please select payment date.');
        document.getElementById('payment_date').focus();
        return false;
    }
    
    if (isNaN(amountCNY) || amountCNY <= 0) {
        alert('Please enter a valid amount in CNY greater than 0.');
        document.getElementById('amount_cny').focus();
        return false;
    }
    
    if (isNaN(rate) || rate <= 0) {
        alert('Please enter a valid exchange rate.');
        document.getElementById('exchange_rate').focus();
        return false;
    }
    
    if (paymentType === '') {
        alert('Please select payment type.');
        document.getElementById('payment_type').focus();
        return false;
    }
    
    <?php if (!$editPayment): ?>
        // Check if amount exceeds balance
        var selected = $('#supplier_id').find('option:selected');
        var balance = parseFloat(selected.data('balance'));
        if (!isNaN(balance) && amountCNY > balance) {
            var symbol = selected.data('symbol') || '¥';
            if (!confirm('This payment (' + symbol + ' ' + amountCNY.toFixed(2) + ') exceeds the supplier\'s current balance (' + symbol + ' ' + balance.toFixed(2) + '). Do you want to continue?')) {
                return false;
            }
        }
    <?php endif; ?>
    
    return true;
}
</script>

<!-- Include DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#paymentTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]],
"language": {
    "search": "Search Payments:",
    "lengthMenu": "Show _MENU_ entries",
    "info": "Showing _START_ to _END_ of _TOTAL_ payments",
    "emptyTable": "No payments found"
}
    });
});
</script>

<?php
include '../includes/footer.php';
?>