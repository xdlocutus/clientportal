<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('reports.view');

$invoiceScopeSql = company_scope_sql('company_id', 'invoices');
$invoiceParams = company_scope_params();
if (is_client_role()) {
    $invoiceScopeSql .= ' AND invoices.client_id = :client_id';
    $invoiceParams['client_id'] = current_client_id();
}

$ticketScopeSql = company_scope_sql('company_id', 'tickets');
$ticketParams = company_scope_params();
if (is_client_role()) {
    $ticketScopeSql .= ' AND tickets.client_id = :client_id';
    $ticketParams['client_id'] = current_client_id();
}

$salesStmt = db()->prepare("SELECT
    COALESCE(SUM(CASE WHEN invoices.status NOT IN ('draft', 'cancelled') THEN invoices.total_amount ELSE 0 END), 0) AS billed_total,
    COALESCE(SUM(CASE WHEN invoices.status = 'paid' THEN invoices.total_amount ELSE 0 END), 0) AS paid_total,
    COALESCE(SUM(CASE WHEN invoices.status IN ('sent', 'unpaid', 'overdue') THEN invoices.total_amount ELSE 0 END), 0) AS unpaid_total,
    COUNT(CASE WHEN invoices.status = 'overdue' THEN 1 END) AS overdue_count
    FROM invoices
    WHERE {$invoiceScopeSql}");
$salesStmt->execute($invoiceParams);
$salesSummary = $salesStmt->fetch() ?: ['billed_total' => 0, 'paid_total' => 0, 'unpaid_total' => 0, 'overdue_count' => 0];

$revenueStmt = db()->prepare("SELECT clients.company_name, COUNT(invoices.id) AS invoice_count,
    COALESCE(SUM(CASE WHEN invoices.status NOT IN ('draft', 'cancelled') THEN invoices.total_amount ELSE 0 END), 0) AS revenue_total
    FROM clients
    LEFT JOIN invoices ON invoices.client_id = clients.id AND invoices.company_id = clients.company_id
    WHERE " . company_scope_sql('company_id', 'clients') . (is_client_role() ? ' AND clients.id = :client_id' : '') . '
    GROUP BY clients.id, clients.company_name
    ORDER BY revenue_total DESC, clients.company_name ASC');
$revenueParams = company_scope_params();
if (is_client_role()) {
    $revenueParams['client_id'] = current_client_id();
}
$revenueStmt->execute($revenueParams);
$revenuePerClient = $revenueStmt->fetchAll();

$unpaidStmt = db()->prepare("SELECT invoices.id, invoices.invoice_number, invoices.due_date, invoices.total_amount, invoices.status, clients.company_name
    FROM invoices
    INNER JOIN clients ON clients.id = invoices.client_id
    WHERE {$invoiceScopeSql} AND invoices.status IN ('sent', 'unpaid', 'overdue')
    ORDER BY invoices.due_date ASC, invoices.invoice_number ASC");
$unpaidStmt->execute($invoiceParams);
$unpaidInvoices = $unpaidStmt->fetchAll();

$ticketStatusStmt = db()->prepare("SELECT tickets.status, COUNT(*) AS total
    FROM tickets
    WHERE {$ticketScopeSql}
    GROUP BY tickets.status
    ORDER BY tickets.status ASC");
$ticketStatusStmt->execute($ticketParams);
$ticketStatusCounts = $ticketStatusStmt->fetchAll();

$ticketPriorityStmt = db()->prepare("SELECT tickets.priority, COUNT(*) AS total
    FROM tickets
    WHERE {$ticketScopeSql}
    GROUP BY tickets.priority
    ORDER BY FIELD(tickets.priority, 'high', 'medium', 'low')");
$ticketPriorityStmt->execute($ticketParams);
$ticketPriorityCounts = $ticketPriorityStmt->fetchAll();

$pageTitle = 'Custom Reports';
require BASE_PATH . '/includes/header.php';
?>
<div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
        <span class="eyebrow-label">Reporting</span>
        <h1 class="h3 mb-1 mt-2">Custom Reports</h1>
        <p class="text-body-secondary mb-0">Track sales performance, revenue per client, unpaid invoices, and ticket statistics.</p>
    </div>
    <?php if (has_permission('exports.view')): ?>
        <a class="btn btn-outline-secondary" href="/modules/exports/index.php">Go to exports</a>
    <?php endif; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3"><div class="card card-stat"><div class="card-body"><div class="stat-label">Billed Revenue</div><div class="display-6 mb-0"><?= h(money_format_portal((float) $salesSummary['billed_total'])) ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card card-stat"><div class="card-body"><div class="stat-label">Paid Revenue</div><div class="display-6 mb-0"><?= h(money_format_portal((float) $salesSummary['paid_total'])) ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card card-stat"><div class="card-body"><div class="stat-label">Unpaid Revenue</div><div class="display-6 mb-0"><?= h(money_format_portal((float) $salesSummary['unpaid_total'])) ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card card-stat"><div class="card-body"><div class="stat-label">Overdue Invoices</div><div class="display-6 mb-0"><?= (int) $salesSummary['overdue_count'] ?></div></div></div></div>
</div>

<div class="row g-4">
    <div class="col-xl-6">
        <div class="card border-0 shadow-sm surface-card h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4"><strong>Revenue per Client</strong></div>
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead><tr><th>Client</th><th>Invoices</th><th>Revenue</th></tr></thead>
                    <tbody>
                    <?php foreach ($revenuePerClient as $client): ?>
                        <tr>
                            <td><?= h($client['company_name']) ?></td>
                            <td><?= (int) $client['invoice_count'] ?></td>
                            <td><?= h(money_format_portal((float) $client['revenue_total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$revenuePerClient): ?><tr><td colspan="3" class="text-center text-muted">No revenue data found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card border-0 shadow-sm surface-card h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4"><strong>Ticket Stats</strong></div>
            <div class="card-body pt-0">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100 bg-body-tertiary">
                            <div class="fw-semibold mb-3">By Status</div>
                            <?php foreach ($ticketStatusCounts as $item): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2"><span><?= h(ucfirst($item['status'])) ?></span><strong><?= (int) $item['total'] ?></strong></div>
                            <?php endforeach; ?>
                            <?php if (!$ticketStatusCounts): ?><div class="text-muted small">No ticket status data found.</div><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100 bg-body-tertiary">
                            <div class="fw-semibold mb-3">By Priority</div>
                            <?php foreach ($ticketPriorityCounts as $item): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2"><span><?= h(ucfirst($item['priority'])) ?></span><strong><?= (int) $item['total'] ?></strong></div>
                            <?php endforeach; ?>
                            <?php if (!$ticketPriorityCounts): ?><div class="text-muted small">No ticket priority data found.</div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm surface-card h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4"><strong>Unpaid Invoices</strong></div>
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead><tr><th>#</th><th>Client</th><th>Due date</th><th>Status</th><th>Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($unpaidInvoices as $invoice): ?>
                        <tr>
                            <td><a href="/modules/invoices/view.php?id=<?= (int) $invoice['id'] ?>"><?= h($invoice['invoice_number']) ?></a></td>
                            <td><?= h($invoice['company_name']) ?></td>
                            <td><?= h($invoice['due_date']) ?></td>
                            <td><?= invoice_status_badge($invoice['status']) ?></td>
                            <td><?= h(money_format_portal((float) $invoice['total_amount'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$unpaidInvoices): ?><tr><td colspan="5" class="text-center text-muted">No unpaid invoices found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
