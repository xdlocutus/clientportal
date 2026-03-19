<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_staff();

$sql = 'SELECT clients.*, companies.name AS tenant_name
        FROM clients
        INNER JOIN companies ON companies.id = clients.company_id
        WHERE ' . (is_super_admin() ? '1=1' : 'clients.company_id = :company_id') . '
        ORDER BY clients.company_name';
$stmt = db()->prepare($sql);
$stmt->execute(company_scope_params());
$clients = $stmt->fetchAll();

$pageTitle = 'Clients';
require BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Clients</h1><a class="btn btn-primary" href="/modules/clients/add.php">Add Client</a></div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Company</th><th>Contact</th><th>Email</th><th>Phone</th><th>Tenant</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($clients as $client): ?><tr><td><?= h($client['company_name']) ?></td><td><?= h($client['contact_name']) ?></td><td><?= h($client['email']) ?></td><td><?= h($client['phone']) ?></td><td><?= h($client['tenant_name']) ?></td><td><?= invoice_status_badge($client['status']) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="/modules/clients/view.php?id=<?= (int) $client['id'] ?>">View</a> <a class="btn btn-sm btn-outline-primary" href="/modules/clients/edit.php?id=<?= (int) $client['id'] ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="/modules/clients/delete.php?id=<?= (int) $client['id'] ?>" data-confirm="Delete this client?">Delete</a></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
