<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('invoices.delete');
$id = request_int('id');
$sql = 'SELECT id, status FROM invoices WHERE id = :id';
$params = ['id' => $id];
if (!is_super_admin()) {
    $sql .= ' AND company_id = :company_id';
    $params['company_id'] = current_company_id();
}
$stmt = db()->prepare($sql . ' LIMIT 1');
$stmt->execute($params);
$invoice = $stmt->fetch();
if (!$invoice) {
    redirect('/modules/invoices/index.php');
}

$delete = db()->prepare('DELETE FROM invoices WHERE id = :id');
$delete->execute(['id' => $id]);
set_flash('success', invoice_is_quote($invoice) ? 'Quote deleted successfully.' : 'Invoice deleted successfully.');
redirect(invoice_is_quote($invoice) ? '/modules/invoices/index.php' : '/modules/billing/index.php');
