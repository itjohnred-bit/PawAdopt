<?php
require_once __DIR__ . '/../../includes/functions.php';

requireRole('ADOPTER');
$pageTitle = 'Messages';
$user = getCurrentUser();
$db   = Database::getInstance();

$convs = $db->fetchAll(
    "SELECT c.*, sp.shelter_name AS other_name,
            (SELECT m.message_text FROM messages m WHERE m.conversation_id = c.conversation_id ORDER BY m.created_at DESC LIMIT 1) AS last_message,
            (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.conversation_id AND m.is_read = 0 AND m.sender_id != ?) AS unread_count,
            (SELECT MAX(m.created_at) FROM messages m WHERE m.conversation_id = c.conversation_id) AS last_message_at,
            p.name AS pet_name
     FROM conversations c
     JOIN shelter_profiles sp ON c.shelter_id = sp.shelter_id
     LEFT JOIN pets p ON c.pet_id = p.pet_id
     WHERE c.adopter_id = ?
     ORDER BY COALESCE((SELECT MAX(m.created_at) FROM messages m WHERE m.conversation_id = c.conversation_id), c.created_at) DESC",
    [$user['user_id'], $user['user_id']]
);

$activeConvId = (int) ($_GET['conv'] ?? ($convs[0]['conversation_id'] ?? 0));
$activeConv   = $activeConvId ? current(array_filter($convs, fn($c) => (int) $c['conversation_id'] === $activeConvId)) : null;

include __DIR__ . '/../../includes/header.php';
?>

