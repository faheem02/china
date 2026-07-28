<?php
require_once '../includes/database.php';

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['balance' => 0, 'balance_type' => 'receivable']);
    exit;
}

$customer = getRow("SELECT opening_balance, opening_balance_type FROM customers WHERE id = ?", 'i', [$id]);
if (!$customer) {
    echo json_encode(['balance' => 0, 'balance_type' => 'receivable']);
    exit;
}

$saleData = getRow("SELECT COALESCE(SUM(total_amount), 0) as total_sales,
                           COALESCE(SUM(paid_amount), 0) as total_paid
                    FROM sales WHERE customer_id = ? AND status = 'completed'", 'i', [$id]);

$receiptData = getRow("SELECT COALESCE(SUM(amount), 0) as total_receipts FROM customer_receipts WHERE customer_id = ?", 'i', [$id]);

$opening = (float)$customer['opening_balance'];
if (($customer['opening_balance_type'] ?? 'receivable') === 'payable') {
    $opening = -$opening;
}
$totalSales = (float)($saleData['total_sales'] ?? 0);
$totalPaid = (float)($saleData['total_paid'] ?? 0);
$totalReceipts = (float)($receiptData['total_receipts'] ?? 0);

$balance = $opening + $totalSales - $totalPaid - $totalReceipts;
$balanceType = $balance >= 0 ? 'receivable' : 'payable';

echo json_encode(['balance' => round($balance, 2), 'balance_type' => $balanceType]);
