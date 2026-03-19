<?php

declare(strict_types=1);

require_once BASE_PATH . '/includes/functions.php';

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
