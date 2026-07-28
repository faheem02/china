<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Banks';

$flash = getFlash();
$message = '';
$messageType = '';
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $bankId = (int)$_GET['delete'];
    $bank = getRow("SELECT bank_name FROM banks WHERE id = ?", 'i', [$bankId]);
    if ($bank) {
        $check = getRow("SELECT COUNT(*) as cnt FROM accounts WHERE description LIKE ?", 's', ['%' . $bank['bank_name'] . '%']);
        if ($check && $check['cnt'] > 0) {
            setFlash('Cannot delete: bank is referenced in account transactions.', 'danger');
        } else {
            modifyData("DELETE FROM banks WHERE id = ?", 'i', [$bankId]);
            setFlash('Bank deleted successfully.', 'success');
        }
    } else {
        setFlash('Bank not found.', 'danger');
    }
    header('Location: banks.php');
    exit;
}

$banks = getRows("SELECT * FROM banks ORDER BY id DESC");

$totalBanks = count($banks);
$totalOpening = 0;
$totalCurrent = 0;
foreach ($banks as $b) {
    $totalOpening += (float)$b['opening_balance'];
    $totalCurrent += (float)$b['current_balance'];
}

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<div class="container-fluid px-3">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show my-2 py-2"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-2 no-print">
        <h5 class="mb-0"><i class="fas fa-university me-2"></i>Banks</h5>
        <div>
            <a href="add_bank.php" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>Add Bank</a>
            <a href="<?php echo $r; ?>index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <div class="card shadow-sm" style="border-top:3px solid #1a2332;">
                <div class="card-body py-2">
                    <div class="text-muted small">Total Banks</div>
                    <h4 class="mb-0"><?php echo $totalBanks; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm" style="border-top:3px solid #1a2332;">
                <div class="card-body py-2">
                    <div class="text-muted small">Total Opening Balance</div>
                    <h4 class="mb-0"><?php echo number_format($totalOpening, 2); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm" style="border-top:3px solid #1a2332;">
                <div class="card-body py-2">
                    <div class="text-muted small">Total Current Balance</div>
                    <h4 class="mb-0"><?php echo number_format($totalCurrent, 2); ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm" style="border-top:3px solid #1a2332;">
        <div class="card-header py-2 d-flex justify-content-between align-items-center" style="background:#1a2332;color:#fff;">
            <span><i class="fas fa-university me-1"></i>All Banks</span>
            <a href="banks_print.php" target="_blank" class="btn btn-danger btn-sm"><i class="fas fa-print me-1"></i>Print</a>
        </div>
        <div class="card-body p-2">
            <div class="table-responsive">
                <table id="banksTable" class="table table-sm table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Bank Name</th>
                            <th>Account No</th>
                            <th>Account Type</th>
                            <th>Opening Balance</th>
                            <th>Current Balance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($banks as $i => $bank): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><?php echo htmlspecialchars($bank['bank_name']); ?></td>
                                <td><?php echo htmlspecialchars($bank['account_number']); ?></td>
                                <td><?php echo ucfirst($bank['account_type']); ?></td>
                                <td class="text-end"><?php echo number_format($bank['opening_balance'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($bank['current_balance'], 2); ?></td>
                                <td>
                                    <a href="add_bank.php?edit=<?php echo $bank['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                    <a href="banks.php?delete=<?php echo $bank['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this bank?');"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#banksTable').DataTable({ order: [[0, 'desc']], pageLength: 25, language: { emptyTable: 'No banks found' } });
});
</script>

<?php include '../includes/footer.php'; ?>
