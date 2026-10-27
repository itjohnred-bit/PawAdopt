<?php
/**
 * PAWAdopt - Fixed Audit Logs Dashboard
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || strtoupper($_SESSION['user']['role'] ?? '') !== 'ADMIN') {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/functions_audit.php';

$pdo = $pdo ?? $conn ?? Database::getInstance()->getConnection();
$currentUser = $_SESSION['user'] ?? [];

$logs = fetch_recent_audit_logs(50);
$stats_data = get_audit_statistics(30);

$u_id = $currentUser['user_id'] ?? $currentUser['id'] ?? 0;
log_action($u_id, 'view_dashboard', 'Accessed Audit Logs Dashboard');

if (!function_exists('audit_role_class')) {
    function audit_role_class(?string $role): string {
        return match (strtoupper((string) $role)) {
            'ADMIN' => 'badge-admin',
            'SHELTER', 'VETERINARY' => 'badge-shelter',
            'ADOPTER' => 'badge-adopter',
            'SYSTEM' => 'badge-system',
            default => 'badge-guest',
        };
    }
}

if (!function_exists('audit_action_icon_fallback')) {
    function audit_action_icon_fallback(?string $action): string {
        $action = strtolower((string) $action);
        return match (true) {
            str_contains($action, 'login') => 'fa-right-to-bracket',
            str_contains($action, 'logout') => 'fa-right-from-bracket',
            str_contains($action, 'delete'), str_contains($action, 'remove') => 'fa-trash',
            str_contains($action, 'create'), str_contains($action, 'add') => 'fa-plus',
            str_contains($action, 'update'), str_contains($action, 'edit') => 'fa-pen',
            str_contains($action, 'view') => 'fa-eye',
            default => 'fa-clock-rotate-left',
        };
    }
}

if (!function_exists('audit_action_label_fallback')) {
    function audit_action_label_fallback(?string $action): string {
        $action = str_replace('_', ' ', (string) $action);
        return ucwords(trim($action));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs | PAWAdopt Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/dark-mode.css">
    <style>
        :root {
            --primary-color: #0d9488;
            --primary-dark: #0f766e;
            --primary-light: #14b8a6;
            --secondary-color: #64748b;
            --success-color: #22c55e;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --bg-color: #edf5f2;
            --card-bg: #fbfefd;
            --text-color: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --shadow-soft: 0 14px 30px rgba(15, 23, 42, 0.08);
            --shadow-card: 0 22px 48px rgba(15, 23, 42, 0.1);
            --radius-xl: 24px;
            --radius-lg: 18px;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Nunito', Arial, sans-serif;
            background: linear-gradient(180deg, #f2f7f5 0%, #ebf4f1 100%);
            color: var(--text-color);
        }
        a { color: inherit; text-decoration: none; }

        .admin-shell { display: flex; min-height: 100vh; }
        .sidebar {
            width: 260px;
            background: rgba(253, 254, 253, 0.98);
            border-right: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 22px 0 52px rgba(15, 23, 42, 0.09);
            position: sticky;
            top: 0;
            height: 100vh;
            padding: 22px 16px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px 18px;
            border-bottom: 1px solid rgba(15, 23, 42, 0.06);
        }
        .brand-mark {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #d8f8f2, #effcf8);
            color: var(--primary-color);
            box-shadow: var(--shadow-soft);
            font-size: 20px;
        }
        .brand h1 { margin: 0; font-size: 22px; color: var(--primary-color); }
        .brand p { margin: 4px 0 0; font-size: 12px; color: var(--text-muted); }

        .sidebar-nav { display: grid; gap: 8px; }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 13px 14px;
            border-radius: 16px;
            color: var(--text-muted);
            transition: .18s ease;
            box-shadow: var(--shadow-soft);
            background: var(--card-bg);
            border: 1px solid rgba(15, 23, 42, 0.05);
        }
        .nav-item:hover { transform: translateY(-1px); color: var(--text-color); }
        .nav-item.active {
            background: linear-gradient(135deg, #e4f4ef, #f1fbf8);
            color: var(--primary-dark);
            font-weight: 700;
        }
        .nav-item i { width: 20px; text-align: center; }

        .main-wrap { flex: 1; padding: 28px; }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            padding: 16px 20px;
            border-radius: var(--radius-xl);
            background: rgba(253, 254, 253, 0.92);
            box-shadow: var(--shadow-card);
            border: 1px solid rgba(15, 23, 42, 0.05);
            backdrop-filter: blur(12px);
        }
        .page-title h2 { margin: 0; font-size: 30px; }
        .page-title p { margin: 8px 0 0; color: var(--text-muted); }
        .top-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .admin-chip {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 999px;
            background: var(--card-bg);
            box-shadow: var(--shadow-soft);
            border: 1px solid rgba(15, 23, 42, 0.05);
            color: var(--text-color);
            font-weight: 700;
        }
        .admin-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: #fff;
            font-size: 13px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 18px;
            border-radius: 14px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            background: var(--card-bg);
            color: var(--text-color);
            box-shadow: var(--shadow-soft);
            font-weight: 700;
            transition: .18s ease;
        }
        .btn:hover { transform: translateY(-1px); box-shadow: var(--shadow-card); }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: #fff;
            border-color: transparent;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            border: 1px solid rgba(15, 23, 42, 0.05);
            box-shadow: var(--shadow-card);
            padding: 22px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            font-size: 20px;
            box-shadow: var(--shadow-soft);
        }
        .stat-icon.blue { background: rgba(59, 130, 246, 0.12); color: #2563eb; }
        .stat-icon.green { background: rgba(34, 197, 94, 0.12); color: #16a34a; }
        .stat-icon.red { background: rgba(239, 68, 68, 0.12); color: #dc2626; }
        .stat-card strong { display: block; font-size: 28px; }
        .stat-card span { color: var(--text-muted); font-size: 13px; }

        .card {
            background: var(--card-bg);
            border-radius: var(--radius-xl);
            border: 1px solid rgba(15, 23, 42, 0.05);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }
        .card-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 18px 22px;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(180deg, rgba(243, 250, 248, 0.88), rgba(251, 254, 253, 0.96));
        }
        .card-head h3 { margin: 0; font-size: 18px; }
        .card-head p { margin: 6px 0 0; color: var(--text-muted); font-size: 13px; }
        .pill {
            padding: 8px 12px;
            border-radius: 999px;
            background: #eefbf8;
            color: var(--primary-dark);
            font-size: 12px;
            font-weight: 800;
            border: 1px solid rgba(13, 148, 136, 0.12);
        }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 760px; }
        thead { background: #f6fbf9; }
        th {
            padding: 15px 18px;
            text-align: left;
            font-size: 11px;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-color);
        }
        td {
            padding: 16px 18px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            font-size: 14px;
        }
        tbody tr:hover { background: rgba(13, 148, 136, 0.03); }

        .user-name { font-weight: 800; margin-bottom: 4px; }
        .subtle { font-size: 12px; color: var(--text-muted); }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            box-shadow: var(--shadow-soft);
        }
        .badge-admin   { background: rgba(239, 68, 68, 0.12); color: #dc2626; }
        .badge-shelter { background: rgba(34, 197, 94, 0.12); color: #16a34a; }
        .badge-adopter { background: rgba(59, 130, 246, 0.12); color: #2563eb; }
        .badge-guest   { background: rgba(100, 116, 139, 0.14); color: #475569; }
        .badge-system  { background: rgba(30, 41, 59, 0.12); color: #0f172a; }

        .action-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 12px;
            background: rgba(13, 148, 136, 0.09);
            color: var(--primary-dark);
            font-weight: 700;
            box-shadow: var(--shadow-soft);
        }
        .ip-address {
            display: inline-flex;
            padding: 6px 10px;
            border-radius: 10px;
            background: #f5f8fa;
            color: var(--text-muted);
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
        }

        .empty-state {
            padding: 56px 20px;
            text-align: center;
            color: var(--text-muted);
        }
        .empty-state i { font-size: 42px; margin-bottom: 12px; color: #cbd5e1; }

        body.dark-mode .sidebar,
        body.dark-mode .topbar,
        body.dark-mode .stat-card,
        body.dark-mode .card,
        body.dark-mode .nav-item,
        body.dark-mode .admin-chip,
        body.dark-mode .btn,
        body.dark-mode .action-badge,
        body.dark-mode .ip-address,
        body.dark-mode .pill,
        body.dark-mode .brand-mark {
            border-color: rgba(125, 211, 252, 0.08) !important;
        }
        body.dark-mode { background: linear-gradient(180deg, #0f1720 0%, #101925 100%) !important; }
        body.dark-mode .sidebar { background: #0f172a !important; }
        body.dark-mode .topbar,
        body.dark-mode .stat-card,
        body.dark-mode .card,
        body.dark-mode .nav-item,
        body.dark-mode .admin-chip,
        body.dark-mode .btn,
        body.dark-mode .ip-address,
        body.dark-mode .pill { background: #111827 !important; color: #e5eef5 !important; }
        body.dark-mode .card-head { background: linear-gradient(180deg, #16212d 0%, #111827 100%) !important; border-bottom-color: #2b3b4c !important; }
        body.dark-mode thead { background: #0f172a !important; }
        body.dark-mode tbody tr:hover { background: rgba(125, 211, 252, 0.06) !important; }
        body.dark-mode table,
        body.dark-mode th,
        body.dark-mode td,
        body.dark-mode .user-name,
        body.dark-mode .page-title h2,
        body.dark-mode .brand h1,
        body.dark-mode .card-head h3,
        body.dark-mode .stat-card strong { color: #f8fbff !important; }
        body.dark-mode .subtle,
        body.dark-mode .page-title p,
        body.dark-mode .brand p,
        body.dark-mode th,
        body.dark-mode td .subtle { color: #9fb1c1 !important; }
        body.dark-mode td,
        body.dark-mode th { border-color: #2b3b4c !important; }
        body.dark-mode .ip-address { background: #16212d !important; color: #cbd5e1 !important; }
        body.dark-mode .action-badge { background: rgba(13, 148, 136, 0.2) !important; color: #99f6e4 !important; }
        body.dark-mode .badge-admin { background: #991b1b !important; color: #fee2e2 !important; }
        body.dark-mode .badge-shelter { background: #166534 !important; color: #dcfce7 !important; }
        body.dark-mode .badge-adopter { background: #1d4ed8 !important; color: #dbeafe !important; }
        body.dark-mode .badge-guest { background: #475569 !important; color: #f8fafc !important; }
        body.dark-mode .badge-system { background: #334155 !important; color: #e2e8f0 !important; }
        body.dark-mode .brand-mark { background: linear-gradient(135deg, #134e4a, #16212d) !important; color: #99f6e4 !important; }
        body.dark-mode .nav-item.active { background: linear-gradient(135deg, #134e4a, #16212d) !important; color: #f8fbff !important; }
        body.dark-mode .btn-primary { background: linear-gradient(135deg, #0d9488, #115e59) !important; color: #fff !important; }

        @media (max-width: 980px) {
            .admin-shell { display: block; }
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                border-right: none;
                border-bottom: 1px solid rgba(15, 23, 42, 0.06);
            }
            .main-wrap { padding: 18px; }
            .topbar { flex-direction: column; align-items: stretch; }
            .top-actions { justify-content: space-between; }
        }
    </style>
</head>
<body>
    <div class="admin-shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark"><i class="fas fa-paw"></i></div>
                <div>
                    <h1>Paw-Adopt</h1>
                    <p>Admin controls</p>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="users.php" class="nav-item"><i class="fas fa-users"></i> Users</a>
        
                <a href="pets.php" class="nav-item"><i class="fas fa-dog"></i> Pets</a>
                <a href="reports.php" class="nav-item"><i class="fas fa-chart-column"></i> Reports</a>
                <a href="audit-logs.php" class="nav-item active"><i class="fas fa-history"></i> Audit Logs</a>
            </nav>
        </aside>

        <main class="main-wrap">
            <div class="topbar">
                <div class="page-title">
                    <h2>System Activity Logs</h2>
                    <p>Track logins, account actions, and security-sensitive events across Paw-Adopt.</p>
                </div>

                <div class="top-actions">
                    <div class="admin-chip">
                        <div class="admin-avatar"><?= strtoupper(substr($currentUser['username'] ?? 'A', 0, 1)) ?></div>
                        <span><?= htmlspecialchars($currentUser['username'] ?? 'Admin') ?></span>
                    </div>
                    <a href="export-audit-pdf.php" class="btn btn-primary">
                        <i class="fas fa-file-pdf"></i> Export PDF Report
                    </a>
                </div>
            </div>

            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-list"></i></div>
                    <div>
                        <strong><?= number_format($stats_data['total_logs'] ?? 0) ?></strong>
                        <span>Total Logs</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-calendar-day"></i></div>
                    <div>
                        <strong><?= number_format($stats_data['today_logs'] ?? 0) ?></strong>
                        <span>Today's Activity</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                    <div>
                        <strong><?= number_format($stats_data['failed_logins'] ?? 0) ?></strong>
                        <span>Failed Logins (30d)</span>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card-head">
                    <div>
                        <h3><i class="fas fa-clock-rotate-left"></i> Recent Audit Events</h3>
                        <p>Latest 50 entries recorded by the PAWAdopt audit log system.</p>
                    </div>
                    <span class="pill"><?= count($logs) ?> records</span>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Action</th>
                                <th>Date / Time</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $log): ?>
                                    <?php
                                        $username = htmlspecialchars($log['username'] ?? 'System');
                                        $role = htmlspecialchars($log['role'] ?? 'Guest');
                                        $roleClass = audit_role_class($log['role'] ?? '');
                                        $actionIcon = function_exists('get_action_icon') ? get_action_icon($log['action'] ?? '') : audit_action_icon_fallback($log['action'] ?? '');
                                        $actionLabel = function_exists('format_action_name') ? format_action_name($log['action'] ?? '') : audit_action_label_fallback($log['action'] ?? '');
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="user-name"><?= $username ?></div>
                                            <div class="subtle">ID: <?= htmlspecialchars((string)($log['user_id'] ?? 'N/A')) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge <?= $roleClass ?>"><?= $role ?></span>
                                        </td>
                                        <td>
                                            <span class="action-badge"><i class="fas <?= htmlspecialchars($actionIcon) ?>"></i> <?= htmlspecialchars((string)$actionLabel) ?></span>
                                        </td>
                                        <td>
                                            <div class="user-name" style="font-size:14px"><?= !empty($log['created_at']) ? date('M d, Y', strtotime($log['created_at'])) : '—' ?></div>
                                            <div class="subtle"><?= !empty($log['created_at']) ? date('H:i', strtotime($log['created_at'])) : '' ?></div>
                                        </td>
                                        <td>
                                            <span class="ip-address"><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <i class="fas fa-box-open"></i>
                                            <h4 style="margin:0 0 8px;color:inherit">No audit logs found</h4>
                                            <p style="margin:0">No audit logs are currently stored in the database.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <script src="<?= APP_URL ?>/assets/js/dark-mode.js"></script>
</body>
</html>
