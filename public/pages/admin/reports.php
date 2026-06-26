<?php
require_once __DIR__ . '/../../includes/functions.php';
requireRole('ADMIN');
$pageTitle = 'Reports & Analytics';
$user = getCurrentUser();
$db   = Database::getInstance();

// Stats
$stats = [
    'total_users'    => $db->fetch("SELECT COUNT(*) as c FROM users")['c'] ?? 0,
    'total_adopters' => $db->fetch("SELECT COUNT(*) as c FROM users WHERE role='ADOPTER'")['c'] ?? 0,
    'total_shelters' => $db->fetch("SELECT COUNT(*) as c FROM users WHERE role='SHELTER'")['c'] ?? 0,
    'verified_sh'    => $db->fetch("SELECT COUNT(*) as c FROM shelter_profiles WHERE is_verified=1")['c'] ?? 0,
    'total_pets'     => $db->fetch("SELECT COUNT(*) as c FROM pets WHERE status!='Removed'")['c'] ?? 0,
    'available_pets' => $db->fetch("SELECT COUNT(*) as c FROM pets WHERE status='Available'")['c'] ?? 0,
    'pending_pets'   => $db->fetch("SELECT COUNT(*) as c FROM pets WHERE status='Pending'")['c'] ?? 0,
    'adopted_pets'   => $db->fetch("SELECT COUNT(*) as c FROM pets WHERE status='Adopted'")['c'] ?? 0,
    'total_apps'     => $db->fetch("SELECT COUNT(*) as c FROM adoption_applications")['c'] ?? 0,
    'approved_apps'  => $db->fetch("SELECT COUNT(*) as c FROM adoption_applications WHERE status='Approved'")['c'] ?? 0,
    'rejected_apps'  => $db->fetch("SELECT COUNT(*) as c FROM adoption_applications WHERE status='Rejected'")['c'] ?? 0,
    'total_msgs'     => $db->fetch("SELECT COUNT(*) as c FROM messages")['c'] ?? 0,
];

$monthlyRegs = $db->fetchAll(
    "SELECT DATE_FORMAT(created_at,'%b') as month, COUNT(*) as count
     FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY MIN(created_at)"
);

$monthlyAdoptions = $db->fetchAll(
    "SELECT DATE_FORMAT(reviewed_at,'%b') as month, COUNT(*) as count
     FROM adoption_applications WHERE status='Approved' AND reviewed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY YEAR(reviewed_at), MONTH(reviewed_at) ORDER BY MIN(reviewed_at)"
);

$topSpecies = $db->fetchAll(
    "SELECT species, COUNT(*) as count FROM pets WHERE status!='Removed' GROUP BY species ORDER BY count DESC"
);

// Content management
$aboutContent = $db->fetch("SELECT content_value FROM site_content WHERE content_key='about_text'");
$termsContent = $db->fetch("SELECT content_value FROM site_content WHERE content_key='terms_text'");

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="icon">📊</span> Reports &amp; Analytics</h1>
</div>

<!-- Overview Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
    <div class="stat-card"><div class="stat-icon teal"><span>👥</span></div><div><div class="stat-value"><?= $stats['total_users'] ?></div><div class="stat-label">Total Users</div></div></div>
    <div class="stat-card"><div class="stat-icon blue"><span>🐶</span></div><div><div class="stat-value"><?= $stats['total_adopters'] ?></div><div class="stat-label">Adopters</div></div></div>
    <div class="stat-card"><div class="stat-icon amber"><span>🐾</span></div><div><div class="stat-value"><?= $stats['total_pets'] ?></div><div class="stat-label">Total Pets</div></div></div>
    <div class="stat-card"><div class="stat-icon green"><span>✅</span></div><div><div class="stat-value"><?= $stats['available_pets'] ?></div><div class="stat-label">Available</div></div></div>
    <div class="stat-card"><div class="stat-icon teal"><span>🎉</span></div><div><div class="stat-value"><?= $stats['adopted_pets'] ?></div><div class="stat-label">Adopted</div></div></div>
    <div class="stat-card"><div class="stat-icon blue"><span>📋</span></div><div><div class="stat-value"><?= $stats['total_apps'] ?></div><div class="stat-label">Applications</div></div></div>