<style>
.chat-shell{display:grid;grid-template-columns:340px minmax(0,1fr);gap:22px;min-height:calc(100vh - 220px)}
.chat-sidebar,.chat-main{background:#fff;border:1px solid #e6efec;border-radius:28px;box-shadow:0 18px 40px rgba(15,118,110,.08);overflow:hidden}
.chat-sidebar{display:flex;flex-direction:column}
.chat-main{display:flex;flex-direction:column;background:linear-gradient(180deg,#fcfffe 0%,#f6fbf9 100%)}
.chat-side-top{padding:22px 22px 14px;border-bottom:1px solid #edf2f0}
.chat-kicker{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:999px;background:#ecfeff;color:#0f766e;font-size:.74rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase}
.chat-side-title{margin:14px 0 6px;font-size:1.25rem;font-weight:900;color:#16302b}
.chat-side-copy{color:#6b7280;font-size:.88rem;line-height:1.55}
.chat-search{margin-top:14px;position:relative}
.chat-search input{width:100%;padding:12px 14px 12px 40px;border:1px solid #dce8e4;border-radius:16px;background:#f8fcfb;font:inherit}
.chat-search i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#7b8b86}
.chat-list{padding:10px;overflow:auto;display:grid;gap:8px}
.chat-item{display:flex;gap:12px;align-items:flex-start;padding:14px;border-radius:20px;background:#fff;border:1px solid transparent;cursor:pointer;transition:all .18s ease;position:relative}
.chat-item:hover{background:#f8fcfb;border-color:#e3eeea;transform:translateY(-1px)}
.chat-item.active{background:#effcf8;border-color:#bde8db;box-shadow:inset 4px 0 0 #0f766e}
.chat-avatar{width:48px;height:48px;border-radius:16px;background:linear-gradient(135deg,#0f766e,#115e59);color:#fff;display:grid;place-items:center;font-weight:900;flex:none}
.chat-item-body{min-width:0;flex:1}
.chat-item-row{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:4px}
.chat-name{font-weight:800;color:#16302b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chat-time{font-size:.74rem;color:#94a3b8;white-space:nowrap}
.chat-pet{font-size:.76rem;font-weight:700;color:#0f766e;margin-bottom:3px}
.chat-preview{font-size:.82rem;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chat-unread{min-width:22px;height:22px;padding:0 7px;border-radius:999px;background:#0f766e;color:#fff;font-size:.72rem;font-weight:800;display:inline-flex;align-items:center;justify-content:center}
.chat-empty-side{padding:40px 22px;text-align:center;color:#94a3b8}
.chat-main-top{padding:20px 24px;border-bottom:1px solid #e7efec;background:#fff;display:flex;justify-content:space-between;align-items:center;gap:14px}
.chat-main-user{display:flex;align-items:center;gap:14px;min-width:0}.chat-main-avatar{width:52px;height:52px;border-radius:18px;background:#e6f7f2;color:#0f766e;display:grid;place-items:center;font-weight:900}
.chat-main-name{font-size:1rem;font-weight:900;color:#16302b}.chat-main-sub{font-size:.83rem;color:#6b7280}
.chat-status-pill{display:inline-flex;align-items:center;gap:7px;padding:8px 12px;border-radius:999px;background:#ecfdf5;color:#047857;font-size:.76rem;font-weight:800}.chat-status-pill::before{content:'';width:8px;height:8px;border-radius:50%;background:#10b981}
.chat-thread{flex:1;overflow:auto;padding:24px;display:flex;flex-direction:column;gap:14px}
.chat-welcome{margin:auto;max-width:420px;text-align:center;color:#94a3b8}.chat-welcome i{font-size:3rem;color:#b7c9c3;margin-bottom:10px}
.msg-row{display:flex;flex-direction:column;max-width:min(76%,560px)}
.msg-row.me{align-self:flex-end;align-items:flex-end}.msg-row.other{align-self:flex-start;align-items:flex-start}
.msg-bubble{padding:13px 16px;border-radius:20px;font-size:.92rem;line-height:1.6;box-shadow:0 10px 24px rgba(15,23,42,.05);word-break:break-word}
.msg-row.me .msg-bubble{background:linear-gradient(135deg,#0f766e,#115e59);color:#fff;border-bottom-right-radius:8px}
.msg-row.other .msg-bubble{background:#fff;color:#1f2937;border:1px solid #e7efec;border-bottom-left-radius:8px}
.msg-meta{margin-top:6px;font-size:.72rem;color:#94a3b8;display:flex;gap:8px;align-items:center}
.chat-compose{padding:18px 22px;border-top:1px solid #e7efec;background:#fff}
.chat-compose-wrap{display:flex;gap:12px;align-items:flex-end;background:#f8fcfb;border:1px solid #dfe9e5;border-radius:24px;padding:10px 10px 10px 16px}
.chat-compose textarea{flex:1;min-height:24px;max-height:140px;border:none;background:transparent;resize:none;font:inherit;line-height:1.5;color:#16302b;outline:none;padding-top:6px}
.chat-send{width:46px;height:46px;border:none;border-radius:50%;background:#0f766e;color:#fff;display:grid;place-items:center;box-shadow:0 12px 24px rgba(15,118,110,.24)}
.chat-send:disabled{opacity:.55;box-shadow:none;cursor:not-allowed}.chat-empty-main{margin:auto;text-align:center;color:#94a3b8}.chat-empty-main i{font-size:3.3rem;margin-bottom:12px;color:#bfd3cc}
@media (max-width: 980px){.chat-shell{grid-template-columns:1fr;min-height:auto}.chat-sidebar{min-height:320px}.chat-main{min-height:560px}}
</style>

<div class="page-header">
    <h1 class="page-title"><span class="icon">💬</span> Messages</h1>
</div>

<div class="chat-shell">
    <aside class="chat-sidebar">
        <div class="chat-side-top">
            <span class="chat-kicker"><i class="fas fa-comments"></i> Inbox</span>
            <div class="chat-side-title">Talk to shelters</div>
            <div class="chat-side-copy">Track replies, pet-related questions, and adoption updates in one clean thread.</div>
            <div class="chat-search">
                <i class="fas fa-search"></i>
                <input type="text" id="conversationSearch" placeholder="Search shelter or pet name...">
            </div>
        </div>

        <div class="chat-list" id="conversationList">
            <?php if (empty($convs)): ?>
                <div class="chat-empty-side">
                    <div style="font-size:2.4rem;margin-bottom:8px">💬</div>
                    No conversations yet.
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <section class="chat-main">
        <?php if ($activeConvId && $activeConv): ?>
            <div class="chat-main-top">
                <div class="chat-main-user">
                    <div class="chat-main-avatar" id="chatPartnerAvatar"><?= strtoupper(substr($activeConv['other_name'], 0, 1)) ?></div>
                    <div>
                        <div class="chat-main-name" id="chatPartnerName"><?= sanitize($activeConv['other_name']) ?></div>
                        <div class="chat-main-sub" id="chatPartnerMeta"><?= $activeConv['pet_name'] ? 'Discussing: ' . sanitize($activeConv['pet_name']) : 'General adoption inquiry' ?></div>
                    </div>
                </div>
                <span class="chat-status-pill">Conversation active</span>
            </div>
            <div class="chat-thread" id="chatMessages">
                <div style="text-align:center;color:var(--gray-mid);padding:20px"><i class="fas fa-spinner fa-spin"></i> Loading…</div>
            </div>
            <div class="chat-compose">
                <div class="chat-compose-wrap">
                    <textarea id="messageInput" placeholder="Type your message..." rows="1"></textarea>
                    <button id="sendMessageBtn" class="chat-send" type="button" aria-label="Send message"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        <?php else: ?>
            <div class="chat-empty-main">
                <i class="fas fa-comment-dots"></i>
                <div style="font-weight:800;font-size:1.05rem">Select a conversation</div>
                <div style="font-size:.9rem">Browse pets to start a message with a shelter.</div>
            </div>
        <?php endif; ?>
    </section>
</div>

<script>
(function(){
    const endpoint = '<?= APP_URL ?>/api/messages.php';
    const currentUserId = <?= (int) $user['user_id'] ?>;
    const initialConvs = <?= json_encode(array_values($convs), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const initialActiveConvId = <?= (int) $activeConvId ?>;
    const listEl = document.getElementById('conversationList');
    const searchEl = document.getElementById('conversationSearch');
    const threadEl = document.getElementById('chatMessages');
    const nameEl = document.getElementById('chatPartnerName');
    const metaEl = document.getElementById('chatPartnerMeta');
    const avatarEl = document.getElementById('chatPartnerAvatar');
    const inputEl = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendMessageBtn');
    let conversations = initialConvs || [];
    let activeConvId = initialActiveConvId || 0;

    function esc(text){
        return String(text || '').replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }
    function formatTime(value){
        if (!value) return '';
        const d = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(d.getTime())) return '';
        return d.toLocaleDateString([], {month:'short', day:'numeric'});
    }
    function autoGrow(){
        if (!inputEl) return;
        inputEl.style.height = '24px';
        inputEl.style.height = Math.min(inputEl.scrollHeight, 140) + 'px';
    }
    function renderConversations(items){
        if (!listEl) return;
        if (!items.length) {
            listEl.innerHTML = '<div class="chat-empty-side"><div style="font-size:2.4rem;margin-bottom:8px">💬</div>No matching conversations.</div>';
            return;
        }
        listEl.innerHTML = items.map((c) => `
            <div class="chat-item ${Number(c.conversation_id) === Number(activeConvId) ? 'active' : ''}" data-conv="${Number(c.conversation_id)}">
                <div class="chat-avatar">${esc((c.other_name || '?').charAt(0).toUpperCase())}</div>
                <div class="chat-item-body">
                    <div class="chat-item-row">
                        <div class="chat-name">${esc(c.other_name || 'Conversation')}</div>
                        <div style="display:flex;align-items:center;gap:8px">
                            ${c.unread_count > 0 ? `<span class="chat-unread">${Number(c.unread_count)}</span>` : ''}
                            <span class="chat-time">${esc(formatTime(c.last_message_at || c.created_at))}</span>
                        </div>
                    </div>
                    <div class="chat-pet">${c.pet_name ? `🐾 ${esc(c.pet_name)}` : 'General inquiry'}</div>
                    <div class="chat-preview">${esc(c.last_message || 'Start the conversation!')}</div>
                </div>
            </div>
        `).join('');
        listEl.querySelectorAll('.chat-item').forEach(item => {
            item.addEventListener('click', () => openConversation(Number(item.dataset.conv)));
        });
    }
    function updateHeader(conv){
        if (!conv || !nameEl || !metaEl || !avatarEl) return;
        nameEl.textContent = conv.other_name || 'Conversation';
        metaEl.textContent = conv.pet_name ? `Discussing: ${conv.pet_name}` : 'General adoption inquiry';
        avatarEl.textContent = (conv.other_name || '?').charAt(0).toUpperCase();
    }
    async function fetchMessages(convId){
        if (!threadEl) return;
        threadEl.innerHTML = '<div style="text-align:center;color:var(--gray-mid);padding:20px"><i class="fas fa-spinner fa-spin"></i> Loading…</div>';
        const res = await fetch(`${endpoint}?action=get_messages&conv_id=${convId}`, { credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Failed to load messages.');
        const rows = Array.isArray(data.data) ? data.data : [];
        if (!rows.length) {
            threadEl.innerHTML = '<div class="chat-welcome"><i class="fas fa-paw"></i><div style="font-weight:800;margin-bottom:6px">No messages yet</div><div>Say hello and ask about the pet, schedule, or adoption requirements.</div></div>';
            return;
        }
        threadEl.innerHTML = rows.map((m) => {
            const mine = Number(m.sender_user_id) === Number(currentUserId);
            return `
                <div class="msg-row ${mine ? 'me' : 'other'}">
                    <div class="msg-bubble">${esc(m.message_text || '')}</div>
                    <div class="msg-meta">
                        <span>${esc(m.time_ago || '')}</span>
                        <span>${esc(formatTime(m.created_at))}</span>
                    </div>
                </div>
            `;
        }).join('');
        threadEl.scrollTop = threadEl.scrollHeight;
    }
    async function refreshConversations(){
        const res = await fetch(`${endpoint}?action=conversations`, { credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Failed to load conversations.');
        conversations = Array.isArray(data.data) ? data.data : [];
        filterConversations();
    }
    async function openConversation(convId){
        activeConvId = convId;
        const conv = conversations.find((c) => Number(c.conversation_id) === Number(convId));
        updateHeader(conv);
        renderConversations(filteredConversations());
        history.replaceState({}, '', `?conv=${convId}`);
        await fetchMessages(convId);
        await refreshConversations();
    }
    function filteredConversations(){
        const q = (searchEl?.value || '').trim().toLowerCase();
        if (!q) return conversations;
        return conversations.filter((c) => `${c.other_name || ''} ${c.pet_name || ''} ${c.last_message || ''}`.toLowerCase().includes(q));
    }
    function filterConversations(){
        renderConversations(filteredConversations());
    }
    async function sendMessage(){
        if (!activeConvId || !inputEl || !sendBtn) return;
        const message = inputEl.value.trim();
        if (!message) return;
        sendBtn.disabled = true;
        try {
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'send', conv_id: activeConvId, message })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to send message.');
            inputEl.value = '';
            autoGrow();
            await Promise.all([fetchMessages(activeConvId), refreshConversations()]);
        } catch (error) {
            if (typeof showToast === 'function') showToast(error.message || 'Failed to send message.', 'error');
        } finally {
            sendBtn.disabled = false;
        }
    }

    searchEl?.addEventListener('input', filterConversations);
    inputEl?.addEventListener('input', autoGrow);
    inputEl?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    });
    sendBtn?.addEventListener('click', sendMessage);

    renderConversations(conversations);
    autoGrow();
    if (activeConvId && conversations.some((c) => Number(c.conversation_id) === Number(activeConvId))) {
        openConversation(activeConvId).catch(() => {});
    }
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
