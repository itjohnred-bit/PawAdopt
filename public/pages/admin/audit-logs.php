<?php

session_start();

// SECURITY CHECK
if (!isset($_SESSION['user']) || strtoupper($_SESSION['user']['role'] ?? '') !== 'ADMIN') {
    header("Location: ../../login.php");
    exit();
}

require_once "../../../config/database.php";
require_once "../../../includes/functions.php";
require_once "../../../includes/functions_audit.php";

$pdo = $pdo ?? $conn ?? Database::getInstance()->getConnection();

// Fetch Data
$logs = fetch_recent_audit_logs(50);

// Fetch Statistics
$stats_data = get_audit_statistics(30);

// Log the access safely
$u_id = $_SESSION['user']['user_id'] ?? $_SESSION['user']['id'] ?? 0;
log_action($u_id, 'view_dashboard', 'Accessed Audit Logs Dashboard');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs | PAWAdopt Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-slate-50 flex text-slate-800">

    <!-- Sidebar -->
    <aside class="w-64 bg-white border-r h-screen sticky top-0 hidden md:block">
        <div class="p-6 border-b">
            <h1 class="text-teal-600 font-bold text-xl"><i class="fas fa-paw mr-2"></i>Paw-Adopt</h1>
        </div>
        <nav class="p-4 space-y-2">
            <a href="dashboard.php" class="flex items-center p-3 text-slate-600 hover:bg-slate-50 rounded-lg"><i class="fas fa-th-large w-8"></i> Dashboard</a>
            <a href="users.php" class="flex items-center p-3 text-slate-600 hover:bg-slate-50 rounded-lg"><i class="fas fa-users w-8"></i> Users</a>
            <a href="pets.php" class="flex items-center p-3 text-slate-600 hover:bg-slate-50 rounded-lg"><i class="fas fa-dog w-8"></i> Pets</a>
            <a href="audit-logs.php" class="flex items-center p-3 bg-teal-50 text-teal-700 font-medium rounded-lg"><i class="fas fa-history w-8"></i> Audit Logs</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-8">
        <div class="flex justify-between items-center mb-8">
            <h2 class="text-2xl font-bold">System Activity Logs</h2>
            <a href="export-audit-pdf.php" class="bg-teal-600 hover:bg-teal-700 text-white px-6 py-2 rounded-lg font-semibold transition">
                <i class="fas fa-file-pdf mr-2"></i> Export PDF Report
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl border border-slate-100 shadow-sm flex items-center">
                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center mr-4 text-xl"><i class="fas fa-list"></i></div>
                <div>
                    <div class="text-2xl font-bold"><?= number_format($stats_data['total_logs'] ?? 0) ?></div>
                    <div class="text-slate-500 text-sm">Total Logs</div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-slate-100 shadow-sm flex items-center">
                <div class="w-12 h-12 bg-green-50 text-green-600 rounded-lg flex items-center justify-center mr-4 text-xl"><i class="fas fa-calendar-day"></i></div>
                <div>
                    <div class="text-2xl font-bold"><?= number_format($stats_data['today_logs'] ?? 0) ?></div>
                    <div class="text-slate-500 text-sm">Today's Activity</div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-slate-100 shadow-sm flex items-center">
                <div class="w-12 h-12 bg-red-50 text-red-600 rounded-lg flex items-center justify-center mr-4 text-xl"><i class="fas fa-exclamation-triangle"></i></div>
                <div>
                    <div class="text-2xl font-bold"><?= number_format($stats_data['failed_logins'] ?? 0) ?></div>
                    <div class="text-slate-500 text-sm">Failed Logins (30d)</div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">User</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Action</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">Date / Time</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $log): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800"><?= htmlspecialchars($log['username'] ?? 'System') ?></div>
                                <div class="text-xs text-slate-400"><?= htmlspecialchars($log['role'] ?? 'N/A') ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700">
                                <i class="fas <?= function_exists('get_action_icon') ? get_action_icon($log['action']) : 'fa-history' ?> mr-2 text-slate-400"></i>
                                <?= function_exists('format_action_name') ? format_action_name($log['action']) : htmlspecialchars($log['action']) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500"><?= date('M d, Y H:i', strtotime($log['created_at'])) ?></td>
                            <td class="px-6 py-4 text-sm text-slate-400 font-mono"><?= $log['ip_address'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="px-6 py-12 text-center text-slate-400">No audit logs found in the database.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
