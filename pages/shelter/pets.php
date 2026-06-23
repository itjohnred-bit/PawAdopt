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
     (SELECT pp.mime      FROM pet_photos pp WHERE pp.pet_id = p.pet_id AND pp.is_primary = 1 LIMIT 1) as primary_mime,
     (SELECT COUNT(*) FROM adoption_applications aa WHERE aa.pet_id = p.pet_id) as app_count
     FROM pets p WHERE $whereStr ORDER BY p.created_at DESC",
    $params
);


function petImageUrl(array $pet): string {
    $placeholder = APP_URL . '/assets/images/pet-placeholder.png';

    $candidate = trim((string)($pet['primary_photo'] ?? ''));
    if ($candidate === '') {
        return $placeholder;
    }

    if (preg_match('#^(https?:|/)#i', $candidate)) {
        if ($candidate[0] === '/' && defined('APP_URL')) {
            return rtrim(APP_URL, '/') . $candidate;
        }
        return $candidate;
    }

    if (strlen($candidate) > 256 && preg_match('#^[A-Za-z0-9+/=\s]+$#', $candidate)) {
        $mime = (string)($pet['primary_mime'] ?? 'image/jpeg');
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            $mime = 'image/jpeg';
        }
        return 'data:' . $mime . ';base64,' . $candidate;
    }

    if (defined('APP_URL')) {
        return rtrim(APP_URL, '/') . '/uploads/pets/' . basename($candidate);
    }

    return $placeholder;
}

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
            $photoUrl = petImageUrl($pet);
        ?>
        <tr>
            <td><img
                src="<?= htmlspecialchars($photoUrl, ENT_QUOTES) ?>"
                class="pet-thumb" alt="<?= htmlspecialchars($pet['name'], ENT_QUOTES) ?>"
                onerror="this.onerror=null;this.src='<?= htmlspecialchars(APP_URL . '/assets/images/pet-placeholder.png', ENT_QUOTES) ?>'"></td>
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
                    <button onclick="deletePet(<?= (int)$pet['pet_id'] ?>)" class="btn btn-danger btn-sm">Remove</button>
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
            const endpoint = (typeof APP_URL !== 'undefined' && APP_URL
                ? APP_URL.replace(/\/+$/,'') + '/api/pets.php?action=delete&id=' + petId
                : '/api/pets.php?action=delete&id=' + petId);

            const res = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            const data = await res.json().catch(() => ({ success:false, message:'Bad response' }));

            if (data.success) {
                safeToast('Pet removed.');
                setTimeout(() => location.reload(), 900);
            } else {
                safeToast(data.message || 'Error removing pet.', 'error');
            }
        } catch (err) {
            safeToast('Network error or server failed to respond.', 'error');
        }
    });
}

</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
