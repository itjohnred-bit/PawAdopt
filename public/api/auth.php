<?php
declare(strict_types=1);

require __DIR__ . '/../../config/email.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/functions_audit.php';
require __DIR__ . '/../../includes/mailer.php';
startSession();

$db     = Database::getInstance();
$pdo    = $db->getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function refreshSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(false);
    }
}

function redirectForRole(string $role): string {
    $role = strtoupper($role);
    $map  = [
        'VETERINARY' => 'vet',
        'SHELTER'    => 'shelter',
        'ADMIN'      => 'admin',
        'ADOPTER'    => 'adopter',
    ];
    $dir = $map[$role] ?? 'adopter';
    
    $baseUrl = rtrim((string)getenv('APP_URL'), '/');
    return $baseUrl . "/pages/{$dir}/dashboard.php";
}

function fail(string $msg, int $code = 400): never {
    jsonError($msg, $code);
    exit;
}

if ($method !== 'POST') {
    fail('Method not allowed', 405);
}

switch ($action) {
    case 'login':
        handleLogin($pdo, $db);
        break;

    case 'register':
        handleRegister($pdo, $db);
        break;

    case 'logout':
        handleLogout();
        break;

    case 'forgot':
        handleForgot($db);
        break;

    default:
        fail('Unknown action.', 400);
}

function handleLogin(PDO $pdo, Database $db): void {
    $username  = trim((string)($_POST['username'] ?? ''));
    $password  = (string)($_POST['password'] ?? '');
    $roleInput = strtoupper(trim((string)($_POST['role'] ?? '')));

    $knownRoles = ['ADOPTER', 'VETERINARY', 'SHELTER', 'ADMIN'];
    if ($username === '' || $password === '' || $roleInput === '') {
        fail('All fields are required.', 400);
    }
    if (!in_array($roleInput, $knownRoles, true)) {
        fail('Unknown role selection.', 400);
    }

    try {
        $user = $db->fetch(
            "SELECT user_id, username, role, password_hash, is_active
               FROM users
              WHERE (username = ? OR email = ?)
                AND is_active = 1
              LIMIT 1",
            [$username, $username]
        );
    } catch (Throwable $e) {
        error_log('Login lookup failed: ' . $e->getMessage());
        fail('Server error during login.', 500);
    }

    if (!$user || !password_verify($password, $user['password_hash'])) {
        usleep(random_int(50_000, 150_000));
        fail('Invalid credentials.', 401);
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
        $newHash = password_hash($password, PASSWORD_BCRYPT);
        $db->execute(
            "UPDATE users SET password_hash = ? WHERE user_id = ?",
            [$newHash, $user['user_id']]
        );
    }

    $userRole = strtoupper((string)$user['role']);
    if (!in_array($userRole, $knownRoles, true)) {
        error_log("Login role unknown for user_id={$user['user_id']}: {$user['role']}");
        fail('Account misconfigured. Contact support.', 500);
    }
    if ($userRole !== $roleInput) {
        fail('Incorrect role selection for this account.', 403);
    }

    session_regenerate_id(true);
    $_SESSION['user_id']   = (int)$user['user_id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['role']      = $userRole;
    $_SESSION['last_seen'] = time();

    log_action((int)$user['user_id'], 'login', 'Successful login');

    jsonSuccess(['redirect' => redirectForRole($userRole)], 'Login successful!');
}

function handleRegister(PDO $pdo, Database $db): void {
    $username = trim((string)($_POST['username'] ?? ''));
    $email    = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $role     = strtoupper(trim((string)($_POST['role'] ?? 'ADOPTER')));

    $allowedRoles = ['ADOPTER', 'SHELTER', 'VETERINARY'];
    if (!in_array($role, $allowedRoles, true)) fail('Invalid role supplied.', 400);
    if (strlen($username) < 3 || strlen($username) > 32) fail('Username must be 3–32 characters.', 400);
    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $username)) fail('Username has invalid characters.', 400);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Invalid email format.', 400);
    if (strlen($password) < 8) fail('Password must be at least 8 characters.', 400);

    try {
        if (!$pdo->beginTransaction()) {
            throw new RuntimeException('Could not start DB transaction.');
        }

        $exists = $db->fetch(
            "SELECT user_id FROM users WHERE username = ? OR email = ? LIMIT 1",
            [$username, $email]
        );
        if (is_array($exists) && !empty($exists)) {
            $pdo->rollBack();
            fail('That username or email is already taken.', 409);
        }

        $hash   = password_hash($password, PASSWORD_BCRYPT);
        $insert = $pdo->prepare(
            "INSERT INTO users (role, username, email, password_hash, is_active, created_at)
                  VALUES (?, ?, ?, ?, 1, CURRENT_TIMESTAMP)"
        );
        $insert->execute([$role, $username, $email, $hash]);
        $userId = (int)$pdo->lastInsertId();
        if ($userId <= 0) throw new RuntimeException('Insert returned no ID.');

        if ($role === 'ADOPTER') {
            $pdo->prepare("INSERT INTO adopter_profiles (adopter_id) VALUES (?)")
                ->execute([$userId]);
        } elseif ($role === 'SHELTER') {
            $defaultName = $username . "'s Shelter";
            $pdo->prepare("INSERT INTO shelter_profiles (shelter_id, shelter_name) VALUES (?, ?)")
                ->execute([$userId, $defaultName]);
            $pdo->prepare("INSERT INTO shelter_verifications (shelter_id, status) VALUES (?, 'PENDING')")
                ->execute([$userId]);
        } elseif ($role === 'VETERINARY') {
            $defaultName = $username . "'s Clinic";
            $pdo->prepare("INSERT INTO shelter_profiles (shelter_id, shelter_name) VALUES (?, ?)")
                ->execute([$userId, $defaultName]);
            $pdo->prepare("INSERT INTO shelter_verifications (shelter_id, status) VALUES (?, 'PENDING')")
                ->execute([$userId]);
        }

        $pdo->commit();

        session_regenerate_id(true);
        $_SESSION['user_id']   = $userId;
        $_SESSION['username']  = $username;
        $_SESSION['role']      = $role;
        $_SESSION['last_seen'] = time();

        log_action($userId, 'register', "New account ({$role})");

        jsonSuccess(
            ['redirect' => redirectForRole($role)],
            'Account created!'
        );

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Registration failed: ' . $e->getMessage());
        fail('Registration failed. Please try again later.', 500);
    }
}

