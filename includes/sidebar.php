<?php

declare(strict_types=1);

$navItems = [
    ['label' => 'Dashboard', 'href' => '/modules/dashboard/index.php', 'visible' => true, 'icon' => '◫'],
    ['label' => 'Companies', 'href' => '/modules/companies/index.php', 'visible' => is_super_admin(), 'icon' => '▣'],
    ['label' => 'Users', 'href' => '/modules/users/index.php', 'visible' => has_role(['super_admin', 'company_admin']), 'icon' => '◌'],
    ['label' => 'Clients', 'href' => '/modules/clients/index.php', 'visible' => has_role(['super_admin', 'company_admin', 'company_staff']), 'icon' => '◎'],
    ['label' => 'Services', 'href' => '/modules/services/index.php', 'visible' => has_role(['super_admin', 'company_admin', 'company_staff']), 'icon' => '✦'],
    ['label' => 'Invoices', 'href' => '/modules/invoices/index.php', 'visible' => true, 'icon' => '◩'],
    ['label' => 'Tickets', 'href' => '/modules/tickets/index.php', 'visible' => true, 'icon' => '✉'],
    ['label' => 'Settings', 'href' => '/modules/settings/index.php', 'visible' => !is_client_role(), 'icon' => '⚙'],
];
?>
<aside class="sidebar p-3">
    <div class="sidebar-brand mb-4">
        <a class="text-decoration-none text-white fs-4 fw-bold d-inline-flex align-items-center gap-2" href="/index.php">
            <span class="sidebar-brand-mark">BP</span>
            <span><?= h(APP_NAME) ?></span>
        </a>
        <p class="sidebar-copy mb-0 mt-3">A cleaner, faster workspace for your team and clients.</p>
    </div>
    <nav class="nav flex-column gap-2">
        <?php foreach ($navItems as $item): ?>
            <?php if (!$item['visible']) {
                continue;
            }

            $isActive = str_starts_with($currentPath, dirname($item['href'])) || $currentPath === $item['href'];
            ?>
            <a class="nav-link<?= $isActive ? ' active' : '' ?>" href="<?= h($item['href']) ?>">
                <span class="nav-icon" aria-hidden="true"><?= h($item['icon']) ?></span>
                <span><?= h($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
        <a class="nav-link logout-link mt-2" href="/modules/auth/logout.php">
            <span class="nav-icon" aria-hidden="true">↗</span>
            <span>Logout</span>
        </a>
    </nav>
    <div class="sidebar-footer mt-auto pt-4">
        <div class="sidebar-user-label">Signed in as</div>
        <div class="sidebar-user-name"><?= h(current_user()['full_name'] ?? '') ?></div>
        <div class="sidebar-user-role"><?= h(ucwords(str_replace('_', ' ', current_user()['role'] ?? ''))) ?></div>
    </div>
</aside>
