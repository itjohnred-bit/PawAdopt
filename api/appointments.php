<?php
/**
 * api/appointments.php  🟢 NEW (with race-safe duplicate guard)
 * Veterinary-scope appointment API.
 *   GET  /api/appointments.php?action=list
 *   POST /api/appointments.php?action=create
 *   POST /api/appointments.php?action=set_status
 *
 * Duplicate prevention:
 *   The `create` action now wraps the duplicate-check and INSERT in a
 *   transaction using SELECT … FOR UPDATE, so two retried POSTs cannot
 *   both win the same slot. A recommended UNIQUE index is listed in
 *   the notes below for a stronger, server-immutable guarantee.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions_audit.php';
startSession();

if (!isLoggedIn()) { jsonError('Unauthorized', 401); exit; }

$user = getCurrentUser();
$role = strtoupper($user['role']);
pa_get_csrf();                                              // seed session token

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    /* ────────── CREATE — veterinary only ────────── */
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); break; }
        if (!in_array($role, ['VETERINARY', 'SHELTER', 'ADMIN'], true)) {
            jsonError('Only veterinarians may create appointments.', 403); break;
        }
        if (!pa_verify_csrf()) { jsonError('Invalid CSRF token.', 403); break; }

        $applicationId = (int)($_POST['application_id'] ?? 0);
        $date          = $_POST['date'] ?? '';
        $time          = $_POST['time'] ?? '';
        $type          = trim($_POST['type']  ?? 'Veterinary Consultation');
        $notes         = trim($_POST['notes'] ?? '');

        if (!$applicationId) { jsonError('application_id is required.'); break; }

        /* ── Normalize date — accept ISO YYYY-MM-DD, US M/D/YYYY, or DD/MM/YYYY → YYYY-MM-DD ── */
        $dateNorm = null;
        $d = trim($date);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) {
            $dateNorm = sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
        } elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2,4})$#', $d, $m)) {
            $yy = strlen($m[3]) === 2 ? ('20' . $m[3]) : $m[3];
            $dateNorm = sprintf('%04d-%02d-%02d', (int)$yy, (int)$m[1], (int)$m[2]);
        } elseif (strtotime($d) !== false) {
            $dateNorm = date('Y-m-d', strtotime($d));
        }
        if (!$dateNorm) { jsonError('Date must be YYYY-MM-DD or M/D/YYYY.'); break; }
        $date = $dateNorm;

        /* ── Normalize time — accept H:MM, HH:MM, h:MM AM/PM, HH:MM:SS, or "now"-style → HH:MM:SS ── */
        $timeNorm = null;
        $t = trim($time);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $t, $m)) {
            $h = (int)$m[1]; $mn = (int)$m[2];
            if ($h >= 0 && $h < 24 && $mn >= 0 && $mn < 60) {
                $timeNorm = sprintf('%02d:%02d:%02d', $h, $mn, (int)($m[3] ?? 0));
            }
        }
        if (!$timeNorm && preg_match('/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i', $t, $m)) {
            $h = (int)$m[1]; $mn = (int)$m[2];
            $ap = strtoupper($m[3]);
            if ($ap === 'PM' && $h < 12) $h += 12;
            if ($ap === 'AM' && $h === 12) $h = 0;
            if ($h >= 0 && $h < 24 && $mn < 60) $timeNorm = sprintf('%02d:%02d:00', $h, $mn);
        }
        if (!$timeNorm && strtotime($t) !== false) {
            $timeNorm = date('H:i:s', strtotime($t));
        }
        if (!$timeNorm) { jsonError('Time must be HH:MM (24-hour) or H:MM AM/PM.'); break; }
        $time = $timeNorm;

        $db = Database::getInstance();

        /* ── Application + ownership ── */
        $app = $db->fetch(
            "SELECT aa.application_id, aa.pet_id, aa.adopter_id, aa.shelter_id,
                    p.name AS pet_name
             FROM adoption_applications aa
             JOIN pets p ON p.pet_id = aa.pet_id
             WHERE aa.application_id = ?",
            [$applicationId]
        );
        if (!$app) { jsonError('Application not found.', 404); break; }

        $owns = ($role === 'ADMIN') ||
                ((int)$app['shelter_id'] === (int)$user['user_id']);
        if (!$owns) { jsonError('This application is not yours.', 403); break; }

        /* ── Race-safe duplicate guard (transaction + SELECT … FOR UPDATE) ── */
        $db->execute('START TRANSACTION');
        try {
            $existing = $db->fetch(
                "SELECT appointment_id, appointment_date, appointment_time, appointment_type, status
                 FROM appointments
                 WHERE application_id = ?
                   AND status IN ('SCHEDULED','CONFIRMED','RESCHEDULED')
                 FOR UPDATE",
                [(int)$app['application_id']]
            );

            if ($existing) {
                $db->execute('ROLLBACK');
                jsonError(
                    'An appointment is already scheduled for this application '
                    . '(Appointment #' . (int)$existing['appointment_id'] . ' — '
                    . $existing['status'] . ' on '
                    . date('M j, Y', strtotime($existing['appointment_date']))
                    . ' at '
                    . date('g:i A', strtotime($existing['appointment_time']))
                    . '). Cancel or complete it first.',
                    409
                );
                break;
            }

            $db->execute(
                "INSERT INTO appointments
                    (application_id, pet_id, adopter_id, veterinary_id,
                     appointment_date, appointment_time, appointment_type, notes, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'SCHEDULED')",
                [
                    (int)$app['application_id'],
                    (int)$app['pet_id'],
                    (int)$app['adopter_id'],
                    (int)$user['user_id'],
                    $date, $time, $type, $notes
                ]
            );
            $apptId = (int)$db->lastInsertId();

            $db->execute('COMMIT');
        } catch (Throwable $txErr) {
            try { $db->execute('ROLLBACK'); } catch (Throwable $ignored) {}
            jsonError('Could not schedule appointment. Please try again.', 500);
            break;
        }

        /* ── In-app notification. NO SMS / NO EMAIL is claimed.
              Wrapped in try/catch: the earlier #1054 "Unknown column 'body'"
              error came from pa_create_notification; a notification failure
              must NOT roll back the booking. If you want the notification
              columns mapped to your real schema, paste includes/functions.php
              (and / or functions_audit.php) and I'll fix pa_create_notification
              properly. ── */
        $title = '📅 Appointment Scheduled';
        $body  = "Your appointment for {$app['pet_name']} is set for "
               . date('M j, Y', strtotime($date)) . ' at '
               . date('g:i A', strtotime($time)) . ".\n\n"
               . "Type: {$type}"
               . ($notes ? "\n\nNotes: {$notes}" : '');
        $link  = APP_URL . '/pages/adopter/appointment.php?id=' . $apptId;

        try {
            pa_create_notification((int)$app['adopter_id'], $title, $body, $link, 'appointment');
        } catch (Throwable $nErr) {
            error_log('[appointments] notification failed for appointment #' . $apptId
                      . ' — booking preserved. Error: ' . $nErr->getMessage());
        }

        log_action($user['user_id'], 'appointment_create',
            "Appointment #$apptId (app #$applicationId)", $role, $user['username']);

        jsonSuccess(['appointment_id' => $apptId], 'Appointment scheduled.');
        break;

    /* ────────── LIST — role scoped ────────── */
    case 'list':
        $db = Database::getInstance();
        if ($role === 'ADOPTER') {
            $rows = $db->fetchAll(
                "SELECT a.*, p.name AS pet_name, sp.shelter_name, sp.phone AS vet_phone
                 FROM appointments a
                 JOIN shelter_profiles sp ON sp.shelter_id = a.veterinary_id
                 JOIN pets p              ON p.pet_id      = a.pet_id
                 WHERE a.adopter_id = ?
                 ORDER BY a.appointment_date DESC, a.appointment_time DESC",
                [$user['user_id']]
            );
        } elseif (in_array($role, ['VETERINARY', 'SHELTER', 'ADMIN'], true)) {
            $rows = $db->fetchAll(
                "SELECT a.*, p.name AS pet_name, u.email AS adopter_email
                 FROM appointments a
                 JOIN users u ON u.user_id = a.adopter_id
                 JOIN pets p  ON p.pet_id  = a.pet_id
                 WHERE a.veterinary_id = ?
                 ORDER BY a.appointment_date DESC, a.appointment_time DESC",
                [$user['user_id']]
            );
        } else { $rows = []; }
        jsonSuccess(['appointments' => $rows, 'csrf' => $_SESSION['csrf']]);
        break;

    /* ────────── SET STATUS — veterinary only ────────── */
    case 'set_status':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); break; }
        if (!pa_verify_csrf()) { jsonError('Invalid CSRF token.', 403); break; }
        if (!in_array($role, ['VETERINARY', 'SHELTER', 'ADMIN'], true)) {
            jsonError('Permission denied.', 403); break;
        }
        $apptId    = (int)($_POST['appointment_id'] ?? 0);
        $newStatus = strtoupper($_POST['status'] ?? '');
        $valid     = ['SCHEDULED', 'CONFIRMED', 'COMPLETED', 'CANCELLED', 'RESCHEDULED'];
        if (!$apptId || !in_array($newStatus, $valid, true)) {
            jsonError('Bad input.', 400); break;
        }
        $db  = Database::getInstance();
        $own = $db->fetch(
            "SELECT * FROM appointments
             WHERE appointment_id = ? AND veterinary_id = ?",
            [$apptId, $user['user_id']]
        );
        if (!$own) { jsonError('Appointment not found.', 404); break; }
        $db->execute(
            "UPDATE appointments SET status = ? WHERE appointment_id = ?",
            [$newStatus, $apptId]
        );
        log_action($user['user_id'], 'appointment_status',
            "Appointment #$apptId → $newStatus", $role, $user['username']);
        jsonSuccess([], 'Status updated.');
        break;

    default:
        jsonError('Unknown action.', 400);
}
