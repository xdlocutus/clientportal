<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_login();

$requestedModules = [];
$enabledWidgets = enabled_dashboard_widgets();
$metricQueries = [];
if (dashboard_widget_enabled('stats.clients')) {
    $metricQueries['clients'] = 'SELECT COUNT(*) FROM clients WHERE ' . company_scope_sql();
}
if (dashboard_widget_enabled('stats.services')) {
    $metricQueries['services'] = 'SELECT COUNT(*) FROM services WHERE ' . company_scope_sql();
}
if (dashboard_widget_enabled('stats.products') && products_storage_available()) {
    $metricQueries['products'] = 'SELECT COUNT(*) FROM products WHERE ' . company_scope_sql();
}
if (dashboard_widget_enabled('stats.unpaid_invoices')) {
    $metricQueries['unpaid_invoices'] = "SELECT COUNT(*) FROM invoices WHERE " . company_scope_sql() . " AND status IN ('draft','sent','unpaid','overdue')";
}
if (dashboard_widget_enabled('stats.open_tickets')) {
    $metricQueries['open_tickets'] = "SELECT COUNT(*) FROM tickets WHERE " . company_scope_sql() . " AND status = 'open'";
}

$counts = [];
foreach ($metricQueries as $key => $sql) {
    $stmt = db()->prepare($sql);
    $stmt->execute(company_scope_params());
    $counts[$key] = (int) $stmt->fetchColumn();
}

