<?php
/**
 * GET  api/ui-settings.php                → current user's UI customization
 * POST api/ui-settings.php  (action=save) → update current user's UI customization
 *
 * Server-side enforcement: every request requires a logged-in user and the
 * query is always scoped to that user's own row (auth-equivalent of RLS:
 * users can only read/change their OWN preferences row).
 *
 * Theme keys: pixel | minimalist | greenish_minimalist | cozy_warmth
 * Requires the columns added by sql/ui_customization.sql.
 */

require_once __DIR__ . '/../includes/functions.php';
startSession();
requireLogin(); // data requests themselves require a logged-in user

header('Content-Type: application/json; charset=UTF-8');

$user = getCurrentUser();
$db   = Database::getInstance();
$userId = (int) $user['user_id'];

const UI_THEMES = ['pixel', 'minimalist', 'greenish_minimalist', 'cozy_warmth'];
const UI_FONTS  = ['Arial', 'Inter', 'Roboto', 'Open Sans', 'Courier Prime', 'Pixelify', 'Nunito'];
const UI_SIZES  = ['7pt', '8pt', '9pt', '10pt'];
const UI_SCALES = [1.10, 1.25, 1.40];
const UI_RADII  = [0, 4, 8, 16];
const UI_SHADOWS = ['none', 'soft', 'hard', 'retro'];
const UI_DENSITY = ['compact', 'standard', 'detailed'];

$themeDefaults = [
    'pixel'              => ['accent' => '#5865F2', 'radius' => 0,  'shadow' => 'retro'],
    'minimalist'         => ['accent' => '#111111', 'radius' => 4,  'shadow' => 'none'],
    'greenish_minimalist'=> ['accent' => '#4A7C59', 'radius' => 8,  'shadow' => 'soft'],
    'cozy_warmth'        => ['accent' => '#E07A5F', 'radius' => 16, 'shadow' => 'soft'],
];

function pa_valid_hex(string $v): bool {
    return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $v);
}

function pa_valid_rgba(string $v): bool {
    return (bool) preg_match('/^rgba\(\s*(0|1?\d{1,2}|2[0-4]\d|25[0-5])\s*,\s*(0|1?\d{1,2}|2[0-4]\d|25[0-5])\s*,\s*(0|1?\d{1,2}|2[0-4]\d|25[0-5])\s*,\s*(0|0?\.\d+|1)\s*\)$/', $v);
}

