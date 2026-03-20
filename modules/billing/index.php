<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('invoices.view');
billing_system_ready();

$selectedCompanyId = is_super_admin() ? request_int('company_id') : (int) current_company_id();
if ($selectedCompanyId > 0) {
    require_company_access($selectedCompanyId);
}
$selectedClientId = request_int('client_id');

if ($selectedClientId > 0) {
    $clientCheck = db()->prepare('SELECT id, company_id, company_name, contact_name, email, billing_email FROM clients WHERE id = :id AND ' . company_scope_sql());
    $clientCheck->execute(['id' => $selectedClientId] + company_scope_params());
    $selectedClient = $clientCheck->fetch();
    if (!$selectedClient) {
        set_flash('danger', 'Client not found for your company scope.');
        redirect('/modules/billing/index.php');
    }
    $selectedCompanyId = (int) $selectedClient['company_id'];
} else {
    $selectedClient = null;
}

if (has_permission('invoices.create')) {
    $generated = generate_due_recurring_invoices($selectedCompanyId > 0 ? $selectedCompanyId : null);
    if ($generated > 0) {
        set_flash('success', $generated . ' recurring invoice' . ($generated === 1 ? ' was' : 's were') . ' generated automatically.');
        $query = [];
        if (is_super_admin() && $selectedCompanyId > 0) {
            $query['company_id'] = $selectedCompanyId;
        }
        if ($selectedClientId > 0) {
            $query['client_id'] = $selectedClientId;
        }
        redirect('/modules/billing/index.php' . ($query ? '?' . http_build_query($query) : ''));
    }
}

if (is_post()) {
    verify_csrf();
    $action = request_string('action');

    if ($action === 'create_profile' && has_permission('invoices.create')) {
        $companyId = is_super_admin() ? request_int('company_id') : (int) current_company_id();
        require_company_access($companyId);
        $clientId = request_int('client_id');
        $title = request_string('title');
        $description = request_string('description');
        $billingCycle = request_string('billing_cycle', 'monthly');
        $status = request_string('status', 'active');
        $startDate = request_string('start_date', date('Y-m-d'));
        $nextInvoiceDate = request_string('next_invoice_date', $startDate);
        $endDate = request_string('end_date');
        $serviceId = request_int('service_id');
        $quantity = max(0.01, (float) request_string('quantity', '1'));
        $unitPrice = max(0, (float) request_string('unit_price', '0'));
        $taxAmount = max(0, (float) request_string('tax_amount', '0'));
        $discountAmount = max(0, (float) request_string('discount_amount', '0'));
        $dueDays = max(0, request_int('due_days', 7));
        $notes = request_string('notes');

        if ($title === '' || $clientId < 1 || !in_array($billingCycle, ['weekly', 'monthly', 'quarterly', 'yearly'], true)) {
            set_flash('danger', 'Please complete the recurring billing form with a client, title, and valid cycle.');
        } else {
            $clientStmt = db()->prepare('SELECT id FROM clients WHERE id = :id AND company_id = :company_id LIMIT 1');
            $clientStmt->execute(['id' => $clientId, 'company_id' => $companyId]);
            if (!$clientStmt->fetch()) {
                set_flash('danger', 'The selected client does not belong to that company.');
            } else {
                $serviceId = $serviceId > 0 ? $serviceId : null;
                $stmt = db()->prepare(
                    'INSERT INTO recurring_billing_profiles (
                        company_id, client_id, service_id, title, description, billing_cycle, quantity, unit_price,
                        tax_amount, discount_amount, due_days, start_date, next_invoice_date, end_date, status, notes, created_at, updated_at
                     ) VALUES (
                        :company_id, :client_id, :service_id, :title, :description, :billing_cycle, :quantity, :unit_price,
                        :tax_amount, :discount_amount, :due_days, :start_date, :next_invoice_date, :end_date, :status, :notes, NOW(), NOW()
                     )'
                );
                $stmt->execute([
                    'company_id' => $companyId,
                    'client_id' => $clientId,
                    'service_id' => $serviceId,
                    'title' => $title,
                    'description' => $description,
                    'billing_cycle' => $billingCycle,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_amount' => $taxAmount,
                    'discount_amount' => $discountAmount,
                    'due_days' => $dueDays,
                    'start_date' => $startDate,
                    'next_invoice_date' => $nextInvoiceDate,
                    'end_date' => $endDate !== '' ? $endDate : null,
                    'status' => in_array($status, ['active', 'paused', 'completed'], true) ? $status : 'active',
                    'notes' => $notes,
                ]);
                set_flash('success', 'Recurring billing profile created successfully.');
            }
        }
    }

    if ($action === 'toggle_profile_status' && has_permission('invoices.edit')) {
        $profileId = request_int('profile_id');
        $nextStatus = request_string('next_status', 'paused');
        if (in_array($nextStatus, ['active', 'paused', 'completed'], true)) {
            $stmt = db()->prepare('UPDATE recurring_billing_profiles SET status = :status, updated_at = NOW() WHERE id = :id AND ' . company_scope_sql());
            $stmt->execute(['id' => $profileId, 'status' => $nextStatus] + company_scope_params());
            set_flash('success', 'Recurring profile updated.');
        }
    }

    if ($action === 'generate_profile_now' && has_permission('invoices.create')) {
        $profileId = request_int('profile_id');
        $stmt = db()->prepare('UPDATE recurring_billing_profiles SET next_invoice_date = :today, status = :status, updated_at = NOW() WHERE id = :id AND ' . company_scope_sql());
        $stmt->execute(['id' => $profileId, 'today' => date('Y-m-d'), 'status' => 'active'] + company_scope_params());
        $generatedCount = generate_due_recurring_invoices($selectedCompanyId > 0 ? $selectedCompanyId : null);
        set_flash('success', $generatedCount > 0 ? 'Recurring invoice generated.' : 'Profile scheduled for the next billing run.');
    }

    $query = [];
    if (is_super_admin() && $selectedCompanyId > 0) {
        $query['company_id'] = $selectedCompanyId;
    }
    if ($selectedClientId > 0) {
        $query['client_id'] = $selectedClientId;
    }
    redirect('/modules/billing/index.php' . ($query ? '?' . http_build_query($query) : ''));
}

