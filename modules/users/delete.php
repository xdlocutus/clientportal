<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_role(['super_admin', 'company_admin']);
$id = request_int('id');
$params = ['id' => $id] + company_scope_params();
$sql = 'DELETE FROM users WHERE id = :id';
if (!is_super_admin()) {
    $sql .= ' AND company_id = :company_id';
}
$stmt = db()->prepare($sql);
$stmt->execute($params);
set_flash('success', 'User deleted successfully.');
redirect('/modules/users/index.php');
