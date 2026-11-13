<?php
/**
 * PawAdopt — Community API (posts / comments / reports / moderation)
 *
 *   POST api/community.php?action=create_post
 *   POST api/community.php?action=add_comment
 *   POST api/community.php?action=delete_post
 *   POST api/community.php?action=delete_comment
 *   POST api/community.php?action=report
 *   POST api/community.php?action=moderate          (ADMIN only)
 *   GET  api/community.php?action=list&filter=all&page=1
 *   GET  api/community.php?action=comments&post_id=1
 *
 * Server-side enforcement (never the UI):
 *   • requireLogin() at the top — the request itself needs a session.
 *   • CSRF token required on every write.
 *   • Role check for ANNOUNCEMENT / SYSTEM posts and for @all mentions.
 *   • Ownership check before edit/delete (author or ADMIN).
 *   • All output is escaped; input is length-capped and tag-stripped.
 *   • Author email / phone / address is NEVER selected or returned.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/community.php';
startSession();
requireLogin();

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

$user   = getCurrentUser();
$db     = Database::getInstance();

/** Accepts either CSRF convention used across the codebase. */
function pa_csrf_ok(): bool {
    $t = (string) ($_POST['csrf_token'] ?? '');
    if ($t === '') { return false; }
    if (function_exists('verifyCsrfToken') && verifyCsrfToken($t)) { return true; }
    $sess = (string) ($_SESSION['csrf'] ?? '');
    if ($sess !== '' && hash_equals($sess, $t)) { return true; }
    return false;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    switch ($action) {

        case 'list':
            $filter = strtoupper(trim((string) ($_GET['filter'] ?? 'ALL')));
            if ($filter === 'ALL') { $filter = 'all'; }
            $page   = max(1, (int) ($_GET['page'] ?? 1));
            $limit  = 20;
            $offset = ($page - 1) * $limit;
            $posts  = pa_community_posts($db, $filter, $limit, $offset);
            $out    = [];
            foreach ($posts as $p) {
                $out[] = [
                    'post_id'       => (int) $p['post_id'],
                    'type'          => $p['post_type'],
                    'type_label'    => pa_community_type_label($p['post_type']),
                    'title'         => $p['title'],
                    'body'          => $p['body'],
                    'html'          => pa_community_render_text($p['body']),
                    'author'        => $p['username'],
                    'role'          => $p['role'],
                    'comment_count' => (int) $p['comment_count'],
                    'created_at'    => $p['created_at'],
                    'is_mine'       => (int) $p['user_id'] === (int) $user['user_id'],
                ];
            }
            jsonSuccess(['posts' => $out, 'page' => $page]);

        case 'comments':
            $postId = (int) ($_GET['post_id'] ?? 0);
            if ($postId <= 0) { jsonError('Missing post_id.'); }
            $rows = pa_community_comments($db, $postId);
            $out  = [];
            foreach ($rows as $c) {
                $out[] = [
                    'comment_id' => (int) $c['comment_id'],
                    'body'       => $c['body'],
                    'html'       => pa_community_render_text($c['body']),
                    'author'     => $c['username'],
                    'role'       => $c['role'],
                    'created_at' => $c['created_at'],
                    'is_mine'    => (int) $c['user_id'] === (int) $user['user_id'],
                ];
            }
            jsonSuccess(['comments' => $out]);

        case 'create_post':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('POST only.', 405); }
            if (!pa_csrf_ok()) { jsonError('Invalid CSRF token.', 403); }
            [$ok, $msg] = pa_community_create_post(
                $db,
                $user,
                (string) ($_POST['post_type'] ?? 'INQUIRY'),
                (string) ($_POST['title'] ?? ''),
                (string) ($_POST['body'] ?? '')
            );
            $ok ? jsonSuccess([], $msg) : jsonError($msg);

        case 'add_comment':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('POST only.', 405); }
            if (!pa_csrf_ok()) { jsonError('Invalid CSRF token.', 403); }
            [$ok, $msg] = pa_community_add_comment(
                $db, $user, (int) ($_POST['post_id'] ?? 0), (string) ($_POST['body'] ?? '')
            );
            $ok ? jsonSuccess([], $msg) : jsonError($msg);

        case 'delete_post':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('POST only.', 405); }
            if (!pa_csrf_ok()) { jsonError('Invalid CSRF token.', 403); }
            [$ok, $msg] = pa_community_delete_post($db, $user, (int) ($_POST['post_id'] ?? 0));
            $ok ? jsonSuccess([], $msg) : jsonError($msg, 403);

        case 'delete_comment':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('POST only.', 405); }
            if (!pa_csrf_ok()) { jsonError('Invalid CSRF token.', 403); }
            [$ok, $msg] = pa_community_delete_comment($db, $user, (int) ($_POST['comment_id'] ?? 0));
            $ok ? jsonSuccess([], $msg) : jsonError($msg, 403);

        case 'report':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('POST only.', 405); }
            if (!pa_csrf_ok()) { jsonError('Invalid CSRF token.', 403); }
            $pid = (int) ($_POST['post_id'] ?? 0);
            $cid = (int) ($_POST['comment_id'] ?? 0);
            [$ok, $msg] = pa_community_report($db, $user, (string) ($_POST['reason'] ?? ''), $pid ?: null, $cid ?: null);
            $ok ? jsonSuccess([], $msg) : jsonError($msg);

        case 'moderate':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('POST only.', 405); }
            if (!pa_csrf_ok()) { jsonError('Invalid CSRF token.', 403); }
            [$ok, $msg] = pa_community_moderate(
                $db, $user, (string) ($_POST['op'] ?? ''), (int) ($_POST['target_id'] ?? 0)
            );
            $ok ? jsonSuccess([], $msg) : jsonError($msg, 403);

        default:
            jsonError('Unknown action.', 400);
    }
} catch (Throwable $e) {
    error_log('PawAdopt community: ' . $e->getMessage());
    jsonError('The community service is unavailable right now. Please retry.', 500);
}
