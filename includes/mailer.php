<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$envFile = file_exists('/etc/secrets/.env')
    ? '/etc/secrets/.env'
    : __DIR__ . '/../.env';

if (is_file($envFile) && is_readable($envFile)) {
    try {
        $dotenv = Dotenv::createImmutable(dirname($envFile), basename($envFile));
        $dotenv->safeLoad();
    } catch (Throwable $e) {
        error_log('mailer.php: Dotenv load failed: ' . $e->getMessage());
    }

    $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (!str_contains($line, '=')) continue;
            [$name, $value] = explode('=', $line, 2);
            $name  = trim($name);
            $value = trim($value, " \t\"'");
            if ($name === '' || !preg_match('/^[A-Z][A-Z0-9_]*$/i', $name)) continue;
            putenv("{$name}={$value}");
            $_ENV[$name]    = $value;
            $_SERVER[$name] = $value;
        }
    }
}

if (!function_exists('mailer_env')) {
    function mailer_env(string $key, ?string $default = null): ?string {
        if (array_key_exists($key, $_ENV) && $_ENV[$key] !== null && $_ENV[$key] !== '') {
            return trim((string)$_ENV[$key], " \t\"'");
        }
        $v = getenv($key);
        if ($v !== false && $v !== '') {
            return trim($v, " \t\"'");
        }
        return $default;
    }
}

