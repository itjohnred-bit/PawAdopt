<?php
require_once __DIR__ . '/../../includes/functions.php';
requireRole('ADOPTER');
$pageTitle = 'Dashboard';
$user = getCurrentUser();
$db = Database::getInstance();

// Stats
$favCount = $db->fetch("SELECT COUNT(*) as c FROM favorites WHERE adopter_id = ?", [$user['user_id']])['c'] ?? 0;
$appCount = $db->fetch("SELECT COUNT(*) as c FROM adoption_applications WHERE adopter_id = ?", [$user['user_id']])['c'] ?? 0;
$petCount = $db->fetch("SELECT COUNT(*) as c FROM pets WHERE status = 'Available'")['c'] ?? 0;
$msgCount = getUnreadMessageCount($user['user_id']);

// Profile
$profile = $db->fetch("SELECT * FROM adopter_profiles WHERE adopter_id = ?", [$user['user_id']]);

// Recent applications
$recentApps = $db->fetchAll(
    "SELECT aa.*, p.name as pet_name, p.species, sp.shelter_name,
     (SELECT pp.photo_url FROM pet_photos pp WHERE pp.pet_id = p.pet_id AND pp.is_primary = 1 LIMIT 1) as pet_photo
     FROM adoption_applications aa
     JOIN pets p ON aa.pet_id = p.pet_id
     JOIN shelter_profiles sp ON aa.shelter_id = sp.shelter_id
     WHERE aa.adopter_id = ?
     ORDER BY aa.submitted_at DESC LIMIT 5",
    [$user['user_id']]
);

// Featured pets
$featuredPets = $db->fetchAll(
    "SELECT p.*, sp.shelter_name, sp.city as shelter_city,
     (SELECT pp.photo_url FROM pet_photos pp WHERE pp.pet_id = p.pet_id AND pp.is_primary = 1 LIMIT 1) as primary_photo
     FROM pets p JOIN shelter_profiles sp ON p.shelter_id = sp.shelter_id
     WHERE p.status = 'Available'
     ORDER BY RAND() LIMIT 4"
);

