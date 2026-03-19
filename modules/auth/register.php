<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

if (is_logged_in()) {
    redirect('/modules/dashboard/index.php');
}

$companies = db()->query('SELECT id, name FROM companies ORDER BY name')->fetchAll();

if (is_post()) {
    verify_csrf();

    $companyId = request_int('company_id');
    $fullName = request_string('full_name');
    $email = filter_var(request_string('email'), FILTER_VALIDATE_EMAIL) ?: '';
    $password = request_string('password');
    $clientCompany = request_string('company_name');
    $phone = request_string('phone');

    if ($companyId < 1 || $fullName === '' || $email === '' || $password === '' || $clientCompany === '') {
        set_flash('danger', 'Please complete all required fields.');
    } else {
        $check = db()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $check->execute(['email' => $email]);

        if ($check->fetch()) {
            set_flash('danger', 'That email address is already in use.');
        } else {
            $clientStmt = db()->prepare('INSERT INTO clients (company_id, company_name, contact_name, email, phone, status, created_at, updated_at) VALUES (:company_id, :company_name, :contact_name, :email, :phone, :status, NOW(), NOW())');
            $clientStmt->execute([
                'company_id' => $companyId,
                'company_name' => $clientCompany,
                'contact_name' => $fullName,
                'email' => $email,
                'phone' => $phone,
                'status' => 'active',
            ]);

            $clientId = (int) db()->lastInsertId();
            $userStmt = db()->prepare('INSERT INTO users (company_id, client_id, role, full_name, email, password, is_active, created_at, updated_at) VALUES (:company_id, :client_id, :role, :full_name, :email, :password, 1, NOW(), NOW())');
            $userStmt->execute([
                'company_id' => $companyId,
                'client_id' => $clientId,
                'role' => 'client',
                'full_name' => $fullName,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            set_flash('success', 'Registration complete. You can now log in.');
            redirect('/modules/auth/login.php');
        }
    }
}

$pageTitle = 'Register';
require BASE_PATH . '/includes/header.php';
?>
<div class="row align-items-center g-4 justify-content-center">
    <div class="col-lg-5">
        <div class="auth-feature">
            <div class="auth-kicker">Self-service onboarding</div>
            <h1 class="display-6 fw-bold mb-3">Create a client portal account in minutes.</h1>
            <p class="lead text-body-secondary mb-0">Submit your company details, choose the tenant you belong to, and get access to invoices and support tickets.</p>
            <ul class="auth-feature-list">
                <li><span>✓</span><div><strong>Secure authentication</strong><div class="text-body-secondary">Passwords are hashed and sessions are regenerated on sign-in.</div></div></li>
                <li><span>✓</span><div><strong>Client-only visibility</strong><div class="text-body-secondary">Registered clients only see their own invoices and tickets.</div></div></li>
                <li><span>✓</span><div><strong>Designed for growth</strong><div class="text-body-secondary">Modular pages make future portal expansion straightforward.</div></div></li>
            </ul>
        </div>
    </div>
    <div class="col-lg-6 col-xl-5">
        <div class="auth-panel">
            <div class="auth-kicker mb-2">Client access</div>
            <h2 class="h3 mb-1">Register</h2>
            <p class="text-body-secondary mb-4">Complete the form below to request portal access.</p>
            <form method="post" class="row g-3">
                <?= csrf_field() ?>
                <div class="col-md-6">
                    <label class="form-label">Company / Tenant</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= (int) $company['id'] ?>"><?= h($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Your Company Name</label>
                    <input type="text" name="company_name" class="form-control" autocomplete="organization" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" autocomplete="name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" autocomplete="tel">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" autocomplete="email" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" autocomplete="new-password" required>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                    <button class="btn btn-primary px-4" type="submit">Register</button>
                    <a class="btn btn-link" href="/modules/auth/login.php">Back to login</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
