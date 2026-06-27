<?php
require_once __DIR__ . '/../../includes/functions.php';
requireRole('ADOPTER');
$pageTitle = 'My Applications';
$user = getCurrentUser();
$db   = Database::getInstance();

$apps = $db->fetchAll(
    "SELECT aa.*, p.name as pet_name, p.species, p.breed, sp.veterinary_name, sp.city as veterinary_city,
     (SELECT pp.photo_url FROM pet_photos pp WHERE pp.pet_id = p.pet_id AND pp.is_primary = 1 LIMIT 1) as pet_photo
     FROM adoption_applications aa
     JOIN pets p ON aa.pet_id = p.pet_id
     JOIN veterinary_profiles sp ON aa.veterinary_id = sp.veterinary_id
     WHERE aa.adopter_id = ?
     ORDER BY aa.submitted_at DESC",
    [$user['user_id']]
);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="icon">📋</span> My Applications</h1>
    <a href="<?= APP_URL ?>/pages/adopter/browse.php" class="btn btn-primary">
        <i class="fas fa-search"></i> Browse More Pets
    </a>
</div>

<?php if (empty($apps)): ?>
<div class="empty-state">
    <div class="empty-icon">📋</div>
    <h3>No applications yet!</h3>
    <p>Browse our adorable pets and submit your first adoption application.</p>
    <a href="<?= APP_URL ?>/pages/adopter/browse.php" class="btn btn-primary">Find a Pet 🐾</a>
</div>
<?php else: ?>

<!-- Status filter tabs -->
<div class="tabs mb-3">
    <button class="tab-btn active" data-tab="tab-all">All (<?= count($apps) ?>)</button>
    <?php
    $statusCounts = array_count_values(array_column($apps, 'status'));
    foreach ($statusCounts as $s => $c):
    ?>
    <button class="tab-btn" data-tab="tab-<?= strtolower(str_replace(' ','-',$s)) ?>"><?= $s ?> (<?= $c ?>)</button>
    <?php endforeach; ?>
</div>

<div class="tab-panel active" id="tab-all">
<div style="display:flex;flex-direction:column;gap:16px">
<?php foreach ($apps as $app):
    $photo = $app['pet_photo'] ? APP_URL.'/'.$app['pet_photo'] : APP_URL.'/assets/images/pet-placeholder.png';
?>
<div class="card" id="app-<?= $app['application_id'] ?>">
    <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">
        <img src="<?= $photo ?>" alt="<?= sanitize($app['pet_name']) ?>"
             style="width:90px;height:90px;border-radius:12px;object-fit:cover;flex-shrink:0"
             onerror="this.src='<?= APP_URL ?>/assets/images/pet-placeholder.png'">
        <div style="flex:1;min-width:200px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px">
                <div>
                    <div style="font-size:1.1rem;font-weight:800"><?= sanitize($app['pet_name']) ?></div>
                    <div class="text-muted" style="font-size:.84rem"><?= sanitize($app['species']) ?><?= $app['breed'] ? ' · '.sanitize($app['breed']) : '' ?></div>
                    <div class="text-muted" style="font-size:.82rem;margin-top:2px"><i class="fas fa-building"></i> <?= sanitize($app['veterinary_name']) ?><?= $app['veterinary_city'] ? ', '.sanitize($app['veterinary_city']) : '' ?></div>
                </div>
                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
                    <?= statusBadge($app['status']) ?>
                    <span class="text-muted" style="font-size:.78rem">
                        Applied: <?= (date('Y', strtotime($app['submitted_at'])) > 9999) ? date('M j, Y') : date('M j, Y', strtotime($app['submitted_at'])) ?>
                    </span>
                </div>
            </div>

            <?php if ($app['message_to_veterinary']): ?>
            <div style="margin-top:10px;background:var(--gray-bg);border-radius:10px;padding:10px;font-size:.85rem">
                <strong>Your message:</strong> <?= sanitize($app['message_to_veterinary']) ?>
            </div>
            <?php endif; ?>

            <?php if ($app['decision_note'] && in_array($app['status'], ['Approved','Rejected'])): ?>
            <div style="margin-top:8px;background:<?= $app['status']==='Approved'?'#ecfdf5':'#fef2f2' ?>;border-radius:10px;padding:10px;font-size:.85rem;color:<?= $app['status']==='Approved'?'#065f46':'#991b1b' ?>">
                <strong>veterinary note:</strong> <?= sanitize($app['decision_note']) ?>
            </div>
            <?php endif; ?>

            <?php if (in_array($app['status'], ['Submitted','Under Review'])): ?>
            <div style="margin-top:10px;display:flex;gap:8px">
                <a href="<?= APP_URL ?>/pages/adopter/pet-detail.php?id=<?= $app['pet_id'] ?>" class="btn btn-outline btn-sm">View Pet</a>
                <button onclick="cancelApp(<?= $app['application_id'] ?>)" class="btn btn-danger btn-sm">Cancel</button>
            </div>
            <?php else: ?>
            <div style="margin-top:10px">
                <a href="<?= APP_URL ?>/pages/adopter/pet-detail.php?id=<?= $app['pet_id'] ?>" class="btn btn-outline btn-sm">View Pet</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>

<script>
if (typeof window.confirmAction !== 'function') {
    window.confirmAction = function (message, onYes) {
        if (window.confirm(message)) {
            try { onYes && onYes(); } catch (e) { console.error(e); }
        }
    };
}

async function cancelApp(appId) {
    confirmAction('Are you sure you want to cancel this application?', async () => {
        try {
            const res = await apiRequest(
                '/PAWAdopt/api/applications.php', 'POST',
                new URLSearchParams({ action: 'cancel', application_id: appId })
            );
            if (res.success) {
                showToast('Application cancelled.');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(res.message || 'Could not cancel.', 'error');
            }
        } catch (e) {
            showToast('Network error.', 'error');
        }
    });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
