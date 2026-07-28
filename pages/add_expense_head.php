<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Add Expense Head';
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
    $category_name = trim($_POST['category_name']);
    $description = trim($_POST['description']);
    $status = $_POST['status'] ?? 'active';

    if (empty($category_name)) {
        $message = 'Please enter expense head name!';
        $messageType = 'danger';
    } else {
        // Check duplicate
        $dupCheck = getRow("SELECT id FROM expense_categories WHERE category_name = ? AND id != ?", 'si', [$category_name, $id]);
        if ($dupCheck) {
            $message = 'Expense head with this name already exists!';
            $messageType = 'danger';
        } else {
            $currentDateTime = getCurrentDateTime();
            if ($id > 0) {
                $result = modifyData("UPDATE expense_categories SET category_name=?, description=?, status=? WHERE id=?",
                    'sssi', [$category_name, $description, $status, $id]);
            } else {
                $result = insertData("INSERT INTO expense_categories (category_name, description, status, date_time) VALUES (?,?,?,?)",
                    'ssss', [$category_name, $description, $status, $currentDateTime]);
            }
            if ($result !== false) {
                setFlash($id > 0 ? 'Expense head updated!' : 'Expense head added!', 'success');
                header('Location: expense_heads.php');
                exit;
            } else {
                $message = 'Error saving expense head!';
                $messageType = 'danger';
            }
        }
    }
}

// Edit data
$editHead = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editHead = getRow("SELECT * FROM expense_categories WHERE id = ?", 'i', [(int)$_GET['edit']]);
    $pageTitle = 'Edit Expense Head';
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header" style="background:#1a2332;color:#fff;">
                <i class="fas fa-tag me-2"></i><?php echo $editHead ? 'Edit Expense Head' : 'Add Expense Head'; ?>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?php if ($editHead): ?>
                        <input type="hidden" name="id" value="<?php echo $editHead['id']; ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Head Name *</label>
                        <input type="text" class="form-control" name="category_name" value="<?php echo $editHead ? htmlspecialchars($editHead['category_name']) : ''; ?>" placeholder="e.g. Electricity, Rent, Petrol" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"><?php echo $editHead ? htmlspecialchars($editHead['description']) : ''; ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active" <?php echo ($editHead && $editHead['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($editHead && $editHead['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i><?php echo $editHead ? 'Update' : 'Save'; ?></button>
                        <a href="expense_heads.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
