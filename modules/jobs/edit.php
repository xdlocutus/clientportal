<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('jobs.edit');
if (!jobs_storage_available()) {
    set_flash('danger', 'Jobcard storage is not available in this environment.');
    redirect('/modules/dashboard/index.php');
}

$id = request_int('id');
$stmt = db()->prepare('SELECT * FROM jobcards WHERE id = :id AND ' . company_scope_sql());
$stmt->execute(['id' => $id] + company_scope_params());
$job = $stmt->fetch();
if (!$job) {
    redirect('/modules/jobs/index.php');
}

if (is_post()) {
    verify_csrf();
    $companyId = is_super_admin() ? request_int('company_id', (int) $job['company_id']) : (int) current_company_id();
    require_company_access($companyId);

    $update = db()->prepare('UPDATE jobcards SET
        company_id = :company_id,
        client_id = :client_id,
        assigned_user_id = :assigned_user_id,
        job_number = :job_number,
        title = :title,
        job_type = :job_type,
        scheduled_for = :scheduled_for,
        status = :status,
        priority = :priority,
        site_contact_name = :site_contact_name,
        site_contact_phone = :site_contact_phone,
        service_address = :service_address,
        scope_of_work = :scope_of_work,
        access_instructions = :access_instructions,
        materials_required = :materials_required,
        internal_notes = :internal_notes,
        updated_at = NOW()
        WHERE id = :id');
    $update->execute([
        'id' => $id,
        'company_id' => $companyId,
        'client_id' => request_int('client_id'),
        'assigned_user_id' => request_int('assigned_user_id') ?: null,
        'job_number' => request_string('job_number', (string) $job['job_number']),
        'title' => request_string('title'),
        'job_type' => request_string('job_type'),
        'scheduled_for' => normalize_datetime_input(request_string('scheduled_for')) ?? (string) $job['scheduled_for'],
        'status' => request_string('status', (string) $job['status']),
        'priority' => request_string('priority', (string) $job['priority']),
        'site_contact_name' => request_string('site_contact_name'),
        'site_contact_phone' => request_string('site_contact_phone'),
        'service_address' => request_string('service_address'),
        'scope_of_work' => request_string('scope_of_work'),
        'access_instructions' => request_string('access_instructions'),
        'materials_required' => request_string('materials_required'),
        'internal_notes' => request_string('internal_notes'),
    ]);

    set_flash('success', 'Jobcard updated successfully.');
    redirect('/modules/jobs/view.php?id=' . $id);
}

$pageTitle = 'Edit Jobcard';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body">
<form method="post" class="row g-3"><?= csrf_field() ?>
    <?php if (is_super_admin()): ?><div class="col-md-6"><label class="form-label">Tenant</label><select class="form-select" name="company_id" required><?= company_select_options((int) $job['company_id']) ?></select></div><?php endif; ?>
    <div class="col-md-6"><label class="form-label">Client</label><select class="form-select" name="client_id" required><?= client_select_options((int) $job['client_id'], (int) $job['company_id']) ?></select></div>
    <div class="col-md-4"><label class="form-label">Job Number</label><input class="form-control" name="job_number" value="<?= h((string) $job['job_number']) ?>" required></div>
    <div class="col-md-4"><label class="form-label">Job Type</label><input class="form-control" name="job_type" value="<?= h((string) $job['job_type']) ?>"></div>
    <div class="col-md-4"><label class="form-label">Scheduled For</label><input class="form-control" type="datetime-local" name="scheduled_for" value="<?= h(format_datetime_local((string) $job['scheduled_for'])) ?>" required></div>
    <div class="col-md-8"><label class="form-label">Job Title</label><input class="form-control" name="title" value="<?= h((string) $job['title']) ?>" required></div>
    <div class="col-md-4"><label class="form-label">Assigned Technician</label><select class="form-select" name="assigned_user_id"><option value="">Unassigned</option><?= technician_select_options($job['assigned_user_id'] !== null ? (int) $job['assigned_user_id'] : null, (int) $job['company_id']) ?></select></div>
    <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['scheduled','in_progress','completed','on_hold','cancelled'] as $status): ?><option value="<?= h($status) ?>" <?= $job['status'] === $status ? 'selected' : '' ?>><?= h(ucfirst(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Priority</label><select class="form-select" name="priority"><?php foreach (['low','medium','high','urgent'] as $priority): ?><option value="<?= h($priority) ?>" <?= $job['priority'] === $priority ? 'selected' : '' ?>><?= h(ucfirst($priority)) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Site Contact</label><input class="form-control" name="site_contact_name" value="<?= h((string) $job['site_contact_name']) ?>"></div>
    <div class="col-md-3"><label class="form-label">Contact Phone</label><input class="form-control" name="site_contact_phone" value="<?= h((string) $job['site_contact_phone']) ?>"></div>
    <div class="col-12"><label class="form-label">Service Address</label><textarea class="form-control" name="service_address" rows="2"><?= h((string) $job['service_address']) ?></textarea></div>
    <div class="col-12"><label class="form-label">Scope of Work</label><textarea class="form-control" name="scope_of_work" rows="4"><?= h((string) $job['scope_of_work']) ?></textarea></div>
    <div class="col-md-6"><label class="form-label">Access Instructions</label><textarea class="form-control" name="access_instructions" rows="4"><?= h((string) $job['access_instructions']) ?></textarea></div>
    <div class="col-md-6"><label class="form-label">Materials Required</label><textarea class="form-control" name="materials_required" rows="4"><?= h((string) $job['materials_required']) ?></textarea></div>
    <div class="col-12"><label class="form-label">Internal Booking Notes</label><textarea class="form-control" name="internal_notes" rows="4"><?= h((string) $job['internal_notes']) ?></textarea></div>
    <div class="col-12"><button class="btn btn-primary">Update Jobcard</button> <a class="btn btn-link" href="/modules/jobs/view.php?id=<?= (int) $job['id'] ?>">Cancel</a></div>
</form>
</div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
