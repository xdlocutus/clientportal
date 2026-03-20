<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('services.view');
$sql = 'SELECT services.*, clients.company_name
        FROM services
        INNER JOIN clients ON clients.id = services.client_id
        WHERE ' . (is_super_admin() ? '1=1' : 'services.company_id = :company_id') . '
        ORDER BY services.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute(company_scope_params());
$services = $stmt->fetchAll();
$pageTitle = 'Services';
require BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Services</h1><?php if (has_permission('services.manage')): ?><a class="btn btn-primary" href="/modules/services/add.php">Add Service</a><?php endif; ?></div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Service</th><th>Client</th><th>Billing Cycle</th><th>Price</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($services as $service): ?><tr><td><?= h($service['service_name']) ?></td><td><?= h($service['company_name']) ?></td><td><?= h(ucfirst($service['billing_cycle'])) ?></td><td><?= h(money_format_portal((float) $service['price'])) ?></td><td><?= invoice_status_badge($service['status']) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="/modules/services/view.php?id=<?= (int) $service['id'] ?>">View</a><?php if (has_permission('services.manage')): ?> <a class="btn btn-sm btn-outline-primary" href="/modules/services/edit.php?id=<?= (int) $service['id'] ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="/modules/services/delete.php?id=<?= (int) $service['id'] ?>" data-confirm="Delete this service?">Delete</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
