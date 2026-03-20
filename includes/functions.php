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

function setting_array(string $key, array $default = []): array
{
    $raw = trim(setting($key, ''));
    if ($raw === '') {
        return $default;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $default;
    }

    $values = [];
    foreach ($decoded as $value) {
        if (is_string($value) && $value !== '') {
            $values[] = $value;
        }
    }

    return array_values(array_unique($values));
}

function dashboard_widget_catalog(): array
{
    return [
        'stats.clients' => [
            'label' => 'Clients total',
            'description' => 'Show the total number of clients.',
            'group' => 'Stat cards',
            'permission' => 'clients.view',
        ],
        'stats.services' => [
            'label' => 'Services total',
            'description' => 'Show the total number of services.',
            'group' => 'Stat cards',
            'permission' => 'services.view',
        ],
        'stats.products' => [
            'label' => 'Products total',
            'description' => 'Show the total number of products.',
            'group' => 'Stat cards',
            'permission' => 'products.view',
        ],
        'stats.unpaid_invoices' => [
            'label' => 'Open invoices',
            'description' => 'Show the number of draft, sent, unpaid, and overdue invoices.',
            'group' => 'Stat cards',
            'permission' => 'invoices.view',
        ],
        'stats.open_tickets' => [
            'label' => 'Open tickets',
            'description' => 'Show the number of open support tickets.',
            'group' => 'Stat cards',
            'permission' => 'tickets.view',
        ],
        'panel.recent_invoices' => [
            'label' => 'Recent invoices',
            'description' => 'List the latest quotes and invoices.',
            'group' => 'Insight panels',
            'permission' => 'invoices.view',
        ],
        'panel.recent_tickets' => [
            'label' => 'Recent tickets',
            'description' => 'List the latest support tickets.',
            'group' => 'Insight panels',
            'permission' => 'tickets.view',
        ],
        'panel.top_clients' => [
            'label' => 'Top clients',
            'description' => 'Rank clients by billed revenue.',
            'group' => 'Insight panels',
            'permission' => 'invoices.view',
        ],
        'panel.overdue_invoices' => [
            'label' => 'Overdue invoices',
            'description' => 'Highlight invoices that need follow-up.',
            'group' => 'Insight panels',
            'permission' => 'invoices.view',
        ],
        'panel.active_services' => [
            'label' => 'Active services',
            'description' => 'Show currently active services and their clients.',
            'group' => 'Insight panels',
            'permission' => 'services.view',
        ],
    ];
}

function default_dashboard_widgets(): array
{
    return array_keys(dashboard_widget_catalog());
}

function enabled_dashboard_widgets(): array
{
    $widgets = setting_array('dashboard_widgets', default_dashboard_widgets());
    $catalog = dashboard_widget_catalog();
    $enabled = [];

    foreach ($widgets as $widget) {
        if (!isset($catalog[$widget])) {
            continue;
        }

        $permission = $catalog[$widget]['permission'] ?? null;
        if ($permission !== null && !has_permission($permission)) {
            continue;
        }

        if ($widget === 'stats.products' && !products_storage_available()) {
            continue;
        }

        $enabled[] = $widget;
    }

    return array_values(array_unique($enabled));
}

function dashboard_widget_enabled(string $widgetKey): bool
{
    return in_array($widgetKey, enabled_dashboard_widgets(), true);
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
        'scheduled' => 'info',
        'in_progress' => 'primary',
        'completed' => 'success',
        'on_hold' => 'warning',
        'low' => 'secondary',
        'medium' => 'info',
        'high' => 'warning',
        'urgent' => 'danger',
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


function normalize_datetime_input(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $value = str_replace('T', ' ', $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1) {
        $value .= ':00';
    }

    return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) === 1 ? $value : null;
}

function format_datetime_local(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d\TH:i');
    } catch (Throwable) {
        return '';
    }
}

function format_datetime_display(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format('j M Y g:i A');
    } catch (Throwable) {
        return $value;
    }
}

