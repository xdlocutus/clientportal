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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php if (is_logged_in()): ?>
<div class="app-shell d-flex">
    <?php require BASE_PATH . '/includes/sidebar.php'; ?>
    <main class="app-content flex-grow-1">
        <?php require BASE_PATH . '/includes/topbar.php'; ?>
        <div class="container-fluid py-4">
            <?php foreach ($flashMessages as $flash): ?>
                <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= h($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
<?php else: ?>
<div class="container py-5">
    <?php foreach ($flashMessages as $flash): ?>
        <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= h($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
