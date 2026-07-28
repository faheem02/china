<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Add Bank';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

$editBank = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $sql = "SELECT * FROM banks WHERE id = ?";
    $editBank = getRow($sql, 'i', [$editId]);

    if ($editBank) {
        $pageTitle = 'Edit Bank';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $bank_name = trim($_POST['bank_name']);
    $account_number = trim($_POST['account_number']);
    $bank_address = trim($_POST['bank_address']);
    $account_type = $_POST['account_type'];
    $opening_balance = (float)$_POST['opening_balance'];
    $notes = trim($_POST['notes']);

    if (empty($bank_name)) {
        $message = 'Bank name is required!';
        $messageType = 'danger';
    } else {
        if ($id > 0) {
            $oldBank = getRow("SELECT opening_balance, current_balance FROM banks WHERE id = ?", 'i', [$id]);

            if ($oldBank) {
                $old_opening = (float)$oldBank['opening_balance'];
                $old_current = (float)$oldBank['current_balance'];
                $new_current = $opening_balance + ($old_current - $old_opening);

                $sql = "UPDATE banks SET bank_name = ?, account_number = ?, bank_address = ?, account_type = ?, opening_balance = ?, current_balance = ?, notes = ? WHERE id = ?";
                $result = modifyData($sql, 'ssssddsi', [$bank_name, $account_number, $bank_address, $account_type, $opening_balance, $new_current, $notes, $id]);
            } else {
                $result = false;
            }
        } else {
            $sql = "INSERT INTO banks (bank_name, account_number, bank_address, account_type, opening_balance, current_balance, notes, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $result = insertData($sql, 'ssssddss', [$bank_name, $account_number, $bank_address, $account_type, $opening_balance, $opening_balance, $notes, getCurrentDateTime()]);
        }

        if ($result !== false) {
            if ($id > 0) {
                setFlash('Bank updated successfully!', 'success');
            } else {
                setFlash('Bank added successfully!', 'success');
            }
            header('Location: banks.php');
            exit;
        } else {
            $message = $id > 0 ? 'Error updating bank!' : 'Error adding bank!';
            $messageType = 'danger';
        }
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

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

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #1a2332; color: #fff;">
                <span><i class="fas fa-<?php echo $editBank ? 'edit' : 'plus'; ?> me-2"></i><?php echo $editBank ? 'Edit Bank' : 'Add New Bank'; ?></span>
                <a href="banks.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php if ($editBank): ?>
                        <input type="hidden" name="id" value="<?php echo $editBank['id']; ?>">
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="bank_name" class="form-label">Bank Name *</label>
                            <input type="text" class="form-control" id="bank_name" name="bank_name"
                                   value="<?php echo $editBank ? htmlspecialchars($editBank['bank_name']) : ''; ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="account_number" class="form-label">Account Number</label>
                            <input type="text" class="form-control" id="account_number" name="account_number"
                                   value="<?php echo $editBank ? htmlspecialchars($editBank['account_number']) : ''; ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="bank_address" class="form-label">Bank Address</label>
                            <input type="text" class="form-control" id="bank_address" name="bank_address"
                                   value="<?php echo $editBank ? htmlspecialchars($editBank['bank_address']) : ''; ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="account_type" class="form-label">Account Type</label>
                            <select class="form-select" id="account_type" name="account_type">
                                <option value="current" <?php echo ($editBank && $editBank['account_type'] == 'current') ? 'selected' : ''; ?>>Current</option>
                                <option value="savings" <?php echo ($editBank && $editBank['account_type'] == 'savings') ? 'selected' : ''; ?>>Savings</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="opening_balance" class="form-label">Opening Balance (PKR)</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <input type="number" step="0.01" class="form-control" id="opening_balance" name="opening_balance"
                                       value="<?php echo $editBank ? number_format($editBank['opening_balance'], 2, '.', '') : '0.00'; ?>">
                            </div>
                        </div>

                        <?php if ($editBank): ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Current Balance (PKR)</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <input type="text" class="form-control" value="<?php echo number_format($editBank['current_balance'], 2); ?>" readonly>
                            </div>
                            <small class="text-muted">Adjusted automatically based on opening balance change</small>
                        </div>
                        <?php endif; ?>

                        <div class="col-md-12 mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo $editBank ? htmlspecialchars($editBank['notes']) : ''; ?></textarea>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="banks.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i><?php echo $editBank ? 'Update Bank' : 'Save Bank'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
