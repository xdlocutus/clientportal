<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

if (is_logged_in()) {
    redirect('/modules/dashboard/index.php');
}

if (is_post()) {
    verify_csrf();
    $email = filter_var(request_string('email'), FILTER_VALIDATE_EMAIL) ?: '';
    $password = request_string('password');

    if ($email && $password && attempt_login($email, $password)) {
        set_flash('success', 'Welcome back!');
        redirect('/modules/dashboard/index.php');
    }

    set_flash('danger', 'Invalid email or password.');
}

$pageTitle = 'Login';
require BASE_PATH . '/includes/header.php';
?>
<div class="row align-items-center g-4 justify-content-center">
    <div class="col-lg-5">
        <div class="auth-feature">
            <div class="auth-kicker">Modern client management</div>
            <h1 class="display-6 fw-bold mb-3">A cleaner workspace for billing, support, and tenant operations.</h1>
            <p class="lead text-body-secondary mb-0">Manage companies, clients, invoices, services, and tickets from a single dashboard with secure tenant isolation.</p>
            <ul class="auth-feature-list">
                <li><span>✓</span><div><strong>Multi-tenant by default</strong><div class="text-body-secondary">Every operational module is scoped by company and role.</div></div></li>
                <li><span>✓</span><div><strong>Fast admin workflows</strong><div class="text-body-secondary">Bootstrap-powered tables and forms keep common actions quick.</div></div></li>
                <li><span>✓</span><div><strong>Client-friendly portal</strong><div class="text-body-secondary">Clients can securely review invoices and support tickets.</div></div></li>
            </ul>
        </div>
    </div>
    <div class="col-lg-5 col-xl-4">
        <div class="auth-panel">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <div class="auth-kicker mb-2">Welcome back</div>
                    <h2 class="h3 mb-1">Sign in</h2>
                    <p class="text-body-secondary mb-0">Enter your portal credentials to continue.</p>
                </div>
            </div>
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="name@company.com" autocomplete="email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" autocomplete="current-password" required>
                </div>
                <button class="btn btn-primary w-100 py-3" type="submit">Login</button>
            </form>
            <div class="mt-4 small text-body-secondary">
                Need a portal account? Ask your administrator or use the self-registration page if enabled.
            </div>
            <p class="mt-2 mb-0"><a href="/modules/auth/register.php">Client self-registration</a></p>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h1 class="h3 mb-3">Sign in</h1>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Login</button>
                </form>
                <p class="mt-3 mb-0 text-muted small">Need a portal account? Ask your administrator or use the self-registration page if enabled.</p>
                <p class="mt-2 mb-0"><a href="/modules/auth/register.php">Client self-registration</a></p>
            </div>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
