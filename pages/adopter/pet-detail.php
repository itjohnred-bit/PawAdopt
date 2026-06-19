<?php
require_once __DIR__ . '/../../includes/functions.php';
requireRole('ADOPTER');

$petId = (int)($_GET['id'] ?? 0);
if (!$petId) { redirect(APP_URL . '/pages/adopter/browse.php'); }

$db   = Database::getInstance();
$user = getCurrentUser();

$pet = $db->fetch(
    "SELECT p.*, sp.shelter_name, sp.city as shelter_city, sp.phone as shelter_phone,
            sp.description as shelter_desc, sp.is_verified
     FROM pets p
     JOIN shelter_profiles sp ON p.shelter_id = sp.shelter_id
     WHERE p.pet_id = ? AND p.status != 'Removed'",
    [$petId]
);
if (!$pet) { flashMessage('error', 'Pet not found.'); redirect(APP_URL . '/pages/adopter/browse.php'); }

$photos = $db->fetchAll("SELECT * FROM pet_photos WHERE pet_id = ? ORDER BY is_primary DESC", [$petId]);
$mainPhoto = !empty($photos) ? APP_URL.'/'.$photos[0]['photo_url'] : APP_URL.'/assets/images/pet-placeholder.png';

$isFav = (bool)$db->fetch("SELECT favorite_id FROM favorites WHERE adopter_id = ? AND pet_id = ?", [$user['user_id'], $petId]);

$existingApp = $db->fetch(
    "SELECT status FROM adoption_applications WHERE pet_id = ? AND adopter_id = ? AND status NOT IN ('Cancelled','Rejected')",
    [$petId, $user['user_id']]
);

$pageTitle = sanitize($pet['name']);
include __DIR__ . '/../../includes/header.php';
?>

<div style="margin-bottom:16px">
    <a href="<?= APP_URL ?>/pages/adopter/browse.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Browse</a>
</div>

