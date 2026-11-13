<?php
/**
 * PawAdopt — Community helpers (server-side enforcement lives here).
 *
 * Rules enforced on the SERVER, never only in the UI:
 *   • Any signed-in role may read the feed and write EXPERIENCE / INQUIRY posts.
 *   • Only SHELTER / VETERINARY / ADMIN may write ANNOUNCEMENT posts.
 *   • Only ADMIN may write SYSTEM posts (platform updates).
 *   • @all mentions are allowed for SHELTER / VETERINARY / ADMIN only.
 *   • Only the author or an ADMIN may edit/delete a post or comment.
 *   • CSRF token required for every write.
 *   • Output is HTML-escaped at render time; input is length-capped and stripped.
 *   • No email / phone / address is ever stored in or selected from community
 *     tables — author identity is the username + role only.
 */

const PA_COMMUNITY_TITLE_MAX   = 160;
const PA_COMMUNITY_BODY_MAX    = 4000;
const PA_COMMUNITY_COMMENT_MAX = 1500;
const PA_COMMUNITY_RATE_LIMIT  = 6;   // posts per rolling hour, per user
const PA_COMMUNITY_NOTIFY_CAP  = 500; // never fan out notifications endlessly

/** Roles that may publish announcements / use @all. */
function pa_community_can_announce(string $role): bool
{
    return in_array(strtoupper($role), ['SHELTER', 'VETERINARY', 'ADMIN'], true);
}

/** Roles that may moderate the community. */
function pa_community_is_moderator(string $role): bool
{
    return strtoupper($role) === 'ADMIN';
}

/** Post types the given role may submit. */
function pa_community_allowed_types(string $role): array
{
    $types = ['EXPERIENCE', 'INQUIRY'];
    if (pa_community_can_announce($role)) {
        $types[] = 'ANNOUNCEMENT';
    }
    if (pa_community_is_moderator($role)) {
        $types[] = 'SYSTEM';
    }
    return $types;
}

/** Human label for a post type. */
function pa_community_type_label(string $type): string
{
    switch (strtoupper($type)) {
        case 'EXPERIENCE':   return 'Adoption story';
        case 'INQUIRY':      return 'Community question';
        case 'ANNOUNCEMENT': return 'Announcement';
        case 'SYSTEM':       return 'System update';
        default:             return 'Post';
    }
}

/** Icon for a post type. */
function pa_community_type_icon(string $type): string
{
    switch (strtoupper($type)) {
        case 'EXPERIENCE':   return 'fa-heart';
        case 'INQUIRY':      return 'fa-circle-question';
        case 'ANNOUNCEMENT': return 'fa-bullhorn';
        case 'SYSTEM':       return 'fa-shield-halved';
        default:             return 'fa-comment';
    }
}

/**
 * Normalise user text: strip control chars / tags, collapse runs of blank
 * lines, trim, and hard-cap the length. Never trust the browser's maxlength.
 */
function pa_community_clean(string $text, int $max, bool $singleLine = false): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = strip_tags($text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    if ($singleLine) {
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';
    } else {
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? '';
    }
    $text = trim($text);
    if (function_exists('mb_substr')) {
        $text = mb_substr($text, 0, $max, 'UTF-8');
    } else {
        $text = substr($text, 0, $max);
    }
    return $text;
}

/** True when the text contains an @all mention. */
function pa_community_has_all_mention(string $text): bool
{
    return (bool) preg_match('/@all\b/i', $text);
}

/** Posts created by this user in the last hour (anti-spam). */
function pa_community_recent_post_count(Database $db, int $userId): int
{
    $row = $db->fetch(
        'SELECT COUNT(*) AS c FROM community_posts
         WHERE user_id = ? AND created_at >= (NOW() - INTERVAL 1 HOUR)',
        [$userId]
    );
    return (int) ($row['c'] ?? 0);
}

/**
 * Fetch the feed. Only username + role of the author is selected — never the
 * author's email, phone or address.
 */
function pa_community_posts(Database $db, string $filter = 'all', int $limit = 30, int $offset = 0): array
{
    $limit  = max(1, min(60, $limit));
    $offset = max(0, $offset);

    $where  = ['p.is_hidden = 0'];
    $params = [];
    if (in_array($filter, ['EXPERIENCE', 'INQUIRY', 'ANNOUNCEMENT', 'SYSTEM'], true)) {
        $where[]  = 'p.post_type = ?';
        $params[] = $filter;
    }

    $sql = 'SELECT p.post_id, p.post_type, p.title, p.body, p.mention_all, p.is_pinned,
                   p.comment_count, p.created_at, p.updated_at, p.user_id,
                   u.username, u.role
            FROM community_posts p
            JOIN users u ON u.user_id = p.user_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY p.is_pinned DESC, p.created_at DESC
            LIMIT ' . $limit . ' OFFSET ' . $offset;

    return $db->fetchAll($sql, $params);
}

