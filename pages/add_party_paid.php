<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Pay to Party';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

$partyId = isset($_GET['party_id']) ? (int)$_GET['party_id'] : 0;
$parties = getRows("SELECT id, party_name, party_code, current_balance FROM parties WHERE status = 'active' ORDER BY party_name");

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $party_id = (int)$_POST['party_id'];
    $amount = (float)($_POST['amount'] ?? 0);
    $transaction_date = $_POST['transaction_date'];
    $description = trim($_POST['description'] ?? '');
    $reference_no = trim($_POST['reference_no'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'cash';

    if (empty($party_id)) {
        $message = 'Please select a party!';
        $messageType = 'danger';
    } elseif ($amount <= 0) {
        $message = 'Amount must be greater than 0!';
        $messageType = 'danger';
    } elseif (empty($transaction_date)) {
        $message = 'Please select date!';
        $messageType = 'danger';
    } else {
        $currentDateTime = getCurrentDateTime();
        $txn_no = generateCode('PP');

        $result = insertData("INSERT INTO party_transactions (transaction_no, party_id, type, amount, transaction_date, description, reference_no, payment_method, date_time) VALUES (?,?,?,?,?,?,?,?,?)",
            'sssdsssss', [$txn_no, $party_id, 'paid', $amount, $transaction_date, $description, $reference_no, $payment_method, $currentDateTime]);

        if ($result !== false) {
            modifyData("UPDATE parties SET current_balance = current_balance - ? WHERE id = ?", 'di', [$amount, $party_id]);
            setFlash('Paid ' . formatCurrency($amount) . ' successfully!', 'success');
            header('Location: party_detail.php?id=' . $party_id);
            exit;
        } else {
            $message = 'Error recording payment!';
            $messageType = 'danger';
        }
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header" style="background:#0d6efd;color:#fff;">
                <i class="fas fa-money-bill me-2"></i>Pay to Party
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Select Party *</label>
                        <select class="form-select" name="party_id" id="partySelect" required>
                            <option value="">Select Party</option>
                            <?php foreach ($parties as $pt): ?>
                                <option value="<?php echo $pt['id']; ?>" <?php echo $partyId == $pt['id'] ? 'selected' : ''; ?>
                                    data-balance="<?php echo $pt['current_balance']; ?>">
                                    <?php echo htmlspecialchars($pt['party_name']); ?> (<?php echo htmlspecialchars($pt['party_code']); ?>) - Bal: <?php echo formatCurrency($pt['current_balance']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount (PKR) *</label>
                            <input type="number" step="0.01" class="form-control" name="amount" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date *</label>
                            <input type="date" class="form-control" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Payment details..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Reference No</label>
                            <input type="text" class="form-control" name="reference_no" placeholder="Cheque #, Transfer #">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select" name="payment_method">
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Payment</button>
                        <a href="parties.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
