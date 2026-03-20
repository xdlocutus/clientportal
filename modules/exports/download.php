<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('exports.view');

$dataset = request_string('dataset');
$format = request_string('format', 'csv');

$definitions = [
    'clients' => [
        'title' => 'Client List',
        'filename' => 'clients',
        'sql' => 'SELECT company_name, contact_name, email, phone, status, created_at FROM clients WHERE ' . company_scope_sql(),
        'headers' => ['Company', 'Contact', 'Email', 'Phone', 'Status', 'Created'],
        'columns' => ['company_name', 'contact_name', 'email', 'phone', 'status', 'created_at'],
    ],
    'invoices' => [
        'title' => 'Invoices',
        'filename' => 'invoices',
        'sql' => 'SELECT invoices.invoice_number, clients.company_name, invoices.invoice_date, invoices.due_date, invoices.status, invoices.total_amount
            FROM invoices
            INNER JOIN clients ON clients.id = invoices.client_id
            WHERE ' . company_scope_sql('company_id', 'invoices'),
        'headers' => ['Invoice', 'Client', 'Invoice Date', 'Due Date', 'Status', 'Total'],
        'columns' => ['invoice_number', 'company_name', 'invoice_date', 'due_date', 'status', 'total_amount'],
    ],
    'tickets' => [
        'title' => 'Tickets',
        'filename' => 'tickets',
        'sql' => 'SELECT tickets.subject, clients.company_name, tickets.priority, tickets.status, tickets.updated_at
            FROM tickets
            INNER JOIN clients ON clients.id = tickets.client_id
            WHERE ' . company_scope_sql('company_id', 'tickets'),
        'headers' => ['Subject', 'Client', 'Priority', 'Status', 'Updated'],
        'columns' => ['subject', 'company_name', 'priority', 'status', 'updated_at'],
    ],
];

if (!isset($definitions[$dataset])) {
    http_response_code(404);
    exit('Export dataset not found.');
}

$definition = $definitions[$dataset];
$params = company_scope_params();
if (is_client_role()) {
    if ($dataset === 'clients') {
        $definition['sql'] .= ' AND id = :client_id';
    } else {
        $table = $dataset === 'invoices' ? 'invoices' : 'tickets';
        $definition['sql'] .= ' AND ' . $table . '.client_id = :client_id';
    }
    $params['client_id'] = current_client_id();
}

$orderBy = match ($dataset) {
    'clients' => ' ORDER BY company_name ASC',
    'invoices' => ' ORDER BY invoices.invoice_date DESC, invoices.invoice_number DESC',
    'tickets' => ' ORDER BY tickets.updated_at DESC',
    default => '',
};

$stmt = db()->prepare($definition['sql'] . $orderBy);
$stmt->execute($params);
$rows = $stmt->fetchAll();

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $definition['filename'] . '-' . date('Ymd-His') . '.csv"');

    $output = fopen('php://output', 'wb');
    fputcsv($output, $definition['headers']);
    foreach ($rows as $row) {
        $line = [];
        foreach ($definition['columns'] as $column) {
            $line[] = $row[$column] ?? '';
        }
        fputcsv($output, $line);
    }
    fclose($output);
    exit;
}

if ($format !== 'print') {
    http_response_code(400);
    exit('Unsupported export format.');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= h($definition['title']) ?> Export</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; color: #111827; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; gap: 1rem; }
        .toolbar button, .toolbar a { padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid #d1d5db; background: #fff; color: #111827; text-decoration: none; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 0.75rem; text-align: left; }
        th { background: #f3f4f6; }
        @media print { .toolbar { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <h1 style="margin:0 0 0.25rem;"><?= h($definition['title']) ?></h1>
            <div style="color:#6b7280;">Generated <?= h(date('Y-m-d H:i:s')) ?> UTC</div>
        </div>
        <div style="display:flex; gap:0.75rem;">
            <button type="button" onclick="window.print()">Print / Save PDF</button>
            <a href="/modules/exports/index.php">Back to exports</a>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <?php foreach ($definition['headers'] as $header): ?>
                    <th><?= h($header) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($definition['columns'] as $column): ?>
                        <td><?= h((string) ($row[$column] ?? '')) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="<?= count($definition['headers']) ?>" style="text-align:center;color:#6b7280;">No records found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