$filterParams = [];
$filterInvoicesSql = 'SELECT invoices.*, clients.company_name,
                             COALESCE((SELECT SUM(amount) FROM invoice_payments WHERE invoice_id = invoices.id), 0) AS paid_amount
                      FROM invoices
                      INNER JOIN clients ON clients.id = invoices.client_id
                      WHERE ' . company_scope_sql('company_id', 'invoices');
$filterParams += company_scope_params();

if ($selectedCompanyId > 0 && is_super_admin()) {
    $filterInvoicesSql = 'SELECT invoices.*, clients.company_name,
                                 COALESCE((SELECT SUM(amount) FROM invoice_payments WHERE invoice_id = invoices.id), 0) AS paid_amount
                          FROM invoices
                          INNER JOIN clients ON clients.id = invoices.client_id
                          WHERE invoices.company_id = :company_id';
    $filterParams = ['company_id' => $selectedCompanyId];
}
if ($selectedClientId > 0) {
    $filterInvoicesSql .= ' AND invoices.client_id = :client_id';
    $filterParams['client_id'] = $selectedClientId;
}
$filterInvoicesSql .= ' ORDER BY invoices.invoice_date DESC, invoices.id DESC LIMIT 25';
$invoiceStmt = db()->prepare($filterInvoicesSql);
$invoiceStmt->execute($filterParams);
$invoices = $invoiceStmt->fetchAll();

$profileSql = 'SELECT recurring_billing_profiles.*, clients.company_name, services.service_name
               FROM recurring_billing_profiles
               INNER JOIN clients ON clients.id = recurring_billing_profiles.client_id
               LEFT JOIN services ON services.id = recurring_billing_profiles.service_id
               WHERE ' . company_scope_sql('company_id', 'recurring_billing_profiles');
$profileParams = company_scope_params();
if ($selectedCompanyId > 0 && is_super_admin()) {
    $profileSql = 'SELECT recurring_billing_profiles.*, clients.company_name, services.service_name
                   FROM recurring_billing_profiles
                   INNER JOIN clients ON clients.id = recurring_billing_profiles.client_id
                   LEFT JOIN services ON services.id = recurring_billing_profiles.service_id
                   WHERE recurring_billing_profiles.company_id = :company_id';
    $profileParams = ['company_id' => $selectedCompanyId];
}
if ($selectedClientId > 0) {
    $profileSql .= ' AND recurring_billing_profiles.client_id = :client_id';
    $profileParams['client_id'] = $selectedClientId;
}
$profileSql .= ' ORDER BY FIELD(recurring_billing_profiles.status, "active", "paused", "completed"), recurring_billing_profiles.next_invoice_date ASC, recurring_billing_profiles.id DESC';
$profileStmt = db()->prepare($profileSql);
$profileStmt->execute($profileParams);
$profiles = $profileStmt->fetchAll();