/** Comments for one post (hidden ones are filtered out). */
function pa_community_comments(Database $db, int $postId, int $limit = 100): array
{
    return $db->fetchAll(
        'SELECT c.comment_id, c.post_id, c.body, c.created_at, c.user_id,
                u.username, u.role
         FROM community_comments c
         JOIN users u ON u.user_id = c.user_id
         WHERE c.post_id = ? AND c.is_hidden = 0
         ORDER BY c.created_at ASC
         LIMIT ' . max(1, min(200, $limit)),
        [$postId]
    );
}

/** Create a post. Returns [ok, message]. */
function pa_community_create_post(Database $db, array $user, string $type, string $title, string $body): array
{
    $role = strtoupper((string) ($user['role'] ?? ''));
    $type = strtoupper(trim($type));
    if (!in_array($type, pa_community_allowed_types($role), true)) {
        return [false, 'Your account type cannot publish that kind of post.'];
    }

    $title = pa_community_clean($title, PA_COMMUNITY_TITLE_MAX, true);
    $body  = pa_community_clean($body, PA_COMMUNITY_BODY_MAX);
    if (mb_strlen($title) < 4) {
        return [false, 'Title needs at least 4 characters.'];
    }
    if (mb_strlen($body) < 10) {
        return [false, 'Post body needs at least 10 characters.'];
    }

    $wantsAll = pa_community_has_all_mention($title . ' ' . $body);
    if ($wantsAll && !pa_community_can_announce($role)) {
        return [false, 'Only shelters, veterinary accounts and admins can mention @all.'];
    }

    if (pa_community_recent_post_count($db, (int) $user['user_id']) >= PA_COMMUNITY_RATE_LIMIT) {
        return [false, 'You reached the posting limit for this hour. Please try again later.'];
    }

    $db->execute(
        'INSERT INTO community_posts (user_id, post_type, title, body, mention_all, is_pinned, is_hidden, comment_count)
         VALUES (?, ?, ?, ?, ?, 0, 0, 0)',
        [(int) $user['user_id'], $type, $title, $body, $wantsAll ? 1 : 0]
    );
    $row   = $db->fetch('SELECT post_id FROM community_posts WHERE user_id = ? ORDER BY post_id DESC LIMIT 1', [(int) $user['user_id']]);
    $newId = (int) ($row['post_id'] ?? 0);

    if ($wantsAll && $newId > 0) {
        pa_community_notify_all($db, (int) $user['user_id'], $title, $type, $newId);
    }

    if (function_exists('log_action')) {
        log_action((int) $user['user_id'], 'community_post_create', 'community_posts', $newId, null, ['type' => $type]);
    }

    return [true, 'Posted to the community.'];
}

/** @all fan-out — one notification per active user, capped. */
function pa_community_notify_all(Database $db, int $authorId, string $title, string $type, int $postId): void
{
    $users = $db->fetchAll(
        'SELECT user_id FROM users WHERE is_active = 1 AND user_id <> ? ORDER BY user_id ASC LIMIT ' . PA_COMMUNITY_NOTIFY_CAP,
        [$authorId]
    );
    if (!$users) {
        return;
    }
    $link  = APP_URL . '/pages/community.php#post-' . $postId;
    $label = pa_community_type_label($type);
    foreach ($users as $u) {
        try {
            createNotification(
                (int) $u['user_id'],
                'community',
                '📣 ' . $label . ' for everyone',
                $title,
                $link
            );
        } catch (Throwable $e) {
            // A notification failure must never lose the post itself.
        }
    }
}

/** Add a comment. Returns [ok, message]. */
function pa_community_add_comment(Database $db, array $user, int $postId, string $body): array
{
    $body = pa_community_clean($body, PA_COMMUNITY_COMMENT_MAX);
    if (mb_strlen($body) < 2) {
        return [false, 'Comment is too short.'];
    }
    $post = $db->fetch('SELECT post_id, is_hidden FROM community_posts WHERE post_id = ?', [$postId]);
    if (!$post) {
        return [false, 'That post no longer exists.'];
    }
    if ((int) $post['is_hidden'] === 1) {
        return [false, 'That post is not available.'];
    }

    $db->execute(
        'INSERT INTO community_comments (post_id, user_id, body, is_hidden, created_at) VALUES (?, ?, ?, 0, NOW())',
        [$postId, (int) $user['user_id'], $body]
    );
    $db->execute('UPDATE community_posts SET comment_count = comment_count + 1 WHERE post_id = ?', [$postId]);

    return [true, 'Comment added.'];
}

/** Author-or-admin ownership check for a post. */
function pa_community_can_manage_post(Database $db, array $user, int $postId): bool
{
    if (pa_community_is_moderator((string) ($user['role'] ?? ''))) {
        return true;
    }
    $post = $db->fetch('SELECT user_id FROM community_posts WHERE post_id = ?', [$postId]);
    return $post && (int) $post['user_id'] === (int) $user['user_id'];
}

