<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('clients.view');
billing_system_ready();
if (has_permission('invoices.create')) {
    generate_due_recurring_invoices(is_super_admin() ? null : (int) current_company_id());
}
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
$payments = [];
$statement = [];
$recurringProfiles = [];
if (has_permission('invoices.view')) {
    $invoiceStmt = db()->prepare('SELECT * FROM invoices WHERE client_id = :client_id AND company_id = :company_id ORDER BY created_at DESC');
    $invoiceStmt->execute(['client_id' => $id, 'company_id' => (int) $client['company_id']]);
    $invoices = $invoiceStmt->fetchAll();
    if (invoice_payments_storage_available()) {
        $paymentStmt = db()->prepare('SELECT * FROM invoice_payments WHERE client_id = :client_id AND company_id = :company_id ORDER BY payment_date DESC, id DESC');
        $paymentStmt->execute(['client_id' => $id, 'company_id' => (int) $client['company_id']]);
        $payments = $paymentStmt->fetchAll();
    }
    if (recurring_billing_profiles_storage_available()) {
        $profileStmt = db()->prepare('SELECT * FROM recurring_billing_profiles WHERE client_id = :client_id AND company_id = :company_id ORDER BY FIELD(status, \"active\", \"paused\", \"completed\"), next_invoice_date ASC');
        $profileStmt->execute(['client_id' => $id, 'company_id' => (int) $client['company_id']]);
        $recurringProfiles = $profileStmt->fetchAll();
    }
    $statement = statement_entries((int) $client['company_id'], $id);
}
$tickets = [];
if (has_permission('tickets.view')) {
    $ticketStmt = db()->prepare('SELECT * FROM tickets WHERE client_id = :client_id AND company_id = :company_id ORDER BY created_at DESC');
    $ticketStmt->execute(['client_id' => $id, 'company_id' => (int) $client['company_id']]);
    $tickets = $ticketStmt->fetchAll();
}
$jobs = [];
if (has_permission('jobs.view') && jobs_storage_available()) {
    $jobStmt = db()->prepare('SELECT id, job_number, title, scheduled_for, status, client_signed_at FROM jobcards WHERE client_id = :client_id AND company_id = :company_id ORDER BY scheduled_for DESC');
    $jobStmt->execute(['client_id' => $id, 'company_id' => (int) $client['company_id']]);
    $jobs = $jobStmt->fetchAll();
}
$pageTitle = 'View Client';
require BASE_PATH . '/includes/header.php';
?>
<div class="row g-4">
<div class="col-lg-4"><div class="card border-0 shadow-sm"><div class="card-body"><h1 class="h4"><?= h($client['company_name']) ?></h1><p class="mb-1"><strong>Contact:</strong> <?= h($client['contact_name']) ?></p><p class="mb-1"><strong>Email:</strong> <?= h($client['email']) ?></p><p class="mb-1"><strong>Billing Email:</strong> <?= h($client['billing_email']) ?></p><p class="mb-1"><strong>Phone:</strong> <?= h($client['phone']) ?></p><p class="mb-0"><strong>Status:</strong> <?= invoice_status_badge($client['status']) ?></p><div class="mt-3"><a class="btn btn-primary btn-sm" href="/modules/billing/index.php?client_id=<?= (int) $client['id'] ?>">Open billing statement</a></div></div></div></div>
<div class="col-lg-8">
<?php if (has_permission('services.view')): ?><div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><strong>Services</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Name</th><th>Status</th><th>Price</th></tr></thead><tbody><?php foreach ($services as $service): ?><tr><td><?= h($service['service_name']) ?></td><td><?= invoice_status_badge($service['status']) ?></td><td><?= h(money_format_portal((float) $service['price'])) ?></td></tr><?php endforeach; ?><?php if (!$services): ?><tr><td colspan="3" class="text-center text-muted">No services found.</td></tr><?php endif; ?></tbody></table></div></div><?php endif; ?>
<?php if (has_permission('invoices.view')): ?><div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white d-flex justify-content-between align-items-center"><strong>Invoices</strong><span class="text-muted small"><?= count($invoices) ?> total</span></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Number</th><th>Type</th><th>Status</th><th>Total</th></tr></thead><tbody><?php foreach ($invoices as $invoice): ?><tr><td><a href="/modules/invoices/view.php?id=<?= (int) $invoice['id'] ?>"><?= h($invoice['invoice_number']) ?></a></td><td><span class="badge text-bg-light border"><?= h(format_billing_type((string) ($invoice['billing_type'] ?? 'once_off'))) ?></span></td><td><?= invoice_status_badge($invoice['status']) ?></td><td><?= h(money_format_portal((float) $invoice['total_amount'])) ?></td></tr><?php endforeach; ?><?php if (!$invoices): ?><tr><td colspan="4" class="text-center text-muted">No invoices found.</td></tr><?php endif; ?></tbody></table></div></div><?php endif; ?>
<?php if (has_permission('invoices.view')): ?><div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><strong>Statement</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Date</th><th>Entry</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead><tbody><?php foreach ($statement as $entry): ?><tr><td><?= h($entry['entry_date']) ?></td><td><?= h($entry['label']) ?></td><td class="text-end"><?= $entry['debit'] > 0 ? h(money_format_portal((float) $entry['debit'])) : '—' ?></td><td class="text-end"><?= $entry['credit'] > 0 ? h(money_format_portal((float) $entry['credit'])) : '—' ?></td><td class="text-end fw-semibold"><?= h(money_format_portal((float) $entry['running_balance'])) ?></td></tr><?php endforeach; ?><?php if (!$statement): ?><tr><td colspan="5" class="text-center text-muted">No statement activity found.</td></tr><?php endif; ?></tbody></table></div></div><?php endif; ?>
<?php if (has_permission('invoices.view')): ?><div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><strong>Recurring Billing</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Title</th><th>Cycle</th><th>Next Invoice</th><th>Status</th></tr></thead><tbody><?php foreach ($recurringProfiles as $profile): ?><tr><td><?= h($profile['title']) ?></td><td><?= h(billing_cycle_label((string) $profile['billing_cycle'])) ?></td><td><?= h($profile['next_invoice_date']) ?></td><td><?= invoice_status_badge($profile['status']) ?></td></tr><?php endforeach; ?><?php if (!$recurringProfiles): ?><tr><td colspan="4" class="text-center text-muted">No recurring billing profiles found.</td></tr><?php endif; ?></tbody></table></div></div><?php endif; ?>
<?php if (has_permission('invoices.view')): ?><div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><strong>Payments</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Date</th><th>Reference</th><th>Notes</th><th class="text-end">Amount</th></tr></thead><tbody><?php foreach ($payments as $payment): ?><tr><td><?= h($payment['payment_date']) ?></td><td><?= h($payment['reference']) ?></td><td><?= h($payment['notes']) ?></td><td class="text-end"><?= h(money_format_portal((float) $payment['amount'])) ?></td></tr><?php endforeach; ?><?php if (!$payments): ?><tr><td colspan="4" class="text-center text-muted">No payments recorded.</td></tr><?php endif; ?></tbody></table></div></div><?php endif; ?>
<?php if (has_permission('jobs.view') && jobs_storage_available()): ?><div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><strong>Jobcards</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Job</th><th>Scheduled</th><th>Status</th><th>Sign-off</th></tr></thead><tbody><?php foreach ($jobs as $job): ?><tr><td><a href="/modules/jobs/view.php?id=<?= (int) $job['id'] ?>"><?= h($job['job_number']) ?></a><div class="small text-muted"><?= h($job['title']) ?></div></td><td><?= h(format_datetime_display($job['scheduled_for'])) ?></td><td><?= invoice_status_badge($job['status']) ?></td><td><?= $job['client_signed_at'] ? '<span class="badge bg-success">Signed</span>' : '<span class="badge bg-secondary">Pending</span>' ?></td></tr><?php endforeach; ?><?php if (!$jobs): ?><tr><td colspan="4" class="text-center text-muted">No jobcards found.</td></tr><?php endif; ?></tbody></table></div></div><?php endif; ?>
<?php if (has_permission('tickets.view')): ?><div class="card border-0 shadow-sm"><div class="card-header bg-white"><strong>Tickets</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Subject</th><th>Status</th><th>Priority</th></tr></thead><tbody><?php foreach ($tickets as $ticket): ?><tr><td><a href="/modules/tickets/view.php?id=<?= (int) $ticket['id'] ?>"><?= h($ticket['subject']) ?></a></td><td><?= invoice_status_badge($ticket['status']) ?></td><td><?= h(ucfirst($ticket['priority'])) ?></td></tr><?php endforeach; ?><?php if (!$tickets): ?><tr><td colspan="3" class="text-center text-muted">No tickets found.</td></tr><?php endif; ?></tbody></table></div></div><?php endif; ?>
</div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
