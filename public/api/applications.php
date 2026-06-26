<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/functions_audit.php';
startSession();

$pdo = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user   = getCurrentUser();
$db     = Database::getInstance();

switch ($action) {

    // ──────────────────────── ADOPTER: submit application ────────────────────────
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

    // ──────────────────────── SHELTER: view full application details ─────────────
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

        // Authorization check
        $role      = strtoupper($user['role']);
        $isShelter = ($role === 'SHELTER' || $role === 'VETERINARY') && (int)$app['shelter_id'] === (int)$user['user_id'];
        $isAdopter = $role === 'ADOPTER' && (int)$app['adopter_id'] === (int)$user['user_id'];
        $isAdmin   = $role === 'ADMIN';
        if (!$isShelter && !$isAdopter && !$isAdmin) {
            jsonError('Not authorized to view this application.'); exit;
        }

        jsonSuccess($app);
        exit;

    // ──────────────────────── SHELTER: approve / reject / mark under review ──────
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
            $db->execute("UPDATE pets status = 'Pending' WHERE pet_id = ?", [$app['pet_id']]);
        }

        if (function_exists('createNotification')) {
            $title = $status === 'Approved' ? '🎉 Application approved!' : ($status === 'Rejected' ? 'Application rejected' : 'Application under review');
            $body  = $note ?: ('Your application has been updated to: ' . $status);
            createNotification($app['adopter_id'], 'APPLICATION_' . strtoupper(str_replace(' ', '_', $status)), $title, $body, APP_URL . '/pages/adopter/applications.php?focus=' . $appId);
        }

        jsonSuccess([], "Application $status.");
        exit;
}

if (!empty($action)) {
    jsonError('Invalid action context endpoint requested.');
    exit;
}
?>

<?php if (isset($app) && is_array($app)): ?>
<button class="btn btn-success" onclick="reviewApplication(<?= (int)$app['application_id'] ?>, 'Approved')">
    <i class="fas fa-check"></i> Approve
</button>

<button class="btn btn-danger" onclick="reviewApplication(<?= (int)$app['application_id'] ?>, 'Rejected')">
    <i class="fas fa-times"></i> Reject
</button>

<button class="btn btn-link" onclick="reviewApplication(<?= (int)$app['application_id'] ?>, 'Under Review')">
    <i class="fas fa-search"></i> Under Review
</button>

<button class="btn btn-info" onclick="viewApplication(<?= (int)$app['application_id'] ?>)">
    <i class="fas fa-file-alt"></i> View Application
</button>
<?php endif; ?>

<div class="modal fade" id="applicationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.2);">
            <div class="modal-header" style="background: #f8fbf9; border-bottom: 1px solid #eee; padding: 20px 25px;">
                <h5 class="modal-title" style="color: #2d5a4c; font-weight: 800; font-size: 1.25rem;">
                    <i class="fas fa-file-signature me-2"></i> Full Adoption Application
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-0">
                <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-center bg-white">
                    <div>
                        <label class="text-muted small fw-bold text-uppercase d-block" style="letter-spacing: 0.5px;">Adopter</label>
                        <span id="modal_adopter_name" class="fw-bold text-dark fs-5">-</span>
                    </div>
                    <div class="text-end">
                        <label class="text-muted small fw-bold text-uppercase d-block" style="letter-spacing: 0.5px;">Pet</label>
                        <span id="modal_pet_name" class="fw-bold text-primary fs-5">-</span>
                    </div>
                </div>

                <div class="p-4 bg-white">
                    <h6 class="fw-bold mb-3 d-flex align-items-center" style="color: #2d5a4c;">
                        <i class="fas fa-tasks me-2"></i> Application Answers
                    </h6>
                    <div id="modal_responses_container" class="custom-scroll" style="max-height: 400px; overflow-y: auto; overflow-x: hidden;">
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-spinner fa-spin me-2"></i> Loading responses...
                        </div>
                    </div>
                </div>

                <div class="px-4 py-3" style="background: #fcfdfc; border-top: 1px solid #eee;">
                    <div class="mb-3">
                        <label class="text-muted small fw-bold text-uppercase mb-1 d-block">Additional Message</label>
                        <div id="modal_message" class="p-3 bg-white border-start border-4 border-success rounded shadow-sm" style="white-space: pre-wrap; font-style: italic; color: #444; font-size: 14px;">-</div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small fw-bold text-uppercase">Application Status</span>
                        <span id="modal_status" class="badge rounded-pill bg-info text-dark px-4 py-2" style="font-weight: 800; letter-spacing: 0.5px;">PENDING</span>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="padding: 15px 25px;">
                <button type="button" class="btn btn-light border px-4 fw-bold" data-bs-dismiss="modal" style="border-radius: 8px; color: #666;">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.custom-scroll::-webkit-scrollbar { width: 6px; }
.custom-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
.custom-scroll::-webkit-scrollbar-thumb { background: #d1d1d1; border-radius: 10px; }
.custom-scroll::-webkit-scrollbar-thumb:hover { background: #bbb; }
</style>