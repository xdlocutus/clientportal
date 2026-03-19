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
