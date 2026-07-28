<?php
require_once '../includes/database.php';
requireLogin();

$paymentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($paymentId <= 0) {
    header('Location: chinese_supplier_payment.php');
    exit;
}

$payment = getRow("SELECT p.*, s.supplier_name, s.company_name, s.phone, s.email, s.address 
    FROM chinese_supplier_payments p 
    LEFT JOIN chinese_suppliers s ON p.supplier_id = s.id 
    WHERE p.id = ?", 'i', [$paymentId]);

if (!$payment) {
    header('Location: chinese_supplier_payment.php');
    exit;
}

$company = getRow("SELECT * FROM company_settings LIMIT 1");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt (CNY)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Poppins', sans-serif; }
        body { background: #f8f9fa; padding: 50px; }
        .receipt { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .receipt-header { text-align: center; border-bottom: 2px solid #1a2332; padding-bottom: 20px; margin-bottom: 30px; }
        .receipt-header h2 { color: #1a2332; font-weight: 700; }
        .receipt-header small { color: #6c757d; }
        .receipt-details table td { padding: 8px 0; }
        .receipt-details table td:first-child { font-weight: 600; width: 40%; color: #1a2332; }
        .receipt-details table td:last-child { color: #333; }
        .receipt-amount { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; margin: 30px 0; }
        .receipt-amount h1 { color: #28a745; font-weight: 700; }
        .receipt-amount .pkr-amount { color: #6c757d; font-size: 16px; margin-top: 5px; }
        .receipt-footer { border-top: 2px solid #e8eaed; padding-top: 20px; margin-top: 30px; text-align: center; color: #6c757d; }
        @media print { body { background: #fff; padding: 20px; } .btn { display: none !important; } .no-print { display: none !important; } .receipt { box-shadow: none; border: 1px solid #ddd; } }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="text-end no-print mb-3">
            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-2"></i>Print Receipt</button>
            <a href="chinese_supplier_payment.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>
        </div>
        
        <div class="receipt-header">
            <h2><i class="fas fa-receipt me-2"></i>Payment Receipt (CNY)</h2>
            <small><?php echo htmlspecialchars($company['company_name'] ?? 'ERP System'); ?></small>
            <?php if ($company['address'] ?? null): ?><br><small><?php echo htmlspecialchars($company['address']); ?></small><?php endif; ?>
            <?php if ($company['phone'] ?? null): ?><br><small>Ph: <?php echo htmlspecialchars($company['phone']); ?></small><?php endif; ?>
        </div>
        
        <div class="receipt-details">
            <table class="table table-borderless">
                <tr>
                    <td>Payment No</td>
                    <td><strong><?php echo htmlspecialchars($payment['payment_no']); ?></strong></td>
                </tr>
                <tr>
                    <td>Payment Date</td>
                    <td><?php echo date('d-m-Y', strtotime($payment['payment_date'])); ?></td>
                </tr>
                <tr>
                    <td>Supplier Name</td>
                    <td><strong><?php echo htmlspecialchars($payment['supplier_name']); ?></strong></td>
                </tr>
                <?php if ($payment['company_name']): ?>
                <tr>
                    <td>Company</td>
                    <td><?php echo htmlspecialchars($payment['company_name']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($payment['phone']): ?>
                <tr>
                    <td>Phone</td>
                    <td><?php echo htmlspecialchars($payment['phone']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($payment['email']): ?>
                <tr>
                    <td>Email</td>
                    <td><?php echo htmlspecialchars($payment['email']); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Payment Type</td>
                    <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_type'])); ?></td>
                </tr>
                <tr>
                    <td>Exchange Rate</td>
                    <td>1 CNY = <?php echo number_format($payment['exchange_rate'], 4); ?> PKR</td>
                </tr>
                <?php if ($payment['bank_name']): ?>
                <tr>
                    <td>Bank Name</td>
                    <td><?php echo htmlspecialchars($payment['bank_name']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($payment['cheque_no']): ?>
                <tr>
                    <td>Cheque No</td>
                    <td><?php echo htmlspecialchars($payment['cheque_no']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($payment['reference_no']): ?>
                <tr>
                    <td>Reference No</td>
                    <td><?php echo htmlspecialchars($payment['reference_no']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($payment['notes']): ?>
                <tr>
                    <td>Notes</td>
                    <td><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <div class="receipt-amount">
            <small class="text-muted">Payment Amount (CNY)</small>
            <h1>¥ <?php echo number_format($payment['amount_cny'], 2); ?></h1>
            <div class="pkr-amount">Equivalent: PKR <?php echo number_format($payment['amount_pkr'], 2); ?></div>
        </div>
        
        <div class="receipt-footer">
            <p>Thank you for your payment!</p>
            <small>This is a system-generated receipt.</small>
        </div>
    </div>
</body>
</html>