$recentInvoices = [];
if (dashboard_widget_enabled('panel.recent_invoices')) {
    $invoiceSql = 'SELECT invoices.id, invoices.invoice_number, invoices.total_amount, invoices.status, clients.company_name
                   FROM invoices
                   INNER JOIN clients ON clients.id = invoices.client_id
                   WHERE ' . company_scope_sql('company_id', 'invoices');
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
if (dashboard_widget_enabled('panel.recent_tickets')) {
    $ticketSql = 'SELECT tickets.id, tickets.subject, tickets.priority, tickets.status, clients.company_name
                  FROM tickets
                  INNER JOIN clients ON clients.id = tickets.client_id
                  WHERE ' . company_scope_sql('company_id', 'tickets');
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

$topClients = [];
if (dashboard_widget_enabled('panel.top_clients')) {
    $topClientsSql = "SELECT clients.company_name, COALESCE(SUM(invoices.total_amount), 0) AS billed_total, COUNT(invoices.id) AS invoice_count
        FROM clients
        LEFT JOIN invoices ON invoices.client_id = clients.id
            AND invoices.company_id = clients.company_id
            AND invoices.status NOT IN ('draft', 'cancelled')
        WHERE " . company_scope_sql('company_id', 'clients') . '
        GROUP BY clients.id, clients.company_name
        ORDER BY billed_total DESC, clients.company_name ASC
        LIMIT 5';
    $topClientsStmt = db()->prepare($topClientsSql);
    $topClientsStmt->execute(company_scope_params());
    $topClients = $topClientsStmt->fetchAll();
}

$overdueInvoices = [];
if (dashboard_widget_enabled('panel.overdue_invoices')) {
    $overdueSql = 'SELECT invoices.id, invoices.invoice_number, invoices.due_date, invoices.total_amount, clients.company_name
        FROM invoices
        INNER JOIN clients ON clients.id = invoices.client_id
        WHERE ' . company_scope_sql('company_id', 'invoices') . " AND invoices.status = 'overdue'";
    $overdueParams = company_scope_params();
    if (is_client_role()) {
        $overdueSql .= ' AND invoices.client_id = :client_id';
        $overdueParams['client_id'] = current_client_id();
    }
    $overdueSql .= ' ORDER BY invoices.due_date ASC LIMIT 5';
    $overdueStmt = db()->prepare($overdueSql);
    $overdueStmt->execute($overdueParams);
    $overdueInvoices = $overdueStmt->fetchAll();
}

$activeServices = [];
if (dashboard_widget_enabled('panel.active_services')) {
    $activeServicesSql = 'SELECT services.id, services.service_name, services.price, services.billing_cycle, clients.company_name
        FROM services
        INNER JOIN clients ON clients.id = services.client_id
        WHERE ' . company_scope_sql('company_id', 'services') . " AND services.status = 'active'";
    $activeServicesParams = company_scope_params();
    if (is_client_role()) {
        $activeServicesSql .= ' AND services.client_id = :client_id';
        $activeServicesParams['client_id'] = current_client_id();
    }
    $activeServicesSql .= ' ORDER BY services.updated_at DESC LIMIT 5';
    $activeServicesStmt = db()->prepare($activeServicesSql);
    $activeServicesStmt->execute($activeServicesParams);
    $activeServices = $activeServicesStmt->fetchAll();
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
            <?php if (has_permission('dashboard_widgets.manage')): ?>
                <a class="btn btn-outline-secondary" href="/modules/dashboard_widgets/index.php">Customize widgets</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($counts !== []): ?>
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
<?php endif; ?>

<?php if ($enabledWidgets === []): ?>
    <div class="alert alert-warning">No dashboard widgets are enabled for your company yet.</div>
<?php endif; ?>

<div class="row g-4">
    <?php if (dashboard_widget_enabled('panel.recent_invoices')): ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm surface-card h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4"><strong>Recent Quotes & Invoices</strong></div>
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead><tr><th>#</th><th>Client</th><th>Total</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentInvoices as $invoice): ?>
                            <tr>
                                <td><a href="/modules/invoices/view.php?id=<?= (int) $invoice['id'] ?>"><?= h($invoice['invoice_number']) ?></a></td>
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

    <?php if (dashboard_widget_enabled('panel.recent_tickets')): ?>
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

    <?php if (dashboard_widget_enabled('panel.top_clients')): ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm surface-card h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4"><strong>Top Clients</strong></div>
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead><tr><th>Client</th><th>Invoices</th><th>Revenue</th></tr></thead>
                        <tbody>
                        <?php foreach ($topClients as $client): ?>
                            <tr>
                                <td><?= h($client['company_name']) ?></td>
                                <td><?= (int) $client['invoice_count'] ?></td>
                                <td><?= h(money_format_portal((float) $client['billed_total'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$topClients): ?><tr><td colspan="3" class="text-center text-muted">No client revenue data found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (dashboard_widget_enabled('panel.overdue_invoices')): ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm surface-card h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4"><strong>Overdue Invoices</strong></div>
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead><tr><th>#</th><th>Client</th><th>Due date</th><th>Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($overdueInvoices as $invoice): ?>
                            <tr>
                                <td><a href="/modules/invoices/view.php?id=<?= (int) $invoice['id'] ?>"><?= h($invoice['invoice_number']) ?></a></td>
                                <td><?= h($invoice['company_name']) ?></td>
                                <td><?= h($invoice['due_date']) ?></td>
                                <td><?= h(money_format_portal((float) $invoice['total_amount'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$overdueInvoices): ?><tr><td colspan="4" class="text-center text-muted">No overdue invoices found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (dashboard_widget_enabled('panel.active_services')): ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm surface-card h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4"><strong>Active Services</strong></div>
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead><tr><th>Service</th><th>Client</th><th>Billing cycle</th><th>Price</th></tr></thead>
                        <tbody>
                        <?php foreach ($activeServices as $service): ?>
                            <tr>
                                <td><a href="/modules/services/view.php?id=<?= (int) $service['id'] ?>"><?= h($service['service_name']) ?></a></td>
                                <td><?= h($service['company_name']) ?></td>
                                <td><?= h(ucfirst(str_replace('_', ' ', $service['billing_cycle']))) ?></td>
                                <td><?= h(money_format_portal((float) $service['price'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$activeServices): ?><tr><td colspan="4" class="text-center text-muted">No active services found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="mt-4">
    <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3 mb-3">
        <div>
            <span class="eyebrow-label">Next up</span>
            <h2 class="h4 mb-1 mt-2">Requested modules</h2>
            <p class="text-body-secondary mb-0">Additional modules requested for reporting, personalization, and data exports.</p>
        </div>
    </div>
    <div class="row g-4">
        <?php foreach ($requestedModules as $module): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm surface-card h-100 requested-module-card">
                    <div class="card-body p-4">
                        <div class="requested-module-icon mb-3"><?= h($module['icon']) ?></div>
                        <h3 class="h5 mb-2"><?= h($module['title']) ?></h3>
                        <p class="text-body-secondary mb-3"><?= h($module['summary']) ?></p>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($module['examples'] as $example): ?>
                                <span class="requested-module-pill"><?= h($example) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require BASE_PATH . '/includes/footer.php'; ?>
