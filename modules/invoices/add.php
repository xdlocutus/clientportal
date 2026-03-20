<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('invoices.create');
ensure_invoice_item_source_columns();
$catalogItems = invoice_catalog_items();
if (is_post()) {
    verify_csrf();
    $companyId = is_super_admin() ? request_int('company_id') : (int) current_company_id();
    require_company_access($companyId);
    $clientId = request_int('client_id');
    $normalized = normalize_invoice_items(
        $_POST['item_description'] ?? [],
        $_POST['item_quantity'] ?? [],
        $_POST['item_price'] ?? [],
        $_POST['item_source_type'] ?? [],
        $_POST['item_source_id'] ?? []
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
        $stmt = db()->prepare('INSERT INTO invoices (company_id, client_id, invoice_number, invoice_date, due_date, status, notes, subtotal, tax_amount, discount_amount, total_amount, created_at, updated_at) VALUES (:company_id, :client_id, :invoice_number, :invoice_date, :due_date, :status, :notes, :subtotal, :tax_amount, :discount_amount, :total_amount, NOW(), NOW())');
        $stmt->execute([
            'company_id' => $companyId,
            'client_id' => $clientId,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => request_string('invoice_date') ?: date('Y-m-d'),
            'due_date' => request_string('due_date') ?: date('Y-m-d'),
            'status' => request_string('status', 'draft'),
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
        set_flash('success', 'Invoice created successfully.');
        redirect('/modules/invoices/view.php?id=' . $invoiceId);
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
}
$pageTitle = 'Create Quote';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body"><form method="post" data-invoice-form data-catalog='<?= h(json_encode($catalogItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]') ?>'><?= csrf_field() ?>
<div class="row g-3 mb-3"><?php if (is_super_admin()): ?><div class="col-md-4"><label class="form-label">Tenant</label><select class="form-select" name="company_id" data-company-select required><option value="">Select company</option><?= company_select_options() ?></select></div><?php endif; ?><div class="col-md-4"><label class="form-label">Client</label><select class="form-select" name="client_id" data-client-select required><option value="">Select client</option><?= client_select_options() ?></select></div><div class="col-md-2"><label class="form-label">Invoice Date</label><input class="form-control" type="date" name="invoice_date" value="<?= date('Y-m-d') ?>"></div><div class="col-md-2"><label class="form-label">Due Date</label><input class="form-control" type="date" name="due_date" value="<?= date('Y-m-d') ?>"></div><div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['draft','sent','unpaid','paid','overdue','cancelled'] as $status): ?><option value="<?= h($status) ?>"><?= h(ucfirst($status)) ?></option><?php endforeach; ?></select></div></div>
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
<div class="row g-3 mt-3"><div class="col-md-3"><label class="form-label">Tax</label><input class="form-control" type="number" step="0.01" name="tax_amount" value="0"></div><div class="col-md-3"><label class="form-label">Discount</label><input class="form-control" type="number" step="0.01" name="discount_amount" value="0"></div><div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="4"></textarea></div><div class="col-12"><button class="btn btn-primary"><?php echo 'Save Quote'; ?></button> <a class="btn btn-link" href="/modules/invoices/index.php">Cancel</a></div></div>
</form></div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