$recentFeedback = [];
try {
    $recentFeedback = $db->fetchAll(
        "SELECT title, message, rating, created_at
         FROM user_feedback
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 3",
        [$user['user_id']]
    );
} catch (Throwable $e) {
    $recentFeedback = [];
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
.stats-grid a.stat-card{display:flex;text-decoration:none;color:inherit;transition:transform .15s ease,box-shadow .15s ease;border-radius:14px}
.stats-grid a.stat-card:hover{transform:translateY(-3px);box-shadow:0 10px 22px rgba(13,148,136,.18)}
.stats-grid a.stat-card:focus-visible{outline:3px solid #5eead4;outline-offset:2px}
.action-strip{display:flex;gap:12px;flex-wrap:wrap;margin:0 0 24px}
.action-mini{display:inline-flex;align-items:center;gap:10px;padding:12px 18px;border-radius:999px;background:#ffffff;border:1px solid #dbe7e4;color:#0f766e;font-weight:800;text-decoration:none;box-shadow:0 8px 18px rgba(15,118,110,.08);transition:transform .15s ease,box-shadow .15s ease,background .15s ease}
.action-mini:hover{transform:translateY(-2px);box-shadow:0 12px 22px rgba(15,118,110,.14);background:#f0fdfa}
.action-mini.report{color:#b42318;border-color:#fecaca;background:#fff7f7}
.action-mini.report:hover{background:#fef2f2}
.quick-panel{display:grid;grid-template-columns:1.1fr .9fr;gap:20px;margin-top:20px}
.mini-card{background:#fff;border:1px solid #ebf0ee;border-radius:18px;padding:20px;box-shadow:0 10px 24px rgba(15,118,110,.07)}
.mini-card h3{margin:0 0 14px;font-size:1rem;font-weight:900;color:#134e4a}
.feed-item{padding:12px 0;border-bottom:1px solid #edf2f0}.feed-item:last-child{border-bottom:none;padding-bottom:0}
.feed-stars{color:#f59e0b;font-size:.82rem;letter-spacing:1px}
.feed-meta{font-size:.78rem;color:var(--gray-mid)}
.dash-modal{position:fixed;inset:0;background:rgba(15,23,42,.55);display:none;align-items:center;justify-content:center;padding:18px;z-index:9999;backdrop-filter:blur(6px)}
.dash-modal.open{display:flex}
.dash-modal-card{background:#fff;border-radius:28px;width:min(560px,100%);padding:28px;box-shadow:0 25px 60px rgba(2,8,23,.28)}
.dash-modal-card h2{margin:0 0 8px;font-size:1.6rem;color:#111827;font-weight:900}
.dash-modal-card p{margin:0 0 18px;color:#6b7280;font-size:.94rem;line-height:1.6}
.dash-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.dash-grid .full{grid-column:1/-1}
.dash-input,.dash-textarea,.dash-select{width:100%;border:1px solid #d6e3df;border-radius:14px;padding:12px 14px;font:inherit;background:#fcfefe}
.dash-textarea{min-height:120px;resize:vertical}
.dash-check{display:flex;gap:10px;align-items:flex-start;font-size:.88rem;color:#475569;line-height:1.5;margin-top:12px}
.dash-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px;flex-wrap:wrap}
.dash-btn{border:none;border-radius:999px;padding:11px 18px;font-weight:800;cursor:pointer}
.dash-btn.primary{background:#0f766e;color:#fff}.dash-btn.secondary{background:#f1f5f9;color:#334155}.dash-btn.danger{background:#b42318;color:#fff}
.dash-note{margin-top:12px;padding:12px 14px;border-radius:14px;background:#ecfeff;color:#155e75;font-size:.84rem;line-height:1.55}
.feedback-modal-card{max-width:540px;padding:30px 30px 24px;border:1px solid #eef2f7}
.feedback-title{margin-bottom:6px;font-size:1.7rem;line-height:1.15;color:#111827;font-weight:900}
.feedback-subtitle{margin-bottom:22px;color:#6b7280;font-size:.95rem;line-height:1.6}
.feedback-rating-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:18px}
.feedback-rating-btn{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;min-height:86px;padding:10px 6px;border:1px solid #e5e7eb;border-radius:18px;background:#fff;transition:border-color .2s ease,box-shadow .2s ease,transform .2s ease}
.feedback-rating-btn:hover{transform:translateY(-2px);box-shadow:0 10px 22px rgba(15,23,42,.08)}
.feedback-rating-btn.active{border-color:#60a5fa;box-shadow:0 0 0 3px rgba(96,165,250,.18)}
.feedback-rating-emoji{font-size:1.7rem;line-height:1}
.feedback-rating-label{font-size:.72rem;font-weight:800;color:#64748b;text-align:center;line-height:1.2}
.feedback-label{display:block;margin:0 0 8px;font-size:.88rem;font-weight:800;color:#1f2937}
.feedback-input,.feedback-textarea{width:100%;border:1px solid #e5e7eb;border-radius:14px;background:#fff;padding:13px 14px;font:inherit;color:#111827;transition:border-color .2s ease,box-shadow .2s ease}
.feedback-input:focus,.feedback-textarea:focus{outline:none;border-color:#93c5fd;box-shadow:0 0 0 4px rgba(147,197,253,.18)}
.feedback-textarea{min-height:132px;resize:vertical}
.feedback-check{display:flex;gap:10px;align-items:flex-start;margin-top:14px;color:#4b5563;font-size:.88rem;line-height:1.55}
.feedback-check input{margin-top:3px;accent-color:#2563eb}
.feedback-check a{color:#2563eb;text-decoration:underline}
.feedback-actions{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:22px}
.feedback-btn{border:none;border-radius:14px;padding:14px 18px;font-weight:800;font-size:.95rem;cursor:pointer;transition:transform .15s ease,box-shadow .15s ease,opacity .15s ease}
.feedback-btn:hover{transform:translateY(-1px)}
.feedback-btn.cancel{background:#f3f4f6;color:#111827}
.feedback-btn.submit{background:#2563eb;color:#fff;box-shadow:0 12px 24px rgba(37,99,235,.22)}
.feedback-btn.submit:disabled{opacity:.6;cursor:not-allowed;box-shadow:none}
.feedback-chip-row{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px}
.feedback-chip{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:.74rem;font-weight:800}

/* Upcoming Appointment Status Pill Styling */
.vd-status-pill{display:inline-block;padding:5px 12px;border-radius:99px;font-weight:800;font-size:.75rem;letter-spacing:.04em}
.vd-status-pill--scheduled{background:#fef3c7;color:#92400e}
.vd-status-pill--confirmed{background:#d1fae5;color:#065f46}
.vd-status-pill--completed{background:#dbeafe;color:#1e40af}
.vd-status-pill--cancelled{background:#fee2e2;color:#991b1b}
.vd-status-pill--rescheduled{background:#ede9fe;color:#5b21b6}

@media (max-width: 900px){.quick-panel{grid-template-columns:1fr}.dash-grid{grid-template-columns:1fr}}
@media (max-width: 640px){.feedback-modal-card{padding:24px 18px 20px}.feedback-rating-grid{grid-template-columns:repeat(2,1fr)}.feedback-actions{grid-template-columns:1fr}}
@media (max-width:480px){.vd-upcoming__actions .btn{width:100%;justify-content:center}}
</style>

<div class="page-header">
    <h1 class="page-title"><span class="icon">🏠</span> Welcome back, <?= sanitize(!empty($profile['full_name']) ? $profile['full_name'] : $user['username']) ?>!</h1>
    <a href="<?= APP_URL ?>/pages/adopter/browse.php" class="btn btn-primary">
        <i class="fas fa-search"></i> Browse Pets
    </a>
</div>

<div class="action-strip">
    <button type="button" class="action-mini" id="openFeedbackBtn"><i class="fas fa-heart"></i> Share Feedback</button>
    <button type="button" class="action-mini report" id="openReportBtn"><i class="fas fa-flag"></i> Report a Concern</button>
</div>

<div class="stats-grid">
    <a href="<?= APP_URL ?>/pages/adopter/browse.php" class="stat-card">
        <div class="stat-icon teal"><span>🐾</span></div>
        <div>
            <div class="stat-value"><?= $petCount ?></div>
            <div class="stat-label">Pets Available</div>
        </div>
    </a>
    <a href="<?= APP_URL ?>/pages/adopter/favorites.php" class="stat-card">
        <div class="stat-icon green"><span>❤️</span></div>
        <div>
            <div class="stat-value"><?= $favCount ?></div>
            <div class="stat-label">Favorites Saved</div>
        </div>
    </a>
    <a href="<?= APP_URL ?>/pages/adopter/applications.php" class="stat-card">
        <div class="stat-icon blue"><span>📋</span></div>
        <div>
            <div class="stat-value"><?= $appCount ?></div>
            <div class="stat-label">Applications</div>
        </div>
    </a>
    <a href="<?= APP_URL ?>/pages/adopter/messages.php" class="stat-card">
        <div class="stat-icon amber"><span>💬</span></div>
        <div>
            <div class="stat-value"><?= $msgCount ?></div>
            <div class="stat-label">New Messages</div>
        </div>
    </a>
</div>

<!-- Upcoming Appointment Block -->
<?php
$upcomingAppt = $db->fetch(
    "SELECT a.*, p.name AS pet_name, p.species, sp.shelter_name, sp.phone AS vet_phone
     FROM appointments a
     JOIN pets p                ON p.pet_id      = a.pet_id
     JOIN shelter_profiles sp ON sp.shelter_id = a.veterinary_id
     WHERE a.adopter_id = ?
       AND a.status IN ('SCHEDULED','CONFIRMED')
       AND a.appointment_date >= CURDATE()
     ORDER BY a.appointment_date ASC, a.appointment_time ASC
     LIMIT 1",
    [$user['user_id']]
);
$vetPhoneDigits = $upcomingAppt && !empty($upcomingAppt['vet_phone'])
    ? preg_replace('/[^0-9+]/', '', $upcomingAppt['vet_phone']) : '';
?>
<?php if ($upcomingAppt): ?>
<div class="card vd-upcoming" style="border-left:5px solid #0d9488; margin-top:20px;">
    <div class="card-title">
        <i class="fas fa-calendar-check"></i> Upcoming Appointment
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:14px;align-items:center;">
        <div style="flex:1 1 220px;">
            <div style="font-size:1.1rem;font-weight:800;color:#0f172a;">
                🐾 <?= sanitize($upcomingAppt['pet_name']) ?>
            </div>
            <div style="font-size:.85rem;color:#475569;margin-top:2px;">
                with <b><?= sanitize($upcomingAppt['shelter_name']) ?></b>
            </div>
            <div style="margin-top:8px;color:#0f172a;font-weight:700;">
                📅 <?= date('M j, Y', strtotime($upcomingAppt['appointment_date'])) ?>
            </div>
            <div style="color:#0f172a;font-weight:700;">
                ⏰ <?= date('g:i A', strtotime($upcomingAppt['appointment_time'])) ?>
            </div>
            <div style="color:#475569;font-size:.85rem;margin-top:4px;">
                <?= sanitize($upcomingAppt['appointment_type']) ?>
            </div>
        </div>
        <div style="text-align:center;">
            <span class="vd-status-pill vd-status-pill--<?= strtolower($upcomingAppt['status']) ?>">
                <?= sanitize($upcomingAppt['status']) ?>
            </span>
        </div>
    </div>
    <div class="vd-upcoming__actions" style="margin-top:14px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
        <span style="font-weight:800;font-size:.9rem;color:#0f766e;margin-right:4px;">Contact Vet now:</span>
        <a class="btn btn-primary btn-sm" href="tel:027772081">
            <i class="fas fa-phone"></i> (02) 777 2081
        </a>
        <a class="btn btn-outline btn-sm" href="https://www.facebook.com/paranaqueveterinaryservicesoffice" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-facebook"></i> Facebook
        </a>
        <a class="btn btn-outline btn-sm" href="mailto:veterinary@paranaque.gov.ph">
            <i class="fas fa-envelope"></i> veterinary@paranaque.gov.ph
        </a>
        <?php if ($vetPhoneDigits): ?>
            <a class="btn btn-outline btn-sm" href="tel:<?= htmlspecialchars($vetPhoneDigits) ?>">
                <i class="fas fa-phone"></i> Call Veterinary
            </a>
            <a class="btn btn-outline btn-sm" href="<?= APP_URL ?>/pages/adopter/messages.php?to=<?= (int)$upcomingAppt['veterinary_id'] ?>">
                <i class="fas fa-comment"></i> Message Veterinary
            </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="grid-2" style="margin-top:20px;">
    <div class="card">
        <div class="card-title"><i class="fas fa-file-alt"></i> Recent Applications</div>
        <?php if (empty($recentApps)): ?>
        <div class="empty-state" style="padding:30px 0">
            <div class="empty-icon">🐾</div>
            <p>No applications yet. <a href="<?= APP_URL ?>/pages/adopter/browse.php">Browse pets</a> to get started!</p>
        </div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Pet</th><th>Shelter</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($recentApps as $app): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <?php $photo = $app['pet_photo'] ? APP_URL.'/'.$app['pet_photo'] : APP_URL.'/assets/images/pet-placeholder.png'; ?>
                            <img src="<?= $photo ?>" class="pet-thumb" alt="<?= sanitize($app['pet_name']) ?>" onerror="this.src='<?= APP_URL ?>/assets/images/pet-placeholder.png'">
                            <div>
                                <div class="fw-bold"><?= sanitize($app['pet_name']) ?></div>
                                <div class="text-muted" style="font-size:.78rem"><?= sanitize($app['species']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= sanitize($app['shelter_name']) ?></td>
                    <td><?= statusBadge($app['status']) ?></td>
                    <td style="font-size:.82rem;color:var(--gray-mid)"><?= date('M j', strtotime($app['submitted_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><i class="fas fa-star"></i> Featured Pets</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <?php foreach ($featuredPets as $p):
                $photo = $p['primary_photo'] ? APP_URL.'/'.$p['primary_photo'] : APP_URL.'/assets/images/pet-placeholder.png';
            ?>
            <a href="<?= APP_URL ?>/pages/adopter/pet-detail.php?id=<?= $p['pet_id'] ?>" style="text-decoration:none;color:inherit;">
                <div class="pet-card" style="cursor:pointer">
                    <div class="pet-card-img" style="height:130px">
                        <img src="<?= $photo ?>" alt="<?= sanitize($p['name']) ?>" onerror="this.src='<?= APP_URL ?>/assets/images/pet-placeholder.png'" style="height:130px;object-fit:cover;width:100%">
                        <div class="pet-species-badge"><?= $p['species'] ?></div>
                    </div>
                    <div class="pet-card-body" style="padding:10px">
                        <div class="pet-card-name" style="font-size:.95rem"><?= sanitize($p['name']) ?></div>
                        <div class="pet-card-meta">
                            <span>🏠 <?= sanitize($p['shelter_city'] ?? '') ?></span>
                        </div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
            <?php if (empty($featuredPets)): ?>
            <div class="empty-state" style="grid-column:span 2;padding:20px">
                <p>No pets available right now.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="quick-panel">
    <div class="mini-card">
        <h3><i class="fas fa-heart"></i> My Recent Feedback</h3>
        <?php if (empty($recentFeedback)): ?>
            <p style="color:#64748b;margin:0">You have not submitted feedback yet. Share a good adoption experience so it can appear on the landing page stories tab.</p>
        <?php else: ?>
            <?php foreach ($recentFeedback as $item): ?>
                <div class="feed-item">
                    <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
                        <strong><?= sanitize($item['title']) ?></strong>
                        <span class="feed-stars"><?= str_repeat('★', max(0, min(5, (int) $item['rating']))) ?></span>
                    </div>
                    <div style="margin:6px 0;color:#475569"><?= sanitize($item['message']) ?></div>
                    <div class="feed-meta"><?= !empty($item['created_at']) ? date('M j, Y', strtotime($item['created_at'])) : '' ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="mini-card">
        <h3><i class="fas fa-bullhorn"></i> Quick Safety Tools</h3>
        <p style="margin:0 0 10px;color:#64748b">Use one clear action per need. This keeps the dashboard minimal and avoids duplicate buttons.</p>
        <div class="dash-note">
            <strong>Share Feedback</strong> is for positive adoption experiences.<br>
            <strong>Report a Concern</strong> is for complaints, abuse, suspicious users, or platform problems. Reports are sent to admins for review.
        </div>
    </div>
</div>

<div class="dash-modal" id="feedbackModal" aria-hidden="true">
    <div class="dash-modal-card feedback-modal-card">
        <div class="feedback-chip-row">
            <span class="feedback-chip"><i class="fas fa-heart"></i> Feedback</span>
        </div>
        <h2 class="feedback-title">Help us improve!</h2>
        <p class="feedback-subtitle">How would you describe your adoption experience with Paw-Adopt? Positive public feedback with 4 or 5 stars can appear on the landing page <strong>Stories</strong> tab.</p>
        <form id="feedbackForm">
            <input type="hidden" id="feedbackRating" name="rating" value="">
            <div class="feedback-rating-grid" role="radiogroup" aria-label="Feedback rating">
                <button type="button" class="feedback-rating-btn" data-rating="1" aria-pressed="false">
                    <span class="feedback-rating-emoji">😞</span>
                    <span class="feedback-rating-label">Poor</span>
                </button>
                <button type="button" class="feedback-rating-btn" data-rating="2" aria-pressed="false">
                    <span class="feedback-rating-emoji">😕</span>
                    <span class="feedback-rating-label">Fair</span>
                </button>
                <button type="button" class="feedback-rating-btn" data-rating="3" aria-pressed="false">
                    <span class="feedback-rating-emoji">👌</span>
                    <span class="feedback-rating-label">Good</span>
                </button>
                <button type="button" class="feedback-rating-btn" data-rating="4" aria-pressed="false">
                    <span class="feedback-rating-emoji">👍</span>
                    <span class="feedback-rating-label">Very Good</span>
                </button>
                <button type="button" class="feedback-rating-btn" data-rating="5" aria-pressed="false">
                    <span class="feedback-rating-emoji">🔥</span>
                    <span class="feedback-rating-label">Excellent</span>
                </button>
            </div>

            <div style="margin-bottom:14px">
                <label for="feedbackTitle" class="feedback-label">Give your feedback a short title</label>
                <input id="feedbackTitle" name="title" class="feedback-input" maxlength="120" placeholder="Happy adoption story" required>
            </div>

            <div>
                <label for="feedbackMessage" class="feedback-label">What's your overall experience?</label>
                <textarea id="feedbackMessage" name="message" class="feedback-textarea" maxlength="800" placeholder="Please share your thoughts..." required></textarea>
            </div>

            <label class="feedback-check"><input type="checkbox" name="is_public" value="1" checked> I allow Paw-Adopt to show this positive feedback on the public Stories tab. <a href="<?= APP_URL ?>/login.php" target="_blank" rel="noopener noreferrer">Read Privacy Policy.</a></label>
            <label class="feedback-check"><input type="checkbox" name="allow_contact" value="1"> I may be contacted about this feedback for follow-up.</label>

            <div class="feedback-actions">
                <button type="button" class="feedback-btn cancel" data-close-modal="feedbackModal">Cancel</button>
                <button type="submit" class="feedback-btn submit" id="feedbackSubmitBtn" disabled>Submit</button>
            </div>
        </form>
    </div>
</div>

<div class="dash-modal" id="reportModal" aria-hidden="true">
    <div class="dash-modal-card">
        <h2>Report a Concern</h2>
        <p>This sends a direct report to admins for review. Use this for abuse, suspicious activity, animal welfare concerns, or technical problems.</p>
        <form id="reportForm">
            <input type="hidden" name="source_page" value="adopter_dashboard">
            <div class="dash-grid">
                <div>
                    <label for="reportCategory" style="font-weight:800;font-size:.86rem">Category</label>
                    <select id="reportCategory" name="category" class="dash-select" required>
                        <option value="">Select category</option>
                        <option value="user_behavior">User Behavior</option>
                        <option value="animal_welfare">Animal Welfare</option>
                        <option value="fake_listing">Fake Listing</option>
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
                    <textarea id="reportMessage" name="details" class="dash-textarea" maxlength="1000" placeholder="Explain the issue clearly so admins can review it quickly." required></textarea>
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
    const feedbackModal = document.getElementById('feedbackModal');
    const reportModal = document.getElementById('reportModal');
    const feedbackForm = document.getElementById('feedbackForm');
    const reportForm = document.getElementById('reportForm');
    const endpoint = '<?= APP_URL ?>/api/feedback-report.php';
    const ratingInput = document.getElementById('feedbackRating');
    const ratingButtons = Array.from(document.querySelectorAll('.feedback-rating-btn'));
    const feedbackTitle = document.getElementById('feedbackTitle');
    const feedbackMessage = document.getElementById('feedbackMessage');
    const feedbackSubmitBtn = document.getElementById('feedbackSubmitBtn');

    function openModal(modal){
        if (!modal) return;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function closeModal(modal){
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }
    function notify(msg, type){
        if (typeof showToast === 'function') showToast(msg, type || 'success');
        else alert(msg);
    }
    function setRating(value){
        if (!ratingInput) return;
        ratingInput.value = value;
        ratingButtons.forEach(btn => {
            const active = btn.getAttribute('data-rating') === String(value);
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        syncFeedbackSubmit();
    }
    function syncFeedbackSubmit(){
        if (!feedbackSubmitBtn) return;
        const ready = !!(ratingInput?.value && feedbackTitle?.value.trim() && feedbackMessage?.value.trim());
        feedbackSubmitBtn.disabled = !ready;
    }

    document.getElementById('openFeedbackBtn')?.addEventListener('click', () => openModal(feedbackModal));
    document.getElementById('openReportBtn')?.addEventListener('click', () => openModal(reportModal));
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => closeModal(document.getElementById(btn.getAttribute('data-close-modal'))));
    });
    [feedbackModal, reportModal].forEach(modal => {
        modal?.addEventListener('click', (e) => { if (e.target === modal) closeModal(modal); });
    });
    ratingButtons.forEach(btn => btn.addEventListener('click', () => setRating(btn.getAttribute('data-rating') || '')));
    feedbackTitle?.addEventListener('input', syncFeedbackSubmit);
    feedbackMessage?.addEventListener('input', syncFeedbackSubmit);

    feedbackForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(feedbackForm);
        fd.set('action', 'submit_feedback');
        const submitBtn = feedbackForm.querySelector('[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
        try {
            const res = await fetch(endpoint, { method:'POST', body:fd });
            const text = await res.text();
            let data;
            try { data = JSON.parse(text); } catch (jsonError) { throw new Error('Server returned HTML instead of JSON. Check /api/feedback-report.php'); }
            if (!data.success) throw new Error(data.message || 'Feedback submission failed.');
            notify(data.message || 'Feedback submitted!');
            feedbackForm.reset();
            setRating('');
            closeModal(feedbackModal);
            setTimeout(() => window.location.reload(), 700);
        } catch (err) {
            notify(err.message || 'Feedback submission failed.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Feedback';
        }
    });

    reportForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(reportForm);
        fd.set('action', 'submit_report');
        const submitBtn = reportForm.querySelector('[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';
        try {
            const res = await fetch(endpoint, { method:'POST', body:fd });
            const text = await res.text();
            let data;
            try { data = JSON.parse(text); } catch (jsonError) { throw new Error('Server returned HTML instead of JSON. Check /api/feedback-report.php'); }
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

    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
            closeModal(feedbackModal);
            closeModal(reportModal);
        }
    });
    syncFeedbackSubmit();
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>