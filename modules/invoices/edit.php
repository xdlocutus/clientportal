<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_staff();
$id = request_int('id');
$stmt = db()->prepare('SELECT * FROM invoices WHERE id = :id AND ' . (is_super_admin() ? '1=1' : 'company_id = :company_id'));
$stmt->execute(['id' => $id] + company_scope_params());
$invoice = $stmt->fetch();
if (!$invoice) {
    redirect('/modules/invoices/index.php');
}
$itemStmt = db()->prepare('SELECT * FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY id');
$itemStmt->execute(['invoice_id' => $id]);
$items = $itemStmt->fetchAll();
if (is_post()) {
    verify_csrf();
    $companyId = is_super_admin() ? request_int('company_id', (int) $invoice['company_id']) : (int) current_company_id();
    require_company_access($companyId);
    $descriptions = $_POST['item_description'] ?? [];
    $quantities = $_POST['item_quantity'] ?? [];
    $prices = $_POST['item_price'] ?? [];
    $subtotal = 0.0;
    $newItems = [];
    foreach ($descriptions as $index => $description) {
        $description = trim((string) $description);
        $quantity = (float) ($quantities[$index] ?? 0);
        $price = (float) ($prices[$index] ?? 0);
        if ($description === '') {
            continue;
        }
        $lineTotal = $quantity * $price;
        $subtotal += $lineTotal;
        $newItems[] = ['description' => $description, 'quantity' => $quantity, 'unit_price' => $price, 'line_total' => $lineTotal];
    }
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
        $insertItem = db()->prepare('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total, created_at, updated_at) VALUES (:invoice_id, :description, :quantity, :unit_price, :line_total, NOW(), NOW())');
        foreach ($newItems as $item) {
            $insertItem->execute(['invoice_id' => $id] + $item);
        }
        db()->commit();
        set_flash('success', 'Invoice updated successfully.');
        redirect('/modules/invoices/view.php?id=' . $id);
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
}
$pageTitle = 'Edit Invoice';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body"><form method="post"><?= csrf_field() ?>
<div class="row g-3 mb-3"><?php if (is_super_admin()): ?><div class="col-md-4"><label class="form-label">Tenant</label><select class="form-select" name="company_id" required><?= company_select_options((int) $invoice['company_id']) ?></select></div><?php endif; ?><div class="col-md-4"><label class="form-label">Client</label><select class="form-select" name="client_id" required><?= client_select_options((int) $invoice['client_id'], (int) $invoice['company_id']) ?></select></div><div class="col-md-2"><label class="form-label">Invoice Date</label><input class="form-control" type="date" name="invoice_date" value="<?= h($invoice['invoice_date']) ?>"></div><div class="col-md-2"><label class="form-label">Due Date</label><input class="form-control" type="date" name="due_date" value="<?= h($invoice['due_date']) ?>"></div><div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['draft','sent','unpaid','paid','overdue','cancelled'] as $status): ?><option value="<?= h($status) ?>" <?= $invoice['status'] === $status ? 'selected' : '' ?>><?= h(ucfirst($status)) ?></option><?php endforeach; ?></select></div></div>
<h2 class="h5">Line Items</h2>
<?php $rows = max(3, count($items)); for ($i = 0; $i < $rows; $i++): $item = $items[$i] ?? ['description'=>'','quantity'=>'','unit_price'=>'']; ?><div class="row g-3 mb-2"><div class="col-md-6"><input class="form-control" name="item_description[]" value="<?= h($item['description']) ?>" placeholder="Description"></div><div class="col-md-2"><input class="form-control" type="number" step="0.01" name="item_quantity[]" value="<?= h((string) $item['quantity']) ?>" placeholder="Qty"></div><div class="col-md-2"><input class="form-control" type="number" step="0.01" name="item_price[]" value="<?= h((string) $item['unit_price']) ?>" placeholder="Unit Price"></div></div><?php endfor; ?>
<div class="row g-3 mt-3"><div class="col-md-3"><label class="form-label">Tax</label><input class="form-control" type="number" step="0.01" name="tax_amount" value="<?= h((string) $invoice['tax_amount']) ?>"></div><div class="col-md-3"><label class="form-label">Discount</label><input class="form-control" type="number" step="0.01" name="discount_amount" value="<?= h((string) $invoice['discount_amount']) ?>"></div><div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="4"><?= h($invoice['notes']) ?></textarea></div><div class="col-12"><button class="btn btn-primary">Update Invoice</button> <a class="btn btn-link" href="/modules/invoices/index.php">Cancel</a></div></div>
</form></div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
