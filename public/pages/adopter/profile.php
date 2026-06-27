<?php
require_once __DIR__ . '/../../includes/functions.php';
requireRole('ADOPTER');
$pageTitle = 'My Profile';
$user = getCurrentUser();
$db   = Database::getInstance();

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    // Handle avatar upload
    if (!empty($_FILES['avatar']['name'])) {
        $avatarUrl = uploadImage($_FILES['avatar'], 'avatars');
        if ($avatarUrl) {
            $db->execute("UPDATE adopter_profiles SET avatar_url = ? WHERE adopter_id = ?", [$avatarUrl, $user['user_id']]);
        }
    }

    flashMessage('success', 'Profile updated!');
    redirect(APP_URL . '/pages/adopter/profile.php');
}

$profile = $db->fetch("SELECT * FROM adopter_profiles WHERE adopter_id = ?", [$user['user_id']]);
include __DIR__ . '/../../includes/header.php';
?>

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
                <textarea name="bio" class="form-control" rows="3" placeholder="Tell veterinarys about yourself and your experience with pets…"><?= sanitize($profile['bio'] ?? '') ?></textarea>
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
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>