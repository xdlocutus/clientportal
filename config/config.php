<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

define('APP_NAME', 'Business Portal');
define('APP_URL', 'http://localhost');
define('BASE_PATH', dirname(__DIR__));
define('DEFAULT_TIMEZONE', 'UTC');

date_default_timezone_set(DEFAULT_TIMEZONE);

error_reporting(E_ALL);
ini_set('display_errors', '1');
