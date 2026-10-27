<?php
/** PAWAdopt - Fixed Authentication System with Audit Logging */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions_audit.php';
if (file_exists(__DIR__ . '/../config/smtp.php')) {
    require_once __DIR__ . '/../config/smtp.php';
}
startSession();

$pdo = Database::getInstance()->getConnection();

if (!function_exists('pa_has_account_deletion_table')) {
    function pa_has_account_deletion_table(PDO $pdo): bool
    {
        static $hasTable = null;

        if ($hasTable !== null) {
            return $hasTable;
        }

        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'account_deletion_requests'");
            $hasTable = (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            $hasTable = false;
        }

        return $hasTable;
    }
}

if (!function_exists('pa_table_columns')) {
    function pa_table_columns(PDO $pdo, string $table): array
    {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName === '') {
            return $cache[$table] = [];
        }

        $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
        $stmt->execute([$dbName, $table]);

        $cols = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $col) {
            $cols[(string) $col] = true;
        }

        return $cache[$table] = $cols;
    }
}

if (!function_exists('pa_delete_notification_rows')) {
    function pa_delete_notification_rows(Database $db, PDO $pdo, int $userId): void
    {
        $cols = pa_table_columns($pdo, 'notifications');
        if (!$cols) {
            return;
        }

        if (isset($cols['user_id'])) {
            $db->execute('DELETE FROM notifications WHERE user_id = ?', [$userId]);
        }
        if (isset($cols['recipient_id'])) {
            $db->execute('DELETE FROM notifications WHERE recipient_id = ?', [$userId]);
        }
    }
}

