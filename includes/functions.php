<?php
require_once __DIR__ . '/../config/database.php';

// ─── Session & Auth ─────────────────────────────────────────────────
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn(): bool {
    startSession();
    return isset($_SESSION['user_id']);
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    return $_SESSION['user'] ?? null;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

function requireRole(string $role): void {
    requireLogin();
    $user = getCurrentUser();
    if (!$user || $user['role'] !== $role) {
        header('Location: ' . APP_URL . '/index.php?error=unauthorized');
        exit;
    }
}

function requireAnyRole(array $roles): void {
    requireLogin();
    $user = getCurrentUser();
    if (!$user || !in_array($user['role'], $roles)) {
        header('Location: ' . APP_URL . '/index.php?error=unauthorized');
        exit;
    }
}

function logout(): void {
    startSession();
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

date_default_timezone_set('Asia/Manila'); 
// ─── Security ───────────────────────────────────────────────────────
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateCsrfToken(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    startSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ─── JSON Response ──────────────────────────────────────────────────
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function jsonSuccess(array $data = [], string $message = 'Success'): void {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
}

function jsonError(string $message, int $code = 400): void {
    jsonResponse(['success' => false, 'message' => $message], $code);
}

// ─── File Upload ────────────────────────────────────────────────────
function uploadImage(array $file, string $subdir = 'pets'): ?string {
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($file['type'], $allowed)) return null;
    if ($file['size'] > MAX_FILE_SIZE) return null;

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_', true) . '.' . strtolower($ext);
    $dir = UPLOAD_PATH . $subdir . '/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $path = $dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $path)) {
        return 'uploads/' . $subdir . '/' . $filename;
    }
    return null;
}

// ─── Notifications ──────────────────────────────────────────────────
function createNotification($userId, $type, $title, $message, $link = '') {
    $db = Database::getInstance();
    return $db->query(
        "INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())",
        [$userId, $type, $title, $message, $link]
    );
}
function getUnreadNotificationCount(int $userId): int {
    $db = Database::getInstance();
    $result = $db->fetch("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0", [$userId]);
    return $result ? (int)$result['cnt'] : 0;
}

function getUnreadMessageCount(int $userId): int {
    $db = Database::getInstance();
    $result = $db->fetch(
        "SELECT COUNT(*) as cnt FROM messages WHERE is_read = 0 AND sender_user_id != ?
         AND conversation_id IN (
             SELECT conversation_id FROM conversations
             WHERE adopter_id = ? OR shelter_id = ?
         )",
        [$userId, $userId, $userId]
    );
    return $result ? (int)$result['cnt'] : 0;
}

// ─── Helpers ────────────────────────────────────────────────────────
function formatAge(int $months): string {
    if ($months < 12) return $months . ' month' . ($months !== 1 ? 's' : '');
    $years = floor($months / 12);
    $rem = $months % 12;
    $str = $years . ' year' . ($years !== 1 ? 's' : '');
    if ($rem > 0) $str .= ' ' . $rem . ' mo';
    return $str;
}

function getPetPrimaryPhoto(int $petId): string {
    $db = Database::getInstance();
    $photo = $db->fetch("SELECT photo_url FROM pet_photos WHERE pet_id = ? AND is_primary = 1 LIMIT 1", [$petId]);
    if ($photo) return APP_URL . '/' . $photo['photo_url'];
    $any = $db->fetch("SELECT photo_url FROM pet_photos WHERE pet_id = ? LIMIT 1", [$petId]);
    if ($any) return APP_URL . '/' . $any['photo_url'];
    return APP_URL . '/assets/images/pet-placeholder.png';
}

function timeAgo(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('M j, Y', $time);
}

function statusBadge(string $status): string {
    $colors = [
        'Available'    => 'badge-success',
        'Pending'      => 'badge-warning',
        'Adopted'      => 'badge-info',
        'Removed'      => 'badge-danger',
        'Submitted'    => 'badge-primary',
        'Under Review' => 'badge-warning',
        'Approved'     => 'badge-success',
        'Rejected'     => 'badge-danger',
        'Cancelled'    => 'badge-secondary',
        'PENDING'      => 'badge-warning',
        'APPROVED'     => 'badge-success',
        'REJECTED'     => 'badge-danger',
    ];
    $cls = $colors[$status] ?? 'badge-secondary';
    return "<span class='badge $cls'>$status</span>";
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function flashMessage(string $type, string $msg): void {
    startSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $msg];
}

function getFlash(): ?array {
    startSession();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
?>
<?php
/* ─── Paste at bottom of: includes/functions.php ─── */

function pa_validate_email(string $email, ?string &$err = null): bool {
    $email = strtolower(trim($email));
    if ($email === '' || strlen($email) > 254)            { $err='Email is required.'; return false; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))      { $err='Email format is invalid.'; return false; }
    if (!preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', $email)) {
        $err='Email format is invalid.'; return false;
    }
    [$local, $domain] = explode('@', $email, 2);

    $allowed = trim((string)(getenv('PAWADOPT_ALLOWED_DOMAINS') ?: ''));
    if ($allowed !== '') {
        $list = array_filter(array_map('trim', explode(',', strtolower($allowed))));
        if ($list && !in_array($domain, $list, true)) {
            $err = 'Only emails from approved domains are accepted.'; return false;
        }
    }

    $blacklist = ['mailinator.com','tempmail.com','temp-mail.org','10minutemail.com',
        'guerrillamail.com','yopmail.com','sharklasers.com','trashmail.com',
        'trashmail.de','fakeinbox.com','getnada.com','maildrop.cc',
        'dispostable.com','mintemail.com','spamgourmet.com','mailnesia.com',
        'tempinbox.com','throwawaymail.com','jetable.org','spam4.me','grr.la',
        'mohmal.com','tempemail.com','spambox.us'];
    if (in_array($domain, $blacklist, true)) {
        $err = 'Disposable / temporary email addresses are not allowed.'; return false;
    }

    if (function_exists('checkdnsrr')) { @checkdnsrr($domain, 'MX'); }

    $err = null; return true;
}

function pa_validate_password(string $pw, ?string &$err = null): bool {
    if (strlen($pw) < 8) { $err='Password must be at least 8 characters.'; return false; }
    $classes = 0;
    if (preg_match('/[A-Z]/', $pw))         $classes++;
    if (preg_match('/[a-z]/', $pw))         $classes++;
    if (preg_match('/\d/',    $pw))         $classes++;
    if (preg_match('/[^A-Za-z0-9]/', $pw)) $classes++;
    if ($classes < 3) { $err='Password must include uppercase, lowercase, and a number or symbol.'; return false; }
    $err = null; return true;
}
if (!function_exists('pa_get_csrf')) {
    function pa_get_csrf(): string {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf'];
    }
    function pa_verify_csrf(): bool {
        return ($_POST['csrf'] ?? '') === ($_SESSION['csrf'] ?? '');
    }
}

/* In-app notification helper. If your project already has /api/notifications.php,
   route pa_create_notification through that API instead of touching the table directly. */
if (!function_exists('pa_create_notification')) {
    function pa_create_notification(
        int $userId, string $title, string $body, ?string $link = null, string $type = 'info'
    ): bool {
        try {
            $db = Database::getInstance();
            $db->execute(
                "INSERT INTO notifications (user_id, type, title, body, link_url, is_read)
                 VALUES (?, ?, ?, ?, ?, 0)",
                [$userId, $type, $title, $body, $link]
            );
            log_action($userId, 'notification', "$type: $title");
            return true;
        } catch (Throwable $e) {
            error_log('[pa_notify] ' . $e->getMessage());
            return false;
        }
    }
}
