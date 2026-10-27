<?php
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$db = Database::getInstance();
$jsonInput = json_decode(file_get_contents('php://input'), true);
if (is_array($jsonInput)) {
    $_POST = array_merge($_POST, $jsonInput);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user   = getCurrentUser();

function decodeMessageFields(array $rows): array {
    foreach ($rows as &$row) {
        if (isset($row['other_name'])) {
            $row['other_name'] = html_entity_decode((string) $row['other_name'], ENT_QUOTES, 'UTF-8');
        }
        if (isset($row['sender_name'])) {
            $row['sender_name'] = html_entity_decode((string) $row['sender_name'], ENT_QUOTES, 'UTF-8');
        }
        if (isset($row['last_message'])) {
            $row['last_message'] = html_entity_decode((string) $row['last_message'], ENT_QUOTES, 'UTF-8');
        }
        if (isset($row['message_text'])) {
            $row['message_text'] = html_entity_decode((string) $row['message_text'], ENT_QUOTES, 'UTF-8');
        }
    }
    unset($row);

    return $rows;
}

function getConversationForUser(Database $db, int $convId, array $user): ?array {
    $conv = $db->fetch('SELECT * FROM conversations WHERE conversation_id = ?', [$convId]);
    if (!$conv) {
        return null;
    }

    $userId = (int) ($user['user_id'] ?? 0);
    $role = strtoupper((string) ($user['role'] ?? ''));
    $allowed = false;

    if ($role === 'ADOPTER' && $userId === (int) $conv['adopter_id']) {
        $allowed = true;
    }
    if (in_array($role, ['SHELTER', 'VETERINARY'], true) && $userId === (int) $conv['shelter_id']) {
        $allowed = true;
    }

    return $allowed ? $conv : null;
}

switch ($action) {
    case 'conversations':
        if (($user['role'] ?? '') === 'ADOPTER') {
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
        } else {
            $convs = $db->fetchAll(
                "SELECT c.*, u.username AS other_name, u.email AS other_email,
                        (SELECT m.message_text FROM messages m WHERE m.conversation_id = c.conversation_id ORDER BY m.created_at DESC LIMIT 1) AS last_message,
                        (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.conversation_id AND m.is_read = 0 AND m.sender_id != ?) AS unread_count,
                        (SELECT MAX(m.created_at) FROM messages m WHERE m.conversation_id = c.conversation_id) AS last_message_at,
                        p.name AS pet_name
                 FROM conversations c
                 JOIN users u ON c.adopter_id = u.user_id
                 LEFT JOIN pets p ON c.pet_id = p.pet_id
                 WHERE c.shelter_id = ?
                 ORDER BY COALESCE((SELECT MAX(m.created_at) FROM messages m WHERE m.conversation_id = c.conversation_id), c.created_at) DESC",
                [$user['user_id'], $user['user_id']]
            );
        }

        $convs = decodeMessageFields($convs);
        jsonSuccess($convs);
        break;

    case 'get_messages':
        $convId = (int) ($_GET['conv_id'] ?? $_POST['conv_id'] ?? 0);
        if (!$convId) {
            jsonError('Conversation ID required.');
            break;
        }

        $conv = getConversationForUser($db, $convId, $user);
        if (!$conv) {
            jsonError('Conversation not found or access denied.', 404);
            break;
        }

        $msgs = $db->fetchAll(
            "SELECT m.*, m.sender_id AS sender_user_id, u.username AS sender_name
             FROM messages m
             JOIN users u ON m.sender_id = u.user_id
             WHERE m.conversation_id = ?
             ORDER BY m.created_at ASC",
            [$convId]
        );

        foreach ($msgs as &$m) {
            $m['time_ago'] = timeAgo($m['created_at'] ?? 'now');
            $m['message_text'] = html_entity_decode((string) ($m['message_text'] ?? ''), ENT_QUOTES, 'UTF-8');
        }
        unset($m);

        $db->execute(
            'UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ?',
            [$convId, $user['user_id']]
        );

        jsonSuccess($msgs);
        break;

    case 'send':
        $convId = (int) ($_POST['conv_id'] ?? 0);
        $text = trim((string) ($_POST['message'] ?? ''));

        if (!$convId || $text === '') {
            jsonError('Message content required.');
            break;
        }

        $conv = getConversationForUser($db, $convId, $user);
        if (!$conv) {
            jsonError('Conversation not found or access denied.', 404);
            break;
        }

        $recipientId = ((int) $user['user_id'] === (int) $conv['adopter_id']) ? (int) $conv['shelter_id'] : (int) $conv['adopter_id'];

        try {
            $db->execute(
                'INSERT INTO messages (conversation_id, sender_id, receiver_id, message_text) VALUES (?, ?, ?, ?)',
                [$convId, $user['user_id'], $recipientId, $text]
            );
            jsonSuccess(['message' => $text], 'Message sent.');
        } catch (Exception $e) {
            jsonError('Failed to save message: ' . $e->getMessage());
        }
        break;

    case 'start':
        if (($user['role'] ?? '') !== 'ADOPTER') {
            jsonError('Only adopters can start chats.', 403);
            break;
        }

        $shelterId = (int) ($_POST['shelter_id'] ?? 0);
        $petId = isset($_POST['pet_id']) && $_POST['pet_id'] !== '' ? (int) $_POST['pet_id'] : 0;

        if (!$shelterId) {
            jsonError('Shelter ID required.');
            break;
        }

        $existing = $db->fetch(
            'SELECT conversation_id, pet_id FROM conversations WHERE adopter_id = ? AND shelter_id = ?',
            [$user['user_id'], $shelterId]
        );

        if ($existing) {
            $convId = (int) $existing['conversation_id'];
            if ($petId > 0 && (int) $existing['pet_id'] !== $petId) {
                $db->execute('UPDATE conversations SET pet_id = ? WHERE conversation_id = ?', [$petId, $convId]);
            }
            jsonSuccess(['conversation_id' => $convId], 'Found chat.');
            break;
        }

        try {
            $insertPetId = $petId > 0 ? $petId : null;
            $db->execute(
                'INSERT INTO conversations (adopter_id, shelter_id, pet_id) VALUES (?, ?, ?)',
                [$user['user_id'], $shelterId, $insertPetId]
            );
            jsonSuccess(['conversation_id' => (int) $db->lastInsertId()], 'Chat started.');
        } catch (Exception $e) {
            $fallback = $db->fetch(
                'SELECT conversation_id FROM conversations WHERE adopter_id = ? AND shelter_id = ?',
                [$user['user_id'], $shelterId]
            );

            if ($fallback) {
                jsonSuccess(['conversation_id' => (int) $fallback['conversation_id']], 'Found chat.');
            } else {
                jsonError('Failed to initialize conversation room: ' . $e->getMessage());
            }
        }
        break;

    default:
        jsonError('Unknown action.', 400);
}
