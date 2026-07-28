<?php
/**
 * Balance Sheet
 * Manufacturing ERP System
 */

require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Balance Sheet';

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';

// ── Helper function to safely get value ──
function safeGetValue($result, $key = 'total', $default = 0) {
    if ($result && is_array($result) && isset($result[$key])) {
        return (float)$result[$key];
    }
    return $default;
}

// ── Total Raw Materials Stock Value ──
$rmValue = getRow("SELECT COALESCE(SUM(current_stock * purchase_price_pkr), 0) as total FROM raw_materials WHERE status = 'active'");
$rawMaterialTotal = safeGetValue($rmValue);

// ── Total Finished Goods Stock Value ──
$fgValue = getRow("SELECT COALESCE(SUM(current_stock * cost_price), 0) as total FROM finished_goods WHERE status = 'active'");
$finishedGoodsTotal = safeGetValue($fgValue);

// ── Total Sales (Revenue) ──
$totalSales = getRow("SELECT COALESCE(SUM(total_amount), 0) as total, COALESCE(SUM(balance), 0) as outstanding FROM sales");
$totalRevenue = safeGetValue($totalSales, 'total');
$totalOutstanding = safeGetValue($totalSales, 'outstanding');

// ── Total Local Purchases ──
$localPurchases = getRow("SELECT COALESCE(SUM(total_amount), 0) as total FROM local_purchases");
$localPurchasesTotal = safeGetValue($localPurchases);

// ── Total Import Purchases (PKR equivalent) ──
$importPurchases = getRow("SELECT COALESCE(SUM(total_amount_pkr), 0) as total FROM import_purchases");
$importPurchasesTotal = safeGetValue($importPurchases);

// ── Total Expenses ──
$totalExpenses = getRow("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE status = 'active'");
$totalExpensesTotal = safeGetValue($totalExpenses);

// ── Total Customer Outstanding ──
$custOutstanding = getRow("SELECT COALESCE(SUM(balance), 0) as total FROM sales WHERE payment_status != 'paid'");
$customerOutstandingTotal = safeGetValue($custOutstanding);

// ── Total Supplier Payable (Local) ──
$localPayable = getRow("SELECT COALESCE(SUM(balance), 0) as total FROM local_purchases WHERE payment_status != 'paid'");
$localPayableTotal = safeGetValue($localPayable);

// ── Total Supplier Payable (Chinese - PKR) ──
$chinesePayable = getRow("SELECT COALESCE(SUM(balance_pkr), 0) as total FROM import_purchases WHERE payment_status != 'paid'");
$chinesePayableTotal = safeGetValue($chinesePayable);

// ── Total Employees Salary Payable ──
$empSalary = getRow("SELECT COALESCE(SUM(monthly_salary), 0) as total FROM employees WHERE status = 'active'");
$empSalaryTotal = safeGetValue($empSalary);

