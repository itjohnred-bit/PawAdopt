<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireLogin();
$currentUser = getCurrentUser();

$unreadNotifs = getUnreadNotificationCount($currentUser['user_id']);
$unreadMsgs   = getUnreadMessageCount($currentUser['user_id']);

$role = $currentUser['role'];
$basePath = APP_URL;

// Presentation: resolve the user's profile picture (adopter avatar /
// shelter logo) so the header icon shows the real photo when set.
$paAvatarUrl = '';
try {
    $paDb = Database::getInstance();
    if ($role === 'ADOPTER') {
        $paRow = $paDb->fetch('SELECT avatar_url FROM adopter_profiles WHERE adopter_id = ?', [$currentUser['user_id']]);
        $paAvatarUrl = (string)($paRow['avatar_url'] ?? '');
    } elseif ($role === 'SHELTER' || $role === 'VETERINARY') {
        $paRow = $paDb->fetch('SELECT logo_url FROM shelter_profiles WHERE shelter_id = ?', [$currentUser['user_id']]);
        $paAvatarUrl = (string)($paRow['logo_url'] ?? '');
    }
} catch (Throwable $e) { $paAvatarUrl = ''; }

require_once __DIR__ . '/ui-runtime.php';
$paUiSettings = pa_ui_runtime_settings((int) ($currentUser['user_id'] ?? 0));
$paUiCss      = pa_ui_runtime_css($paUiSettings);
$paUiDarkHeader = !empty($paUiSettings['header_color']) && pa_ui_is_dark_hex((string) $paUiSettings['header_color']);

$current_page = basename($_SERVER['PHP_SELF']);

// Presentation-only: pick a green-theme variant per page (deep = dashboards,
// mint = messages/notifications, sage = everything else). Admin pages never
// receive pa-restyle, so they are unaffected.
$paPageKey = strtolower(pathinfo($current_page, PATHINFO_FILENAME));
if ($paPageKey === 'dashboard') {
    $paVariant = 'deep';
} elseif (in_array($paPageKey, ['messages', 'notifications'], true)) {
    $paVariant = 'mint';
} else {
    $paVariant = 'sage';
}

