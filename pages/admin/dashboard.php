<?php
require_once __DIR__ . '/../../includes/functions.php';
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
    'reports'   => $db->fetch("SELECT COUNT(*) as c FROM admin_reports WHERE status='PENDING'")['c'] ?? 0,
    'public_feedback' => $db->fetch("SELECT COUNT(*) as c FROM user_feedback WHERE is_public = 1 AND rating >= 4")['c'] ?? 0,
];

$recentUsers = $db->fetchAll("SELECT * FROM users ORDER BY created_at DESC LIMIT 6");
$pendingShelters = $db->fetchAll(
    "SELECT sv.*, sp.shelter_name, u.email
     FROM shelter_verifications sv
     JOIN shelter_profiles sp ON sv.shelter_id = sp.shelter_id
     JOIN users u ON sv.shelter_id = u.user_id
     WHERE sv.status = 'PENDING' ORDER BY sv.submitted_at ASC LIMIT 5"
);
$pendingReports = $db->fetchAll(
    "SELECT ar.*, u.username, u.role
     FROM admin_reports ar
     JOIN users u ON ar.user_id = u.user_id
     WHERE ar.status = 'PENDING'
     ORDER BY ar.created_at DESC
     LIMIT 6"
);
$featuredFeedback = $db->fetchAll(
    "SELECT uf.*, COALESCE(ap.full_name, u.username) AS author_name
     FROM user_feedback uf
     JOIN users u ON uf.user_id = u.user_id
     LEFT JOIN adopter_profiles ap ON ap.adopter_id = u.user_id
     WHERE uf.is_public = 1 AND uf.rating >= 4
     ORDER BY uf.created_at DESC
     LIMIT 4"
);

include __DIR__ . '/../../includes/header.php';
?>

