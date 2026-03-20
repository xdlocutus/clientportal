<?php declare(strict_types=1);

$branding = $companyBranding ?? portal_branding();
?>
<nav class="navbar portal-topbar px-4 py-3">
    <div class="container-fluid p-0 align-items-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <?php if ($branding['logo_url'] !== ''): ?>
                <img class="topbar-company-logo" src="<?= h($branding['logo_url']) ?>" alt="<?= h($branding['brand_name']) ?> logo">
            <?php endif; ?>
            <div>
                <span class="eyebrow-label">Workspace</span>
                <div class="navbar-brand mb-0 h1"><?= h($pageTitle) ?></div>
                <small class="text-body-secondary"><?= h($branding['brand_name']) ?></small>
            </div>
        </div>
        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="status-pill"><?= h(ucwords(str_replace('_', ' ', current_user()['role'] ?? ''))) ?></span>
            <button
                type="button"
                class="btn btn-outline-secondary theme-toggle"
                data-theme-toggle
                data-theme-toggle-label="Switch to dark mode"
                aria-label="Switch color theme"
            >
                <span class="theme-toggle-icon" aria-hidden="true">🌙</span>
                <span class="theme-toggle-text">Dark mode</span>
            </button>
            <div class="text-end">
                <div class="fw-semibold"><?= h(current_user()['full_name'] ?? '') ?></div>
                <small class="text-body-secondary">Manage <?= h($branding['brand_name']) ?></small>
            </div>
        </div>
    </div>
</nav>