if (!function_exists('mailer_send')) {
    function mailer_send($to, string $subject, string $body, array $opts = []): bool {
        $smtpPass = mailer_env('SMTP_PASS');
        $appEnv   = strtolower((string)mailer_env('APP_ENV', 'production'));

        if ($appEnv === 'local' || empty($smtpPass)) {
            return mailer_log_only($to, $subject, $body, $opts);
        }

        try {
            $cfg = mailer_config();
            $from     = (string)mailer_env('MAIL_FROM', 'pawsadopt.pup@gmail.com');
            $fromName = (string)mailer_env('MAIL_FROM_NAME', 'PawAdopt');
            $replyTo  = mailer_env('MAIL_REPLY_TO');

            return $cfg
                ? mailer_send_smtp($to, $subject, $body, $from, $fromName, $replyTo, $cfg, $opts)
                : mailer_send_native($to, $subject, $body, $from, $fromName, $replyTo);
        } catch (Throwable $e) {
            error_log('mailer_send failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('mailer_config')) {
    function mailer_config(): ?array {
        $host = mailer_env('SMTP_HOST', 'smtp.gmail.com');
        $user = mailer_env('SMTP_USER', 'pawsadopt.pup@gmail.com');
        $pass = mailer_env('SMTP_PASS');
        if (!$host || !$user || empty($pass)) return null;

        $secure = strtolower((string)mailer_env('SMTP_SECURE', 'tls'));

        return [
            'host'    => $host,
            'port'    => (int)mailer_env('SMTP_PORT', $secure === 'ssl' ? '465' : '587'),
            'user'    => $user,
            'pass'    => $pass,
            'secure'  => $secure,
            'timeout' => 10,
        ];
    }
}

if (!function_exists('mailer_send_smtp')) {
    function mailer_send_smtp(
        $to, string $subject, string $body,
        string $from, string $fromName, ?string $replyTo,
        array $smtp, array $opts
    ): bool {
        $remote = ($smtp['secure'] === 'ssl' ? 'ssl://' : '') . $smtp['host'] . ':' . $smtp['port'];

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            $smtp['timeout'],
            STREAM_CLIENT_CONNECT
        );
        if (!$socket) {
            throw new RuntimeException("SMTP connect failed for {$remote}: {$errstr} ({$errno})");
        }
        stream_set_timeout($socket, $smtp['timeout']);

        try {
            mailer_smtp_expect($socket, 220);
            mailer_smtp_send($socket, 'EHLO localhost');
            mailer_smtp_expect($socket, 250);

            if ($smtp['secure'] === 'tls') {
                mailer_smtp_send($socket, 'STARTTLS');
                mailer_smtp_expect($socket, 220);
                $cryptoOk = @stream_socket_enable_crypto(
                    $socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                );
                if (!$cryptoOk) {
                    throw new RuntimeException('STARTTLS upgrade failed.');
                }
                mailer_smtp_send($socket, 'EHLO localhost');
                mailer_smtp_expect($socket, 250);
            }

            mailer_smtp_send($socket, 'AUTH LOGIN');
            mailer_smtp_expect($socket, 334);
            mailer_smtp_send($socket, base64_encode($smtp['user']));
            mailer_smtp_expect($socket, 334);
            mailer_smtp_send($socket, base64_encode($smtp['pass']));
            mailer_smtp_expect($socket, 235);

            mailer_smtp_send($socket, 'MAIL FROM:<' . filter_var($from, FILTER_VALIDATE_EMAIL) . '>');
            mailer_smtp_expect($socket, 250);

            $toArr = is_array($to) ? $to : [$to];
            $validTo = [];
            foreach ($toArr as $recipient) {
                $recipient = trim((string)$recipient);
                if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) continue;
                mailer_smtp_send($socket, 'RCPT TO:<' . $recipient . '>');
                mailer_smtp_expect($socket, [250, 251]);
                $validTo[] = $recipient;
            }
            if (!$validTo) {
                throw new RuntimeException('No valid recipients after filtering.');
            }

            mailer_smtp_send($socket, 'DATA');
            mailer_smtp_expect($socket, 354);

            $boundary = null;
            $hasHtml  = !empty($opts['html']) && is_string($opts['html']);
            if ($hasHtml) {
                $boundary = 'PawAdopt_' . bin2hex(random_bytes(8));
            }

            $headers = mailer_build_headers(
                $from, $fromName, $replyTo, $validTo, $subject,
                $opts + ['_boundary' => $boundary]
            );

            if ($hasHtml) {
                $payload  = "--{$boundary}\r\n"
                          . "Content-Type: text/plain; charset=UTF-8\r\n"
                          . "Content-Transfer-Encoding: 8bit\r\n\r\n{$body}\r\n"
                          . "--{$boundary}\r\n"
                          . "Content-Type: text/html; charset=UTF-8\r\n"
                          . "Content-Transfer-Encoding: 8bit\r\n\r\n{$opts['html']}\r\n"
                          . "--{$boundary}--\r\n";
            } else {
                $payload = $body;
            }

            fwrite($socket, $headers . "\r\n\r\n" . $payload . "\r\n.\r\n");
            mailer_smtp_expect($socket, 250);

            mailer_smtp_send($socket, 'QUIT');
            return true;
        } finally {
            @fclose($socket);
        }
    }
}

if (!function_exists('mailer_send_native')) {
    function mailer_send_native($to, string $subject, string $body, string $from, string $fromName, ?string $replyTo): bool {
        $toArr   = is_array($to) ? $to : [$to];
        $headers = mailer_build_headers($from, $fromName, $replyTo, $toArr, $subject, []);
        $ok = @mail($to, $subject, $body, $headers);
        if (!$ok) {
            error_log('mailer_send_native: mail() failed (likely no MTA on Render).');
        }
        return $ok;
    }
}

if (!function_exists('mailer_build_headers')) {
    function mailer_build_headers(
        string $from, string $fromName, ?string $replyTo,
        array $toArr, string $subject, array $opts
    ): string {
        $hasHtml = !empty($opts['html']) && is_string($opts['html']);

        $lines   = [];
        $lines[] = 'From: ' . mailer_format_address($fromName, $from);
        if ($replyTo) {
            $lines[] = 'Reply-To: ' . mailer_format_address('', $replyTo);
        }
        $lines[] = 'To: ' . implode(', ', array_filter(array_map(
            static function ($addr): string {
                $addr = trim((string)$addr);
                return $addr !== '' ? mailer_format_address('', $addr) : '';
            },
            $toArr
        )));
        $lines[] = 'Subject: ' . $subject;
        $lines[] = 'Date: ' . date('r');
        $lines[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@'
                  . ($_SERVER['SERVER_NAME'] ?? 'pawadopt.local') . '>';
        $lines[] = 'MIME-Version: 1.0';

        if ($hasHtml && !empty($opts['_boundary'])) {
            $lines[] = 'Content-Type: multipart/alternative; boundary="' . $opts['_boundary'] . '"';
        } else {
            $lines[] = 'Content-Type: text/plain; charset=UTF-8';
            $lines[] = 'Content-Transfer-Encoding: 8bit';
        }

        return implode("\r\n", $lines);
    }
}

if (!function_exists('mailer_format_address')) {
    function mailer_format_address(string $name, string $email): string {
        $name  = trim($name);
        $email = trim($email);
        if ($email === '') return $name;
        if ($name === '')  return $email;
        if (strpbrk($name, ",;\"<>") !== false) {
            $name = '"' . str_replace('"', '\\"', $name) . '"';
        }
        return $name . ' <' . $email . '>';
    }
}

if (!function_exists('mailer_smtp_send')) {
    function mailer_smtp_send($socket, string $line): void {
        fwrite($socket, $line . "\r\n");
    }
}

if (!function_exists('mailer_smtp_expect')) {
    function mailer_smtp_expect($socket, $expected): void {
        $expect = is_array($expected) ? $expected : [$expected];
        $buf    = '';
        while (!feof($socket)) {
            $line = fgets($socket, 4096);
            if ($line === false) break;
            $buf .= $line;
            $code = (int)substr($line, 0, 3);
            if (strlen($line) >= 4 && $line[3] === ' ') {
                if (in_array($code, $expect, true)) return;
                throw new RuntimeException(
                    'SMTP unexpected response (wanted '
                    . implode('/', array_map('strval', $expect))
                    . '): ' . trim($buf)
                );
            }
        }
        throw new RuntimeException('SMTP connection closed unexpectedly.');
    }
}

if (!function_exists('mailer_log_only')) {
    function mailer_log_only($to, string $subject, string $body, array $opts = []): bool {
        $recipient = is_array($to) ? implode(', ', $to) : (string)$to;
        $line = str_repeat('=', 60) . "\n"
              . '[' . date('c') . "] TO: {$recipient}\nSUBJECT: {$subject}\n\n{$body}\n";

        $path = __DIR__ . '/../storage/mail.log';
        $dir  = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        if ((!is_dir($dir) || !is_writable($dir)) && function_exists('sys_get_temp_dir')) {
            $path = rtrim(sys_get_temp_dir(), '/') . '/pawadopt-mail.log';
        }

        return (bool)@file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }
}
