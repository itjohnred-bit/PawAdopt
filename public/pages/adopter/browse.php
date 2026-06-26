<?php
require_once __DIR__ . '/../../includes/functions.php';
requireRole('ADOPTER');
$pageTitle = 'Browse Pets';
$user = getCurrentUser();
$db   = Database::getInstance();

// Filters from GET
$species = $_GET['species'] ?? '';
$sex     = $_GET['sex'] ?? '';
$size    = $_GET['size'] ?? '';
$search  = $_GET['search'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 12;
$offset  = ($page - 1) * $limit;

// Build query
$where  = ['p.status = ?'];
$params = ['Available'];
if ($species) { $where[] = 'p.species = ?'; $params[] = $species; }
if ($sex)     { $where[] = 'p.sex = ?';     $params[] = $sex; }
if ($size)    { $where[] = 'p.size = ?';    $params[] = $size; }
if ($search)  {
    $where[] = '(p.name LIKE ? OR p.breed LIKE ? OR sp.shelter_name LIKE ?)';
    $params = array_merge($params, ["%$search%","%$search%","%$search%"]);
}
$whereStr = implode(' AND ', $where);

$totalRow = $db->fetch("SELECT COUNT(*) as c FROM pets p JOIN shelter_profiles sp ON p.shelter_id = sp.shelter_id WHERE $whereStr", $params);
$total  = $totalRow ? (int)$totalRow['c'] : 0;
$pages  = max(1, ceil($total / $limit));

$pets = $db->fetchAll(
    "SELECT p.*, sp.shelter_name, sp.city as shelter_city,
     (SELECT pp.photo_url FROM pet_photos pp WHERE pp.pet_id = p.pet_id AND pp.is_primary = 1 LIMIT 1) as primary_photo
     FROM pets p JOIN shelter_profiles sp ON p.shelter_id = sp.shelter_id
     WHERE $whereStr ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset",
    $params
);

// Get favorites
$favRes = $db->fetchAll("SELECT pet_id FROM favorites WHERE adopter_id = ?", [$user['user_id']]);
$favIds = array_column($favRes, 'pet_id');

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="icon">🔍</span> Browse Adoptable Pets</h1>
    <span class="text-muted"><?= $total ?> pet<?= $total !== 1 ? 's' : '' ?> found</span>
</div>

<!-- Filters -->
<form method="GET" id="filterForm">
<div class="filter-bar">
    <div class="search-input-wrap">
        <i class="fas fa-search search-icon"></i>
        <input type="text" name="search" class="form-control" placeholder="Search pets..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="species" class="form-control" onchange="this.form.submit()">
        <option value="">All Species</option>
        <?php foreach (['Dog','Cat'] as $s): ?>
        <option value="<?= $s ?>" <?= $species === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
    </select>
    <select name="sex" class="form-control" onchange="this.form.submit()">
        <option value="">Any Gender</option>
        <option value="Male"    <?= $sex === 'Male'    ? 'selected' : '' ?>>Male</option>
        <option value="Female"  <?= $sex === 'Female'  ? 'selected' : '' ?>>Female</option>
    </select>
    <select name="size" class="form-control" onchange="this.form.submit()">
        <option value="">Any Size</option>
        <?php foreach (['Tiny','Small','Medium','Large','Giant'] as $sz): ?>
        <option value="<?= $sz ?>" <?= $size === $sz ? 'selected' : '' ?>><?= $sz ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
    <?php if ($search || $species || $sex || $size): ?>
    <a href="<?= APP_URL ?>/pages/adopter/browse.php" class="btn btn-secondary">Clear</a>
    <?php endif; ?>
</div>
</form>

<!-- Pet Grid -->
<?php if (empty($pets)): ?>
<div class="empty-state">
    <div class="empty-icon">🐾</div>
    <h3>No pets found</h3>
    <p>Try adjusting your filters or check back later!</p>
    <a href="<?= APP_URL ?>/pages/adopter/browse.php" class="btn btn-primary">Clear Filters</a>
</div>
<?php else: ?>
<div class="pets-grid">
<?php foreach ($pets as $pet):
    $photo = $pet['primary_photo'] ? APP_URL.'/'.$pet['primary_photo'] : APP_URL.'/assets/images/pet-placeholder.png';
    $isFav = in_array($pet['pet_id'], $favIds);
    $ageDisplay = formatAge($pet['age_months']);
?>
<div class="pet-card">
    <div class="pet-card-img" style="position:relative">
        <img src="<?= $photo ?>" alt="<?= sanitize($pet['name']) ?>" loading="lazy" onerror="this.src='<?= APP_URL ?>/assets/images/pet-placeholder.png'" style="width:100%;height:200px;object-fit:cover">
        <div class="pet-species-badge"><?= htmlspecialchars($pet['species']) ?></div>
        <button class="pet-fav-btn <?= $isFav ? 'active' : '' ?>"
                onclick="event.stopPropagation();toggleFavorite(<?= $pet['pet_id'] ?>,this)"
                title="<?= $isFav ? 'Remove from favorites' : 'Add to favorites' ?>">
            <?= $isFav ? '❤️' : '🤍' ?>
        </button>
    </div>
    <div class="pet-card-body" onclick="window.location='<?= APP_URL ?>/pages/adopter/pet-detail.php?id=<?= $pet['pet_id'] ?>'">
        <div class="pet-card-name"><?= sanitize($pet['name']) ?></div>
        <div class="pet-card-meta">
            <span>🐾 <?= htmlspecialchars($pet['breed'] ?: $pet['species']) ?></span>
            <span>📅 <?= $ageDisplay ?></span>
            <span><?= $pet['sex'] === 'Male' ? '♂' : ($pet['sex'] === 'Female' ? '♀' : '⚥') ?> <?= $pet['sex'] ?></span>
        </div>
        <div style="font-size:.8rem;color:var(--gray-mid)">
            <i class="fas fa-building"></i> <?= sanitize($pet['shelter_name']) ?>
            <?php if ($pet['shelter_city']): ?> · <?= sanitize($pet['shelter_city']) ?><?php endif; ?>
        </div>
    </div>
    <div class="pet-card-footer">
        <?= statusBadge($pet['status']) ?>
        <a href="<?= APP_URL ?>/pages/adopter/pet-detail.php?id=<?= $pet['pet_id'] ?>" class="btn btn-primary btn-sm">
            View &rarr;
        </a>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-btn">&laquo;</a>
    <?php endif; ?>
    <?php for ($i = max(1, $page-2); $i <= min($pages, $page+2); $i++): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $pages): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-btn">&raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
