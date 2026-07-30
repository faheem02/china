<?php
require_once 'includes/database.php';
requireLogin();

$pageTitle = 'Dashboard';

// ── Counts ──
$totalEmployees = (getRow("SELECT COUNT(*) as t FROM employees WHERE status='active'")['t'] ?? 0);
$totalCustomers = (getRow("SELECT COUNT(*) as t FROM customers WHERE status='active'")['t'] ?? 0);
$totalMaterials = (getRow("SELECT COUNT(*) as t FROM raw_materials WHERE status='active'")['t'] ?? 0);
$totalGoods     = (getRow("SELECT COUNT(*) as t FROM finished_goods WHERE status='active'")['t'] ?? 0);

// ── Financial ──
$cashBalance = (getRow("SELECT COALESCE(balance,0) as t FROM accounts WHERE account_type='Cash' ORDER BY id DESC LIMIT 1")['t'] ?? 0);
$bankBalance = (getRow("SELECT COALESCE(balance,0) as t FROM accounts WHERE account_type='Bank' ORDER BY id DESC LIMIT 1")['t'] ?? 0);
$customerOutstanding = (getRow("SELECT COALESCE(SUM(current_balance),0) as t FROM customers WHERE status='active' AND current_balance > 0")['t'] ?? 0);
$supplierOutstanding = (getRow("SELECT COALESCE(SUM(current_balance),0) as t FROM local_suppliers WHERE status='active' AND current_balance > 0")['t'] ?? 0);

// ── FIXED: chinese_suppliers uses balance_cny instead of balance ──
$chineseSupplierOutstanding = (getRow("SELECT COALESCE(SUM(current_balance),0) as t FROM chinese_suppliers WHERE status='active' AND current_balance > 0")['t'] ?? 0);

// ── FIXED: parties table may not exist, handle gracefully ──
$partyReceivable = 0;
$partyPayable = 0;
$partyCheck = getRow("SHOW TABLES LIKE 'parties'");
if ($partyCheck) {
    $partyReceivable = (getRow("SELECT COALESCE(SUM(current_balance),0) as t FROM parties WHERE status='active' AND current_balance > 0")['t'] ?? 0);
    $partyPayable = abs((getRow("SELECT COALESCE(SUM(current_balance),0) as t FROM parties WHERE status='active' AND current_balance < 0")['t'] ?? 0));
}

// ── Today / Month ──
$todaySales = getRow("SELECT COUNT(*) as cnt, COALESCE(SUM(net_amount),0) as amt FROM sales WHERE DATE(sale_date)=CURDATE()");
$monthSales = getRow("SELECT COUNT(*) as cnt, COALESCE(SUM(net_amount),0) as amt FROM sales WHERE MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE())");
$monthExpenses = getRow("SELECT COALESCE(SUM(amount),0) as amt FROM expenses WHERE MONTH(expense_date)=MONTH(CURDATE()) AND YEAR(expense_date)=YEAR(CURDATE())");

// ── FIXED: production table with production_date column ──
$monthIssuances = getRow("SELECT COUNT(*) as cnt, COALESCE(SUM(total_value),0) as amt FROM material_issues WHERE MONTH(issue_date)=MONTH(CURDATE()) AND YEAR(issue_date)=YEAR(CURDATE())");

// ── Recent Sales ──
$recentSales = getRows("SELECT s.*, c.customer_name FROM sales s LEFT JOIN customers c ON s.customer_id=c.id ORDER BY s.id DESC LIMIT 5");

