<?php
declare(strict_types=1);

/**
 * Email helper for PawAdopt.
 *
 * Public API:
 *   - mailer_send($to, $subject, $body, $opts = [])
 *   - mailer_log_only($to, $subject, $body, $opts = [])
 *
 * Reads SMTP credentials from environment / config/email.php so credentials
 * never end up in source control.  Falls back log-only in local dev.
 */

if (!function_exists('mailer_send')) {

    function mailer_send($to, string $subject, string $body, array $opts = []): bool {
        $from     = $opts['from']     ?? (defined('MAIL_FROM')     ? MAIL_FROM     : 'pawsadopt.pup@gmail.com');
        $fromName = $opts['fromName'] ?? (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'PawAdopt');
        $replyTo  = $opts['replyTo']  ?? null;

        $smtpPass = getenv('SMTP_PASS') ?: (defined('SMTP_PASS') ? SMTP_PASS : null);
        $env      = strtolower((string)(getenv('APP_ENV') ?: ''));

        if ($env === 'local' || $smtpPass === null || $smtpPass === '') {
            return mailer_log_only($to, $subject, $body, $opts);
        }

        try {
            $smtp = mailer_config();
            return $smtp
                ? mailer_send_smtp($to, $subject, $body, $from, $fromName, $replyTo, $smtp, $opts)
                : mailer_send_native($to, $subject, $body, $from, $fromName, $replyTo);
        } catch (Throwable $e) {
            error_log('mailer_send failed: ' . $e->getMessage());
            return false;
        }
    }

    function mailer_config(): ?array {
        $host = getenv('SMTP_HOST') ?: (defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com');
        $user = getenv('SMTP_USER') ?: (defined('SMTP_USER') ? SMTP_USER : 'pawsadopt.pup@gmail.com');
        $pass = getenv('SMTP_PASS') ?: (defined('SMTP_PASS') ? SMTP_PASS : null);

        if (!$host || !$user || !$pass) return null;

        return [
            'host'    => $host,
            'port'    => (int)(getenv('SMTP_PORT') ?: (defined('SMTP_PORT') ? SMTP_PORT : 587)),
            'user'    => $user,
            'pass'    => $pass,
            'secure'  => strtolower((string)(getenv('SMTP_SECURE') ?: (defined('SMTP_SECURE') ? SMTP_SECURE : 'tls'))),
            'timeout' => 10,
        ];
    }

    function mailer_send_smtp($to, string $subject, string $body, string $from, string $fromName, ?string $replyTo, array $smtp, array $opts): bool {
        $remote = ($smtp['secure'] === 'ssl' ? 'ssl://' : '') . $smtp['host'] . ':' . $smtp['port'];

        $errno = 0; $errstr = '';
        $socket = @stream_socket_client(
            $remote, $errno, $errstr, $smtp['timeout'], STREAM_CLIENT_CONNECT
        );
        if (!$socket) {
            throw new RuntimeException("SMTP socket connect failed: {$errstr} ({$errno})");
        }

        try {
            mailer_smtp_expect($socket, 220);
            mailer_smtp_send($socket, 'EHLO localhost');
            mailer_smtp_expect($socket, 250);

            if ($smtp['secure'] === 'tls') {
                mailer_smtp_send($socket, 'STARTTLS');
                mailer_smtp_expect($socket, 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT)) {
                    throw new RuntimeException('STARTTLS upgrade failed.');
                }
                mailer_smtp_send($socket, 'EHLO localhost');
                mailer_smtp_expect($socket, 250);
            }

            if ($smtp['user'] && $smtp['pass']) {
                mailer_smtp_send($socket, 'AUTH LOGIN');
                mailer_smtp_expect($socket, 334);
                mailer_smtp_send($socket, base64_encode($smtp['user']));
                mailer_smtp_expect($socket, 334);
                mailer_smtp_send($socket, base64_encode($smtp['pass']));
                mailer_smtp_expect($socket, 235);
            }

            mailer_smtp_send($socket, 'MAIL FROM:<' . $from . '>');
            mailer_smtp_expect($socket, 250);

            $toArr = is_array($to) ? $to : [$to];
            foreach ($toArr as $recipient) {
                $recipient = trim((string)$recipient);
                if ($recipient === '') continue;
                mailer_smtp_send($socket, 'RCPT TO:<' . $recipient . '>');
                mailer_smtp_expect($socket, [250, 251]);
            }

            mailer_smtp_send($socket, 'DATA');
            mailer_smtp_expect($socket, 354);

            $headers = mailer_build_headers($from, $fromName, $replyTo, $toArr, $subject, $opts);
            fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
            mailer_smtp_expect($socket, 250);

            mailer_smtp_send($socket, 'QUIT');
            return true;

        } finally {
            @fclose($socket);
        }
    }

    function mailer_send_native($to, string $subject, string $body, string $from, string $fromName, ?string $replyTo): bool {
        $toArr  = is_array($to) ? $to : [$to];
        $headers = mailer_build_headers($from, $fromName, $replyTo, $toArr, $subject, []);
        return @mail($to, $subject, $body, $headers);
    }

    function mailer_build_headers(string $from, string $fromName, ?string $replyTo, array $toArr, string $subject, array $opts): string {
        $hasHtml = !empty($opts['html']) && is_string($opts['html']);

        $lines = [];
        $lines[] = 'From: ' . mailer_format_address($fromName, $from);
        if ($replyTo) $lines[] = 'Reply-To: ' . mailer_format_address('', $replyTo);
        $lines[] = 'To: ' . implode(', ', array_map(static function ($addr) {
            return mailer_format_address('', $addr);
        }, $toArr));
        $lines[] = 'Subject: ' . $subject;
        $lines[] = 'Date: ' . date('r');
        $lines[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . ($_SERVER['SERVER_NAME'] ?? 'pawadopt.local') . '>';
        $lines[] = 'MIME-Version: 1.0';

        if ($hasHtml) {
            $boundary = 'PawAdopt_' . bin2hex(random_bytes(8));
            $lines[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        } else {
            $lines[] = 'Content-Type: text/plain; charset=UTF-8';
            $lines[] = 'Content-Transfer-Encoding: 8bit';
        }

        return implode("\r\n", $lines);
    }

    function mailer_format_address(string $name, string $email): string {
        $name = trim($name);
        $email = trim($email);
        if ($name === '') return $email;
        if (strpbrk($name, ",;\"<>") !== false) {
            $name = '"' . str_replace('"', '\\"', $name) . '"';
        }
        return $name . ' <' . $email . '>';
    }

    function mailer_smtp_send($socket, string $line): void {
        fwrite($socket, $line . "\r\n");
    }

    function mailer_smtp_expect($socket, $expected): void {
        $expect = is_array($expected) ? $expected : [$expected];
        $buf = '';
        while (!feof($socket)) {
            $line = fgets($socket, 1024);
            if ($line === false) break;
            $buf .= $line;
            $code = (int)substr($line, 0, 3);
            if (strlen($line) < 4 || $line[3] === ' ' || $line[3] === "\r" || $line[3] === "\n") {
                if (in_array($code, $expect, true)) return;
                throw new RuntimeException("SMTP unexpected response (wanted " .
                    implode('/', array_map('strval', $expect)) . "): " . trim($buf));
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
        return (bool)@file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }
}
