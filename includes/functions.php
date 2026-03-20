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

function company_settings(int $companyId): array
{
    static $cache = [];

    if ($companyId < 1) {
        return [];
    }

    if (!isset($cache[$companyId])) {
        $stmt = db()->prepare('SELECT setting_key, setting_value FROM settings WHERE company_id = :company_id');
        $stmt->execute(['company_id' => $companyId]);
        $cache[$companyId] = [];
        foreach ($stmt->fetchAll() as $row) {
            $cache[$companyId][$row['setting_key']] = $row['setting_value'];
        }
    }

    return $cache[$companyId];
}

function setting(string $key, string $default = ''): string
{
    $companyId = current_company_id();
    if (!$companyId) {
        return $default;
    }

    $settings = company_settings($companyId);

    return $settings[$key] ?? $default;
}

function normalize_hex_color(?string $value, string $default = '#4f46e5'): string
{
    $candidate = strtoupper(trim((string) $value));
    if (preg_match('/^#([0-9A-F]{3}|[0-9A-F]{6})$/', $candidate) !== 1) {
        return strtoupper($default);
    }

    if (strlen($candidate) === 4) {
        return sprintf(
            '#%s%s%s%s%s%s',
            $candidate[1],
            $candidate[1],
            $candidate[2],
            $candidate[2],
            $candidate[3],
            $candidate[3]
        );
    }

    return $candidate;
}

function hex_to_rgb_string(string $hexColor): string
{
    $hexColor = ltrim(normalize_hex_color($hexColor), '#');

    return implode(', ', [
        (string) hexdec(substr($hexColor, 0, 2)),
        (string) hexdec(substr($hexColor, 2, 2)),
        (string) hexdec(substr($hexColor, 4, 2)),
    ]);
}

function brand_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return 'BP';
    }

    $parts = preg_split('/\s+/', $name) ?: [];
    $initials = '';

    foreach ($parts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'BP';
}

