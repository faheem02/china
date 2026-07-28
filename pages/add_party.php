<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Add Party';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $party_name = trim($_POST['party_name']);
    $contact_person = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $opening_balance = (float)($_POST['opening_balance'] ?? 0);
    $status = $_POST['status'] ?? 'active';

    if (empty($party_name)) {
        $message = 'Please enter party name!';
        $messageType = 'danger';
    } else {
        $currentDateTime = getCurrentDateTime();
        $party_code = $id > 0 ? null : generateCode('PT');

        if ($id > 0) {
            $old = getRow("SELECT opening_balance FROM parties WHERE id = ?", 'i', [$id]);
            $diff = $opening_balance - (float)$old['opening_balance'];
            $result = modifyData("UPDATE parties SET party_name=?, contact_person=?, phone=?, email=?, address=?, city=?, opening_balance=?, current_balance=current_balance+?, status=? WHERE id=?",
                'ssssssddsi', [$party_name, $contact_person, $phone, $email, $address, $city, $opening_balance, $diff, $status, $id]);
        } else {
            $result = insertData("INSERT INTO parties (party_name, party_code, contact_person, phone, email, address, city, opening_balance, current_balance, status, date_time) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                'ssssssssdds', [$party_name, $party_code, $contact_person, $phone, $email, $address, $city, $opening_balance, $opening_balance, $status, $currentDateTime]);
        }

        if ($result !== false) {
            setFlash($id > 0 ? 'Party updated successfully!' : 'Party added successfully!', 'success');
            header('Location: parties.php');
            exit;
        } else {
            $message = 'Error saving party!';
            $messageType = 'danger';
        }
    }
}

// Edit data
$editParty = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editParty = getRow("SELECT * FROM parties WHERE id = ?", 'i', [(int)$_GET['edit']]);
    $pageTitle = 'Edit Party';
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header" style="background:#1a2332;color:#fff;">
                <i class="fas fa-user-plus me-2"></i><?php echo $editParty ? 'Edit Party' : 'Add New Party'; ?>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?php if ($editParty): ?>
                        <input type="hidden" name="id" value="<?php echo $editParty['id']; ?>">
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Party Name *</label>
                            <input type="text" class="form-control" name="party_name" value="<?php echo $editParty ? htmlspecialchars($editParty['party_name']) : ''; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Person</label>
                            <input type="text" class="form-control" name="contact_person" value="<?php echo $editParty ? htmlspecialchars($editParty['contact_person'] ?? '') : ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" value="<?php echo $editParty ? htmlspecialchars($editParty['phone'] ?? '') : ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo $editParty ? htmlspecialchars($editParty['email'] ?? '') : ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" value="<?php echo $editParty ? htmlspecialchars($editParty['city'] ?? '') : ''; ?>">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"><?php echo $editParty ? htmlspecialchars($editParty['address'] ?? '') : ''; ?></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Opening Balance (PKR)</label>
                            <input type="number" step="0.01" class="form-control" name="opening_balance" value="<?php echo $editParty ? $editParty['opening_balance'] : '0.00'; ?>">
                            <small class="text-muted">Positive = party owes us, Negative = we owe party</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" <?php echo ($editParty && $editParty['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($editParty && $editParty['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i><?php echo $editParty ? 'Update' : 'Save'; ?></button>
                        <a href="parties.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
