<?php
require_once __DIR__ . '/../../includes/functions.php';
requireRole('SHELTER');
$pageTitle = 'Edit Pet';
$user  = getCurrentUser();
$db    = Database::getInstance();
$petId = (int)($_GET['id'] ?? 0);
if (!$petId) { redirect(APP_URL.'/pages/shelter/pets.php'); }

$pet = $db->fetch("SELECT * FROM pets WHERE pet_id = ? AND shelter_id = ?", [$petId, $user['user_id']]);
if (!$pet) { flashMessage('error','Pet not found.'); redirect(APP_URL.'/pages/shelter/pets.php'); }

$photos = $db->fetchAll("SELECT * FROM pet_photos WHERE pet_id = ? ORDER BY is_primary DESC", [$petId]);
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="icon">✏️</span> Edit <?= sanitize($pet['name']) ?></h1>
    <a href="<?= APP_URL ?>/pages/shelter/pets.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<div style="max-width:700px;margin:0 auto">
<div class="card">
<form id="editPetForm" enctype="multipart/form-data" onsubmit="event.preventDefault();submitEditPet(this,<?= $petId ?>)">

    <div class="grid-2" style="gap:16px">
        <div class="form-group">
            <label class="form-label">Pet Name *</label>
            <input type="text" name="name" class="form-control" value="<?= sanitize($pet['name']) ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <?php foreach (['Available','Pending','Adopted'] as $s): ?>
                <option value="<?= $s ?>" <?= $pet['status'] === $s ? 'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="grid-2" style="gap:16px">
        <div class="form-group">
            <label class="form-label">Species</label>
            <select name="species" class="form-control">
                <?php foreach (['Dog','Cat'] as $s): ?>
                <option value="<?= $s ?>" <?= $pet['species'] === $s ? 'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Breed</label>
            <input type="text" name="breed" class="form-control" value="<?= sanitize($pet['breed']) ?>">
        </div>
    </div>

    <div class="grid-2" style="gap:16px">
        <div class="form-group">
            <label class="form-label">Age (months)</label>
            <input type="number" name="age_months" class="form-control" value="<?= (int)$pet['age_months'] ?>" min="0">
        </div>
        <div class="form-group">
            <label class="form-label">Gender</label>
            <select name="sex" class="form-control">
                <?php foreach (['Male','Female','Unknown'] as $s): ?>
                <option value="<?= $s ?>" <?= $pet['sex'] === $s ? 'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="grid-2" style="gap:16px">
        <div class="form-group">
            <label class="form-label">Size</label>
            <select name="size" class="form-control">
                <?php foreach (['Tiny','Small','Medium','Large','Giant'] as $s): ?>
                <option value="<?= $s ?>" <?= $pet['size'] === $s ? 'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Color</label>
            <input type="text" name="color" class="form-control" value="<?= sanitize($pet['color']) ?>">
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Temperament</label>
        <input type="text" name="temperament" class="form-control" value="<?= sanitize($pet['temperament']) ?>">
    </div>
    <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="4"><?= sanitize($pet['description']) ?></textarea>
    </div>

    <!-- ADDED: Medical Notes Section with PDF Upload -->
    <div class="form-group">
        <label class="form-label">Medical Description and Files</label>
        <textarea name="medical_notes" class="form-control" rows="2" placeholder="Vaccinations, spay/neuter status, medical conditions..."><?= sanitize($pet['medical_notes']) ?></textarea>
        
        <div class="upload-wrapper" style="margin-top:10px">
            <label for="medical_cert" class="custom-file-upload">
                <i class="fas fa-paw"></i> UPLOAD
            </label>
            <input type="file" id="medical_cert" name="medical_cert" accept=".pdf" class="file-input-hidden" onchange="updateFileName(this)">
            <span class="file-name-display">
                <?= $pet['medical_certificate'] ? 'Current: ' . basename($pet['medical_certificate']) : 'No medical certificate chosen (Optional PDF)' ?>
            </span>
        </div>
    </div>

    <!-- Existing Photos -->
    <?php if (!empty($photos)): ?>
    <div class="form-group">
        <label class="form-label">Current Photos</label>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <?php foreach ($photos as $ph): ?>
            <div style="position:relative">
                <img src="<?= APP_URL.'/'.$ph['photo_url'] ?>" style="width:80px;height:80px;object-fit:cover;border-radius:10px;border:2px solid <?= $ph['is_primary']?'var(--teal)':'var(--gray-light)' ?>" onerror="this.src='<?= APP_URL ?>/assets/images/pet-placeholder.png'">
                <?php if ($ph['is_primary']): ?><div style="text-align:center;font-size:.65rem;color:var(--teal);font-weight:700">Primary</div><?php endif; ?>
                <button type="button" onclick="deletePhoto(<?= $ph['photo_id'] ?>,this)" style="position:absolute;top:-6px;right:-6px;background:var(--danger);color:#fff;border:none;border-radius:50%;width:20px;height:20px;cursor:pointer;font-size:.7rem;display:flex;align-items:center;justify-content:center">×</button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="form-group">
        <label class="form-label">Add More Photos</label>
        <div class="upload-wrapper">
            <label for="photos" class="custom-file-upload">
                <i class="fas fa-paw"></i> UPLOAD
            </label>
            <input type="file" id="photos" name="photos[]" multiple accept="image/*" class="file-input-hidden" onchange="updateFileName(this)">
            <span class="file-name-display">No new photos chosen</span>
        </div>
    </div>

    <div style="display:flex;gap:12px;margin-top:20px">
        <button type="submit" class="btn btn-primary flex-1" id="editBtn"><i class="fas fa-save"></i> Save Changes</button>
        <a href="<?= APP_URL ?>/pages/shelter/pets.php" class="btn btn-secondary">Cancel</a>
    </div>
</form>
</div>
</div>

<script>
// Helper to update file names on display
function updateFileName(input) {
    const display = input.parentElement.querySelector('.file-name-display');
    if (input.files && input.files.length > 0) {
        display.textContent = input.files.length > 1 ? input.files.length + " files chosen" : input.files[0].name;
    } else {
        display.textContent = "No file chosen";
    }
}

async function deletePhoto(photoId, btn) {
    confirmAction('Remove this photo?', async () => {
        const res = await apiRequest('/PAWAdopt/api/pets.php?action=delete_photo&photo_id='+photoId);
        if (res.success) { btn.closest('div[style]').remove(); showToast('Photo removed.'); }
        else showToast(res.message,'error');
    });
}

async function submitEditPet(form, petId) {
    const fd = new FormData(form);
    fd.append('action','edit');
    fd.append('pet_id', petId);
    
    const btn = document.getElementById('editBtn');
    const originalContent = btn.innerHTML;
    btn.disabled = true; 
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

    try {
        const res = await fetch('/PAWAdopt/api/pets.php?action=edit', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) { 
            showToast('Pet updated! 🐾'); 
            setTimeout(()=>window.location.href='/PAWAdopt/pages/shelter/pets.php', 1000); 
        } else {
            showToast(data.message || 'Update failed.', 'error');
        }
    } catch (err) {
        showToast("Network error occurred.", "error");
    } finally {
        btn.disabled = false; 
        btn.innerHTML = originalContent;
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
