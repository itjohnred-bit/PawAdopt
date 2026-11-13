<?php
/**
 * PawAdopt — SIMPLIFIED UI CUSTOMIZATION API
 *
 * GET  api/ui-settings.php                 → current user's settings
 * POST api/ui-settings.php?action=save      → save (CSRF token required)
 *
 * Security / privacy
 *   • requireLogin(): the request itself needs a session, not just the page.
 *   • Ownership is enforced in SQL: UPDATE user_preferences WHERE user_id = ?
 *     so a user can never read or write another user's row (IDOR-safe).
 *   • Strict allowlists for every enum; strict regex for every hex colour and
 *     integer range for blur strength — nothing from the browser is trusted.
 *   • Only a same-origin relative URL produced by api/ui-background.php is
 *     accepted for the background image (no remote/tracker URLs).
 */

require_once __DIR__ . '/../includes/functions.php';
startSession();
requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$user   = getCurrentUser();
$db     = Database::getInstance();
$userId = (int) $user['user_id'];

const PA_UI_FONTS     = ['Nunito', 'Inter', 'Roboto', 'Open Sans', 'Lato', 'Georgia', 'Verdana', 'Courier New'];
const PA_UI_TEXTURES  = ['none', 'dots', 'gradient', 'diagonal', 'grain'];
const PA_UI_FITS      = ['cover', 'contain', 'tile'];

function pa_ui_hex(string $v): bool
{
    return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $v);
}

function pa_ui_safe_image_url(string $url): bool
{
    // Only local uploads written by api/ui-background.php.
    return (bool) preg_match('#^uploads/ui/[a-f0-9]{32}\.(jpg|png|webp)$#', $url);
}

/** Ensure the row exists, then return it with defaults filled in. */
function pa_ui_row(Database $db, int $userId): array
{
    $row = $db->fetch('SELECT * FROM user_preferences WHERE user_id = ?', [$userId]);
    if (!$row) {
        $db->execute(
            'INSERT INTO user_preferences (user_id, theme, accent_color) VALUES (?, ?, ?)',
            [$userId, 'cozy_warmth', '#0d9488']
        );
        $row = $db->fetch('SELECT * FROM user_preferences WHERE user_id = ?', [$userId]);
    }
    $defaults = [
        'bg_color'          => '#134E43',
        'bg_image_url'      => null,
        'bg_blur'           => 0,
        'bg_blur_strength'  => 14,
        'bg_image_fit'      => 'cover',
        'font_family'       => 'Nunito',
        'large_text'        => 0,
        'header_color'      => '#FFFFFF',
        'header_texture'    => 'none',
        'sidebar_color'     => '#FFFFFF',
        'sidebar_texture'   => 'none',
    ];
    foreach ($defaults as $k => $v) {
        if (!array_key_exists($k, $row) || $row[$k] === null || $row[$k] === '') {
            $row[$k] = $v;
        }
    }
    $row['bg_blur']          = (int) $row['bg_blur'];
    $row['large_text']       = (int) $row['large_text'];
    $row['bg_blur_strength'] = (int) $row['bg_blur_strength'];
    return $row;
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

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';

if ($action === 'get') {
    jsonSuccess(['settings' => pa_ui_row($db, $userId)]);
}

if ($action === 'save') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('POST only.', 405);
    }
    if (!pa_csrf_ok()) {
        jsonError('Invalid CSRF token.', 403);
    }

    pa_ui_row($db, $userId); // guarantees the row exists

    $updates = [];
    $params  = [];
    $set     = static function (string $col, $value) use (&$updates, &$params) {
        $updates[] = "`$col` = ?";
        $params[]  = $value;
    };

    if (isset($_POST['bg_color'])) {
        $c = strtoupper(trim((string) $_POST['bg_color']));
        if (!pa_ui_hex($c)) { jsonError('Background colour must be a hex value like #134E43.'); }
        $set('bg_color', $c);
        // mirror the legacy accent column so older pages stay in sync
        $set('accent_color', $c);
    }

    if (isset($_POST['bg_image_url'])) {
        $u = trim((string) $_POST['bg_image_url']);
        if ($u === '') {
            $set('bg_image_url', null);
        } elseif (!pa_ui_safe_image_url($u)) {
            jsonError('Background image must be an uploaded file.');
        } else {
            $set('bg_image_url', $u);
        }
    }

    if (isset($_POST['bg_blur'])) {
        $set('bg_blur', !empty($_POST['bg_blur']) && $_POST['bg_blur'] !== '0' ? 1 : 0);
    }

    if (isset($_POST['bg_blur_strength'])) {
        $b = (int) $_POST['bg_blur_strength'];
        if ($b < 0 || $b > 40) { jsonError('Blur strength must be between 0 and 40.'); }
        $set('bg_blur_strength', $b);
    }

    if (isset($_POST['bg_image_fit'])) {
        $f = strtolower(trim((string) $_POST['bg_image_fit']));
        if (!in_array($f, PA_UI_FITS, true)) { jsonError('Invalid image fit.'); }
        $set('bg_image_fit', $f);
    }

    if (isset($_POST['font_family'])) {
        $f = trim((string) $_POST['font_family']);
        if (!in_array($f, PA_UI_FONTS, true)) { jsonError('Invalid font family.'); }
        $set('font_family', $f);
    }

    if (isset($_POST['large_text'])) {
        $set('large_text', !empty($_POST['large_text']) && $_POST['large_text'] !== '0' ? 1 : 0);
    }

    if (isset($_POST['header_color'])) {
        $h = strtoupper(trim((string) $_POST['header_color']));
        if (!pa_ui_hex($h)) { jsonError('Header colour must be a hex value.'); }
        $set('header_color', $h);
    }
    if (isset($_POST['header_texture'])) {
        $t = strtolower(trim((string) $_POST['header_texture']));
        if (!in_array($t, PA_UI_TEXTURES, true)) { jsonError('Invalid header texture.'); }
        $set('header_texture', $t);
    }
    if (isset($_POST['sidebar_color'])) {
        $s = strtoupper(trim((string) $_POST['sidebar_color']));
        if (!pa_ui_hex($s)) { jsonError('Sidebar colour must be a hex value.'); }
        $set('sidebar_color', $s);
    }
    if (isset($_POST['sidebar_texture'])) {
        $t = strtolower(trim((string) $_POST['sidebar_texture']));
        if (!in_array($t, PA_UI_TEXTURES, true)) { jsonError('Invalid sidebar texture.'); }
        $set('sidebar_texture', $t);
    }

    if (!$updates) {
        jsonSuccess(['settings' => pa_ui_row($db, $userId)], 'Nothing to update.');
    }

    // Ownership enforced in SQL — the session user is the only possible target.
    $params[] = $userId;
    $db->execute(
        'UPDATE user_preferences SET ' . implode(', ', $updates) . ' WHERE user_id = ?',
        $params
    );

    if (function_exists('log_action')) {
        log_action($userId, 'ui_settings_update', 'user_preferences', $userId, null, ['fields' => count($updates)]);
    }

    jsonSuccess(['settings' => pa_ui_row($db, $userId)], 'Appearance saved.');
}

jsonError('Unknown action.', 400);
