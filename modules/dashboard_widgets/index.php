<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('dashboard_widgets.manage');

$defaultCompanyId = current_company_id() ?? 0;
if (is_super_admin() && $defaultCompanyId < 1) {
    $defaultCompanyId = (int) (db()->query('SELECT id FROM companies ORDER BY name LIMIT 1')->fetchColumn() ?: 0);
}

$companyId = is_super_admin() ? request_int('company_id', $defaultCompanyId) : (int) current_company_id();
if ($companyId < 1) {
    set_flash('warning', 'Select a company to manage dashboard widgets.');
    redirect('/modules/dashboard/index.php');
}

require_company_access($companyId);
$stmt = db()->prepare('SELECT id, name FROM companies WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $companyId]);
$company = $stmt->fetch();
if (!$company) {
    set_flash('danger', 'Company not found.');
    redirect('/modules/dashboard/index.php');
}

$catalog = dashboard_widget_catalog();
$currentWidgets = company_settings($companyId)['dashboard_widgets'] ?? '';
$hasSavedWidgets = $currentWidgets !== '';
$selectedWidgets = [];
if ($currentWidgets !== '') {
    $decoded = json_decode((string) $currentWidgets, true);
    if (is_array($decoded)) {
        foreach ($decoded as $widget) {
            if (is_string($widget) && isset($catalog[$widget])) {
                $selectedWidgets[] = $widget;
            }
        }
    }
}
if (!$hasSavedWidgets) {
    $selectedWidgets = default_dashboard_widgets();
}

if (is_post()) {
    verify_csrf();
    $postedWidgets = [];
    foreach ((array) ($_POST['widgets'] ?? []) as $widget) {
        if (is_string($widget) && isset($catalog[$widget])) {
            $postedWidgets[] = $widget;
        }
    }
    $postedWidgets = array_values(array_unique($postedWidgets));

    $save = db()->prepare('INSERT INTO settings (company_id, setting_key, setting_value, created_at, updated_at) VALUES (:company_id, :setting_key, :setting_value, NOW(), NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()');
    $save->execute([
        'company_id' => $companyId,
        'setting_key' => 'dashboard_widgets',
        'setting_value' => json_encode($postedWidgets, JSON_THROW_ON_ERROR),
    ]);

    set_flash('success', 'Dashboard widgets updated successfully.');
    redirect('/modules/dashboard_widgets/index.php' . (is_super_admin() ? '?company_id=' . $companyId : ''));
}

$groupedCatalog = [];
foreach ($catalog as $key => $widget) {
    $groupedCatalog[$widget['group']][$key] = $widget;
}

$pageTitle = 'Dashboard Widgets';
$companyBranding = portal_branding($companyId);
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">Dashboard Widgets</h1>
                <p class="text-body-secondary mb-0">Choose which widgets appear on the dashboard for <?= h($company['name']) ?>.</p>
            </div>
            <a class="btn btn-outline-secondary" href="/modules/dashboard/index.php">Back to dashboard</a>
        </div>

        <form method="post" class="row g-3">
            <?= csrf_field() ?>
            <?php if (is_super_admin()): ?>
                <div class="col-md-6">
                    <label class="form-label">Company</label>
                    <select class="form-select" onchange="window.location='?company_id='+this.value">
                        <option value="">Select company</option>
                        <?php $companies = db()->query('SELECT id, name FROM companies ORDER BY name')->fetchAll(); ?>
                        <?php foreach ($companies as $tenant): ?>
                            <option value="<?= (int) $tenant['id'] ?>" <?= $companyId === (int) $tenant['id'] ? 'selected' : '' ?>><?= h($tenant['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="col-12">
                <div class="row g-3">
                    <?php foreach ($groupedCatalog as $group => $widgets): ?>
                        <div class="col-lg-6">
                            <div class="border rounded-3 p-3 h-100 bg-body-tertiary">
                                <h2 class="h5 mb-3"><?= h($group) ?></h2>
                                <?php foreach ($widgets as $key => $widget): ?>
                                    <label class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="widgets[]" value="<?= h($key) ?>" <?= in_array($key, $selectedWidgets, true) ? 'checked' : '' ?>>
                                        <span class="form-check-label">
                                            <span class="d-block fw-semibold"><?= h($widget['label']) ?></span>
                                            <span class="small text-body-secondary"><?= h($widget['description']) ?></span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary">Save Widgets</button>
                <a class="btn btn-link" href="/modules/settings/index.php<?= is_super_admin() ? '?company_id=' . $companyId : '' ?>">Company settings</a>
            </div>
        </form>
    </div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
