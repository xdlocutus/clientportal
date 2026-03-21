<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('invoices.create');
billing_system_ready();
ensure_invoice_item_source_columns();
ensure_invoice_item_description_capacity();
$catalogItems = invoice_catalog_items();
$mode = request_string('mode') === 'invoice' ? 'invoice' : 'quote';
$statusOptions = $mode === 'invoice' ? ['unpaid', 'paid', 'overdue', 'cancelled'] : quote_statuses();
$pageTitle = $mode === 'invoice' ? 'Create Invoice' : 'Create Quote';
$successMessage = $mode === 'invoice' ? 'Invoice created successfully.' : 'Quote created successfully.';
$submitLabel = $mode === 'invoice' ? 'Save Invoice' : 'Save Quote';
$cancelUrl = $mode === 'invoice' ? '/modules/billing/index.php' : '/modules/invoices/index.php';
if (is_post()) {
    verify_csrf();
    $companyId = is_super_admin() ? request_int('company_id') : (int) current_company_id();
    require_company_access($companyId);
    $clientId = request_int('client_id');
    $descriptionMaxLength = ensure_invoice_item_description_capacity();
    $normalized = normalize_invoice_items(
        $_POST['item_description'] ?? [],
        $_POST['item_quantity'] ?? [],
        $_POST['item_price'] ?? [],
        $_POST['item_source_type'] ?? [],
        $_POST['item_source_id'] ?? [],
        $descriptionMaxLength
    );
    $items = $normalized['items'];
    $subtotal = $normalized['subtotal'];
    $taxAmount = (float) request_string('tax_amount', '0');
    $discountAmount = (float) request_string('discount_amount', '0');
    $total = max(0, $subtotal + $taxAmount - $discountAmount);
    $prefix = setting('invoice_prefix', 'INV-');
    $invoiceNumber = $prefix . date('YmdHis');

    db()->beginTransaction();
    try {
        $stmt = db()->prepare('INSERT INTO invoices (company_id, client_id, invoice_number, invoice_date, due_date, billing_type, recurring_profile_id, status, notes, subtotal, tax_amount, discount_amount, total_amount, created_at, updated_at) VALUES (:company_id, :client_id, :invoice_number, :invoice_date, :due_date, :billing_type, NULL, :status, :notes, :subtotal, :tax_amount, :discount_amount, :total_amount, NOW(), NOW())');
        $stmt->execute([
            'company_id' => $companyId,
            'client_id' => $clientId,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => request_string('invoice_date') ?: date('Y-m-d'),
            'due_date' => request_string('due_date') ?: date('Y-m-d'),
            'billing_type' => request_string('billing_type', 'once_off') === 'recurring' ? 'recurring' : 'once_off',
            'status' => in_array(request_string('status', $statusOptions[0]), $statusOptions, true) ? request_string('status', $statusOptions[0]) : $statusOptions[0],
            'notes' => request_string('notes'),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => $total,
        ]);
        $invoiceId = (int) db()->lastInsertId();
        $withSourceColumns = ensure_invoice_item_source_columns();
        $itemSql = $withSourceColumns
            ? 'INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total, source_type, source_id, created_at, updated_at) VALUES (:invoice_id, :description, :quantity, :unit_price, :line_total, :source_type, :source_id, NOW(), NOW())'
            : 'INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total, created_at, updated_at) VALUES (:invoice_id, :description, :quantity, :unit_price, :line_total, NOW(), NOW())';
        $itemStmt = db()->prepare($itemSql);
        foreach ($items as $item) {
            $params = ['invoice_id' => $invoiceId] + $item;
            if (!$withSourceColumns) {
                unset($params['source_type'], $params['source_id']);
            }
            $itemStmt->execute($params);
        }
        db()->commit();
        set_flash('success', $successMessage);
        redirect('/modules/invoices/view.php?id=' . $invoiceId);
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
}
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body"><form method="post" data-invoice-form data-catalog='<?= h(json_encode($catalogItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]') ?>'><?= csrf_field() ?>
<input type="hidden" name="mode" value="<?= h($mode) ?>">
<div class="row g-3 mb-3"><?php if (is_super_admin()): ?><div class="col-md-4"><label class="form-label">Tenant</label><select class="form-select" name="company_id" data-company-select required><option value="">Select company</option><?= company_select_options() ?></select></div><?php endif; ?><div class="col-md-4"><label class="form-label">Client</label><select class="form-select" name="client_id" data-client-select required><option value="">Select client</option><?= client_select_options() ?></select></div><div class="col-md-2"><label class="form-label"><?= $mode === 'invoice' ? 'Invoice Date' : 'Quote Date' ?></label><input class="form-control" type="date" name="invoice_date" value="<?= date('Y-m-d') ?>"></div><div class="col-md-2"><label class="form-label"><?= $mode === 'invoice' ? 'Due Date' : 'Valid Until' ?></label><input class="form-control" type="date" name="due_date" value="<?= date('Y-m-d') ?>"></div><div class="col-md-3"><label class="form-label">Billing Type</label><select class="form-select" name="billing_type"><option value="once_off">Once-off</option><option value="recurring">Recurring-generated</option></select></div><div class="col-md-3"><label class="form-label"><?= $mode === 'invoice' ? 'Invoice Status' : 'Quote Status' ?></label><select class="form-select" name="status"><?php foreach ($statusOptions as $status): ?><option value="<?= h($status) ?>"><?= h(ucfirst($status)) ?></option><?php endforeach; ?></select></div></div>
<h2 class="h5">Line Items</h2>
<div class="d-flex flex-column gap-3" data-line-items>
<?php for ($i = 0; $i < 3; $i++): ?>
    <div class="border rounded p-3" data-line-item>
        <div class="row g-3 align-items-end">
            <div class="col-lg-4"><label class="form-label">Product or Service</label><select class="form-select" data-catalog-select><option value="">Manual entry</option></select><input type="hidden" name="item_source_type[]" value="manual"><input type="hidden" name="item_source_id[]" value=""></div>
            <div class="col-lg-4"><label class="form-label">Description</label><input class="form-control" name="item_description[]" placeholder="Description"></div>
            <div class="col-lg-2"><label class="form-label">Qty</label><input class="form-control" type="number" step="0.01" name="item_quantity[]" placeholder="Qty" value="1"></div>
            <div class="col-lg-2"><label class="form-label">Unit Price</label><input class="form-control" type="number" step="0.01" name="item_price[]" placeholder="Unit Price"></div>
        </div>
    </div>
<?php endfor; ?>
</div>
<div class="mt-3"><button class="btn btn-outline-secondary" type="button" data-add-line-item>Add Another Line</button></div>
<template id="invoice-line-item-template">
    <div class="border rounded p-3 mt-3" data-line-item>
        <div class="row g-3 align-items-end">
            <div class="col-lg-4"><label class="form-label">Product or Service</label><select class="form-select" data-catalog-select><option value="">Manual entry</option></select><input type="hidden" name="item_source_type[]" value="manual"><input type="hidden" name="item_source_id[]" value=""></div>
            <div class="col-lg-4"><label class="form-label">Description</label><input class="form-control" name="item_description[]" placeholder="Description"></div>
            <div class="col-lg-2"><label class="form-label">Qty</label><input class="form-control" type="number" step="0.01" name="item_quantity[]" placeholder="Qty" value="1"></div>
            <div class="col-lg-2"><label class="form-label">Unit Price</label><input class="form-control" type="number" step="0.01" name="item_price[]" placeholder="Unit Price"></div>
        </div>
    </div>
</template>
<div class="row g-3 mt-3"><div class="col-md-3"><label class="form-label">Tax</label><input class="form-control" type="number" step="0.01" name="tax_amount" value="0"></div><div class="col-md-3"><label class="form-label">Discount</label><input class="form-control" type="number" step="0.01" name="discount_amount" value="0"></div><div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="4"></textarea></div><div class="col-12"><button class="btn btn-primary"><?= h($submitLabel) ?></button> <a class="btn btn-link" href="<?= h($cancelUrl) ?>">Cancel</a></div></div>
</form></div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