if (!function_exists('pa_delete_adopter_account')) {
    function pa_delete_adopter_account(Database $db, PDO $pdo, int $userId): void
    {
        $pdo->beginTransaction();

        try {
            $convRows = $db->fetchAll('SELECT conversation_id FROM conversations WHERE adopter_id = ?', [$userId]);
            foreach ($convRows as $conv) {
                $db->execute('DELETE FROM messages WHERE conversation_id = ?', [$conv['conversation_id']]);
            }

            $db->execute('DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?', [$userId, $userId]);
            $db->execute('DELETE FROM conversations WHERE adopter_id = ?', [$userId]);
            $db->execute('DELETE FROM favorites WHERE adopter_id = ?', [$userId]);
            $db->execute('DELETE FROM adoption_applications WHERE adopter_id = ?', [$userId]);
            $db->execute('DELETE FROM adopter_profiles WHERE adopter_id = ?', [$userId]);

            if (pa_table_columns($pdo, 'user_feedback')) {
                $db->execute('DELETE FROM user_feedback WHERE user_id = ?', [$userId]);
            }
            if (pa_table_columns($pdo, 'admin_reports')) {
                $db->execute('DELETE FROM admin_reports WHERE user_id = ?', [$userId]);
            }
            if (pa_has_account_deletion_table($pdo)) {
                $db->execute("UPDATE account_deletion_requests SET status = 'PROCESSED', processed_at = NOW() WHERE user_id = ? AND status = 'PENDING'", [$userId]);
            }

            pa_delete_notification_rows($db, $pdo, $userId);
            $db->execute('DELETE FROM users WHERE user_id = ? AND role = ?', [$userId, 'ADOPTER']);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

if (!function_exists('pa_cleanup_due_deletions')) {
    function pa_cleanup_due_deletions(Database $db, PDO $pdo): void
    {
        if (!pa_has_account_deletion_table($pdo)) {
            return;
        }

        $due = $db->fetchAll(
            "SELECT adr.user_id
             FROM account_deletion_requests adr
             JOIN users u ON u.user_id = adr.user_id
             WHERE adr.status = 'PENDING' AND adr.scheduled_for <= NOW() AND u.role = 'ADOPTER'",
            []
        );

        foreach ($due as $row) {
            pa_delete_adopter_account($db, $pdo, (int) $row['user_id']);
        }
    }
}

pa_cleanup_due_deletions(Database::getInstance(), $pdo);

if (!function_exists('pa_has_password_reset_table')) {
    function pa_has_password_reset_table(PDO $pdo): bool
    {
        static $hasTable = null;

        if ($hasTable !== null) {
            return $hasTable;
        }

        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_otps'");
            $hasTable = (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            $hasTable = false;
        }

        return $hasTable;
    }
}

if (!function_exists('pa_smtp_config')) {
    function pa_smtp_config(): array
    {
        return [
            'host' => defined('SMTP_HOST') ? (string) SMTP_HOST : '',
            'port' => defined('SMTP_PORT') ? (int) SMTP_PORT : 587,
            'encryption' => defined('SMTP_ENCRYPTION') ? strtolower((string) SMTP_ENCRYPTION) : 'tls',
            'username' => defined('SMTP_USERNAME') ? trim((string) SMTP_USERNAME) : '',
            'password' => defined('SMTP_PASSWORD') ? preg_replace('/\s+/', '', (string) SMTP_PASSWORD) : '',
            'from_email' => defined('SMTP_FROM_EMAIL') ? trim((string) SMTP_FROM_EMAIL) : (defined('SMTP_USERNAME') ? trim((string) SMTP_USERNAME) : ''),
            'from_name' => defined('SMTP_FROM_NAME') ? trim((string) SMTP_FROM_NAME) : 'PawAdopt',
        ];
    }
}

if (!function_exists('pa_smtp_read')) {
    function pa_smtp_read($socket, array $expectedCodes): string
    {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            if ($line === false) {
                break;
            }
            $response .= $line;
            if (preg_match('/^(\d{3})([\s-])/', $line, $m) && $m[2] === ' ') {
                $code = (int) $m[1];
                if (!in_array($code, $expectedCodes, true)) {
                    throw new RuntimeException('SMTP error: ' . trim($response));
                }
                return $response;
            }
        }

        throw new RuntimeException('SMTP response was incomplete.');
    }
}

if (!function_exists('pa_smtp_cmd')) {
    function pa_smtp_cmd($socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");
        return pa_smtp_read($socket, $expectedCodes);
    }
}

if (!function_exists('pa_send_smtp_mail')) {
    function pa_send_smtp_mail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        $cfg = pa_smtp_config();
        if ($cfg['host'] === '' || $cfg['username'] === '' || $cfg['password'] === '' || $cfg['from_email'] === '') {
            throw new RuntimeException('SMTP settings are incomplete.');
        }

        $socket = stream_socket_client(
            'tcp://' . $cfg['host'] . ':' . $cfg['port'],
            $errno,
            $errstr,
            20,
            STREAM_CLIENT_CONNECT
        );

        if (!$socket) {
            throw new RuntimeException('SMTP connection failed: ' . $errstr);
        }

        stream_set_timeout($socket, 20);

        try {
            pa_smtp_read($socket, [220]);
            pa_smtp_cmd($socket, 'EHLO pawadopt.local', [250]);

            if ($cfg['encryption'] === 'tls') {
                pa_smtp_cmd($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Unable to enable TLS encryption.');
                }
                pa_smtp_cmd($socket, 'EHLO pawadopt.local', [250]);
            }

            pa_smtp_cmd($socket, 'AUTH LOGIN', [334]);
            pa_smtp_cmd($socket, base64_encode($cfg['username']), [334]);
            pa_smtp_cmd($socket, base64_encode($cfg['password']), [235]);
            pa_smtp_cmd($socket, 'MAIL FROM: <' . $cfg['from_email'] . '>', [250]);
            pa_smtp_cmd($socket, 'RCPT TO: <' . $toEmail . '>', [250, 251]);
            pa_smtp_cmd($socket, 'DATA', [354]);

            $boundary = 'pawadopt-' . bin2hex(random_bytes(8));
            $subjectHeader = 'Subject: ' . $subject;
            $headers = [
                'From: ' . $cfg['from_name'] . ' <' . $cfg['from_email'] . '>',
                'To: ' . ($toName !== '' ? $toName . ' <' . $toEmail . '>' : $toEmail),
                $subjectHeader,
                'MIME-Version: 1.0',
                'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            ];

            $textBody = $textBody !== '' ? $textBody : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));
            $message = implode("\r\n", $headers) . "\r\n\r\n"
                . '--' . $boundary . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
                . $textBody . "\r\n"
                . '--' . $boundary . "\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
                . $htmlBody . "\r\n"
                . '--' . $boundary . "--\r\n";

            $message = preg_replace("/(?m)^\./", '..', $message);
            fwrite($socket, $message . "\r\n.\r\n");
            pa_smtp_read($socket, [250]);
            pa_smtp_cmd($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }

        return true;
    }
}

if (!function_exists('pa_send_reset_otp_email')) {
    function pa_send_reset_otp_email(string $toEmail, string $username, string $otp): bool
    {
        $subject = 'PawAdopt Password Reset OTP';
        $html = '<div style="font-family:Arial,sans-serif;padding:20px;color:#1f2937">'
            . '<h2 style="margin:0 0 12px;color:#0f766e">Password Reset Request</h2>'
            . '<p style="margin:0 0 14px">Hello ' . htmlspecialchars($username ?: 'PawAdopt user', ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p style="margin:0 0 14px">Use this one-time password to reset your PawAdopt account:</p>'
            . '<div style="display:inline-block;padding:14px 20px;border-radius:12px;background:#ecfeff;color:#0f766e;font-size:28px;font-weight:800;letter-spacing:6px">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<p style="margin:16px 0 8px">This OTP expires in 10 minutes.</p>'
            . '<p style="margin:0;color:#6b7280">If you did not request a password reset, you can ignore this email.</p>'
            . '</div>';
        $text = "PawAdopt Password Reset\n\nHello {$username},\n\nUse this OTP to reset your password: {$otp}\nThis OTP expires in 10 minutes.\n\nIf you did not request this, ignore this email.";

        return pa_send_smtp_mail($toEmail, $username, $subject, $html, $text);
    }
}

if (!function_exists('pa_allowed_email_domains')) {
    function pa_allowed_email_domains(): array
    {
        return [
            'gmail.com',
            'icloud.com',
            'outlook.com',
            'yahoo.com',
            'hotmail.com',
            'aol.com',
            'proton.me',
            'protonmail.com',
            'zoho.com',
            'comcast.net',
            'msn.com',
        ];
    }
}

if (!function_exists('pa_disposable_email_domains')) {
    function pa_disposable_email_domains(): array
    {
        return [
            'mailinator.com', 'tempmail.com', 'temp-mail.org', '10minutemail.com',
            'guerrillamail.com', 'yopmail.com', 'sharklasers.com', 'trashmail.com',
            'trashmail.de', 'trashmail.io', 'fakeinbox.com', 'getnada.com', 'maildrop.cc',
            'dispostable.com', 'mintemail.com', 'spamgourmet.com', 'mailnesia.com',
            'tempr.email', 'tempinbox.com', 'throwawaymail.com', 'jetable.org',
            'spam4.me', 'grr.la', 'mailcatch.com', 'mohmal.com', 'tempemail.com', 'spambox.us',
        ];
    }
}

if (!function_exists('pa_validate_email')) {
    function pa_validate_email(string $email, ?string &$error = null): bool
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            $error = 'Email address is required.';
            return false;
        }

        if (strlen($email) > 254) {
            $error = 'Email address is too long.';
            return false;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
            return false;
        }

        $parts = explode('@', $email);
        $domain = $parts[1] ?? '';

        if ($domain === '') {
            $error = 'Email domain is invalid.';
            return false;
        }

        if (in_array($domain, pa_disposable_email_domains(), true)) {
            $error = 'Disposable / temporary email addresses are not allowed.';
            return false;
        }

        $allowed = pa_allowed_email_domains();
        if (!in_array($domain, $allowed, true)) {
            $error = 'Use a supported email provider only: ' . implode(', ', $allowed);
            return false;
        }

        if (function_exists('checkdnsrr')) {
            $hasMx = checkdnsrr($domain, 'MX');
            $hasA = checkdnsrr($domain, 'A');
            $hasAaaa = checkdnsrr($domain, 'AAAA');
            if (!$hasMx && !$hasA && !$hasAaaa) {
                $error = 'Email domain could not be verified.';
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('pa_validate_password')) {
    function pa_validate_password(string $password, ?string &$error = null): bool
    {
        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
            return false;
        }

        $classes = 0;
        $classes += preg_match('/[A-Z]/', $password) ? 1 : 0;
        $classes += preg_match('/[a-z]/', $password) ? 1 : 0;
        $classes += preg_match('/\d/', $password) ? 1 : 0;
        $classes += preg_match('/[^A-Za-z0-9]/', $password) ? 1 : 0;

        if ($classes < 3) {
            $error = 'Password must include at least 3 of these: uppercase, lowercase, number, and symbol.';
            return false;
        }

        return true;
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); break; }

        $username  = trim($_POST['username'] ?? '');
        $password  = $_POST['password'] ?? '';
        $roleInput = strtoupper(trim($_POST['role'] ?? ''));

        if (!$username || !$password || !$roleInput) {
            jsonError('All fields are required.', 400);
            break;
        }

        $db = Database::getInstance();

        $user = $db->fetch(
            'SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1',
            [$username, $username]
        );

        if (!$user) {
            jsonError('Username or email not found.');
            break;
        }

        if (!password_verify($password, $user['password_hash'])) {
            log_action($user['user_id'], 'failed_login', 'Failed login attempt for user: ' . $user['username'], strtoupper($user['role']), $user['username']);
            jsonError('Incorrect password.');
            break;
        }

        if (strtoupper($user['role']) !== $roleInput) {
            $actualRole = ucfirst(strtolower($user['role']));
            jsonError("Incorrect role selection. This account is registered as: {$actualRole}");
            break;
        }

        $_SESSION['user_id']  = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = strtoupper($user['role']);
        $_SESSION['user']     = [
            'user_id'  => $user['user_id'],
            'username' => $user['username'],
            'role'     => strtoupper($user['role']),
        ];

        log_action($user['user_id'], 'login', 'Successful login from ' . $user['username']);

        $roleDir = strtolower($user['role']);
        if ($roleDir === 'veterinary') $roleDir = 'shelter';
        $redirect = APP_URL . "/pages/{$roleDir}/dashboard.php";

        jsonSuccess(['redirect' => $redirect], 'Login successful!');
        break;

    case 'register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); break; }

        $username      = trim($_POST['username'] ?? '');
        $email         = strtolower(trim($_POST['email'] ?? ''));
        $password      = $_POST['password'] ?? '';
        $confirm       = $_POST['confirm_password'] ?? '';
        $role          = strtoupper(trim($_POST['role'] ?? 'ADOPTER'));
        $privacyAccept = ($_POST['privacy_accepted'] ?? '0') === '1';

        $allowedRoles = ['ADOPTER', 'SHELTER', 'VETERINARY', 'ADMIN'];

        if (!$privacyAccept) {
            jsonError('You must accept the privacy policy to create an account.');
            break;
        }

        if (strlen($username) < 3) {
            jsonError('Username must be at least 3 characters.');
            break;
        }

        $emailErr = null;
        if (!pa_validate_email($email, $emailErr)) {
            jsonError($emailErr ?? 'Invalid email address.');
            break;
        }

        $pwErr = null;
        if (!pa_validate_password($password, $pwErr)) {
            jsonError($pwErr ?? 'Invalid password.');
            break;
        }

        if ($password !== $confirm) {
            jsonError('Passwords do not match.');
            break;
        }

        if (!in_array($role, $allowedRoles, true)) {
            jsonError('Invalid role selection.');
            break;
        }

        $db = Database::getInstance();
        $exists = $db->fetch(
            'SELECT user_id FROM users WHERE username = ? OR email = ?',
            [$username, $email]
        );
        if ($exists) {
            jsonError('Username or email already registered.');
            break;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $db->execute(
                'INSERT INTO users (role, username, email, password_hash) VALUES (?, ?, ?, ?)',
                [$role, $username, $email, $hash]
            );
            $userId = (int)$db->lastInsertId();

            if ($role === 'ADOPTER') {
                $db->execute('INSERT INTO adopter_profiles (adopter_id) VALUES (?)', [$userId]);
            } elseif ($role === 'SHELTER' || $role === 'VETERINARY') {
                $db->execute(
                    'INSERT INTO shelter_profiles (shelter_id, shelter_name) VALUES (?, ?)',
                    [$userId, $username . "'s " . ($role === 'VETERINARY' ? 'Clinic' : 'Shelter')]
                );
                $db->execute('INSERT INTO shelter_verifications (shelter_id) VALUES (?)', [$userId]);
            }

            log_action($userId, 'privacy_consent', 'Accepted privacy policy at registration', $role, $username);

            $_SESSION['user_id']  = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['role']     = $role;
            $_SESSION['user']     = [
                'user_id' => $userId,
                'username' => $username,
                'email' => $email,
                'role' => $role,
            ];

            log_action($userId, 'register', 'New account registered: ' . $username);

            $roleDir = strtolower($role);
            if ($roleDir === 'veterinary') $roleDir = 'shelter';

            jsonSuccess(
                ['redirect' => APP_URL . "/pages/{$roleDir}/dashboard.php"],
                'Account created!'
            );
        } catch (Exception $e) {
            jsonError('Registration failed.');
        }
        break;

    case 'request_reset_otp':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); break; }
        if (!pa_has_password_reset_table($pdo)) {
            jsonError('Password reset is not ready yet. Run the password reset SQL migration first.');
            break;
        }

        $email = strtolower(trim($_POST['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonError('Please enter a valid email address.');
            break;
        }

        $db = Database::getInstance();
        $recentByEmail = $db->fetch(
            "SELECT COUNT(*) AS c FROM password_reset_otps WHERE email = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)",
            [$email]
        );
        $requestIp = $_SERVER['REMOTE_ADDR'] ?? '';
        $recentByIp = $db->fetch(
            "SELECT COUNT(*) AS c FROM password_reset_otps WHERE request_ip = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)",
            [$requestIp]
        );
        if ((int)($recentByEmail['c'] ?? 0) >= 3 || ($requestIp !== '' && (int)($recentByIp['c'] ?? 0) >= 10)) {
            jsonError('Too many OTP requests. Please wait a few minutes and try again.');
            break;
        }

        $user = $db->fetch('SELECT user_id, username, email, role FROM users WHERE email = ? AND is_active = 1 LIMIT 1', [$email]);
        if (!$user) {
            jsonSuccess([], 'If that email exists, an OTP has been sent.');
            break;
        }

        $otp = (string) random_int(100000, 999999);
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        try {
            $db->execute('UPDATE password_reset_otps SET used_at = NOW() WHERE email = ? AND used_at IS NULL', [$email]);
            $db->execute(
                'INSERT INTO password_reset_otps (user_id, email, otp_hash, expires_at, request_ip) VALUES (?, ?, ?, ?, ?)',
                [$user['user_id'], $email, $otpHash, $expiresAt, $requestIp]
            );
            pa_send_reset_otp_email($email, (string)($user['username'] ?? 'User'), $otp);
            $userRole = strtoupper((string)($user['role'] ?? 'ADOPTER'));
            log_action((int)$user['user_id'], 'request_password_reset_otp', 'Requested password reset OTP', $userRole, (string)($user['username'] ?? ''));
            jsonSuccess([], 'If that email exists, an OTP has been sent.');
        } catch (Throwable $e) {
            jsonError('Unable to send OTP right now. Check SMTP configuration and try again.');
        }
        break;

    case 'reset_password_otp':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); break; }
        if (!pa_has_password_reset_table($pdo)) {
            jsonError('Password reset is not ready yet. Run the password reset SQL migration first.');
            break;
        }

        $email = strtolower(trim($_POST['email'] ?? ''));
        $otp = trim($_POST['otp'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($email === '' || $otp === '' || $password === '' || $confirm === '') {
            jsonError('All reset fields are required.');
            break;
        }
        if (!preg_match('/^\d{6}$/', $otp)) {
            jsonError('OTP must be 6 digits.');
            break;
        }
        if ($password !== $confirm) {
            jsonError('Passwords do not match.');
            break;
        }

        $pwErr = null;
        if (!pa_validate_password($password, $pwErr)) {
            jsonError($pwErr ?? 'Invalid password.');
            break;
        }

        $db = Database::getInstance();
        $user = $db->fetch('SELECT user_id, username, role FROM users WHERE email = ? LIMIT 1', [$email]);
        if (!$user) {
            jsonError('Invalid email or OTP.');
            break;
        }

        $resetRows = $db->fetchAll(
            "SELECT *
             FROM password_reset_otps
             WHERE email = ? AND used_at IS NULL
             ORDER BY reset_id DESC
             LIMIT 5",
            [$email]
        );
        if (!$resetRows) {
            jsonError('No active OTP request found. Request a new OTP first.');
            break;
        }

        $matchedReset = null;
        $hasUnexpiredOtp = false;
        foreach ($resetRows as $reset) {
            if (strtotime((string)$reset['expires_at']) < time()) {
                $db->execute('UPDATE password_reset_otps SET used_at = NOW() WHERE reset_id = ?', [$reset['reset_id']]);
                continue;
            }

            $hasUnexpiredOtp = true;
            if (password_verify($otp, (string)$reset['otp_hash'])) {
                $matchedReset = $reset;
                break;
            }
        }

        if (!$hasUnexpiredOtp) {
            jsonError('OTP expired. Request a new one.');
            break;
        }

        if (!$matchedReset) {
            jsonError('Invalid OTP.');
            break;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        try {
            $db->execute('UPDATE users SET password_hash = ?, is_active = 1 WHERE user_id = ?', [$hash, $user['user_id']]);
            $db->execute('UPDATE password_reset_otps SET used_at = NOW() WHERE email = ? AND used_at IS NULL', [$email]);
            log_action((int)$user['user_id'], 'reset_password', 'Password reset via OTP', strtoupper((string)$user['role'] ?? 'ADOPTER'), (string)$user['username']);
            jsonSuccess([], 'Password reset successful. You can now sign in.');
        } catch (Throwable $e) {
            jsonError('Unable to reset password right now.');
        }
        break;

    case 'logout':
        if (isset($_SESSION['user_id'])) {
            log_action($_SESSION['user_id'], 'logout', 'User logged out');
        }

        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            jsonSuccess([], 'Logged out.');
        } else {
            header('Location: ' . APP_URL . '/index.php');
            exit;
        }
        break;

    default:
        jsonError('Unknown action.', 400);
}
