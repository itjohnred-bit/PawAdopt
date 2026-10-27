<?php
/**
 * PawAdopt — minimal .env loader.
 *
 * Reads KEY=VALUE pairs from a `.env` file in the project root (if present)
 * and registers them as real environment variables. Real environment
 * variables (Docker/Render dashboard) always win — existing values are
 * never overwritten. On hosts where configuration comes from the dashboard
 * the file is simply absent and this is a no-op.
 */

if (!function_exists('pa_load_env_file')) {
    function pa_load_env_file(string $path): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        if (!is_readable($path)) {
            return; // no .env — fine; configuration must come from the environment
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));

            $len = strlen($val);
            if ($len >= 2
                && (($val[0] === '"'  && $val[$len - 1] === '"')
                 || ($val[0] === '\'' && $val[$len - 1] === '\''))) {
                $val = substr($val, 1, -1);
            }

            if ($key !== '' && getenv($key) === false) {
                putenv("$key=$val");
                $_ENV[$key]    = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

pa_load_env_file(dirname(__DIR__) . '/.env');
