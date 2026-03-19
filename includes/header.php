<?php

declare(strict_types=1);

require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

$pageTitle = $pageTitle ?? APP_NAME;
$flashMessages = get_flash_messages();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> - <?= h(APP_NAME) ?></title>
    <script>
        (() => {
            const storedTheme = localStorage.getItem('business-portal-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = storedTheme || (prefersDark ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body class="portal-body">
<?php if (is_logged_in()): ?>
<div class="app-shell d-flex">
    <?php require BASE_PATH . '/includes/sidebar.php'; ?>
    <main class="app-content flex-grow-1">
        <?php require BASE_PATH . '/includes/topbar.php'; ?>
        <div class="container-fluid py-4 py-lg-5 px-4 px-lg-5">
            <?php foreach ($flashMessages as $flash): ?>
                <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show modern-alert" role="alert">
                    <?= h($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
<?php else: ?>
<div class="theme-toggle-floating">
    <button type="button" class="btn btn-outline-secondary theme-toggle-btn" data-theme-toggle aria-label="Toggle color theme">
        <span class="theme-toggle-icon" aria-hidden="true">🌙</span>
        <span class="theme-toggle-label">Dark mode</span>
    </button>
</div>
<div class="auth-shell">
    <div class="container py-5">
        <?php foreach ($flashMessages as $flash): ?>
            <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show modern-alert" role="alert">
                <?= h($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>
<?php endif; ?>
