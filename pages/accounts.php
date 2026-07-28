<?php
/**
 * Accounts / General Ledger
 * Manufacturing ERP System
 */

require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Accounts Ledger';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

// Handle Add Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_entry'])) {
    $account_date = $_POST['account_date'];
    $account_type = trim($_POST['account_type']);
    $reference_type = trim($_POST['reference_type']);
    $reference_id = isset($_POST['reference_id']) && $_POST['reference_id'] ? (int)$_POST['reference_id'] : null;
    $description = trim($_POST['description']);
    $debit = (float)$_POST['debit'];
    $credit = (float)$_POST['credit'];
    
    if (empty($account_date)) {
        $message = 'Please select date!';
        $messageType = 'danger';
    } elseif (empty($account_type)) {
        $message = 'Please enter account type!';
        $messageType = 'danger';
    } elseif ($debit <= 0 && $credit <= 0) {
        $message = 'Either debit or credit must be greater than 0!';
        $messageType = 'danger';
    } else {
        // Get last balance
        $lastEntry = getRow("SELECT balance FROM accounts ORDER BY id DESC LIMIT 1");
        $lastBalance = $lastEntry ? (float)$lastEntry['balance'] : 0;
        $newBalance = $lastBalance + $debit - $credit;
        
        $sql = "INSERT INTO accounts (account_date, account_type, reference_type, reference_id, description, debit, credit, balance, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $result = insertData($sql, 'ssssdddds', [$account_date, $account_type, $reference_type, $reference_id, $description, $debit, $credit, $newBalance, getCurrentDateTime()]);
        
        if ($result !== false) {
            setFlash('Journal entry added successfully!', 'success');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $message = 'Error adding entry!';
            $messageType = 'danger';
        }
    }
}

// Get all entries
$sql = "SELECT * FROM accounts ORDER BY id DESC";
$entries = getRows($sql);

// Get account summary
$summary = getRow("SELECT COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit FROM accounts");
$lastEntry = getRow("SELECT balance FROM accounts ORDER BY id DESC LIMIT 1");
$currentBalance = $lastEntry ? (float)$lastEntry['balance'] : 0;

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';
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

<!-- Summary Cards -->
<div class="row mb-3 no-print">
    <div class="col-md-4">
        <div class="stat-card" style="border-left-color: #28a745;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number text-success"><?php echo formatCurrency($summary['total_debit']); ?></div>
                    <div class="stat-label">Total Debit</div>
                </div>
                <div class="stat-icon" style="color: #28a745;">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stat-card" style="border-left-color: #dc3545;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number text-danger"><?php echo formatCurrency($summary['total_credit']); ?></div>
                    <div class="stat-label">Total Credit</div>
                </div>
                <div class="stat-icon" style="color: #dc3545;">
                    <i class="fas fa-arrow-left"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stat-card" style="border-left-color: #1a2332;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo formatCurrency($currentBalance); ?></div>
                    <div class="stat-label">Current Balance</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-balance-scale"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <button type="button" class="btn btn-primary no-print" data-bs-toggle="modal" data-bs-target="#entryModal">
            <i class="fas fa-plus me-2"></i>New Journal Entry
        </button>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-book me-2"></i>General Ledger
                <span class="ms-2 badge bg-success">PKR</span></span>
                <div class="d-flex gap-1 no-print">
                    <a href="accounts_print.php" target="_blank" class="btn btn-danger btn-sm"><i class="fas fa-print me-1"></i>Print</a>
                    <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="accountTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Account Type</th>
                                <th>Reference</th>
                                <th>Description</th>
                                <th>Debit (PKR)</th>
                                <th>Credit (PKR)</th>
                                <th>Balance (PKR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($entries) > 0): ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($entries as $entry): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo date('d-m-Y', strtotime($entry['account_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($entry['account_type']); ?></td>
                                        <td>
                                            <?php if ($entry['reference_type']): ?>
                                                <small><?php echo htmlspecialchars($entry['reference_type'] . ' #' . $entry['reference_id']); ?></small>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($entry['description'] ?: '-'); ?></td>
                                        <td class="text-success"><?php echo $entry['debit'] > 0 ? formatCurrency($entry['debit']) : '-'; ?></td>
                                        <td class="text-danger"><?php echo $entry['credit'] > 0 ? formatCurrency($entry['credit']) : '-'; ?></td>
                                        <td><strong><?php echo formatCurrency($entry['balance']); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-3">
                                        <i class="fas fa-info-circle me-2"></i>No ledger entries found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Entry Modal -->
<div class="modal fade" id="entryModal" tabindex="-1" aria-labelledby="entryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="entryModalLabel">
                    <i class="fas fa-plus-circle me-2"></i>New Journal Entry
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="account_date" class="form-label">Date *</label>
                            <input type="date" class="form-control" id="account_date" name="account_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="account_type" class="form-label">Account Type *</label>
                            <select class="form-select" id="account_type" name="account_type" required>
                                <option value="">Select Type</option>
                                <option value="Cash">Cash</option>
                                <option value="Bank">Bank</option>
                                <option value="Accounts Receivable">Accounts Receivable</option>
                                <option value="Accounts Payable">Accounts Payable</option>
                                <option value="Equity">Equity</option>
                                <option value="Revenue">Revenue</option>
                                <option value="Expense">Expense</option>
                                <option value="Asset">Asset</option>
                                <option value="Liability">Liability</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="reference_type" class="form-label">Reference Type</label>
                            <input type="text" class="form-control" id="reference_type" name="reference_type" placeholder="e.g. Sale, Purchase">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="reference_id" class="form-label">Reference ID</label>
                            <input type="number" class="form-control" id="reference_id" name="reference_id">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="debit" class="form-label">Debit (PKR)</label>
                            <input type="number" step="0.01" class="form-control" id="debit" name="debit" value="0.00" onchange="toggleAmount()" onkeyup="toggleAmount()">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="credit" class="form-label">Credit (PKR)</label>
                            <input type="number" step="0.01" class="form-control" id="credit" name="credit" value="0.00" onchange="toggleAmount()" onkeyup="toggleAmount()">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_entry" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Add Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Clear the other field when one is entered
function toggleAmount() {
    var debit = parseFloat(document.getElementById('debit').value) || 0;
    var credit = parseFloat(document.getElementById('credit').value) || 0;
}
</script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#accountTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]],
"language": {
    "search": "Search Entries:",
    "lengthMenu": "Show _MENU_ entries",
    "info": "Showing _START_ to _END_ of _TOTAL_ entries",
    "emptyTable": "No account entries found"
}
    });
});
</script>

<?php
include '../includes/footer.php';
?>
