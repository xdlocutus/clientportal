<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('products.edit');
products_storage_available();
$id = request_int('id');
$stmt = db()->prepare('SELECT * FROM products WHERE id = :id AND ' . (is_super_admin() ? '1=1' : 'company_id = :company_id'));
$stmt->execute(['id' => $id] + company_scope_params());
$product = $stmt->fetch();
if (!$product) {
    redirect('/modules/products/index.php');
}
if (is_post()) {
    verify_csrf();
    $companyId = is_super_admin() ? request_int('company_id', (int) $product['company_id']) : (int) current_company_id();
    require_company_access($companyId);
    $update = db()->prepare('UPDATE products SET company_id = :company_id, product_name = :product_name, sku = :sku, description = :description, price = :price, status = :status, updated_at = NOW() WHERE id = :id');
    $update->execute([
        'id' => $id,
        'company_id' => $companyId,
        'product_name' => request_string('product_name'),
        'sku' => request_string('sku') ?: null,
        'description' => request_string('description'),
        'price' => (float) request_string('price', '0'),
        'status' => request_string('status', 'active'),
    ]);
    set_flash('success', 'Product updated successfully.');
    redirect('/modules/products/index.php');
}
$pageTitle = 'Edit Product';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body"><form method="post" class="row g-3"><?= csrf_field() ?>
<?php if (is_super_admin()): ?><div class="col-md-6"><label class="form-label">Tenant</label><select class="form-select" name="company_id" required><?= company_select_options((int) $product['company_id']) ?></select></div><?php endif; ?>
<div class="col-md-6"><label class="form-label">Product Name</label><input class="form-control" name="product_name" value="<?= h($product['product_name']) ?>" required></div>
<div class="col-md-6"><label class="form-label">SKU</label><input class="form-control" name="sku" value="<?= h((string) $product['sku']) ?>"></div>
<div class="col-md-6"><label class="form-label">Price</label><input class="form-control" name="price" type="number" step="0.01" value="<?= h((string) $product['price']) ?>" required></div>
<div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['active','inactive'] as $status): ?><option value="<?= h($status) ?>" <?= $product['status'] === $status ? 'selected' : '' ?>><?= h(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="4"><?= h($product['description']) ?></textarea></div>
<div class="col-12"><button class="btn btn-primary">Update Product</button> <a class="btn btn-link" href="/modules/products/index.php">Cancel</a></div>
</form></div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
