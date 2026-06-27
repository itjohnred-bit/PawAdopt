<?php
require_once __DIR__ . '/../../includes/functions.php';
requireRole('VETERINARY');
$pageTitle = 'veterinary Profile';
$user = getCurrentUser();
$db   = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $veterinary_name = trim($_POST['veterinary_name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $city         = trim($_POST['city'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $website      = trim($_POST['website'] ?? '');

    if (!$veterinary_name) { flashMessage('error','veterinary name is required.'); redirect(APP_URL.'/pages/veterinary/profile.php'); }

    $db->execute(
        "UPDATE veterinary_profiles SET veterinary_name=?,phone=?,address=?,city=?,description=?,website=?,updated_at=NOW() WHERE veterinary_id=?",
        [$veterinary_name,$phone,$address,$city,$description,$website,$user['user_id']]
    );

    if (!empty($_FILES['logo']['name'])) {
        $logoUrl = uploadImage($_FILES['logo'], 'logos');
        if ($logoUrl) $db->execute("UPDATE veterinary_profiles SET logo_url=? WHERE veterinary_id=?", [$logoUrl, $user['user_id']]);
    }
    flashMessage('success','Profile updated!');
    redirect(APP_URL.'/pages/veterinary/profile.php');
}

$profile = $db->fetch("SELECT * FROM veterinary_profiles WHERE veterinary_id = ?", [$user['user_id']]);
$verif   = $db->fetch("SELECT * FROM veterinary_verifications WHERE veterinary_id = ?", [$user['user_id']]);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="icon">🏠</span> veterinary Profile</h1>
</div>

<div class="grid-2">
    <div class="card">
        <div style="display:flex;align-items:center;gap:20px;margin-bottom:24px">
            <?php if ($profile['logo_url']): ?>
            <img src="<?= APP_URL.'/'.$profile['logo_url'] ?>" style="width:90px;height:90px;border-radius:50%;object-fit:cover" alt="Logo">
            <?php else: ?>
            <div class="profile-avatar-lg">🏠</div>
            <?php endif; ?>
            <div>
                <div class="profile-name"><?= sanitize($profile['veterinary_name'] ?? '') ?></div>
                <div class="profile-sub">@<?= sanitize($user['username']) ?></div>
                <div class="role-tag mt-1">VETERINARY</div>
                <?php if ($profile['is_verified']): ?>
                <span class="badge badge-success" style="margin-top:4px">✓ Verified</span>
                <?php else: ?>
                <span class="badge badge-warning" style="margin-top:4px">⏳ Pending Verification</span>
                <?php endif; ?>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">veterinary Name *</label>
                <input type="text" name="veterinary_name" class="form-control" value="<?= sanitize($profile['veterinary_name'] ?? '') ?>" required>
            </div>
            <div class="grid-2" style="gap:16px">
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" name="phone" class="form-control" value="<?= sanitize($profile['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" value="<?= sanitize($profile['city'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control" value="<?= sanitize($profile['address'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Website</label>
                <input type="url" name="website" class="form-control" value="<?= sanitize($profile['website'] ?? '') ?>" placeholder="https://yourveterinary.com">
            </div>
            <div class="form-group">
                <label class="form-label">About Your veterinary</label>
                <textarea name="description" class="form-control" rows="4"><?= sanitize($profile['description'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">veterinary Logo</label>
                <input type="file" name="logo" class="form-control" accept="image/*">
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Save Profile</button>
        </form>
    </div>

    <div>
        <!-- Verification Status -->
        <div class="card mb-3">
            <div class="card-title"><i class="fas fa-shield-alt"></i> Verification Status</div>
            <?php if ($verif): ?>
            <div style="background:<?= $verif['status']==='APPROVED'?'#ecfdf5':($verif['status']==='REJECTED'?'#fef2f2':'#fffbeb') ?>;border-radius:12px;padding:16px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <span class="fw-bold">Status</span>
                    <?= statusBadge($verif['status']) ?>
                </div>
                <?php if ($verif['note']): ?>
                <div style="font-size:.85rem;color:var(--gray-dark)"><strong>Admin note:</strong> <?= sanitize($verif['note']) ?></div>
                <?php endif; ?>
                <?php if ($verif['reviewed_at']): ?>
                <div style="font-size:.78rem;color:var(--gray-mid);margin-top:6px">Reviewed: <?= date('M j, Y', strtotime($verif['reviewed_at'])) ?></div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <p class="text-muted" style="font-size:.88rem">No verification record found.</p>
            <?php endif; ?>
        </div>

        <!-- veterinary Stats -->
        <div class="card">
            <div class="card-title"><i class="fas fa-chart-bar"></i> veterinary Stats</div>
            <?php
            $totPets  = $db->fetch("SELECT COUNT(*) as c FROM pets WHERE veterinary_id=? AND status!='Removed'",[$user['user_id']])['c']??0;
            $adopted  = $db->fetch("SELECT COUNT(*) as c FROM pets WHERE veterinary_id=? AND status='Adopted'",[$user['user_id']])['c']??0;
            $apps     = $db->fetch("SELECT COUNT(*) as c FROM adoption_applications WHERE veterinary_id=?",[$user['user_id']])['c']??0;
            ?>
            <div style="display:flex;flex-direction:column;gap:10px">
                <div style="display:flex;justify-content:space-between;padding:12px;background:var(--gray-bg);border-radius:10px">
                    <span>🐾 Total Pet Listings</span><strong><?= $totPets ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:12px;background:var(--gray-bg);border-radius:10px">
                    <span>🎉 Pets Adopted</span><strong><?= $adopted ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:12px;background:var(--gray-bg);border-radius:10px">
                    <span>📋 Total Applications</span><strong><?= $apps ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
