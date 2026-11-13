<?php
/**
 * PawAdopt — background image upload for the UI customizer.
 *
 * POST api/ui-background.php   (multipart: background=<file>, csrf_token=...)
 *
 * Hardening
 *   • requireLogin() — the request needs a session, not just the page.
 *   • CSRF token required.
 *   • Size cap 4 MB (checked before any decode).
 *   • Real type decided by getimagesize()/finfo magic bytes, never the
 *     browser-supplied Content-Type or the file extension.
 *   • Re-encoded through GD, which strips EXIF/GPS metadata and any embedded
 *     PHP payload, then written with a random 32-hex filename.
 *   • Stored under uploads/ui/ as .jpg/.png/.webp only.
 *   • The old file is removed so a user cannot fill the disk.
 */

require_once __DIR__ . '/../includes/functions.php';
startSession();
requireLogin();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('POST only.', 405);
}

/** Accepts either CSRF convention used across the codebase. */
function pa_csrf_ok(): bool {
    $t = (string) ($_POST['csrf_token'] ?? '');
    if ($t === '') { return false; }
    if (function_exists('verifyCsrfToken') && verifyCsrfToken($t)) { return true; }
    $sess = (string) ($_SESSION['csrf'] ?? '');
    if ($sess !== '' && hash_equals($sess, $t)) { return true; }
    return false;
}

if (!pa_csrf_ok()) {
    jsonError('Invalid CSRF token.', 403);
}
if (!isset($_FILES['background']) || !is_array($_FILES['background'])) {
    jsonError('No image was received.');
}

$f = $_FILES['background'];
if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    jsonError('Upload failed (code ' . (int) $f['error'] . '). Try a smaller image.');
}
if (($f['size'] ?? 0) > 4 * 1024 * 1024) {
    jsonError('Image must be 4 MB or smaller.');
}
$tmp = (string) ($f['tmp_name'] ?? '');
if ($tmp === '' || !is_uploaded_file($tmp)) {
    jsonError('Invalid upload.');
}

// Magic-byte type detection (not the extension, not $_FILES['type']).
$info = @getimagesize($tmp);
$mime = is_array($info) ? ($info['mime'] ?? '') : '';
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($allowed[$mime])) {
    jsonError('Only JPG, PNG or WebP images are allowed.');
}
if (($info[0] ?? 0) < 320 || ($info[1] ?? 0) < 240) {
    jsonError('Image must be at least 320×240 pixels.');
}
if (($info[0] ?? 0) > 6000 || ($info[1] ?? 0) > 6000) {
    jsonError('Image is too large in dimensions (max 6000×6000).');
}

$src = null;
switch ($mime) {
    case 'image/jpeg': $src = @imagecreatefromjpeg($tmp); break;
    case 'image/png':  $src = @imagecreatefrompng($tmp);  break;
    case 'image/webp': $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : null; break;
}
if (!$src) {
    jsonError('That image could not be read. Please try another file.');
}

// Downscale very large backgrounds — keeps free hosting happy.
$maxW = 1920;
$w = imagesx($src);
$h = imagesy($src);
if ($w > $maxW) {
    $nh  = (int) round($h * ($maxW / $w));
    $dst = imagecreatetruecolor($maxW, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxW, $nh, $w, $h);
    imagedestroy($src);
    $src = $dst;
}

$dirFs  = __DIR__ . '/../uploads/ui';
$dirUrl = 'uploads/ui';
if (!is_dir($dirFs) && !@mkdir($dirFs, 0775, true) && !is_dir($dirFs)) {
    imagedestroy($src);
    jsonError('Upload folder is not writable.', 500);
}
// Never execute anything inside the uploads tree.
@file_put_contents($dirFs . '/.htaccess', "php_flag engine off\nOptions -ExecCGI\n<FilesMatch \"\\.(php|phtml|phar)$\">\n    Require all denied\n</FilesMatch>\n");

$name = bin2hex(random_bytes(16));
$ext  = $allowed[$mime];
$path = $dirFs . '/' . $name . '.' . $ext;

$ok = false;
if ($ext === 'jpg') {
    $ok = @imagejpeg($src, $path, 88);
} elseif ($ext === 'png') {
    $ok = @imagepng($src, $path, 7);
} else {
    $ok = function_exists('imagewebp') ? @imagewebp($src, $path, 88) : false;
}
imagedestroy($src);

if (!$ok) {
    jsonError('Could not save the image on the server.', 500);
}
@chmod($path, 0644);

// Remove the previous background so storage does not grow forever.
$user = getCurrentUser();
try {
    $db  = Database::getInstance();
    $row = $db->fetch('SELECT bg_image_url FROM user_preferences WHERE user_id = ?', [(int) $user['user_id']]);
    $old = (string) ($row['bg_image_url'] ?? '');
    if ($old !== '' && preg_match('#^uploads/ui/[a-f0-9]{32}\.(jpg|png|webp)$#', $old)) {
        $oldFs = __DIR__ . '/../' . $old;
        if (is_file($oldFs)) { @unlink($oldFs); }
    }
    $db->execute(
        'UPDATE user_preferences SET bg_image_url = ? WHERE user_id = ?',
        [$dirUrl . '/' . $name . '.' . $ext, (int) $user['user_id']]
    );
    if (function_exists('log_action')) {
        log_action((int) $user['user_id'], 'ui_background_upload', 'user_preferences', (int) $user['user_id']);
    }
} catch (Throwable $e) {
    error_log('PawAdopt ui-background: ' . $e->getMessage());
    jsonError('Image saved but the setting could not be stored.', 500);
}

jsonSuccess(['bg_image_url' => $dirUrl . '/' . $name . '.' . $ext], 'Background image uploaded.');
