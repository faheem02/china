<?php
require_once '../includes/database.php';
requireLogin();

$pageTitle = 'Company Settings';
$message = '';
$messageType = '';

$flash = getFlash();
if ($flash) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

$company = getRow("SELECT * FROM company_settings LIMIT 1");

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name']);
    $company_tagline = trim($_POST['company_tagline']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $website = trim($_POST['website']);

    if (empty($company_name)) {
        $message = 'Company name is required!';
        $messageType = 'danger';
    } else {
        if ($company) {
            $result = modifyData("UPDATE company_settings SET company_name=?, company_tagline=?, address=?, phone=?, email=?, website=?, date_time=? WHERE id=?",
                'ssssssss', [$company_name, $company_tagline, $address, $phone, $email, $website, getCurrentDateTime(), $company['id']]);
        } else {
            $result = insertData("INSERT INTO company_settings (company_name, company_tagline, address, phone, email, website, date_time) VALUES (?,?,?,?,?,?,?)",
                'sssssss', [$company_name, $company_tagline, $address, $phone, $email, $website, getCurrentDateTime()]);
        }
        if ($result !== false) {
            setFlash('Company settings updated successfully!', 'success');
            header('Location: company_settings.php');
            exit;
        } else {
            $message = 'Error saving company settings!';
            $messageType = 'danger';
        }
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-building me-2"></i>Company Information
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <p class="text-muted mb-3">This information appears on all printed invoices, receipts, and reports.</p>

                <form method="POST">
                    <div class="mb-3">
                        <label for="company_name" class="form-label">Company Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="company_name" name="company_name"
                               value="<?php echo htmlspecialchars($company['company_name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="company_tagline" class="form-label">Tagline / Subtitle</label>
                        <input type="text" class="form-control" id="company_tagline" name="company_tagline"
                               value="<?php echo htmlspecialchars($company['company_tagline'] ?? ''); ?>"
                               placeholder="e.g. Manufacturing ERP System">
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($company['address'] ?? ''); ?></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone"
                                   value="<?php echo htmlspecialchars($company['phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo htmlspecialchars($company['email'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="website" class="form-label">Website</label>
                        <input type="text" class="form-control" id="website" name="website"
                               value="<?php echo htmlspecialchars($company['website'] ?? ''); ?>"
                               placeholder="www.company.com">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Settings</button>
                        <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Preview -->
        <div class="card mt-3">
            <div class="card-header">
                <i class="fas fa-eye me-2"></i>Print Preview
            </div>
            <div class="card-body text-center" style="border-bottom:3px solid #1a2332; padding-bottom:20px;">
                <h4 class="fw-bold" style="color:#1a2332;"><?php echo htmlspecialchars($company['company_name'] ?? 'Your Company Name'); ?></h4>
                <?php if ($company['company_tagline'] ?? null): ?>
                    <div class="text-muted" style="font-size:13px;"><?php echo htmlspecialchars($company['company_tagline']); ?></div>
                <?php endif; ?>
                <?php if ($company['address'] ?? null): ?>
                    <div style="font-size:12px;"><?php echo htmlspecialchars($company['address']); ?></div>
                <?php endif; ?>
                <?php if ($company['phone'] || $company['email']): ?>
                    <div style="font-size:12px;">
                        <?php if ($company['phone']): ?>Ph: <?php echo htmlspecialchars($company['phone']); ?><?php endif; ?>
                        <?php if ($company['phone'] && $company['email']): ?> | <?php endif; ?>
                        <?php if ($company['email']): ?><?php echo htmlspecialchars($company['email']); ?><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
