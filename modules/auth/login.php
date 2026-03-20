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
<div class="row justify-content-center align-items-center g-4 min-vh-100 py-4">
    <div class="col-lg-5">
        <div class="hero-panel">
            <span class="eyebrow-label">Modern client operations</span>
            <h1 class="display-6 fw-semibold mt-2 mb-3">A refreshed portal built for focus, speed, and after-hours work.</h1>
            <p class="mb-4 text-body-secondary">Use the new appearance toggle once you sign in to switch between a crisp daytime layout and a low-glare dark mode.</p>
            <div class="hero-feature-list">
                <div class="hero-feature"><span>✓</span><span>Sharper navigation and cleaner spacing</span></div>
                <div class="hero-feature"><span>✓</span><span>Improved contrast for dashboards, tables, and forms</span></div>
                <div class="hero-feature"><span>✓</span><span>Persistent theme preference on every page</span></div>
            </div>
        </div>
    </div>
    <div class="col-md-7 col-lg-5">
        <div class="card shadow-sm border-0 auth-card">
            <div class="card-body p-4 p-lg-5">
                <span class="eyebrow-label">Welcome back</span>
                <h1 class="h3 mb-3 mt-2">Sign in to your portal</h1>
                <p class="text-body-secondary mb-4">Access clients, billing, support, and company operations from one place.</p>
                <form method="post" class="vstack gap-3">
                    <?= csrf_field() ?>
                    <div>
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control form-control-lg" placeholder="you@company.com" required>
                    </div>
                    <div>
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control form-control-lg" placeholder="Enter your password" required>
                    </div>
                    <button class="btn btn-primary btn-lg w-100" type="submit">Login</button>
                </form>
                <p class="mt-4 mb-0 text-body-secondary small">Need a portal account? Ask your administrator or use the self-registration page if enabled.</p>
                <p class="mt-2 mb-0"><a href="/modules/auth/register.php">Client self-registration</a></p>
            </div>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
