<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireLogin();
requireRole('ADOPTER');

$db     = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? 'toggle';
$user   = getCurrentUser();

switch ($action) {

     case 'toggle':
        
        $petId = (int)($_POST['pet_id'] ?? $_GET['pet_id'] ?? 0);

        if (!$petId) { jsonError('Pet ID required.'); break; }

        $existing = $db->fetch(
            "SELECT favorite_id FROM favorites WHERE adopter_id = ? AND pet_id = ?",
            [$user['user_id'], $petId]
        );

        if ($existing) {
            $db->execute("DELETE FROM favorites WHERE adopter_id = ? AND pet_id = ?", [$user['user_id'], $petId]);
            jsonSuccess(['favorited' => false], 'Removed from favorites.');
        } else {
            $db->execute("INSERT INTO favorites (adopter_id, pet_id) VALUES (?, ?)", [$user['user_id'], $petId]);
            jsonSuccess(['favorited' => true], 'Added to favorites! ❤️');
        }
        break;

    case 'list':
        $pets = $db->fetchAll(
            "SELECT p.*, f.created_at as favorited_at, sp.shelter_name,
                    (SELECT pp.photo_url FROM pet_photos pp WHERE pp.pet_id = p.pet_id AND pp.is_primary = 1 LIMIT 1) as primary_photo
             FROM favorites f
             JOIN pets p ON f.pet_id = p.pet_id
             JOIN shelter_profiles sp ON p.shelter_id = sp.shelter_id
             WHERE f.adopter_id = ? AND p.status != 'Removed'
             ORDER BY f.created_at DESC",
            [$user['user_id']]
        );
        foreach ($pets as &$p) {
            $p['photo_url']   = $p['primary_photo'] ? APP_URL . '/' . $p['primary_photo'] : APP_URL . '/assets/images/pet-placeholder.png';
            $p['age_display'] = formatAge($p['age_months']);
            $p['is_favorited'] = true;
        }
        jsonSuccess($pets);
        break;

    default:
        jsonError('Unknown action.', 400);
}
?>
