<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('invoices.view');
$id = request_int('id');
$sql = 'SELECT invoices.*, clients.company_name, clients.contact_name, clients.email
        FROM invoices
        INNER JOIN clients ON clients.id = invoices.client_id
        WHERE invoices.id = :id AND ' . (is_super_admin() ? '1=1' : 'invoices.company_id = :company_id');
$params = ['id' => $id] + company_scope_params();
if (is_client_role()) {
    $sql .= ' AND invoices.client_id = :client_id';
    $params['client_id'] = current_client_id();
}
$stmt = db()->prepare($sql);
$stmt->execute($params);
$invoice = $stmt->fetch();
if (!$invoice) {
    redirect('/modules/invoices/index.php');
}
$itemStmt = db()->prepare('SELECT * FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY id');
$itemStmt->execute(['invoice_id' => $id]);
$items = $itemStmt->fetchAll();
$pageTitle = 'Quote Details';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body">
<div class="d-flex justify-content-between align-items-start mb-4"><div><h1 class="h3 mb-0"><?= h($invoice['invoice_number']) ?></h1><div class="text-muted"><?= h($invoice['company_name']) ?></div></div><div><?= invoice_status_badge($invoice['status']) ?></div></div>
<div class="row mb-4"><div class="col-md-6"><p class="mb-1"><strong>Client:</strong> <?= h($invoice['contact_name']) ?></p><p class="mb-1"><strong>Email:</strong> <?= h($invoice['email']) ?></p></div><div class="col-md-6 text-md-end"><p class="mb-1"><strong>Invoice Date:</strong> <?= h($invoice['invoice_date']) ?></p><p class="mb-1"><strong>Due Date:</strong> <?= h($invoice['due_date']) ?></p></div></div>
<div class="table-responsive"><table class="table"><thead><tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Line Total</th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td><?= h($item['description']) ?></td><td><?= h((string) $item['quantity']) ?></td><td><?= h(money_format_portal((float) $item['unit_price'])) ?></td><td><?= h(money_format_portal((float) $item['line_total'])) ?></td></tr><?php endforeach; ?></tbody><tfoot><tr><th colspan="3" class="text-end">Subtotal</th><th><?= h(money_format_portal((float) $invoice['subtotal'])) ?></th></tr><tr><th colspan="3" class="text-end">Tax</th><th><?= h(money_format_portal((float) $invoice['tax_amount'])) ?></th></tr><tr><th colspan="3" class="text-end">Discount</th><th><?= h(money_format_portal((float) $invoice['discount_amount'])) ?></th></tr><tr><th colspan="3" class="text-end">Total</th><th><?= h(money_format_portal((float) $invoice['total_amount'])) ?></th></tr></tfoot></table></div>
<?php if ($invoice['notes']): ?><div><strong>Notes:</strong><div class="mt-2"><?= nl2br(h($invoice['notes'])) ?></div></div><?php endif; ?>
</div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
