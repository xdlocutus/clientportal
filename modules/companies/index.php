<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_role('super_admin');

$companies = db()->query('SELECT * FROM companies ORDER BY name')->fetchAll();
$pageTitle = 'Companies';
require BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Companies</h1>
    <a class="btn btn-primary" href="/modules/companies/add.php">Add Company</a>
</div>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead><tr><th>Name</th><th>Email</th><th>Contact</th><th>Phone</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($companies as $company): ?>
                <tr>
                    <td><?= h($company['name']) ?></td>
                    <td><?= h($company['email']) ?></td>
                    <td><?= h($company['contact_name']) ?></td>
                    <td><?= h($company['phone']) ?></td>
                    <td><?= invoice_status_badge($company['status']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="/modules/settings/index.php?company_id=<?= (int) $company['id'] ?>">Branding</a>
                        <a class="btn btn-sm btn-outline-primary" href="/modules/companies/edit.php?id=<?= (int) $company['id'] ?>">Edit</a>
                        <a class="btn btn-sm btn-outline-danger" href="/modules/companies/delete.php?id=<?= (int) $company['id'] ?>" data-confirm="Delete this company?">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
