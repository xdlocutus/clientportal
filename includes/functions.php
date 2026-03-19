<?php

declare(strict_types=1);

require_once BASE_PATH . '/config/database.php';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flash_messages(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('CSRF validation failed.');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function request_string(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $_GET[$key] ?? $default));
}

function request_int(string $key, int $default = 0): int
{
    return (int) ($_POST[$key] ?? $_GET[$key] ?? $default);
}

function money_format_portal(float $value, ?string $currency = null): string
{
    $code = $currency ?: setting('currency', 'USD');
    return $code . ' ' . number_format($value, 2);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function company_scope_sql(string $column = 'company_id', ?string $tableAlias = null): string
{
    $qualified = $tableAlias ? $tableAlias . '.' . $column : $column;
    return is_super_admin() ? '1=1' : $qualified . ' = :company_id';
}

function company_scope_params(): array
{
    return is_super_admin() ? [] : ['company_id' => current_company_id()];
}

function setting(string $key, string $default = ''): string
{
    static $cache = [];

    $companyId = current_company_id();
    if (!$companyId) {
        return $default;
    }

    if (!isset($cache[$companyId])) {
        $stmt = db()->prepare('SELECT setting_key, setting_value FROM settings WHERE company_id = :company_id');
        $stmt->execute(['company_id' => $companyId]);
        $cache[$companyId] = [];
        foreach ($stmt->fetchAll() as $row) {
            $cache[$companyId][$row['setting_key']] = $row['setting_value'];
        }
    }

    return $cache[$companyId][$key] ?? $default;
}

function invoice_status_badge(string $status): string
{
    $classes = [
        'draft' => 'secondary',
        'sent' => 'info',
        'paid' => 'success',
        'overdue' => 'danger',
        'cancelled' => 'dark',
        'unpaid' => 'warning',
        'open' => 'primary',
        'closed' => 'secondary',
    ];

    $class = $classes[$status] ?? 'secondary';
    return '<span class="badge bg-' . $class . '">' . h(ucfirst($status)) . '</span>';
}

function company_select_options(?int $selected = null): string
{
    $html = '';
    $sql = is_super_admin()
        ? 'SELECT id, name FROM companies ORDER BY name'
        : 'SELECT id, name FROM companies WHERE id = :company_id ORDER BY name';
    $stmt = db()->prepare($sql);
    $stmt->execute(company_scope_params());
    foreach ($stmt->fetchAll() as $company) {
        $isSelected = $selected === (int) $company['id'] ? ' selected' : '';
        $html .= '<option value="' . (int) $company['id'] . '"' . $isSelected . '>' . h($company['name']) . '</option>';
    }
    return $html;
}

function client_select_options(?int $selected = null, ?int $companyId = null): string
{
    $params = [];
    $sql = 'SELECT id, company_name FROM clients WHERE 1=1';

    if ($companyId) {
        $sql .= ' AND company_id = :company_id';
        $params['company_id'] = $companyId;
    } elseif (!is_super_admin()) {
        $sql .= ' AND company_id = :company_id';
        $params['company_id'] = current_company_id();
    }

    $sql .= ' ORDER BY company_name';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $html = '';
    foreach ($stmt->fetchAll() as $client) {
        $isSelected = $selected === (int) $client['id'] ? ' selected' : '';
        $html .= '<option value="' . (int) $client['id'] . '"' . $isSelected . '>' . h($client['company_name']) . '</option>';
    }

    return $html;
}

function user_select_options(?int $selected = null, array $roles = []): string
{
    $params = [];
    $sql = 'SELECT id, full_name, role FROM users WHERE 1=1';

    if (!is_super_admin()) {
        $sql .= ' AND company_id = :company_id';
        $params['company_id'] = current_company_id();
    }

    if ($roles !== []) {
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $sql .= ' AND role IN (' . $placeholders . ')';
    }

    $sql .= ' ORDER BY full_name';

    $stmt = db()->prepare($sql);
    $stmt->execute(array_values(array_merge($params, $roles)));

    $html = '';
    foreach ($stmt->fetchAll() as $user) {
        $isSelected = $selected === (int) $user['id'] ? ' selected' : '';
        $html .= '<option value="' . (int) $user['id'] . '"' . $isSelected . '>' . h($user['full_name'] . ' (' . $user['role'] . ')') . '</option>';
    }

    return $html;
}

function require_company_access(int $companyId): void
{
    if (!is_super_admin() && current_company_id() !== $companyId) {
        set_flash('danger', 'You are not allowed to access that company resource.');
        redirect('/index.php');
    }
}
