<?php
if (!isset($company)) {
    $company = getRow("SELECT * FROM company_settings LIMIT 1");
}
?>
<style>
@media print {
    .btn, .navbar-custom, #sidebar, .sidebar-header, .sidebar-nav, .no-print, form, .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_length { display: none !important; }
    #sidebar { width: 0 !important; }
    #content { margin-left: 0 !important; padding: 10px !important; }
    .print-header { display: block !important; }
    .print-header table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .print-header td { padding: 2px 5px; font-size: 12px; }
    .print-header .company-name { font-size: 18px; font-weight: bold; }
    .print-header .tagline { font-size: 11px; color: #555; }
    .print-header .report-title { font-size: 14px; font-weight: bold; background: #f0f0f0; padding: 5px; text-align: center; border: 1px solid #ccc; margin-top: 5px; }
    .print-header .date-range { font-size: 11px; color: #666; }
    body { font-size: 11px !important; }
    .table { font-size: 11px !important; }
    .card { border: none !important; box-shadow: none !important; }
}
@media screen {
    .print-header { display: none; }
}
</style>

<div class="print-header">
    <table>
        <tr>
            <td style="width:60%">
                <div class="company-name"><?php echo htmlspecialchars($company['company_name'] ?? 'ERP System'); ?></div>
                <div class="tagline"><?php echo htmlspecialchars($company['company_tagline'] ?? ''); ?></div>
                <?php if ($company['address']): ?><div style="font-size:11px;"><?php echo htmlspecialchars($company['address']); ?></div><?php endif; ?>
                <?php if ($company['phone'] || $company['email']): ?>
                <div style="font-size:11px;">
                    <?php if ($company['phone']): ?>Ph: <?php echo htmlspecialchars($company['phone']); ?><?php endif; ?>
                    <?php if ($company['phone'] && $company['email']): ?> | <?php endif; ?>
                    <?php if ($company['email']): ?><?php echo htmlspecialchars($company['email']); ?><?php endif; ?>
                </div>
                <?php endif; ?>
            </td>
            <td style="width:40%; text-align:right; font-size:11px;">
                <div>Date: <?php echo date('d-m-Y'); ?></div>
                <div>Time: <?php echo date('h:i A'); ?></div>
                <div>User: <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin'; ?></div>
            </td>
        </tr>
    </table>
    <?php if (isset($pageTitle) && $pageTitle): ?>
        <div class="report-title"><?php echo htmlspecialchars($pageTitle); ?>
            <?php if (isset($fromDate) && isset($toDate)): ?>
                <span class="date-range"> | <?php echo date('d-m-Y', strtotime($fromDate)); ?> to <?php echo date('d-m-Y', strtotime($toDate)); ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
