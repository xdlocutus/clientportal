<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('jobs.view');
if (!jobs_storage_available()) {
    set_flash('danger', 'Jobcard storage is not available in this environment.');
    redirect('/modules/dashboard/index.php');
}
jobcard_notes_storage_available();

$id = request_int('id');
$sql = 'SELECT jobcards.*, clients.company_name, clients.contact_name, clients.email, clients.phone,
               assigned.full_name AS assigned_name, creator.full_name AS created_by_name
        FROM jobcards
        INNER JOIN clients ON clients.id = jobcards.client_id
        LEFT JOIN users AS assigned ON assigned.id = jobcards.assigned_user_id
        INNER JOIN users AS creator ON creator.id = jobcards.created_by_user_id
        WHERE jobcards.id = :id AND ' . company_scope_sql('company_id', 'jobcards');
$params = ['id' => $id] + company_scope_params();
if (is_client_role()) {
    $sql .= ' AND jobcards.client_id = :client_id';
    $params['client_id'] = current_client_id();
}
$stmt = db()->prepare($sql);
$stmt->execute($params);
$job = $stmt->fetch();
if (!$job) {
    redirect('/modules/jobs/index.php');
}

if (is_post()) {
    verify_csrf();

    if (request_string('action') === 'add_note' && has_permission('jobs.notes') && !is_client_role() && jobcard_notes_storage_available()) {
        $note = request_string('note');
        if ($note !== '') {
            $noteStmt = db()->prepare('INSERT INTO jobcard_notes (jobcard_id, user_id, note, created_at, updated_at) VALUES (:jobcard_id, :user_id, :note, NOW(), NOW())');
            $noteStmt->execute([
                'jobcard_id' => $id,
                'user_id' => current_user_id(),
                'note' => $note,
            ]);
            set_flash('success', 'Technician note added.');
        }

        redirect('/modules/jobs/view.php?id=' . $id);
    }

    if (request_string('action') === 'client_sign' && has_permission('jobs.sign') && is_client_role()) {
        $signatureName = request_string('client_signature_name', (string) (current_user()['full_name'] ?? ''));
        if ($signatureName !== '') {
            $signStmt = db()->prepare('UPDATE jobcards SET client_signature_name = :client_signature_name, client_signature_notes = :client_signature_notes, client_signed_at = NOW(), updated_at = NOW() WHERE id = :id');
            $signStmt->execute([
                'id' => $id,
                'client_signature_name' => $signatureName,
                'client_signature_notes' => request_string('client_signature_notes'),
            ]);
            set_flash('success', 'Jobcard signed successfully.');
        }

        redirect('/modules/jobs/view.php?id=' . $id);
    }
}

$notes = [];
if (jobcard_notes_storage_available()) {
    $notesStmt = db()->prepare('SELECT jobcard_notes.*, users.full_name FROM jobcard_notes INNER JOIN users ON users.id = jobcard_notes.user_id WHERE jobcard_notes.jobcard_id = :jobcard_id ORDER BY jobcard_notes.created_at DESC');
    $notesStmt->execute(['jobcard_id' => $id]);
    $notes = $notesStmt->fetchAll();
}

