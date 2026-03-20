<?php

declare(strict_types=1);

require_once BASE_PATH . '/includes/functions.php';

function permission_catalog(): array
{
    return [
        'clients.view' => [
            'label' => 'View clients',
            'description' => 'See the clients list and client records.',
            'category' => 'Clients',
        ],
        'clients.create' => [
            'label' => 'Create clients',
            'description' => 'Add new client records.',
            'category' => 'Clients',
        ],
        'clients.edit' => [
            'label' => 'Edit clients',
            'description' => 'Update existing client records.',
            'category' => 'Clients',
        ],
        'clients.delete' => [
            'label' => 'Delete clients',
            'description' => 'Remove client records.',
            'category' => 'Clients',
        ],
        'services.view' => [
            'label' => 'View services',
            'description' => 'See services linked to company clients.',
            'category' => 'Services',
        ],
        'services.create' => [
            'label' => 'Create services',
            'description' => 'Add new services.',
            'category' => 'Services',
        ],
        'services.edit' => [
            'label' => 'Edit services',
            'description' => 'Update existing services.',
            'category' => 'Services',
        ],
        'services.delete' => [
            'label' => 'Delete services',
            'description' => 'Remove services.',
            'category' => 'Services',
        ],
        'products.view' => [
            'label' => 'View products',
            'description' => 'See the products catalog for this company.',
            'category' => 'Products',
        ],
        'products.create' => [
            'label' => 'Create products',
            'description' => 'Add new products to the catalog.',
            'category' => 'Products',
        ],
        'products.edit' => [
            'label' => 'Edit products',
            'description' => 'Update existing products.',
            'category' => 'Products',
        ],
        'products.delete' => [
            'label' => 'Delete products',
            'description' => 'Remove products from the catalog.',
            'category' => 'Products',
        ],
        'invoices.view' => [
            'label' => 'View quotes & invoices',
            'description' => 'Open the quotes/invoices list and detail pages.',
            'category' => 'Quotes & invoices',
        ],
        'invoices.create' => [
            'label' => 'Create quotes & invoices',
            'description' => 'Create new quotes/invoices.',
            'category' => 'Quotes & invoices',
        ],
        'invoices.edit' => [
            'label' => 'Edit quotes & invoices',
            'description' => 'Update existing quotes/invoices.',
            'category' => 'Quotes & invoices',
        ],
        'invoices.delete' => [
            'label' => 'Delete quotes & invoices',
            'description' => 'Remove quotes/invoices.',
            'category' => 'Quotes & invoices',
        ],
        'tickets.view' => [
            'label' => 'View tickets',
            'description' => 'Open support tickets and conversations.',
            'category' => 'Tickets',
        ],
        'tickets.create' => [
            'label' => 'Create tickets',
            'description' => 'Open new support tickets.',
            'category' => 'Tickets',
        ],
        'tickets.reply' => [
            'label' => 'Reply to tickets',
            'description' => 'Post replies in ticket conversations.',
            'category' => 'Tickets',
        ],
        'tickets.close' => [
            'label' => 'Close tickets',
            'description' => 'Close open tickets.',
            'category' => 'Tickets',
        ],
        'reports.view' => [
            'label' => 'View reports',
            'description' => 'Open custom sales, revenue, unpaid invoice, and ticket reports.',
            'category' => 'Reporting',
        ],
        'dashboard_widgets.manage' => [
            'label' => 'Manage dashboard widgets',
            'description' => 'Choose which dashboard widgets each company sees.',
            'category' => 'Reporting',
        ],
        'exports.view' => [
            'label' => 'Export data',
            'description' => 'Export invoices, tickets, and client lists as CSV or print/PDF views.',
            'category' => 'Reporting',
        ],
        'users.manage' => [
            'label' => 'Manage users',
            'description' => 'Create users and update team access.',
            'category' => 'Administration',
        ],
        'settings.manage' => [
            'label' => 'Manage settings',
            'description' => 'Change company settings and invoice defaults.',
            'category' => 'Administration',
        ],
    ];
}

function permission_groups(): array
{
    $groups = [];
    foreach (permission_catalog() as $key => $permission) {
        $groups[$permission['category']][$key] = $permission;
    }

    return $groups;
}

function default_permissions_for_role(string $role): array
{
    return match ($role) {
        'super_admin', 'company_admin' => array_keys(permission_catalog()),
        'company_staff' => ['invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete'],
        'client' => ['invoices.view', 'tickets.view', 'tickets.create', 'tickets.reply'],
        default => [],
    };
}

function normalize_permissions(array $permissions): array
{
    $catalog = permission_catalog();
    $normalized = [];

    foreach ($permissions as $permission) {
        $permission = (string) $permission;
        if (isset($catalog[$permission])) {
            $normalized[] = $permission;
        }
    }

    $normalized = array_values(array_unique($normalized));
    sort($normalized);

    return $normalized;
}

