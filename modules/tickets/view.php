<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('tickets.view');
$id = request_int('id');
$sql = 'SELECT tickets.*, clients.company_name, clients.contact_name
        FROM tickets
        INNER JOIN clients ON clients.id = tickets.client_id
        WHERE tickets.id = :id AND ' . (is_super_admin() ? '1=1' : 'tickets.company_id = :company_id');
$params = ['id' => $id] + company_scope_params();
if (is_client_role()) {
    $sql .= ' AND tickets.client_id = :client_id';
    $params['client_id'] = current_client_id();
}
$stmt = db()->prepare($sql);
$stmt->execute($params);
$ticket = $stmt->fetch();
if (!$ticket) {
    redirect('/modules/tickets/index.php');
}
$replyStmt = db()->prepare('SELECT ticket_replies.*, users.full_name
                           FROM ticket_replies
                           INNER JOIN users ON users.id = ticket_replies.user_id
                           WHERE ticket_replies.ticket_id = :ticket_id
                           ORDER BY ticket_replies.created_at ASC');
$replyStmt->execute(['ticket_id' => $id]);
$replies = $replyStmt->fetchAll();
$pageTitle = 'Ticket Details';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm mb-4"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><h1 class="h3 mb-1"><?= h($ticket['subject']) ?></h1><div class="text-muted"><?= h($ticket['company_name']) ?> / <?= h($ticket['contact_name']) ?></div></div><div><?= invoice_status_badge($ticket['status']) ?></div></div><hr><p><strong>Priority:</strong> <?= h(ucfirst($ticket['priority'])) ?></p><div class="p-3 bg-light rounded"><?= nl2br(h($ticket['message'])) ?></div></div></div>
<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><strong>Conversation</strong></div><div class="card-body"><?php foreach ($replies as $reply): ?><div class="border rounded p-3 mb-3"><div class="d-flex justify-content-between"><strong><?= h($reply['full_name']) ?></strong><small class="text-muted"><?= h($reply['created_at']) ?></small></div><div class="mt-2"><?= nl2br(h($reply['message'])) ?></div></div><?php endforeach; ?><?php if (!$replies): ?><p class="text-muted mb-0">No replies yet.</p><?php endif; ?></div></div>
<div class="d-flex gap-2"><?php if ($ticket['status'] === 'open'): ?><?php if (has_permission('tickets.reply')): ?><a class="btn btn-primary" href="/modules/tickets/reply.php?id=<?= (int) $ticket['id'] ?>">Reply</a><?php endif; ?><?php if (has_permission('tickets.close')): ?><a class="btn btn-outline-danger" href="/modules/tickets/close.php?id=<?= (int) $ticket['id'] ?>" data-confirm="Close this ticket?">Close Ticket</a><?php endif; ?><?php endif; ?></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
