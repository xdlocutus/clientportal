<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (!is_logged_in()) {
    redirect('/modules/auth/login.php');
}

redirect('/modules/dashboard/index.php');
