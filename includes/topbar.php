<?php declare(strict_types=1); ?>
<nav class="topbar px-4 px-lg-5 py-3">
    <div class="topbar-panel d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="eyebrow text-uppercase small fw-semibold">Business Portal</div>
            <h1 class="topbar-title h3 mb-0"><?= h($pageTitle) ?></h1>
        </div>
        <div class="d-flex align-items-center gap-3 ms-auto">
            <button type="button" class="btn btn-outline-secondary theme-toggle-btn" data-theme-toggle aria-label="Toggle color theme">
                <span class="theme-toggle-icon" aria-hidden="true">🌙</span>
                <span class="theme-toggle-label">Dark mode</span>
            </button>
            <div class="user-chip text-end">
                <div class="fw-semibold"><?= h(current_user()['full_name'] ?? '') ?></div>
                <small class="text-body-secondary"><?= h(ucwords(str_replace('_', ' ', current_user()['role'] ?? ''))) ?></small>
            </div>
        </div>
    </div>
</nav>
