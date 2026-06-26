<?php
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireLogin();

$db = Database::getInstance();

$jsonInput = json_decode(file_get_contents('php://input'), true);
if ($jsonInput) {
    $_POST = array_merge($_POST, $jsonInput);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user   = getCurrentUser();

switch ($action) {

    case 'conversations':
        if ($user['role'] === 'ADOPTER') {
            $convs = $db->fetchAll(
                "SELECT c.*, sp.shelter_name as other_name,
                 (SELECT m.message_text FROM messages m WHERE m.conversation_id = c.conversation_id ORDER BY m.created_at DESC LIMIT 1) as last_message,
                 (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.conversation_id AND m.is_read = 0 AND m.sender_id != ?) as unread_count,
                 p.name as pet_name
                 FROM conversations c
                 JOIN shelter_profiles sp ON c.shelter_id = sp.shelter_id
                 LEFT JOIN pets p ON c.pet_id = p.pet_id
                 WHERE c.adopter_id = ?
                 ORDER BY c.created_at DESC",
                [$user['user_id'], $user['user_id']]
            );
        } else {
            $convs = $db->fetchAll(
                "SELECT c.*, u.username as other_name,
                 (SELECT m.message_text FROM messages m WHERE m.conversation_id = c.conversation_id ORDER BY m.created_at DESC LIMIT 1) as last_message,
                 (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.conversation_id AND m.is_read = 0 AND m.sender_id != ?) as unread_count,
                 p.name as pet_name
                 FROM conversations c
                 JOIN users u ON c.adopter_id = u.user_id
                 LEFT JOIN pets p ON c.pet_id = p.pet_id
                 WHERE c.shelter_id = ?
                 ORDER BY c.created_at DESC",
                [$user['user_id'], $user['user_id']]
            );
        }

        foreach ($convs as &$c) {
            $c['other_name'] = html_entity_decode($c['other_name'] ?? '', ENT_QUOTES, 'UTF-8');
            if (isset($c['last_message'])) {
                $c['last_message'] = html_entity_decode($c['last_message'], ENT_QUOTES, 'UTF-8');
            }
        }
        jsonSuccess($convs);
        break;

    case 'get_messages':
        $convId = (int)($_GET['conv_id'] ?? 0);
        if (!$convId) { jsonError('Conversation ID required.'); break; }

        $msgs = $db->fetchAll(
            "SELECT m.*, m.sender_id as sender_user_id, u.username as sender_name
             FROM messages m
             JOIN users u ON m.sender_id = u.user_id
             WHERE m.conversation_id = ?
             ORDER BY m.created_at ASC",
            [$convId]
        );
        
        date_default_timezone_set('Asia/Manila');
        foreach ($msgs as &$m) {
            $m['time_ago'] = timeAgo($m['created_at'] ?? 'now');
            $m['message_text'] = html_entity_decode($m['message_text'] ?? '', ENT_QUOTES, 'UTF-8');
        }

        $db->execute(
            "UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ?",
            [$convId, $user['user_id']]
        );

        jsonSuccess($msgs);
        break;

    case 'send':
        $convId  = (int)($_POST['conv_id'] ?? 0);
        $text    = trim($_POST['message'] ?? '');

        if (!$convId || !$text) { jsonError('Message content required.'); break; }

        $conv = $db->fetch("SELECT * FROM conversations WHERE conversation_id = ?", [$convId]);
        if (!$conv) { jsonError('Conversation not found.', 404); break; }

        $recipientId = ($user['user_id'] == $conv['adopter_id']) ? $conv['shelter_id'] : $conv['adopter_id'];

        try {
            $db->execute(
                "INSERT INTO messages (conversation_id, sender_id, receiver_id, message_text) VALUES (?, ?, ?, ?)",
                [$convId, $user['user_id'], $recipientId, $text]
            );
            jsonSuccess([], 'Message sent.');
        } catch (Exception $e) {
            jsonError('Failed to save message: ' . $e->getMessage());
        }
        break;
case 'start':
        if ($user['role'] !== 'ADOPTER') { jsonError('Only adopters can start chats.', 403); break; }

        $shelterId = (int)($_POST['shelter_id'] ?? 0);
        $petId     = isset($_POST['pet_id']) && $_POST['pet_id'] !== '' ? (int)$_POST['pet_id'] : 0;

        if (!$shelterId) { jsonError('Shelter ID required.'); break; }

        $existing = $db->fetch(
            "SELECT conversation_id, pet_id FROM conversations WHERE adopter_id = ? AND shelter_id = ?",
            [$user['user_id'], $shelterId]
        );

        if ($existing) {
            $convId = (int)$existing['conversation_id'];
            
            if ($petId > 0 && (int)$existing['pet_id'] !== $petId) {
                $db->execute(
                    "UPDATE conversations SET pet_id = ? WHERE conversation_id = ?",
                    [$petId, $convId]
                );
            }
            
            jsonSuccess(['conversation_id' => $convId], 'Found chat.');
            break;
        }

        try {
            $insertPetId = ($petId > 0) ? $petId : null;
            $db->execute(
                "INSERT INTO conversations (adopter_id, shelter_id, pet_id) VALUES (?, ?, ?)",
                [$user['user_id'], $shelterId, $insertPetId]
            );
            jsonSuccess(['conversation_id' => (int)$db->lastInsertId()], 'Chat started.');
        } catch (Exception $e) {
            $fallback = $db->fetch(
                "SELECT conversation_id FROM conversations WHERE adopter_id = ? AND shelter_id = ?",
                [$user['user_id'], $shelterId]
            );
            
            if ($fallback) {
                jsonSuccess(['conversation_id' => (int)$fallback['conversation_id']], 'Found chat.');
            } else {
                jsonError('Failed to initialize conversation room: ' . $e->getMessage());
            }
        }
        break;
        
    default:
        jsonError('Unknown action.', 400);
}