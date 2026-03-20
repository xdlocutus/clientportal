<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('services.manage');
if (is_post()) {
    verify_csrf();
    $companyId = is_super_admin() ? request_int('company_id') : (int) current_company_id();
    require_company_access($companyId);
    $stmt = db()->prepare('INSERT INTO services (company_id, client_id, service_name, description, price, billing_cycle, start_date, end_date, status, created_at, updated_at) VALUES (:company_id, :client_id, :service_name, :description, :price, :billing_cycle, :start_date, :end_date, :status, NOW(), NOW())');
    $stmt->execute([
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
    set_flash('success', 'Service created successfully.');
    redirect('/modules/services/index.php');
}
$pageTitle = 'Add Service';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body"><form method="post" class="row g-3"><?= csrf_field() ?>
<?php if (is_super_admin()): ?><div class="col-md-6"><label class="form-label">Tenant</label><select class="form-select" name="company_id" required><option value="">Select company</option><?= company_select_options() ?></select></div><?php endif; ?>
<div class="col-md-6"><label class="form-label">Client</label><select class="form-select" name="client_id" required><option value="">Select client</option><?= client_select_options() ?></select></div>
<div class="col-md-6"><label class="form-label">Service Name</label><input class="form-control" name="service_name" required></div>
<div class="col-md-6"><label class="form-label">Price</label><input class="form-control" name="price" type="number" step="0.01" required></div>
<div class="col-md-6"><label class="form-label">Billing Cycle</label><select class="form-select" name="billing_cycle"><option value="one_time">One Time</option><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="yearly">Yearly</option></select></div>
<div class="col-md-3"><label class="form-label">Start Date</label><input class="form-control" type="date" name="start_date"></div>
<div class="col-md-3"><label class="form-label">End Date</label><input class="form-control" type="date" name="end_date"></div>
<div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active">Active</option><option value="suspended">Suspended</option><option value="cancelled">Cancelled</option></select></div>
<div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="4"></textarea></div>
<div class="col-12"><button class="btn btn-primary">Save Service</button> <a class="btn btn-link" href="/modules/services/index.php">Cancel</a></div>
</form></div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