if ($role === 'ADOPTER') {
    $dashUrl    = "$basePath/pages/adopter/dashboard.php";
    $browseUrl  = "$basePath/pages/adopter/browse.php";
    $favUrl     = "$basePath/pages/adopter/favorites.php";
    $appsUrl    = "$basePath/pages/adopter/applications.php";
    $msgUrl     = "$basePath/pages/adopter/messages.php";
    $profileUrl = "$basePath/pages/adopter/profile.php";
    $notifUrl   = "$basePath/pages/adopter/notifications.php";
} elseif ($role === 'SHELTER') {
    $dashUrl    = "$basePath/pages/shelter/dashboard.php";
    $petsUrl    = "$basePath/pages/shelter/pets.php";
    $appsUrl    = "$basePath/pages/shelter/applications.php";
    $msgUrl     = "$basePath/pages/shelter/messages.php";
    $profileUrl = "$basePath/pages/shelter/profile.php";
    $notifUrl   = "$basePath/pages/shelter/notifications.php";
} else {
    $dashUrl      = "$basePath/pages/admin/dashboard.php";
    $usersUrl     = "$basePath/pages/admin/users.php";
    $sheltersUrl  = "$basePath/pages/admin/shelters.php";
    $petsAdmUrl   = "$basePath/pages/admin/pets.php";
    $reportsUrl   = "$basePath/pages/admin/reports.php";
    $auditLogsUrl = "$basePath/pages/admin/audit-logs.php";
    $profileUrl   = "$basePath/pages/admin/dashboard.php";
    $notifUrl     = '#';
    $msgUrl       = '#';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' – ' . APP_NAME : APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Pacifico&family=Alfa+Slab+One&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/dark-mode.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/layout-fix.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/user-theme.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= APP_URL ?>/assets/js/ui-customization.js"></script>

    <style>
        :root {
            --sidebar-bg: #ffffff;
            --active-bg: #e9f5f2;
            --active-text: #0d6d5d;
            --text-color: #555555;
            --sidebar-width: 260px;
        }

        body { margin: 0; font-family: 'Nunito', sans-serif; background: #f4f7f6; overflow-x: hidden; }

        .admin-wrapper { display: flex; min-height: 100vh; width: 100%; }

        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            border-right: 1px solid #eeeeee;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            z-index: 1000;
        }

        .sidebar-brand {
            padding: 30px 25px;
            font-size: 24px;
            font-weight: 800;
            color: var(--active-text);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-nav { flex: 1; display: flex; flex-direction: column; padding: 10px; }

        .nav-item {
            padding: 12px 20px;
            text-decoration: none;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 15px;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: 0.3s;
            font-weight: 600;
        }

        .nav-item i { width: 20px; text-align: center; font-size: 18px; }
        .nav-item:hover { background: #f8f9fa; color: #000; }

        .nav-item.active {
            background: var(--active-bg);
            color: var(--active-text);
            border-left: 4px solid var(--active-text);
            border-radius: 0 8px 8px 0;
        }

        .top-header {
            position: fixed;
            top: 0;
            right: 0;
            width: calc(100% - var(--sidebar-width));
            height: 70px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 0 30px;
            border-bottom: 1px solid #eee;
            z-index: 999;
            box-sizing: border-box;
        }

        .header-actions { display: flex; align-items: center; gap: 20px; }

        .icon-btn {
            position: relative;
            background: none;
            border: none;
            font-size: 20px;
            color: #666;
            cursor: pointer;
            text-decoration: none;
        }

        .badge-dot {
            position: absolute; top: -5px; right: -5px;
            background: #ff4d4d; color: #fff; font-size: 10px;
            padding: 2px 5px; border-radius: 10px; font-weight: bold;
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            margin-top: 70px;
            padding: 30px;
            width: calc(100% - var(--sidebar-width));
            min-height: calc(100vh - 70px);
            box-sizing: border-box;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 20px;
        }

        @media (max-width: 1100px) {
            .grid-2 { grid-template-columns: 1fr; }
        }

        .user-pill {
            display: flex; align-items: center; gap: 10px;
            background: #f0f0f0; padding: 5px 15px; border-radius: 30px;
            cursor: pointer; font-weight: 600; font-size: 14px;
        }

        .avatar-circle-sm {
            width: 30px; height: 30px; background: var(--active-text);
            color: #fff; border-radius: 50%; display: flex;
            align-items: center; justify-content: center; font-size: 12px;
        }
    </style>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/restyle.css?v=4">
</head>
<body class="<?= ($role === 'SHELTER' || $role === 'ADOPTER') ? 'pa-restyle theme-' . $paVariant . ' pa-page-' . $paPageKey : '' ?> pa-user-theme<?= !empty($paUiSettings['bg_image_url']) ? ' has-bg-image' : '' ?><?= !empty($paUiSettings['bg_blur']) ? ' blur-on' : '' ?><?= !empty($paUiSettings['large_text']) ? ' large-text' : '' ?><?= $paUiDarkHeader ? ' dark-header' : '' ?>" data-header-texture="<?= htmlspecialchars((string) $paUiSettings['header_texture'], ENT_QUOTES, 'UTF-8') ?>" data-sidebar-texture="<?= htmlspecialchars((string) $paUiSettings['sidebar_texture'], ENT_QUOTES, 'UTF-8') ?>">
<style id="paUserTheme">:root{<?= $paUiCss ?>}</style>

<div class="admin-wrapper">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <span class="pa-brand-paw"><i class="fas fa-paw"></i></span> <span class="pa-brand-text"><span class="pa-paw-word">Paw</span><span class="pa-adopt-word">adopt</span><i class="fas fa-paw pa-paw-ico"></i></span>
        </div>

        <nav class="sidebar-nav">
            <?php if ($role === 'ADOPTER'): ?>
                <a href="<?= $basePath ?>/index.php" class="nav-item"><i class="fas fa-house"></i> Home</a>
                <a href="<?= $basePath ?>/pages/community.php" class="nav-item <?= $current_page == 'community.php' ? 'active' : '' ?>"><i class="fas fa-users-rectangle"></i> Community</a>

                <a href="<?= $dashUrl ?>" class="nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>"><i class="fas fa-home"></i> Dashboard</a>
                <a href="<?= $browseUrl ?>" class="nav-item <?= $current_page == 'browse.php' ? 'active' : '' ?>"><i class="fas fa-search"></i> Browse</a>
                <a href="<?= $favUrl ?>" class="nav-item <?= $current_page == 'favorites.php' ? 'active' : '' ?>"><i class="fas fa-heart"></i> Favorites</a>
                <a href="<?= $appsUrl ?>" class="nav-item <?= $current_page == 'applications.php' ? 'active' : '' ?>"><i class="fas fa-file-alt"></i> My Applications</a>
                <a href="<?= $basePath ?>/pages/customize.php" class="nav-item <?= $current_page == 'customize.php' ? 'active' : '' ?>"><i class="fas fa-palette"></i> Customize UI</a>
            <?php elseif ($role === 'SHELTER'): ?>
                <a href="<?= $basePath ?>/index.php" class="nav-item"><i class="fas fa-house"></i> Home</a>
                <a href="<?= $basePath ?>/pages/community.php" class="nav-item <?= $current_page == 'community.php' ? 'active' : '' ?>"><i class="fas fa-users-rectangle"></i> Community</a>

                <a href="<?= $dashUrl ?>" class="nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="<?= $petsUrl ?>" class="nav-item <?= $current_page == 'pets.php' ? 'active' : '' ?>"><i class="fas fa-paw"></i> My Pets</a>
                <a href="<?= $appsUrl ?>" class="nav-item <?= $current_page == 'applications.php' ? 'active' : '' ?>"><i class="fas fa-file-alt"></i> Applications</a>
                <a href="<?= $basePath ?>/pages/customize.php" class="nav-item <?= $current_page == 'customize.php' ? 'active' : '' ?>"><i class="fas fa-palette"></i> Customize UI</a>
            <?php else: ?>
                <a href="<?= $dashUrl ?>" class="nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="<?= $basePath ?>/index.php" class="nav-item"><i class="fas fa-house"></i> Home</a>
                <a href="<?= $basePath ?>/pages/community.php" class="nav-item <?= $current_page == 'community.php' ? 'active' : '' ?>"><i class="fas fa-users-rectangle"></i> Community</a>

                <a href="<?= $usersUrl ?>" class="nav-item <?= $current_page == 'users.php' ? 'active' : '' ?>"><i class="fas fa-users"></i> Users</a>
                <a href="<?= $sheltersUrl ?>" class="nav-item <?= $current_page == 'shelters.php' ? 'active' : '' ?>"><i class="fas fa-user-shield"></i> Verification <span id="paVetPend"></span></a>

                <script>
                /* Small badge: how many vet/shelter accounts await approval */
                (function () {
                    var el = document.getElementById('paVetPend');
                    if (!el) return;
                    fetch('<?= APP_URL ?>/api/admin.php?action=pending_count')
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            var n = (j && j.data && j.data.count) || 0;
                            if (n > 0) {
                                el.textContent = ' ' + n;
                                el.style.cssText = 'background:#dc2626;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;font-weight:800;margin-left:4px';
                            }
                        })
                        .catch(function () {});
                })();
                </script>
 
                <a href="<?= $petsAdmUrl ?>" class="nav-item <?= $current_page == 'pets.php' ? 'active' : '' ?>"><i class="fas fa-paw"></i> Pets</a>
                <a href="<?= $reportsUrl ?>" class="nav-item <?= $current_page == 'reports.php' ? 'active' : '' ?>"><i class="fas fa-chart-bar"></i> Reports</a>
                <a href="<?= $auditLogsUrl ?>" class="nav-item <?= $current_page == 'audit-logs.php' ? 'active' : '' ?>"><i class="fas fa-history"></i> Audit Logs</a>
                <a href="<?= $basePath ?>/pages/customize.php" class="nav-item <?= $current_page == 'customize.php' ? 'active' : '' ?>"><i class="fas fa-palette"></i> Customize UI</a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-nav" style="flex: 0; border-top: 1px solid #eee;">
            <a href="<?= $basePath ?>/api/auth.php?action=logout" class="nav-item" style="color: #dc3545;"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
        </div>
    </aside>

    <div style="flex: 1; display: flex; flex-direction: column;">
        <header class="top-header">
            <div class="header-actions">
                <a href="<?= $msgUrl ?>" class="icon-btn">
                    <i class="fas fa-envelope"></i>
                    <?php if ($unreadMsgs > 0): ?><span class="badge-dot"><?= $unreadMsgs ?></span><?php endif; ?>
                </a>

                <a href="<?= $notifUrl ?>" class="icon-btn">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadNotifs > 0): ?><span class="badge-dot"><?= $unreadNotifs ?></span><?php endif; ?>
                </a>

                <a href="<?= $profileUrl ?>" style="text-decoration:none; color:inherit;">
                    <div class="user-pill">
                        <?php if ($paAvatarUrl !== ''): ?>
                        <img class="avatar-circle-sm" src="<?= APP_URL . '/' . ltrim($paAvatarUrl, '/') ?>?v=<?= (function_exists('filemtime') && @filemtime(__DIR__ . '/../' . $paAvatarUrl)) ? @filemtime(__DIR__ . '/../' . $paAvatarUrl) : time() ?>" alt="" style="object-fit:cover;">
                        <?php else: ?>
                        <div class="avatar-circle-sm"><?= strtoupper(substr($currentUser['username'] ?? 'U', 0, 1)) ?></div>
                        <?php endif; ?>
                        <span><?= sanitize($currentUser['username'] ?? 'User') ?></span>
                    </div>
                </a>

                <?php if ($role === 'SHELTER' || $role === 'ADOPTER'): ?>
                <a href="<?= $basePath ?>/api/auth.php?action=logout" class="icon-btn pa-signout" title="Sign Out" aria-label="Sign Out"><i class="fas fa-sign-out-alt"></i></a>
                <?php endif; ?>
            </div>
        </header>


        <?php $flash = getFlash(); if ($flash): ?>
        <div class="flash-message flash-<?= $flash['type'] ?>" id="flashMsg">
            <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= sanitize($flash['message']) ?>
        </div>
        <script>setTimeout(() => { document.getElementById('flashMsg')?.remove(); }, 4000);</script>
        <?php endif; ?>

        <main class="main-content">
