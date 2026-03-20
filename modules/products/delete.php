<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('products.delete');
products_storage_available();
$id = request_int('id');
$sql = 'DELETE FROM products WHERE id = :id';
$params = ['id' => $id];
if (!is_super_admin()) {
    $sql .= ' AND company_id = :company_id';
    $params['company_id'] = current_company_id();
}
$stmt = db()->prepare($sql);
$stmt->execute($params);
set_flash('success', 'Product deleted successfully.');
redirect('/modules/products/index.php');
