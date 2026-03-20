<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('users.manage');

$selectedRole = request_string('role', 'company_staff');
$selectedPermissions = normalize_permissions($_POST['permissions'] ?? default_permissions_for_role($selectedRole));

if (is_post()) {
    verify_csrf();
    $companyId = is_super_admin() ? request_int('company_id') : (int) current_company_id();
    require_company_access($companyId);

    db()->beginTransaction();
    try {
        $stmt = db()->prepare('INSERT INTO users (company_id, client_id, role, full_name, email, password, is_active, created_at, updated_at) VALUES (:company_id, :client_id, :role, :full_name, :email, :password, :is_active, NOW(), NOW())');
        $stmt->execute([
            'company_id' => $companyId,
            'client_id' => request_int('client_id') ?: null,
            'role' => $selectedRole,
            'full_name' => request_string('full_name'),
            'email' => request_string('email'),
            'password' => password_hash(request_string('password'), PASSWORD_DEFAULT),
            'is_active' => request_int('is_active', 1),
        ]);

        sync_user_permissions((int) db()->lastInsertId(), $selectedRole, $selectedPermissions);
        db()->commit();
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }

    set_flash('success', 'User created successfully.');
    redirect('/modules/users/index.php');
}

$pageTitle = 'Add User';
$permissionGroups = permission_groups();
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body">
<form method="post" class="row g-3"><?= csrf_field() ?>
    <?php if (is_super_admin()): ?><div class="col-md-6"><label class="form-label">Company</label><select class="form-select" name="company_id" required><option value="">Select company</option><?= company_select_options(request_int('company_id')) ?></select></div><?php endif; ?>
    <div class="col-md-6"><label class="form-label">Role</label><select class="form-select" name="role" required><option value="company_admin" <?= $selectedRole === 'company_admin' ? 'selected' : '' ?>>Company Admin</option><option value="company_staff" <?= $selectedRole === 'company_staff' ? 'selected' : '' ?>>Company Staff</option><option value="client" <?= $selectedRole === 'client' ? 'selected' : '' ?>>Client</option><?php if (is_super_admin()): ?><option value="super_admin" <?= $selectedRole === 'super_admin' ? 'selected' : '' ?>>Super Admin</option><?php endif; ?></select></div>
    <div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" name="full_name" value="<?= h(request_string('full_name')) ?>" required></div>
    <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= h(request_string('email')) ?>" required></div>
    <div class="col-md-6"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required></div>
    <div class="col-md-6"><label class="form-label">Link Client (optional)</label><select class="form-select" name="client_id"><option value="">None</option><?= client_select_options(request_int('client_id') ?: null, is_super_admin() ? request_int('company_id') ?: null : current_company_id()) ?></select></div>
    <div class="col-md-3"><label class="form-label">Active</label><select class="form-select" name="is_active"><option value="1" <?= request_int('is_active', 1) === 1 ? 'selected' : '' ?>>Yes</option><option value="0" <?= request_int('is_active', 1) === 0 ? 'selected' : '' ?>>No</option></select></div>

    <div class="col-12">
        <div class="border rounded-3 p-3 bg-body-tertiary">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1">Permissions</h2>
                    <p class="text-body-secondary mb-0">Choose exactly what this user can access. Company admins and super admins always receive full access. Company staff defaults to quotes/invoices only, and you can separately enable create, edit, delete, reply, or close actions as needed.</p>
                </div>
            </div>
            <div class="row g-3">
                <?php foreach ($permissionGroups as $group => $permissions): ?>
                    <div class="col-lg-6">
                        <div class="border rounded-3 h-100 p-3 bg-body">
                            <div class="fw-semibold mb-2"><?= h($group) ?></div>
                            <?php foreach ($permissions as $key => $permission): ?>
                                <label class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= h($key) ?>" <?= in_array($key, $selectedPermissions, true) ? 'checked' : '' ?>>
                                    <span class="form-check-label">
                                        <span class="d-block fw-medium"><?= h($permission['label']) ?></span>
                                        <span class="small text-body-secondary"><?= h($permission['description']) ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-12"><button class="btn btn-primary">Save User</button> <a class="btn btn-link" href="/modules/users/index.php">Cancel</a></div>
</form>
</div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
