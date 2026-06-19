<?php
require_once __DIR__ . '/../../includes/functions.php';
requireRole('ADOPTER');
$pageTitle = 'Dashboard';
$user = getCurrentUser();
$db = Database::getInstance();

// Stats
$favCount = $db->fetch("SELECT COUNT(*) as c FROM favorites WHERE adopter_id = ?", [$user['user_id']])['c'] ?? 0;
$appCount = $db->fetch("SELECT COUNT(*) as c FROM adoption_applications WHERE adopter_id = ?", [$user['user_id']])['c'] ?? 0;
$petCount = $db->fetch("SELECT COUNT(*) as c FROM pets WHERE status = 'Available'")['c'] ?? 0;
$msgCount = getUnreadMessageCount($user['user_id']);

// Profile
$profile = $db->fetch("SELECT * FROM adopter_profiles WHERE adopter_id = ?", [$user['user_id']]);

// Recent applications
$recentApps = $db->fetchAll(
    "SELECT aa.*, p.name as pet_name, p.species, sp.shelter_name,
     (SELECT pp.photo_url FROM pet_photos pp WHERE pp.pet_id = p.pet_id AND pp.is_primary = 1 LIMIT 1) as pet_photo
     FROM adoption_applications aa
     JOIN pets p ON aa.pet_id = p.pet_id
     JOIN shelter_profiles sp ON aa.shelter_id = sp.shelter_id
     WHERE aa.adopter_id = ?
     ORDER BY aa.submitted_at DESC LIMIT 5",
    [$user['user_id']]
);

// Featured pets
$featuredPets = $db->fetchAll(
    "SELECT p.*, sp.shelter_name, sp.city as shelter_city,
     (SELECT pp.photo_url FROM pet_photos pp WHERE pp.pet_id = p.pet_id AND pp.is_primary = 1 LIMIT 1) as primary_photo
     FROM pets p JOIN shelter_profiles sp ON p.shelter_id = sp.shelter_id
     WHERE p.status = 'Available'
     ORDER BY RAND() LIMIT 4"
);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="icon">🏠</span> Welcome back, <?= sanitize($profile['full_name'] ?? $user['username']) ?>!</h1>
    <a href="<?= APP_URL ?>/pages/adopter/browse.php" class="btn btn-primary">
        <i class="fas fa-search"></i> Browse Pets
    </a>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon teal"><span>🐾</span></div>
        <div>
            <div class="stat-value"><?= $petCount ?></div>
            <div class="stat-label">Pets Available</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><span>❤️</span></div>
        <div>
            <div class="stat-value"><?= $favCount ?></div>
            <div class="stat-label">Favorites Saved</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><span>📋</span></div>
        <div>
            <div class="stat-value"><?= $appCount ?></div>
            <div class="stat-label">Applications</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><span>💬</span></div>
        <div>
            <div class="stat-value"><?= $msgCount ?></div>
            <div class="stat-label">New Messages</div>
        </div>
    </div>
</div>

<div class="quick-actions mb-3">
    <a href="<?= APP_URL ?>/pages/adopter/browse.php" class="quick-action-btn">
        <span class="qa-icon">🔍</span><span class="qa-label">Browse Pets</span>
    </a>
    <a href="<?= APP_URL ?>/pages/adopter/favorites.php" class="quick-action-btn">
        <span class="qa-icon">❤️</span><span class="qa-label">Favorites</span>
    </a>
    <a href="<?= APP_URL ?>/pages/adopter/applications.php" class="quick-action-btn">
        <span class="qa-icon">📋</span><span class="qa-label">Applications</span>
    </a>
    <a href="<?= APP_URL ?>/pages/adopter/messages.php" class="quick-action-btn">
        <span class="qa-icon">💬</span><span class="qa-label">Messages</span>
    </a>
    <a href="<?= APP_URL ?>/pages/adopter/profile.php" class="quick-action-btn">
        <span class="qa-icon">👤</span><span class="qa-label">Profile</span>
    </a>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title"><i class="fas fa-file-alt"></i> Recent Applications</div>
        <?php if (empty($recentApps)): ?>
        <div class="empty-state" style="padding:30px 0">
            <div class="empty-icon">🐾</div>
            <p>No applications yet. <a href="<?= APP_URL ?>/pages/adopter/browse.php">Browse pets</a> to get started!</p>
        </div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Pet</th><th>Shelter</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($recentApps as $app): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <?php $photo = $app['pet_photo'] ? APP_URL.'/'.$app['pet_photo'] : APP_URL.'/assets/images/pet-placeholder.png'; ?>
                            <img src="<?= $photo ?>" class="pet-thumb" alt="<?= sanitize($app['pet_name']) ?>" onerror="this.src='<?= APP_URL ?>/assets/images/pet-placeholder.png'">
                            <div>
                                <div class="fw-bold"><?= sanitize($app['pet_name']) ?></div>
                                <div class="text-muted" style="font-size:.78rem"><?= sanitize($app['species']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= sanitize($app['shelter_name']) ?></td>
                    <td><?= statusBadge($app['status']) ?></td>
                    <td style="font-size:.82rem;color:var(--gray-mid)"><?= date('M j', strtotime($app['submitted_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-2">
            <a href="<?= APP_URL ?>/pages/adopter/applications.php" class="btn btn-outline btn-sm">View All Applications</a>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><i class="fas fa-star"></i> Featured Pets</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <?php foreach ($featuredPets as $p):
                $photo = $p['primary_photo'] ? APP_URL.'/'.$p['primary_photo'] : APP_URL.'/assets/images/pet-placeholder.png';
            ?>
            <a href="<?= APP_URL ?>/pages/adopter/pet-detail.php?id=<?= $p['pet_id'] ?>" style="text-decoration:none;color:inherit;">
                <div class="pet-card" style="cursor:pointer">
                    <div class="pet-card-img" style="height:130px">
                        <img src="<?= $photo ?>" alt="<?= sanitize($p['name']) ?>" onerror="this.src='<?= APP_URL ?>/assets/images/pet-placeholder.png'" style="height:130px;object-fit:cover;width:100%">
                        <div class="pet-species-badge"><?= $p['species'] ?></div>
                    </div>
                    <div class="pet-card-body" style="padding:10px">
                        <div class="pet-card-name" style="font-size:.95rem"><?= sanitize($p['name']) ?></div>
                        <div class="pet-card-meta">
                            <span>🏠 <?= sanitize($p['shelter_city'] ?? '') ?></span>
                        </div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
            <?php if (empty($featuredPets)): ?>
            <div class="empty-state" style="grid-column:span 2;padding:20px">
                <p>No pets available right now.</p>
            </div>
            <?php endif; ?>
        </div>
        <div class="mt-2">
            <a href="<?= APP_URL ?>/pages/adopter/browse.php" class="btn btn-primary btn-sm">View All Pets 🐾</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>