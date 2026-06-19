<?php
require_once __DIR__ . '/../includes/functions.php';
startSession(); 
requireLogin();
$currentUser = getCurrentUser();

// Standardizing function names to match your DB schema
$unreadNotifs = getUnreadNotificationCount($currentUser['user_id']);
$unreadMsgs   = getUnreadMessageCount($currentUser['user_id']);

$role = $currentUser['role'];
$basePath = APP_URL;

// Detect current page for "active" class
$current_page = basename($_SERVER['PHP_SELF']);

// Role-specific nav paths
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
    $dashUrl    = "$basePath/pages/admin/dashboard.php";
    $usersUrl   = "$basePath/pages/admin/users.php";
    $sheltersUrl= "$basePath/pages/admin/shelters.php";
    $petsAdmUrl = "$basePath/pages/admin/pets.php";
    $reportsUrl = "$basePath/pages/admin/reports.php";
    $auditLogsUrl = "$basePath/pages/admin/audit-logs.php";
    $profileUrl = "$basePath/pages/admin/dashboard.php"; 
    $notifUrl   = "#";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' – ' . APP_NAME : APP_NAME ?></title>
    <link rel="stylesheet" href="/PawAdopt/assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="/PawAdopt/assets/css/main.css">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    
    
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

        /* SIDEBAR STYLES */
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

        /* TOP HEADER */
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
            box-sizing: border-box; /* Crucial for layout */
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

        /* MAIN CONTENT AREA FIX */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            margin-top: 70px;
            padding: 30px;
            width: calc(100% - var(--sidebar-width)); /* Fixed width calculation */
            min-height: calc(100vh - 70px);
            box-sizing: border-box; /* Prevents overflow */
        }

        /* Dashboard Layout Helper Classes */
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
</head>
<body>

<div class="admin-wrapper">
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <span>🐾</span> Paw-Adopt
        </div>
        
        <nav class="sidebar-nav">
            <?php if ($role === 'ADOPTER'): ?>
                <a href="<?= $dashUrl ?>" class="nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>"><i class="fas fa-home"></i> Dashboard</a>
                <a href="<?= $browseUrl ?>" class="nav-item <?= $current_page == 'browse.php' ? 'active' : '' ?>"><i class="fas fa-search"></i> Browse</a>
                <a href="<?= $favUrl ?>" class="nav-item <?= $current_page == 'favorites.php' ? 'active' : '' ?>"><i class="fas fa-heart"></i> Favorites</a>
                <a href="<?= $appsUrl ?>" class="nav-item <?= $current_page == 'applications.php' ? 'active' : '' ?>"><i class="fas fa-file-alt"></i> My Applications</a>
            <?php elseif ($role === 'SHELTER'): ?>
                <a href="<?= $dashUrl ?>" class="nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="<?= $petsUrl ?>" class="nav-item <?= $current_page == 'pets.php' ? 'active' : '' ?>"><i class="fas fa-paw"></i> My Pets</a>
                <a href="<?= $appsUrl ?>" class="nav-item <?= $current_page == 'applications.php' ? 'active' : '' ?>"><i class="fas fa-file-alt"></i> Applications</a>
            <?php else: ?>
                <!-- ADMIN MENU -->
                <a href="<?= $dashUrl ?>" class="nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="<?= $usersUrl ?>" class="nav-item <?= $current_page == 'users.php' ? 'active' : '' ?>"><i class="fas fa-users"></i> Users</a>
                <a href="<?= $sheltersUrl ?>" class="nav-item <?= $current_page == 'shelters.php' ? 'active' : '' ?>"><i class="fas fa-building"></i> Shelters</a>
                <a href="<?= $petsAdmUrl ?>" class="nav-item <?= $current_page == 'pets.php' ? 'active' : '' ?>"><i class="fas fa-paw"></i> Pets</a>
                <a href="<?= $reportsUrl ?>" class="nav-item <?= $current_page == 'reports.php' ? 'active' : '' ?>"><i class="fas fa-chart-bar"></i> Reports</a>
                <a href="<?= $auditLogsUrl ?>" class="nav-item <?= $current_page == 'audit-logs.php' ? 'active' : '' ?>"><i class="fas fa-history"></i> Audit Logs</a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-nav" style="flex: 0; border-top: 1px solid #eee;">
            <a href="<?= $basePath ?>/api/auth.php?action=logout" class="nav-item" style="color: #dc3545;"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
        </div>
    </aside>

    <div style="flex: 1; display: flex; flex-direction: column;">
        <header class="top-header">
            <div class="header-actions">
                <!-- MESSAGES -->
                <a href="<?= $msgUrl ?>" class="icon-btn">
                    <i class="fas fa-envelope"></i>
                    <?php if ($unreadMsgs > 0): ?><span class="badge-dot"><?= $unreadMsgs ?></span><?php endif; ?>
                </a>
                
                <!-- NOTIFICATIONS -->
                <a href="<?= $notifUrl ?>" class="icon-btn">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadNotifs > 0): ?><span class="badge-dot"><?= $unreadNotifs ?></span><?php endif; ?>
                </a>

                <a href="<?= $profileUrl ?>" style="text-decoration:none; color:inherit;">
                    <div class="user-pill">
                        <div class="avatar-circle-sm"><?= strtoupper(substr($currentUser['username'] ?? 'U', 0, 1)) ?></div>
                        <span><?= sanitize($currentUser['username'] ?? 'User') ?></span>
                    </div>
                </a>
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
