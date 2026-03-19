<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_role('super_admin');
$id = request_int('id');
if ($id > 0) {
    $stmt = db()->prepare('DELETE FROM companies WHERE id = :id');
    $stmt->execute(['id' => $id]);
    set_flash('success', 'Company deleted successfully.');
}
redirect('/modules/companies/index.php');