// ── Recent Purchases (local + import combined) ──
$recentPurchases = getRows("
    SELECT 'import' as src, p.purchase_no, s.supplier_name, p.grand_total_pkr as amount, p.payment_status, p.purchase_date
    FROM import_purchases p LEFT JOIN chinese_suppliers s ON p.supplier_id=s.id
    UNION ALL
    SELECT 'local' as src, p.purchase_no, s.supplier_name, p.total_amount as amount, p.payment_status, p.purchase_date
    FROM local_purchases p LEFT JOIN local_suppliers s ON p.supplier_id=s.id
    ORDER BY purchase_date DESC LIMIT 5
");

// ── Recent Issuances ──
$recentIssuances = getRows("SELECT mi.*, 
    CASE WHEN mi.customer_id IS NOT NULL THEN c.customer_name ELSE mi.customer_name END as display_customer
    FROM material_issues mi 
    LEFT JOIN customers c ON mi.customer_id = c.id 
    ORDER BY mi.id DESC LIMIT 5");

// ── Low Stock (FG with stock <= 10) ──
$lowStockFG = getRows("SELECT product_name, product_code, current_stock FROM finished_goods WHERE status='active' AND current_stock <= 10 ORDER BY current_stock ASC LIMIT 5");

// ── Recent Expenses ──
$recentExpenses = getRows("SELECT * FROM expenses ORDER BY id DESC LIMIT 5");

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
.dash-stat{background:#fff;border-radius:8px;padding:18px 20px;border-left:4px solid #6c757d;box-shadow:0 1px 4px rgba(0,0,0,.06);transition:transform .15s}
.dash-stat:hover{transform:translateY(-2px)}
.dash-stat .stat-val{font-size:22px;font-weight:700;color:#1a2332}
.dash-stat .stat-lbl{font-size:12px;color:#888;margin-top:2px}
.dash-stat .stat-ico{font-size:22px;opacity:.35}
.dash-finance{background:#fff;border-radius:8px;padding:14px 18px;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.dash-finance .fin-val{font-size:18px;font-weight:700}
.dash-finance .fin-lbl{font-size:11px;color:#888}
.low-stock-badge{font-size:11px;padding:2px 8px;border-radius:10px}
</style>

<!-- Row 1: Stats -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="dash-stat" style="border-left-color:#6f42c1">
            <div class="d-flex justify-content-between align-items-center">
                <div><div class="stat-val"><?php echo $totalEmployees; ?></div><div class="stat-lbl">Employees</div></div>
                <div class="stat-ico" style="color:#6f42c1"><i class="fas fa-users"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dash-stat" style="border-left-color:#28a745">
            <div class="d-flex justify-content-between align-items-center">
                <div><div class="stat-val"><?php echo $totalCustomers; ?></div><div class="stat-lbl">Customers</div></div>
                <div class="stat-ico" style="color:#28a745"><i class="fas fa-user-friends"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dash-stat" style="border-left-color:#17a2b8">
            <div class="d-flex justify-content-between align-items-center">
                <div><div class="stat-val"><?php echo $totalMaterials; ?></div><div class="stat-lbl">Raw Materials</div></div>
                <div class="stat-ico" style="color:#17a2b8"><i class="fas fa-cubes"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dash-stat" style="border-left-color:#ffc107">
            <div class="d-flex justify-content-between align-items-center">
                <div><div class="stat-val"><?php echo $totalGoods; ?></div><div class="stat-lbl">Finished Goods</div></div>
                <div class="stat-ico" style="color:#ffc107"><i class="fas fa-boxes"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Row 2: Financial Overview -->
<div class="row g-3 mb-3">
    <div class="col">
        <div class="dash-finance">
            <div class="fin-val text-primary"><?php echo formatCurrency($cashBalance); ?></div>
            <div class="fin-lbl">Cash Balance</div>
        </div>
    </div>
    <div class="col">
        <div class="dash-finance">
            <div class="fin-val" style="color:#17a2b8"><?php echo formatCurrency($bankBalance); ?></div>
            <div class="fin-lbl">Bank Balance</div>
        </div>
    </div>
    <div class="col">
        <div class="dash-finance">
            <div class="fin-val text-success"><?php echo formatCurrency($customerOutstanding); ?></div>
            <div class="fin-lbl">Customer Receivable</div>
        </div>
    </div>
    <div class="col">
        <div class="dash-finance">
            <div class="fin-val text-danger"><?php echo formatCurrency($supplierOutstanding + $chineseSupplierOutstanding); ?></div>
            <div class="fin-lbl">Supplier Payable</div>
        </div>
    </div>
    <div class="col">
        <div class="dash-finance">
            <div class="fin-val" style="color:#6f42c1"><?php echo formatCurrency($partyReceivable); ?></div>
            <div class="fin-lbl">Party Receivable</div>
        </div>
    </div>
    <div class="col">
        <div class="dash-finance">
            <div class="fin-val text-warning"><?php echo formatCurrency($partyPayable); ?></div>
            <div class="fin-lbl">Party Payable</div>
        </div>
    </div>
</div>

<!-- Row 3: Today / Month -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-muted" style="font-size:12px"><i class="fas fa-calendar-day me-1"></i>Today's Sales</div>
                <h4 class="mb-0 mt-1 text-primary"><?php echo isset($todaySales['amt']) ? formatCurrency($todaySales['amt']) : '0.00'; ?></h4>
                <small class="text-muted"><?php echo isset($todaySales['cnt']) ? $todaySales['cnt'] : 0; ?> invoices</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-muted" style="font-size:12px"><i class="fas fa-calendar-alt me-1"></i>Month Sales</div>
                <h4 class="mb-0 mt-1 text-success"><?php echo isset($monthSales['amt']) ? formatCurrency($monthSales['amt']) : '0.00'; ?></h4>
                <small class="text-muted"><?php echo isset($monthSales['cnt']) ? $monthSales['cnt'] : 0; ?> invoices</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-muted" style="font-size:12px"><i class="fas fa-sign-out-alt me-1"></i>Month Issuances</div>
                <h4 class="mb-0 mt-1" style="color:#17a2b8"><?php echo isset($monthIssuances['cnt']) ? $monthIssuances['cnt'] : 0; ?> issues</h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-muted" style="font-size:12px"><i class="fas fa-money-bill-wave me-1"></i>Month Expenses</div>
                <h4 class="mb-0 mt-1 text-danger"><?php echo isset($monthExpenses['amt']) ? formatCurrency($monthExpenses['amt']) : '0.00'; ?></h4>
            </div>
        </div>
    </div>
</div>

<!-- Row 4: Recent Sales + Recent Purchases -->
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-shopping-cart me-2"></i>Recent Sales</span>
                <a href="pages/sales.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Invoice</th><th>Customer</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if ($recentSales && count($recentSales) > 0): foreach ($recentSales as $s): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s['sale_no'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($s['customer_name'] ?? 'Walk-in'); ?></td>
                            <td class="text-end"><?php echo isset($s['net_amount']) ? formatCurrency($s['net_amount']) : '0.00'; ?></td>
                            <td>
                                <?php 
                                $status = $s['payment_status'] ?? 'unpaid';
                                $badgeClass = $status === 'paid' ? 'success' : ($status === 'partial' ? 'warning' : 'danger');
                                ?>
                                <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo ucfirst($status); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No sales yet</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-truck me-2"></i>Recent Purchases</span>
                <a href="pages/import_purchases.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Purchase #</th><th>Supplier</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if ($recentPurchases && count($recentPurchases) > 0): foreach ($recentPurchases as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['purchase_no'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($p['supplier_name'] ?? ''); ?></td>
                            <td class="text-end"><?php echo isset($p['amount']) ? formatCurrency($p['amount']) : '0.00'; ?></td>
                            <td>
                                <?php 
                                $status = $p['payment_status'] ?? 'unpaid';
                                $badgeClass = $status === 'paid' ? 'success' : ($status === 'partial' ? 'warning' : 'danger');
                                ?>
                                <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo ucfirst($status); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No purchases yet</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Row 5: Recent Issuances + Low Stock + Recent Expenses -->
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-sign-out-alt me-2"></i>Recent Issuances</span>
                <a href="pages/material_issue.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Issue No</th><th>Customer</th><th class="text-end">Value</th></tr></thead>
                    <tbody>
                    <?php if ($recentIssuances && count($recentIssuances) > 0): foreach ($recentIssuances as $iss): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($iss['issue_no'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($iss['display_customer'] ?: '-'); ?></td>
                            <td class="text-end"><?php echo isset($iss['total_value']) ? formatCurrency($iss['total_value']) : '0.00'; ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">No issuances yet</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-exclamation-triangle me-2 text-warning"></i>Low Stock Alerts
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Product</th><th>Code</th><th class="text-end">Stock</th></tr></thead>
                    <tbody>
                    <?php if ($lowStockFG && count($lowStockFG) > 0): foreach ($lowStockFG as $fg): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($fg['product_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($fg['product_code'] ?? ''); ?></td>
                            <td class="text-end">
                                <?php 
                                $stock = $fg['current_stock'] ?? 0;
                                $badgeClass = $stock == 0 ? 'danger' : 'warning';
                                ?>
                                <span class="low-stock-badge bg-<?php echo $badgeClass; ?> text-white">
                                    <?php echo number_format($stock, 2); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">All stock levels OK</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-money-bill-wave me-2"></i>Recent Expenses</span>
                <a href="pages/expenses.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Expense #</th><th>Category</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                    <?php if ($recentExpenses && count($recentExpenses) > 0): foreach ($recentExpenses as $ex): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ex['expense_no'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($ex['category'] ?? ''); ?></td>
                            <td class="text-end"><?php echo isset($ex['amount']) ? formatCurrency($ex['amount']) : '0.00'; ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">No expenses yet</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>