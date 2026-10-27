<?php
require_once __DIR__ . '/../../includes/functions.php';
requireRole('SHELTER');

$pageTitle = 'Applications';
$user = getCurrentUser();
$db   = Database::getInstance();

$petFilter    = (int)($_GET['pet'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');
$focusApp     = (int)($_GET['app'] ?? 0);

$where  = ['p.shelter_id = ?'];
$params = [$user['user_id']];

if ($petFilter) {
    $where[]  = 'aa.pet_id = ?';
    $params[] = $petFilter;
}
if ($statusFilter !== '') {
    $where[]  = 'aa.status = ?';
    $params[] = $statusFilter;
}

$apps = $db->fetchAll(
    "SELECT aa.*,
            p.name AS pet_name,
            p.species,
            u.username AS adopter_username,
            u.email AS adopter_email,
            ap.full_name AS adopter_name,
            ap.full_name AS adopter_full_name,
            ap.phone AS adopter_phone,
            ap.city AS adopter_city,
            ap.bio AS adopter_bio,
            (
                SELECT pp.photo_url
                FROM pet_photos pp
                WHERE pp.pet_id = p.pet_id AND pp.is_primary = 1
                LIMIT 1
            ) AS pet_photo
     FROM adoption_applications aa
     JOIN pets p ON aa.pet_id = p.pet_id
     JOIN users u ON aa.adopter_id = u.user_id
     LEFT JOIN adopter_profiles ap ON aa.adopter_id = ap.adopter_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY aa.submitted_at DESC",
    $params
);

$myPets = $db->fetchAll(
    "SELECT pet_id, name
     FROM pets
     WHERE shelter_id = ? AND status != 'Removed'
     ORDER BY name",
    [$user['user_id']]
);

include __DIR__ . '/../../includes/header.php';

function appStatusBadgeClass(string $status): string {
    return match ($status) {
        'Approved'     => 'bg-success',
        'Rejected'     => 'bg-danger',
        'Under Review' => 'bg-warning text-dark',
        'Cancelled'    => 'bg-secondary',
        default        => 'bg-info text-dark',
    };
}
?>

<!-- BLOCK B — Modal CSS -->
<style>
.vd-modal[hidden] { display: none !important; }.vd-modal {
    position: fixed; inset: 0; z-index: 10020;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Nunito', sans-serif; padding: 16px;
}.vd-modal__scrim { position: absolute; inset: 0; background: rgba(0,0,0,.55); }.vd-modal__panel {
    position: relative; background: #fff; border-radius: 20px;
    width: 100%; max-width: 560px; max-height: 92vh;
    display: flex; flex-direction: column;
    box-shadow: 0 24px 60px rgba(0,0,0,.30);
    border: 2px solid #99f6e4; overflow: hidden;
    animation: vdPop .22s ease;
}@keyframes vdPop { from {opacity:0;transform:translateY(12px) scale(.97);} to {opacity:1;transform:none;} }.vd-modal__head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 22px;
    background: linear-gradient(135deg,#0d9488,#0f766e); color: #fff;
}.vd-modal__head h2 { margin: 0; font-size: 1.2rem; }.vd-modal__close {
    background: rgba(255,255,255,.15); border: none; color: #fff;
    width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 14px;
}.vd-modal__close:hover { background: rgba(255,255,255,.3); }.vd-modal__body {
    flex: 1; overflow-y: auto; padding: 22px;
    display: flex; flex-direction: column; gap: 18px; background: #f0fdfa;
}.vd-adopter { display: flex; gap: 14px; align-items: center; }.vd-adopter__avatar {
    width: 52px; height: 52px; border-radius: 50%; background: #99f6e4; color: #0f766e;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; font-weight: 800; flex-shrink: 0;
}.vd-adopter__name { font-weight: 800; color: #0f172a; font-size: 1rem; }.vd-adopter__email, .vd-adopter__phone { font-size: .85rem; color: #475569; margin-top: 2px; }.vd-adopter__phone--missing { color: #e11d48; font-weight: 700; }.vd-actions { display: flex; flex-direction: column; gap: 10px; }.vd-actions__row { display: flex; gap: 10px; }.vd-actions__row .vd-btn { flex: 1; }.vd-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    border: none; border-radius: 12px; cursor: pointer; padding: 11px 16px;
    font-weight: 800; font-size: .9rem; text-decoration: none;
    transition: transform .15s, box-shadow .15s, opacity .15s;
}.vd-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 14px rgba(0,0,0,.08); }.vd-btn:disabled { opacity: .5; cursor: not-allowed; }.vd-btn--primary { background: #0d9488; color: #fff; }.vd-btn--primary:hover:not(:disabled) { background: #0f766e; }.vd-btn--secondary { background: #fff; color: #0f766e; border: 2px solid #5eead4; }.vd-btn--secondary:hover:not(:disabled) { background: #0d9488; color: #fff; }.vd-btn--ghost { background: #f3f4f6; color: #374151; }.vd-btn--danger { background: #e11d48; color: #fff; }.vd-btn--block { width: 100%; }.vd-btn__icon { font-size: 1.05rem; }.vd-info {
    display: grid; grid-template-columns: 1fr 1fr; gap: 6px 14px;
    padding: 12px 14px; background: #fff; border-radius: 12px; border: 1px solid #ccfbf1;
}.vd-info__row { display: contents; }.vd-info__k { font-size: .72rem; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; font-weight: 800; align-self: center; }.vd-info__v { font-weight: 700; color: #1f2937; }.vd-section__h { font-size: .78rem; margin: 0 0 6px; color: #0f766e; text-transform: uppercase; letter-spacing: .06em; }.vd-section__p { margin: 0; color: #374151; line-height: 1.55; font-size: .9rem; white-space: pre-line; }.vd-section__p--muted { color: #94a3b8; font-style: italic; }.vd-screen { margin: 0; padding: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 6px 14px; }.vd-screen__row { display: contents; }.vd-screen__k { font-size: .72rem; color: #6b7280; text-transform: uppercase; font-weight: 800; align-self: center; }.vd-screen__v { font-weight: 700; color: #1f2937; font-size: .88rem; padding: 4px 0; border-bottom: 1px dashed #e5e7eb; }.vd-status { display: flex; flex-wrap: wrap; gap: 8px; padding-top: 6px; border-top: 1px dashed #cbd5e1; }@media (max-width: 480px) {
    .vd-modal { padding: 0; }
    .vd-modal__panel { max-width: 100%; max-height: 100vh; height: 100%; border-radius: 0; border: none; }
    .vd-actions__row { flex-direction: column; }
    .vd-info, .vd-screen { grid-template-columns: 1fr; }
}@media (min-width: 481px) and (max-width: 768px) {
    .vd-actions__row .vd-btn { flex: 1 1 50%; }
}@media (min-width: 1280px) {
    .vd-modal__panel { max-width: 640px; }
}
.custom-scroll::-webkit-scrollbar { width: 6px; }
.custom-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
.custom-scroll::-webkit-scrollbar-thumb { background: #d1d1d1; border-radius: 10px; }
.custom-scroll::-webkit-scrollbar-thumb:hover { background: #bbb; }
</style>

<div class="page-header">
    <h1 class="page-title"><span class="icon">📋</span> Adoption Applications</h1>
    <span class="text-muted"><?= count($apps) ?> application<?= count($apps) !== 1 ? 's' : '' ?></span>
</div>

<div class="filter-bar">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <select name="pet" class="form-control" onchange="this.form.submit()">
            <option value="">All Pets</option>
            <?php foreach ($myPets as $p): ?>
                <option value="<?= (int)$p['pet_id'] ?>" <?= $petFilter === (int)$p['pet_id'] ? 'selected' : '' ?>>
                    <?= sanitize($p['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="status" class="form-control" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <?php foreach (['Submitted', 'Under Review', 'Approved', 'Rejected', 'Cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>>
                    <?= $s ?>
                </option>
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
        <?php foreach ($apps as $app): ?>
            <?php
                $photo = !empty($app['pet_photo'])
                    ? rtrim(APP_URL, '/') . '/' . ltrim($app['pet_photo'], '/')
                    : rtrim(APP_URL, '/') . '/assets/images/pet-placeholder.png';

                $highlighted = ($focusApp === (int)$app['application_id']);
                $status     = (string)($app['status'] ?? 'Submitted');
                $canReview   = !in_array($status, ['Approved', 'Rejected', 'Cancelled'], true);
                $appId       = (int)($app['application_id'] ?? 0);
            ?>
            <div class="card <?= $highlighted ? 'highlight-card' : '' ?>"
                 id="app-<?= $appId ?>"
                 data-application-id="<?= $appId ?>"
                 data-status="<?= sanitize($status) ?>"
                 style="<?= $highlighted ? 'border:2px solid var(--teal)' : '' ?>">

                <div style="display:flex;gap:16px;flex-wrap:wrap">
                    <img src="<?= sanitize($photo) ?>"
                         alt="<?= sanitize($app['pet_name']) ?>"
                         style="width:80px;height:80px;border-radius:12px;object-fit:cover;flex-shrink:0"
                         onerror="this.src='<?= rtrim(APP_URL, '/') ?>/assets/images/pet-placeholder.png'">

                    <div style="flex:1;min-width:220px">
                        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px">
                            <div>
                                <span class="fw-bold" style="font-size:1.05rem">
                                    <?= sanitize($app['adopter_name'] ?: $app['adopter_username']) ?>
                                </span>
                                <span class="text-muted"> wants to adopt </span>
                                <span class="fw-bold text-teal"><?= sanitize($app['pet_name']) ?></span>
                            </div>

                            <div style="display:flex;align-items:center;gap:8px">
                                <span class="badge js-app-status-badge <?= appStatusBadgeClass($status) ?>">
                                    <?= sanitize($status) ?>
                                </span>
                                <span class="text-muted" style="font-size:.78rem">
                                    <?= !empty($app['submitted_at']) ? date('M j, Y', strtotime($app['submitted_at'])) : '' ?>
                                </span>
                            </div>
                        </div>

                        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px;font-size:.82rem;color:#888">
                            <span><i class="fas fa-envelope"></i> <?= sanitize($app['adopter_email']) ?></span>

                            <?php if (!empty($app['adopter_phone'])): ?>
                                <span><i class="fas fa-phone"></i> <?= sanitize($app['adopter_phone']) ?></span>
                            <?php endif; ?>

                            <?php if (!empty($app['adopter_city'])): ?>
                                <span><i class="fas fa-map-marker-alt"></i> <?= sanitize($app['adopter_city']) ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($app['message_to_shelter'])): ?>
                            <div style="margin-top:10px;background:#f8f9fa;border-radius:10px;padding:12px;font-size:.88rem">
                                <strong>Adopter's message:</strong><br>
                                <?= nl2br(sanitize($app['message_to_shelter'])) ?>
                            </div>
                        <?php endif; ?>

                        <div class="js-app-actions" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                            <button type="button"
                                    data-open-app="<?= $appId ?>"
                                    class="btn btn-info btn-sm"
                                    style="background:#0ea5e9;color:#fff;border:none;cursor:pointer;">
                                <i class="fas fa-file-alt"></i> View Application
                            </button>

                            <?php if ($canReview): ?>
                                <?php if ($status === 'Submitted'): ?>
                                    <a href="#"
                                       data-action="under-review"
                                       data-app-id="<?= $appId ?>"
                                       class="js-under-review-link"
                                       style="font-size:.82rem;color:#92400e;text-decoration:underline;align-self:center;cursor:pointer;">
                                        🔍 Mark as Under&nbsp;Review
                                    </a>
                                <?php else: ?>
                                    <span class="js-under-review-label"
                                          style="font-size:.82rem;color:#666;align-self:center;">
                                        🔍 Under Review
                                    </span>
                                <?php endif; ?>

                                <button type="button"
                                        data-action="approve"
                                        data-app-id="<?= $appId ?>"
                                        class="btn btn-success btn-sm js-review-action"
                                        style="cursor:pointer;">
                                    ✅ Approve
                                </button>

                                <button type="button"
                                        data-action="reject"
                                        data-app-id="<?= $appId ?>"
                                        class="btn btn-danger btn-sm js-review-action"
                                        style="cursor:pointer;">
                                    ❌ Reject
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BLOCK A — Modal HTML -->
            <?php $csrf = pa_get_csrf(); ?>
            <div class="vd-modal" id="vdModal<?= (int)$app['application_id'] ?>" role="dialog"
                 aria-labelledby="vdTitle<?= (int)$app['application_id'] ?>" hidden>
                <div class="vd-modal__scrim" data-close></div>

                <div class="vd-modal__panel" role="document">
                    <header class="vd-modal__head">
                        <h2 id="vdTitle<?= (int)$app['application_id'] ?>">📋 Application Details</h2>
                        <button class="vd-modal__close" aria-label="Close" data-close>✕</button>
                    </header>

                    <div class="vd-modal__body">
                        <?php
                            $adopterName  = $app['adopter_full_name'] ?? ($app['adopter_username'] ?? 'Adopter');
                            $adopterEmail =$app['adopter_email']     ?? '';
                            $adopterPhone =$app['adopter_phone']     ?? '';
                            $petName      =$app['pet_name']          ?? '';
                        ?>

                        <section class="vd-adopter">
                            <div class="vd-adopter__avatar"><?= strtoupper(mb_substr($adopterName, 0, 1)) ?></div>
                            <div class="vd-adopter__meta">
                                <div class="vd-adopter__name"><?= htmlspecialchars($adopterName) ?></div>
                                <div class="vd-adopter__email"><?= htmlspecialchars($adopterEmail) ?></div>
                                <?php if ($adopterPhone): ?>
                                    <div class="vd-adopter__phone">📞 <?= htmlspecialchars($adopterPhone) ?></div>
                                <?php else: ?>
                                    <div class="vd-adopter__phone vd-adopter__phone--missing">
                                        📞 Phone number unavailable
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="vd-actions">
                            <button class="vd-btn vd-btn--primary vd-btn--block"
                                    data-open-schedule
                                    data-application-id="<?= (int)$app['application_id'] ?>"
                                    data-pet-name="<?= htmlspecialchars($petName) ?>"
                                    data-adopter-name="<?= htmlspecialchars($adopterName) ?>"
                                    data-csrf="<?= htmlspecialchars($csrf) ?>">
                                <span class="vd-btn__icon">📅</span> Schedule Appointment
                            </button>

                            <div class="vd-actions__row">
                                <?php if ($adopterPhone): ?>
                                    <a class="vd-btn vd-btn--secondary"
                                       href="tel:<?= preg_replace('/[^0-9+]/', '', $adopterPhone) ?>">
                                        <span class="vd-btn__icon">📞</span> Call Adopter
                                    </a>
                                    <a class="vd-btn vd-btn--secondary"
                                       href="sms:<?= preg_replace('/[^0-9+]/', '', $adopterPhone) ?>">
                                        <span class="vd-btn__icon">💬</span> Text Adopter
                                    </a>
                                <?php else: ?>
                                    <button class="vd-btn vd-btn--secondary" disabled
                                            aria-label="Phone number unavailable">
                                        <span class="vd-btn__icon">📞</span> Call Adopter
                                    </button>
                                    <button class="vd-btn vd-btn--secondary" disabled
                                            aria-label="Phone number unavailable">
                                        <span class="vd-btn__icon">💬</span> Text Adopter
                                    </button>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="vd-info">
                            <div class="vd-info__row">
                                <span class="vd-info__k">Pet</span>
                                <span class="vd-info__v">🐾 <?= htmlspecialchars($petName) ?></span>
                            </div>
                            <div class="vd-info__row">
                                <span class="vd-info__k">Status</span>
                                <span class="vd-info__v"><?= sanitize($status) ?></span>
                            </div>
                            <div class="vd-info__row">
                                <span class="vd-info__k">Submitted</span>
                                <span class="vd-info__v">
                                    <?= !empty($app['submitted_at']) ? date('M j, Y', strtotime($app['submitted_at'])) : '—' ?>
                                </span>
                            </div>
                        </section>

                        <section class="vd-section">
                            <h3 class="vd-section__h">Message from Adopter</h3>
                            <p class="vd-section__p">
                                <?= nl2br(htmlspecialchars($app['message_to_shelter'] ?? '—')) ?>
                            </p>
                        </section>

                        <section class="vd-section">
                            <h3 class="vd-section__h">Application Information</h3>
                            <dl class="vd-screen">
                                <?php
                                    $screenFields = [
                                        'App Type'          => $app['app_type']        ?? null,
                                        'Full Name'         => $app['full_name']       ?? null,
                                        'Sex'               => $app['sex']             ?? null,
                                        'DOB'               => $app['dob']             ?? null,
                                        'Civil Status'      => $app['civil_status']    ?? null,
                                        'Address'           => $app['address']         ?? null,
                                        'Residence Status'  => $app['residence']       ?? null,
                                        'Phone'             => $app['phone']           ?? null,
                                        'Income'            => $app['income']          ?? null,
                                    ];
                                    foreach ($screenFields as $k =>$v):
                                        if ($v === null ||$v === '') continue; ?>
                                        <div class="vd-screen__row">
                                            <dt class="vd-screen__k"><?= htmlspecialchars($k) ?></dt>
                                            <dd class="vd-screen__v"><?= htmlspecialchars($v) ?></dd>
                                        </div>
                                    <?php endforeach; ?>
                            </dl>
                        </section>

                        <section class="vd-section" data-appt-history
                                 data-application-id="<?= (int)$app['application_id'] ?>">
                            <h3 class="vd-section__h">Appointment History</h3>
                            <p class="vd-section__p vd-section__p--muted">Loading…</p>
                        </section>

                        <section class="vd-status">
                            <button class="vd-btn vd-btn--ghost" data-action="approve" data-app-id="<?= (int)$app['application_id'] ?>">Approve</button>
                            <button class="vd-btn vd-btn--ghost" data-action="under-review" data-app-id="<?= (int)$app['application_id'] ?>">Under Review</button>
                            <button class="vd-btn vd-btn--danger" data-action="reject" data-app-id="<?= (int)$app['application_id'] ?>">Reject</button>
                            <button class="vd-btn vd-btn--ghost" data-close>Close</button>
                        </section>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.2);">
            <div class="modal-header" style="background:#f8fbf9;border-bottom:1px solid #eee;">
                <h5 class="modal-title" id="reviewModalTitle">Review Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <p id="reviewModalPrompt" class="mb-3 text-muted">
                    Confirm this review action.
                </p>

                <div class="form-group">
                    <label for="decisionNote" class="form-label">Decision Note (optional)</label>
                    <textarea id="decisionNote"
                              class="form-control"
                              rows="4"
                              placeholder="Add a short explanation for your decision..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmReviewBtn">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const CURRENT_PATH = window.location.pathname;
    const API_BASE     = CURRENT_PATH.replace(/\/pages\/.*$/, '/api/');

    let reviewAppId         = null;
    let reviewStatus        = null;
    let isSubmittingReview = false;

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function $(id) {
        return document.getElementById(id);
    }

    function badgeClassForStatus(status) {
        switch (status) {
            case 'Approved':     return 'bg-success';
            case 'Rejected':     return 'bg-danger';
            case 'Under Review': return 'bg-warning text-dark';
            case 'Cancelled':    return 'bg-secondary';
            default:             return 'bg-info text-dark';
        }
    }

    function showToast(message, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
            return;
        }
        const existing = document.getElementById('js-toast-fallback');
        if (existing) existing.remove();

        const el = document.createElement('div');
        el.id = 'js-toast-fallback';
        el.textContent = message;
        el.style.cssText =
            'position:fixed;bottom:24px;right:24px;z-index:99999;' +
            'background:' + (type === 'error' ? '#dc2626' : '#0f766e') + ';' +
            'color:#fff;padding:12px 18px;border-radius:10px;font-size:14px;' +
            'box-shadow:0 8px 24px rgba(0,0,0,.18);font-family:inherit;';
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3500);
    }

    function getModal(id) {
        const el = $(id);
        if (!el) {
            console.error('Modal element not found:', id);
            return null;
        }
        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            console.error('Bootstrap 5 JS is not loaded — falling back to manual show.');
            return {
                show() {
                    el.style.display = 'block';
                    el.classList.add('show');
                    document.body.classList.add('modal-open');
                },
                hide() {
                    el.style.display = 'none';
                    el.classList.remove('show');
                    document.body.classList.remove('modal-open');
                }
            };
        }
        return bootstrap.Modal.getOrCreateInstance(el);
    }

    function updateCardStatusUI(appId, status) {
        const card = document.getElementById('app-' + appId);
        if (!card) return;

        card.dataset.status = status;

        const badge = card.querySelector('.js-app-status-badge');
        if (badge) {
            badge.className = 'badge js-app-status-badge ' + badgeClassForStatus(status);
            badge.textContent = status;
        }

        const actions = card.querySelector('.js-app-actions');
        if (!actions) return;

        if (status === 'Under Review') {
            const reviewLink = actions.querySelector('.js-under-review-link');
            if (reviewLink) {
                const span = document.createElement('span');
                span.className = 'js-under-review-label';
                span.style.cssText = 'font-size:.82rem;color:#666;align-self:center;';
                span.textContent = '🔍 Under Review';
                reviewLink.replaceWith(span);
            }
        }

        if (['Approved', 'Rejected', 'Cancelled'].includes(status)) {
            actions.querySelectorAll('.js-review-action, .js-under-review-link, .js-under-review-label')
                .forEach(el => el.remove());
        }
    }

    async function apiRequest(payload) {
        const body = new FormData();
        Object.entries(payload).forEach(([k, v]) => body.append(k, v ?? ''));

        const response = await fetch(API_BASE + 'applications.php', {
            method: 'POST',
            body,
            credentials: 'same-origin'
        });

        let res = null;
        try { res = await response.json(); }
        catch (e) {
            throw new Error('Server returned non-JSON response (HTTP ' + response.status + ').');
        }
        return res;
    }

    function showReviewModal(appId, status) {
        reviewAppId  = Number(appId) || 0;
        reviewStatus = status;

        const modal   = getModal('reviewModal');
        if (!modal) {
            alert('Cannot open review modal — required markup missing.');
            return;
        }
        const title   = $('reviewModalTitle');
        const prompt  = $('reviewModalPrompt');
        const noteEl  = $('decisionNote');
        const confirm = $('confirmReviewBtn');

        if (noteEl) noteEl.value = '';
        if (confirm) confirm.disabled = false;

        if (status === 'Approved') {
            title.textContent = '✅ Approve Application';
            prompt.textContent = 'You are about to approve this adoption application.';
            confirm.textContent = 'Approve Application';
            confirm.classList.remove('btn-primary', 'btn-danger');
            confirm.classList.add('btn-success');
        } else {
            title.textContent = '❌ Reject Application';
            prompt.textContent = 'You are about to reject this adoption application.';
            confirm.textContent = 'Reject Application';
            confirm.classList.remove('btn-primary', 'btn-success');
            confirm.classList.add('btn-danger');
        }

        modal.show();
    }

    async function submitReview() {
        if (!reviewAppId || !reviewStatus || isSubmittingReview) return;

        const note    = (($('decisionNote') || {}).value || '').trim();
        const appId   = reviewAppId;
        const status  = reviewStatus;
        const confirm = $('confirmReviewBtn');

        const originalText = confirm ? confirm.textContent : '';
        isSubmittingReview = true;
        if (confirm) {
            confirm.disabled = true;
            confirm.textContent = 'Processing...';
        }

        try {
            const modal = getModal('reviewModal');
            if (modal) modal.hide();

            const res = await apiRequest({
                action: 'review',
                application_id: appId,
                status: status,
                decision_note: note
            });

            if (res && res.success) {
                updateCardStatusUI(appId, status);
                showToast('Application ' + status + ' ✓', 'success');
                setTimeout(() => window.location.reload(), 600);
                return true;
            }

            showToast((res && res.message) || 'Failed to update application.', 'error');
            return false;
        } catch (e) {
            console.error('submitReview error:', e);
            showToast('Network error: ' + (e.message || e), 'error');
            return false;
        } finally {
            isSubmittingReview = false;
            if (confirm) {
                confirm.disabled = false;
                confirm.textContent = originalText;
            }
        }
    }

    async function markUnderReviewManually(appId) {
        if (!appId) return;
        const card = document.getElementById('app-' + appId);
        if (card && card.dataset.status === 'Under Review') return;
        try {
            const res = await apiRequest({
                action: 'review',
                application_id: appId,
                status: 'Under Review',
                decision_note: ''
            });
            if (res && res.success) {
                updateCardStatusUI(appId, 'Under Review');
                showToast('Marked as Under Review ✓', 'success');
                setTimeout(() => window.location.reload(), 400);
            } else {
                showToast((res && res.message) || 'Failed.', 'error');
            }
        } catch (e) {
            showToast('Network error: ' + (e.message || e), 'error');
        }
    }

    window.showReviewModal         = showReviewModal;
    window.submitReview            = submitReview;
    window.markUnderReviewManually = markUnderReviewManually;

    document.addEventListener('click', function (event) {
        const target = event.target.closest('[data-action]');
        if (!target) return;

        const action = target.getAttribute('data-action');
        const appId  = parseInt(target.getAttribute('data-app-id'), 10) || 0;

        if (action === 'under-review') {
            event.preventDefault();
            markUnderReviewManually(appId);
            return;
        }
        if (action === 'approve') {
            event.preventDefault();
            showReviewModal(appId, 'Approved');
            return;
        }
        if (action === 'reject') {
            event.preventDefault();
            showReviewModal(appId, 'Rejected');
            return;
        }
    });

    const confirmBtn = $('confirmReviewBtn');
    if (confirmBtn) confirmBtn.addEventListener('click', submitReview);

    const reviewModalEl = $('reviewModal');
    if (reviewModalEl) {
        reviewModalEl.addEventListener('hidden.bs.modal', () => {
            if (!isSubmittingReview) {
                reviewAppId  = null;
                reviewStatus = null;
                const note = $('decisionNote');
                if (note) note.value = '';
            }
        });
    }
})();
</script>

