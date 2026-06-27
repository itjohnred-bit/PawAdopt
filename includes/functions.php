<?php
require_once __DIR__ . '/../config/database.php';

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
        $baseUrl = rtrim(APP_URL, '/');
        header('Location: ' . $baseUrl . '/index.php');
        exit;
    }
}

function requireRole(string $role): void {
    requireLogin();
    $user = getCurrentUser();
    if (!$user || $user['role'] !== $role) {
        $baseUrl = rtrim(APP_URL, '/');
        header('Location: ' . $baseUrl . '/index.php?error=unauthorized');
        exit;
    }
}

function requireAnyRole(array $roles): void {
    requireLogin();
    $user = getCurrentUser();
    if (!$user || !in_array($user['role'], $roles)) {
        $baseUrl = rtrim(APP_URL, '/');
        header('Location: ' . $baseUrl . '/index.php?error=unauthorized');
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
    $baseUrl = rtrim(APP_URL, '/');
    header('Location: ' . $baseUrl . '/index.php');
    exit;
}

date_default_timezone_set('Asia/Manila'); 
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

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function jsonSuccess(array $data = [], string $message = 'Success'): void {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
}

function jsonError($message, $code = 400) {
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}
if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 5242880); 
}


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

function uploadImageToAiven($fileField) {
    if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $imgBinaryData = file_get_contents($_FILES[$fileField]['tmp_name']);
    
    return $imgBinaryData;
}
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
             WHERE adopter_id = ? OR veterinary_id = ?
         )",
        [$userId, $userId, $userId]
    );
    return $result ? (int)$result['cnt'] : 0;
}

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
    $baseUrl = rtrim(APP_URL, '/');
    if ($photo) return $baseUrl . '/' . $photo['photo_url'];
    $any = $db->fetch("SELECT photo_url FROM pet_photos WHERE pet_id = ? LIMIT 1", [$petId]);
    if ($any) return $baseUrl . '/' . $any['photo_url'];
    return $baseUrl . '/assets/images/pet-placeholder.png';
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