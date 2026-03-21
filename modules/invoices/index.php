<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('invoices.view');
billing_system_ready();
if (has_permission('invoices.create')) {
    generate_due_recurring_invoices(is_super_admin() ? null : (int) current_company_id());
}

$sql = 'SELECT invoices.*, clients.company_name,
               COALESCE((SELECT SUM(amount) FROM invoice_payments WHERE invoice_id = invoices.id), 0) AS paid_amount
        FROM invoices
        INNER JOIN clients ON clients.id = invoices.client_id
        WHERE ' . (is_super_admin() ? '1=1' : 'invoices.company_id = :company_id') . " AND invoices.status IN ('draft', 'sent')";
$params = company_scope_params();
if (is_client_role()) {
    $sql .= ' AND invoices.client_id = :client_id';
    $params['client_id'] = current_client_id();
}
$sql .= ' ORDER BY invoices.invoice_date DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();
$pageTitle = 'Quotes';
require BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-0">Quotes</h1><p class="text-muted mb-0">Draft and sent quotes stay here until you convert them into invoices.</p></div><?php if (has_permission('invoices.create')): ?><a class="btn btn-primary" href="/modules/invoices/add.php">Create Quote</a><?php endif; ?></div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Quote #</th><th>Client</th><th>Type</th><th>Quote Date</th><th>Valid Until</th><th class="text-end">Total</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($invoices as $invoice): ?><tr><td><?= h($invoice['invoice_number']) ?></td><td><?= h($invoice['company_name']) ?></td><td><span class="badge text-bg-light border"><?= h(format_billing_type((string) ($invoice['billing_type'] ?? 'once_off'))) ?></span></td><td><?= h($invoice['invoice_date']) ?></td><td><?= h($invoice['due_date']) ?></td><td class="text-end fw-semibold"><?= h(money_format_portal((float) $invoice['total_amount'])) ?></td><td><?= invoice_status_badge($invoice['status']) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="/modules/invoices/view.php?id=<?= (int) $invoice['id'] ?>">View</a><?php if (has_permission('invoices.edit')): ?> <a class="btn btn-sm btn-outline-primary" href="/modules/invoices/edit.php?id=<?= (int) $invoice['id'] ?>">Edit</a><?php endif; ?><?php if (has_permission('invoices.delete')): ?> <a class="btn btn-sm btn-outline-danger" href="/modules/invoices/delete.php?id=<?= (int) $invoice['id'] ?>" data-confirm="Delete this quote?">Delete</a><?php endif; ?></td></tr><?php endforeach; ?><?php if (!$invoices): ?><tr><td colspan="8" class="text-center text-muted py-4">No quotes found.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
