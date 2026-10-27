<?php
require_once __DIR__ . '/../../includes/functions.php';
requireRole('SHELTER');
$pageTitle = 'Shelter Dashboard';
$user = getCurrentUser();
$db   = Database::getInstance();

$profile = $db->fetch("SELECT * FROM shelter_profiles WHERE shelter_id = ?", [$user['user_id']]);
$verif   = $db->fetch("SELECT * FROM shelter_verifications WHERE shelter_id = ?", [$user['user_id']]);

$petCount  = $db->fetch("SELECT COUNT(*) as c FROM pets WHERE shelter_id = ? AND status != 'Removed'", [$user['user_id']])['c'] ?? 0;
$availPets = $db->fetch("SELECT COUNT(*) as c FROM pets WHERE shelter_id = ? AND status = 'Available'", [$user['user_id']])['c'] ?? 0;

$appStats = $db->fetch("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN aa.status = 'Submitted' THEN 1 END) as pending
    FROM adoption_applications aa
    JOIN pets p ON aa.pet_id = p.pet_id
    WHERE p.shelter_id = ?", 
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
    WHERE p.shelter_id = ?
    ORDER BY aa.submitted_at DESC LIMIT 5",
    [$user['user_id']]
);

include __DIR__ . '/../../includes/header.php';
?>

