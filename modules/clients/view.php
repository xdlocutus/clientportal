<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('clients.view');
$id = request_int('id');
$sql = 'SELECT * FROM clients WHERE id = :id AND ' . (is_super_admin() ? '1=1' : 'company_id = :company_id');
$stmt = db()->prepare($sql);
$stmt->execute(['id' => $id] + company_scope_params());
$client = $stmt->fetch();
if (!$client) {
    redirect('/modules/clients/index.php');
}
$services = [];
if (has_permission('services.view')) {
    $serviceStmt = db()->prepare('SELECT * FROM services WHERE client_id = :client_id AND company_id = :company_id ORDER BY created_at DESC');
    $serviceStmt->execute(['client_id' => $id, 'company_id' => (int) $client['company_id']]);
    $services = $serviceStmt->fetchAll();
}
$invoices = [];
if (has_permission('invoices.view')) {
    $invoiceStmt = db()->prepare('SELECT * FROM invoices WHERE client_id = :client_id AND company_id = :company_id ORDER BY created_at DESC');
    $invoiceStmt->execute(['client_id' => $id, 'company_id' => (int) $client['company_id']]);
    $invoices = $invoiceStmt->fetchAll();
}
$tickets = [];
if (has_permission('tickets.view')) {
    $ticketStmt = db()->prepare('SELECT * FROM tickets WHERE client_id = :client_id AND company_id = :company_id ORDER BY created_at DESC');
    $ticketStmt->execute(['client_id' => $id, 'company_id' => (int) $client['company_id']]);
    $tickets = $ticketStmt->fetchAll();
}
$pageTitle = 'View Client';
require BASE_PATH . '/includes/header.php';
?>
<div class="row g-4">
<div class="col-lg-4"><div class="card border-0 shadow-sm"><div class="card-body"><h1 class="h4"><?= h($client['company_name']) ?></h1><p class="mb-1"><strong>Contact:</strong> <?= h($client['contact_name']) ?></p><p class="mb-1"><strong>Email:</strong> <?= h($client['email']) ?></p><p class="mb-1"><strong>Billing Email:</strong> <?= h($client['billing_email']) ?></p><p class="mb-1"><strong>Phone:</strong> <?= h($client['phone']) ?></p><p class="mb-0"><strong>Status:</strong> <?= invoice_status_badge($client['status']) ?></p></div></div></div>
<div class="col-lg-8">
<?php if (has_permission('services.view')): ?><div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><strong>Services</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Name</th><th>Status</th><th>Price</th></tr></thead><tbody><?php foreach ($services as $service): ?><tr><td><?= h($service['service_name']) ?></td><td><?= invoice_status_badge($service['status']) ?></td><td><?= h(money_format_portal((float) $service['price'])) ?></td></tr><?php endforeach; ?><?php if (!$services): ?><tr><td colspan="3" class="text-center text-muted">No services found.</td></tr><?php endif; ?></tbody></table></div></div><?php endif; ?>
<?php if (has_permission('invoices.view')): ?><div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><strong>Invoices</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Number</th><th>Status</th><th>Total</th></tr></thead><tbody><?php foreach ($invoices as $invoice): ?><tr><td><a href="/modules/invoices/view.php?id=<?= (int) $invoice['id'] ?>"><?= h($invoice['invoice_number']) ?></a></td><td><?= invoice_status_badge($invoice['status']) ?></td><td><?= h(money_format_portal((float) $invoice['total_amount'])) ?></td></tr><?php endforeach; ?><?php if (!$invoices): ?><tr><td colspan="3" class="text-center text-muted">No invoices found.</td></tr><?php endif; ?></tbody></table></div></div><?php endif; ?>
<?php if (has_permission('tickets.view')): ?><div class="card border-0 shadow-sm"><div class="card-header bg-white"><strong>Tickets</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Subject</th><th>Status</th><th>Priority</th></tr></thead><tbody><?php foreach ($tickets as $ticket): ?><tr><td><a href="/modules/tickets/view.php?id=<?= (int) $ticket['id'] ?>"><?= h($ticket['subject']) ?></a></td><td><?= invoice_status_badge($ticket['status']) ?></td><td><?= h(ucfirst($ticket['priority'])) ?></td></tr><?php endforeach; ?><?php if (!$tickets): ?><tr><td colspan="3" class="text-center text-muted">No tickets found.</td></tr><?php endif; ?></tbody></table></div></div><?php endif; ?>
</div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
