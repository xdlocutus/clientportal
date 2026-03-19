<?php declare(strict_types=1); ?>
<?php
$currentPath = $_SERVER['SCRIPT_NAME'] ?? '';
$navItems = [
    ['label' => 'Dashboard', 'href' => '/modules/dashboard/index.php', 'match' => '/modules/dashboard/', 'icon' => '🏠', 'show' => true],
    ['label' => 'Companies', 'href' => '/modules/companies/index.php', 'match' => '/modules/companies/', 'icon' => '🏢', 'show' => is_super_admin()],
    ['label' => 'Users', 'href' => '/modules/users/index.php', 'match' => '/modules/users/', 'icon' => '👥', 'show' => has_role(['super_admin', 'company_admin'])],
    ['label' => 'Clients', 'href' => '/modules/clients/index.php', 'match' => '/modules/clients/', 'icon' => '🤝', 'show' => has_role(['super_admin', 'company_admin', 'company_staff'])],
    ['label' => 'Services', 'href' => '/modules/services/index.php', 'match' => '/modules/services/', 'icon' => '🧩', 'show' => has_role(['super_admin', 'company_admin', 'company_staff'])],
    ['label' => 'Invoices', 'href' => '/modules/invoices/index.php', 'match' => '/modules/invoices/', 'icon' => '🧾', 'show' => true],
    ['label' => 'Tickets', 'href' => '/modules/tickets/index.php', 'match' => '/modules/tickets/', 'icon' => '🎫', 'show' => true],
    ['label' => 'Settings', 'href' => '/modules/settings/index.php', 'match' => '/modules/settings/', 'icon' => '⚙️', 'show' => !is_client_role()],
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
            <?php $active = str_contains($currentPath, $item['match']) ? ' active' : ''; ?>
            <a class="nav-link sidebar-link<?= $active ?>" href="<?= h($item['href']) ?>">
                <span class="sidebar-icon" aria-hidden="true"><?= $item['icon'] ?></span>
                <span><?= h($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer mt-auto pt-4">
        <div class="small text-body-secondary mb-2">Signed in as</div>
        <div class="fw-semibold"><?= h(current_user()['full_name'] ?? '') ?></div>
        <div class="small text-body-secondary mb-3 text-break"><?= h(current_user()['email'] ?? '') ?></div>
        <a class="btn btn-outline-secondary w-100" href="/modules/auth/logout.php">Logout</a>
    </div>
<aside class="sidebar bg-dark text-white p-3">
    <div class="sidebar-brand mb-4">
        <a class="text-decoration-none text-white fs-4 fw-bold" href="/index.php"><?= h(APP_NAME) ?></a>
    </div>
    <nav class="nav flex-column gap-1">
        <a class="nav-link text-white" href="/modules/dashboard/index.php">Dashboard</a>
        <?php if (is_super_admin()): ?>
            <a class="nav-link text-white" href="/modules/companies/index.php">Companies</a>
        <?php endif; ?>
        <?php if (has_role(['super_admin', 'company_admin'])): ?>
            <a class="nav-link text-white" href="/modules/users/index.php">Users</a>
        <?php endif; ?>
        <?php if (has_role(['super_admin', 'company_admin', 'company_staff'])): ?>
            <a class="nav-link text-white" href="/modules/clients/index.php">Clients</a>
            <a class="nav-link text-white" href="/modules/services/index.php">Services</a>
        <?php endif; ?>
        <a class="nav-link text-white" href="/modules/invoices/index.php">Invoices</a>
        <a class="nav-link text-white" href="/modules/tickets/index.php">Tickets</a>
        <?php if (!is_client_role()): ?>
            <a class="nav-link text-white" href="/modules/settings/index.php">Settings</a>
        <?php endif; ?>
        <a class="nav-link text-white" href="/modules/auth/logout.php">Logout</a>
    </nav>
</aside>
