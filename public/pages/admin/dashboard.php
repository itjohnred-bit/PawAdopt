<?php
declare(strict_types=1);

$root = dirname($_SERVER['DOCUMENT_ROOT'] ?: __DIR__, 3);
require_once $root . '/includes/functions.php';

requireRole('ADMIN');
$pageTitle = 'Admin Dashboard';
$user = getCurrentUser();
$db   = Database::getInstance();

$stats = [
    'users'     => $db->fetch("SELECT COUNT(*) as c FROM users")['c'] ?? 0,
    'adopters'  => $db->fetch("SELECT COUNT(*) as c FROM users WHERE role='ADOPTER'")['c'] ?? 0,
    'shelters'  => $db->fetch("SELECT COUNT(*) as c FROM users WHERE role='SHELTER'")['c'] ?? 0,
    'pets'      => $db->fetch("SELECT COUNT(*) as c FROM pets WHERE status!='Removed'")['c'] ?? 0,
    'available' => $db->fetch("SELECT COUNT(*) as c FROM pets WHERE status='Available'")['c'] ?? 0,
    'apps'      => $db->fetch("SELECT COUNT(*) as c FROM adoption_applications")['c'] ?? 0,
    'pending_v' => $db->fetch("SELECT COUNT(*) as c FROM shelter_verifications WHERE status='PENDING'")['c'] ?? 0,
];

$recentUsers = $db->fetchAll("SELECT * FROM users ORDER BY created_at DESC LIMIT 6");
$pendingShelters = $db->fetchAll(
    "SELECT sv.*, sp.shelter_name, u.email
     FROM shelter_verifications sv
     JOIN shelter_profiles sp ON sv.shelter_id = sp.shelter_id
     JOIN users u ON sv.shelter_id = u.user_id
     WHERE sv.status = 'PENDING' ORDER BY sv.submitted_at ASC LIMIT 5"
);

require_once $root . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="icon">🛡️</span> Admin Dashboard</h1>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon teal"><span>👥</span></div>
        <div><div class="stat-value"><?= (int)$stats['users'] ?></div><div class="stat-label">Total Users</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><span>🐶</span></div>
        <div><div class="stat-value"><?= (int)$stats['adopters'] ?></div><div class="stat-label">Adopters</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><span>🏠</span></div>
        <div><div class="stat-value"><?= (int)$stats['shelters'] ?></div><div class="stat-label">Shelters</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><span>🐾</span></div>
        <div><div class="stat-value"><?= (int)$stats['pets'] ?></div><div class="stat-label">Pet Listings</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><span>⏳</span></div>
        <div><div class="stat-value"><?= (int)$stats['pending_v'] ?></div><div class="stat-label">Pending Verif.</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon teal"><span>📋</span></div>
        <div><div class="stat-value"><?= (int)$stats['apps'] ?></div><div class="stat-label">Applications</div></div>
    </div>
</div>

<div class="quick-actions mb-3">
    <a href="<?= APP_URL ?>/pages/admin/users.php"     class="quick-action-btn"><span class="qa-icon">👥</span><span class="qa-label">Users</span></a>
    <a href="<?= APP_URL ?>/pages/admin/shelters.php" class="quick-action-btn"><span class="qa-icon">🏠</span><span class="qa-label">Shelters</span></a>
    <a href="<?= APP_URL ?>/pages/admin/pets.php"      class="quick-action-btn"><span class="qa-icon">🐾</span><span class="qa-label">Pets</span></a>
    <a href="<?= APP_URL ?>/pages/admin/reports.php"  class="quick-action-btn"><span class="qa-icon">📊</span><span class="qa-label">Reports</span></a>
    <a href="<?= APP_URL ?>/pages/admin/audit-logs.php" class="quick-action-btn"><span class="qa-icon">📜</span><span class="qa-label">Audit Logs</span></a>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title" style="display:flex;justify-content:space-between">
            <span><i class="fas fa-shield-alt"></i> Pending Verifications</span>
            <a href="<?= APP_URL ?>/pages/admin/shelters.php" class="text-teal" style="font-size:.82rem;font-weight:700">View All</a>
        </div>
        <?php if (empty($pendingShelters)): ?>
        <div class="empty-state" style="padding:20px 0">
            <p>✅ No pending verifications!</p>
        </div>
        <?php else: ?>
        <?php foreach ($pendingShelters as $sv): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:var(--gray-bg);border-radius:10px;margin-bottom:8px">
            <div>
                <div class="fw-bold"><?= sanitize((string)$sv['shelter_name']) ?></div>
                <div style="font-size:.78rem;color:var(--gray-mid)"><?= sanitize((string)$sv['email']) ?></div>
            </div>
            <div style="display:flex;gap:6px">
                <button onclick="quickVerify(<?= (int)$sv['shelter_id'] ?>,'APPROVED')" class="btn btn-success btn-sm">✓</button>
                <button onclick="quickVerify(<?= (int)$sv['shelter_id'] ?>,'REJECTED')" class="btn btn-danger btn-sm">✕</button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title" style="display:flex;justify-content:space-between">
            <span><i class="fas fa-users"></i> Recent Users</span>
            <a href="<?= APP_URL ?>/pages/admin/users.php" class="text-teal" style="font-size:.82rem;font-weight:700">View All</a>
        </div>
        <?php foreach ($recentUsers as $u): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;border-bottom:1px solid var(--gray-light)">
            <div style="display:flex;align-items:center;gap:10px">
                <div class="avatar-circle" style="width:32px;height:32px;font-size:.85rem"><?= strtoupper(substr((string)$u['username'],0,1)) ?></div>
                <div>
                    <div style="font-weight:700;font-size:.88rem"><?= sanitize((string)$u['username']) ?></div>
                    <div style="font-size:.75rem;color:var(--gray-mid)"><?= sanitize((string)$u['email']) ?></div>
                </div>
            </div>
            <div style="display:flex;gap:6px;align-items:center">
                <span class="badge badge-<?= $u['role']==='ADOPTER'?'primary':($u['role']==='SHELTER'?'info':'danger') ?>" style="font-size:.7rem"><?= sanitize((string)$u['role']) ?></span>
                <?= $u['is_active'] ? '<span class="badge badge-success" style="font-size:.7rem">Active</span>' : '<span class="badge badge-danger" style="font-size:.7rem">Inactive</span>' ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
async function quickVerify(shelterId, status) {
    const note = status === 'REJECTED' ? (prompt('Rejection note (optional):') || '') : 'Verified by admin.';
    
    const res = await apiRequest('/api/admin.php', 'POST', new URLSearchParams({
        action: 'verify_shelter', 
        shelter_id: shelterId, 
        status: status, 
        note: note
    }));
    
    if (res.success) { 
        showToast('Shelter ' + status.toLowerCase() + '!'); 
        setTimeout(() => location.reload(), 900); 
    } else {
        showToast(res.message, 'error');
    }
}
</script>

<?php include $root . '/includes/footer.php'; ?>