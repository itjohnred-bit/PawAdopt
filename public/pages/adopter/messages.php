<?php
require_once __DIR__ . '/../../includes/functions.php';

requireRole('ADOPTER');
$pageTitle = 'Messages';
$user = getCurrentUser();
$db   = Database::getInstance();

$convs = $db->fetchAll(
    "SELECT c.*, sp.veterinary_name as other_name,
     (SELECT m.message_text FROM messages m WHERE m.conversation_id = c.conversation_id ORDER BY m.created_at DESC LIMIT 1) as last_message,
     (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.conversation_id AND m.is_read = 0 AND m.sender_id != ?) as unread_count,
     p.name as pet_name
     FROM conversations c
     JOIN veterinary_profiles sp ON c.veterinary_id = sp.veterinary_id
     LEFT JOIN pets p ON c.pet_id = p.pet_id
     WHERE c.adopter_id = ?
     ORDER BY c.created_at DESC",
    [$user['user_id'], $user['user_id']]
);

$activeConvId = (int)($_GET['conv'] ?? ($convs[0]['conversation_id'] ?? 0));
$activeConv   = $activeConvId ? current(array_filter($convs, fn($c) => $c['conversation_id'] == $activeConvId)) : null;

include __DIR__ . '/../../includes/header.php';
?>

<script src="../../assets/js/app.js?v=<?= time() ?>"></script>

<div class="page-header">
    <h1 class="page-title"><span class="icon">💬</span> Messages</h1>
</div>

<div class="msg-layout">
    <div class="conv-list">
        <div class="conv-list-header">Conversations (<?= count($convs) ?>)</div>
        <div class="conv-items-container" style="flex: 1; overflow-y: auto;">
            <?php if (empty($convs)): ?>
            <div class="notif-empty" style="padding:30px;text-align:center;color:var(--gray-mid)">
                <div style="font-size:2.5rem;margin-bottom:8px">💬</div>
                <div>No conversations yet.</div>
            </div>
            <?php else: ?>
                <?php foreach ($convs as $c): ?>
                <?php $jsName = json_encode(sanitize($c['other_name'])); ?>
                <div class="conv-item <?= $c['conversation_id'] == $activeConvId ? 'active' : '' ?>"
                     data-conv="<?= (int)$c['conversation_id'] ?>"
                     onclick='selectConversation(<?= (int)$c['conversation_id'] ?>, <?= $jsName ?>)'>
                    <div class="conv-avatar"><?= strtoupper(substr($c['other_name'],0,1)) ?></div>
                    <div style="flex:1;overflow:hidden">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="conv-name" style="font-weight: 700;"><?= sanitize($c['other_name']) ?></div>
                            <?php if ($c['unread_count'] > 0): ?>
                            <span class="badge-inline" style="background: var(--teal); color: #fff; padding: 2px 6px; border-radius: 10px; font-size: 0.7rem;"><?= $c['unread_count'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="conv-preview" style="font-size: 0.8rem; color: var(--teal); font-weight: 600;">
                            <?= $c['pet_name'] ? '🐾 '.sanitize($c['pet_name']) : '' ?>
                        </div>
                        <div class="conv-last-msg" style="font-size: 0.82rem; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?= sanitize($c['last_message'] ?? 'Start the conversation!') ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="chat-pane">
        <?php if ($activeConvId && $activeConv): ?>
            <div class="chat-header">
                <div class="avatar-circle" style="width: 40px; height: 40px; border-radius: 50%; background: var(--teal); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; margin-right: 12px;">
                    <?= strtoupper(substr($activeConv['other_name'], 0, 1)) ?>
                </div>
                <div>
                    <div id="chatPartnerName" style="font-weight: 800;"><?= sanitize($activeConv['other_name']) ?></div>
                </div>
            </div>
            <div class="chat-messages" id="chatMessages">
                <div style="text-align:center;color:var(--gray-mid);padding:20px"><i class="fas fa-spinner fa-spin"></i> Loading…</div>
            </div>
            <div class="chat-input-bar">
                <input type="text" id="messageInput" class="form-control" placeholder="Type a message…" onkeypress="if(event.key === 'Enter') sendMessage()">
                <button onclick="sendMessage()" class="btn btn-primary"><i class="fas fa-paper-plane"></i></button>
            </div>
        <?php else: ?>
            <div class="chat-no-conv">
                <i class="fas fa-comment-dots"></i>
                <div style="font-weight:700">Select a conversation</div>
                <div style="font-size:.88rem">or browse pets to message a veterinary</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
window.currentConvId = <?= (int)$activeConvId ?>;
document.body.setAttribute('data-user-id', '<?= (int)$user['user_id'] ?>');

<?php if ($activeConvId && $activeConv): ?>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof selectConversation === 'function') {
        selectConversation(<?= (int)$activeConvId ?>, <?= json_encode(sanitize($activeConv['other_name'])) ?>);
    }
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