function permissions_storage_available(): bool
{
    static $checked = false;
    static $available = false;

    if ($checked) {
        return $available;
    }

    $checked = true;

    try {
        db()->query('SELECT 1 FROM user_permissions LIMIT 1');
        $available = true;
        return true;
    } catch (PDOException) {
        try {
            db()->exec(
                'CREATE TABLE IF NOT EXISTS user_permissions (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    permission_key VARCHAR(120) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT fk_user_permissions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                    UNIQUE KEY uq_user_permission (user_id, permission_key),
                    KEY idx_user_permissions_key (permission_key)
                ) ENGINE=InnoDB'
            );
            $available = true;
        } catch (PDOException) {
            $available = false;
        }
    }

    return $available;
}

function load_user_permissions(array $user): array
{
    if (in_array($user['role'], ['super_admin', 'company_admin'], true)) {
        return default_permissions_for_role($user['role']);
    }

    if (!permissions_storage_available()) {
        return default_permissions_for_role($user['role']);
    }

    $stmt = db()->prepare('SELECT permission_key FROM user_permissions WHERE user_id = :user_id ORDER BY permission_key');
    $stmt->execute(['user_id' => (int) $user['id']]);

    return normalize_permissions($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function sync_user_permissions(int $userId, string $role, array $permissions): array
{
    $permissions = in_array($role, ['super_admin', 'company_admin'], true)
        ? default_permissions_for_role($role)
        : normalize_permissions($permissions);

    if (!permissions_storage_available()) {
        return $permissions;
    }

    $delete = db()->prepare('DELETE FROM user_permissions WHERE user_id = :user_id');
    $delete->execute(['user_id' => $userId]);

    if ($permissions !== []) {
        $insert = db()->prepare('INSERT INTO user_permissions (user_id, permission_key, created_at, updated_at) VALUES (:user_id, :permission_key, NOW(), NOW())');
        foreach ($permissions as $permission) {
            $insert->execute([
                'user_id' => $userId,
                'permission_key' => $permission,
            ]);
        }
    }

    return $permissions;
}

function permissions_summary(array $permissions): string
{
    $permissions = normalize_permissions($permissions);
    if ($permissions === []) {
        return 'No extra permissions';
    }

    if (count($permissions) === count(permission_catalog())) {
        return 'Full access';
    }

    $labels = [];
    foreach ($permissions as $permission) {
        $labels[] = permission_catalog()[$permission]['label'];
    }

    return implode(', ', $labels);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function current_user_id(): ?int
{
    return isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;
}

function current_company_id(): ?int
{
    return isset($_SESSION['user']['company_id']) ? (int) $_SESSION['user']['company_id'] : null;
}

function current_client_id(): ?int
{
    return isset($_SESSION['user']['client_id']) ? (int) $_SESSION['user']['client_id'] : null;
}

function current_user_permissions(): array
{
    $user = current_user();
    return isset($user['permissions']) && is_array($user['permissions'])
        ? normalize_permissions($user['permissions'])
        : [];
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function has_role(string|array $roles): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    $roles = (array) $roles;
    return in_array($user['role'], $roles, true);
}

function is_super_admin(): bool
{
    return has_role('super_admin');
}

function is_company_admin(): bool
{
    return has_role('company_admin');
}

function is_company_staff(): bool
{
    return has_role('company_staff');
}

function is_client_role(): bool
{
    return has_role('client');
}

function has_permission(string|array $permissions): bool
{
    if (!is_logged_in()) {
        return false;
    }

    if (is_super_admin() || is_company_admin()) {
        return true;
    }

    $granted = current_user_permissions();
    foreach ((array) $permissions as $permission) {
        if (in_array($permission, $granted, true)) {
            return true;
        }
    }

    return false;
}

function require_permission(string|array $permissions, string $message = 'You do not have permission to access that area.'): void
{
    require_login();
    if (!has_permission($permissions)) {
        set_flash('danger', $message);
        redirect('/modules/dashboard/index.php');
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'company_id' => $user['company_id'] !== null ? (int) $user['company_id'] : null,
        'client_id' => $user['client_id'] !== null ? (int) $user['client_id'] : null,
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'permissions' => load_user_permissions($user),
    ];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function attempt_login(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email AND is_active = 1 LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    login_user($user);
    return true;
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Please log in to continue.');
        redirect('/modules/auth/login.php');
    }
}

function require_role(string|array $roles): void
{
    require_login();
    if (!has_role($roles)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function require_staff(): void
{
    require_role(['super_admin', 'company_admin', 'company_staff']);
}
