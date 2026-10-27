<?php
/**
 * SMTP configuration — PawAdopt
 *
 * SECURITY: the app-password / secret must come from a server-side
 * environment variable, never from code committed to the repo.
 *
 * Set these environment variables on your host (Render dashboard →
 * Environment, Apache .htaccess SetEnv, or a php.ini / .env loader):
 *   SMTP_HOST       (default: smtp.gmail.com)
 *   SMTP_PORT       (default: 587)
 *   SMTP_ENCRYPTION (default: tls)
 *   SMTP_USERNAME   — the sending mailbox address
 *   SMTP_PASSWORD   — the app password (SECRET — server-side only)
 *   SMTP_FROM_EMAIL
 *   SMTP_FROM_NAME
 *
 * The browser never receives any of these values: mail is sent
 * exclusively from server-side code (api/auth.php).
 */

$pa_env = static function (string $key, string $default = ''): string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? '';
    }
    return ($value !== '') ? $value : $default;
};

define('SMTP_HOST',       $pa_env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT',       (int) $pa_env('SMTP_PORT', '587'));
define('SMTP_ENCRYPTION', $pa_env('SMTP_ENCRYPTION', 'tls'));
define('SMTP_USERNAME',   $pa_env('SMTP_USERNAME'));
define('SMTP_PASSWORD',   $pa_env('SMTP_PASSWORD')); // SECRET — env var only
define('SMTP_FROM_EMAIL', $pa_env('SMTP_FROM_EMAIL', $pa_env('SMTP_USERNAME')));
define('SMTP_FROM_NAME',  $pa_env('SMTP_FROM_NAME', 'PawAdopt'));
