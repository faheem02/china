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

// Get all production orders with product info - using correct table name 'production'
$sql = "SELECT p.*, fg.product_code, fg.product_name, fg.unit 
        FROM production p 
        LEFT JOIN finished_goods fg ON p.finished_good_id = fg.id 
        ORDER BY p.id DESC";
$orders = getRows($sql);

// Calculate totals
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

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
            <div class="stat-number"><?php echo $totalOrders; ?></div>
            <div class="stat-label">Total Production Orders</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle text-success"></i></div>
            <div class="stat-number"><?php echo $completedOrders; ?></div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-boxes text-info"></i></div>
            <div class="stat-number"><?php echo number_format($totalQuantity, 2); ?></div>
            <div class="stat-label">Total Quantity Produced</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-rupee-sign text-warning"></i></div>
            <div class="stat-number"><?php echo formatCurrency($totalCost); ?></div>
            <div class="stat-label">Total Production Cost</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-cogs me-2"></i>Production Details</span>
                <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
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
                                <th>Materials Used</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($orders) > 0): ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($orders as $order): 
                                    // Use correct table name 'production_raw_materials'
                                    $mats = getRows("SELECT prm.*, rm.material_code, rm.material_name, rm.unit 
                                                     FROM production_raw_materials prm 
                                                     LEFT JOIN raw_materials rm ON prm.material_id = rm.id 
                                                     WHERE prm.production_id = ?", 'i', [$order['id']]);
                                ?>
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
                                            $statusLabels = ['pending' => 'In Production', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
                                            ?>
                                            <span class="badge badge-status badge-<?php echo $statusClass; ?>">
                                                <?php echo isset($statusLabels[$status]) ? $statusLabels[$status] : ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (count($mats) > 0): ?>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#matModal<?php echo $order['id']; ?>">
                                                    <i class="fas fa-list"></i> <?php echo count($mats); ?> Items
                                                </button>
                                                
                                                <!-- Materials Modal -->
                                                <div class="modal fade" id="matModal<?php echo $order['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-lg">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">
                                                                    <i class="fas fa-cubes me-2"></i>
                                                                    Materials for <?php echo htmlspecialchars($order['production_no'] ?? ''); ?>
                                                                </h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Material</th>
                                                                            <th>Quantity Used</th>
                                                                            <th>Unit Price</th>
                                                                            <th>Total</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach ($mats as $m): ?>
                                                                            <tr>
                                                                                <td><?php echo htmlspecialchars(($m['material_code'] ?? '') . ' - ' . ($m['material_name'] ?? '')); ?></td>
                                                                                <td><?php echo isset($m['quantity_used']) ? number_format($m['quantity_used'], 2) : '0.00'; ?> <?php echo $m['unit'] ?? ''; ?></td>
                                                                                <td><?php echo isset($m['cost_per_unit']) ? formatCurrency($m['cost_per_unit']) : '0.00'; ?></td>
                                                                                <td><?php echo isset($m['total_cost']) ? formatCurrency($m['total_cost']) : '0.00'; ?></td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                    <tfoot>
                                                                        <tr class="table-primary">
                                                                            <th colspan="3" class="text-end">Total</th>
                                                                            <th><?php echo isset($order['total_cost']) ? formatCurrency($order['total_cost']) : '0.00'; ?></th>
                                                                        </tr>
                                                                    </tfoot>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">No materials</span>
                                            <?php endif; ?>
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

<style>
.stat-card {
    background: #fff;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}
.stat-icon {
    font-size: 30px;
    margin-bottom: 10px;
}
.stat-number {
    font-size: 28px;
    font-weight: 600;
    color: #2c3e50;
}
.stat-label {
    font-size: 14px;
    color: #6c757d;
    margin-top: 5px;
}
</style>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#productionTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]],
        "language": {
            "search": "Search Production:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ production records",
            "emptyTable": "No production records found"
        },
        "columnDefs": [
            { "targets": [0, 1, 2, 3, 4, 5, 6, 7], "orderable": true }
        ]
    });
});
</script>

<?php
include '../includes/footer.php';
?>