function handleLogout(): void {
    $sessionName = session_name();

    if (!empty($_SESSION['user_id'])) {
        log_action((int)$_SESSION['user_id'], 'logout', 'User logged out');
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            $sessionName,
            '',
            [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'] ?? false,
                'httponly' => $params['httponly'] ?? true,
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }
    session_destroy();
    jsonSuccess([], 'Logged out.');
}

function handleForgot(Database $db): void {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail('Please enter a valid email address.', 400);
    }

    $user = $db->fetch(
        "SELECT user_id, username, email FROM users WHERE email = ? AND is_active = 1 LIMIT 1",
        [$email]
    );

    if (!is_array($user) || empty($user)) {
        jsonSuccess([
            'state'   => 'unknown',
            'message' => 'No account is registered with that email.',
        ], 'No account found.');
        return;
    }

    $throttleSeconds = 60;
    $lastReset = $db->fetch(
        "SELECT created_at, expires_at, used_at
           FROM password_resets
          WHERE user_id = ?
          ORDER BY created_at DESC
          LIMIT 1",
        [(int)$user['user_id']]
    );

    if (is_array($lastReset) && !empty($lastReset)) {
        $createdTs   = strtotime((string)$lastReset['created_at']);
        $now         = time();
        $alreadyUsed = !empty($lastReset['used_at']);

        if (!$alreadyUsed
            && (int)$lastReset['expires_at'] > $now
            && ($now - $createdTs) < $throttleSeconds
        ) {
            $remaining = max(1, $throttleSeconds - ($now - $createdTs));
            jsonSuccess([
                'state'    => 'throttled',
                'message'  => 'A reset link was already sent recently. Please check your inbox (and spam folder).',
                'retry_in' => $remaining,
            ], 'Reset link recently sent.');
            return;
        }
    }

    $token  = bin2hex(random_bytes(32));
    $hash   = hash('sha256', $token);
    $now    = date('Y-m-d H:i:s');
    $expiry = date('Y-m-d H:i:s', time() + 3600);
    $ip     = clientIp();
    $agent  = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    try {
        $db->execute(
            "INSERT INTO password_resets (user_id, token_hash, expires_at, ip_address, user_agent, created_at)
                  VALUES (?, ?, ?, ?, ?, ?)",
            [(int)$user['user_id'], $hash, $expiry, $ip, $agent, $now]
        );
    } catch (Throwable $e) {
        error_log('handleForgot persistence failed: ' . $e->getMessage());
        fail('Could not process request. Please try again.', 500);
        return;
    }

    $link = APP_URL . '/pages/reset.php?token=' . urlencode($token);
    $sent = mailer_send(
        (string)$user['email'],
        'Reset your PawAdopt password',
        "Hi {$user['username']},\n\n"
        . "We received a request to reset the password for your PawAdopt account.\n\n"
        . "Click here within the next hour to choose a new password:\n"
        . "{$link}\n\n"
        . "If you didn't request this, you can safely ignore this email and your password will remain unchanged.\n\n"
        . "— The PawAdopt team"
    );

    $resp = [
        'state'   => 'sent',
        'message' => "We've sent a reset link to " . $user['email'] . ". Check your inbox (and spam folder).",
        'sent_ok' => $sent,
    ];

    if (!$sent) {
        $resp['message'] .= " If it doesn't arrive in a few minutes, please contact support.";
    }

    jsonSuccess($resp, $sent ? 'Reset link sent.' : 'Reset requested.');
}

function clientIp(): ?string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', (string)$_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return null;
}