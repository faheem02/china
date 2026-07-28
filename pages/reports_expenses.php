<?php
require_once '../includes/database.php';
requireLogin();
$pageTitle = 'Expense Reports';

$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');
$headFilter = $_GET['head'] ?? '';

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/print_header.php';

$where = "WHERE e.expense_date BETWEEN ? AND ?";
$params = [$fromDate, $toDate];
if (!empty($headFilter)) { $where .= " AND e.category = ?"; $params[] = $headFilter; }

$expenses = getRows("SELECT e.* FROM expenses e $where ORDER BY e.expense_date DESC", 'ss' . (isset($params[2]) ? 's' : ''), $params);
$totalAmount = array_sum(array_column($expenses, 'amount'));
$totalPaid = array_sum(array_column($expenses, 'paid_amount'));

$byCategory = getRows("SELECT e.category, COUNT(*) AS cnt, SUM(e.amount) AS total_amt, SUM(e.paid_amount) AS total_paid FROM expenses e $where GROUP BY e.category ORDER BY total_amt DESC", 'ss' . (isset($params[2]) ? 's' : ''), $params);
$expenseHeads = getRows("SELECT DISTINCT category_name FROM expense_categories WHERE status='active' ORDER BY category_name");
?>

<div class="row mb-3"><div class="col-12"><div class="card">
    <div class="card-header d-flex justify-content-between align-items-center"><span><i class="fas fa-filter me-2"></i>Filter</span><a href="reports_expenses_print.php?from_date=<?php echo urlencode($fromDate); ?>&to_date=<?php echo urlencode($toDate); ?>&head=<?php echo urlencode($headFilter); ?>" target="_blank" class="btn btn-danger btn-sm"><i class="fas fa-print me-1"></i>Print</a></div>
    <div class="card-body"><form method="GET" class="row g-3">
        <div class="col-md-3"><label class="form-label">From</label><input type="date" class="form-control" name="from_date" value="<?php echo $fromDate; ?>"></div>
        <div class="col-md-3"><label class="form-label">To</label><input type="date" class="form-control" name="to_date" value="<?php echo $toDate; ?>"></div>
        <div class="col-md-3"><label class="form-label">Expense Head</label><select class="form-select" name="head"><option value="">All Heads</option><?php foreach($expenseHeads as $eh):?><option value="<?php echo htmlspecialchars($eh['category_name']);?>" <?php echo $headFilter === $eh['category_name'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($eh['category_name']);?></option><?php endforeach;?></select></div>
        <div class="col-md-3 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Generate</button></div>
    </form></div>
</div></div></div>

<div class="row mb-3">
    <div class="col-md-3"><div class="card bg-danger text-white"><div class="card-body text-center"><h6>Total Expenses</h6><h3><?php echo formatCurrency($totalAmount);?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body text-center"><h6>Total Paid</h6><h3><?php echo formatCurrency($totalPaid);?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-warning text-white"><div class="card-body text-center"><h6>Outstanding</h6><h3><?php echo formatCurrency($totalAmount - $totalPaid);?></h3></div></div></div>
    <div class="col-md-3"><div class="card bg-info text-white"><div class="card-body text-center"><h6>Entries</h6><h3><?php echo count($expenses);?></h3></div></div></div>
</div>

<div class="row mb-3"><div class="col-md-6"><div class="card shadow-sm">
    <div class="card-header"><i class="fas fa-chart-pie me-2"></i>By Expense Head</div>
    <div class="card-body"><table class="table table-striped"><thead><tr><th>Head</th><th class="text-end">Count</th><th class="text-end">Amount</th><th class="text-end">% of Total</th></tr></thead><tbody>
    <?php foreach($byCategory as $cat): ?>
    <tr><td><span class="badge bg-primary"><?php echo htmlspecialchars($cat['category']);?></span></td><td class="text-end"><?php echo $cat['cnt'];?></td><td class="text-end fw-bold"><?php echo formatCurrency($cat['total_amt']);?></td><td class="text-end"><?php echo $totalAmount > 0 ? number_format(($cat['total_amt']/$totalAmount)*100,1).'%' : '0%';?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div></div>

<div class="col-md-6"><div class="card shadow-sm">
    <div class="card-header"><i class="fas fa-list me-2"></i>All Expenses</div>
    <div class="card-body" style="max-height:400px;overflow-y:auto;"><table class="table table-sm table-striped"><thead><tr><th>Date</th><th>Head</th><th>Amount</th><th>Status</th></tr></thead><tbody>
    <?php foreach($expenses as $ex): ?>
    <tr><td><?php echo date('d-m-Y', strtotime($ex['expense_date']));?></td><td><?php echo htmlspecialchars($ex['category']);?></td><td class="text-end"><?php echo formatCurrency($ex['amount']);?></td><td><span class="badge bg-<?php echo $ex['payment_status']==='paid'?'success':($ex['payment_status']==='partial'?'warning':'danger');?>"><?php echo ucfirst($ex['payment_status']);?></span></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div></div></div>

<?php include '../includes/footer.php'; ?>
