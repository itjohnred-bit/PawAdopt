<?php
require_once __DIR__ . '/../../includes/functions.php';
requireRole('ADOPTER');
$pageTitle = 'My Profile';
$user = getCurrentUser();
$db   = Database::getInstance();
$pdo  = $db->getConnection();

if (!function_exists('pa_has_account_deletion_table')) {
    function pa_has_account_deletion_table(PDO $pdo): bool
    {
        static $hasTable = null;

        if ($hasTable !== null) {
            return $hasTable;
        }

        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'account_deletion_requests'");
            $hasTable = (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            $hasTable = false;
        }

        return $hasTable;
    }
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profileAction = $_POST['profile_action'] ?? 'save_profile';

    if ($profileAction === 'save_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $address   = trim($_POST['address'] ?? '');
        $city      = trim($_POST['city'] ?? '');
        $bio       = trim($_POST['bio'] ?? '');

        $db->execute(
            "INSERT INTO adopter_profiles (adopter_id, full_name, phone, address, city, bio) VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), phone=VALUES(phone), address=VALUES(address), city=VALUES(city), bio=VALUES(bio), updated_at=NOW()",
            [$user['user_id'], $full_name, $phone, $address, $city, $bio]
        );

        if (!empty($_FILES['avatar']['name'])) {
            $avatarUrl = uploadImage($_FILES['avatar'], 'avatars');
            if ($avatarUrl) {
                $db->execute("UPDATE adopter_profiles SET avatar_url = ? WHERE adopter_id = ?", [$avatarUrl, $user['user_id']]);
            }
        }

        flashMessage('success', 'Profile updated!');
        redirect(APP_URL . '/pages/adopter/profile.php');
    }

    if ($profileAction === 'deactivate_account') {
        $confirm = strtoupper(trim($_POST['deactivate_confirm'] ?? ''));
        if ($confirm !== 'DEACTIVATE') {
            flashMessage('error', 'Type DEACTIVATE to confirm account deactivation.');
            redirect(APP_URL . '/pages/adopter/profile.php');
        }

        $db->execute("UPDATE users SET is_active = 0 WHERE user_id = ?", [$user['user_id']]);
        log_action($user['user_id'], 'deactivate_account', 'Adopter deactivated their account', 'ADOPTER', $user['username'] ?? '');

        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        header('Location: ' . APP_URL . '/login.php?account=deactivated');
        exit;
    }

    if ($profileAction === 'schedule_delete') {
        if (!pa_has_account_deletion_table($pdo)) {
            flashMessage('error', 'Deletion scheduling is not ready yet. Run the account deletion SQL migration first.');
            redirect(APP_URL . '/pages/adopter/profile.php');
        }

        $confirm = strtoupper(trim($_POST['delete_confirm'] ?? ''));
        if ($confirm !== 'DELETE') {
            flashMessage('error', 'Type DELETE to confirm scheduled account deletion.');
            redirect(APP_URL . '/pages/adopter/profile.php');
        }

        $scheduledFor = date('Y-m-d H:i:s', strtotime('+7 days'));
        $existing = $db->fetch(
            "SELECT request_id FROM account_deletion_requests WHERE user_id = ? AND status = 'PENDING' ORDER BY request_id DESC LIMIT 1",
            [$user['user_id']]
        );

        if ($existing) {
            $db->execute(
                "UPDATE account_deletion_requests SET requested_at = NOW(), scheduled_for = ?, cancelled_at = NULL, processed_at = NULL, note = ? WHERE request_id = ?",
                [$scheduledFor, 'User requested account deletion from profile.', $existing['request_id']]
            );
        } else {
            $db->execute(
                "INSERT INTO account_deletion_requests (user_id, requested_by_role, status, requested_at, scheduled_for, note) VALUES (?, 'ADOPTER', 'PENDING', NOW(), ?, ?)",
                [$user['user_id'], $scheduledFor, 'User requested account deletion from profile.']
            );
        }

        log_action($user['user_id'], 'schedule_account_deletion', 'Adopter scheduled account deletion in 7 days', 'ADOPTER', $user['username'] ?? '');
        flashMessage('success', 'Account deletion scheduled. Your account is set for permanent removal in 7 days.');
        redirect(APP_URL . '/pages/adopter/profile.php');
    }

    if ($profileAction === 'cancel_delete') {
        if (pa_has_account_deletion_table($pdo)) {
            $db->execute(
                "UPDATE account_deletion_requests SET status = 'CANCELLED', cancelled_at = NOW() WHERE user_id = ? AND status = 'PENDING'",
                [$user['user_id']]
            );
            log_action($user['user_id'], 'cancel_account_deletion', 'Adopter cancelled scheduled account deletion', 'ADOPTER', $user['username'] ?? '');
            flashMessage('success', 'Scheduled account deletion cancelled.');
        }

        redirect(APP_URL . '/pages/adopter/profile.php');
    }
}

