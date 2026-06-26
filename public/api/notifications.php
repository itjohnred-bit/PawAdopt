<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireLogin();

$db     = Database::getInstance();
$action = $_GET['action'] ?? 'list';
$user   = getCurrentUser();

switch ($action) {
    case 'list':
        $notifs = $db->fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20",
            [$user['user_id']]
        );
        foreach ($notifs as &$n) {
            $n['time_ago'] = timeAgo($n['created_at']);
        }
        jsonSuccess($notifs);
        break;

    case 'mark_read':
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            $db->execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?", [$id, $user['user_id']]);
        }
        jsonSuccess([], 'Marked read.');
        break;

    case 'mark_all_read':
        $db->execute("UPDATE notifications SET is_read = 1 WHERE user_id = ?", [$user['user_id']]);
        header('Location: ' . $_SERVER['HTTP_REFERER'] ?? APP_URL . '/pages/' . strtolower($user['role']) . '/dashboard.php');
        exit;

    case 'count':
        $count = getUnreadNotificationCount($user['user_id']);
        jsonSuccess(['count' => $count]);
        break;

    default:
        jsonError('Unknown action.', 400);
}
?>
