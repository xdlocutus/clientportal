<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('jobs.create');
if (!jobs_storage_available()) {
    set_flash('danger', 'Jobcard storage is not available in this environment.');
    redirect('/modules/dashboard/index.php');
}

$defaultJobNumber = 'JOB-' . date('Ymd-His');
if (is_post()) {
    verify_csrf();
    $companyId = is_super_admin() ? request_int('company_id') : (int) current_company_id();
    require_company_access($companyId);

    $stmt = db()->prepare('INSERT INTO jobcards (
        company_id, client_id, created_by_user_id, assigned_user_id, job_number, title, job_type, scheduled_for,
        status, priority, site_contact_name, site_contact_phone, service_address, scope_of_work,
        access_instructions, materials_required, internal_notes, created_at, updated_at
    ) VALUES (
        :company_id, :client_id, :created_by_user_id, :assigned_user_id, :job_number, :title, :job_type, :scheduled_for,
        :status, :priority, :site_contact_name, :site_contact_phone, :service_address, :scope_of_work,
        :access_instructions, :materials_required, :internal_notes, NOW(), NOW()
    )');
    $stmt->execute([
        'company_id' => $companyId,
        'client_id' => request_int('client_id'),
        'created_by_user_id' => current_user_id(),
        'assigned_user_id' => request_int('assigned_user_id') ?: null,
        'job_number' => request_string('job_number', $defaultJobNumber),
        'title' => request_string('title'),
        'job_type' => request_string('job_type'),
        'scheduled_for' => normalize_datetime_input(request_string('scheduled_for')) ?? now(),
        'status' => request_string('status', 'scheduled'),
        'priority' => request_string('priority', 'medium'),
        'site_contact_name' => request_string('site_contact_name'),
        'site_contact_phone' => request_string('site_contact_phone'),
        'service_address' => request_string('service_address'),
        'scope_of_work' => request_string('scope_of_work'),
        'access_instructions' => request_string('access_instructions'),
        'materials_required' => request_string('materials_required'),
        'internal_notes' => request_string('internal_notes'),
    ]);

    set_flash('success', 'Jobcard booked successfully.');
    redirect('/modules/jobs/view.php?id=' . (int) db()->lastInsertId());
}

$pageTitle = 'Book Job';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body">
<form method="post" class="row g-3"><?= csrf_field() ?>
    <?php if (is_super_admin()): ?><div class="col-md-6"><label class="form-label">Tenant</label><select class="form-select" name="company_id" required><option value="">Select company</option><?= company_select_options(request_int('company_id')) ?></select></div><?php endif; ?>
    <div class="col-md-6"><label class="form-label">Client</label><select class="form-select" name="client_id" required><option value="">Select client</option><?= client_select_options(request_int('client_id') ?: null, is_super_admin() ? request_int('company_id') ?: null : current_company_id()) ?></select></div>
    <div class="col-md-4"><label class="form-label">Job Number</label><input class="form-control" name="job_number" value="<?= h(request_string('job_number', $defaultJobNumber)) ?>" required></div>
    <div class="col-md-4"><label class="form-label">Job Type</label><input class="form-control" name="job_type" value="<?= h(request_string('job_type')) ?>" placeholder="Solar install, fault callout, rewire"></div>
    <div class="col-md-4"><label class="form-label">Scheduled For</label><input class="form-control" type="datetime-local" name="scheduled_for" value="<?= h(format_datetime_local(request_string('scheduled_for') ?: date('Y-m-d H:i:s'))) ?>" required></div>
    <div class="col-md-8"><label class="form-label">Job Title</label><input class="form-control" name="title" value="<?= h(request_string('title')) ?>" placeholder="Install inverter and commission system" required></div>
    <div class="col-md-4"><label class="form-label">Assigned Technician</label><select class="form-select" name="assigned_user_id"><option value="">Unassigned</option><?= technician_select_options(request_int('assigned_user_id') ?: null, is_super_admin() ? request_int('company_id') ?: null : current_company_id()) ?></select></div>
    <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['scheduled','in_progress','completed','on_hold','cancelled'] as $status): ?><option value="<?= h($status) ?>" <?= request_string('status', 'scheduled') === $status ? 'selected' : '' ?>><?= h(ucfirst(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Priority</label><select class="form-select" name="priority"><?php foreach (['low','medium','high','urgent'] as $priority): ?><option value="<?= h($priority) ?>" <?= request_string('priority', 'medium') === $priority ? 'selected' : '' ?>><?= h(ucfirst($priority)) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Site Contact</label><input class="form-control" name="site_contact_name" value="<?= h(request_string('site_contact_name')) ?>"></div>
    <div class="col-md-3"><label class="form-label">Contact Phone</label><input class="form-control" name="site_contact_phone" value="<?= h(request_string('site_contact_phone')) ?>"></div>
    <div class="col-12"><label class="form-label">Service Address</label><textarea class="form-control" name="service_address" rows="2" placeholder="Full job address and access reference"><?= h(request_string('service_address')) ?></textarea></div>
    <div class="col-12"><label class="form-label">Scope of Work</label><textarea class="form-control" name="scope_of_work" rows="4" placeholder="What the technician is booked to do, expected outputs, and compliance requirements."><?= h(request_string('scope_of_work')) ?></textarea></div>
    <div class="col-md-6"><label class="form-label">Access Instructions</label><textarea class="form-control" name="access_instructions" rows="4" placeholder="Gate code, roof access, isolator location, parking instructions."><?= h(request_string('access_instructions')) ?></textarea></div>
    <div class="col-md-6"><label class="form-label">Materials Required</label><textarea class="form-control" name="materials_required" rows="4" placeholder="Panels, cable, breakers, labels, PPE, test equipment."><?= h(request_string('materials_required')) ?></textarea></div>
    <div class="col-12"><label class="form-label">Internal Booking Notes</label><textarea class="form-control" name="internal_notes" rows="4" placeholder="Anything the office team wants the technician to know before dispatch."><?= h(request_string('internal_notes')) ?></textarea></div>
    <div class="col-12"><button class="btn btn-primary">Save Jobcard</button> <a class="btn btn-link" href="/modules/jobs/index.php">Cancel</a></div>
</form>
</div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
