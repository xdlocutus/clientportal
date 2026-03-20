<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_login();

$where = is_super_admin() ? '1=1' : 'company_id = :company_id';
$params = company_scope_params();

$counts = [];
foreach ([
    'clients' => 'SELECT COUNT(*) FROM clients WHERE ' . $where,
    'services' => 'SELECT COUNT(*) FROM services WHERE ' . $where,
    'unpaid_invoices' => "SELECT COUNT(*) FROM invoices WHERE {$where} AND status IN ('draft','sent','unpaid','overdue')",
    'open_tickets' => "SELECT COUNT(*) FROM tickets WHERE {$where} AND status = 'open'",
] as $key => $sql) {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $counts[$key] = (int) $stmt->fetchColumn();
}

$invoiceSql = 'SELECT invoices.invoice_number, invoices.total_amount, invoices.status, clients.company_name
               FROM invoices
               INNER JOIN clients ON clients.id = invoices.client_id
               WHERE ' . (is_super_admin() ? '1=1' : 'invoices.company_id = :company_id');
$invoiceParams = company_scope_params();
if (is_client_role()) {
    $invoiceSql .= ' AND invoices.client_id = :client_id';
    $invoiceParams['client_id'] = current_client_id();
}
$invoiceSql .= ' ORDER BY invoices.created_at DESC LIMIT 5';
$invoiceStmt = db()->prepare($invoiceSql);
$invoiceStmt->execute($invoiceParams);
$recentInvoices = $invoiceStmt->fetchAll();

$ticketSql = 'SELECT tickets.id, tickets.subject, tickets.priority, tickets.status, clients.company_name
              FROM tickets
              INNER JOIN clients ON clients.id = tickets.client_id
              WHERE ' . (is_super_admin() ? '1=1' : 'tickets.company_id = :company_id');
$ticketParams = company_scope_params();
if (is_client_role()) {
    $ticketSql .= ' AND tickets.client_id = :client_id';
    $ticketParams['client_id'] = current_client_id();
}
$ticketSql .= ' ORDER BY tickets.updated_at DESC LIMIT 5';
$ticketStmt = db()->prepare($ticketSql);
$ticketStmt->execute($ticketParams);
$recentTickets = $ticketStmt->fetchAll();

$pageTitle = 'Dashboard';
require BASE_PATH . '/includes/header.php';
?>
<section class="hero-panel mb-4">
    <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-4">
        <div>
            <span class="eyebrow-label">Overview</span>
            <h2 class="h3 mb-2 mt-2">Everything important, at a glance.</h2>
            <p class="mb-0 text-body-secondary">Track client growth, unpaid invoices, and active support work from a single modernized dashboard.</p>
        </div>
        <div class="quick-actions">
            <?php if (has_role(['super_admin', 'company_admin', 'company_staff'])): ?>
                <a class="btn btn-primary" href="/modules/clients/add.php">Add client</a>
                <a class="btn btn-outline-secondary" href="/modules/services/add.php">Add service</a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="/modules/tickets/add.php">Open ticket</a>
        </div>
    </div>
</section>
<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card card-stat">
            <div class="card-body">
                <div class="stat-label">Clients</div>
                <div class="d-flex align-items-end justify-content-between gap-3">
                    <div class="display-6 mb-0"><?= $counts['clients'] ?></div>
                    <span class="stat-icon">◎</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card card-stat">
            <div class="card-body">
                <div class="stat-label">Services</div>
                <div class="d-flex align-items-end justify-content-between gap-3">
                    <div class="display-6 mb-0"><?= $counts['services'] ?></div>
                    <span class="stat-icon">✦</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card card-stat">
            <div class="card-body">
                <div class="stat-label">Unpaid Invoices</div>
                <div class="d-flex align-items-end justify-content-between gap-3">
                    <div class="display-6 mb-0"><?= $counts['unpaid_invoices'] ?></div>
                    <span class="stat-icon">◩</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card card-stat">
            <div class="card-body">
                <div class="stat-label">Open Tickets</div>
                <div class="d-flex align-items-end justify-content-between gap-3">
                    <div class="display-6 mb-0"><?= $counts['open_tickets'] ?></div>
                    <span class="stat-icon">✉</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm surface-card h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4"><strong>Recent Invoices</strong></div>
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead><tr><th>#</th><th>Client</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentInvoices as $invoice): ?>
                        <tr>
                            <td><?= h($invoice['invoice_number']) ?></td>
                            <td><?= h($invoice['company_name']) ?></td>
                            <td><?= h(money_format_portal((float) $invoice['total_amount'])) ?></td>
                            <td><?= invoice_status_badge($invoice['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentInvoices): ?><tr><td colspan="4" class="text-center text-muted">No invoices found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm surface-card h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4"><strong>Recent Tickets</strong></div>
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead><tr><th>Subject</th><th>Client</th><th>Priority</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentTickets as $ticket): ?>
                        <tr>
                            <td><a href="/modules/tickets/view.php?id=<?= (int) $ticket['id'] ?>"><?= h($ticket['subject']) ?></a></td>
                            <td><?= h($ticket['company_name']) ?></td>
                            <td><?= h(ucfirst($ticket['priority'])) ?></td>
                            <td><?= invoice_status_badge($ticket['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentTickets): ?><tr><td colspan="4" class="text-center text-muted">No tickets found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
