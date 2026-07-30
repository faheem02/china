<div style="display:flex;align-items:flex-start;gap:12px;padding:10px 0;border-bottom:2px solid #1a2332;margin-bottom:12px;">
    <div style="flex:1;min-width:0;">
        <div style="font-size:17px;font-weight:700;color:#1a2332;line-height:1.3;"><?php echo CLIENT_NAME; ?></div>
        <div style="font-size:10px;color:#555;line-height:1.4;"> Prop: <?php echo CLIENT_PERSON; ?></div>
        <div style="font-size:10px;color:#777;line-height:1.4;"><?php echo CLIENT_ADDRESS; ?> | Ph: <?php echo CLIENT_PHONE; ?></div>
    </div>
    <div style="text-align:right;font-size:10px;color:#555;white-space:nowrap;flex-shrink:0;">
        <div><strong>Date:</strong> <?php echo date('d-m-Y'); ?></div>
        <div><strong>Time:</strong> <?php echo date('h:i A'); ?></div>
        <div><strong>User:</strong> <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin'; ?></div>
    </div>
</div>