$pageTitle = 'View Jobcard';
require BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <div class="eyebrow-label">Jobcard</div>
        <h1 class="h3 mb-1"><?= h($job['job_number']) ?> · <?= h($job['title']) ?></h1>
        <p class="text-body-secondary mb-0">For site teams handling installs, fault calls, maintenance, and commissioning work.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <span><?= invoice_status_badge($job['status']) ?></span>
        <?php if (has_permission('jobs.edit') && !is_client_role()): ?>
            <a class="btn btn-outline-primary" href="/modules/jobs/edit.php?id=<?= (int) $job['id'] ?>">Edit Job</a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="/modules/jobs/index.php">Back to Jobcards</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm mb-4"><div class="card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <h2 class="h5 mb-3">Booking Details</h2>
                    <p class="mb-2"><strong>Client:</strong> <?= h($job['company_name']) ?></p>
                    <p class="mb-2"><strong>Primary Contact:</strong> <?= h((string) $job['contact_name']) ?></p>
                    <p class="mb-2"><strong>Client Email:</strong> <?= h((string) $job['email']) ?></p>
                    <p class="mb-0"><strong>Client Phone:</strong> <?= h((string) $job['phone']) ?></p>
                </div>
                <div class="col-md-6">
                    <h2 class="h5 mb-3">Dispatch</h2>
                    <p class="mb-2"><strong>Scheduled:</strong> <?= h(format_datetime_display($job['scheduled_for'])) ?></p>
                    <p class="mb-2"><strong>Technician:</strong> <?= h((string) ($job['assigned_name'] ?: 'Unassigned')) ?></p>
                    <p class="mb-2"><strong>Priority:</strong> <?= h(ucfirst((string) $job['priority'])) ?></p>
                    <p class="mb-0"><strong>Booked By:</strong> <?= h((string) $job['created_by_name']) ?></p>
                </div>
            </div>
            <hr>
            <div class="row g-4">
                <div class="col-12">
                    <h2 class="h5 mb-2">Service Address</h2>
                    <div class="p-3 bg-light rounded"><?= nl2br(h((string) $job['service_address'])) ?></div>
                </div>
                <div class="col-12">
                    <h2 class="h5 mb-2">Scope of Work</h2>
                    <div class="p-3 bg-light rounded"><?= nl2br(h((string) $job['scope_of_work'])) ?></div>
                </div>
                <div class="col-md-6">
                    <h2 class="h5 mb-2">Access Instructions</h2>
                    <div class="p-3 bg-light rounded"><?= nl2br(h((string) $job['access_instructions'])) ?></div>
                </div>
                <div class="col-md-6">
                    <h2 class="h5 mb-2">Materials Required</h2>
                    <div class="p-3 bg-light rounded"><?= nl2br(h((string) $job['materials_required'])) ?></div>
                </div>
                <div class="col-12">
                    <h2 class="h5 mb-2">Internal Booking Notes</h2>
                    <div class="p-3 bg-light rounded"><?= nl2br(h((string) $job['internal_notes'])) ?></div>
                </div>
            </div>
        </div></div>

        <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><strong>Technician Notes</strong></div><div class="card-body">
            <?php foreach ($notes as $note): ?>
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex justify-content-between gap-3 flex-wrap">
                        <strong><?= h($note['full_name']) ?></strong>
                        <small class="text-body-secondary"><?= h(format_datetime_display($note['created_at'])) ?></small>
                    </div>
                    <div class="mt-2"><?= nl2br(h($note['note'])) ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$notes): ?><p class="text-body-secondary mb-0">No technician notes have been added yet.</p><?php endif; ?>

            <?php if (has_permission('jobs.notes') && !is_client_role()): ?>
                <hr>
                <form method="post" class="vstack gap-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_note">
                    <div>
                        <label class="form-label">Add technician note</label>
                        <textarea class="form-control" name="note" rows="4" placeholder="Record site findings, completed work, test results, and anything the office team needs next."></textarea>
                    </div>
                    <div><button class="btn btn-primary">Save Note</button></div>
                </form>
            <?php endif; ?>
        </div></div>
    </div>

    <div class="col-xl-4">
        <div class="card border-0 shadow-sm mb-4"><div class="card-body">
            <h2 class="h5 mb-3">Site Contact</h2>
            <p class="mb-2"><strong>Name:</strong> <?= h((string) $job['site_contact_name']) ?></p>
            <p class="mb-0"><strong>Phone:</strong> <?= h((string) $job['site_contact_phone']) ?></p>
        </div></div>

        <div class="card border-0 shadow-sm"><div class="card-body">
            <h2 class="h5 mb-3">Client Sign-off</h2>
            <?php if ($job['client_signed_at']): ?>
                <p class="mb-2"><strong>Signed By:</strong> <?= h((string) $job['client_signature_name']) ?></p>
                <p class="mb-2"><strong>Signed At:</strong> <?= h(format_datetime_display($job['client_signed_at'])) ?></p>
                <div class="p-3 bg-light rounded"><?= nl2br(h((string) $job['client_signature_notes'])) ?></div>
            <?php elseif (is_client_role() && has_permission('jobs.sign')): ?>
                <p class="text-body-secondary">Review the completed jobcard and sign to confirm the visit details.</p>
                <form method="post" class="vstack gap-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="client_sign">
                    <div>
                        <label class="form-label">Your Name</label>
                        <input class="form-control" name="client_signature_name" value="<?= h((string) (current_user()['full_name'] ?? '')) ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Client Comment</label>
                        <textarea class="form-control" name="client_signature_notes" rows="4" placeholder="Optional feedback, acceptance note, or issue to follow up."></textarea>
                    </div>
                    <div><button class="btn btn-primary">Sign Jobcard</button></div>
                </form>
            <?php else: ?>
                <p class="text-body-secondary mb-0">Pending client sign-off.</p>
            <?php endif; ?>
        </div></div>
    </div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