function jobs_storage_available(): bool
{
    static $checked = false;
    static $available = false;

    if ($checked) {
        return $available;
    }

    $checked = true;

    try {
        db()->query('SELECT 1 FROM jobcards LIMIT 1');
        $available = true;
        return true;
    } catch (PDOException) {
        try {
            db()->exec(
                "CREATE TABLE IF NOT EXISTS jobcards (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    company_id INT UNSIGNED NOT NULL,
                    client_id INT UNSIGNED NOT NULL,
                    created_by_user_id INT UNSIGNED NOT NULL,
                    assigned_user_id INT UNSIGNED DEFAULT NULL,
                    job_number VARCHAR(50) NOT NULL,
                    title VARCHAR(190) NOT NULL,
                    job_type VARCHAR(120) DEFAULT NULL,
                    scheduled_for DATETIME NOT NULL,
                    status ENUM('scheduled','in_progress','completed','on_hold','cancelled') NOT NULL DEFAULT 'scheduled',
                    priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
                    site_contact_name VARCHAR(150) DEFAULT NULL,
                    site_contact_phone VARCHAR(50) DEFAULT NULL,
                    service_address TEXT DEFAULT NULL,
                    scope_of_work TEXT DEFAULT NULL,
                    access_instructions TEXT DEFAULT NULL,
                    materials_required TEXT DEFAULT NULL,
                    internal_notes TEXT DEFAULT NULL,
                    client_signature_name VARCHAR(150) DEFAULT NULL,
                    client_signature_notes TEXT DEFAULT NULL,
                    client_signed_at DATETIME DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT fk_jobcards_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
                    CONSTRAINT fk_jobcards_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
                    CONSTRAINT fk_jobcards_created_by_user FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE CASCADE,
                    CONSTRAINT fk_jobcards_assigned_user FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON DELETE SET NULL,
                    UNIQUE KEY uq_jobcards_company_number (company_id, job_number),
                    KEY idx_jobcards_company_client_status (company_id, client_id, status),
                    KEY idx_jobcards_assigned_user (assigned_user_id),
                    KEY idx_jobcards_scheduled_for (company_id, scheduled_for)
                ) ENGINE=InnoDB"
            );
            $available = true;
        } catch (PDOException) {
            $available = false;
        }
    }

    return $available;
}

function jobcard_notes_storage_available(): bool
{
    static $checked = false;
    static $available = false;

    if ($checked) {
        return $available;
    }

    $checked = true;

    if (!jobs_storage_available()) {
        return false;
    }

    try {
        db()->query('SELECT 1 FROM jobcard_notes LIMIT 1');
        $available = true;
        return true;
    } catch (PDOException) {
        try {
            db()->exec(
                "CREATE TABLE IF NOT EXISTS jobcard_notes (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    jobcard_id INT UNSIGNED NOT NULL,
                    user_id INT UNSIGNED NOT NULL,
                    note TEXT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT fk_jobcard_notes_jobcard FOREIGN KEY (jobcard_id) REFERENCES jobcards (id) ON DELETE CASCADE,
                    CONSTRAINT fk_jobcard_notes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                    KEY idx_jobcard_notes_jobcard (jobcard_id),
                    KEY idx_jobcard_notes_user (user_id)
                ) ENGINE=InnoDB"
            );
            $available = true;
        } catch (PDOException) {
            $available = false;
        }
    }

    return $available;
}

