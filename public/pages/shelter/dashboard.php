<?php
require_once __DIR__ . '/../../includes/functions.php';
requireRole('VETERINARY');
$pageTitle = 'veterinary Dashboard';
$user = getCurrentUser();
$db   = Database::getInstance();

$profile = $db->fetch("SELECT * FROM veterinary_profiles WHERE veterinary_id = ?", [$user['user_id']]);
$verif   = $db->fetch("SELECT * FROM veterinary_verifications WHERE veterinary_id = ?", [$user['user_id']]);

$petCount  = $db->fetch("SELECT COUNT(*) as c FROM pets WHERE veterinary_id = ? AND status != 'Removed'", [$user['user_id']])['c'] ?? 0;
$availPets = $db->fetch("SELECT COUNT(*) as c FROM pets WHERE veterinary_id = ? AND status = 'Available'", [$user['user_id']])['c'] ?? 0;

$appStats = $db->fetch("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN aa.status = 'Submitted' THEN 1 END) as pending
    FROM adoption_applications aa
    JOIN pets p ON aa.pet_id = p.pet_id
    WHERE p.veterinary_id = ?", 
    [$user['user_id']]
);

$appCount = $appStats['total'] ?? 0;
$pendApps = $appStats['pending'] ?? 0;
$msgCount = getUnreadMessageCount($user['user_id']);

$recentApps = $db->fetchAll("
    SELECT aa.*, p.name as pet_name, u.username as adopter_username,
        (SELECT pp.photo_url FROM pet_photos pp WHERE pp.pet_id = p.pet_id AND pp.is_primary = 1 LIMIT 1) as pet_photo
    FROM adoption_applications aa
    JOIN pets p ON aa.pet_id = p.pet_id
    JOIN users u ON aa.adopter_id = u.user_id
    WHERE p.veterinary_id = ?
    ORDER BY aa.submitted_at DESC LIMIT 5",
    [$user['user_id']]
);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:30px">
    <h1 class="page-title" style="font-weight:900;margin:0">🏠 <?= sanitize($profile['veterinary_name'] ?? 'My veterinary') ?></h1>
    <a href="<?= APP_URL ?>/pages/veterinary/add-pet.php" class="btn btn-primary" style="background:var(--active-text);color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:700">
        <i class="fas fa-plus"></i> Add New Pet
    </a>
</div>

<?php if (isset($profile['is_verified']) && !$profile['is_verified']): ?>
<div style="background:#fffbeb;border:2px solid #f59e0b;border-radius:14px;padding:16px;margin-bottom:24px;display:flex;gap:12px;align-items:center">
    <span style="font-size:1.8rem">⚠️</span>
    <div>
        <div style="font-weight:800;color:#92400e">Verification Pending</div>
        <div style="color:#78350f;font-size:.88rem">Admin is reviewing your veterinary. 
            Status: <strong><?= $verif ? sanitize($verif['status']) : 'Not submitted' ?></strong>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:20px;margin-bottom:30px">
    <div class="stat-card" style="background:#fff;padding:20px;border-radius:15px;display:flex;align-items:center;gap:15px;border:1px solid #eee">
        <div style="background:var(--active-bg);color:var(--active-text);width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem">🐾</div>
        <div><div style="font-size:1.5rem;font-weight:900"><?= $petCount ?></div><div style="color:#888;font-size:0.85rem">Total Pets</div></div>
    </div>
    <div class="stat-card" style="background:#fff;padding:20px;border-radius:15px;display:flex;align-items:center;gap:15px;border:1px solid #eee">
        <div style="background:#e6fffa;color:#047481;width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem">✅</div>
        <div><div style="font-size:1.5rem;font-weight:900"><?= $availPets ?></div><div style="color:#888;font-size:0.85rem">Available</div></div>
    </div>
    <div class="stat-card" style="background:#fff;padding:20px;border-radius:15px;display:flex;align-items:center;gap:15px;border:1px solid #eee">
        <div style="background:#fffbea;color:#92400e;width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem">📋</div>
        <div><div style="font-size:1.5rem;font-weight:900"><?= $pendApps ?></div><div style="color:#888;font-size:0.85rem">Pending Apps</div></div>
    </div>
    <div class="stat-card" style="background:#fff;padding:20px;border-radius:15px;display:flex;align-items:center;gap:15px;border:1px solid #eee">
        <div style="background:#ebf8ff;color:#2c5282;width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem">💬</div>
        <div><div style="font-size:1.5rem;font-weight:900"><?= $msgCount ?></div><div style="color:#888;font-size:0.85rem">Messages</div></div>
    </div>
</div>

<!-- Table -->
<div class="card" style="background:#fff;padding:25px;border-radius:15px;border:1px solid #eee">
    <div style="font-weight:900;margin-bottom:20px;font-size:1.1rem"><i class="fas fa-file-alt"></i> Recent Applications</div>
    <?php if (empty($recentApps)): ?>
        <p style="text-align:center;color:#888;padding:20px">No recent applications found.</p>
    <?php else: ?>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <tr style="text-align:left;border-bottom:2px solid #f8f8f8;color:#888;font-size:0.8rem">
                    <th style="padding:12px">PET</th>
                    <th>APPLICANT</th>
                    <th>STATUS</th>
                    <th>ACTIONS</th>
                </tr>
                <?php foreach ($recentApps as $app): 
                    $photo = $app['pet_photo'] ? APP_URL.'/'.$app['pet_photo'] : APP_URL.'/assets/images/pet-placeholder.png';
                ?>
                <tr style="border-bottom:1px solid #f8f8f8">
                    <td style="padding:15px 12px">
                        <div style="display:flex;align-items:center;gap:12px">
                            <img src="<?= $photo ?>" style="width:40px;height:40px;border-radius:8px;object-fit:cover" alt="">
                            <span style="font-weight:700"><?= sanitize($app['pet_name']) ?></span>
                        </div>
                    </td>
                    <td><?= sanitize($app['adopter_username']) ?></td>
                    <td><span style="background:#eee;padding:4px 10px;border-radius:20px;font-size:0.7rem;font-weight:700"><?= strtoupper($app['status']) ?></span></td>
                    <td><a href="<?= APP_URL ?>/pages/veterinary/applications.php?id=<?= $app['application_id'] ?>" style="color:var(--active-text);text-decoration:none;font-weight:700">Review</a></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
