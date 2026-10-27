<?php
require_once __DIR__ . '/../../includes/functions.php';
requireRole('ADOPTER');
$pageTitle = 'My Favorites';
$user = getCurrentUser();
$db   = Database::getInstance();

$pets = $db->fetchAll(
    "SELECT p.*, f.created_at as favorited_at, sp.shelter_name, sp.city as shelter_city,
     (SELECT pp.photo_url FROM pet_photos pp WHERE pp.pet_id = p.pet_id AND pp.is_primary = 1 LIMIT 1) as primary_photo
     FROM favorites f
     JOIN pets p ON f.pet_id = p.pet_id
     JOIN shelter_profiles sp ON p.shelter_id = sp.shelter_id
     WHERE f.adopter_id = ? AND p.status != 'Removed'
     ORDER BY f.created_at DESC",
    [$user['user_id']]
);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="icon">❤️</span> My Favorites</h1>
    <a href="<?= APP_URL ?>/pages/adopter/browse.php" class="btn btn-primary">
        <i class="fas fa-search"></i> Discover More
    </a>
</div>

<?php if (empty($pets)): ?>
<div class="empty-state">
    <div class="empty-icon">💔</div>
    <h3>No favorites yet!</h3>
    <p>Browse pets and tap the ❤️ button to save your favorites here.</p>
    <a href="<?= APP_URL ?>/pages/adopter/browse.php" class="btn btn-primary">Browse Pets 🐾</a>
</div>
<?php else: ?>
<div class="pets-grid">
<?php foreach ($pets as $pet):
    $photo = $pet['primary_photo'] ? APP_URL.'/'.$pet['primary_photo'] : APP_URL.'/assets/images/pet-placeholder.png';
?>
<div class="pet-card" id="fav-<?= $pet['pet_id'] ?>">
    <div class="pet-card-img" style="position:relative">
        <img src="<?= $photo ?>" alt="<?= sanitize($pet['name']) ?>" loading="lazy" style="width:100%;height:200px;object-fit:cover" onerror="this.src='<?= APP_URL ?>/assets/images/pet-placeholder.png'">
        <div class="pet-species-badge"><?= htmlspecialchars($pet['species']) ?></div>
        <button class="pet-fav-btn active" onclick="event.stopPropagation();removeFav(<?= $pet['pet_id'] ?>,this)" title="Remove from favorites">❤️</button>
    </div>
    <div class="pet-card-body" onclick="window.location='<?= APP_URL ?>/pages/adopter/pet-detail.php?id=<?= $pet['pet_id'] ?>'">
        <div class="pet-card-name"><?= sanitize($pet['name']) ?></div>
        <div class="pet-card-meta">
            <span>🐾 <?= htmlspecialchars($pet['breed'] ?: $pet['species']) ?></span>
            <span>📅 <?= formatAge($pet['age_months']) ?></span>
        </div>
        <div style="font-size:.8rem;color:var(--gray-mid)">
            <i class="fas fa-building"></i> <?= sanitize($pet['shelter_name']) ?>
        </div>
    </div>
    <div class="pet-card-footer">
        <?= statusBadge($pet['status']) ?>
        <a href="<?= APP_URL ?>/pages/adopter/pet-detail.php?id=<?= $pet['pet_id'] ?>" class="btn btn-primary btn-sm">
            <?= $pet['status'] === 'Available' ? 'Adopt →' : 'View →' ?>
        </a>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<script>
async function removeFav(petId, btn) {
    const card = document.getElementById('fav-' + petId);
    const res  = await apiRequest('/PAWAdopt/api/favorites.php', 'POST', new URLSearchParams({action:'toggle',pet_id:petId}));
    if (res.success && !res.data.favorited) {
        card.style.opacity = '0';
        card.style.transform = 'scale(.9)';
        card.style.transition = 'all .3s';
        setTimeout(() => card.remove(), 300);
        showToast('Removed from favorites');
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