function technician_select_options(?int $selected = null, ?int $companyId = null): string
{
    $params = [];
    $sql = "SELECT id, full_name, role FROM users WHERE role IN ('company_admin','company_staff') AND is_active = 1";

    if ($companyId) {
        $sql .= ' AND company_id = :company_id';
        $params['company_id'] = $companyId;
    } elseif (!is_super_admin()) {
        $sql .= ' AND company_id = :company_id';
        $params['company_id'] = current_company_id();
    }

    $sql .= ' ORDER BY full_name';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $html = '';
    foreach ($stmt->fetchAll() as $user) {
        $label = $user['full_name'] . ' (' . ucwords(str_replace('_', ' ', (string) $user['role'])) . ')';
        $isSelected = $selected === (int) $user['id'] ? ' selected' : '';
        $html .= '<option value="' . (int) $user['id'] . '"' . $isSelected . '>' . h($label) . '</option>';
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

    try {
        $descriptionColumn = db()->query("SHOW COLUMNS FROM invoice_items LIKE 'description'")->fetch();
        if (is_array($descriptionColumn)) {
            $descriptionType = strtolower((string) ($descriptionColumn['Type'] ?? ''));
            if ($descriptionType !== 'text') {
                db()->exec('ALTER TABLE invoice_items MODIFY COLUMN description TEXT NOT NULL');
            }
        }
    } catch (PDOException) {
    }

    return $available;
}

function ensure_invoice_item_description_capacity(): ?int
{
    static $checked = false;
    static $maxLength = null;

    if ($checked) {
        return $maxLength;
    }

    $checked = true;

    try {
        $descriptionColumn = db()->query("SHOW COLUMNS FROM invoice_items LIKE 'description'")->fetch();
        if (!is_array($descriptionColumn)) {
            return null;
        }

        $descriptionType = strtolower((string) ($descriptionColumn['Type'] ?? ''));
        if ($descriptionType !== 'text') {
            try {
                db()->exec('ALTER TABLE invoice_items MODIFY COLUMN description TEXT NOT NULL');
                $descriptionColumn = db()->query("SHOW COLUMNS FROM invoice_items LIKE 'description'")->fetch();
                $descriptionType = strtolower((string) (($descriptionColumn['Type'] ?? '')));
            } catch (PDOException) {
            }
        }

        if (preg_match('/^(?:var)?char\((\d+)\)$/', $descriptionType, $matches) === 1) {
            $maxLength = (int) $matches[1];
        }
    } catch (PDOException) {
        $maxLength = null;
    }

    return $maxLength;
}

function trim_text_to_length(string $value, int $maxLength): string
{
    if ($maxLength < 1) {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
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

function normalize_invoice_items(array $descriptions, array $quantities, array $prices, array $sourceTypes = [], array $sourceIds = [], ?int $descriptionMaxLength = null): array
{
    $items = [];
    $subtotal = 0.0;

    foreach ($descriptions as $index => $description) {
        $description = trim((string) $description);
        if ($descriptionMaxLength !== null && $descriptionMaxLength > 0) {
            $description = trim(trim_text_to_length($description, $descriptionMaxLength));
        }
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

function ensure_invoices_billing_columns(): bool
{
    static $checked = false;
    static $available = false;

    if ($checked) {
        return $available;
    }

    $checked = true;

    try {
        db()->query('SELECT billing_type, recurring_profile_id FROM invoices LIMIT 1');
        $available = true;
        return true;
    } catch (PDOException) {
        try {
            db()->exec("ALTER TABLE invoices ADD COLUMN billing_type VARCHAR(20) NOT NULL DEFAULT 'once_off' AFTER due_date");
        } catch (PDOException) {
        }

        try {
            db()->exec('ALTER TABLE invoices ADD COLUMN recurring_profile_id INT UNSIGNED DEFAULT NULL AFTER billing_type');
        } catch (PDOException) {
        }

        try {
            db()->query('SELECT billing_type, recurring_profile_id FROM invoices LIMIT 1');
            $available = true;
        } catch (PDOException) {
            $available = false;
        }
    }

    return $available;
}

function invoice_payments_storage_available(): bool
{
    static $checked = false;
    static $available = false;

    if ($checked) {
        return $available;
    }

    $checked = true;

    try {
        db()->query('SELECT 1 FROM invoice_payments LIMIT 1');
        $available = true;
        return true;
    } catch (PDOException) {
        try {
            db()->exec(
                "CREATE TABLE IF NOT EXISTS invoice_payments (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    company_id INT UNSIGNED NOT NULL,
                    client_id INT UNSIGNED NOT NULL,
                    invoice_id INT UNSIGNED NOT NULL,
                    payment_date DATE NOT NULL,
                    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    reference VARCHAR(120) DEFAULT NULL,
                    notes TEXT DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT fk_invoice_payments_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
                    CONSTRAINT fk_invoice_payments_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
                    CONSTRAINT fk_invoice_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
                    KEY idx_invoice_payments_invoice (invoice_id),
                    KEY idx_invoice_payments_client_date (company_id, client_id, payment_date)
                ) ENGINE=InnoDB"
            );
            $available = true;
        } catch (PDOException) {
            $available = false;
        }
    }

    return $available;
}

function recurring_billing_profiles_storage_available(): bool
{
    static $checked = false;
    static $available = false;

    if ($checked) {
        return $available;
    }

    $checked = true;

    try {
        db()->query('SELECT 1 FROM recurring_billing_profiles LIMIT 1');
        $available = true;
        return true;
    } catch (PDOException) {
        try {
            db()->exec(
                "CREATE TABLE IF NOT EXISTS recurring_billing_profiles (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    company_id INT UNSIGNED NOT NULL,
                    client_id INT UNSIGNED NOT NULL,
                    service_id INT UNSIGNED DEFAULT NULL,
                    title VARCHAR(150) NOT NULL,
                    description TEXT DEFAULT NULL,
                    billing_cycle ENUM('weekly','monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
                    quantity DECIMAL(12,2) NOT NULL DEFAULT 1.00,
                    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    due_days INT NOT NULL DEFAULT 7,
                    start_date DATE NOT NULL,
                    next_invoice_date DATE NOT NULL,
                    end_date DATE DEFAULT NULL,
                    last_invoiced_at DATETIME DEFAULT NULL,
                    status ENUM('active','paused','completed') NOT NULL DEFAULT 'active',
                    notes TEXT DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT fk_recurring_profiles_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
                    CONSTRAINT fk_recurring_profiles_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
                    CONSTRAINT fk_recurring_profiles_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE SET NULL,
                    KEY idx_recurring_profiles_due (company_id, status, next_invoice_date),
                    KEY idx_recurring_profiles_client (company_id, client_id, status)
                ) ENGINE=InnoDB"
            );
            $available = true;
        } catch (PDOException) {
            $available = false;
        }
    }

    return $available;
}

function billing_system_ready(): bool
{
    ensure_invoice_item_source_columns();
    ensure_invoice_item_description_capacity();

    return ensure_invoices_billing_columns()
        && invoice_payments_storage_available()
        && recurring_billing_profiles_storage_available();
}

function format_billing_type(string $billingType): string
{
    return match ($billingType) {
        'recurring' => 'Recurring',
        default => 'Once-off',
    };
}

function billing_cycle_label(string $cycle): string
{
    return match ($cycle) {
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'quarterly' => 'Quarterly',
        'yearly' => 'Yearly',
        'one_time' => 'One-time',
        default => ucfirst(str_replace('_', ' ', $cycle)),
    };
}

function generate_company_invoice_number(int $companyId): string
{
    $prefix = setting('invoice_prefix', 'INV-');

    if ($companyId > 0) {
        $settings = company_settings($companyId);
        $prefix = trim((string) ($settings['invoice_prefix'] ?? $prefix));
        if ($prefix === '') {
            $prefix = 'INV-';
        }
    }

    return sprintf('%s%s%04d', $prefix, date('YmdHis'), random_int(1, 9999));
}

function calculate_next_billing_date(string $cycle, string $fromDate): string
{
    $date = new DateTimeImmutable($fromDate);

    return match ($cycle) {
        'weekly' => $date->modify('+1 week')->format('Y-m-d'),
        'quarterly' => $date->modify('+3 months')->format('Y-m-d'),
        'yearly' => $date->modify('+1 year')->format('Y-m-d'),
        default => $date->modify('+1 month')->format('Y-m-d'),
    };
}

function invoice_payment_totals(array $invoiceIds): array
{
    if ($invoiceIds === [] || !invoice_payments_storage_available()) {
        return [];
    }

    $invoiceIds = array_values(array_unique(array_map('intval', $invoiceIds)));
    $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
    $stmt = db()->prepare(
        'SELECT invoice_id, COALESCE(SUM(amount), 0) AS paid_total
         FROM invoice_payments
         WHERE invoice_id IN (' . $placeholders . ')
         GROUP BY invoice_id'
    );
    $stmt->execute($invoiceIds);

    $totals = [];
    foreach ($stmt->fetchAll() as $row) {
        $totals[(int) $row['invoice_id']] = (float) $row['paid_total'];
    }

    return $totals;
}

function invoice_balance_amount(float $totalAmount, float $paidAmount): float
{
    return max(0, round($totalAmount - $paidAmount, 2));
}

function sync_invoice_payment_status(int $invoiceId): void
{
    ensure_invoices_billing_columns();

    $stmt = db()->prepare('SELECT id, due_date, status, total_amount FROM invoices WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $invoiceId]);
    $invoice = $stmt->fetch();

    if (!$invoice || in_array($invoice['status'], ['draft', 'cancelled'], true)) {
        return;
    }

    $paidAmount = invoice_payment_totals([$invoiceId])[$invoiceId] ?? 0.0;
    $balance = invoice_balance_amount((float) $invoice['total_amount'], $paidAmount);
    $today = date('Y-m-d');

    $status = 'paid';
    if ($balance > 0) {
        $status = ((string) $invoice['due_date'] < $today) ? 'overdue' : 'unpaid';
    }

    db()->prepare('UPDATE invoices SET status = :status, updated_at = NOW() WHERE id = :id')->execute([
        'id' => $invoiceId,
        'status' => $status,
    ]);
}

function create_invoice_record(array $invoiceData, array $items): int
{
    ensure_invoices_billing_columns();
    ensure_invoice_item_source_columns();

    $stmt = db()->prepare(
        'INSERT INTO invoices (
            company_id, client_id, invoice_number, invoice_date, due_date, billing_type, recurring_profile_id,
            status, notes, subtotal, tax_amount, discount_amount, total_amount, created_at, updated_at
        ) VALUES (
            :company_id, :client_id, :invoice_number, :invoice_date, :due_date, :billing_type, :recurring_profile_id,
            :status, :notes, :subtotal, :tax_amount, :discount_amount, :total_amount, NOW(), NOW()
        )'
    );
    $stmt->execute([
        'company_id' => (int) $invoiceData['company_id'],
        'client_id' => (int) $invoiceData['client_id'],
        'invoice_number' => (string) $invoiceData['invoice_number'],
        'invoice_date' => (string) $invoiceData['invoice_date'],
        'due_date' => (string) $invoiceData['due_date'],
        'billing_type' => (string) ($invoiceData['billing_type'] ?? 'once_off'),
        'recurring_profile_id' => $invoiceData['recurring_profile_id'] ?? null,
        'status' => (string) ($invoiceData['status'] ?? 'unpaid'),
        'notes' => (string) ($invoiceData['notes'] ?? ''),
        'subtotal' => (float) $invoiceData['subtotal'],
        'tax_amount' => (float) $invoiceData['tax_amount'],
        'discount_amount' => (float) $invoiceData['discount_amount'],
        'total_amount' => (float) $invoiceData['total_amount'],
    ]);
    $invoiceId = (int) db()->lastInsertId();

    $itemStmt = db()->prepare(
        'INSERT INTO invoice_items (
            invoice_id, description, quantity, unit_price, line_total, source_type, source_id, created_at, updated_at
         ) VALUES (
            :invoice_id, :description, :quantity, :unit_price, :line_total, :source_type, :source_id, NOW(), NOW()
         )'
    );

    foreach ($items as $item) {
        $itemStmt->execute([
            'invoice_id' => $invoiceId,
            'description' => (string) $item['description'],
            'quantity' => (float) $item['quantity'],
            'unit_price' => (float) $item['unit_price'],
            'line_total' => (float) $item['line_total'],
            'source_type' => (string) ($item['source_type'] ?? 'manual'),
            'source_id' => $item['source_id'] ?? null,
        ]);
    }

    return $invoiceId;
}

function generate_due_recurring_invoices(?int $companyId = null): int
{
    if (!billing_system_ready() || !has_permission('invoices.create')) {
        return 0;
    }

    $params = ['today' => date('Y-m-d')];
    $sql = "SELECT *
            FROM recurring_billing_profiles
            WHERE status = 'active'
              AND next_invoice_date <= :today";

    if ($companyId !== null && $companyId > 0) {
        $sql .= ' AND company_id = :company_id';
        $params['company_id'] = $companyId;
    } elseif (!is_super_admin()) {
        $sql .= ' AND company_id = :company_id';
        $params['company_id'] = current_company_id();
    }

    $sql .= ' ORDER BY next_invoice_date ASC, id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $profiles = $stmt->fetchAll();

    $generated = 0;

    foreach ($profiles as $profile) {
        $nextInvoiceDate = (string) $profile['next_invoice_date'];
        $endDate = trim((string) $profile['end_date']);

        while ($nextInvoiceDate !== '' && $nextInvoiceDate <= date('Y-m-d')) {
            if ($endDate !== '' && $nextInvoiceDate > $endDate) {
                db()->prepare("UPDATE recurring_billing_profiles SET status = 'completed', updated_at = NOW() WHERE id = :id")
                    ->execute(['id' => (int) $profile['id']]);
                break;
            }

            $subtotal = round((float) $profile['quantity'] * (float) $profile['unit_price'], 2);
            $taxAmount = (float) $profile['tax_amount'];
            $discountAmount = (float) $profile['discount_amount'];
            $totalAmount = max(0, round($subtotal + $taxAmount - $discountAmount, 2));

            db()->beginTransaction();
            try {
                create_invoice_record(
                    [
                        'company_id' => (int) $profile['company_id'],
                        'client_id' => (int) $profile['client_id'],
                        'invoice_number' => generate_company_invoice_number((int) $profile['company_id']),
                        'invoice_date' => $nextInvoiceDate,
                        'due_date' => (new DateTimeImmutable($nextInvoiceDate))->modify('+' . max(0, (int) $profile['due_days']) . ' days')->format('Y-m-d'),
                        'billing_type' => 'recurring',
                        'recurring_profile_id' => (int) $profile['id'],
                        'status' => 'unpaid',
                        'notes' => (string) ($profile['notes'] ?? ''),
                        'subtotal' => $subtotal,
                        'tax_amount' => $taxAmount,
                        'discount_amount' => $discountAmount,
                        'total_amount' => $totalAmount,
                    ],
                    [[
                        'description' => trim((string) $profile['description']) !== '' ? (string) $profile['description'] : (string) $profile['title'],
                        'quantity' => (float) $profile['quantity'],
                        'unit_price' => (float) $profile['unit_price'],
                        'line_total' => $subtotal,
                        'source_type' => ((int) $profile['service_id'] > 0) ? 'service' : 'manual',
                        'source_id' => ((int) $profile['service_id'] > 0) ? (int) $profile['service_id'] : null,
                    ]]
                );

                $nextInvoiceDate = calculate_next_billing_date((string) $profile['billing_cycle'], $nextInvoiceDate);
                $nextStatus = ($endDate !== '' && $nextInvoiceDate > $endDate) ? 'completed' : 'active';

                db()->prepare(
                    'UPDATE recurring_billing_profiles
                     SET next_invoice_date = :next_invoice_date, last_invoiced_at = NOW(), status = :status, updated_at = NOW()
                     WHERE id = :id'
                )->execute([
                    'id' => (int) $profile['id'],
                    'next_invoice_date' => $nextInvoiceDate,
                    'status' => $nextStatus,
                ]);

                db()->commit();
                $generated++;

                if ($nextStatus === 'completed') {
                    break;
                }
            } catch (Throwable $exception) {
                db()->rollBack();
                throw $exception;
            }
        }
    }

    return $generated;
}

function statement_entries(?int $companyId = null, ?int $clientId = null): array
{
    billing_system_ready();

    $entries = [];
    $params = [];
    $invoiceSql = 'SELECT invoices.id, invoices.company_id, invoices.client_id, invoices.invoice_number AS reference, invoices.invoice_date AS entry_date,
                          invoices.total_amount AS amount, invoices.notes, invoices.billing_type, clients.company_name
                   FROM invoices
                   INNER JOIN clients ON clients.id = invoices.client_id
                   WHERE invoices.status <> :cancelled';
    $params['cancelled'] = 'cancelled';

    if ($companyId !== null && $companyId > 0) {
        $invoiceSql .= ' AND invoices.company_id = :company_id';
        $params['company_id'] = $companyId;
    } elseif (!is_super_admin()) {
        $invoiceSql .= ' AND invoices.company_id = :company_id';
        $params['company_id'] = current_company_id();
    }

    if ($clientId !== null && $clientId > 0) {
        $invoiceSql .= ' AND invoices.client_id = :client_id';
        $params['client_id'] = $clientId;
    }

    $invoiceStmt = db()->prepare($invoiceSql);
    $invoiceStmt->execute($params);
    foreach ($invoiceStmt->fetchAll() as $invoice) {
        $entries[] = [
            'entry_date' => (string) $invoice['entry_date'],
            'type' => 'invoice',
            'label' => 'Invoice ' . $invoice['reference'],
            'reference' => (string) $invoice['reference'],
            'client_name' => (string) $invoice['company_name'],
            'billing_type' => (string) $invoice['billing_type'],
            'debit' => (float) $invoice['amount'],
            'credit' => 0.0,
            'notes' => (string) ($invoice['notes'] ?? ''),
        ];
    }

    if (invoice_payments_storage_available()) {
        $paymentSql = 'SELECT invoice_payments.*, clients.company_name, invoices.invoice_number
                       FROM invoice_payments
                       INNER JOIN clients ON clients.id = invoice_payments.client_id
                       INNER JOIN invoices ON invoices.id = invoice_payments.invoice_id
                       WHERE 1=1';
        $paymentParams = [];

        if ($companyId !== null && $companyId > 0) {
            $paymentSql .= ' AND invoice_payments.company_id = :company_id';
            $paymentParams['company_id'] = $companyId;
        } elseif (!is_super_admin()) {
            $paymentSql .= ' AND invoice_payments.company_id = :company_id';
            $paymentParams['company_id'] = current_company_id();
        }

        if ($clientId !== null && $clientId > 0) {
            $paymentSql .= ' AND invoice_payments.client_id = :client_id';
            $paymentParams['client_id'] = $clientId;
        }

        $paymentStmt = db()->prepare($paymentSql);
        $paymentStmt->execute($paymentParams);
        foreach ($paymentStmt->fetchAll() as $payment) {
            $entries[] = [
                'entry_date' => (string) $payment['payment_date'],
                'type' => 'payment',
                'label' => 'Payment for ' . $payment['invoice_number'],
                'reference' => trim((string) $payment['reference']) !== '' ? (string) $payment['reference'] : (string) $payment['invoice_number'],
                'client_name' => (string) $payment['company_name'],
                'billing_type' => '',
                'debit' => 0.0,
                'credit' => (float) $payment['amount'],
                'notes' => (string) ($payment['notes'] ?? ''),
            ];
        }
    }

    usort(
        $entries,
        static function (array $left, array $right): int {
            $dateCompare = strcmp((string) $left['entry_date'], (string) $right['entry_date']);
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return strcmp((string) $left['type'], (string) $right['type']);
        }
    );

    $runningBalance = 0.0;
    foreach ($entries as &$entry) {
        $runningBalance += (float) $entry['debit'] - (float) $entry['credit'];
        $entry['running_balance'] = round($runningBalance, 2);
    }
    unset($entry);

    return $entries;
}