<style>
.shelter-actions{display:flex;gap:12px;flex-wrap:wrap;margin:0 0 22px}
.shelter-action{display:inline-flex;align-items:center;gap:10px;padding:12px 18px;border-radius:999px;font-weight:800;text-decoration:none;border:1px solid #dbe7e4;background:#fff;color:#0f766e;box-shadow:0 8px 18px rgba(15,118,110,.08);transition:transform .15s ease,box-shadow .15s ease,background .15s ease}
.shelter-action:hover{transform:translateY(-2px);box-shadow:0 12px 22px rgba(15,118,110,.14);background:#f0fdfa}
.shelter-action.report{background:#fff7f7;color:#b42318;border-color:#fecaca}
.shelter-action.report:hover{background:#fef2f2}
.dashboard-tip{margin-bottom:24px;padding:16px 18px;border-radius:16px;background:#f8fafc;border:1px solid #e2e8f0;color:#475569;line-height:1.6}
.dash-modal{position:fixed;inset:0;background:rgba(15,23,42,.55);display:none;align-items:center;justify-content:center;padding:18px;z-index:9999}
.dash-modal.open{display:flex}.dash-modal-card{background:#fff;border-radius:22px;width:min(560px,100%);padding:26px;box-shadow:0 25px 60px rgba(2,8,23,.28)}
.dash-modal-card h2{margin:0 0 10px;font-size:1.35rem;color:#134e4a}.dash-modal-card p{margin:0 0 16px;color:#64748b;font-size:.92rem;line-height:1.6}
.dash-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.dash-grid .full{grid-column:1/-1}
.dash-input,.dash-textarea,.dash-select{width:100%;border:1px solid #d6e3df;border-radius:14px;padding:12px 14px;font:inherit;background:#fcfefe}
.dash-textarea{min-height:120px;resize:vertical}.dash-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px;flex-wrap:wrap}
.dash-btn{border:none;border-radius:999px;padding:11px 18px;font-weight:800;cursor:pointer}.dash-btn.primary{background:#0f766e;color:#fff}.dash-btn.secondary{background:#f1f5f9;color:#334155}.dash-btn.danger{background:#b42318;color:#fff}
@media (max-width: 900px){.dash-grid{grid-template-columns:1fr}}
</style>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
    <h1 class="page-title" style="font-weight:900;margin:0">🏠 <?= sanitize($profile['shelter_name'] ?? 'My Shelter') ?></h1>
    <a href="<?= APP_URL ?>/pages/shelter/add-pet.php" class="btn btn-primary" style="background:var(--active-text);color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:700">
        <i class="fas fa-plus"></i> Add New Pet
    </a>
</div>

<div class="shelter-actions">
    <button type="button" class="shelter-action report" id="openReportBtn"><i class="fas fa-flag"></i> Report to Admin</button>
</div>

<div class="dashboard-tip">
    Use <strong>one report button only</strong> for account concerns, adopter issues, animal welfare concerns, or technical problems. This keeps the shelter dashboard clean and avoids duplicate actions.
</div>

<?php if (isset($profile['is_verified']) && !$profile['is_verified']): ?>
<div style="background:#fffbeb;border:2px solid #f59e0b;border-radius:14px;padding:16px;margin-bottom:24px;display:flex;gap:12px;align-items:center">
    <span style="font-size:1.8rem">⚠️</span>
    <div>
        <div style="font-weight:800;color:#92400e">Verification Pending</div>
        <div style="color:#78350f;font-size:.88rem">Admin is reviewing your shelter. 
            Status: <strong><?= $verif ? sanitize($verif['status']) : 'Not submitted' ?></strong>
        </div>
    </div>
</div>
<?php endif; ?>

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
                    <td><a href="<?= APP_URL ?>/pages/shelter/applications.php?id=<?= $app['application_id'] ?>" style="color:var(--active-text);text-decoration:none;font-weight:700">Review</a></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="dash-modal" id="reportModal" aria-hidden="true">
    <div class="dash-modal-card">
        <h2>Report a Concern to Admin</h2>
        <p>Send a direct report for platform abuse, suspicious adopters, verification issues, animal welfare risks, or technical problems.</p>
        <form id="reportForm">
            <input type="hidden" name="source_page" value="shelter_dashboard">
            <div class="dash-grid">
                <div>
                    <label for="reportCategory" style="font-weight:800;font-size:.86rem">Category</label>
                    <select id="reportCategory" name="category" class="dash-select" required>
                        <option value="">Select category</option>
                        <option value="adopter_issue">Adopter Issue</option>
                        <option value="animal_welfare">Animal Welfare</option>
                        <option value="listing_issue">Listing Issue</option>
                        <option value="technical_issue">Technical Issue</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label for="reportSubject" style="font-weight:800;font-size:.86rem">Subject</label>
                    <input id="reportSubject" name="subject" class="dash-input" maxlength="150" placeholder="Short concern title" required>
                </div>
                <div class="full">
                    <label for="reportMessage" style="font-weight:800;font-size:.86rem">Details</label>
                    <textarea id="reportMessage" name="details" class="dash-textarea" maxlength="1000" placeholder="Explain the issue clearly for admin review." required></textarea>
                </div>
            </div>
            <div class="dash-actions">
                <button type="button" class="dash-btn secondary" data-close-modal="reportModal">Cancel</button>
                <button type="submit" class="dash-btn danger">Send Report</button>
            </div>
        </form>
    </div>
</div>

<script>
(function(){
    const reportModal = document.getElementById('reportModal');
    const reportForm = document.getElementById('reportForm');
    const endpoint = '<?= APP_URL ?>/api/feedback-report.php';

    function openModal(modal){ if (!modal) return; modal.classList.add('open'); modal.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; }
    function closeModal(modal){ if (!modal) return; modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); document.body.style.overflow=''; }
    function notify(msg, type){ if (typeof showToast === 'function') showToast(msg, type || 'success'); else alert(msg); }

    document.getElementById('openReportBtn')?.addEventListener('click', () => openModal(reportModal));
    document.querySelectorAll('[data-close-modal]').forEach(btn => btn.addEventListener('click', () => closeModal(document.getElementById(btn.getAttribute('data-close-modal')))));
    reportModal?.addEventListener('click', (e) => { if (e.target === reportModal) closeModal(reportModal); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(reportModal); });

    reportForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(reportForm);
        fd.set('action', 'submit_report');
        const submitBtn = reportForm.querySelector('[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';
        try {
            const res = await fetch(endpoint, { method:'POST', body:fd });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Report submission failed.');
            notify(data.message || 'Report sent to admins.');
            reportForm.reset();
            closeModal(reportModal);
        } catch (err) {
            notify(err.message || 'Report submission failed.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send Report';
        }
    });
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