$profile = $db->fetch("SELECT * FROM adopter_profiles WHERE adopter_id = ?", [$user['user_id']]);
$pendingDeletion = null;
if (pa_has_account_deletion_table($pdo)) {
    $pendingDeletion = $db->fetch(
        "SELECT * FROM account_deletion_requests WHERE user_id = ? AND status = 'PENDING' ORDER BY request_id DESC LIMIT 1",
        [$user['user_id']]
    );
}
include __DIR__ . '/../../includes/header.php';
?>

<style>
.danger-zone{margin-top:18px;border:1px solid rgba(185,28,28,.16);border-radius:18px;padding:20px;background:linear-gradient(180deg,#fff 0%,#fff7f7 100%);box-shadow:0 16px 34px rgba(15,23,42,.06)}
.danger-zone h3{margin:0 0 10px;font-size:1.15rem;font-weight:900;color:#7f1d1d}
.danger-zone p{margin:0 0 14px;color:#7c2d12;line-height:1.6;font-size:.9rem}
.danger-zone label{display:block;margin-bottom:8px;font-size:.84rem;font-weight:800;color:#7c2d12}
.danger-zone .confirm-row{display:flex;gap:10px;align-items:stretch;flex-wrap:wrap}
.danger-zone .confirm-row .form-control{flex:1;min-width:180px}
.danger-zone .btn-danger{background:#dc2626;border:none;color:#fff;box-shadow:0 14px 24px rgba(220,38,38,.18)}
.danger-zone .btn-danger:hover{background:#b91c1c}
.danger-zone .btn-warning{background:#f59e0b;border:none;color:#fff;box-shadow:0 14px 24px rgba(245,158,11,.18)}
.danger-zone .btn-warning:hover{background:#d97706}
.danger-zone .btn-outline-secondary{border:1px solid #cbd5e1;background:#fff;color:#334155}
.danger-zone .danger-block + .danger-block{margin-top:22px;padding-top:22px;border-top:1px dashed rgba(127,29,29,.15)}
.danger-pill{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:#fff3cd;color:#92400e;font-size:.78rem;font-weight:800;margin-bottom:12px;border:1px solid rgba(146,64,14,.15)}
body.dark-mode .danger-zone{background:linear-gradient(180deg,#1f1114 0%,#17151f 100%) !important;border-color:rgba(248,113,113,.22) !important;box-shadow:0 18px 36px rgba(2,6,23,.28) !important}
body.dark-mode .danger-zone h3{color:#fecaca !important}
body.dark-mode .danger-zone p,body.dark-mode .danger-zone label{color:#fca5a5 !important}
body.dark-mode .danger-zone .btn-outline-secondary{background:#111827 !important;color:#e5eef5 !important;border-color:#334155 !important}
body.dark-mode .danger-pill{background:#3b2a10 !important;color:#fff7d6 !important;border-color:#92400e !important}
</style>

<div class="page-header">
    <h1 class="page-title"><span class="icon">👤</span> My Profile</h1>
</div>

<div class="grid-2">
    <div class="card">
        <div class="profile-header-card" style="margin-bottom:0;padding:0;box-shadow:none;flex-direction:column;align-items:flex-start">
            <div style="display:flex;align-items:center;gap:20px;margin-bottom:24px">
                <?php if ($profile && $profile['avatar_url']): ?>
                <img src="<?= APP_URL.'/'.$profile['avatar_url'] ?>" style="width:90px;height:90px;border-radius:50%;object-fit:cover" alt="Avatar">
                <?php else: ?>
                <div class="profile-avatar-lg"><?= strtoupper(substr(($user['username'] ?? 'U'), 0, 1)) ?></div>
                <?php endif; ?>
                <div>
                    <div class="profile-name"><?= sanitize($profile['full_name'] ?? $user['username'] ?? 'User') ?></div>
                    <div class="profile-sub">@<?= sanitize($user['username'] ?? 'username') ?> · <?= sanitize($user['email'] ?? 'No email provided') ?></div>
                    <div class="role-tag mt-1">ADOPTER</div>
                </div>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?= sanitize($profile['full_name'] ?? '') ?>" placeholder="Your full name">
            </div>
            <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="tel" name="phone" class="form-control" value="<?= sanitize($profile['phone'] ?? '') ?>" placeholder="+63 9XX XXX XXXX">
            </div>
            <div class="grid-2" style="gap:16px">
                <div class="form-group">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" value="<?= sanitize($profile['city'] ?? '') ?>" placeholder="City">
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" value="<?= sanitize($profile['address'] ?? '') ?>" placeholder="Street address">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Bio</label>
                <textarea name="bio" class="form-control" rows="3" placeholder="Tell shelters about yourself and your experience with pets…"><?= sanitize($profile['bio'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Profile Photo</label>
                <input type="file" name="avatar" class="form-control" accept="image/*">
                <div class="form-hint">JPG, PNG, WebP. Max 5MB.</div>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Save Profile</button>
        </form>
    </div>

    <div>
        <div class="card mb-3">
            <div class="card-title"><i class="fas fa-shield-alt"></i> Account Information</div>
            <div style="display:flex;flex-direction:column;gap:12px">
                <div style="display:flex;justify-content:space-between;padding:12px;background:var(--teal-xlight);border-radius:10px">
                    <span style="font-weight:700">Username</span>
                    <span><?= sanitize($user['username'] ?? '') ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:12px;background:var(--teal-xlight);border-radius:10px">
                    <span style="font-weight:700">Email</span>
                    <span><?= sanitize($user['email'] ?? 'None Specified') ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:12px;background:var(--teal-xlight);border-radius:10px">
                    <span style="font-weight:700">Role</span>
                    <span class="badge badge-primary">ADOPTER</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title"><i class="fas fa-chart-bar"></i> My Stats</div>
            <?php
            $favCount = $db->fetch("SELECT COUNT(*) as c FROM favorites WHERE adopter_id = ?",[$user['user_id']])['c'] ?? 0;
            $appCount = $db->fetch("SELECT COUNT(*) as c FROM adoption_applications WHERE adopter_id = ?",[$user['user_id']])['c'] ?? 0;
            $apvCount = $db->fetch("SELECT COUNT(*) as c FROM adoption_applications WHERE adopter_id = ? AND status='Approved'",[$user['user_id']])['c'] ?? 0;
            ?>
            <div style="display:flex;flex-direction:column;gap:10px">
                <div style="display:flex;justify-content:space-between;padding:12px;background:var(--gray-bg);border-radius:10px">
                    <span>❤️ Favorites Saved</span><strong><?= $favCount ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:12px;background:var(--gray-bg);border-radius:10px">
                    <span>📋 Applications</span><strong><?= $appCount ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:12px;background:var(--gray-bg);border-radius:10px">
                    <span>🎉 Approved</span><strong><?= $apvCount ?></strong>
                </div>
            </div>
        </div>

        <div class="danger-zone">
            <h3><i class="fas fa-triangle-exclamation"></i> Account Controls</h3>
            <p>Deactivate your account immediately or schedule permanent deletion after a 7-day waiting period.</p>

            <div class="danger-block">
                <h3 style="font-size:1rem;margin-bottom:8px">Deactivate account</h3>
                <p>Your account will be disabled immediately and you will be signed out. You will not be able to log in until an admin reactivates it.</p>
                <form method="POST">
                    <input type="hidden" name="profile_action" value="deactivate_account">
                    <label for="deactivateConfirm">To confirm, type <strong>DEACTIVATE</strong></label>
                    <div class="confirm-row">
                        <input id="deactivateConfirm" type="text" name="deactivate_confirm" class="form-control" placeholder="Type DEACTIVATE">
                        <button type="submit" class="btn btn-warning">Deactivate Account</button>
                    </div>
                </form>
            </div>

            <div class="danger-block">
                <h3 style="font-size:1rem;margin-bottom:8px">Delete account</h3>
                <?php if ($pendingDeletion): ?>
                    <div class="danger-pill"><i class="fas fa-clock"></i> Scheduled for deletion on <?= date('M j, Y g:i A', strtotime($pendingDeletion['scheduled_for'])) ?></div>
                    <p>Your account is queued for permanent deletion. You can still cancel it before the 7-day deadline.</p>
                    <form method="POST">
                        <input type="hidden" name="profile_action" value="cancel_delete">
                        <button type="submit" class="btn btn-outline-secondary">Cancel Scheduled Deletion</button>
                    </form>
                <?php else: ?>
                    <p>Deleting your account removes your adopter profile and schedules permanent account cleanup after 7 days. This cannot be undone after the deadline passes.</p>
                    <form method="POST">
                        <input type="hidden" name="profile_action" value="schedule_delete">
                        <label for="deleteConfirm">To confirm, type <strong>DELETE</strong></label>
                        <div class="confirm-row">
                            <input id="deleteConfirm" type="text" name="delete_confirm" class="form-control" placeholder="Type DELETE">
                            <button type="submit" class="btn btn-danger">Schedule Deletion</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
