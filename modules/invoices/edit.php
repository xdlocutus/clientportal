<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('invoices.edit');
ensure_invoice_item_source_columns();
ensure_invoice_item_description_capacity();
$id = request_int('id');
$stmt = db()->prepare('SELECT * FROM invoices WHERE id = :id AND ' . (is_super_admin() ? '1=1' : 'company_id = :company_id'));
$stmt->execute(['id' => $id] + company_scope_params());
$invoice = $stmt->fetch();
if (!$invoice) {
    redirect('/modules/invoices/index.php');
}
$withSourceColumns = ensure_invoice_item_source_columns();
$itemSql = $withSourceColumns
    ? 'SELECT * FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY id'
    : "SELECT id, invoice_id, description, quantity, unit_price, line_total, 'manual' AS source_type, NULL AS source_id, created_at, updated_at FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY id";
$itemStmt = db()->prepare($itemSql);
$itemStmt->execute(['invoice_id' => $id]);
$items = $itemStmt->fetchAll();
$catalogItems = invoice_catalog_items();
if (is_post()) {
    verify_csrf();
    $companyId = is_super_admin() ? request_int('company_id', (int) $invoice['company_id']) : (int) current_company_id();
    require_company_access($companyId);
    $descriptionMaxLength = ensure_invoice_item_description_capacity();
    $normalized = normalize_invoice_items(
        $_POST['item_description'] ?? [],
        $_POST['item_quantity'] ?? [],
        $_POST['item_price'] ?? [],
        $_POST['item_source_type'] ?? [],
        $_POST['item_source_id'] ?? [],
        $descriptionMaxLength
    );
    $newItems = $normalized['items'];
    $subtotal = $normalized['subtotal'];
    $taxAmount = (float) request_string('tax_amount', '0');
    $discountAmount = (float) request_string('discount_amount', '0');
    $total = max(0, $subtotal + $taxAmount - $discountAmount);
    db()->beginTransaction();
    try {
        $update = db()->prepare('UPDATE invoices SET company_id = :company_id, client_id = :client_id, invoice_date = :invoice_date, due_date = :due_date, status = :status, notes = :notes, subtotal = :subtotal, tax_amount = :tax_amount, discount_amount = :discount_amount, total_amount = :total_amount, updated_at = NOW() WHERE id = :id');
        $update->execute([
            'id' => $id,
            'company_id' => $companyId,
            'client_id' => request_int('client_id'),
            'invoice_date' => request_string('invoice_date'),
            'due_date' => request_string('due_date'),
            'status' => request_string('status'),
            'notes' => request_string('notes'),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => $total,
        ]);
        db()->prepare('DELETE FROM invoice_items WHERE invoice_id = :invoice_id')->execute(['invoice_id' => $id]);
        $withSourceColumns = ensure_invoice_item_source_columns();
        $insertSql = $withSourceColumns
            ? 'INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total, source_type, source_id, created_at, updated_at) VALUES (:invoice_id, :description, :quantity, :unit_price, :line_total, :source_type, :source_id, NOW(), NOW())'
            : 'INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total, created_at, updated_at) VALUES (:invoice_id, :description, :quantity, :unit_price, :line_total, NOW(), NOW())';
        $insertItem = db()->prepare($insertSql);
        foreach ($newItems as $item) {
            $params = ['invoice_id' => $id] + $item;
            if (!$withSourceColumns) {
                unset($params['source_type'], $params['source_id']);
            }
            $insertItem->execute($params);
        }
        db()->commit();
        set_flash('success', 'Invoice updated successfully.');
        redirect('/modules/invoices/view.php?id=' . $id);
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
}
$pageTitle = 'Edit Quote';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body"><form method="post" data-invoice-form data-catalog='<?= h(json_encode($catalogItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]') ?>'><?= csrf_field() ?>
<div class="row g-3 mb-3"><?php if (is_super_admin()): ?><div class="col-md-4"><label class="form-label">Tenant</label><select class="form-select" name="company_id" data-company-select required><?= company_select_options((int) $invoice['company_id']) ?></select></div><?php endif; ?><div class="col-md-4"><label class="form-label">Client</label><select class="form-select" name="client_id" data-client-select required><?= client_select_options((int) $invoice['client_id'], (int) $invoice['company_id']) ?></select></div><div class="col-md-2"><label class="form-label">Invoice Date</label><input class="form-control" type="date" name="invoice_date" value="<?= h($invoice['invoice_date']) ?>"></div><div class="col-md-2"><label class="form-label">Due Date</label><input class="form-control" type="date" name="due_date" value="<?= h($invoice['due_date']) ?>"></div><div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['draft','sent','unpaid','paid','overdue','cancelled'] as $status): ?><option value="<?= h($status) ?>" <?= $invoice['status'] === $status ? 'selected' : '' ?>><?= h(ucfirst($status)) ?></option><?php endforeach; ?></select></div></div>
<h2 class="h5">Line Items</h2>
<div class="d-flex flex-column gap-3" data-line-items>
<?php $rows = max(3, count($items)); for ($i = 0; $i < $rows; $i++): $item = $items[$i] ?? ['description' => '', 'quantity' => '1', 'unit_price' => '', 'source_type' => 'manual', 'source_id' => null]; ?>
    <div class="border rounded p-3" data-line-item>
        <div class="row g-3 align-items-end">
            <div class="col-lg-4"><label class="form-label">Product or Service</label><select class="form-select" data-catalog-select data-selected-source-type="<?= h((string) $item['source_type']) ?>" data-selected-source-id="<?= h((string) $item['source_id']) ?>"><option value="">Manual entry</option></select><input type="hidden" name="item_source_type[]" value="<?= h((string) $item['source_type']) ?>"><input type="hidden" name="item_source_id[]" value="<?= h((string) $item['source_id']) ?>"></div>
            <div class="col-lg-4"><label class="form-label">Description</label><input class="form-control" name="item_description[]" value="<?= h($item['description']) ?>" placeholder="Description"></div>
            <div class="col-lg-2"><label class="form-label">Qty</label><input class="form-control" type="number" step="0.01" name="item_quantity[]" value="<?= h((string) $item['quantity']) ?>" placeholder="Qty"></div>
            <div class="col-lg-2"><label class="form-label">Unit Price</label><input class="form-control" type="number" step="0.01" name="item_price[]" value="<?= h((string) $item['unit_price']) ?>" placeholder="Unit Price"></div>
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
<div class="row g-3 mt-3"><div class="col-md-3"><label class="form-label">Tax</label><input class="form-control" type="number" step="0.01" name="tax_amount" value="<?= h((string) $invoice['tax_amount']) ?>"></div><div class="col-md-3"><label class="form-label">Discount</label><input class="form-control" type="number" step="0.01" name="discount_amount" value="<?= h((string) $invoice['discount_amount']) ?>"></div><div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="4"><?= h($invoice['notes']) ?></textarea></div><div class="col-12"><button class="btn btn-primary">Update Quote</button> <a class="btn btn-link" href="/modules/invoices/index.php">Cancel</a></div></div>
</form></div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
