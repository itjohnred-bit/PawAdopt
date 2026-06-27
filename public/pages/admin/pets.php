<?php
require_once __DIR__ . '/../../../includes/functions.php';
requireRole('ADMIN');
$pageTitle = 'Pet Listings';
$user = getCurrentUser();
$db   = Database::getInstance();

$statusFilter = $_GET['status'] ?? '';
$search       = trim($_GET['search'] ?? '');

$where  = []; $params = [];
if ($statusFilter) { $where[] = 'p.status = ?'; $params[] = $statusFilter; }
if ($search)       { $where[] = '(p.name LIKE ? OR p.breed LIKE ? OR sp.veterinary_name LIKE ?)'; $params = array_merge($params,["%$search%","%$search%","%$search%"]); }
$whereStr = $where ? 'WHERE '.implode(' AND ',$where) : '';

$pets = $db->fetchAll(
    "SELECT p.*, sp.veterinary_name, sp.city as veterinary_city,
     (SELECT pp.photo_url FROM pet_photos pp WHERE pp.pet_id = p.pet_id AND pp.is_primary = 1 LIMIT 1) as primary_photo
     FROM pets p JOIN veterinary_profiles sp ON p.veterinary_id = sp.veterinary_id
     $whereStr ORDER BY p.created_at DESC",
    $params
);

include __DIR__ . '/../../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="icon">🐾</span> Pet Listings</h1>
    <span class="text-muted"><?= count($pets) ?> listing<?= count($pets)!==1?'s':'' ?></span>
</div>

<form method="GET">
<div class="filter-bar">
    <div class="search-input-wrap">
        <i class="fas fa-search search-icon"></i>
        <input type="text" name="search" class="form-control" placeholder="Search pets or veterinarys…" value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="status" class="form-control" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <?php foreach (['Available','Pending','Adopted','Removed'] as $s): ?>
        <option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= $s ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
    <?php if ($search||$statusFilter): ?><a href="?" class="btn btn-secondary">Clear</a><?php endif; ?>
</div>
</form>

<div class="card" style="padding:0">
<div class="table-wrap">
<table class="data-table">
    <thead><tr><th>Photo</th><th>Name</th><th>Species / Breed</th><th>veterinary</th><th>Age</th><th>Status</th><th>Posted</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($pets as $pet):
        $photo = $pet['primary_photo'] ? APP_URL.'/'.$pet['primary_photo'] : APP_URL.'/assets/images/pet-placeholder.png';
    ?>
    <tr>
        <td><img src="<?= $photo ?>" class="pet-thumb" onerror="this.src='<?= APP_URL ?>/assets/images/pet-placeholder.png'" alt=""></td>
        <td class="fw-bold"><?= sanitize($pet['name']) ?></td>
        <td><?= htmlspecialchars($pet['species']) ?><?= $pet['breed']?' / '.sanitize($pet['breed']):'' ?></td>
        <td><?= sanitize($pet['veterinary_name']) ?><?= $pet['veterinary_city']?' · '.sanitize($pet['veterinary_city']):'' ?></td>
        <td><?= formatAge($pet['age_months']) ?></td>
        <td><?= statusBadge($pet['status']) ?></td>
        <td class="text-muted" style="font-size:.82rem"><?= date('M j, Y', strtotime($pet['created_at'])) ?></td>
        <td>
            <?php if ($pet['status'] !== 'Removed'): ?>
            <button onclick="removePet(<?= $pet['pet_id'] ?>)" class="btn btn-danger btn-sm">Remove</button>
            <?php else: ?>
            <span class="text-muted" style="font-size:.8rem">Removed</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<script>
async function removePet(petId) {
    confirmAction('Remove this pet listing? It will be hidden from adopters.', async () => {
        const res = await apiRequest(window.BASE_URL + '/api/admin.php', 'POST', new URLSearchParams({
            action: 'remove_pet',
            pet_id: petId
        }));

        if (res.success) {
            showToast('Pet removed.');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast(res.message, 'error');
        }
    });
}
</script>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
