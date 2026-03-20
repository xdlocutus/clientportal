<?php

declare(strict_types=1);

$branding = $companyBranding ?? portal_branding();
$primaryNavItems = [
    ['label' => 'Dashboard', 'href' => '/modules/dashboard/index.php', 'visible' => true, 'icon' => '◫'],
];
$navGroups = [
    [
        'label' => 'Workspace',
        'icon' => '⌘',
        'items' => [
            ['label' => 'Clients', 'href' => '/modules/clients/index.php', 'visible' => has_permission('clients.view'), 'icon' => '◎'],
            ['label' => 'Quotes', 'href' => '/modules/invoices/index.php', 'visible' => has_permission('invoices.view'), 'icon' => '◩'],
            ['label' => 'Billing', 'href' => '/modules/billing/index.php', 'visible' => has_permission('invoices.view'), 'icon' => '¤'],
            ['label' => 'Jobcards', 'href' => '/modules/jobs/index.php', 'visible' => has_permission('jobs.view'), 'icon' => '🛠'],
            ['label' => 'Tickets', 'href' => '/modules/tickets/index.php', 'visible' => has_permission('tickets.view'), 'icon' => '✉'],
        ],
    ],
    [
        'label' => 'Catalog',
        'icon' => '◈',
        'items' => [
            ['label' => 'Services', 'href' => '/modules/services/index.php', 'visible' => has_permission('services.view'), 'icon' => '✦'],
            ['label' => 'Products', 'href' => '/modules/products/index.php', 'visible' => has_permission('products.view'), 'icon' => '⬡'],
            ['label' => 'Exports', 'href' => '/modules/exports/index.php', 'visible' => has_permission('exports.view'), 'icon' => '⇩'],
        ],
    ],
    [
        'label' => 'Admin',
        'icon' => '⚙',
        'items' => [
            ['label' => 'Companies', 'href' => '/modules/companies/index.php', 'visible' => is_super_admin(), 'icon' => '▣'],
            ['label' => 'Users', 'href' => '/modules/users/index.php', 'visible' => has_permission('users.manage'), 'icon' => '◌'],
            ['label' => 'Reports', 'href' => '/modules/reports/index.php', 'visible' => has_permission('reports.view'), 'icon' => '▤'],
            ['label' => 'Widgets', 'href' => '/modules/dashboard_widgets/index.php', 'visible' => has_permission('dashboard_widgets.manage'), 'icon' => '◧'],
            ['label' => 'Settings', 'href' => '/modules/settings/index.php', 'visible' => has_permission('settings.manage'), 'icon' => '⚙'],
        ],
    ],
];
?>
<aside class="sidebar p-3">
    <div class="sidebar-brand mb-4">
        <a class="text-decoration-none text-white fs-4 fw-bold d-inline-flex align-items-center gap-2" href="/index.php">
            <?php if ($branding['logo_url'] !== ''): ?>
                <img class="sidebar-brand-logo" src="<?= h($branding['logo_url']) ?>" alt="<?= h($branding['brand_name']) ?> logo">
            <?php else: ?>
                <span class="sidebar-brand-mark"><?= h($branding['brand_initials']) ?></span>
            <?php endif; ?>
            <span><?= h($branding['brand_name']) ?></span>
        </a>
        <p class="sidebar-copy mb-0 mt-3"><?= h($branding['portal_tagline']) ?></p>
    </div>
    <nav class="nav flex-column gap-2 sidebar-nav">
        <?php foreach ($primaryNavItems as $item): ?>
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
        <?php foreach ($navGroups as $group): ?>
            <?php
            $visibleItems = array_values(array_filter(
                $group['items'],
                static fn(array $item): bool => $item['visible']
            ));

            if ($visibleItems === []) {
                continue;
            }

            $groupIsActive = false;
            foreach ($visibleItems as $item) {
                if (str_starts_with($currentPath, dirname($item['href'])) || $currentPath === $item['href']) {
                    $groupIsActive = true;
                    break;
                }
            }
            ?>
            <details class="sidebar-group"<?= $groupIsActive ? ' open' : '' ?>>
                <summary class="sidebar-group-toggle">
                    <span class="nav-link sidebar-group-summary<?= $groupIsActive ? ' active' : '' ?>">
                        <span class="nav-icon" aria-hidden="true"><?= h($group['icon']) ?></span>
                        <span><?= h($group['label']) ?></span>
                        <span class="sidebar-group-chevron ms-auto" aria-hidden="true">⌄</span>
                    </span>
                </summary>
                <div class="sidebar-group-items">
                    <?php foreach ($visibleItems as $item): ?>
                        <?php $isActive = str_starts_with($currentPath, dirname($item['href'])) || $currentPath === $item['href']; ?>
                        <a class="nav-link sidebar-sublink<?= $isActive ? ' active' : '' ?>" href="<?= h($item['href']) ?>">
                            <span class="nav-icon" aria-hidden="true"><?= h($item['icon']) ?></span>
                            <span><?= h($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </details>
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
