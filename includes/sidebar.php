<?php declare(strict_types=1); ?>
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
