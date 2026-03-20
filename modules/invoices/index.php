<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('invoices.view');

$sql = 'SELECT invoices.*, clients.company_name
        FROM invoices
        INNER JOIN clients ON clients.id = invoices.client_id
        WHERE ' . (is_super_admin() ? '1=1' : 'invoices.company_id = :company_id');
$params = company_scope_params();
if (is_client_role()) {
    $sql .= ' AND invoices.client_id = :client_id';
    $params['client_id'] = current_client_id();
}
$sql .= ' ORDER BY invoices.invoice_date DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();
$pageTitle = 'Quotes & Invoices';
require BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Quotes & Invoices</h1><?php if (has_permission('invoices.manage')): ?><a class="btn btn-primary" href="/modules/invoices/add.php">Create Quote</a><?php endif; ?></div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Invoice #</th><th>Client</th><th>Date</th><th>Due</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($invoices as $invoice): ?><tr><td><?= h($invoice['invoice_number']) ?></td><td><?= h($invoice['company_name']) ?></td><td><?= h($invoice['invoice_date']) ?></td><td><?= h($invoice['due_date']) ?></td><td><?= h(money_format_portal((float) $invoice['total_amount'])) ?></td><td><?= invoice_status_badge($invoice['status']) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="/modules/invoices/view.php?id=<?= (int) $invoice['id'] ?>">View</a><?php if (has_permission('invoices.manage')): ?> <a class="btn btn-sm btn-outline-primary" href="/modules/invoices/edit.php?id=<?= (int) $invoice['id'] ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="/modules/invoices/delete.php?id=<?= (int) $invoice['id'] ?>" data-confirm="Delete this invoice?">Delete</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
