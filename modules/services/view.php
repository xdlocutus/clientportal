<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_staff();
$id = request_int('id');
$sql = 'SELECT services.*, clients.company_name, clients.contact_name
        FROM services
        INNER JOIN clients ON clients.id = services.client_id
        WHERE services.id = :id AND ' . (is_super_admin() ? '1=1' : 'services.company_id = :company_id');
$stmt = db()->prepare($sql);
$stmt->execute(['id' => $id] + company_scope_params());
$service = $stmt->fetch();
if (!$service) {
    redirect('/modules/services/index.php');
}
$pageTitle = 'Service Details';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body"><h1 class="h3"><?= h($service['service_name']) ?></h1><p><strong>Client:</strong> <?= h($service['company_name']) ?> (<?= h($service['contact_name']) ?>)</p><p><strong>Status:</strong> <?= invoice_status_badge($service['status']) ?></p><p><strong>Billing Cycle:</strong> <?= h(ucfirst(str_replace('_', ' ', $service['billing_cycle']))) ?></p><p><strong>Price:</strong> <?= h(money_format_portal((float) $service['price'])) ?></p><p><strong>Start Date:</strong> <?= h((string) $service['start_date']) ?></p><p><strong>End Date:</strong> <?= h((string) $service['end_date']) ?></p><div><strong>Description:</strong><div class="mt-2"><?= nl2br(h($service['description'])) ?></div></div></div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