$statement = statement_entries($selectedCompanyId > 0 ? $selectedCompanyId : null, $selectedClientId > 0 ? $selectedClientId : null);
$outstandingTotal = 0.0;
$paidThisMonth = 0.0;
foreach ($invoices as &$invoice) {
    $invoice['balance'] = invoice_balance_amount((float) $invoice['total_amount'], (float) $invoice['paid_amount']);
    if ($invoice['balance'] > 0) {
        $outstandingTotal += $invoice['balance'];
    }
}
unset($invoice);
foreach ($statement as $entry) {
    if ($entry['type'] === 'payment' && str_starts_with((string) $entry['entry_date'], date('Y-m'))) {
        $paidThisMonth += (float) $entry['credit'];
    }
}
$activeProfiles = array_values(array_filter($profiles, static fn(array $profile): bool => $profile['status'] === 'active'));
$overdueInvoices = array_values(array_filter($invoices, static fn(array $invoice): bool => $invoice['status'] === 'overdue' || ((string) $invoice['due_date'] < date('Y-m-d') && (float) $invoice['balance'] > 0)));

$pageTitle = 'Billing Centre';
require BASE_PATH . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Billing Centre</h1>
        <p class="text-muted mb-0">Track once-off invoices, recurring billing schedules, payments, and statement activity in one place.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (has_permission('invoices.create')): ?>
            <a class="btn btn-primary" href="/modules/invoices/add.php">Create once-off invoice</a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="/modules/invoices/index.php">Open invoice list</a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get">
            <?php if (is_super_admin()): ?>
                <div class="col-md-4">
                    <label class="form-label">Company</label>
                    <select class="form-select" name="company_id">
                        <option value="">All companies</option>
                        <?= company_select_options($selectedCompanyId > 0 ? $selectedCompanyId : null) ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-md-4">
                <label class="form-label">Client</label>
                <select class="form-select" name="client_id">
                    <option value="">All clients</option>
                    <?= client_select_options($selectedClientId > 0 ? $selectedClientId : null, $selectedCompanyId > 0 ? $selectedCompanyId : null) ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Apply filters</button>
                <a class="btn btn-link" href="/modules/billing/index.php">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm billing-stat-card h-100"><div class="card-body"><div class="text-muted small">Outstanding balance</div><div class="fs-4 fw-semibold"><?= h(money_format_portal($outstandingTotal)) ?></div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm billing-stat-card h-100"><div class="card-body"><div class="text-muted small">Payments this month</div><div class="fs-4 fw-semibold"><?= h(money_format_portal($paidThisMonth)) ?></div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm billing-stat-card h-100"><div class="card-body"><div class="text-muted small">Overdue invoices</div><div class="fs-4 fw-semibold"><?= count($overdueInvoices) ?></div></div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm billing-stat-card h-100"><div class="card-body"><div class="text-muted small">Active recurring schedules</div><div class="fs-4 fw-semibold"><?= count($activeProfiles) ?></div></div></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong><?= $selectedClient ? h($selectedClient['company_name']) . ' statement' : 'Statement activity' ?></strong>
                <span class="text-muted small"><?= count($statement) ?> entries</span>
            </div>
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <?php if (!$selectedClient): ?><th>Client</th><?php endif; ?>
                            <th>Entry</th>
                            <th>Type</th>
                            <th class="text-end">Debit</th>
                            <th class="text-end">Credit</th>
                            <th class="text-end">Running balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statement as $entry): ?>
                            <tr>
                                <td><?= h($entry['entry_date']) ?></td>
                                <?php if (!$selectedClient): ?><td><?= h($entry['client_name']) ?></td><?php endif; ?>
                                <td>
                                    <div class="fw-medium"><?= h($entry['label']) ?></div>
                                    <?php if ($entry['notes'] !== ''): ?><div class="small text-muted"><?= h($entry['notes']) ?></div><?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($entry['type'] === 'invoice'): ?>
                                        <span class="badge text-bg-primary"><?= h(format_billing_type($entry['billing_type'])) ?></span>
                                    <?php else: ?>
                                        <span class="badge text-bg-success">Payment</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= $entry['debit'] > 0 ? h(money_format_portal((float) $entry['debit'])) : '—' ?></td>
                                <td class="text-end"><?= $entry['credit'] > 0 ? h(money_format_portal((float) $entry['credit'])) : '—' ?></td>
                                <td class="text-end fw-semibold"><?= h(money_format_portal((float) $entry['running_balance'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($statement === []): ?>
                            <tr><td colspan="<?= $selectedClient ? 6 : 7 ?>" class="text-center text-muted py-4">No statement activity found for this filter.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Recent invoices</strong>
                <span class="text-muted small">Includes once-off and recurring invoices</span>
            </div>
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead><tr><th>Invoice</th><th>Client</th><th>Type</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Balance</th></tr></thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td><a href="/modules/invoices/view.php?id=<?= (int) $invoice['id'] ?>"><?= h($invoice['invoice_number']) ?></a><div class="small text-muted"><?= h($invoice['invoice_date']) ?> due <?= h($invoice['due_date']) ?></div></td>
                                <td><?= h($invoice['company_name']) ?></td>
                                <td><span class="badge text-bg-light border"><?= h(format_billing_type((string) ($invoice['billing_type'] ?? 'once_off'))) ?></span></td>
                                <td><?= invoice_status_badge($invoice['status']) ?></td>
                                <td class="text-end"><?= h(money_format_portal((float) $invoice['total_amount'])) ?></td>
                                <td class="text-end"><?= h(money_format_portal((float) $invoice['paid_amount'])) ?></td>
                                <td class="text-end fw-semibold"><?= h(money_format_portal((float) $invoice['balance'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($invoices === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No invoices found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <?php if (has_permission('invoices.create')): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><strong>Create recurring billing profile</strong></div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create_profile">
                        <?php if (is_super_admin()): ?>
                            <div class="mb-3"><label class="form-label">Company</label><select class="form-select" name="company_id" required><?= company_select_options($selectedCompanyId > 0 ? $selectedCompanyId : null) ?></select></div>
                        <?php endif; ?>
                        <div class="mb-3"><label class="form-label">Client</label><select class="form-select" name="client_id" required><?= client_select_options($selectedClientId > 0 ? $selectedClientId : null, $selectedCompanyId > 0 ? $selectedCompanyId : null) ?></select></div>
                        <div class="mb-3"><label class="form-label">Title</label><input class="form-control" name="title" placeholder="Managed hosting plan" required></div>
                        <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" placeholder="Optional invoice line description"></textarea></div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Cycle</label><select class="form-select" name="billing_cycle"><?php foreach (['weekly', 'monthly', 'quarterly', 'yearly'] as $cycle): ?><option value="<?= h($cycle) ?>"><?= h(billing_cycle_label($cycle)) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active">Active</option><option value="paused">Paused</option></select></div>
                            <div class="col-md-6"><label class="form-label">Quantity</label><input class="form-control" type="number" step="0.01" name="quantity" value="1"></div>
                            <div class="col-md-6"><label class="form-label">Unit price</label><input class="form-control" type="number" step="0.01" name="unit_price" value="0"></div>
                            <div class="col-md-6"><label class="form-label">Tax</label><input class="form-control" type="number" step="0.01" name="tax_amount" value="0"></div>
                            <div class="col-md-6"><label class="form-label">Discount</label><input class="form-control" type="number" step="0.01" name="discount_amount" value="0"></div>
                            <div class="col-md-6"><label class="form-label">Start date</label><input class="form-control" type="date" name="start_date" value="<?= date('Y-m-d') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Next invoice date</label><input class="form-control" type="date" name="next_invoice_date" value="<?= date('Y-m-d') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Payment terms (days)</label><input class="form-control" type="number" name="due_days" value="7" min="0"></div>
                            <div class="col-md-6"><label class="form-label">End date</label><input class="form-control" type="date" name="end_date"></div>
                        </div>
                        <div class="mt-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3" placeholder="Optional internal or invoice note"></textarea></div>
                        <div class="mt-3"><button class="btn btn-primary">Save recurring profile</button></div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Recurring schedules</strong>
                <span class="text-muted small"><?= count($profiles) ?> profiles</span>
            </div>
            <div class="card-body d-flex flex-column gap-3">
                <?php foreach ($profiles as $profile): ?>
                    <div class="border rounded-4 p-3 recurring-profile-card">
                        <div class="d-flex justify-content-between gap-2 align-items-start mb-2">
                            <div>
                                <div class="fw-semibold"><?= h($profile['title']) ?></div>
                                <div class="small text-muted"><?= h($profile['company_name']) ?> · <?= h(billing_cycle_label($profile['billing_cycle'])) ?></div>
                            </div>
                            <?= invoice_status_badge($profile['status']) ?>
                        </div>
                        <div class="small text-muted mb-2">Next invoice <?= h($profile['next_invoice_date']) ?> · <?= h(money_format_portal((float) $profile['quantity'] * (float) $profile['unit_price'] + (float) $profile['tax_amount'] - (float) $profile['discount_amount'])) ?></div>
                        <?php if ((string) $profile['notes'] !== ''): ?><div class="small mb-2"><?= h($profile['notes']) ?></div><?php endif; ?>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if (has_permission('invoices.create')): ?>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="generate_profile_now">
                                    <input type="hidden" name="profile_id" value="<?= (int) $profile['id'] ?>">
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Generate now</button>
                                </form>
                            <?php endif; ?>
                            <?php if (has_permission('invoices.edit')): ?>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_profile_status">
                                    <input type="hidden" name="profile_id" value="<?= (int) $profile['id'] ?>">
                                    <input type="hidden" name="next_status" value="<?= $profile['status'] === 'active' ? 'paused' : 'active' ?>">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit"><?= $profile['status'] === 'active' ? 'Pause' : 'Resume' ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($profiles === []): ?><div class="text-center text-muted py-4">No recurring billing profiles yet.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
