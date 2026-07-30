<?php
if (!isset($company)) {
    try {
        $company = getRow("SELECT * FROM company_settings LIMIT 1");
    } catch (\Throwable $e) {
        $company = null;
    }
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
            <td style="width:55%;">
                <div class="company-name"><?php echo CLIENT_NAME; ?></div>
                <div style="font-size:10px;color:#555;line-height:1.4;"><?php echo CLIENT_REG; ?> | Prop: <?php echo CLIENT_PERSON; ?></div>
                <div style="font-size:10px;color:#777;line-height:1.4;"><?php echo CLIENT_ADDRESS; ?> | Ph: <?php echo CLIENT_PHONE; ?></div>
            </td>
            <td style="width:45%; text-align:right; font-size:10px; vertical-align:top;">
                <div style="background:#f0f0f0;padding:6px 10px;border-radius:4px;display:inline-block;text-align:left;">
                    <div><strong>Date:</strong> <?php echo date('d-m-Y'); ?></div>
                    <div><strong>Time:</strong> <?php echo date('h:i A'); ?></div>
                    <div><strong>User:</strong> <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin'; ?></div>
                </div>
            </td>
        </tr>
    </table>
    <?php if (isset($pageTitle) && $pageTitle): ?>
        <div class="report-title"><?php echo htmlspecialchars($pageTitle); ?>
            <?php if (isset($fromDate) && isset($toDate)): ?>
                <span class="date-range"> | <?php echo formatDate($fromDate); ?> to <?php echo formatDate($toDate); ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
