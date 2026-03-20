<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('settings.manage');
$companyId = is_super_admin() ? request_int('company_id', current_company_id() ?? 0) : (int) current_company_id();
if ($companyId < 1) {
    set_flash('warning', 'Select a company to manage settings.');
    redirect('/modules/dashboard/index.php');
}
require_company_access($companyId);
$stmt = db()->prepare('SELECT name FROM companies WHERE id = :id');
$stmt->execute(['id' => $companyId]);
$company = $stmt->fetch();
if (!$company) {
    redirect('/modules/dashboard/index.php');
}
$defaults = [
    'company_name' => $company['name'],
    'company_email' => '',
    'invoice_prefix' => 'INV-',
    'currency' => 'USD',
    'timezone' => 'UTC',
];
$settingStmt = db()->prepare('SELECT setting_key, setting_value FROM settings WHERE company_id = :company_id');
$settingStmt->execute(['company_id' => $companyId]);
$current = $defaults;
foreach ($settingStmt->fetchAll() as $row) {
    $current[$row['setting_key']] = $row['setting_value'];
}
if (is_post()) {
    verify_csrf();
    $keys = array_keys($defaults);
    db()->beginTransaction();
    try {
        $save = db()->prepare('INSERT INTO settings (company_id, setting_key, setting_value, created_at, updated_at) VALUES (:company_id, :setting_key, :setting_value, NOW(), NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()');
        foreach ($keys as $key) {
            $save->execute(['company_id' => $companyId, 'setting_key' => $key, 'setting_value' => request_string($key)]);
        }
        db()->commit();
        set_flash('success', 'Settings saved successfully.');
        redirect('/modules/settings/index.php' . (is_super_admin() ? '?company_id=' . $companyId : ''));
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
}
$pageTitle = 'Settings';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body">
<h1 class="h3 mb-3">Settings for <?= h($company['name']) ?></h1>
<form method="post" class="row g-3"><?= csrf_field() ?>
<?php if (is_super_admin()): ?><div class="col-md-6"><label class="form-label">Company</label><select class="form-select" onchange="window.location='?company_id='+this.value"><option value="">Select company</option><?php $companies = db()->query('SELECT id, name FROM companies ORDER BY name')->fetchAll(); foreach ($companies as $tenant): ?><option value="<?= (int) $tenant['id'] ?>" <?= $companyId === (int) $tenant['id'] ? 'selected' : '' ?>><?= h($tenant['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
<div class="col-md-6"><label class="form-label">Company Name</label><input class="form-control" name="company_name" value="<?= h($current['company_name']) ?>"></div>
<div class="col-md-6"><label class="form-label">Company Email</label><input class="form-control" type="email" name="company_email" value="<?= h($current['company_email']) ?>"></div>
<div class="col-md-3"><label class="form-label">Invoice Prefix</label><input class="form-control" name="invoice_prefix" value="<?= h($current['invoice_prefix']) ?>"></div>
<div class="col-md-3"><label class="form-label">Currency</label><input class="form-control" name="currency" value="<?= h($current['currency']) ?>"></div>
<div class="col-md-3"><label class="form-label">Timezone</label><input class="form-control" name="timezone" value="<?= h($current['timezone']) ?>"></div>
<div class="col-12"><button class="btn btn-primary">Save Settings</button></div>
</form>
</div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
