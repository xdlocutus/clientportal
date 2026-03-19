<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_role(['super_admin', 'company_admin']);

if (is_post()) {
    verify_csrf();
    $companyId = is_super_admin() ? request_int('company_id') : (int) current_company_id();
    require_company_access($companyId);

    $stmt = db()->prepare('INSERT INTO users (company_id, client_id, role, full_name, email, password, is_active, created_at, updated_at) VALUES (:company_id, :client_id, :role, :full_name, :email, :password, :is_active, NOW(), NOW())');
    $stmt->execute([
        'company_id' => $companyId,
        'client_id' => request_int('client_id') ?: null,
        'role' => request_string('role'),
        'full_name' => request_string('full_name'),
        'email' => request_string('email'),
        'password' => password_hash(request_string('password'), PASSWORD_DEFAULT),
        'is_active' => request_int('is_active', 1),
    ]);
    set_flash('success', 'User created successfully.');
    redirect('/modules/users/index.php');
}

$pageTitle = 'Add User';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body">
<form method="post" class="row g-3"><?= csrf_field() ?>
    <?php if (is_super_admin()): ?><div class="col-md-6"><label class="form-label">Company</label><select class="form-select" name="company_id" required><option value="">Select company</option><?= company_select_options() ?></select></div><?php endif; ?>
    <div class="col-md-6"><label class="form-label">Role</label><select class="form-select" name="role" required><option value="company_admin">Company Admin</option><option value="company_staff">Company Staff</option><option value="client">Client</option><?php if (is_super_admin()): ?><option value="super_admin">Super Admin</option><?php endif; ?></select></div>
    <div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" name="full_name" required></div>
    <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
    <div class="col-md-6"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required></div>
    <div class="col-md-6"><label class="form-label">Link Client (optional)</label><select class="form-select" name="client_id"><option value="">None</option><?= client_select_options() ?></select></div>
    <div class="col-md-3"><label class="form-label">Active</label><select class="form-select" name="is_active"><option value="1">Yes</option><option value="0">No</option></select></div>
    <div class="col-12"><button class="btn btn-primary">Save User</button> <a class="btn btn-link" href="/modules/users/index.php">Cancel</a></div>
</form>
</div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
