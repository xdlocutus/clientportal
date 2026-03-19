<?php declare(strict_types=1); ?>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-4 shadow-sm">
    <div class="container-fluid p-0">
        <span class="navbar-brand mb-0 h1"><?= h($pageTitle) ?></span>
        <div class="ms-auto text-end">
            <div class="fw-semibold"><?= h(current_user()['full_name'] ?? '') ?></div>
            <small class="text-muted"><?= h(current_user()['role'] ?? '') ?></small>
        </div>
    </div>
</nav>