/** Ensure the user has a preferences row; returns it. */
function pa_get_prefs(Database $db, int $userId, array $themeDefaults): array {
    $row = $db->fetch('SELECT * FROM user_preferences WHERE user_id = ?', [$userId]);
    if (!$row) {
        $db->execute(
            'INSERT INTO user_preferences (user_id, theme, accent_color) VALUES (?, ?, ?)',
            [$userId, 'cozy_warmth', '#0d9488']
        );
        $row = $db->fetch('SELECT * FROM user_preferences WHERE user_id = ?', [$userId]);
    }
    // Sensible defaults for customization columns on old rows.
    $theme = in_array($row['ui_theme'] ?? '', ['pixel','minimalist','greenish_minimalist','cozy_warmth'], true)
        ? $row['ui_theme'] : 'cozy_warmth';
    $row['ui_theme']         = $theme;
    $row['accent_color_hex'] = $row['accent_color_hex'] ?: $themeDefaults[$theme]['accent'];
    $row['font_family']      = $row['font_family']      ?: 'Nunito';
    $row['base_font_size']   = $row['base_font_size']   ?: '8pt';
    $row['header_scale']     = (float)($row['header_scale'] ?: 1.25);
    $row['modal_bg_color']   = $row['modal_bg_color']   ?: '#FFFFFF';
    $row['overlay_backdrop'] = $row['overlay_backdrop'] ?: 'rgba(0, 0, 0, 0.5)';
    $row['border_radius_px'] = (int)($row['border_radius_px'] ?? 16);
    $row['shadow_elevation'] = $row['shadow_elevation'] ?: 'soft';
    $row['grid_density']     = $row['grid_density']     ?: 'standard';
    foreach (['show_contact_phone','show_contact_email','show_contact_address','notif_badges'] as $f) {
        $row[$f] = (int)($row[$f] ?? 1);
    }
    foreach (['high_contrast','disable_animations'] as $f) {
        $row[$f] = (int)($row[$f] ?? 0);
    }
    return $row;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';

if ($action === 'get') {
    jsonSuccess(pa_get_prefs($db, $userId, $themeDefaults));
}

if ($action === 'save') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('POST only.', 405);
    }

    $prefs = pa_get_prefs($db, $userId, $themeDefaults); // ownership: UPDATE ... WHERE user_id = ?

    $updates = [];
    $params  = [];

    $set = static function (string $col, $value) use (&$updates, &$params) {
        $updates[] = "$col = ?";
        $params[]  = $value;
    };

    // Validate against strict formats / enum values before touching the DB.
    if (isset($_POST['ui_theme'])) {
        $t = strtolower(trim($_POST['ui_theme']));
        if (!in_array($t, UI_THEMES, true))    jsonError('Invalid theme.');
        $set('ui_theme', $t);
        $d = $themeDefaults[$t];
        if (!isset($_POST['accent_color_hex'])) { $set('accent_color_hex', $d['accent']); }
        if (!isset($_POST['border_radius_px'])) { $set('border_radius_px', $d['radius']); }
        if (!isset($_POST['shadow_elevation'])) { $set('shadow_elevation', $d['shadow']); }
    }
    if (isset($_POST['accent_color_hex'])) {
        $a = strtoupper(trim($_POST['accent_color_hex']));
        if (!pa_valid_hex($a)) jsonError('Accent color must be a hex value like #5865F2.');
        $set('accent_color_hex', $a);
        $set('accent_color', $a); // keep legacy column in sync
    }
    if (isset($_POST['font_family'])) {
        $f = trim($_POST['font_family']);
        if (!in_array($f, UI_FONTS, true)) jsonError('Invalid font family.');
        $set('font_family', $f);
    }
    if (isset($_POST['base_font_size'])) {
        $s = trim($_POST['base_font_size']);
        if (!in_array($s, UI_SIZES, true)) jsonError('Invalid base font size.');
        $set('base_font_size', $s);
    }
    if (isset($_POST['header_scale'])) {
        $h = round((float) $_POST['header_scale'], 2);
        if (!in_array($h, UI_SCALES)) jsonError('Header scale must be 1.10, 1.25, or 1.40.');
        $set('header_scale', $h);
    }
    if (isset($_POST['modal_bg_color'])) {
        $m = strtoupper(trim($_POST['modal_bg_color']));
        if (!pa_valid_hex($m)) jsonError('Modal background must be a hex value.');
        $set('modal_bg_color', $m);
    }
    if (isset($_POST['overlay_backdrop'])) {
        $o = trim($_POST['overlay_backdrop']);
        if (!pa_valid_rgba($o)) jsonError('Backdrop must be an rgba() value, e.g. rgba(0, 0, 0, 0.5).');
        $set('overlay_backdrop', $o);
    }
    if (isset($_POST['border_radius_px'])) {
        $r = (int) $_POST['border_radius_px'];
        if (!in_array($r, UI_RADII, true)) jsonError('Border radius must be 0, 4, 8, or 16.');
        $set('border_radius_px', $r);
    }
    if (isset($_POST['shadow_elevation'])) {
        $s = strtolower(trim($_POST['shadow_elevation']));
        if (!in_array($s, UI_SHADOWS, true)) jsonError('Invalid shadow elevation.');
        $set('shadow_elevation', $s);
    }
    if (isset($_POST['grid_density'])) {
        $g = strtolower(trim($_POST['grid_density']));
        if (!in_array($g, UI_DENSITY, true)) jsonError('Invalid grid density.');
        $set('grid_density', $g);
    }
    foreach (['show_contact_phone','show_contact_email','show_contact_address','notif_badges','high_contrast','disable_animations'] as $flag) {
        if (isset($_POST[$flag])) {
            $set($flag, !empty($_POST[$flag]) ? 1 : 0);
        }
    }

    if (empty($updates)) {
        jsonSuccess($prefs, 'Nothing to update.');
    }

    // Ownership enforced server-side: WHERE user_id = :session_user
    $params[] = $userId;
    $db->execute(
        'UPDATE user_preferences SET ' . implode(', ', $updates) . ' WHERE user_id = ?',
        $params
    );

    jsonSuccess(pa_get_prefs($db, $userId, $themeDefaults), 'Customization saved.');
}

jsonError('Unknown action.', 400);
