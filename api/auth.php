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
    $dir  = match ($role) {
        'VETERINARY', 'SHELTER' => 'shelter',
        'ADMIN'                => 'admin',
        default                => 'adopter',
    };
    return APP_URL . "/pages/{$dir}/dashboard.php";
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
        handleLogin($db, $pdo);
        break;

    case 'register':
        handleRegister($db, $pdo);
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

function handleLogin(Database $db, PDO $pdo): void {
    $username  = trim((string)($_POST['username'] ?? ''));
    $password  = (string)($_POST['password'] ?? '');
    $roleInput = strtoupper(trim((string)($_POST['role'] ?? '')));

    if ($username === '' || $password === '' || $roleInput === '') {
        fail('All fields are required.', 400);
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

    if (strtoupper($user['role']) !== $roleInput) {
        fail('Incorrect role selection for this account.', 403);
    }

    session_regenerate_id(true);
    $_SESSION['user_id']   = (int)$user['user_id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['role']      = strtoupper($user['role']);
    $_SESSION['last_seen'] = time();

    log_action((int)$user['user_id'], 'login', 'Successful login');

    jsonSuccess(['redirect' => redirectForRole($user['role'])], 'Login successful!');
}

function handleRegister(Database $db, PDO $pdo): void {
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
        } else {
            $defaultName = $username . "'s "
                         . ($role === 'VETERINARY' ? 'Clinic' : 'Shelter');
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
        fail('Invalid email format.', 400);
    }

    $user = $db->fetch(
        "SELECT user_id, username FROM users WHERE email = ? AND is_active = 1",
        [$email]
    );

    if (is_array($user) && !empty($user)) {
        try {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            $db->execute(
                "INSERT INTO password_resets (user_id, token_hash, expires_at)
                      VALUES (?, ?, ?)",
                [(int)$user['user_id'], hash('sha256', $token), $expires]
            );
            $link = APP_URL . '/pages/reset.php?token=' . $token;
            mailer_send(
                $email,
                'Reset your PawAdopt password',
                "Hi {$user['username']},\n\n"
                . "Click here to reset (expires in 1 hour):\n{$link}\n\n"
                . "If you didn't request this, ignore this email."
            );
        } catch (Throwable $e) {
            error_log('Forgot-password email failed: ' . $e->getMessage());
        }
    }

    jsonSuccess([], 'If that address exists, a reset link has been sent.');
}
