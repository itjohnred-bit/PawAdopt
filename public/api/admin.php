<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

while (ob_get_level() > 0) { ob_end_clean(); }

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/functions_audit.php';

startSession();
requireRole('ADMIN');

$db     = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user   = getCurrentUser();

switch ($action) {


    case 'users':
        $search = trim($_GET['search'] ?? '');
        $role   = $_GET['role'] ?? '';
        $where  = []; $params = [];
        if ($search) { $where[] = '(username LIKE ? OR email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
        if ($role)   { $where[] = 'role = ?'; $params[] = $role; }
        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $users = $db->fetchAll(
            "SELECT user_id, role, username, email, is_active, created_at FROM users $whereStr ORDER BY created_at DESC",
            $params
        );
        jsonSuccess($users);
        break;

    case 'toggle_user':
        $userId = (int)($_POST['user_id'] ?? 0);
        if (!$userId) { jsonError('User ID required.'); break; }

        $targetUser = $db->fetch("SELECT username, is_active FROM users WHERE user_id = ?", [$userId]);
        if (!$targetUser) { jsonError('User not found.'); break; }

        $newActiveStatus = $targetUser['is_active'] ? 0 : 1;
        $db->execute("UPDATE users SET is_active = ? WHERE user_id = ?", [$newActiveStatus, $userId]);

        if (function_exists('log_action')) {
            $statusLabel = $newActiveStatus ? 'ACTIVATED' : 'DEACTIVATED';
            log_action(
                $user['user_id'],
                'toggle_user',
                "Admin changed status of user '{$targetUser['username']}' (ID: {$userId}) to {$statusLabel}"
            );
        }
        jsonSuccess([], 'User status updated.');
        break;

    case 'delete_user':
        $userId = (int)($_POST['user_id'] ?? 0);
        if (!$userId || $userId === (int)$user['user_id']) { jsonError('Cannot delete this user.'); break; }

        $targetUser = $db->fetch("SELECT username FROM users WHERE user_id = ?", [$userId]);
        if (!$targetUser) { jsonError('User not found.'); break; }

        $db->execute("DELETE FROM users WHERE user_id = ?", [$userId]);

        if (function_exists('log_action')) {
            log_action(
                $user['user_id'],
                'delete_user',
                "Admin permanently deleted user account: '{$targetUser['username']}' (ID: {$userId})"
            );
        }
        jsonSuccess([], 'User deleted.');
        break;

    case 'veterinarys':
        $status = $_GET['status'] ?? '';
        $where  = $status ? 'WHERE sv.status = ?' : '';
        $params = $status ? [$status] : [];
        $veterinarys = $db->fetchAll(
            "SELECT sv.*, sp.veterinary_name, sp.city, sp.phone, sp.is_verified, u.email, u.username
             FROM veterinary_verifications sv
             JOIN veterinary_profiles sp ON sv.veterinary_id = sp.veterinary_id
             JOIN users u ON sv.veterinary_id = u.user_id
             $where
             ORDER BY sv.submitted_at DESC",
            $params
        );
        jsonSuccess($veterinarys);
        break;

    case 'verify_veterinary':
        $shelId  = (int)($_POST['veterinary_id'] ?? 0);
        $status  = $_POST['status'] ?? '';
        $note    = trim($_POST['note'] ?? '');
        if (!$shelId || !in_array($status, ['APPROVED', 'REJECTED'], true)) {
            jsonError('Invalid parameters.');
            break;
        }

        $veterinary = $db->fetch("SELECT veterinary_name FROM veterinary_profiles WHERE veterinary_id = ?", [$shelId]);

        $db->execute(
            "UPDATE veterinary_verifications SET status = ?, note = ?, reviewed_by_admin_id = ?, reviewed_at = NOW() WHERE veterinary_id = ?",
            [$status, $note, $user['user_id'], $shelId]
        );

        $verified = ($status === 'APPROVED') ? 1 : 0;
        $db->execute("UPDATE veterinary_profiles SET is_verified = ? WHERE veterinary_id = ?", [$verified, $shelId]);

        $msg = $status === 'APPROVED'
            ? 'Your veterinary has been verified! You can now post pet listings. 🎉'
            : 'Your veterinary verification was not approved. Note: ' . $note;

        if (function_exists('createNotification')) {
            createNotification($shelId, 'veterinary_VERIFIED', 'veterinary Verification Update', $msg, APP_URL . '/pages/veterinary/profile.php');
        }

        if (function_exists('log_action')) {
            $veterinaryName = $veterinary['veterinary_name'] ?? 'Unknown veterinary';
            log_action(
                $user['user_id'],
                'verify_veterinary',
                "Admin updated verification for '{$veterinaryName}' (ID: {$shelId}) to status: {$status}. Note: " . ($note ?: 'None')
            );
        }
        jsonSuccess([], "veterinary $status.");
        break;

    case 'all_pets':
        $status = $_GET['status'] ?? '';
        $where  = $status ? 'WHERE p.status = ?' : '';
        $params = $status ? [$status] : [];
        $pets = $db->fetchAll(
            "SELECT p.*, sp.veterinary_name,
             (SELECT pp.photo_url FROM pet_photos pp WHERE pp.pet_id = p.pet_id AND pp.is_primary = 1 LIMIT 1) as primary_photo
             FROM pets p JOIN veterinary_profiles sp ON p.veterinary_id = sp.veterinary_id
             $where ORDER BY p.created_at DESC",
            $params
        );

        if (function_exists('formatAge')) {
            foreach ($pets as &$p) {
                $p['photo_url']  = $p['primary_photo'] ? APP_URL . '/' . $p['primary_photo'] : APP_URL . '/assets/images/pet-placeholder.png';
                $p['age_display'] = formatAge($p['age_months']);
            }
            unset($p);
        }
        jsonSuccess($pets);
        break;

    case 'remove_pet':
        $petId = (int)($_POST['pet_id'] ?? 0);
        if (!$petId) { jsonError('Pet ID required.'); break; }

        $pet = $db->fetch("SELECT name, status FROM pets WHERE pet_id = ?", [$petId]);
        if (!$pet) { jsonError('Pet not found.'); break; }

        if ($pet['status'] === 'Removed') {
            jsonSuccess([], 'Pet was already removed.');
            break;
        }


        $db->execute(
            "UPDATE pets SET status = 'Removed', deleted_at = NOW() WHERE pet_id = ?",
            [$petId]
        );

        if (function_exists('log_action')) {
            $petName = $pet['name'] ?? 'Unknown Pet';
            log_action(
                $user['user_id'],
                'remove_pet',
                "Admin changed status of pet listing '{$petName}' (ID: {$petId}) to 'Removed'"
            );
        }
        jsonSuccess([], 'Pet removed.');
        break;


    case 'restore_pet':
        $petId = (int)($_POST['pet_id'] ?? 0);
        if (!$petId) { jsonError('Pet ID required.'); break; }

        $pet = $db->fetch("SELECT name, status FROM pets WHERE pet_id = ?", [$petId]);
        if (!$pet) { jsonError('Pet not found.'); break; }
        if ($pet['status'] !== 'Removed') {
            jsonSuccess([], 'Pet is not removed.');
            break;
        }
        $db->execute(
            "UPDATE pets SET status = 'Available', deleted_at = NULL WHERE pet_id = ?",
            [$petId]
        );
        if (function_exists('log_action')) {
            log_action($user['user_id'], 'restore_pet', "Admin restored pet '{$pet['name']}' (ID: {$petId})");
        }
        jsonSuccess([], 'Pet restored.');
        break;

    case 'reports':
        $stats = [
            'total_users'    => (int)($db->fetch("SELECT COUNT(*) as c FROM users")['c'] ?? 0),
            'total_adopters' => (int)($db->fetch("SELECT COUNT(*) as c FROM users WHERE role='ADOPTER'")['c'] ?? 0),
            'total_veterinarys' => (int)($db->fetch("SELECT COUNT(*) as c FROM users WHERE role='VETERINARY'")['c'] ?? 0),
            'total_pets'     => (int)($db->fetch("SELECT COUNT(*) as c FROM pets WHERE status != 'Removed'")['c'] ?? 0),
            'available_pets' => (int)($db->fetch("SELECT COUNT(*) as c FROM pets WHERE status='Available'")['c'] ?? 0),
            'adopted_pets'   => (int)($db->fetch("SELECT COUNT(*) as c FROM pets WHERE status='Adopted'")['c'] ?? 0),
            'total_apps'     => (int)($db->fetch("SELECT COUNT(*) as c FROM adoption_applications")['c'] ?? 0),
            'approved_apps'  => (int)($db->fetch("SELECT COUNT(*) as c FROM adoption_applications WHERE status='Approved'")['c'] ?? 0),
            'pending_verif'  => (int)($db->fetch("SELECT COUNT(*) as c FROM veterinary_verifications WHERE status='PENDING'")['c'] ?? 0),
        ];

        $monthlyRegs = $db->fetchAll(
            "SELECT DATE_FORMAT(created_at,'%b %Y') as month, COUNT(*) as count
             FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY DATE_FORMAT(created_at,'%Y-%m')
             ORDER BY MIN(created_at) ASC"
        );

        $monthlyAdoptions = $db->fetchAll(
            "SELECT DATE_FORMAT(reviewed_at,'%b %Y') as month, COUNT(*) as count
             FROM adoption_applications WHERE status='Approved' AND reviewed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY DATE_FORMAT(reviewed_at,'%Y-%m')
             ORDER BY MIN(reviewed_at) ASC"
        );

        $topSpecies = $db->fetchAll(
            "SELECT species, COUNT(*) as count FROM pets WHERE status != 'Removed' GROUP BY species ORDER BY count DESC"
        );

        jsonSuccess(compact('stats', 'monthlyRegs', 'monthlyAdoptions', 'topSpecies'));
        break;


    case 'update_content':
        $key   = trim($_POST['key'] ?? '');
        $value = trim($_POST['value'] ?? '');
        if (!$key) { jsonError('Key required.'); break; }

        $db->execute(
            "INSERT INTO site_content (content_key, content_value, updated_by) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE content_value = VALUES(content_value), updated_by = VALUES(updated_by), updated_at = NOW()",
            [$key, $value, $user['user_id']]
        );

        if (function_exists('log_action')) {
            log_action($user['user_id'], 'update_content', "Admin updated content key: '{$key}'");
        }
        jsonSuccess([], 'Content updated.');
        break;

    default:
        jsonError('Unknown action.', 400);
}
