<?php
/**
 * PawAdopt — Community (feed, composer, comments, moderation).
 *
 * Access: requireLogin() — the PAGE itself needs a session, and every write
 * goes through api/community.php which re-checks the session, CSRF, role and
 * ownership server-side. Adopters cannot publish ANNOUNCEMENT/SYSTEM posts and
 * cannot use @all; that is enforced in includes/community.php, not here.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/community.php';
requireLogin();

$user = getCurrentUser();
$role = strtoupper((string) ($user['role'] ?? ''));
$db   = Database::getInstance();

$pageTitle  = 'Community';
$allowed    = pa_community_allowed_types($role);
$canAll     = pa_community_can_announce($role);
$isAdmin    = pa_community_is_moderator($role);
$csrf       = generateCsrfToken();

$filter = strtoupper(trim((string) ($_GET['filter'] ?? 'ALL')));
if (!in_array($filter, ['ALL', 'EXPERIENCE', 'INQUIRY', 'ANNOUNCEMENT', 'SYSTEM'], true)) {
    $filter = 'ALL';
}

try {
    $posts   = pa_community_posts($db, $filter === 'ALL' ? 'all' : $filter, 30, 0);
    $reports = $isAdmin ? pa_community_open_reports($db) : [];
} catch (Throwable $e) {
    $posts   = [];
    $reports = [];
    $migrationNeeded = true;
}

include __DIR__ . '/../includes/header.php';
?>
<style>
.cm-wrap { max-width: 900px; margin: 0 auto; }
.cm-hero { background: linear-gradient(135deg, #165B4C, #1F7261 55%, #134E43); color: #fff; border-radius: 20px; padding: 22px 24px; margin-bottom: 20px; }
.cm-hero h1 { margin: 0 0 6px; font-size: 1.5rem; font-weight: 900; }
.cm-hero p { margin: 0; font-size: .9rem; opacity: .92; }
.cm-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin: 0 0 18px; }
.cm-tab { padding: 8px 16px; border-radius: 999px; background: #fff; border: 1px solid #dbe7e4; color: #0f766e; font-weight: 800; font-size: .84rem; text-decoration: none; }
.cm-tab.active { background: #165B4C; border-color: #165B4C; color: #fff; }
.cm-card { background: #fff; border: 1px solid #e6efeb; border-radius: 16px; padding: 20px; margin-bottom: 16px; box-shadow: 0 8px 22px rgba(15,118,110,.06); }
.cm-composer h2 { margin: 0 0 14px; font-size: 1.05rem; font-weight: 900; color: #134e4a; }
.cm-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
.cm-field label { display: block; font-size: .74rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #4b5563; margin-bottom: 6px; }
.cm-field input, .cm-field select, .cm-field textarea { width: 100%; box-sizing: border-box; border: 1px solid #d6e3df; border-radius: 12px; padding: 11px 13px; font: inherit; background: #fcfefe; }
.cm-field textarea { min-height: 110px; resize: vertical; }
.cm-hint { font-size: .76rem; color: #6b7280; margin-top: 6px; }
.cm-btn { border: none; border-radius: 999px; padding: 11px 22px; font-weight: 800; font-size: .88rem; cursor: pointer; background: #165B4C; color: #fff; }
.cm-btn:hover:not(:disabled) { background: #134E43; }
.cm-btn:disabled { background: #9ca3af; cursor: not-allowed; }
.cm-btn-ghost { background: #f1f5f4; color: #134e4a; }
.cm-btn-ghost:hover:not(:disabled) { background: #e2ebe8; }
.cm-msg { margin-top: 12px; font-weight: 700; font-size: .86rem; display: none; }
.cm-msg.ok { color: #047857; display: block; }
.cm-msg.err { color: #b91c1c; display: block; }

.cm-post { background: #fff; border: 1px solid #e6efeb; border-radius: 16px; padding: 18px 20px; margin-bottom: 14px; box-shadow: 0 6px 18px rgba(15,118,110,.05); }
.cm-post.pinned { border-color: #f59e0b; box-shadow: 0 6px 18px rgba(245,158,11,.16); }
.cm-post-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
.cm-chip { display: inline-flex; align-items: center; gap: 6px; font-size: .68rem; font-weight: 900; letter-spacing: .05em; text-transform: uppercase; padding: 4px 10px; border-radius: 999px; }
.cm-chip.EXPERIENCE { background: #ecfdf5; color: #047857; }
.cm-chip.INQUIRY { background: #eff6ff; color: #1d4ed8; }
.cm-chip.ANNOUNCEMENT { background: #fff7ed; color: #b45309; }
.cm-chip.SYSTEM { background: #f5f3ff; color: #6d28d9; }
.cm-author { font-size: .8rem; color: #6b7280; font-weight: 700; }
.cm-author .role { color: #165B4C; }
.cm-title { margin: 0 0 8px; font-size: 1.08rem; font-weight: 900; color: #111827; }
.cm-body { font-size: .92rem; line-height: 1.65; color: #374151; word-break: break-word; }
.cm-mention-all { background: #fef3c7; color: #92400e; font-weight: 900; padding: 0 4px; border-radius: 4px; }
.cm-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; padding-top: 12px; border-top: 1px solid #f1f5f4; }
.cm-link-btn { background: none; border: none; color: #0f766e; font-weight: 800; font-size: .8rem; cursor: pointer; padding: 4px 8px; border-radius: 8px; }
.cm-link-btn:hover { background: #f0fdfa; }
.cm-link-btn.danger { color: #b91c1c; }
.cm-comments { margin-top: 12px; display: none; }
.cm-comments.open { display: block; }
.cm-comment { padding: 10px 0; border-top: 1px solid #f1f5f4; font-size: .88rem; color: #374151; }
.cm-comment .who { font-weight: 900; color: #134e4a; font-size: .8rem; }
.cm-empty { text-align: center; color: #6b7280; padding: 40px 20px; }
.cm-reports { border-left: 4px solid #f59e0b; }
@media (max-width: 720px) { .cm-row { grid-template-columns: 1fr; } }
</style>

<div class="pa-page">
<div class="cm-wrap">

    <div class="cm-hero">
        <h1>🐾 Community</h1>
        <p>Adopters share adoption experiences and ask questions. Shelters, veterinary accounts and admins post announcements.
        <?php if (!$canAll): ?>Community posts never display your email or phone number.<?php endif; ?></p>
    </div>

    <?php if (!empty($migrationNeeded)): ?>
    <div class="cm-card" style="border-color:#f59e0b;">
        <strong>Community tables are not installed yet.</strong>
        <p class="cm-hint">Run <code>sql/community.sql</code> against the database, then reload this page.</p>
    </div>
    <?php endif; ?>

    <div class="cm-tabs">
        <?php
        $tabs = ['ALL' => 'All posts'];
        foreach (['EXPERIENCE' => 'Adoption stories', 'INQUIRY' => 'Questions', 'ANNOUNCEMENT' => 'Announcements', 'SYSTEM' => 'System updates'] as $k => $label) {
            $tabs[$k] = $label;
        }
        foreach ($tabs as $k => $label): ?>
            <a class="cm-tab <?= $filter === $k ? 'active' : '' ?>"
               href="<?= APP_URL ?>/pages/community.php?filter=<?= $k ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
    </div>

    <!-- ══ COMPOSER ══ -->
    <div class="cm-card cm-composer">
        <h2>✍️ Create a post</h2>
        <form id="cmPostForm" autocomplete="off" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div class="cm-row">
                <div class="cm-field">
                    <label for="cmType">Post type</label>
                    <select id="cmType" name="post_type">
                        <?php foreach ($allowed as $t): ?>
                            <option value="<?= $t ?>"><?= htmlspecialchars(pa_community_type_label($t), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cm-field">
                    <label for="cmTitle">Title</label>
                    <input id="cmTitle" name="title" type="text" maxlength="160" placeholder="Short, clear title" required>
                </div>
            </div>
            <div class="cm-field">
                <label for="cmBody">Message</label>
                <textarea id="cmBody" name="body" maxlength="4000" placeholder="<?= $role === 'ADOPTER' ? 'Share your adoption story, or ask the community a question (food, meds, behaviour…)' : 'Write your announcement or update…' ?>" required></textarea>
                <p class="cm-hint">
                    <?php if ($canAll): ?>
                        Type <code>@all</code> to notify every active user about this post.
                    <?php else: ?>
                        Your post is public to signed-in members. Do not include personal contact details.
                    <?php endif; ?>
                </p>
            </div>
            <div style="display:flex;justify-content:flex-end;">
                <button class="cm-btn" type="submit" id="cmSubmit">Publish post</button>
            </div>
            <p class="cm-msg" id="cmMsg"></p>
        </form>
    </div>

    <!-- ══ ADMIN REPORTS ══ -->
    <?php if ($isAdmin && $reports): ?>
    <div class="cm-card cm-reports">
        <h2 style="margin:0 0 12px;font-size:1rem;font-weight:900;color:#92400e;">🚩 Open reports (<?= count($reports) ?>)</h2>
        <?php foreach ($reports as $r): ?>
            <div style="padding:8px 0;border-top:1px solid #fef3c7;font-size:.86rem;">
                <strong><?= htmlspecialchars($r['reporter'], ENT_QUOTES, 'UTF-8') ?></strong>
                — <?= htmlspecialchars($r['reason'], ENT_QUOTES, 'UTF-8') ?>
                <span class="cm-author">(<?= $r['post_id'] ? 'post #' . (int) $r['post_id'] : 'comment #' . (int) $r['comment_id'] ?>)</span>
                <button class="cm-link-btn" data-mod="close_report" data-id="<?= (int) $r['report_id'] ?>">Resolve</button>
                <button class="cm-link-btn danger" data-mod="dismiss_report" data-id="<?= (int) $r['report_id'] ?>">Dismiss</button>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ══ FEED ══ -->
    <div id="cmFeed">
    <?php if (!$posts): ?>
        <div class="cm-card cm-empty"><div style="font-size:2rem;">🐾</div><p>No posts yet in this filter. Be the first to share.</p></div>
    <?php else: foreach ($posts as $p):
        $mine = (int) $p['user_id'] === (int) $user['user_id'];
        $type = strtoupper($p['post_type']); ?>
        <article class="cm-post <?= (int) $p['is_pinned'] === 1 ? 'pinned' : '' ?>" id="post-<?= (int) $p['post_id'] ?>">
            <div class="cm-post-head">
                <span class="cm-chip <?= $type ?>"><i class="fas <?= pa_community_type_icon($type) ?>"></i> <?= htmlspecialchars(pa_community_type_label($type), ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ((int) $p['is_pinned'] === 1): ?><span class="cm-chip ANNOUNCEMENT">📌 Pinned</span><?php endif; ?>
                <span class="cm-author"><?= htmlspecialchars($p['username'], ENT_QUOTES, 'UTF-8') ?>
                    <span class="role">· <?= htmlspecialchars(strtolower((string) $p['role']), ENT_QUOTES, 'UTF-8') ?></span>
                    · <?= htmlspecialchars((string) $p['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <h3 class="cm-title"><?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?></h3>
            <div class="cm-body"><?= pa_community_render_text($p['body']) ?></div>
            <div class="cm-actions">
                <button class="cm-link-btn" data-toggle-comments="<?= (int) $p['post_id'] ?>">💬 <?= (int) $p['comment_count'] ?> comments</button>
                <button class="cm-link-btn" data-comment="<?= (int) $p['post_id'] ?>">Add comment</button>
                <button class="cm-link-btn" data-report-post="<?= (int) $p['post_id'] ?>">🚩 Report</button>
                <?php if ($mine || $isAdmin): ?>
                    <button class="cm-link-btn danger" data-del-post="<?= (int) $p['post_id'] ?>">Delete</button>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                    <button class="cm-link-btn" data-mod="pin_post" data-id="<?= (int) $p['post_id'] ?>">📌 Pin</button>
                    <button class="cm-link-btn" data-mod="unpin_post" data-id="<?= (int) $p['post_id'] ?>">Unpin</button>
                    <button class="cm-link-btn danger" data-mod="hide_post" data-id="<?= (int) $p['post_id'] ?>">Hide</button>
                <?php endif; ?>
            </div>
            <div class="cm-comments" id="comments-<?= (int) $p['post_id'] ?>"></div>
        </article>
    <?php endforeach; endif; ?>
    </div>
</div>
</div>

<script>
(function () {
    'use strict';
    var API = '<?= APP_URL ?>/api/community.php';
    var CSRF = <?= json_encode($csrf) ?>;

    function msg(el, text, ok) {
        el.textContent = text;
        el.className = 'cm-msg ' + (ok ? 'ok' : 'err');
    }
    function post(action, data) {
        var fd = new FormData();
        fd.append('csrf_token', CSRF);
        Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        return fetch(API + '?action=' + action, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { success: false, message: 'Unexpected server response.' }; }); });
    }

    /* ---- create post: disable button on first click ---- */
    var form = document.getElementById('cmPostForm');
    var submit = document.getElementById('cmSubmit');
    var msgEl = document.getElementById('cmMsg');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (submit.disabled) return;
            var title = document.getElementById('cmTitle').value.trim();
            var body  = document.getElementById('cmBody').value.trim();
            if (title.length < 4) { msg(msgEl, 'Title needs at least 4 characters.', false); return; }
            if (body.length < 10) { msg(msgEl, 'Message needs at least 10 characters.', false); return; }
            submit.disabled = true;
            submit.textContent = 'Publishing…';
            post('create_post', {
                post_type: document.getElementById('cmType').value,
                title: title,
                body: body
            }).then(function (r) {
                if (r.success) {
                    msg(msgEl, r.message || 'Posted.', true);
                    setTimeout(function () { location.reload(); }, 700);
                } else {
                    msg(msgEl, r.message || 'Could not post.', false);
                    submit.disabled = false;
                    submit.textContent = 'Publish post';
                }
            }).catch(function () {
                msg(msgEl, 'Network error. Please retry.', false);
                submit.disabled = false;
                submit.textContent = 'Publish post';
            });
        });
    }

    /* ---- comments ---- */
    function loadComments(postId, box) {
        box.innerHTML = '<p class="cm-hint">Loading comments…</p>';
        fetch(API + '?action=comments&post_id=' + postId, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (!r.success) { box.innerHTML = '<p class="cm-hint">Could not load comments.</p>'; return; }
                var html = '';
                (r.comments || []).forEach(function (c) {
                    html += '<div class="cm-comment"><span class="who">' + esc(c.author) + ' · ' + esc(c.role.toLowerCase()) + '</span>' +
                            '<div>' + c.html + '</div></div>';
                });
                if (!html) { html = '<p class="cm-hint">No comments yet.</p>'; }
                html += '<div style="display:flex;gap:8px;margin-top:10px;">' +
                        '<input class="cm-field" style="flex:1;border:1px solid #d6e3df;border-radius:12px;padding:10px 12px;" id="cmc-' + postId + '" maxlength="1500" placeholder="Write a comment…">' +
                        '<button class="cm-btn" type="button" data-send-comment="' + postId + '">Send</button></div>';
                box.innerHTML = html;
            });
    }
    function esc(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    document.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target : null;
        if (!t) return;

        var toggle = t.closest('[data-toggle-comments]');
        if (toggle) {
            var pid = toggle.getAttribute('data-toggle-comments');
            var box = document.getElementById('comments-' + pid);
            box.classList.toggle('open');
            if (box.classList.contains('open')) loadComments(pid, box);
            return;
        }

        var cbtn = t.closest('[data-comment]');
        if (cbtn) {
            var pid2 = cbtn.getAttribute('data-comment');
            var box2 = document.getElementById('comments-' + pid2);
            box2.classList.add('open');
            loadComments(pid2, box2);
            return;
        }

        var send = t.closest('[data-send-comment]');
        if (send) {
            var pid3 = send.getAttribute('data-send-comment');
            var input = document.getElementById('cmc-' + pid3);
            var val = (input && input.value || '').trim();
            if (val.length < 2) return;
            send.disabled = true;
            send.textContent = 'Sending…';
            post('add_comment', { post_id: pid3, body: val }).then(function (r) {
                if (r.success) { location.reload(); }
                else { send.disabled = false; send.textContent = 'Send'; alert(r.message || 'Could not comment.'); }
            });
            return;
        }

        var del = t.closest('[data-del-post]');
        if (del) {
            if (!confirm('Delete this post? This cannot be undone.')) return;
            del.disabled = true;
            post('delete_post', { post_id: del.getAttribute('data-del-post') }).then(function (r) {
                if (r.success) { location.reload(); }
                else { del.disabled = false; alert(r.message || 'Could not delete.'); }
            });
            return;
        }

        var rep = t.closest('[data-report-post]');
        if (rep) {
            var reason = prompt('Why are you reporting this post?');
            if (!reason) return;
            rep.disabled = true;
            post('report', { post_id: rep.getAttribute('data-report-post'), reason: reason }).then(function (r) {
                rep.disabled = false;
                alert(r.message || (r.success ? 'Reported.' : 'Could not report.'));
            });
            return;
        }

        var mod = t.closest('[data-mod]');
        if (mod) {
            mod.disabled = true;
            post('moderate', { op: mod.getAttribute('data-mod'), target_id: mod.getAttribute('data-id') }).then(function (r) {
                if (r.success) { location.reload(); }
                else { mod.disabled = false; alert(r.message || 'Action failed.'); }
            });
        }
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
