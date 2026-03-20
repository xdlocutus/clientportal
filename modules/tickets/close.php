<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('tickets.close');
$id = request_int('id');
$sql = 'UPDATE tickets SET status = :status, updated_at = NOW() WHERE id = :id';
$params = ['id' => $id, 'status' => 'closed'];
if (!is_super_admin()) {
    $sql .= ' AND company_id = :company_id';
    $params['company_id'] = current_company_id();
}
$stmt = db()->prepare($sql);
$stmt->execute($params);
set_flash('success', 'Ticket closed successfully.');
redirect('/modules/tickets/view.php?id=' . $id);
