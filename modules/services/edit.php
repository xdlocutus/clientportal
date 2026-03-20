<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('services.manage');
$id = request_int('id');
$stmt = db()->prepare('SELECT * FROM services WHERE id = :id AND ' . (is_super_admin() ? '1=1' : 'company_id = :company_id'));
$stmt->execute(['id' => $id] + company_scope_params());
$service = $stmt->fetch();
if (!$service) {
    redirect('/modules/services/index.php');
}
if (is_post()) {
    verify_csrf();
    $companyId = is_super_admin() ? request_int('company_id', (int) $service['company_id']) : (int) current_company_id();
    require_company_access($companyId);
    $update = db()->prepare('UPDATE services SET company_id = :company_id, client_id = :client_id, service_name = :service_name, description = :description, price = :price, billing_cycle = :billing_cycle, start_date = :start_date, end_date = :end_date, status = :status, updated_at = NOW() WHERE id = :id');
    $update->execute([
        'id' => $id,
        'company_id' => $companyId,
        'client_id' => request_int('client_id'),
        'service_name' => request_string('service_name'),
        'description' => request_string('description'),
        'price' => (float) request_string('price', '0'),
        'billing_cycle' => request_string('billing_cycle', 'monthly'),
        'start_date' => request_string('start_date') ?: null,
        'end_date' => request_string('end_date') ?: null,
        'status' => request_string('status', 'active'),
    ]);
    set_flash('success', 'Service updated successfully.');
    redirect('/modules/services/index.php');
}
$pageTitle = 'Edit Service';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body"><form method="post" class="row g-3"><?= csrf_field() ?>
<?php if (is_super_admin()): ?><div class="col-md-6"><label class="form-label">Tenant</label><select class="form-select" name="company_id" required><?= company_select_options((int) $service['company_id']) ?></select></div><?php endif; ?>
<div class="col-md-6"><label class="form-label">Client</label><select class="form-select" name="client_id" required><?= client_select_options((int) $service['client_id'], (int) $service['company_id']) ?></select></div>
<div class="col-md-6"><label class="form-label">Service Name</label><input class="form-control" name="service_name" value="<?= h($service['service_name']) ?>" required></div>
<div class="col-md-6"><label class="form-label">Price</label><input class="form-control" name="price" type="number" step="0.01" value="<?= h((string) $service['price']) ?>" required></div>
<div class="col-md-6"><label class="form-label">Billing Cycle</label><select class="form-select" name="billing_cycle"><?php foreach (['one_time','monthly','quarterly','yearly'] as $cycle): ?><option value="<?= h($cycle) ?>" <?= $service['billing_cycle'] === $cycle ? 'selected' : '' ?>><?= h(ucfirst(str_replace('_', ' ', $cycle))) ?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><label class="form-label">Start Date</label><input class="form-control" type="date" name="start_date" value="<?= h((string) $service['start_date']) ?>"></div>
<div class="col-md-3"><label class="form-label">End Date</label><input class="form-control" type="date" name="end_date" value="<?= h((string) $service['end_date']) ?>"></div>
<div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['active','suspended','cancelled'] as $status): ?><option value="<?= h($status) ?>" <?= $service['status'] === $status ? 'selected' : '' ?>><?= h(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="4"><?= h($service['description']) ?></textarea></div>
<div class="col-12"><button class="btn btn-primary">Update Service</button> <a class="btn btn-link" href="/modules/services/index.php">Cancel</a></div>
</form></div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
