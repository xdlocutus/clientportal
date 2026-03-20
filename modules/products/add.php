<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('products.create');
products_storage_available();
if (is_post()) {
    verify_csrf();
    $companyId = is_super_admin() ? request_int('company_id') : (int) current_company_id();
    require_company_access($companyId);
    $stmt = db()->prepare('INSERT INTO products (company_id, product_name, sku, description, price, status, created_at, updated_at) VALUES (:company_id, :product_name, :sku, :description, :price, :status, NOW(), NOW())');
    $stmt->execute([
        'company_id' => $companyId,
        'product_name' => request_string('product_name'),
        'sku' => request_string('sku') ?: null,
        'description' => request_string('description'),
        'price' => (float) request_string('price', '0'),
        'status' => request_string('status', 'active'),
    ]);
    set_flash('success', 'Product created successfully.');
    redirect('/modules/products/index.php');
}
$pageTitle = 'Add Product';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body"><form method="post" class="row g-3"><?= csrf_field() ?>
<?php if (is_super_admin()): ?><div class="col-md-6"><label class="form-label">Tenant</label><select class="form-select" name="company_id" required><option value="">Select company</option><?= company_select_options() ?></select></div><?php endif; ?>
<div class="col-md-6"><label class="form-label">Product Name</label><input class="form-control" name="product_name" required></div>
<div class="col-md-6"><label class="form-label">SKU</label><input class="form-control" name="sku"></div>
<div class="col-md-6"><label class="form-label">Price</label><input class="form-control" name="price" type="number" step="0.01" required></div>
<div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
<div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="4"></textarea></div>
<div class="col-12"><button class="btn btn-primary">Save Product</button> <a class="btn btn-link" href="/modules/products/index.php">Cancel</a></div>
</form></div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
