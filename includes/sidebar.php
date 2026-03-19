<?php declare(strict_types=1); ?>
<?php
$currentPath = $_SERVER['SCRIPT_NAME'] ?? '';
$navItems = [
    ['label' => 'Dashboard', 'href' => '/modules/dashboard/index.php', 'icon' => '🏠', 'show' => true],
    ['label' => 'Companies', 'href' => '/modules/companies/index.php', 'icon' => '🏢', 'show' => is_super_admin()],
    ['label' => 'Users', 'href' => '/modules/users/index.php', 'icon' => '👥', 'show' => has_role(['super_admin', 'company_admin'])],
    ['label' => 'Clients', 'href' => '/modules/clients/index.php', 'icon' => '🤝', 'show' => has_role(['super_admin', 'company_admin', 'company_staff'])],
    ['label' => 'Services', 'href' => '/modules/services/index.php', 'icon' => '🧩', 'show' => has_role(['super_admin', 'company_admin', 'company_staff'])],
    ['label' => 'Invoices', 'href' => '/modules/invoices/index.php', 'icon' => '🧾', 'show' => true],
    ['label' => 'Tickets', 'href' => '/modules/tickets/index.php', 'icon' => '🎫', 'show' => true],
    ['label' => 'Settings', 'href' => '/modules/settings/index.php', 'icon' => '⚙️', 'show' => !is_client_role()],
];
?>
<aside class="sidebar p-3 p-lg-4">
    <div class="sidebar-brand mb-4">
        <a class="text-decoration-none text-reset" href="/index.php">
            <span class="brand-badge">BP</span>
            <span>
                <span class="d-block fs-4 fw-bold"><?= h(APP_NAME) ?></span>
                <span class="small text-body-secondary">Multi-tenant operations hub</span>
            </span>
        </a>
    </div>
    <div class="sidebar-section-label">Navigation</div>
    <nav class="nav flex-column gap-2">
        <?php foreach ($navItems as $item): ?>
            <?php if (!$item['show']) { continue; } ?>
            <?php $active = str_starts_with($currentPath, dirname($item['href'])) ? ' active' : ''; ?>
            <a class="nav-link sidebar-link<?= $active ?>" href="<?= h($item['href']) ?>">
                <span class="sidebar-icon"><?= $item['icon'] ?></span>
                <span><?= h($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer mt-auto pt-4">
        <div class="small text-body-secondary mb-2">Signed in as</div>
        <div class="fw-semibold"><?= h(current_user()['full_name'] ?? '') ?></div>
        <div class="small text-body-secondary mb-3"><?= h(current_user()['email'] ?? '') ?></div>
        <a class="btn btn-outline-secondary w-100" href="/modules/auth/logout.php">Logout</a>
    </div>
</aside>
