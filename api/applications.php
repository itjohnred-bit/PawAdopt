<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/functions_audit.php';
startSession();

$pdo = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user   = getCurrentUser();
$db     = Database::getInstance();

switch ($action) {

    case 'submit':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); exit; }
        if (!$user || strtoupper($user['role']) !== 'ADOPTER') { jsonError('Adopters only.'); exit; }

        $petId   = (int)($_POST['pet_id'] ?? 0);
        $message = trim($_POST['notes'] ?? $_POST['message'] ?? '');
        if (!$petId) { jsonError('Pet ID is required.'); exit; }

        $pet = $db->fetch("SELECT pet_id, shelter_id, status FROM pets WHERE pet_id = ?", [$petId]);
        if (!$pet) { jsonError('Pet not found.'); exit; }
        if ($pet['status'] !== 'Available') { jsonError('This pet is no longer available.'); exit; }

        $existing = $db->fetch(
            "SELECT application_id FROM adoption_applications
             WHERE adopter_id = ? AND pet_id = ?
               AND status IN ('Submitted','Under Review','Approved')",
            [$user['user_id'], $petId]
        );
        if ($existing) { jsonError('You already applied for this pet.'); exit; }

        $screening = [];
        foreach ($_POST as $k => $v) {
            if (!in_array($k, ['action','pet_id','notes','message'], true)) {
                $screening[$k] = is_string($v) ? trim($v) : $v;
            }
        }
        $screeningJson = $screening ? json_encode($screening, JSON_UNESCAPED_UNICODE) : null;

        $db->execute(
            "INSERT INTO adoption_applications
                 (pet_id, adopter_id, shelter_id, status, message_to_shelter, screening_responses, submitted_at)
             VALUES (?, ?, ?, 'Submitted', ?, ?, NOW())",
            [$petId, $user['user_id'], $pet['shelter_id'], $message, $screeningJson]
        );
        $appId = (int)$db->lastInsertId();

        if (function_exists('log_action')) {
            log_action($user['user_id'], 'submit_application', "Adopter submitted application #$appId for pet #$petId.");
        }
        if (function_exists('createNotification')) {
            createNotification(
                $pet['shelter_id'],
                'NEW_APPLICATION',
                'New adoption application',
                "{$user['username']} applied for one of your pets.",
                APP_URL . '/pages/shelter/applications.php'
            );
        }

        jsonSuccess(['application_id' => $appId], 'Application submitted! 🐾');
        exit;

    case 'cancel':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); exit; }
        if (!$user) { jsonError('Please log in.'); exit; }

        $appId = (int)($_POST['application_id'] ?? 0);
        if (!$appId) { jsonError('Application ID required.'); exit; }

        $app = $db->fetch("SELECT * FROM adoption_applications WHERE application_id = ?", [$appId]);
        if (!$app) { jsonError('Application not found.'); exit; }

        $isOwner = (int)$app['adopter_id'] === (int)$user['user_id'];
        $isAdmin = strtoupper($user['role']) === 'ADMIN';
        if (!$isOwner && !$isAdmin) { jsonError('Not authorized to cancel this application.'); exit; }

        if (in_array($app['status'], ['Approved', 'Cancelled'])) {
            jsonError('This application can no longer be cancelled.'); exit;
        }

        $db->execute(
            "UPDATE adoption_applications
                SET status = 'Cancelled', reviewed_at = NOW(),
                    decision_note = CONCAT(IFNULL(decision_note,''), '\nCancelled by user.')
              WHERE application_id = ?",
            [$appId]
        );

        if (function_exists('log_action')) {
            log_action($user['user_id'], 'cancel_application', "Cancelled application #$appId.");
        }

        jsonSuccess([], 'Application cancelled.');
        exit;

    case 'get_detail':
        if (!$user) { jsonError('Please log in.'); exit; }

        $appId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if (!$appId) { jsonError('Application ID required.'); exit; }

        $sql = "SELECT aa.*,
                       p.name     AS pet_name,
                       p.species,
                       p.breed,
                       sp.shelter_name,
                       sp.city    AS shelter_city,
                       u.username AS adopter_username,
                       u.email    AS adopter_email,
                       ap.full_name AS adopter_name,
                       ap.phone   AS adopter_phone,
                       ap.city    AS adopter_city,
                       ap.bio     AS adopter_bio
                  FROM adoption_applications aa
                  JOIN pets p  ON aa.pet_id     = p.pet_id
                  JOIN shelter_profiles sp ON p.shelter_id = sp.shelter_id
                  JOIN users u  ON aa.adopter_id = u.user_id
                  LEFT JOIN adopter_profiles ap ON aa.adopter_id = ap.adopter_id
                 WHERE aa.application_id = ?";
        $app = $db->fetch($sql, [$appId]);
        if (!$app) { jsonError('Application not found.'); exit; }

        $role      = strtoupper($user['role']);
        $isShelter = ($role === 'SHELTER' || $role === 'VETERINARY') && (int)$app['shelter_id'] === (int)$user['user_id'];
        $isAdopter = $role === 'ADOPTER' && (int)$app['adopter_id'] === (int)$user['user_id'];
        $isAdmin   = $role === 'ADMIN';
        if (!$isShelter && !$isAdopter && !$isAdmin) {
            jsonError('Not authorized to view this application.'); exit;
        }

        jsonSuccess($app);
        exit;

    case 'review':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); exit; }
        if (!$user) { jsonError('Please log in.'); exit; }

        $appId  = (int)($_POST['application_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $note   = trim($_POST['decision_note'] ?? '');

        $allowed = ['Under Review', 'Approved', 'Rejected'];
        if (!$appId) { jsonError('Application ID required.'); exit; }
        if (!in_array($status, $allowed, true)) { jsonError('Invalid status.'); exit; }

        $app = $db->fetch(
            "SELECT aa.*, p.shelter_id
               FROM adoption_applications aa
               JOIN pets p ON aa.pet_id = p.pet_id
              WHERE aa.application_id = ?",
            [$appId]
        );
        if (!$app) { jsonError('Application not found.'); exit; }

        $role      = strtoupper($user['role']);
        $isShelter = ($role === 'SHELTER' || $role === 'VETERINARY') && (int)$app['shelter_id'] === (int)$user['user_id'];
        $isAdmin   = $role === 'ADMIN';
        if (!$isShelter && !$isAdmin) {
            jsonError('Not authorized to review this application.'); exit;
        }

        $db->execute(
            "UPDATE adoption_applications
                SET status = ?, decision_note = ?, reviewed_at = NOW()
              WHERE application_id = ?",
            [$status, $note, $appId]
        );

        if ($status === 'Approved') {
            $db->execute("UPDATE pets SET status = 'Pending' WHERE pet_id = ?", [$app['pet_id']]);
        }

        if (function_exists('createNotification')) {
            $title = $status === 'Approved' ? '🎉 Application approved!' : ($status === 'Rejected' ? 'Application rejected' : 'Application under review');
            $body  = $note ?: ('Your application has been updated to: ' . $status);
            createNotification($app['adopter_id'], 'APPLICATION_' . strtoupper(str_replace(' ', '_', $status)), $title, $body, APP_URL . '/pages/adopter/applications.php?focus=' . $appId);
        }

        jsonSuccess([], "Application updated to $status.");
        exit;
}

if (!empty($action)) {
    jsonError('Invalid action context endpoint requested.');
    exit;
}