<!-- BLOCK C — Modal JS -->
<script>
(function () {'use strict';const APPOINTMENT_TYPES = [
    'Veterinary Consultation', 'Meet & Greet',
    'Follow-up Visit', 'Pickup'
];function openScheduleDialog(opts) {
    document.getElementById('vdScheduleModal')?.remove();
    const wrap = document.createElement('div');
    wrap.id = 'vdScheduleModal';
    wrap.className = 'vd-modal';
    wrap.innerHTML = `
      <div class="vd-modal__scrim" data-close></div>
      <div class="vd-modal__panel" role="document" style="max-width:520px">
        <header class="vd-modal__head">
          <h2>📅 Schedule Appointment</h2>
          <button class="vd-modal__close" data-close aria-label="Close">✕</button>
        </header>
        <form class="vd-modal__body" id="vdScheduleForm" style="gap:14px">
          <input type="hidden" name="application_id" value="${opts.application_id}">
          <input type="hidden" name="csrf"          value="${opts.csrf}">

          <div>
            <div class="vd-section__h">Pet</div>
            <div style="font-weight:800;color:#0f172a;">${opts.pet_name || '—'}</div>
          </div>
          <div>
            <div class="vd-section__h">Adopter</div>
            <div style="font-weight:700;color:#1f2937;">${opts.adopter_name || '—'}</div>
          </div>

          <label style="display:flex;flex-direction:column;gap:4px;font-size:.78rem;color:#0f766e;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">
            Date
            <input type="date" name="date" required min="${new Date().toISOString().slice(0,10)}"
                   style="padding:10px 12px;border:2px solid #ccfbf1;border-radius:10px;font:inherit;color:#0f172a;">
          </label>

          <label style="display:flex;flex-direction:column;gap:4px;font-size:.78rem;color:#0f766e;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">
            Time
            <input type="time" name="time" required
                   style="padding:10px 12px;border:2px solid #ccfbf1;border-radius:10px;font:inherit;color:#0f172a;">
          </label>

          <label style="display:flex;flex-direction:column;gap:4px;font-size:.78rem;color:#0f766e;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">
            Appointment Type
            <select name="type" required
                    style="padding:10px 12px;border:2px solid #ccfbf1;border-radius:10px;font:inherit;color:#0f172a;">
              ${APPOINTMENT_TYPES.map(t => `<option value="${t}">${t}</option>`).join('')}
            </select>
          </label>

          <label style="display:flex;flex-direction:column;gap:4px;font-size:.78rem;color:#0f766e;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">
            Notes
            <textarea name="notes" rows="3" placeholder="Anything the adopter should bring…"
                      style="padding:10px 12px;border:2px solid #ccfbf1;border-radius:10px;font:inherit;color:#0f172a;resize:vertical;"></textarea>
          </label>

          <div id="vdScheduleErr" class="vd-section__p vd-section__p--muted"></div>

          <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:6px;border-top:1px dashed #cbd5e1;">
            <button type="button" class="vd-btn vd-btn--ghost"   data-close>Cancel</button>
            <button type="submit" class="vd-btn vd-btn--primary" id="vdScheduleSubmit">Schedule</button>
          </div>
        </form>
      </div>`;
    document.body.appendChild(wrap);
    wrap.querySelector('[name="date"]').focus();

    wrap.addEventListener('click', e => {
        if (e.target.matches('[data-close]')) wrap.remove();
    });

    wrap.querySelector('#vdScheduleForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const err    = wrap.querySelector('#vdScheduleErr');
        const submit = wrap.querySelector('#vdScheduleSubmit');
        err.textContent = '';

        submit.disabled = true;
        try {
            const fd = new FormData(e.currentTarget);
            fd.append('action', 'create');
            const res  = await fetch('<?= APP_URL ?>/api/appointments.php?action=create', {
                method: 'POST', body: fd, credentials: 'same-origin'
            });
            const json = await res.json();
            const okFlag = (json.ok === true || json.success === true);
            if (!okFlag) {
                console.error('[appointments/create] non-OK response', res.status, json);
                err.textContent = 'Server error (' + res.status + '): ' + (
                    json.error || json.message || JSON.stringify(json)
                );
                err.className   = 'vd-section__p';
                err.style.color = '#e11d48';
                submit.disabled = false;
                return;
            }

            err.textContent = '✓ Appointment scheduled. Adopter notified.';
            err.className   = 'vd-section__p';
            err.style.color = '#0f766e';
            setTimeout(() => { wrap.remove(); refreshHistory(); }, 900);
        } catch (e2) {
            err.textContent = 'Network error. Please retry.';
            err.className   = 'vd-section__p';
            err.style.color = '#e11d48';
            submit.disabled = false;
        }
    });
}async function refreshHistory() {
    const targets = document.querySelectorAll('[data-appt-history]');
    if (!targets.length) return;
    try {
        const res  = await fetch('<?= APP_URL ?>/api/appointments.php?action=list', { credentials: 'same-origin' });
        const json = await res.json();
        const all  = json.appointments || [];
        targets.forEach(node => {
            const appId = node.dataset.applicationId;
            const mine  = all.filter(a => String(a.application_id) === String(appId));
            node.innerHTML = `<h3 class="vd-section__h">Appointment History</h3>` +
                (mine.length
                    ? mine.map(a => `
                      <div class="vd-info__row">
                        <span class="vd-info__k">${a.appointment_date}</span>
                        <span class="vd-info__v">${a.appointment_time} · ${a.appointment_type}</span>
                        <span class="vd-info__k">Status</span>
                        <span class="vd-info__v">${a.status}</span>
                      </div>`).join('')
                    : '<p class="vd-section__p vd-section__p--muted">No appointments yet.</p>');
        });
    } catch (e) {
        targets.forEach(node => {
            node.innerHTML = '<p class="vd-section__p vd-section__p--muted">Could not load appointments.</p>';
        });
    }
}document.addEventListener('click', (e) => {
    if (e.target.closest('[data-open-schedule]')) {
        e.preventDefault();
        const sched = e.target.closest('[data-open-schedule]');
        openScheduleDialog({
            application_id: sched.dataset.applicationId,
            csrf:           sched.dataset.csrf,
            pet_name:       sched.dataset.petName,
            adopter_name:   sched.dataset.adopterName,
        });
        return;
    }
    if (e.target.closest('[data-open-app]')) {
        const m = document.getElementById('vdModal' + e.target.closest('[data-open-app]').dataset.openApp);
        if (m) m.hidden = false;
        return;
    }
    if (e.target.closest('[data-close]')) {
        const m = e.target.closest('.vd-modal');
        if (m) m.hidden = true;
    }
});document.addEventListener('DOMContentLoaded', refreshHistory);
})();</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>