<div class="pet-detail-layout">
    <div>
        <div class="pet-gallery">
            <div class="pet-gallery-main">
                <img src="<?= $mainPhoto ?>" alt="<?= sanitize($pet['name']) ?>" id="galleryMain" onerror="this.src='<?= APP_URL ?>/assets/images/pet-placeholder.png'">
            </div>
            <?php if (count($photos) > 1): ?>
            <div class="pet-gallery-thumbs">
                <?php foreach ($photos as $i => $ph):
                    $url = APP_URL.'/'.$ph['photo_url'];
                ?>
                <img src="<?= $url ?>" alt="" class="<?= $i === 0 ? 'active' : '' ?>"
                     onclick="switchGalleryImage('<?= $url ?>',this)" loading="lazy"
                     onerror="this.src='<?= APP_URL ?>/assets/images/pet-placeholder.png'">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="card mt-3">
            <div class="card-title"><i class="fas fa-info-circle"></i> About <?= sanitize($pet['name']) ?></div>
            <p style="line-height:1.7;color:var(--gray-dark)"><?= nl2br(sanitize($pet['description'] ?: 'No description provided.')) ?></p>

            <?php if ($pet['temperament']): ?>
            <div class="mt-2">
                <strong style="color:var(--teal-dark)">Temperament:</strong>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
                    <?php foreach (explode(',', $pet['temperament']) as $t): ?>
                    <span class="badge badge-primary"><?= trim(sanitize($t)) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($pet['medical_notes'] || $pet['medical_certificate']): ?>
            <div class="mt-2">
                <strong style="color:var(--teal-dark)">Medical Information:</strong>
                <?php if ($pet['medical_notes']): ?>
                    <p style="margin-top:6px;color:var(--gray-dark);margin-bottom:10px"><?= sanitize($pet['medical_notes']) ?></p>
                <?php endif; ?>
                <?php if ($pet['medical_certificate']): ?>
                    <div style="background:var(--teal-xlight); border:1px dashed var(--teal); border-radius:10px; padding:12px; display:flex; align-items:center; justify-content:space-between; margin-top:10px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <i class="fas fa-file-pdf" style="font-size:1.5rem; color:#e74c3c;"></i>
                            <div>
                                <div style="font-weight:700; font-size:0.85rem; color:var(--gray-dark);">Medical Certificate</div>
                                <div style="font-size:0.75rem; color:var(--gray-mid);">Verified PDF Document</div>
                            </div>
                        </div>
                        <a href="<?= APP_URL . '/' . $pet['medical_certificate'] ?>" target="_blank" class="btn btn-primary btn-sm">
                            <i class="fas fa-eye"></i> View Certificate
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="card mb-3">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px">
                <div>
                    <h2 style="font-size:1.8rem;font-weight:900;color:var(--teal-dark)"><?= sanitize($pet['name']) ?></h2>
                    <div style="color:var(--gray-mid);font-size:.9rem"><?= htmlspecialchars($pet['breed'] ?: $pet['species']) ?></div>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                    <?= statusBadge($pet['status']) ?>
                    <button class="btn btn-outline btn-sm pet-fav-btn <?= $isFav ? 'active' : '' ?>"
                            onclick="toggleFavorite(<?= $petId ?>,this)" style="position:static;width:auto;height:auto;border-radius:99px">
                        <?= $isFav ? '❤️ Saved' : '🤍 Save' ?>
                    </button>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <?php $details = [
                    ['🐾','Species',$pet['species']],
                    ['📅','Age',formatAge($pet['age_months'])],
                    ['⚥','Gender',$pet['sex']],
                    ['📏','Size',$pet['size']],
                    ['🎨','Color',$pet['color'] ?: 'N/A'],
                ]; ?>
                <?php foreach ($details as [$icon,$label,$val]): ?>
                <div style="background:var(--teal-xlight);border-radius:12px;padding:12px">
                    <div style="font-size:1.4rem;margin-bottom:4px"><?= $icon ?></div>
                    <div style="font-size:.72rem;color:var(--gray-mid);text-transform:uppercase;font-weight:700"><?= $label ?></div>
                    <div style="font-weight:800;color:var(--gray-dark)"><?= sanitize($val) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-title"><i class="fas fa-building"></i> Posted By</div>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
                <div class="avatar-circle" style="background:var(--teal-light);color:var(--teal-dark)">🏠</div>
                <div>
                    <div class="fw-bold"><?= sanitize($pet['shelter_name']) ?>
                        <?php if ($pet['is_verified']): ?><span class="badge badge-success" style="margin-left:4px">✓ Verified</span><?php endif; ?>
                    </div>
                    <?php if ($pet['shelter_city']): ?>
                    <div class="text-muted" style="font-size:.82rem"><i class="fas fa-map-marker-alt"></i> <?= sanitize($pet['shelter_city']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <button onclick="startMessage(<?= $pet['shelter_id'] ?>,<?= $petId ?>)" class="btn btn-outline btn-sm btn-block">
                <i class="fas fa-comment"></i> Message Shelter
            </button>
        </div>

        <?php if ($pet['status'] === 'Available'): ?>
        <div class="card">
            <div class="card-title"><i class="fas fa-heart"></i> Adopt <?= sanitize($pet['name']) ?></div>
            <?php if ($existingApp): ?>
            <div style="background:var(--teal-xlight);border-radius:12px;padding:16px;text-align:center">
                <div style="font-size:2rem;margin-bottom:8px">📋</div>
                <div class="fw-bold">Application <?= $existingApp['status'] ?></div>
                <div class="text-muted" style="font-size:.84rem;margin-top:4px">You already applied to adopt <?= sanitize($pet['name']) ?>!</div>
                <a href="<?= APP_URL ?>/pages/adopter/applications.php" class="btn btn-primary btn-sm mt-2">View Application</a>
            </div>
            <?php else: ?>
            <p style="font-size:.9rem;color:var(--gray-mid);margin-bottom:16px">Ready to give <?= sanitize($pet['name']) ?> a forever home? Submit your adoption application!</p>
            <button onclick="openModal('adoptModal')" class="btn btn-primary btn-block btn-lg">
                <i class="fas fa-paw"></i> Apply to Adopt
            </button>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="card">
            <div style="text-align:center;padding:16px;color:var(--gray-mid)">
                <div style="font-size:2.5rem">😢</div>
                <div class="fw-bold mt-1"><?= sanitize($pet['name']) ?> is <?= strtolower($pet['status']) ?></div>
                <div style="font-size:.84rem;margin-top:4px">This pet is no longer available for adoption.</div>
                <a href="<?= APP_URL ?>/pages/adopter/browse.php" class="btn btn-primary btn-sm mt-2">Find Another Pet</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="adoptModal" class="modal">
    <div class="modal-content" style="max-width: 850px; width: 95%; height: 90vh; display: flex; flex-direction: column;">
        <div class="modal-header">
            <h2 class="modal-title">🐾 Adoption Application: <?= sanitize($pet['name']) ?></h2>
            <button class="modal-close" onclick="closeModal('adoptModal')">&times;</button>
        </div>

        <div class="wizard-progress" style="padding: 20px; background: #f8f9fa; border-bottom: 1px solid #eee;">
            <div class="progress-steps" style="display: flex; justify-content: space-between; position: relative;">
                <?php for($i=1; $i<=7; $i++): ?>
                    <div class="step-indicator" id="step-ind-<?= $i ?>" style="z-index: 2; width: 30px; height: 30px; border-radius: 50%; background: #ddd; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.8rem;">
                        <?= $i ?>
                    </div>
                <?php endfor; ?>
                <div style="position: absolute; top: 15px; left: 0; width: 100%; height: 2px; background: #eee; z-index: 1;"></div>
            </div>
            <div id="step-title" style="text-align: center; margin-top: 10px; font-weight: 700; color: var(--teal); font-size: 0.9rem;">Section 1: Applicant Information</div>
        </div>

        <form id="adoptionWizardForm" style="flex: 1; overflow-y: auto; padding: 30px;">
            <input type="hidden" name="pet_id" value="<?= $pet['pet_id'] ?>">
            <input type="hidden" name="action" value="submit">
            
            <div class="wizard-section active" id="section-1">
                <div class="form-group">
                    <label>Applicant Type</label>
                    <select name="app_type" class="form-control" onchange="toggleAppType(this.value)">
                        <option value="Individual">Individual</option>
                        <option value="Institution">Group / Institution</option>
                    </select>
                </div>
                <div id="individual-fields">
                    <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group"><label>Full Name</label><input type="text" name="full_name" class="form-control" value="<?= sanitize($user['username']) ?>"></div>
                        <div class="form-group">
                            <label>Sex</label>
                            <select name="sex" class="form-control"><option>Male</option><option>Female</option><option>Prefer not to say</option></select>
                        </div>
                    </div>
                    <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="dob" class="form-control" max="2026-12-31" oninput="if(this.value.split('-')[0]?.length > 4) this.value = ''">
                        </div>
                        <div class="form-group">
                            <label>Civil Status</label>
                            <select name="civil_status" class="form-control">
                                <option>Single</option>
                                <option>Married</option>
                                <option>Widowed</option>
                                <option>Separated</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wizard-section" id="section-2" style="display:none;">
                <div class="form-group"><label>Complete Address</label><textarea name="address" class="form-control" rows="3" placeholder="Unit, Street, Barangay, City, Province"></textarea></div>
                <div class="form-group">
                    <label>Residence Status</label>
                    <select name="residence_status" class="form-control"><option>Owned</option><option>Rented</option><option>Leased</option><option>Mortgaged</option></select>
                </div>
                <div class="form-group"><label>Mobile Number</label><input type="text" name="phone" class="form-control"></div>
            </div>

            <div class="wizard-section" id="section-3" style="display:none;">
                <div class="form-group">
                    <label>Average Monthly Income</label>
                    <select name="income" class="form-control">
                        <option>₱15,000–30,000</option>
                        <option>₱31,000–60,000</option>
                        <option>₱61,000–90,000</option>
                        <option>₱91,000+</option>
                    </select>
                </div>
                <div class="form-group"><label>Source of Income</label><input type="text" name="income_source" class="form-control"></div>
            </div>

            <div class="wizard-section" id="section-4" style="display:none;">
                <div class="form-group">
                    <label>Have you ever owned a pet before?</label>
                    <div style="display:flex; gap:20px;"><label><input type="radio" name="owned_before" value="Yes"> Yes</label><label><input type="radio" name="owned_before" value="No"> No</label></div>
                </div>
                <div class="form-group">
                    <label>What do you do when your pet gets sick?</label>
                    <select name="sick_policy" class="form-control">
                        <option>Bring to Veterinarian</option>
                        <option>Self-medicate</option>
                        <option>Consult Online</option>
                    </select>
                </div>
            </div>

            <div class="wizard-section" id="section-5" style="display:none;">
                <div class="form-group">
                    <label>Do you currently own pets?</label>
                    <select name="has_current_pets" class="form-control" onchange="toggleCurrentPets(this.value)">
                        <option value="No">No</option>
                        <option value="Yes">Yes</option>
                    </select>
                </div>
                <div id="current-pet-details" style="display:none;">
                    <textarea name="current_pet_info" class="form-control" placeholder="Please list your current pets and their vaccination status..."></textarea>
                </div>
            </div>

            <div class="wizard-section" id="section-6" style="display:none;">
                <div class="form-group">
                    <label>Do you currently have a veterinarian?</label>
                    <select name="has_vet" class="form-control"><option>No</option><option>Yes</option></select>
                </div>
                <div class="form-group"><label>Vet Clinic Name (If any)</label><input type="text" name="vet_clinic" class="form-control"></div>
            </div>

            <div class="wizard-section" id="section-7" style="display:none;">
                <div style="background: #e6f2ee; padding: 20px; border-radius: 10px; text-align: center;">
                    <h3>Ready to Submit?</h3>
                    <p>By clicking submit, you agree that adopted pets should be treated as family members and you provide permission for follow-up visits.</p>
                    <div class="form-group">
                        <label>Why do you want to adopt <?= sanitize($pet['name']) ?>?</label>
                        <textarea name="why_adopt" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
            </div>
        </form>

        <div class="modal-footer" style="display: flex; justify-content: space-between;">
            <button type="button" class="btn btn-secondary" id="prevBtn" onclick="changeStep(-1)" style="display:none;">Previous</button>
            <button type="button" class="btn btn-primary" id="nextBtn" onclick="changeStep(1)">Next Section</button>
        </div>
    </div>
</div>

<script>
// Use safe window context properties to avoid Duplicate Identifier declaration errors
window.currentStep = window.currentStep || 1;
window.totalSteps = 7;
const petId = <?= $petId ?>;
const storageKey = `screening_progress_${petId}`;

window.stepTitles = [
    "Section 1: Applicant Information",
    "Section 2: Address & Residence",
    "Section 3: Financial Background",
    "Section 4: Pet History",
    "Section 5: Current Pets Status",
    "Section 6: Veterinary Information",
    "Section 7: Review & Submit"
];

document.addEventListener("DOMContentLoaded", () => {
    updateStepUI();
    
    const form = document.getElementById("adoptionWizardForm");
    if (!form) return;

    const savedData = localStorage.getItem(storageKey);
    if (savedData) {
        try {
            const data = JSON.parse(savedData);
            Object.keys(data).forEach(key => {
                const field = form.elements[key];
                if (field) {
                    if (field instanceof RadioNodeList || field.type === "radio") {
                        const target = Array.from(form.elements[key]).find(el => el.value === data[key]);
                        if (target) target.checked = true;
                    } else {
                        field.value = data[key];
                    }
                }
            });
            
            if (data.app_type) toggleAppType(data.app_type);
            if (data.has_current_pets) toggleCurrentPets(data.has_current_pets);
        } catch (e) {
            console.error("Error formatting saved progress data:", e);
        }
    }

    form.addEventListener("input", () => {
        const formData = new FormData(form);
        const dataObject = {};
        formData.forEach((value, key) => {
            if (key !== 'action' && key !== 'pet_id') {
                dataObject[key] = value;
            }
        });
        localStorage.setItem(storageKey, JSON.stringify(dataObject));
    });
});

function changeStep(stepDirection) {
    if (stepDirection === 1 && window.currentStep === window.totalSteps) {
        const form = document.getElementById("adoptionWizardForm");
        submitAdoptionApp(petId, form);
        return;
    }

    document.getElementById(`section-${window.currentStep}`).style.display = "none";
    window.currentStep += stepDirection;
    
    if (window.currentStep < 1) window.currentStep = 1;
    if (window.currentStep > window.totalSteps) window.currentStep = window.totalSteps;

    document.getElementById(`section-${window.currentStep}`).style.display = "block";
    updateStepUI();
}

function updateStepUI() {
    document.getElementById("step-title").innerText = window.stepTitles[window.currentStep - 1];
    
    for (let i = 1; i <= window.totalSteps; i++) {
        const indicator = document.getElementById(`step-ind-${i}`);
        if (indicator) {
            if (i < window.currentStep) {
                indicator.style.background = "var(--teal-dark)";
            } else if (i === window.currentStep) {
                indicator.style.background = "var(--teal)";
            } else {
                indicator.style.background = "#ddd";
            }
        }
    }

    document.getElementById("prevBtn").style.display = window.currentStep === 1 ? "none" : "block";
    document.getElementById("nextBtn").innerText = window.currentStep === window.totalSteps ? "Submit Application 🐾" : "Next Section";
}

function toggleAppType(val) {
    const fields = document.getElementById("individual-fields");
    if (fields) fields.style.display = val === "Institution" ? "none" : "block";
}

function toggleCurrentPets(val) {
    const details = document.getElementById("current-pet-details");
    if (details) details.style.display = val === "Yes" ? "block" : "none";
}

async function apiRequest(url, method = 'GET', body = null) {
    try {
        const options = { method, headers: {} };
        if (body) {
            if (body instanceof URLSearchParams) {
                options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                options.body = body.toString();
            } else {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(body);
            }
        }
        const response = await fetch(url, options);
        if (!response.ok) throw new Error('Network error');
        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        return { success: false, message: 'Connection failed.' };
    }
}

async function startMessage(shelterId, petId) {
    const res = await apiRequest('<?= APP_URL ?>/api/messages.php', 'POST', new URLSearchParams({
        action: 'start', shelter_id: shelterId, pet_id: petId
    }));
    if (res.success) {
        window.location.href = '<?= APP_URL ?>/pages/adopter/messages.php?conv=' + res.data.conversation_id;
    } else {
        alert(res.message || 'Error starting conversation.');
    }
}

async function toggleFavorite(petId, btn) {
    const res = await apiRequest('<?= APP_URL ?>/api/favorites.php', 'POST', new URLSearchParams({
        action: 'toggle', pet_id: petId
    }));
    if (res.success) {
        btn.classList.toggle('active');
        btn.innerHTML = btn.classList.contains('active') ? '❤️ Saved' : '🤍 Save';
    } else {
        alert(res.message || 'Error toggling favorite.');
    }
}

async function submitAdoptionApp(petId, form) {
    const formData = new FormData(form);
    const params = new URLSearchParams();
    
    formData.forEach((value, key) => {
        params.append(key, value);
    });

    const res = await apiRequest('<?= APP_URL ?>/api/applications.php', 'POST', params);
    if (res.success) {
        localStorage.removeItem(storageKey);
        alert('Application submitted cleanly! 🐾');
        window.location.reload();
    } else {
        alert(res.message || 'Error submitting application.');
    }
}

function openModal(id) { 
    const modal = document.getElementById(id);
    if (modal) modal.style.display = 'block';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none'; 
}

function switchGalleryImage(url, img) {
    document.getElementById('galleryMain').src = url;
    document.querySelectorAll('.pet-gallery-thumbs img').forEach(i => i.classList.remove('active'));
    img.classList.add('active');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>