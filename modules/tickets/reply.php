<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_login();
$id = request_int('id');
$sql = 'SELECT * FROM tickets WHERE id = :id AND ' . (is_super_admin() ? '1=1' : 'company_id = :company_id');
$params = ['id' => $id] + company_scope_params();
if (is_client_role()) {
    $sql .= ' AND client_id = :client_id';
    $params['client_id'] = current_client_id();
}
$stmt = db()->prepare($sql);
$stmt->execute($params);
$ticket = $stmt->fetch();
if (!$ticket) {
    redirect('/modules/tickets/index.php');
}
if (is_post()) {
    verify_csrf();
    $insert = db()->prepare('INSERT INTO ticket_replies (ticket_id, user_id, message, created_at, updated_at) VALUES (:ticket_id, :user_id, :message, NOW(), NOW())');
    $insert->execute(['ticket_id' => $id, 'user_id' => current_user_id(), 'message' => request_string('message')]);
    db()->prepare('UPDATE tickets SET updated_at = NOW(), status = :status WHERE id = :id')->execute(['id' => $id, 'status' => 'open']);
    set_flash('success', 'Reply posted successfully.');
    redirect('/modules/tickets/view.php?id=' . $id);
}
$pageTitle = 'Reply to Ticket';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body"><h1 class="h4 mb-3">Reply to: <?= h($ticket['subject']) ?></h1><form method="post"><?= csrf_field() ?><div class="mb-3"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="6" required></textarea></div><button class="btn btn-primary">Send Reply</button> <a class="btn btn-link" href="/modules/tickets/view.php?id=<?= (int) $id ?>">Cancel</a></form></div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
