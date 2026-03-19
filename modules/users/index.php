<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_role(['super_admin', 'company_admin']);

$sql = 'SELECT users.*, companies.name AS company_name, clients.company_name AS client_name
        FROM users
        LEFT JOIN companies ON companies.id = users.company_id
        LEFT JOIN clients ON clients.id = users.client_id
        WHERE ' . (is_super_admin() ? '1=1' : 'users.company_id = :company_id') . '
        ORDER BY users.full_name';
$stmt = db()->prepare($sql);
$stmt->execute(company_scope_params());
$users = $stmt->fetchAll();

$pageTitle = 'Users';
require BASE_PATH . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Users</h1>
    <a class="btn btn-primary" href="/modules/users/add.php">Add User</a>
</div>
<div class="card border-0 shadow-sm"><div class="table-responsive">
<table class="table table-striped mb-0">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Company</th><th>Client</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $user): ?>
        <tr>
            <td><?= h($user['full_name']) ?></td>
            <td><?= h($user['email']) ?></td>
            <td><?= h($user['role']) ?></td>
            <td><?= h($user['company_name']) ?></td>
            <td><?= h($user['client_name']) ?></td>
            <td><?= $user['is_active'] ? 'Active' : 'Inactive' ?></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/modules/users/edit.php?id=<?= (int) $user['id'] ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="/modules/users/delete.php?id=<?= (int) $user['id'] ?>" data-confirm="Delete this user?">Delete</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