</div>

<div class="grid-2 mt-3">
    <!-- Monthly Registrations Chart -->
    <div class="card">
        <div class="card-title"><i class="fas fa-chart-bar"></i> User Registrations (Last 6 Months)</div>
        <canvas id="regChart" height="200"></canvas>
    </div>
    <!-- Species Distribution -->
    <div class="card">
        <div class="card-title"><i class="fas fa-chart-pie"></i> Pets by Species</div>
        <canvas id="speciesChart" height="200"></canvas>
    </div>
</div>

<!-- Top Species Table -->
<div class="card mt-3">
    <div class="card-title"><i class="fas fa-paw"></i> Pet Species Breakdown</div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Species</th><th>Count</th><th>% of Total</th></tr></thead>
            <tbody>
            <?php foreach ($topSpecies as $sp): ?>
            <tr>
                <td><?= htmlspecialchars($sp['species']) ?></td>
                <td class="fw-bold"><?= $sp['count'] ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px">
                        <div style="flex:1;background:var(--gray-light);border-radius:99px;height:8px">
                            <div style="width:<?= $stats['total_pets'] > 0 ? round($sp['count']/$stats['total_pets']*100) : 0 ?>%;background:var(--teal);height:8px;border-radius:99px"></div>
                        </div>
                        <?= $stats['total_pets'] > 0 ? round($sp['count']/$stats['total_pets']*100) : 0 ?>%
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Content Management -->
<div class="card mt-3">
    <div class="card-title"><i class="fas fa-edit"></i> Site Content Management</div>
    <div class="grid-2" style="gap:20px">
        <div>
            <label class="form-label">About Text</label>
            <textarea id="aboutText" class="form-control" rows="5"><?= htmlspecialchars($aboutContent['content_value'] ?? '') ?></textarea>
            <button onclick="saveContent('about_text', document.getElementById('aboutText').value)" class="btn btn-primary btn-sm mt-1">Save About</button>
        </div>
        <div>
            <label class="form-label">Terms &amp; Conditions</label>
            <textarea id="termsText" class="form-control" rows="5"><?= htmlspecialchars($termsContent['content_value'] ?? '') ?></textarea>
            <button onclick="saveContent('terms_text', document.getElementById('termsText').value)" class="btn btn-primary btn-sm mt-1">Save Terms</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
// Registration Chart
const regLabels = <?= json_encode(array_column($monthlyRegs,'month')) ?>;
const regData   = <?= json_encode(array_column($monthlyRegs,'count')) ?>;
renderBarChart('regChart', regLabels, regData, 'Registrations');

// Species Pie Chart
const speciesLabels = <?= json_encode(array_column($topSpecies,'species')) ?>;
const speciesData   = <?= json_encode(array_column($topSpecies,'count')) ?>;
const colors = ['#0d9488','#0891b2','#059669','#d97706','#dc2626','#7c3aed','#be185d'];
const ctx2 = document.getElementById('speciesChart');
if (ctx2 && window.Chart) {
    new Chart(ctx2, {
        type:'doughnut',
        data:{ labels:speciesLabels, datasets:[{ data:speciesData, backgroundColor:colors, borderWidth:2 }] },
        options:{ responsive:true, plugins:{ legend:{ position:'bottom' } } }
    });
}

async function saveContent(key, value) {
    const res = await apiRequest('/PAWAdopt/api/admin.php','POST',new URLSearchParams({action:'update_content',key:key,value:value}));
    showToast(res.success ? 'Content saved!' : res.message, res.success?'success':'error');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
