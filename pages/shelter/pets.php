<?php
require_once __DIR__ . '/../../includes/functions.php';
requireRole('SHELTER');
$pageTitle = 'My Pet Listings';
$user = getCurrentUser();
$db   = Database::getInstance();

$status = $_GET['status'] ?? '';
$where  = ['p.shelter_id = ?'];
$params = [$user['user_id']];
if ($status) { $where[] = 'p.status = ?'; $params[] = $status; }
$whereStr = implode(' AND ', $where);

$pets = $db->fetchAll(
    "SELECT p.*,
     (SELECT pp.photo_url FROM pet_photos pp WHERE pp.pet_id = p.pet_id AND pp.is_primary = 1 LIMIT 1) as primary_photo,
     (SELECT COUNT(*) FROM adoption_applications aa WHERE aa.pet_id = p.pet_id) as app_count
     FROM pets p WHERE $whereStr ORDER BY p.created_at DESC",
    $params
);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="icon">🐾</span> My Pet Listings</h1>
    <a href="<?= APP_URL ?>/pages/shelter/add-pet.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Pet</a>
</div>

<!-- Status filters -->
<div class="tabs">
    <a href="?status=" class="tab-btn <?= !$status ? 'active' : '' ?>" style="text-decoration:none">All</a>
    <?php foreach (['Available','Pending','Adopted','Removed'] as $s): ?>
    <a href="?status=<?= $s ?>" class="tab-btn <?= $status === $s ? 'active' : '' ?>" style="text-decoration:none"><?= $s ?></a>
    <?php endforeach; ?>
</div>

<?php if (empty($pets)): ?>
<div class="empty-state">
    <div class="empty-icon">🐾</div>
    <h3>No pet listings found</h3>
    <p>Start by adding your first adoptable pet!</p>
    <a href="<?= APP_URL ?>/pages/shelter/add-pet.php" class="btn btn-primary">Add a Pet</a>
</div>
<?php else: ?>
<div class="table-wrap card" style="padding:0">
    <table class="data-table">
        <thead>
            <tr><th>Photo</th><th>Name</th><th>Species / Breed</th><th>Age</th><th>Status</th><th>Apps</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($pets as $pet):
            if (!empty($pet['primary_photo'])) {
                $photo = 'data:image/jpeg;base64,' . base64_encode($pet['primary_photo']);
            } else {
                $photo = APP_URL . '/assets/images/pet-placeholder.png';
            }
        ?>
        <tr>
            <td><img src="<?= $photo ?>" class="pet-thumb" alt="" onerror="this.src='<?= APP_URL ?>/assets/images/pet-placeholder.png'"></td>
            <td class="fw-bold"><?= sanitize($pet['name']) ?></td>
            <td><?= htmlspecialchars($pet['species']) ?><?= $pet['breed'] ? ' / '.sanitize($pet['breed']) : '' ?></td>
            <td><?= formatAge($pet['age_months']) ?></td>
            <td><?= statusBadge($pet['status']) ?></td>
            <td>
                <a href="<?= APP_URL ?>/pages/shelter/applications.php?pet=<?= $pet['pet_id'] ?>" class="badge badge-info" style="text-decoration:none">
                    <?= $pet['app_count'] ?> apps
                </a>
            </td>
            <td>
                <div style="display:flex;gap:6px">
                    <a href="<?= APP_URL ?>/pages/shelter/edit-pet.php?id=<?= $pet['pet_id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                    <?php if ($pet['status'] !== 'Removed'): ?>
                    <button onclick="deletePet(<?= $pet['pet_id'] ?>)" class="btn btn-danger btn-sm">Remove</button>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script>

function safeConfirm(message, callback) {
    if (typeof confirmAction === 'function') {
        confirmAction(message, callback);
    } else {
        if (confirm(message)) callback();
    }
}

function safeToast(message, type = 'success') {
    if (typeof showToast === 'function') {
        showToast(message, type);
    } else {
        alert(message);
    }
}

async function deletePet(petId) {
    safeConfirm('Remove this pet listing? Adopters will no longer see it.', async () => {
        try {
            // Using standard fetch instead of apiRequest for maximum compatibility
            const res = await fetch('/PAWAdopt/api/pets.php?action=delete&id=' + petId);
            const data = await res.json();
            
            if (data.success) { 
                safeToast('Pet removed.'); 
                setTimeout(() => location.reload(), 900); 
            } else { 
                safeToast(data.message || 'Error removing pet.', 'error'); 
            }
        } catch (err) {
            safeToast("Network error or server failed to respond.", "error");
        }
    });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
