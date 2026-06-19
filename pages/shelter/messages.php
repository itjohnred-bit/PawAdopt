<?php
/**
 * Shelter Messages Page
 * Corrected pathing and SQL logic
 */
// 1. Corrected Path to includes
require_once __DIR__ . '/../../includes/functions.php';

startSession(); // Added startSession if not in functions.php

// Ensure only Shelters/Veterinaries can access this page
requireRole('SHELTER');

$pageTitle = 'Shelter Messages';
$user = getCurrentUser();
$db   = Database::getInstance();

// 2. FIXED: Corrected column name from user_id to shelter_id to match your database schema
$shelter = $db->fetch("SELECT shelter_id, shelter_name FROM shelter_profiles WHERE shelter_id = ?", [$user['user_id']]);
$shelterId = $shelter['shelter_id'] ?? 0;

// 3. Fetch all conversations involving this shelter
$convs = $db->fetchAll(
    "SELECT c.*, u.username as other_name, u.email as adopter_email,
     (SELECT m.message_text FROM messages m WHERE m.conversation_id = c.conversation_id ORDER BY m.created_at DESC LIMIT 1) as last_message,
     (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.conversation_id AND m.is_read = 0 AND m.sender_user_id != ?) as unread_count,
     p.name as pet_name
     FROM conversations c
     JOIN users u ON c.adopter_id = u.user_id
     LEFT JOIN pets p ON c.pet_id = p.pet_id
     WHERE c.shelter_id = ?
     ORDER BY c.created_at DESC",
    [$user['user_id'], $shelterId]
);

// Determine which conversation is currently open
$activeConvId = (int)($_GET['conv'] ?? ($convs[0]['conversation_id'] ?? 0));
$activeConv   = $activeConvId ? current(array_filter($convs, fn($c) => $c['conversation_id'] == $activeConvId)) : null;

// 4. FIXED: Corrected path for includes (going up two levels to root)
include __DIR__ . '/../../includes/header.php';
?>

<!-- 5. FIXED: Corrected path for JS asset (going up two levels to root) -->
<script src="../../assets/js/app.js?v=<?= time() ?>"></script>

<div class="page-header">
    <h1 class="page-title"><span class="icon">📩</span> Shelter Inbox</h1>
    <p class="text-muted">Manage inquiries from potential adopters regarding your pets.</p>
</div>

<div class="msg-layout" style="height: calc(100vh - 220px); min-height: 600px; display: flex; border: 1px solid #ddd; border-radius: 12px; overflow: hidden; background: #fff; margin-bottom: 20px;">
    
    <!-- Left Column: Conversations List -->
    <div class="conv-list" style="width: 350px; border-right: 1px solid #ddd; display: flex; flex-direction: column;">
        <div class="conv-list-header" style="padding: 20px; border-bottom: 1px solid #ddd; font-weight: 700; background: #f8f9fa;">
            Inquiries (<?= count($convs) ?>)
        </div>
        
        <div class="conv-scrollbox" style="flex: 1; overflow-y: auto;">
            <?php if (empty($convs)): ?>
                <div class="notif-empty" style="padding: 40px 20px; text-align: center; color: #999;">
                    <div style="font-size: 3rem; margin-bottom: 10px;">📮</div>
                    <p>No messages yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($convs as $c): ?>
                    <div class="conv-item <?= $c['conversation_id'] == $activeConvId ? 'active' : '' ?>"
                         data-conv="<?= $c['conversation_id'] ?>"
                         onclick="selectConversation(<?= $c['conversation_id'] ?>,'<?= addslashes(sanitize($c['other_name'])) ?>')"
                         style="padding: 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s;">
                        
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <div class="conv-avatar" style="width: 45px; height: 45px; border-radius: 50%; background: #2d6a4f; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0;">
                                <?= strtoupper(substr($c['other_name'], 0, 1)) ?>
                            </div>
                            <div style="flex: 1; overflow: hidden;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                    <span style="font-weight: 700; font-size: 0.95rem;"><?= sanitize($c['other_name']) ?></span>
                                    <?php if ($c['unread_count'] > 0): ?>
                                        <span style="background: #e63946; color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem;"><?= $c['unread_count'] ?></span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.8rem; color: #2d6a4f; font-weight: 600;">
                                    <?= $c['pet_name'] ? '🐾 ' . sanitize($c['pet_name']) : 'General Inquiry' ?>
                                </div>
                                <div style="font-size: 0.82rem; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= sanitize($c['last_message'] ?? 'New Inquiry') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Chat Content -->
    <div class="chat-pane" style="flex: 1; display: flex; flex-direction: column; background: #fcfcfc;">
        <?php if ($activeConvId && $activeConv): ?>
            <div class="chat-header" style="padding: 15px 25px; border-bottom: 1px solid #ddd; background: #fff; display: flex; align-items: center; gap: 15px;">
                <div class="avatar-circle" style="width: 40px; height: 40px; border-radius: 50%; background: #eee; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                    <?= strtoupper(substr($activeConv['other_name'], 0, 1)) ?>
                </div>
                <div style="flex: 1;">
                    <div id="chatPartnerName" style="font-weight: 700;"><?= sanitize($activeConv['other_name']) ?></div>
                    <div style="font-size: 0.75rem; color: #888;"><?= sanitize($activeConv['adopter_email']) ?></div>
                </div>
            </div>

            <div class="chat-messages" id="chatMessages" style="flex: 1; padding: 25px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px;">
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin"></i> Loading Messages...
                </div>
            </div>

            <div class="chat-input-bar" style="padding: 20px; background: #fff; border-top: 1px solid #ddd; display: flex; gap: 10px;">
                <input type="text" id="messageInput" class="form-control" placeholder="Type your message..." style="flex: 1; border-radius: 25px; padding: 10px 20px; border: 1px solid #ddd;">
                <button onclick="sendMessage()" class="btn btn-primary" style="border-radius: 50%; width: 45px; height: 45px; padding: 0; background: #2d6a4f; border: none; color: #fff;">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        <?php else: ?>
            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #999;">
                <i class="fas fa-comments" style="font-size: 4rem; margin-bottom: 15px; opacity: 0.3;"></i>
                <div style="font-weight: 700;">Select an inquiry to chat</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.body.dataset.userId = '<?= $user['user_id'] ?>';

<?php if ($activeConvId && $activeConv): ?>
document.addEventListener('DOMContentLoaded', () => {
    selectConversation(<?= $activeConvId ?>, '<?= addslashes(sanitize($activeConv['other_name'])) ?>');
});
<?php endif; ?>
</script>

<?php 
// 6. FIXED: Corrected path for footer (two levels up)
include __DIR__ . '/../../includes/footer.php';

?>
