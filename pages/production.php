<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'View Production';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    modifyData("DELETE FROM production_raw_materials WHERE production_id = ?", 'i', [$id]);
    $result = modifyData("DELETE FROM production WHERE id = ?", 'i', [$id]);
    if ($result !== false) {
        setFlash('Production order deleted successfully!', 'success');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $message = 'Error deleting production order!';
        $messageType = 'danger';
    }
}

$orders = getRows("SELECT p.*, fg.product_code, fg.product_name, fg.unit 
        FROM production p 
        LEFT JOIN finished_goods fg ON p.finished_good_id = fg.id 
        ORDER BY p.id DESC");

$totalOrders = count($orders);
$totalQuantity = 0;
$totalCost = 0;
$completedOrders = 0;
foreach ($orders as $o) {
    $totalQuantity += (float)$o['quantity'];
    $totalCost += (float)$o['total_cost'];
    if ($o['status'] == 'completed') $completedOrders++;
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

<div class="row mb-3">
    <div class="col-12">
        <a href="add_production.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>New Production</a>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color: #1a2332;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo $totalOrders; ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color: #28a745;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number text-success"><?php echo $completedOrders; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle text-success"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color: #0dcaf0;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($totalQuantity, 2); ?></div>
                    <div class="stat-label">Total Qty Produced</div>
                </div>
                <div class="stat-icon"><i class="fas fa-boxes text-info"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-left-color: #ffc107;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo formatCurrency($totalCost); ?></div>
                    <div class="stat-label">Total Cost</div>
                </div>
                <div class="stat-icon"><i class="fas fa-rupee-sign text-warning"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header" style="background:#1a2332;color:#fff;">
                <i class="fas fa-cogs me-2"></i>Production Orders
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="productionTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Production No</th>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Total Cost (PKR)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($orders) > 0): ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($order['production_no'] ?? ''); ?></strong></td>
                                        <td><?php echo isset($order['production_date']) ? date('d-m-Y', strtotime($order['production_date'])) : '-'; ?></td>
                                        <td>
                                            <?php 
                                            if (isset($order['product_code']) && isset($order['product_name'])) {
                                                echo htmlspecialchars($order['product_code'] . ' - ' . $order['product_name']);
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo isset($order['quantity']) ? number_format($order['quantity'], 2) : '0.00'; ?> <?php echo $order['unit'] ?? ''; ?></td>
                                        <td><?php echo isset($order['total_cost']) ? formatCurrency($order['total_cost']) : '0.00'; ?></td>
                                        <td>
                                            <?php 
                                            $status = $order['status'] ?? 'pending';
                                            $statusClass = $status === 'completed' ? 'success' : ($status === 'cancelled' ? 'danger' : 'warning');
                                            ?>
                                            <span class="badge badge-status badge-<?php echo $statusClass; ?>">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="add_production.php?edit=<?php echo $order['id']; ?>" class="btn btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?delete=<?php echo $order['id']; ?>" class="btn btn-danger delete-confirm" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-3">
                                        <i class="fas fa-info-circle me-2"></i>No production orders found
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

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#productionTable').DataTable({
        "pageLength": 25,
        "order": [[1, "desc"]],
        "language": {
            "search": "Search Orders:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ orders",
            "emptyTable": "No production orders found"
        }
    });
    $('.delete-confirm').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this production order?')) {
            e.preventDefault();
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
