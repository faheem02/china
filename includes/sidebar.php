<?php
// Auto-detect path prefix: works whether included from root or pages/
$isInPages = strpos($_SERVER['PHP_SELF'], '/pages/') !== false;
$page = basename($_SERVER['PHP_SELF']);
$p = $isInPages ? '' : 'pages/';
$r = $isInPages ? '../' : '';

// Exact filename lists per section — prevents strpos false positives
// IMPORTANT: Do NOT use names like $customers, $suppliers, $sales, $expenses, $accounts,
// $reports, $party, $production — pages query data into those variables BEFORE including sidebar.
$localPurchases = ['add_local_purchase.php', 'local_purchases.php'];
$importPurchases = ['add_import_purchase.php', 'import_purchases.php'];
$supplierPages = ['local_suppliers.php', 'suppliers_summary.php', 'supplier_payments.php', 'supplier_detail.php', 'supplier_ledger.php', 'supplier_ledger_print.php', 'local_suppliers_print.php', 'chinese_suppliers.php', 'chinese_suppliers_summary.php', 'chinese_supplier_payment.php', 'chinese_supplier_ledger.php', 'chinese_supplier_ledger_print.php', 'chinese_suppliers_print.php'];
$finishedGoodsPages = ['finished_goods.php', 'add_finished_goods.php', 'products.php', 'add_product.php', 'finished_goods_receiving.php', 'finished_goods_print.php'];
$issuancePages = ['material_issue.php', 'raw_materials.php', 'add_raw_materials.php', 'raw_materials_print.php'];
$salesPages = ['new_sale.php', 'sales.php', 'view_hold_bills.php', 'sale_print.php', 'new_quotation.php', 'quotations.php', 'quotation_print.php'];
$customerPages = ['add_customer.php', 'customers.php', 'customer_receiving.php', 'customer_ledger.php', 'customer_party_ledger.php', 'customer_ledger_print.php', 'customers_print.php'];
$partyPages = ['add_party.php', 'parties.php', 'add_party_payable.php', 'add_party_received.php', 'add_party_paid.php', 'party_detail.php', 'general_party_ledger.php'];
$employeePages = ['add_employee.php', 'employees.php', 'employee_ledger.php', 'employee_payable.php', 'employee_paid.php'];
$expensePages = ['add_expense_head.php', 'add_expense.php', 'expenses.php', 'expenses_print.php', 'expense_heads.php', 'reports_expenses.php', 'reports_expenses_print.php'];
$accountPages = ['cashbook.php', 'bankbook.php', 'cashbook_print.php', 'bankbook_print.php'];
$productionPages = ['add_production.php', 'view_production.php', 'production.php'];
$reportPages = ['reports_inventory.php', 'closing_report.php', 'reports_item_sales.php', 'all_customers_closing.php', 'all_suppliers_closing.php', 'all_parties_closing.php'];
?>
<!-- Sidebar -->
<nav id="sidebar">
    <div class="sidebar-header" style="text-align:center;">
        <img src="<?php echo $r; ?>assets/img/logo.svg" alt="Long Life" style="max-width:100%;height:auto;display:block;margin:0 auto;max-height:72px;">
    </div>
    
    <div class="sidebar-nav">
        <!-- Dashboard -->
        <div class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <a href="<?php echo $r; ?>index.php">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
        </div>
        
        <!-- Local Purchases -->
        <div class="nav-item <?php echo in_array($page, $localPurchases) ? 'active' : ''; ?>">
            <a href="#" data-bs-toggle="collapse" data-bs-target="#localPurchaseSubmenu">
                <i class="fas fa-shopping-bag"></i> Local Purchases
                <i class="fas fa-chevron-down ms-auto"></i>
            </a>
            <div class="collapse <?php echo in_array($page, $localPurchases) ? 'show' : ''; ?>" id="localPurchaseSubmenu" data-bs-parent=".sidebar-nav">
                <div class="sub-menu">
                    <a href="<?php echo $p; ?>add_local_purchase.php"><i class="fas fa-angle-right me-2"></i>Add Purchase</a>
                    <a href="<?php echo $p; ?>local_purchases.php"><i class="fas fa-angle-right me-2"></i>View Purchase</a>
                </div>
            </div>
        </div>
        
        <div class="nav-item <?php echo in_array($page, $importPurchases) ? 'active' : ''; ?>">
            <a href="#" data-bs-toggle="collapse" data-bs-target="#importPurchaseSubmenu">
                <i class="fas fa-ship"></i> Import Purchases (CNY)
                <i class="fas fa-chevron-down ms-auto"></i>
            </a>
            <div class="collapse <?php echo in_array($page, $importPurchases) ? 'show' : ''; ?>" id="importPurchaseSubmenu" data-bs-parent=".sidebar-nav">
                <div class="sub-menu">
                    <a href="<?php echo $p; ?>add_import_purchase.php"><i class="fas fa-angle-right me-2"></i>Add Purchase</a>
                    <a href="<?php echo $p; ?>import_purchases.php"><i class="fas fa-angle-right me-2"></i>View Purchase</a>
                </div>
            </div>
        </div>
        
        <!-- Suppliers -->
        <div class="nav-item <?php echo in_array($page, $supplierPages) ? 'active' : ''; ?>">
            <a href="#" data-bs-toggle="collapse" data-bs-target="#supplierSubmenu">
                <i class="fas fa-truck"></i> Suppliers
                <i class="fas fa-chevron-down ms-auto"></i>
            </a>
            <div class="collapse <?php echo in_array($page, $supplierPages) ? 'show' : ''; ?>" id="supplierSubmenu" data-bs-parent=".sidebar-nav">
                <div class="sub-menu">
                    <div class="sub-group-label">Local Suppliers</div>
                    <a href="<?php echo $p; ?>local_suppliers.php"><i class="fas fa-angle-right me-2"></i>Supplier List</a>
                    <a href="<?php echo $p; ?>suppliers_summary.php"><i class="fas fa-angle-right me-2"></i>Summary</a>
                    <a href="<?php echo $p; ?>supplier_payments.php"><i class="fas fa-angle-right me-2"></i>Payments (PKR)</a>
                    <div class="sub-group-label mt-2">Chinese Suppliers</div>
                    <a href="<?php echo $p; ?>chinese_suppliers.php"><i class="fas fa-angle-right me-2"></i>Supplier List</a>
                    <a href="<?php echo $p; ?>chinese_suppliers_summary.php"><i class="fas fa-angle-right me-2"></i>Summary</a>
                    <a href="<?php echo $p; ?>chinese_supplier_payment.php"><i class="fas fa-angle-right me-2"></i>Payments (CNY)</a>
                </div>
            </div>
        </div>
        
        <div class="nav-item <?php echo in_array($page, $finishedGoodsPages) ? 'active' : ''; ?>">
            <a href="#" data-bs-toggle="collapse" data-bs-target="#finishedGoodsSubmenu">
                <i class="fas fa-boxes"></i> Finished Goods
                <i class="fas fa-chevron-down ms-auto"></i>
            </a>
            <div class="collapse <?php echo in_array($page, $finishedGoodsPages) ? 'show' : ''; ?>" id="finishedGoodsSubmenu" data-bs-parent=".sidebar-nav">
                <div class="sub-menu">
                    <a href="<?php echo $p; ?>add_finished_goods.php"><i class="fas fa-angle-right me-2"></i>Add Finished Good</a>
                    <a href="<?php echo $p; ?>finished_goods.php"><i class="fas fa-angle-right me-2"></i>View Finished Goods</a>
                    <div class="sub-group-label mt-2">Stock</div>
                    <a href="<?php echo $p; ?>finished_goods_receiving.php"><i class="fas fa-angle-right me-2"></i>Goods Receiving</a>
                </div>
            </div>
        </div>
        
        <!-- Issuance -->
        <div class="nav-item <?php echo in_array($page, $issuancePages) ? 'active' : ''; ?>">
            <a href="#" data-bs-toggle="collapse" data-bs-target="#issuanceSubmenu">
                <i class="fas fa-sign-out-alt"></i> Raw Material
                <i class="fas fa-chevron-down ms-auto"></i>
            </a>
            <div class="collapse <?php echo in_array($page, $issuancePages) ? 'show' : ''; ?>" id="issuanceSubmenu" data-bs-parent=".sidebar-nav">
                <div class="sub-menu">
                    <a href="<?php echo $p; ?>material_issue.php"><i class="fas fa-angle-right me-2"></i>Material Issuance</a>
                    <div class="sub-group-label mt-2">Raw Materials</div>
                    <a href="<?php echo $p; ?>add_raw_materials.php"><i class="fas fa-angle-right me-2"></i>Add Raw Material</a>
                    <a href="<?php echo $p; ?>raw_materials.php"><i class="fas fa-angle-right me-2"></i>View Raw Materials</a>
                </div>
            </div>
        </div>
        
        <!-- Production -->
        <div class="nav-item <?php echo in_array($page, $productionPages) ? 'active' : ''; ?>">
            <a href="#" data-bs-toggle="collapse" data-bs-target="#productionSubmenu">
                <i class="fas fa-industry"></i> Production
                <i class="fas fa-chevron-down ms-auto"></i>
            </a>
            <div class="collapse <?php echo in_array($page, $productionPages) ? 'show' : ''; ?>" id="productionSubmenu" data-bs-parent=".sidebar-nav">
                <div class="sub-menu">
                    <a href="<?php echo $p; ?>add_production.php"><i class="fas fa-angle-right me-2"></i>Add Production Order</a>
                    <a href="<?php echo $p; ?>view_production.php"><i class="fas fa-angle-right me-2"></i>View Production</a>
                </div>
            </div>
        </div>
        
        <!-- Sales -->
        <div class="nav-item <?php echo in_array($page, $salesPages) ? 'active' : ''; ?>">
            <a href="#" data-bs-toggle="collapse" data-bs-target="#salesSubmenu">
                <i class="fas fa-handshake"></i> Sales
                <i class="fas fa-chevron-down ms-auto"></i>
            </a>
            <div class="collapse <?php echo in_array($page, $salesPages) ? 'show' : ''; ?>" id="salesSubmenu" data-bs-parent=".sidebar-nav">
                <div class="sub-menu">
                    <div class="sub-group-label">Sales Orders</div>
                    <a href="<?php echo $p; ?>new_sale.php"><i class="fas fa-angle-right me-2"></i>New Sale</a>
                    <a href="<?php echo $p; ?>sales.php"><i class="fas fa-angle-right me-2"></i>View Sale Invoice</a>
                    <a href="<?php echo $p; ?>view_hold_bills.php"><i class="fas fa-angle-right me-2"></i>View Hold Bills</a>
                    <div class="sub-group-label mt-2">Quotations</div>
                    <a href="<?php echo $p; ?>new_quotation.php"><i class="fas fa-angle-right me-2"></i>New Quotation</a>
                    <a href="<?php echo $p; ?>quotations.php"><i class="fas fa-angle-right me-2"></i>View Quotations</a>
                </div>
            </div>
        </div>
        
        <!-- Customers -->
        <div class="nav-item <?php echo in_array($page, $customerPages) ? 'active' : ''; ?>">
            <a href="#" data-bs-toggle="collapse" data-bs-target="#customerSubmenu">
                <i class="fas fa-user-friends"></i> Customers
                <i class="fas fa-chevron-down ms-auto"></i>
            </a>
            <div class="collapse <?php echo in_array($page, $customerPages) ? 'show' : ''; ?>" id="customerSubmenu" data-bs-parent=".sidebar-nav">
                <div class="sub-menu">
                    <a href="<?php echo $p; ?>add_customer.php"><i class="fas fa-angle-right me-2"></i>Add Customer</a>
                    <a href="<?php echo $p; ?>customers.php"><i class="fas fa-angle-right me-2"></i>View Customer</a>
                    <a href="<?php echo $p; ?>customer_receiving.php"><i class="fas fa-angle-right me-2"></i>Customer Receiving</a>
                    <a href="<?php echo $p; ?>customer_party_ledger.php"><i class="fas fa-angle-right me-2"></i>Customer Party Ledger</a>
                </div>
            </div>
        </div>

        <!-- Party Ledger -->
        <div class="nav-item <?php echo in_array($page, $partyPages) ? 'active' : ''; ?>">
            <a href="#" data-bs-toggle="collapse" data-bs-target="#partySubmenu">
                <i class="fas fa-address-book"></i> Party Ledger
                <i class="fas fa-chevron-down ms-auto"></i>
            </a>
            <div class="collapse <?php echo in_array($page, $partyPages) ? 'show' : ''; ?>" id="partySubmenu" data-bs-parent=".sidebar-nav">
                <div class="sub-menu">
                    <a href="<?php echo $p; ?>add_party.php"><i class="fas fa-angle-right me-2"></i>Add Party</a>
                    <a href="<?php echo $p; ?>parties.php"><i class="fas fa-angle-right me-2"></i>View Parties</a>
                    <a href="<?php echo $p; ?>add_party_payable.php"><i class="fas fa-angle-right me-2"></i>Add Payable</a>
                    <a href="<?php echo $p; ?>add_party_received.php"><i class="fas fa-angle-right me-2"></i>Receive from Party</a>
                    <a href="<?php echo $p; ?>add_party_paid.php"><i class="fas fa-angle-right me-2"></i>Pay to Party</a>
                    <a href="<?php echo $p; ?>general_party_ledger.php"><i class="fas fa-angle-right me-2"></i>General Party Ledger</a>
                </div>
            </div>
        </div>

        <!-- Employees -->
        <div class="nav-item <?php echo in_array($page, $employeePages) ? 'active' : ''; ?>">
            <a href="#" data-bs-toggle="collapse" data-bs-target="#employeeSubmenu">
                <i class="fas fa-users"></i> Employees
                <i class="fas fa-chevron-down ms-auto"></i>
            </a>
            <div class="collapse <?php echo in_array($page, $employeePages) ? 'show' : ''; ?>" id="employeeSubmenu" data-bs-parent=".sidebar-nav">
                <div class="sub-menu">
                    <a href="<?php echo $p; ?>add_employee.php"><i class="fas fa-angle-right me-2"></i>Add Employee</a>
                    <a href="<?php echo $p; ?>employees.php"><i class="fas fa-angle-right me-2"></i>View Employees</a>
                    <a href="<?php echo $p; ?>employee_ledger.php"><i class="fas fa-angle-right me-2"></i>Salary Ledger</a>
                    <a href="<?php echo $p; ?>employee_payable.php"><i class="fas fa-angle-right me-2"></i>Employee Payable</a>
                    <a href="<?php echo $p; ?>employee_paid.php"><i class="fas fa-angle-right me-2"></i>Employee Paid</a>
                </div>
            </div>
        </div>
        
        <!-- Expenses -->
        <div class="nav-item <?php echo in_array($page, $expensePages) ? 'active' : ''; ?>">
            <a href="#" data-bs-toggle="collapse" data-bs-target="#expenseSubmenu">
                <i class="fas fa-money-bill-wave"></i> Expenses
                <i class="fas fa-chevron-down ms-auto"></i>
            </a>
            <div class="collapse <?php echo in_array($page, $expensePages) ? 'show' : ''; ?>" id="expenseSubmenu" data-bs-parent=".sidebar-nav">
                <div class="sub-menu">
                    <a href="<?php echo $p; ?>add_expense_head.php"><i class="fas fa-angle-right me-2"></i>Add Expense</a>
                    <a href="<?php echo $p; ?>expenses.php"><i class="fas fa-angle-right me-2"></i>All Expenses</a>
                    <a href="<?php echo $p; ?>expense_heads.php"><i class="fas fa-angle-right me-2"></i>Expense Categories</a>
                </div>
            </div>
        </div>
        
        <!-- Accounts -->
        <div class="nav-item <?php echo in_array($page, $accountPages) ? 'active' : ''; ?>">
            <a href="#" data-bs-toggle="collapse" data-bs-target="#accountsSubmenu">
                <i class="fas fa-book"></i> Cash &amp; Bank Book
                <i class="fas fa-chevron-down ms-auto"></i>
            </a>
            <div class="collapse <?php echo in_array($page, $accountPages) ? 'show' : ''; ?>" id="accountsSubmenu" data-bs-parent=".sidebar-nav">
                <div class="sub-menu">
                    <a href="<?php echo $p; ?>cashbook.php"><i class="fas fa-angle-right me-2"></i>Cash Book</a>
                    <a href="<?php echo $p; ?>bankbook.php"><i class="fas fa-angle-right me-2"></i>Bank Book</a>
                </div>
            </div>
        </div>
        
        <!-- Reports -->
        <div class="nav-item <?php echo in_array($page, $reportPages) ? 'active' : ''; ?>">
            <a href="#" data-bs-toggle="collapse" data-bs-target="#reportsSubmenu">
                <i class="fas fa-chart-bar"></i> Reports
                <i class="fas fa-chevron-down ms-auto"></i>
            </a>
            <div class="collapse <?php echo in_array($page, $reportPages) ? 'show' : ''; ?>" id="reportsSubmenu" data-bs-parent=".sidebar-nav">
                <div class="sub-menu">
                    <a href="<?php echo $p; ?>reports_inventory.php"><i class="fas fa-angle-right me-2"></i>Stock Report</a>
                    <a href="<?php echo $p; ?>closing_report.php"><i class="fas fa-angle-right me-2"></i>Closing Report</a>
                    <a href="<?php echo $p; ?>reports_item_sales.php"><i class="fas fa-angle-right me-2"></i>Item Wise Sale Report</a>
                </div>
            </div>
        </div>
        
        <!-- Logout -->
        <div class="nav-item">
            <a href="<?php echo $r; ?>logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</nav>

<!-- Page Content -->
<div id="content">
    <!-- Top Navbar -->
    <nav class="navbar-custom">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <button type="button" id="sidebarCollapse" class="btn btn-light">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="ms-3 fw-bold"><?php echo isset($pageTitle) ? $pageTitle : 'Dashboard'; ?></span>
            </div>
            <div class="user-info">
                <span><?php echo isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'User'; ?></span>
                <div class="avatar">
                    <?php echo isset($_SESSION['full_name']) ? substr($_SESSION['full_name'], 0, 1) : 'U'; ?>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Page Content Start -->
    <div class="container-fluid px-0">
