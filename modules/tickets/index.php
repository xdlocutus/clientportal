<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_login();
$sql = 'SELECT tickets.*, clients.company_name
        FROM tickets
        INNER JOIN clients ON clients.id = tickets.client_id
        WHERE ' . (is_super_admin() ? '1=1' : 'tickets.company_id = :company_id');
$params = company_scope_params();
if (is_client_role()) {
    $sql .= ' AND tickets.client_id = :client_id';
    $params['client_id'] = current_client_id();
}
$sql .= ' ORDER BY tickets.updated_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();
$pageTitle = 'Tickets';
require BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Tickets</h1><a class="btn btn-primary" href="/modules/tickets/add.php">Open Ticket</a></div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Subject</th><th>Client</th><th>Priority</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody><?php foreach ($tickets as $ticket): ?><tr><td><?= h($ticket['subject']) ?></td><td><?= h($ticket['company_name']) ?></td><td><?= h(ucfirst($ticket['priority'])) ?></td><td><?= invoice_status_badge($ticket['status']) ?></td><td><?= h($ticket['updated_at']) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="/modules/tickets/view.php?id=<?= (int) $ticket['id'] ?>">View</a></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
