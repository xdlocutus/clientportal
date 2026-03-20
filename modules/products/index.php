<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('products.view');
products_storage_available();
$sql = 'SELECT products.*
        FROM products
        WHERE ' . (is_super_admin() ? '1=1' : 'products.company_id = :company_id') . '
        ORDER BY products.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute(company_scope_params());
$products = $stmt->fetchAll();
$pageTitle = 'Products';
require BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Products</h1><?php if (has_permission('products.create')): ?><a class="btn btn-primary" href="/modules/products/add.php">Add Product</a><?php endif; ?></div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Product</th><th>SKU</th><th>Price</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($products as $product): ?><tr><td><?= h($product['product_name']) ?></td><td><?= h((string) $product['sku']) ?></td><td><?= h(money_format_portal((float) $product['price'])) ?></td><td><?= invoice_status_badge($product['status']) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="/modules/products/view.php?id=<?= (int) $product['id'] ?>">View</a><?php if (has_permission('products.edit')): ?> <a class="btn btn-sm btn-outline-primary" href="/modules/products/edit.php?id=<?= (int) $product['id'] ?>">Edit</a><?php endif; ?><?php if (has_permission('products.delete')): ?> <a class="btn btn-sm btn-outline-danger" href="/modules/products/delete.php?id=<?= (int) $product['id'] ?>" data-confirm="Delete this product?">Delete</a><?php endif; ?></td></tr><?php endforeach; ?><?php if (!$products): ?><tr><td colspan="5" class="text-center text-muted">No products found.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
