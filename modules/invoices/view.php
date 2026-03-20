<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('invoices.view');
billing_system_ready();
$withSourceColumns = ensure_invoice_item_source_columns();
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

if (is_post() && request_string('action') === 'record_payment') {
    require_permission('invoices.edit');
    verify_csrf();
    $amount = max(0, (float) request_string('amount', '0'));
    $paymentDate = request_string('payment_date', date('Y-m-d'));

    if ($amount <= 0) {
        set_flash('danger', 'Payment amount must be greater than zero.');
    } else {
        $stmt = db()->prepare('INSERT INTO invoice_payments (company_id, client_id, invoice_id, payment_date, amount, reference, notes, created_at, updated_at) VALUES (:company_id, :client_id, :invoice_id, :payment_date, :amount, :reference, :notes, NOW(), NOW())');
        $stmt->execute([
            'company_id' => (int) $invoice['company_id'],
            'client_id' => (int) $invoice['client_id'],
            'invoice_id' => (int) $invoice['id'],
            'payment_date' => $paymentDate,
            'amount' => $amount,
            'reference' => request_string('reference'),
            'notes' => request_string('payment_notes'),
        ]);
        sync_invoice_payment_status((int) $invoice['id']);
        set_flash('success', 'Payment recorded successfully.');
    }

    redirect('/modules/invoices/view.php?id=' . (int) $invoice['id']);
}

$itemSql = $withSourceColumns
    ? 'SELECT * FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY id'
    : "SELECT id, invoice_id, description, quantity, unit_price, line_total, 'manual' AS source_type, NULL AS source_id, created_at, updated_at FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY id";
$itemStmt = db()->prepare($itemSql);
$itemStmt->execute(['invoice_id' => $id]);
$items = $itemStmt->fetchAll();
$payments = [];
if (invoice_payments_storage_available()) {
    $paymentStmt = db()->prepare('SELECT * FROM invoice_payments WHERE invoice_id = :invoice_id ORDER BY payment_date DESC, id DESC');
    $paymentStmt->execute(['invoice_id' => $id]);
    $payments = $paymentStmt->fetchAll();
}
$paidAmount = array_sum(array_map(static fn(array $payment): float => (float) $payment['amount'], $payments));
$balanceAmount = invoice_balance_amount((float) $invoice['total_amount'], $paidAmount);
$pageTitle = 'Quote Details';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body">
<div class="d-flex justify-content-between align-items-start mb-4"><div><h1 class="h3 mb-0"><?= h($invoice['invoice_number']) ?></h1><div class="text-muted"><?= h($invoice['company_name']) ?></div></div><div><?= invoice_status_badge($invoice['status']) ?></div></div>
<div class="row mb-4"><div class="col-md-6"><p class="mb-1"><strong>Client:</strong> <?= h($invoice['contact_name']) ?></p><p class="mb-1"><strong>Email:</strong> <?= h($invoice['email']) ?></p><p class="mb-1"><strong>Billing Type:</strong> <?= h(format_billing_type((string) ($invoice['billing_type'] ?? 'once_off'))) ?></p></div><div class="col-md-6 text-md-end"><p class="mb-1"><strong>Invoice Date:</strong> <?= h($invoice['invoice_date']) ?></p><p class="mb-1"><strong>Due Date:</strong> <?= h($invoice['due_date']) ?></p><p class="mb-1"><strong>Outstanding Balance:</strong> <?= h(money_format_portal($balanceAmount)) ?></p></div></div>
<div class="table-responsive"><table class="table"><thead><tr><th>Description</th><th>Source</th><th>Qty</th><th>Unit Price</th><th>Line Total</th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td><?= h($item['description']) ?></td><td><?= h(ucfirst((string) $item['source_type'])) ?></td><td><?= h((string) $item['quantity']) ?></td><td><?= h(money_format_portal((float) $item['unit_price'])) ?></td><td><?= h(money_format_portal((float) $item['line_total'])) ?></td></tr><?php endforeach; ?></tbody><tfoot><tr><th colspan="4" class="text-end">Subtotal</th><th><?= h(money_format_portal((float) $invoice['subtotal'])) ?></th></tr><tr><th colspan="4" class="text-end">Tax</th><th><?= h(money_format_portal((float) $invoice['tax_amount'])) ?></th></tr><tr><th colspan="4" class="text-end">Discount</th><th><?= h(money_format_portal((float) $invoice['discount_amount'])) ?></th></tr><tr><th colspan="4" class="text-end">Total</th><th><?= h(money_format_portal((float) $invoice['total_amount'])) ?></th></tr></tfoot></table></div>
<?php if ($invoice['notes']): ?><div><strong>Notes:</strong><div class="mt-2"><?= nl2br(h($invoice['notes'])) ?></div></div><?php endif; ?>
<div class="row g-4 mt-2">
    <div class="col-lg-7">
        <div class="border rounded-4 p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3"><strong>Payments</strong><span class="text-muted small">Paid <?= h(money_format_portal($paidAmount)) ?></span></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Date</th><th>Reference</th><th>Notes</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr><td><?= h($payment['payment_date']) ?></td><td><?= h($payment['reference']) ?></td><td><?= h($payment['notes']) ?></td><td class="text-end"><?= h(money_format_portal((float) $payment['amount'])) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (!$payments): ?><tr><td colspan="4" class="text-center text-muted py-3">No payments recorded yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php if (has_permission('invoices.edit')): ?>
        <div class="col-lg-5">
            <div class="border rounded-4 p-3 h-100">
                <strong class="d-block mb-3">Record payment</strong>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="record_payment">
                    <div class="mb-3"><label class="form-label">Payment date</label><input class="form-control" type="date" name="payment_date" value="<?= date('Y-m-d') ?>"></div>
                    <div class="mb-3"><label class="form-label">Amount</label><input class="form-control" type="number" step="0.01" name="amount" max="<?= h((string) $balanceAmount) ?>"></div>
                    <div class="mb-3"><label class="form-label">Reference</label><input class="form-control" name="reference" placeholder="Bank transfer, cash, card, etc."></div>
                    <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="payment_notes" rows="3"></textarea></div>
                    <button class="btn btn-primary">Save payment</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
</div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
