<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_role(['super_admin', 'company_admin']);
$id = request_int('id');
$sql = 'SELECT * FROM users WHERE id = :id AND ' . (is_super_admin() ? '1=1' : 'company_id = :company_id');
$params = ['id' => $id] + company_scope_params();
$stmt = db()->prepare($sql);
$stmt->execute($params);
$user = $stmt->fetch();
if (!$user) {
    redirect('/modules/users/index.php');
}

if (is_post()) {
    verify_csrf();
    $companyId = is_super_admin() ? request_int('company_id', (int) $user['company_id']) : (int) current_company_id();
    require_company_access($companyId);
    $passwordSql = '';
    $updateParams = [
        'id' => $id,
        'company_id' => $companyId,
        'client_id' => request_int('client_id') ?: null,
        'role' => request_string('role'),
        'full_name' => request_string('full_name'),
        'email' => request_string('email'),
        'is_active' => request_int('is_active', 1),
    ];
    if (request_string('password') !== '') {
        $passwordSql = ', password = :password';
        $updateParams['password'] = password_hash(request_string('password'), PASSWORD_DEFAULT);
    }
    $update = db()->prepare('UPDATE users SET company_id = :company_id, client_id = :client_id, role = :role, full_name = :full_name, email = :email, is_active = :is_active' . $passwordSql . ', updated_at = NOW() WHERE id = :id');
    $update->execute($updateParams);
    set_flash('success', 'User updated successfully.');
    redirect('/modules/users/index.php');
}

$pageTitle = 'Edit User';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body">
<form method="post" class="row g-3"><?= csrf_field() ?>
    <?php if (is_super_admin()): ?><div class="col-md-6"><label class="form-label">Company</label><select class="form-select" name="company_id" required><?= company_select_options((int) $user['company_id']) ?></select></div><?php endif; ?>
    <div class="col-md-6"><label class="form-label">Role</label><select class="form-select" name="role" required><?php foreach (['company_admin','company_staff','client','super_admin'] as $role): if ($role === 'super_admin' && !is_super_admin()) { continue; } ?><option value="<?= h($role) ?>" <?= $user['role'] === $role ? 'selected' : '' ?>><?= h($role) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" name="full_name" value="<?= h($user['full_name']) ?>" required></div>
    <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= h($user['email']) ?>" required></div>
    <div class="col-md-6"><label class="form-label">New Password</label><input class="form-control" type="password" name="password"><div class="form-text">Leave blank to keep current password.</div></div>
    <div class="col-md-6"><label class="form-label">Link Client</label><select class="form-select" name="client_id"><option value="">None</option><?= client_select_options($user['client_id'] ? (int) $user['client_id'] : null, (int) $user['company_id']) ?></select></div>
    <div class="col-md-3"><label class="form-label">Active</label><select class="form-select" name="is_active"><option value="1" <?= $user['is_active'] ? 'selected' : '' ?>>Yes</option><option value="0" <?= !$user['is_active'] ? 'selected' : '' ?>>No</option></select></div>
    <div class="col-12"><button class="btn btn-primary">Update User</button> <a class="btn btn-link" href="/modules/users/index.php">Cancel</a></div>
</form>
</div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