<style>
.admin-hero{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;margin-bottom:18px}
.admin-hero-copy p{margin:6px 0 0;color:var(--gray-mid);font-size:.92rem}
.admin-summary{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:22px}
.admin-chip{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:#fff;border:1px solid #e5eeeb;color:#46615b;font-size:.76rem;font-weight:800;letter-spacing:.04em}
.admin-grid{display:grid;grid-template-columns:1.3fr .9fr;gap:20px;margin-top:20px}
.admin-stack{display:grid;gap:20px}
.admin-mini-list{display:grid;gap:10px}
.admin-list-item{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;padding:14px;background:var(--gray-bg);border-radius:12px}
.admin-list-copy{font-size:.84rem;color:var(--gray-mid);line-height:1.5}
.admin-pills{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
.admin-pill{display:inline-flex;align-items:center;padding:5px 10px;border-radius:999px;font-size:.7rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase}
.admin-pill.report{background:#fef2f2;color:#b42318}
.admin-pill.feedback{background:#ecfeff;color:#0f766e}
.admin-actions{display:flex;gap:8px;flex-wrap:wrap}
.admin-actions button{border:none;background:none;padding:0;font-weight:800;cursor:pointer}
.report-review-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border-radius:999px;background:#f8fafc;border:1px solid #e2e8f0;color:#334155;font-size:.8rem;font-weight:800;transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease}
.report-review-btn:hover{transform:translateY(-1px);border-color:#c4b5fd;box-shadow:0 10px 18px rgba(76,29,149,.10)}
.report-review-btn i{color:#7c3aed}
.feedback-card{padding:14px;border:1px solid #edf2f0;border-radius:14px;background:#fff}
.feedback-card h4{margin:0 0 6px;font-size:.92rem}
.feedback-card p{margin:0;color:#64748b;font-size:.85rem;line-height:1.55}
.section-note{font-size:.8rem;color:var(--gray-mid);font-weight:600}
.resolve-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(18,18,28,.62);backdrop-filter:blur(6px);z-index:9999}
.resolve-modal.open{display:flex}
.resolve-card{width:min(520px,100%);background:#1d1b2a;color:#f8f7ff;border-radius:26px;padding:24px 24px 22px;box-shadow:0 28px 70px rgba(15,23,42,.42);border:1px solid rgba(197,184,249,.16)}
.resolve-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:18px}
.resolve-kicker{display:inline-flex;align-items:center;gap:8px;padding:7px 11px;border-radius:999px;background:rgba(197,184,249,.12);color:#d7cdfd;font-size:.72rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
.resolve-card h3{margin:10px 0 6px;font-size:1.4rem;line-height:1.15;color:#fff}
.resolve-card p{margin:0;color:#c8c6d7;font-size:.92rem;line-height:1.6}
.resolve-close{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;background:rgba(255,255,255,.06);color:#ecebff}
.resolve-meta{display:flex;gap:8px;flex-wrap:wrap;margin:18px 0 14px}
.resolve-pill{display:inline-flex;align-items:center;padding:7px 11px;border-radius:999px;background:rgba(255,255,255,.08);color:#ecebff;font-size:.72rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase}
.resolve-copy{padding:14px 16px;border-radius:18px;background:rgba(255,255,255,.05);color:#f5f4ff;font-size:.9rem;line-height:1.65;margin-bottom:14px;max-height:180px;overflow:auto}
.resolve-label{display:block;margin:0 0 8px;color:#f8f7ff;font-size:.84rem;font-weight:800}
.resolve-textarea{width:100%;min-height:96px;border-radius:18px;border:1px solid rgba(197,184,249,.22);background:rgba(255,255,255,.06);color:#fff;padding:14px 15px;font:inherit;resize:vertical}
.resolve-textarea::placeholder{color:#bab7cd}
.resolve-textarea:focus{outline:none;border-color:#c5b8f9;box-shadow:0 0 0 3px rgba(197,184,249,.15)}
.resolve-actions{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:18px;flex-wrap:wrap}
.resolve-side{display:flex;gap:10px;flex-wrap:wrap;margin-left:auto}
.resolve-btn{min-width:98px;padding:10px 18px;border-radius:999px;font-size:.88rem;font-weight:800;border:1px solid transparent;cursor:pointer;transition:transform .15s ease,box-shadow .15s ease,opacity .15s ease}
.resolve-btn:hover{transform:translateY(-1px)}
.resolve-btn.cancel{background:transparent;border-color:#c5b8f9;color:#ecebff}
.resolve-btn.dismiss{background:rgba(190,24,93,.14);border-color:rgba(244,114,182,.22);color:#f9a8d4}
.resolve-btn.approve{background:#c5b8f9;color:#241f38;box-shadow:0 12px 22px rgba(197,184,249,.24)}
.resolve-btn:disabled{opacity:.55;cursor:not-allowed;box-shadow:none}
@media (max-width: 980px){.admin-grid{grid-template-columns:1fr}}
@media (max-width: 640px){.resolve-card{padding:20px 18px}.resolve-actions{flex-direction:column;align-items:stretch}.resolve-side{width:100%;margin-left:0}.resolve-side .resolve-btn,.resolve-actions .resolve-btn{width:100%}}
</style>

<div class="admin-hero">
    <div class="admin-hero-copy">
        <h1 class="page-title"><span class="icon">🛡️</span> Admin Dashboard</h1>
        <p>Cleaned up for action first: no duplicate navigation, just the items admins need to review.</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon teal"><span>👥</span></div>
        <div><div class="stat-value"><?= $stats['users'] ?></div><div class="stat-label">Total Users</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><span>🐾</span></div>
        <div><div class="stat-value"><?= $stats['pets'] ?></div><div class="stat-label">Pet Listings</div></div>
    </div>

    <div class="stat-card">
        <div class="stat-icon amber"><span>🚨</span></div>
        <div><div class="stat-value"><?= $stats['reports'] ?></div><div class="stat-label">Pending Reports</div></div>
    </div>
</div>

<div class="admin-summary">
    <span class="admin-chip"><i class="fas fa-user-friends"></i> Adopters: <?= (int) $stats['adopters'] ?></span>

    <span class="admin-chip"><i class="fas fa-file-lines"></i> Applications: <?= (int) $stats['apps'] ?></span>
    <span class="admin-chip"><i class="fas fa-heart"></i> Public Feedback: <?= (int) $stats['public_feedback'] ?></span>
</div>

<div class="admin-grid">
    <div class="admin-stack">
  

        <div class="card">
        <div class="card-title" style="display:flex;justify-content:space-between;align-items:center">
            <span><i class="fas fa-flag"></i> New User Reports</span>
            <span class="badge badge-danger" style="font-size:.72rem"><?= (int) $stats['reports'] ?> Pending</span>
        </div>
        <?php if (empty($pendingReports)): ?>
            <div class="empty-state" style="padding:20px 0"><p>No pending reports right now.</p></div>
        <?php else: ?>
            <div class="admin-mini-list">
            <?php foreach ($pendingReports as $report): ?>
                <div class="admin-list-item">
                    <div>
                        <div style="font-weight:800"><?= sanitize($report['subject']) ?></div>
                        <div class="admin-list-copy"><?= sanitize($report['details']) ?></div>
                        <div class="admin-pills">
                            <span class="admin-pill report"><?= sanitize($report['category']) ?></span>
                            <span class="admin-pill feedback"><?= sanitize($report['role']) ?></span>
                            <span class="admin-pill feedback"><?= sanitize($report['source_page']) ?></span>
                        </div>
                        <div style="font-size:.76rem;color:var(--gray-mid);margin-top:6px">Reported by <?= sanitize($report['username']) ?> · <?= !empty($report['created_at']) ? date('M j, Y g:i A', strtotime($report['created_at'])) : '' ?></div>
                    </div>
                    <div class="admin-actions">
                        <button
                            type="button"
                            class="report-review-btn"
                            data-report-id="<?= (int) $report['report_id'] ?>"
                            data-report-subject="<?= sanitize($report['subject']) ?>"
                            data-report-details="<?= sanitize($report['details']) ?>"
                            data-report-category="<?= sanitize($report['category']) ?>"
                            data-report-role="<?= sanitize($report['role']) ?>"
                            data-report-source="<?= sanitize($report['source_page']) ?>"
                            data-report-username="<?= sanitize($report['username']) ?>"
                            onclick="openResolveModal(this)">
                            <i class="fas fa-eye"></i> Review
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <div class="admin-stack">
        <div class="card">
            <div class="card-title" style="display:flex;justify-content:space-between;align-items:center">
                <span><i class="fas fa-users"></i> Recent Users</span>
                <a href="<?= APP_URL ?>/pages/admin/users.php" class="text-teal" style="font-size:.82rem;font-weight:700">View All</a>
            </div>
            <div class="section-note" style="margin-bottom:10px">Latest signups only — detailed user management stays in the sidebar page.</div>
            <?php foreach ($recentUsers as $u): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;border-bottom:1px solid var(--gray-light)">
                <div style="display:flex;align-items:center;gap:10px">
                    <div class="avatar-circle" style="width:32px;height:32px;font-size:.85rem"><?= strtoupper(substr($u['username'],0,1)) ?></div>
                    <div>
                        <div style="font-weight:700;font-size:.88rem"><?= sanitize($u['username']) ?></div>
                        <div style="font-size:.75rem;color:var(--gray-mid)"><?= sanitize($u['email']) ?></div>
                    </div>
                </div>
                <div style="display:flex;gap:6px;align-items:center">
                    <span class="badge badge-<?= $u['role']==='ADOPTER'?'primary':($u['role']==='SHELTER'?'info':'danger') ?>" style="font-size:.7rem"><?= $u['role'] ?></span>
                    <?= $u['is_active'] ? '<span class="badge badge-success" style="font-size:.7rem">Active</span>' : '<span class="badge badge-danger" style="font-size:.7rem">Inactive</span>' ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <div class="card-title" style="display:flex;justify-content:space-between;align-items:center">
                <span><i class="fas fa-heart"></i> Public Landing Feedback</span>
                <a href="<?= APP_URL ?>/#stories" class="text-teal" style="font-size:.82rem;font-weight:700">View Landing Tab</a>
            </div>
            <div class="section-note" style="margin-bottom:10px">Only public 4–5 star feedback appears here.</div>
            <?php if (empty($featuredFeedback)): ?>
                <div class="empty-state" style="padding:20px 0"><p>No public feedback yet.</p></div>
            <?php else: ?>
                <div class="admin-mini-list">
                    <?php foreach ($featuredFeedback as $item): ?>
                        <div class="feedback-card">
                            <h4><?= sanitize($item['title']) ?></h4>
                            <p><?= sanitize($item['message']) ?></p>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;font-size:.78rem;color:var(--gray-mid)">
                                <span><?= sanitize($item['author_name']) ?></span>
                                <span><?= str_repeat('★', max(0, min(5, (int) $item['rating']))) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="resolve-modal" id="resolveModal" aria-hidden="true">
    <div class="resolve-card">
        <div class="resolve-top">
            <div>
                <span class="resolve-kicker"><i class="fas fa-flag"></i> Report Review</span>
                <h3 id="resolveTitle">Review report</h3>
                <p>Resolve or dismiss the report with a proper admin note instead of a raw browser prompt.</p>
            </div>
            <button type="button" class="resolve-close" onclick="closeResolveModal()" aria-label="Close review modal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="resolve-meta">
            <span class="resolve-pill" id="resolveCategory">Category</span>
            <span class="resolve-pill" id="resolveRole">Role</span>
            <span class="resolve-pill" id="resolveSource">Source</span>
        </div>

        <div class="resolve-copy" id="resolveDetails">Report details</div>

        <label for="resolveNote" class="resolve-label">Admin note</label>
        <textarea id="resolveNote" class="resolve-textarea" placeholder="Add the reason for your decision..."></textarea>

        <div class="resolve-actions">
            <button type="button" class="resolve-btn cancel" onclick="closeResolveModal()">Cancel</button>
            <div class="resolve-side">
                <button type="button" class="resolve-btn dismiss" id="resolveDismissBtn">Dismiss</button>
                <button type="button" class="resolve-btn approve" id="resolveApproveBtn">Resolve</button>
            </div>
        </div>
    </div>
</div>

<script>
let activeReportId = null;

async function quickVerify(shelterId, status) {
    const note = status === 'REJECTED' ? (prompt('Rejection note (optional):') || '') : 'Verified by admin.';
    const res = await apiRequest('/PAWAdopt/api/admin.php', 'POST', new URLSearchParams({
        action:'verify_shelter', shelter_id:shelterId, status:status, note:note
    }));
    if (res.success) { showToast('Shelter '+status.toLowerCase()+'!'); setTimeout(()=>location.reload(),900); }
    else showToast(res.message,'error');
}

function openResolveModal(button) {
    const modal = document.getElementById('resolveModal');
    if (!button || !modal) return;
    activeReportId = button.getAttribute('data-report-id');
    document.getElementById('resolveTitle').textContent = button.getAttribute('data-report-subject') || 'Review report';
    document.getElementById('resolveDetails').textContent = button.getAttribute('data-report-details') || 'No details provided.';
    document.getElementById('resolveCategory').textContent = button.getAttribute('data-report-category') || 'General';
    document.getElementById('resolveRole').textContent = button.getAttribute('data-report-role') || 'User';
    document.getElementById('resolveSource').textContent = button.getAttribute('data-report-source') || 'Dashboard';
    document.getElementById('resolveNote').value = 'Reviewed by admin: ' + (button.getAttribute('data-report-username') || 'system') + '.';
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function closeResolveModal() {
    const modal = document.getElementById('resolveModal');
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    activeReportId = null;
}

async function resolveReport(status) {
    if (!activeReportId) return;
    const note = document.getElementById('resolveNote').value || '';
    const approveBtn = document.getElementById('resolveApproveBtn');
    const dismissBtn = document.getElementById('resolveDismissBtn');
    approveBtn.disabled = true;
    dismissBtn.disabled = true;
    const res = await apiRequest('<?= APP_URL ?>/api/feedback-report.php', 'POST', new URLSearchParams({
        action:'resolve_report', report_id:activeReportId, status:status, admin_note:note
    }));
    approveBtn.disabled = false;
    dismissBtn.disabled = false;
    if (res.success) {
        showToast(res.message || 'Report updated.');
        closeResolveModal();
        setTimeout(()=>location.reload(),900);
    } else {
        showToast(res.message || 'Unable to update report.','error');
    }
}

document.getElementById('resolveApproveBtn')?.addEventListener('click', () => resolveReport('RESOLVED'));
document.getElementById('resolveDismissBtn')?.addEventListener('click', () => resolveReport('DISMISSED'));
document.getElementById('resolveModal')?.addEventListener('click', (e) => {
    if (e.target.id === 'resolveModal') closeResolveModal();
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeResolveModal();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
