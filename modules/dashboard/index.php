<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_login();

$metricQueries = [];
if (has_permission('clients.view')) {
    $metricQueries['clients'] = 'SELECT COUNT(*) FROM clients WHERE ' . (is_super_admin() ? '1=1' : 'company_id = :company_id');
}
if (has_permission('services.view')) {
    $metricQueries['services'] = 'SELECT COUNT(*) FROM services WHERE ' . (is_super_admin() ? '1=1' : 'company_id = :company_id');
}
if (has_permission('products.view') && products_storage_available()) {
    $metricQueries['products'] = 'SELECT COUNT(*) FROM products WHERE ' . (is_super_admin() ? '1=1' : 'company_id = :company_id');
}
if (has_permission('invoices.view')) {
    $metricQueries['unpaid_invoices'] = "SELECT COUNT(*) FROM invoices WHERE " . (is_super_admin() ? '1=1' : 'company_id = :company_id') . " AND status IN ('draft','sent','unpaid','overdue')";
}
if (has_permission('tickets.view')) {
    $metricQueries['open_tickets'] = "SELECT COUNT(*) FROM tickets WHERE " . (is_super_admin() ? '1=1' : 'company_id = :company_id') . " AND status = 'open'";
}

$counts = [];
foreach ($metricQueries as $key => $sql) {
    $stmt = db()->prepare($sql);
    $stmt->execute(company_scope_params());
    $counts[$key] = (int) $stmt->fetchColumn();
}

$recentInvoices = [];
if (has_permission('invoices.view')) {
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
}

$recentTickets = [];
if (has_permission('tickets.view')) {
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
}

$pageTitle = 'Dashboard';
$branding = portal_branding();
require BASE_PATH . '/includes/header.php';
?>
<section class="hero-panel mb-4">
    <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-4">
        <div>
            <span class="eyebrow-label">Overview</span>
            <h2 class="h3 mb-2 mt-2">Everything important for <?= h($branding['brand_name']) ?>, at a glance.</h2>
            <p class="mb-0 text-body-secondary"><?= h($branding['dashboard_message']) ?></p>
        </div>
        <div class="quick-actions">
            <?php if (has_permission('clients.create')): ?>
                <a class="btn btn-primary" href="/modules/clients/add.php">Add client</a>
            <?php endif; ?>
            <?php if (has_permission('services.create')): ?>
                <a class="btn btn-outline-secondary" href="/modules/services/add.php">Add service</a>
            <?php endif; ?>
            <?php if (has_permission('products.create')): ?>
                <a class="btn btn-outline-secondary" href="/modules/products/add.php">Add product</a>
            <?php endif; ?>
            <?php if (has_permission('invoices.create')): ?>
                <a class="btn btn-outline-secondary" href="/modules/invoices/add.php">Create quote</a>
            <?php endif; ?>
            <?php if (has_permission('tickets.create')): ?>
                <a class="btn btn-outline-secondary" href="/modules/tickets/add.php">Open ticket</a>
            <?php endif; ?>
        </div>
    </div>
</section>
<div class="row g-4 mb-4">
    <?php if (isset($counts['clients'])): ?>
        <div class="col-md-6 col-xl-3"><div class="card card-stat"><div class="card-body"><div class="stat-label">Clients</div><div class="d-flex align-items-end justify-content-between gap-3"><div class="display-6 mb-0"><?= $counts['clients'] ?></div><span class="stat-icon">◎</span></div></div></div></div>
    <?php endif; ?>
    <?php if (isset($counts['services'])): ?>
        <div class="col-md-6 col-xl-3"><div class="card card-stat"><div class="card-body"><div class="stat-label">Services</div><div class="d-flex align-items-end justify-content-between gap-3"><div class="display-6 mb-0"><?= $counts['services'] ?></div><span class="stat-icon">✦</span></div></div></div></div>
    <?php endif; ?>
    <?php if (isset($counts['products'])): ?>
        <div class="col-md-6 col-xl-3"><div class="card card-stat"><div class="card-body"><div class="stat-label">Products</div><div class="d-flex align-items-end justify-content-between gap-3"><div class="display-6 mb-0"><?= $counts['products'] ?></div><span class="stat-icon">⬡</span></div></div></div></div>
    <?php endif; ?>
    <?php if (isset($counts['unpaid_invoices'])): ?>
        <div class="col-md-6 col-xl-3"><div class="card card-stat"><div class="card-body"><div class="stat-label">Open Quotes & Invoices</div><div class="d-flex align-items-end justify-content-between gap-3"><div class="display-6 mb-0"><?= $counts['unpaid_invoices'] ?></div><span class="stat-icon">◩</span></div></div></div></div>
    <?php endif; ?>
    <?php if (isset($counts['open_tickets'])): ?>
        <div class="col-md-6 col-xl-3"><div class="card card-stat"><div class="card-body"><div class="stat-label">Open Tickets</div><div class="d-flex align-items-end justify-content-between gap-3"><div class="display-6 mb-0"><?= $counts['open_tickets'] ?></div><span class="stat-icon">✉</span></div></div></div></div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <?php if (has_permission('invoices.view')): ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm surface-card h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4"><strong>Recent Quotes & Invoices</strong></div>
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
                        <?php if (!$recentInvoices): ?><tr><td colspan="4" class="text-center text-muted">No quotes or invoices found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if (has_permission('tickets.view')): ?>
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
    <?php endif; ?>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
