<?php
require_once __DIR__ . '/../../includes/functions.php';
requireRole('SHELTER');
$pageTitle = 'Add New Pet';
$user = getCurrentUser();
$db   = Database::getInstance();

// Check profile
$profile = $db->fetch("SELECT * FROM shelter_profiles WHERE shelter_id = ?", [$user['user_id']]);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="icon">➕</span> Add New Pet Listing</h1>
    <a href="<?= APP_URL ?>/pages/shelter/pets.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<div style="max-width:700px;margin:0 auto">
<div class="card">
<form id="addPetForm" enctype="multipart/form-data" onsubmit="event.preventDefault();submitAddPet(this)">

    <div class="grid-2" style="gap:16px">
        <div class="form-group">
            <label class="form-label">Pet Name *</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Buddy" required>
        </div>
        <div class="form-group">
            <label class="form-label">Species *</label>
            <select name="species" class="form-control" required>
                <?php foreach (['Dog','Cat'] as $s): ?>
                <option value="<?= $s ?>"><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="grid-2" style="gap:16px">
        <div class="form-group">
            <label class="form-label">Breed</label>
            <input type="text" name="breed" class="form-control" placeholder="e.g. Golden Retriever">
        </div>
        <div class="form-group">
            <label class="form-label">Age (months) *</label>
            <input type="number" name="age_months" class="form-control" min="0" max="300" value="12" required>
        </div>
    </div>

    <div class="grid-2" style="gap:16px">
        <div class="form-group">
            <label class="form-label">Gender</label>
            <select name="sex" class="form-control">
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Unknown">Unknown</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Size</label>
            <select name="size" class="form-control">
                <?php foreach (['Tiny','Small','Medium','Large','Giant'] as $s): ?>
                <option value="<?= $s ?>" <?= $s === 'Medium' ? 'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Color / Markings</label>
        <input type="text" name="color" class="form-control" placeholder="e.g. Golden, Black & White">
    </div>

    <div class="form-group">
        <label class="form-label">Temperament <span class="form-hint">(comma-separated)</span></label>
        <input type="text" name="temperament" class="form-control" placeholder="e.g. Friendly, Playful, Gentle">
    </div>

    <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="4" placeholder="Tell adopters about this pet's personality, backstory, and what makes them special..."></textarea>
    </div>

    <!-- NEW: Medical Notes with PDF Certificate Upload -->
    <div class="form-group">
        <label class="form-label">Medical Description and Files</label>
        <textarea name="medical_notes" class="form-control" rows="2" placeholder="Vaccinations, spay/neuter status, medical conditions..."></textarea>
        
        <div class="upload-wrapper" style="margin-top:10px">
            <label for="medical_cert" class="custom-file-upload">
                <i class="fas fa-paw"></i> UPLOAD
            </label>
            <input type="file" id="medical_cert" name="medical_cert" accept=".pdf" class="file-input-hidden" onchange="updateFileName(this)">
            <span class="file-name-display">No medical certificate chosen (Optional PDF)</span>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Photos <span class="form-hint">(First image will be primary)</span></label>
        <div class="upload-wrapper">
            <label for="photos" class="custom-file-upload">
                <i class="fas fa-paw"></i> UPLOAD
            </label>
            <input type="file" id="photos" name="photos[]" multiple accept="image/*" class="file-input-hidden" onchange="handlePhotoChange(this)">
            <span class="file-name-display">No photos chosen</span>
        </div>
        <div class="form-hint">Upload up to 5 photos. JPG, PNG, WebP. Max 5MB each.</div>
        <div id="photoPreview" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px"></div>
    </div>

    <div style="background:var(--teal-xlight);border-radius:12px;padding:14px;font-size:.85rem;color:var(--teal-dark);margin-bottom:20px">
        <i class="fas fa-info-circle"></i> Your listing will be visible to adopters immediately. Make sure all information is accurate!
    </div>

    <button type="submit" class="btn btn-primary btn-block btn-lg" id="submitBtn">
        <i class="fas fa-paw"></i> Post Pet Listing
    </button>
</form>
</div>
</div>

<script>
function updateFileName(input) {
    const display = input.parentElement.querySelector('.file-name-display');
    if (input.files && input.files[0]) {
        display.textContent = input.files[0].name;
    } else {
        display.textContent = "No file chosen";
    }
}

function handlePhotoChange(input) {
    updateFileName(input);
    const preview = document.getElementById('photoPreview');
    preview.innerHTML = '';
    
    [...input.files].slice(0,5).forEach((f, i) => {
        const reader = new FileReader();
        reader.onload = e => {
            const wrap = document.createElement('div');
            wrap.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:2px';
            
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = 'width:80px;height:80px;object-fit:cover;border-radius:10px;border:2px solid '+(i===0?'var(--teal)':'var(--gray-light)');
            
            const label = document.createElement('div');
            label.style.cssText = 'text-align:center;font-size:.7rem;color:var(--gray-mid)';
            label.textContent = i === 0 ? 'Primary' : '';
            
            wrap.appendChild(img); 
            wrap.appendChild(label);
            preview.appendChild(wrap);
        };
        reader.readAsDataURL(f);
    });
}

async function submitAddPet(form) {
    const fd  = new FormData(form);

    
    const btn = document.getElementById('submitBtn');
    const originalContent = btn.innerHTML;
    
    btn.disabled = true; 
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting…';

    try {
        const res = await fetch('/PAWAdopt/api/pets.php?action=add', { method:'POST', body: fd });
        
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch(e) {
            console.error("Server Error:", text);
            showToast("Server error. Check console.", "error");
            return;
        }

        if (data.success) {
            showToast('Pet listing created! 🐾');
            setTimeout(() => window.location.href = '/PAWAdopt/pages/shelter/pets.php', 1200);
        } else {
            showToast(data.message || 'Failed to create listing.', 'error');
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
