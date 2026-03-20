<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('tickets.manage');
if (is_post()) {
    verify_csrf();
    $companyId = is_client_role() ? (int) current_company_id() : (is_super_admin() ? request_int('company_id') : (int) current_company_id());
    require_company_access($companyId);
    $clientId = is_client_role() ? (int) current_client_id() : request_int('client_id');
    $stmt = db()->prepare('INSERT INTO tickets (company_id, client_id, user_id, subject, message, priority, status, created_at, updated_at) VALUES (:company_id, :client_id, :user_id, :subject, :message, :priority, :status, NOW(), NOW())');
    $stmt->execute([
        'company_id' => $companyId,
        'client_id' => $clientId,
        'user_id' => current_user_id(),
        'subject' => request_string('subject'),
        'message' => request_string('message'),
        'priority' => request_string('priority', 'medium'),
        'status' => 'open',
    ]);
    $ticketId = (int) db()->lastInsertId();
    set_flash('success', 'Ticket created successfully.');
    redirect('/modules/tickets/view.php?id=' . $ticketId);
}
$pageTitle = 'Open Ticket';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body"><form method="post" class="row g-3"><?= csrf_field() ?>
<?php if (!is_client_role() && is_super_admin()): ?><div class="col-md-6"><label class="form-label">Tenant</label><select class="form-select" name="company_id" required><option value="">Select company</option><?= company_select_options() ?></select></div><?php endif; ?>
<?php if (!is_client_role()): ?><div class="col-md-6"><label class="form-label">Client</label><select class="form-select" name="client_id" required><option value="">Select client</option><?= client_select_options() ?></select></div><?php endif; ?>
<div class="col-md-8"><label class="form-label">Subject</label><input class="form-control" name="subject" required></div>
<div class="col-md-4"><label class="form-label">Priority</label><select class="form-select" name="priority"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option></select></div>
<div class="col-12"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="6" required></textarea></div>
<div class="col-12"><button class="btn btn-primary">Submit Ticket</button> <a class="btn btn-link" href="/modules/tickets/index.php">Cancel</a></div>
</form></div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