// ── Calculations ──
$totalInventory = $rawMaterialTotal + $finishedGoodsTotal;
$totalPurchases = $localPurchasesTotal + $importPurchasesTotal;
$totalPayable = $localPayableTotal + $chinesePayableTotal;
$totalReceivable = $customerOutstandingTotal;
$totalLiabilities = $totalPayable + $empSalaryTotal;
$totalAssets = $totalInventory + $totalReceivable;
$totalCosts = $totalPurchases + $totalExpensesTotal;
$netBalance = $totalRevenue - $totalCosts;
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-balance-scale me-2"></i>Balance Sheet
                <span class="ms-2 badge bg-success">PKR</span>
                <span class="ms-2 text-muted">As of <?php echo date('d-m-Y'); ?></span></span>
                <div class="d-flex gap-2 no-print">
                    <a href="balance_sheet_print.php" target="_blank" class="btn btn-danger btn-sm"><i class="fas fa-print me-1"></i>Print</a>
                    <a href="../index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Assets -->
                    <div class="col-md-6">
                        <div class="card mb-3 border-success">
                            <div class="card-header bg-success text-white">
                                <i class="fas fa-plus-circle me-2"></i>Assets
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Current Assets</strong></td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td class="ps-4">Raw Materials Inventory</td>
                                        <td class="text-end"><?php echo formatCurrency($rawMaterialTotal); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="ps-4">Finished Goods Inventory</td>
                                        <td class="text-end"><?php echo formatCurrency($finishedGoodsTotal); ?></td>
                                    </tr>
                                    <tr class="table-info">
                                        <td class="ps-4"><strong>Total Inventory</strong></td>
                                        <td class="text-end"><strong><?php echo formatCurrency($totalInventory); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td class="ps-4">Accounts Receivable (Customers)</td>
                                        <td class="text-end text-danger"><?php echo formatCurrency($totalReceivable); ?></td>
                                    </tr>
                                    <tr class="table-success">
                                        <td><strong>Total Assets</strong></td>
                                        <td class="text-end"><strong><?php echo formatCurrency($totalAssets); ?></strong></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Liabilities -->
                    <div class="col-md-6">
                        <div class="card mb-3 border-danger">
                            <div class="card-header bg-danger text-white">
                                <i class="fas fa-minus-circle me-2"></i>Liabilities
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Current Liabilities</strong></td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td class="ps-4">Accounts Payable (Local Suppliers)</td>
                                        <td class="text-end"><?php echo formatCurrency($localPayableTotal); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="ps-4">Accounts Payable (Chinese Suppliers)</td>
                                        <td class="text-end"><?php echo formatCurrency($chinesePayableTotal); ?></td>
                                    </tr>
                                    <tr class="table-info">
                                        <td class="ps-4"><strong>Total Payable</strong></td>
                                        <td class="text-end"><strong><?php echo formatCurrency($totalPayable); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td class="ps-4">Estimated Salary Payable</td>
                                        <td class="text-end"><?php echo formatCurrency($empSalaryTotal); ?></td>
                                    </tr>
                                    <tr class="table-danger">
                                        <td><strong>Total Liabilities</strong></td>
                                        <td class="text-end"><strong><?php echo formatCurrency($totalLiabilities); ?></strong></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Equity / Summary -->
                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-chart-pie me-2"></i>Financial Summary
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center p-3">
                                            <h5 class="text-success">Revenue</h5>
                                            <h3><?php echo formatCurrency($totalRevenue); ?></h3>
                                            <small class="text-muted">Total Sales</small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="text-center p-3">
                                            <h5 class="text-danger">Costs</h5>
                                            <h3><?php echo formatCurrency($totalCosts); ?></h3>
                                            <small class="text-muted">Purchases + Expenses</small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="text-center p-3">
                                            <h5 class="text-info">Net Balance</h5>
                                            <h3><?php echo formatCurrency($netBalance); ?></h3>
                                            <small class="text-muted">Revenue - Costs</small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="text-center p-3">
                                            <h5 class="text-warning">Outstanding</h5>
                                            <h3><?php echo formatCurrency($totalReceivable); ?></h3>
                                            <small class="text-muted">Customer Receivables</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Balance Sheet Equation Check -->
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <div class="alert <?php echo abs($totalAssets - $totalLiabilities) < 1 ? 'alert-success' : 'alert-warning'; ?> text-center">
                                            <strong>Balance Sheet Equation:</strong>
                                            Assets (<?php echo formatCurrency($totalAssets); ?>) = 
                                            Liabilities (<?php echo formatCurrency($totalLiabilities); ?>) + 
                                            Equity (<?php echo formatCurrency($totalAssets - $totalLiabilities); ?>)
                                            <?php if (abs($totalAssets - $totalLiabilities) < 1): ?>
                                                <i class="fas fa-check-circle text-success ms-2"></i> Balanced ✓
                                            <?php else: ?>
                                                <i class="fas fa-exclamation-triangle text-warning ms-2"></i> Check your entries
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include '../includes/footer.php';
?>