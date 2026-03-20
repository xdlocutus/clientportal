<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_permission('exports.view');

$pageTitle = 'Exports';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3 mb-4">
            <div>
                <span class="eyebrow-label">Exports</span>
                <h1 class="h3 mb-1 mt-2">CSV & PDF Exports</h1>
                <p class="text-body-secondary mb-0">Export invoices, tickets, or client lists for accounting reviews, audits, and handoffs.</p>
            </div>
            <?php if (has_permission('reports.view')): ?>
                <a class="btn btn-outline-secondary" href="/modules/reports/index.php">View reports</a>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <?php foreach ([
                'clients' => ['title' => 'Client List', 'description' => 'Export company, contact, email, phone, and status details.'],
                'invoices' => ['title' => 'Invoices', 'description' => 'Export invoice numbers, dates, balances, totals, and statuses.'],
                'tickets' => ['title' => 'Tickets', 'description' => 'Export support subjects, priorities, statuses, and update timestamps.'],
            ] as $dataset => $meta): ?>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm surface-card h-100">
                        <div class="card-body p-4">
                            <h2 class="h5 mb-2"><?= h($meta['title']) ?></h2>
                            <p class="text-body-secondary mb-4"><?= h($meta['description']) ?></p>
                            <div class="d-grid gap-2">
                                <a class="btn btn-primary" href="/modules/exports/download.php?dataset=<?= h($dataset) ?>&format=csv">Download CSV</a>
                                <a class="btn btn-outline-secondary" href="/modules/exports/download.php?dataset=<?= h($dataset) ?>&format=print" target="_blank" rel="noopener">Open Print / PDF View</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
