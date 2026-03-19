<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_staff();
if (is_post()) {
    verify_csrf();
    $companyId = is_super_admin() ? request_int('company_id') : (int) current_company_id();
    require_company_access($companyId);
    $clientId = request_int('client_id');
    $descriptions = $_POST['item_description'] ?? [];
    $quantities = $_POST['item_quantity'] ?? [];
    $prices = $_POST['item_price'] ?? [];
    $subtotal = 0.0;
    $items = [];
    foreach ($descriptions as $index => $description) {
        $description = trim((string) $description);
        $quantity = (float) ($quantities[$index] ?? 0);
        $price = (float) ($prices[$index] ?? 0);
        if ($description === '') {
            continue;
        }
        $lineTotal = $quantity * $price;
        $subtotal += $lineTotal;
        $items[] = ['description' => $description, 'quantity' => $quantity, 'unit_price' => $price, 'line_total' => $lineTotal];
    }
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
        $itemStmt = db()->prepare('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total, created_at, updated_at) VALUES (:invoice_id, :description, :quantity, :unit_price, :line_total, NOW(), NOW())');
        foreach ($items as $item) {
            $itemStmt->execute(['invoice_id' => $invoiceId] + $item);
        }
        db()->commit();
        set_flash('success', 'Invoice created successfully.');
        redirect('/modules/invoices/view.php?id=' . $invoiceId);
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
}
$pageTitle = 'Create Invoice';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body"><form method="post"><?= csrf_field() ?>
<div class="row g-3 mb-3"><?php if (is_super_admin()): ?><div class="col-md-4"><label class="form-label">Tenant</label><select class="form-select" name="company_id" required><option value="">Select company</option><?= company_select_options() ?></select></div><?php endif; ?><div class="col-md-4"><label class="form-label">Client</label><select class="form-select" name="client_id" required><option value="">Select client</option><?= client_select_options() ?></select></div><div class="col-md-2"><label class="form-label">Invoice Date</label><input class="form-control" type="date" name="invoice_date" value="<?= date('Y-m-d') ?>"></div><div class="col-md-2"><label class="form-label">Due Date</label><input class="form-control" type="date" name="due_date" value="<?= date('Y-m-d') ?>"></div><div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['draft','sent','unpaid','paid','overdue','cancelled'] as $status): ?><option value="<?= h($status) ?>"><?= h(ucfirst($status)) ?></option><?php endforeach; ?></select></div></div>
<h2 class="h5">Line Items</h2>
<?php for ($i = 0; $i < 3; $i++): ?><div class="row g-3 mb-2"><div class="col-md-6"><input class="form-control" name="item_description[]" placeholder="Description"></div><div class="col-md-2"><input class="form-control" type="number" step="0.01" name="item_quantity[]" placeholder="Qty"></div><div class="col-md-2"><input class="form-control" type="number" step="0.01" name="item_price[]" placeholder="Unit Price"></div></div><?php endfor; ?>
<div class="row g-3 mt-3"><div class="col-md-3"><label class="form-label">Tax</label><input class="form-control" type="number" step="0.01" name="tax_amount" value="0"></div><div class="col-md-3"><label class="form-label">Discount</label><input class="form-control" type="number" step="0.01" name="discount_amount" value="0"></div><div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="4"></textarea></div><div class="col-12"><button class="btn btn-primary">Save Invoice</button> <a class="btn btn-link" href="/modules/invoices/index.php">Cancel</a></div></div>
</form></div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