function portal_branding(?int $companyId = null): array
{
    $companyId ??= current_company_id() ?? 0;
    $settings = $companyId > 0 ? company_settings($companyId) : [];
    $companyName = APP_NAME;

    if ($companyId > 0) {
        $stmt = db()->prepare('SELECT name FROM companies WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $companyId]);
        $companyName = (string) ($stmt->fetchColumn() ?: APP_NAME);
    }

    $brandName = trim((string) ($settings['brand_name'] ?? ''));
    $displayName = trim((string) ($settings['company_name'] ?? '')) ?: $companyName;
    $primaryColor = normalize_hex_color($settings['primary_color'] ?? '#4f46e5');
    $logoUrl = trim((string) ($settings['logo_url'] ?? ''));
    $tagline = trim((string) ($settings['portal_tagline'] ?? '')) ?: 'A cleaner, faster workspace for your team and clients.';
    $welcomeMessage = trim((string) ($settings['dashboard_message'] ?? '')) ?: 'Track only the areas this user can access, so each team member sees a focused workspace.';

    return [
        'company_id' => $companyId,
        'company_name' => $displayName,
        'brand_name' => $brandName !== '' ? $brandName : $displayName,
        'logo_url' => $logoUrl,
        'primary_color' => $primaryColor,
        'primary_color_rgb' => hex_to_rgb_string($primaryColor),
        'portal_tagline' => $tagline,
        'dashboard_message' => $welcomeMessage,
        'brand_initials' => brand_initials($brandName !== '' ? $brandName : $displayName),
    ];
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
        'active' => 'success',
        'inactive' => 'secondary',
        'suspended' => 'warning',
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


function products_storage_available(): bool
{
    static $checked = false;
    static $available = false;

    if ($checked) {
        return $available;
    }

    $checked = true;

    try {
        db()->query('SELECT 1 FROM products LIMIT 1');
        $available = true;
        return true;
    } catch (PDOException) {
        try {
            db()->exec(
                "CREATE TABLE IF NOT EXISTS products (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    company_id INT UNSIGNED NOT NULL,
                    product_name VARCHAR(150) NOT NULL,
                    sku VARCHAR(80) DEFAULT NULL,
                    description TEXT DEFAULT NULL,
                    price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT fk_products_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
                    UNIQUE KEY uq_products_company_sku (company_id, sku),
                    KEY idx_products_company_status (company_id, status)
                ) ENGINE=InnoDB"
            );
            $available = true;
        } catch (PDOException) {
            $available = false;
        }
    }

    return $available;
}

function ensure_invoice_item_source_columns(): bool
{
    static $checked = false;
    static $available = false;

    if ($checked) {
        return $available;
    }

    $checked = true;

    try {
        db()->query('SELECT source_type, source_id FROM invoice_items LIMIT 1');
        $available = true;
        return true;
    } catch (PDOException) {
        try {
            db()->exec("ALTER TABLE invoice_items ADD COLUMN source_type VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER line_total");
        } catch (PDOException) {
        }

        try {
            db()->exec('ALTER TABLE invoice_items ADD COLUMN source_id INT UNSIGNED DEFAULT NULL AFTER source_type');
        } catch (PDOException) {
        }

        try {
            db()->query('SELECT source_type, source_id FROM invoice_items LIMIT 1');
            $available = true;
        } catch (PDOException) {
            $available = false;
        }
    }

    return $available;
}

function product_select_options(?int $selected = null, ?int $companyId = null, bool $includeInactive = false): string
{
    if (!products_storage_available()) {
        return '';
    }

    $params = [];
    $sql = 'SELECT id, product_name, sku, status FROM products WHERE 1=1';

    if ($companyId) {
        $sql .= ' AND company_id = :company_id';
        $params['company_id'] = $companyId;
    } elseif (!is_super_admin()) {
        $sql .= ' AND company_id = :company_id';
        $params['company_id'] = current_company_id();
    }

    if (!$includeInactive) {
        $sql .= " AND status = 'active'";
    }

    $sql .= ' ORDER BY product_name';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $html = '';
    foreach ($stmt->fetchAll() as $product) {
        $label = $product['product_name'];
        if ((string) $product['sku'] !== '') {
            $label .= ' (' . $product['sku'] . ')';
        }
        $isSelected = $selected === (int) $product['id'] ? ' selected' : '';
        $html .= '<option value="' . (int) $product['id'] . '"' . $isSelected . '>' . h($label) . '</option>';
    }

    return $html;
}

function invoice_catalog_items(?int $companyId = null): array
{
    $companyId = $companyId ?: (is_super_admin() ? null : (int) current_company_id());
    $catalog = [];

    if (products_storage_available()) {
        $params = [];
        $sql = "SELECT id, company_id, product_name, sku, description, price, status FROM products WHERE status = 'active'";
        if ($companyId) {
            $sql .= ' AND company_id = :company_id';
            $params['company_id'] = $companyId;
        }
        $sql .= ' ORDER BY product_name';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $product) {
            $catalog[] = [
                'type' => 'product',
                'id' => (int) $product['id'],
                'company_id' => (int) $product['company_id'],
                'client_id' => null,
                'name' => $product['product_name'],
                'description' => $product['description'] !== null && trim((string) $product['description']) !== ''
                    ? trim((string) $product['description'])
                    : trim((string) $product['product_name'] . (((string) $product['sku'] !== '') ? ' (' . $product['sku'] . ')' : '')),
                'price' => (float) $product['price'],
                'meta' => (string) $product['sku'],
            ];
        }
    }

    $params = [];
    $sql = "SELECT id, company_id, client_id, service_name, description, price, billing_cycle, status FROM services WHERE status = 'active'";
    if ($companyId) {
        $sql .= ' AND company_id = :company_id';
        $params['company_id'] = $companyId;
    }
    $sql .= ' ORDER BY service_name';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $service) {
        $catalog[] = [
            'type' => 'service',
            'id' => (int) $service['id'],
            'company_id' => (int) $service['company_id'],
            'client_id' => (int) $service['client_id'],
            'name' => $service['service_name'],
            'description' => $service['description'] !== null && trim((string) $service['description']) !== ''
                ? trim((string) $service['description'])
                : trim((string) $service['service_name'] . ' (' . ucfirst(str_replace('_', ' ', (string) $service['billing_cycle'])) . ')'),
            'price' => (float) $service['price'],
            'meta' => (string) $service['billing_cycle'],
        ];
    }

    return $catalog;
}

function normalize_invoice_items(array $descriptions, array $quantities, array $prices, array $sourceTypes = [], array $sourceIds = []): array
{
    $items = [];
    $subtotal = 0.0;

    foreach ($descriptions as $index => $description) {
        $description = trim((string) $description);
        $quantity = (float) ($quantities[$index] ?? 0);
        $price = (float) ($prices[$index] ?? 0);
        if ($description === '') {
            continue;
        }

        $lineTotal = $quantity * $price;
        $subtotal += $lineTotal;
        $sourceType = (string) ($sourceTypes[$index] ?? 'manual');
        if (!in_array($sourceType, ['manual', 'product', 'service'], true)) {
            $sourceType = 'manual';
        }
        $sourceId = (int) ($sourceIds[$index] ?? 0);
        $items[] = [
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $price,
            'line_total' => $lineTotal,
            'source_type' => $sourceType,
            'source_id' => $sourceType === 'manual' || $sourceId < 1 ? null : $sourceId,
        ];
    }

    return ['items' => $items, 'subtotal' => $subtotal];
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
