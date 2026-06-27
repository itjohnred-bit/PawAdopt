<?php
require_once __DIR__ . '/../../includes/functions.php';
requireRole('VETERINARY');
$pageTitle = 'Applications';
$user = getCurrentUser();
$db   = Database::getInstance();

$petFilter    = (int)($_GET['pet'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$focusApp     = (int)($_GET['app'] ?? 0);

$where  = ['p.veterinary_id = ?'];
$params = [$user['user_id']];

if ($petFilter)    { $where[] = 'aa.pet_id = ?';  $params[] = $petFilter; }
if ($statusFilter) { $where[] = 'aa.status = ?'; $params[] = $statusFilter; }

$apps = $db->fetchAll(
    "SELECT aa.*, p.name as pet_name, p.species,
     u.username as adopter_username, u.email as adopter_email,
     ap.full_name as adopter_name, ap.phone as adopter_phone, 
     ap.city as adopter_city, ap.bio as adopter_bio,
     (SELECT pp.photo_url FROM pet_photos pp 
      WHERE pp.pet_id = p.pet_id AND pp.is_primary = 1 LIMIT 1) as pet_photo
     FROM adoption_applications aa
     JOIN pets p ON aa.pet_id = p.pet_id
     JOIN users u ON aa.adopter_id = u.user_id
     LEFT JOIN adopter_profiles ap ON aa.adopter_id = ap.adopter_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY aa.submitted_at DESC",
    $params
);

$myPets = $db->fetchAll(
    "SELECT pet_id, name FROM pets 
     WHERE veterinary_id = ? AND status != 'Removed' 
     ORDER BY name", 
    [$user['user_id']]
);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="icon">📋</span> Adoption Applications</h1>
    <span class="text-muted"><?= count($apps) ?> application<?= count($apps) !== 1?'s':'' ?></span>
</div>

<!-- Filters -->
<div class="filter-bar">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <select name="pet" class="form-control" onchange="this.form.submit()">
            <option value="">All Pets</option>
            <?php foreach ($myPets as $p): ?>
            <option value="<?= $p['pet_id'] ?>" <?= $petFilter == $p['pet_id']?'selected':'' ?>><?= sanitize($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="form-control" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <?php foreach (['Submitted','Under Review','Approved','Rejected','Cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $statusFilter === $s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($petFilter || $statusFilter): ?>
        <a href="?" class="btn btn-secondary btn-sm">Clear</a>
        <?php endif; ?>
    </form>
</div>

<?php if (empty($apps)): ?>
<div class="empty-state">
    <div class="empty-icon">📋</div>
    <h3>No applications found</h3>
    <p>Applications will appear here when adopters apply for your pets.</p>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:16px">
<?php foreach ($apps as $app):
    $photo = $app['pet_photo'] ? APP_URL.'/'.$app['pet_photo'] : APP_URL.'/assets/images/pet-placeholder.png';
    $highlighted = ($focusApp == $app['application_id']);
?>
<div class="card <?= $highlighted ? 'highlight-card' : '' ?>" 
     id="app-<?= $app['application_id'] ?>" 
     style="<?= $highlighted?'border:2px solid var(--teal)':'' ?>">
    <div style="display:flex;gap:16px;flex-wrap:wrap">
        <img src="<?= $photo ?>" alt="" 
             style="width:80px;height:80px;border-radius:12px;object-fit:cover;flex-shrink:0" 
             onerror="this.src='<?= APP_URL ?>/assets/images/pet-placeholder.png'">
        <div style="flex:1;min-width:220px">
            <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px">
                <div>
                    <span class="fw-bold" style="font-size:1.05rem"><?= sanitize($app['adopter_name'] ?: $app['adopter_username']) ?></span>
                    <span class="text-muted"> wants to adopt </span>
                    <span class="fw-bold text-teal"><?= sanitize($app['pet_name']) ?></span>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <span class="badge"><?= sanitize($app['status']) ?></span>
                    <span class="text-muted" style="font-size:.78rem">
                        <?= date('M j, Y', strtotime($app['submitted_at'])) ?>
                    </span>
                </div>
            </div>

            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px;font-size:.82rem;color:#888">
                <span><i class="fas fa-envelope"></i> <?= sanitize($app['adopter_email']) ?></span>
                <?php if ($app['adopter_phone']): ?>
                <span><i class="fas fa-phone"></i> <?= sanitize($app['adopter_phone']) ?></span>
                <?php endif; ?>
                <?php if ($app['adopter_city']): ?>
                <span><i class="fas fa-map-marker-alt"></i> <?= sanitize($app['adopter_city']) ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($app['message_to_veterinary'])): ?>
            <div style="margin-top:10px;background:#f8f9fa;border-radius:10px;padding:12px;font-size:.88rem">
                <strong>Adopter's message:</strong><br>
                <?= sanitize($app['message_to_veterinary']) ?>
            </div>
            <?php endif; ?>

            <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <button onclick="viewApplication(<?= $app['application_id'] ?>)" 
                        class="btn btn-info btn-sm"
                        style="background:#0ea5e9;color:#fff;border:none;">
                    <i class="fas fa-file-alt"></i> View Application
                </button>

                <?php if (!in_array($app['status'], ['Approved','Rejected','Cancelled'])): ?>
                <!-- "Under Review" is STATUS info, not a button. -->
                <?php if ($app['status'] === 'Submitted'): ?>
                <a href="#" onclick="event.preventDefault(); reviewApp(<?= $app['application_id'] ?>,'Under Review','');"
                   style="font-size:.82rem;color:#92400e;text-decoration:underline;align-self:center;">
                    🔍 Mark as Under&nbsp;Review
                </a>
                <?php else: ?>
                <span style="font-size:.82rem;color:#666;align-self:center;">
                    🔍 Under Review
                </span>
                <?php endif; ?>
                <button onclick="showReviewModal(<?= $app['application_id'] ?>,'Approved')" 
                        class="btn btn-success btn-sm">
                    ✅ Approve
                </button>
                <button onclick="showReviewModal(<?= $app['application_id'] ?>,'Rejected')" 
                        class="btn btn-danger btn-sm">
                    ❌ Reject
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Review Modal (Approve / Reject) -->
<div class="modal-overlay" id="reviewModal" 
     style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:2000;">
    <div class="modal" 
         style="background:#fff; width:400px; max-width:94%; margin: 100px auto; padding:20px; border-radius:15px;">
        <div class="modal-header">
            <h3 id="reviewModalTitle">Review Application</h3>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Decision Note (optional)</label>
                <textarea id="decisionNote" class="form-control" rows="3" style="width:100%"></textarea>
            </div>
        </div>
        <div class="modal-footer" 
             style="display:flex; justify-content:flex-end; gap:10px; margin-top:15px;">
            <button class="btn btn-secondary" onclick="closeModalEl('reviewModal')">Cancel</button>
            <button class="btn btn-primary" id="confirmReviewBtn" onclick="submitReview()">Confirm</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="appDetailModal" 
     style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:2100; overflow-y:auto;">
    <div class="modal" 
         style="background:#fff; width:680px; max-width:94%; margin: 60px auto; padding:24px; 
                border-radius:18px; max-height:85vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.3);">
        <div class="modal-header" 
             style="display:flex;justify-content:space-between;align-items:center;
                    border-bottom:1px solid #eee;padding-bottom:12px;margin-bottom:16px;">
            <h3 style="margin:0;color:#0f766e;" id="appDetailTitle">
                <i class="fas fa-file-alt"></i> Application Details
            </h3>
            <button onclick="closeModalEl('appDetailModal')" 
                    style="background:none;border:none;font-size:26px;cursor:pointer;color:#888;line-height:1;">&times;</button>
        </div>
        <div class="modal-body" id="appDetailBody">
            <div style="text-align:center;padding:30px;color:#888;">
                <i class="fas fa-spinner fa-spin"></i> Loading…
            </div>
        </div>
    </div>
</div>

<script>

let reviewAppId   = null;
let reviewStatus  = null;


if (typeof window.confirmAction !== 'function') {
    window.confirmAction = function (message, onYes) {
        if (window.confirm(message)) {
            try { onYes && onYes(); } catch (e) { console.error(e); }
        }
    };
}

function closeModalEl(id) { 
    const el = document.getElementById(id); 
    if (el) el.style.display = 'none'; 
}
function openModalEl(id)  { document.getElementById(id).style.display = 'block'; }

function showReviewModal(appId, status) {
    reviewAppId  = appId;
    reviewStatus = status;
    document.getElementById('reviewModalTitle').textContent =
        (status === 'Approved' ? '✅ Approve' : '❌ Reject') + ' Application';
    document.getElementById('reviewModal').style.display = 'block';
}

async function submitReview() {
    const note  = document.getElementById('decisionNote').value;
    const appId = reviewAppId;
    const st    = reviewStatus;
    closeModalEl('reviewModal');
    await reviewApp(appId, st, note);
}

async function reviewApp(appId, status, note) {
    const formData = new FormData();
    formData.append('action', 'review');
    formData.append('application_id', appId);
    formData.append('status', status);
    formData.append('decision_note', note ?? '');

    try {
        const res = await fetch('/PAWAdopt/api/applications.php', {
            method: 'POST',
            body: formData
        }).then(r => r.json());

        if (res.success) {
            showToast('Application ' + status + ' ✓');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast(res.message || 'Failed to update application.', 'error');
        }
    } catch (e) {
        showToast('Network error: ' + (e.message || e), 'error');
    }
}


function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

async function viewApplication(appId) {
    const modal = document.getElementById('appDetailModal');
    const body  = document.getElementById('appDetailBody');
    document.getElementById('appDetailTitle').innerHTML =
        '<i class="fas fa-file-alt"></i> Application Details';
    body.innerHTML =
        '<div style="text-align:center;padding:30px;color:#888;">' +
        '<i class="fas fa-spinner fa-spin"></i> Loading…</div>';
    modal.style.display = 'block';

    try {
        const res = await fetch(
            '/PAWAdopt/api/applications.php?action=get_detail&id=' + appId
        ).then(r => r.json());

        if (!res.success) {
            body.innerHTML =
                '<div style="padding:20px;color:#b91c1c;">' +
                escapeHtml(res.message || 'Failed to load application.') + '</div>';
            return;
        }

        try {
            if (res.data && res.data.status === 'Submitted') {
                reviewApp(appId, 'Under Review', ''); 
            }
        } catch (_) {}

        const a = res.data;

        let screening = {};
        try {
            screening = a.screening_responses
                ? JSON.parse(a.screening_responses)
                : {};
        } catch (e) {
            screening = {};
        }

        const skipKeys = ['action','pet_id'];
        const screeningHtml = Object.keys(screening)
            .filter(k => !skipKeys.includes(k)
                && screening[k] !== '' && screening[k] != null)
            .map(k => {
                const label = k.replace(/_/g, ' ')
                                .replace(/\b\w/g, c => c.toUpperCase());
                return `
                    <div style="display:flex;border-bottom:1px dashed #eee;
                                padding:8px 0;gap:12px;">
                        <div style="flex:0 0 40%;color:#666;font-size:.85rem;
                                    font-weight:600;">
                            ${escapeHtml(label)}
                        </div>
                        <div style="flex:1;color:#111;font-size:.9rem;
                                    word-break:break-word;">
                            ${escapeHtml(String(screening[k]))}
                        </div>
                    </div>`;
            }).join('');

        const screeningSection = screeningHtml
            ? `<div style="margin-top:18px;">
                 <h4 style="color:#0f766e;margin:0 0 10px 0;font-size:1rem;">
                    📝 Filled-Up Screening Form
                 </h4>
                 <div style="background:#f8f9fa;border-radius:10px;
                            padding:10px 14px;max-height:340px;overflow-y:auto;">
                   ${screeningHtml}
                 </div>
               </div>`
            : `<div style="margin-top:18px;background:#fffbeb;border-left:4px solid
                        #f59e0b;padding:12px;border-radius:8px;color:#92400e;font-size:.85rem;">
                 ⚠️ No screening form answers recorded for this application.
               </div>`;

        /*  Adopter card */
        const adopterName   = a.adopter_name || a.adopter_username || 'Adopter';
        const adopterEmail  = a.adopter_email || '';
        const adopterPhone  = a.adopter_phone || '—';
        const adopterCity   = a.adopter_city  || '—';
        const adopterBio    = a.adopter_bio
            ? `<div style="margin-top:6px;color:#555;font-size:.85rem;font-style:italic;">
                "${escapeHtml(a.adopter_bio)}"
               </div>` : '';
        const submittedDate = a.submitted_at
            ? new Date(a.submitted_at).toLocaleString() : '—';
        const statusBadge   = `<span class="badge">${escapeHtml(a.status || 'Pending')}</span>`;

        body.innerHTML = `
            <!-- Adopter identity card -->
            <div style="display:flex;gap:14px;align-items:center;
                        background:#f0fdfa;border-radius:12px;padding:14px;">
                <div style="width:56px;height:56px;border-radius:50%;
                            background:#0f766e;color:#fff;display:flex;align-items:center;
                            justify-content:center;font-size:1.4rem;font-weight:800;">
                    ${escapeHtml(adopterName.charAt(0).toUpperCase())}
                </div>
                <div style="flex:1;min-width:0">
                    <div style="font-weight:800;font-size:1.05rem;">
                        ${escapeHtml(adopterName)}
                    </div>
                    <div style="color:#555;font-size:.82rem;">
                        <i class="fas fa-envelope"></i> ${escapeHtml(adopterEmail)}
                    </div>
                    <div style="color:#555;font-size:.82rem;">
                        <i class="fas fa-phone"></i> ${escapeHtml(adopterPhone)}
                        &nbsp;&nbsp;<i class="fas fa-map-marker-alt"></i>
                        ${escapeHtml(adopterCity)}
                    </div>
                    ${adopterBio}
                </div>
            </div>

            <!-- Application meta -->
            <div style="margin-top:14px;display:flex;gap:16px;flex-wrap:wrap;
                        font-size:.85rem;color:#444;">
                <span><strong>Pet:</strong> 🐾 ${escapeHtml(a.pet_name || '')}</span>
                <span><strong>Submitted:</strong> ${escapeHtml(submittedDate)}</span>
                <span><strong>Status:</strong> ${statusBadge}</span>
            </div>

            ${a.message_to_veterinary ? `
                <div style="margin-top:14px;background:#f8f9fa;border-radius:10px;
                            padding:12px;font-size:.88rem;">
                    <strong>💬 Message from Adopter:</strong><br>
                    ${escapeHtml(a.message_to_veterinary)}
                </div>` : ''}

            ${screeningSection}

            <div style="margin-top:18px;display:flex;justify-content:flex-end;gap:8px;
                        border-top:1px solid #eee;padding-top:14px;">
                <button onclick="closeModalEl('appDetailModal')"
                        class="btn btn-secondary btn-sm">Close</button>
            </div>
        `;
    } catch (e) {
        body.innerHTML =
            '<div style="padding:20px;color:#b91c1c;">Network error: ' +
            escapeHtml(e.message) + '</div>';
    }
}


['reviewModal','appDetailModal'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('click', e => {
        if (e.target === el) el.style.display = 'none';
    });
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeModalEl('reviewModal');
        closeModalEl('appDetailModal');
    }
});
</script>
<div class="modal fade" id="applicationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.2);">
            <div class="modal-header" style="background: #f8fbf9; border-bottom: 1px solid #eee; padding: 20px 25px;">
                <h5 class="modal-title" style="color: #2d5a4c; font-weight: 800; font-size: 1.25rem;">
                    <i class="fas fa-file-signature me-2"></i> Full Adoption Application
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-0">
                <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-center bg-white">
                    <div>
                        <label class="text-muted small fw-bold text-uppercase d-block" style="letter-spacing: 0.5px;">Adopter</label>
                        <span id="modal_adopter_name" class="fw-bold text-dark fs-5">-</span>
                    </div>
                    <div class="text-end">
                        <label class="text-muted small fw-bold text-uppercase d-block" style="letter-spacing: 0.5px;">Pet</label>
                        <span id="modal_pet_name" class="fw-bold text-primary fs-5">-</span>
                    </div>
                </div>

                <div class="p-4 bg-white">
                    <h6 class="fw-bold mb-3 d-flex align-items-center" style="color: #2d5a4c;">
                        <i class="fas fa-tasks me-2"></i> Application Answers
                    </h6>
                    <div id="modal_responses_container" class="custom-scroll" style="max-height: 400px; overflow-y: auto; overflow-x: hidden;">
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-spinner fa-spin me-2"></i> Loading responses...
                        </div>
                    </div>
                </div>

                <!-- Footer Details -->
                <div class="px-4 py-3" style="background: #fcfdfc; border-top: 1px solid #eee;">
                    <div class="mb-3">
                        <label class="text-muted small fw-bold text-uppercase mb-1 d-block">Additional Message</label>
                        <div id="modal_message" class="p-3 bg-white border-start border-4 border-success rounded shadow-sm" style="white-space: pre-wrap; font-style: italic; color: #444; font-size: 14px;">-</div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small fw-bold text-uppercase">Application Status</span>
                        <span id="modal_status" class="badge rounded-pill bg-info text-dark px-4 py-2" style="font-weight: 800; letter-spacing: 0.5px;">PENDING</span>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="padding: 15px 25px;">
                <button type="button" class="btn btn-light border px-4 fw-bold" data-bs-dismiss="modal" style="border-radius: 8px; color: #666;">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.custom-scroll::-webkit-scrollbar { width: 6px; }
.custom-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
.custom-scroll::-webkit-scrollbar-thumb { background: #d1d1d1; border-radius: 10px; }
.custom-scroll::-webkit-scrollbar-thumb:hover { background: #bbb; }
</style>


<?php include __DIR__ . '/../../includes/footer.php'; ?>
