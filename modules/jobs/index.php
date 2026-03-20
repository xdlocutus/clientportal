<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('jobs.view');
if (!jobs_storage_available()) {
    set_flash('danger', 'Jobcard storage is not available in this environment.');
    redirect('/modules/dashboard/index.php');
}

$sql = 'SELECT jobcards.*, clients.company_name, clients.contact_name, assigned.full_name AS assigned_name
        FROM jobcards
        INNER JOIN clients ON clients.id = jobcards.client_id
        LEFT JOIN users AS assigned ON assigned.id = jobcards.assigned_user_id
        WHERE ' . company_scope_sql('company_id', 'jobcards');
$params = company_scope_params();
if (is_client_role()) {
    $sql .= ' AND jobcards.client_id = :client_id';
    $params['client_id'] = current_client_id();
}
$sql .= ' ORDER BY jobcards.scheduled_for DESC, jobcards.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$pageTitle = 'Jobcards';
require BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-0">Jobcards</h1>
        <p class="text-body-secondary mb-0">Book field work, brief the technician, and capture customer sign-off in one place.</p>
    </div>
    <?php if (has_permission('jobs.create')): ?>
        <a class="btn btn-primary" href="/modules/jobs/add.php">Book Job</a>
    <?php endif; ?>
</div>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped mb-0 align-middle">
            <thead>
                <tr>
                    <th>Job</th>
                    <th>Client</th>
                    <th>Scheduled</th>
                    <th>Technician</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Signed</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jobs as $job): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= h($job['job_number']) ?></div>
                            <div class="small text-body-secondary"><?= h($job['title']) ?></div>
                        </td>
                        <td>
                            <div><?= h($job['company_name']) ?></div>
                            <div class="small text-body-secondary"><?= h((string) $job['contact_name']) ?></div>
                        </td>
                        <td><?= h(format_datetime_display($job['scheduled_for'])) ?></td>
                        <td><?= h((string) ($job['assigned_name'] ?: 'Unassigned')) ?></td>
                        <td><?= invoice_status_badge($job['priority']) ?></td>
                        <td><?= invoice_status_badge($job['status']) ?></td>
                        <td><?= $job['client_signed_at'] ? '<span class="badge bg-success">Signed</span>' : '<span class="badge bg-secondary">Pending</span>' ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="/modules/jobs/view.php?id=<?= (int) $job['id'] ?>">View</a>
                            <?php if (has_permission('jobs.edit') && !is_client_role()): ?>
                                <a class="btn btn-sm btn-outline-primary" href="/modules/jobs/edit.php?id=<?= (int) $job['id'] ?>">Edit</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$jobs): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No jobcards found yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
