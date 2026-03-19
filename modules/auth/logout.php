<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

logout_user();
session_start();
set_flash('success', 'You have been logged out.');
redirect('/modules/auth/login.php');