/** Author-or-admin ownership check for a comment. */
function pa_community_can_manage_comment(Database $db, array $user, int $commentId): bool
{
    if (pa_community_is_moderator((string) ($user['role'] ?? ''))) {
        return true;
    }
    $row = $db->fetch('SELECT user_id FROM community_comments WHERE comment_id = ?', [$commentId]);
    return $row && (int) $row['user_id'] === (int) $user['user_id'];
}

/** Delete a post (author or admin). */
function pa_community_delete_post(Database $db, array $user, int $postId): array
{
    if (!pa_community_can_manage_post($db, $user, $postId)) {
        return [false, 'You can only delete your own posts.'];
    }
    $db->execute('DELETE FROM community_posts WHERE post_id = ?', [$postId]);
    return [true, 'Post deleted.'];
}

/** Delete a comment (author or admin). */
function pa_community_delete_comment(Database $db, array $user, int $commentId): array
{
    if (!pa_community_can_manage_comment($db, $user, $commentId)) {
        return [false, 'You can only delete your own comments.'];
    }
    $row = $db->fetch('SELECT post_id FROM community_comments WHERE comment_id = ?', [$commentId]);
    $db->execute('DELETE FROM community_comments WHERE comment_id = ?', [$commentId]);
    if ($row) {
        $pid = (int) $row['post_id'];
        $db->execute('UPDATE community_posts SET comment_count = GREATEST(comment_count - 1, 0) WHERE post_id = ?', [$pid]);
    }
    return [true, 'Comment deleted.'];
}

/** Report a post or comment. Returns [ok, message]. */
function pa_community_report(Database $db, array $user, string $reason, ?int $postId, ?int $commentId): array
{
    $reason = pa_community_clean($reason, 500, true);
    if (mb_strlen($reason) < 5) {
        return [false, 'Please describe the problem (at least 5 characters).'];
    }
    if (!$postId && !$commentId) {
        return [false, 'Nothing to report.'];
    }
    $db->execute(
        'INSERT INTO community_reports (post_id, comment_id, reporter_id, reason, status, created_at)
         VALUES (?, ?, ?, ?, \'OPEN\', NOW())',
        [$postId ?: null, $commentId ?: null, (int) $user['user_id'], $reason]
    );
    return [true, 'Report sent to the admins.'];
}

/** Admin: hide / unhide / pin a post, or close a report. */
function pa_community_moderate(Database $db, array $user, string $op, int $targetId): array
{
    if (!pa_community_is_moderator((string) ($user['role'] ?? ''))) {
        return [false, 'Admin privileges required.'];
    }
    switch ($op) {
        case 'hide_post':
            $db->execute('UPDATE community_posts SET is_hidden = 1 WHERE post_id = ?', [$targetId]);
            return [true, 'Post hidden.'];
        case 'unhide_post':
            $db->execute('UPDATE community_posts SET is_hidden = 0 WHERE post_id = ?', [$targetId]);
            return [true, 'Post restored.'];
        case 'pin_post':
            $db->execute('UPDATE community_posts SET is_pinned = 1 WHERE post_id = ?', [$targetId]);
            return [true, 'Post pinned.'];
        case 'unpin_post':
            $db->execute('UPDATE community_posts SET is_pinned = 0 WHERE post_id = ?', [$targetId]);
            return [true, 'Post unpinned.'];
        case 'hide_comment':
            $db->execute('UPDATE community_comments SET is_hidden = 1 WHERE comment_id = ?', [$targetId]);
            return [true, 'Comment hidden.'];
        case 'close_report':
            $db->execute(
                'UPDATE community_reports SET status = \'RESOLVED\', resolved_by = ?, resolved_at = NOW() WHERE report_id = ?',
                [(int) $user['user_id'], $targetId]
            );
            return [true, 'Report resolved.'];
        case 'dismiss_report':
            $db->execute(
                'UPDATE community_reports SET status = \'DISMISSED\', resolved_by = ?, resolved_at = NOW() WHERE report_id = ?',
                [(int) $user['user_id'], $targetId]
            );
            return [true, 'Report dismissed.'];
        default:
            return [false, 'Unknown moderation action.'];
    }
}

/** Open reports (admin view). */
function pa_community_open_reports(Database $db, int $limit = 50): array
{
    return $db->fetchAll(
        'SELECT r.report_id, r.post_id, r.comment_id, r.reason, r.created_at,
                u.username AS reporter
         FROM community_reports r
         JOIN users u ON u.user_id = r.reporter_id
         WHERE r.status = \'OPEN\'
         ORDER BY r.created_at DESC
         LIMIT ' . max(1, min(200, $limit))
    );
}

/** Render post/comment text safely (escaped, newlines preserved). */
function pa_community_render_text(string $text): string
{
    $safe = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    // Highlight @all mentions (already escaped at this point).
    $safe = preg_replace('/@all\b/i', '<span class="pa-mention-all">@all</span>', $safe) ?? $safe;
    return nl2br($safe);
}
