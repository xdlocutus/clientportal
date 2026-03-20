<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('products.view');
products_storage_available();
$id = request_int('id');
$stmt = db()->prepare('SELECT products.*, companies.name AS company_name
                       FROM products
                       INNER JOIN companies ON companies.id = products.company_id
                       WHERE products.id = :id AND ' . (is_super_admin() ? '1=1' : 'products.company_id = :company_id'));
$stmt->execute(['id' => $id] + company_scope_params());
$product = $stmt->fetch();
if (!$product) {
    redirect('/modules/products/index.php');
}
$pageTitle = 'Product Details';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body"><h1 class="h3"><?= h($product['product_name']) ?></h1><p><strong>Tenant:</strong> <?= h($product['company_name']) ?></p><p><strong>SKU:</strong> <?= h((string) $product['sku']) ?></p><p><strong>Status:</strong> <?= invoice_status_badge($product['status']) ?></p><p><strong>Price:</strong> <?= h(money_format_portal((float) $product['price'])) ?></p><div><strong>Description:</strong><div class="mt-2"><?= nl2br(h($product['description'])) ?></div></div></div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
