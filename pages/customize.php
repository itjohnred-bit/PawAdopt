<?php
/**
 * PawAdopt — Customize UI (simplified).
 *
 * Exactly six controls, saved per user in user_preferences:
 *   1. Background colour
 *   2. Background image (upload) + fit
 *   3. Radial blur (on/off + strength)
 *   4. Font family
 *   5. Large text
 *   6. Header and sidebar colour + texture
 *
 * All values are re-validated server-side in api/ui-settings.php (hex regex,
 * enum allowlists, numeric ranges, local-upload-only image path) and the
 * upload itself in api/ui-background.php (magic bytes, re-encode, 4 MB cap).
 * Ownership is enforced by `WHERE user_id = <session user>`.
 */

require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user  = getCurrentUser();
$db    = Database::getInstance();
$csrf  = generateCsrfToken();
$pageTitle = 'Customize UI';

$s = [
    'bg_color' => '#134E43', 'bg_image_url' => '', 'bg_blur' => 0,
    'bg_blur_strength' => 14, 'bg_image_fit' => 'cover', 'font_family' => 'Nunito',
    'large_text' => 0, 'header_color' => '#FFFFFF', 'header_texture' => 'none',
    'sidebar_color' => '#FFFFFF', 'sidebar_texture' => 'none',
];
try {
    $row = $db->fetch('SELECT * FROM user_preferences WHERE user_id = ?', [(int) $user['user_id']]);
    if ($row) {
        foreach ($s as $k => $v) {
            if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') { $s[$k] = $row[$k]; }
        }
    }
} catch (Throwable $e) {
    // migration not run yet — the form still renders with defaults
}
$bgImage = (string) ($s['bg_image_url'] ?? '');
$bgImageUrl = preg_match('#^uploads/ui/[a-f0-9]{32}\.(jpg|png|webp)$#', $bgImage) ? APP_URL . '/' . $bgImage : '';

include __DIR__ . '/../includes/header.php';
?>
<style>
.cz-wrap{max-width:820px;margin:0 auto;}
.cz-card{background:#fff;border:1px solid #e6efeb;border-radius:18px;padding:22px;margin-bottom:18px;box-shadow:0 8px 22px rgba(15,118,110,.06);}
.cz-card h2{margin:0 0 4px;font-size:1rem;font-weight:900;color:#134e4a;display:flex;align-items:center;gap:8px;}
.cz-card p.cz-sub{margin:0 0 16px;font-size:.8rem;color:#6b7280;}
.cz-field{margin-bottom:14px;}
.cz-field > label{display:block;font-size:.72rem;font-weight:900;letter-spacing:.05em;text-transform:uppercase;color:#4b5563;margin-bottom:6px;}
.cz-field select,.cz-field input[type=text]{width:100%;box-sizing:border-box;border:1px solid #d6e3df;border-radius:12px;padding:11px 13px;font:inherit;background:#fcfefe;}
.cz-inline{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.cz-swatch{width:52px;height:40px;padding:0;border:1px solid #d6e3df;border-radius:10px;background:#fff;cursor:pointer;}
.cz-range{flex:1;min-width:160px;}
.cz-toggle{display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid #e6efeb;border-radius:12px;padding:11px 14px;background:#fcfefe;}
.cz-toggle span{font-size:.88rem;font-weight:700;color:#1f2937;}
.cz-toggle small{display:block;font-weight:500;color:#6b7280;font-size:.75rem;}
.cz-switch{position:relative;width:46px;height:26px;flex:0 0 auto;}
.cz-switch input{opacity:0;width:0;height:0;}
.cz-switch i{position:absolute;inset:0;background:#cbd5e1;border-radius:99px;transition:.2s;display:block;}
.cz-switch i::after{content:'';position:absolute;width:20px;height:20px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s;}
.cz-switch input:checked + i{background:#165B4C;}
.cz-switch input:checked + i::after{transform:translateX(20px);}
.cz-preview{margin-top:8px;height:88px;border-radius:12px;border:1px dashed #cbd5e1;background-size:cover;background-position:center;position:relative;overflow:hidden;display:flex;align-items:center;justify-content:center;color:#e5e7eb;font-size:.78rem;font-weight:800;}
.cz-actions{position:sticky;bottom:0;display:flex;gap:10px;align-items:center;justify-content:flex-end;background:rgba(255,255,255,.94);padding:14px;border-radius:14px;border:1px solid #e6efeb;backdrop-filter:blur(6px);}
.cz-btn{border:none;border-radius:999px;padding:12px 24px;font-weight:800;font-size:.88rem;cursor:pointer;background:#165B4C;color:#fff;}
.cz-btn:disabled{background:#9ca3af;cursor:not-allowed;}
.cz-btn-ghost{background:#eef4f2;color:#134e4a;}
.cz-msg{font-size:.85rem;font-weight:800;display:none;}
.cz-msg.ok{display:block;color:#047857;}.cz-msg.err{display:block;color:#b91c1c;}
.cz-file{font-size:.82rem;color:#374151;}
</style>

<div class="pa-page">
<div class="cz-wrap">
    <div class="page-header">
        <h1 class="page-title"><span class="icon">🎨</span> Customize UI</h1>
    </div>

    <form id="czForm" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

        <!-- 1. BACKGROUND COLOUR -->
        <div class="cz-card">
            <h2>🎨 Background colour</h2>
            <p class="cz-sub">The base colour behind every page.</p>
            <div class="cz-inline">
                <input class="cz-swatch" type="color" id="czBgColor" value="<?= htmlspecialchars((string) $s['bg_color'], ENT_QUOTES, 'UTF-8') ?>">
                <input class="cz-range" type="text" id="czBgColorHex" maxlength="7" value="<?= htmlspecialchars((string) $s['bg_color'], ENT_QUOTES, 'UTF-8') ?>" style="max-width:160px;border:1px solid #d6e3df;border-radius:12px;padding:11px 13px;font:inherit;">
                <div class="cz-preview" style="flex:1;min-width:120px;background:<?= htmlspecialchars((string) $s['bg_color'], ENT_QUOTES, 'UTF-8') ?>;">Preview</div>
            </div>
        </div>

        <!-- 2. BACKGROUND IMAGE -->
        <div class="cz-card">
            <h2>🖼️ Background image</h2>
            <p class="cz-sub">JPG, PNG or WebP · max 4 MB · minimum 320×240. Re-encoded on the server and stored privately per user.</p>
            <div class="cz-field">
                <label for="czBgFile">Upload image</label>
                <input type="file" id="czBgFile" accept="image/jpeg,image/png,image/webp" class="cz-file">
                <p class="cz-sub" id="czUpMsg" style="margin:8px 0 0;"></p>
            </div>
            <div class="cz-field">
                <label for="czBgFit">Image fit</label>
                <select id="czBgFit">
                    <option value="cover" <?= $s['bg_image_fit'] === 'cover' ? 'selected' : '' ?>>Cover (fill the screen)</option>
                    <option value="contain" <?= $s['bg_image_fit'] === 'contain' ? 'selected' : '' ?>>Contain (show the whole image)</option>
                    <option value="tile" <?= $s['bg_image_fit'] === 'tile' ? 'selected' : '' ?>>Tile (repeat)</option>
                </select>
            </div>
            <?php if ($bgImageUrl): ?>
                <div class="cz-inline">
                    <div class="cz-preview" style="flex:1;min-width:180px;background-image:url('<?= htmlspecialchars($bgImageUrl, ENT_QUOTES, 'UTF-8') ?>');"></div>
                    <button type="button" class="cz-btn cz-btn-ghost" id="czRemoveBg">Remove image</button>
                </div>
            <?php endif; ?>
        </div>

        <!-- 3. RADIAL BLUR -->
        <div class="cz-card">
            <h2>🌫️ Radial blur</h2>
            <p class="cz-sub">Softens the background image with a vignette so text stays readable.</p>
            <div class="cz-toggle">
                <span>Enable blur<small>Applies a gaussian blur plus a radial vignette</small></span>
                <label class="cz-switch"><input type="checkbox" id="czBlur" <?= !empty($s['bg_blur']) ? 'checked' : '' ?>><i></i></label>
            </div>
            <div class="cz-field" style="margin-top:14px;">
                <label for="czBlurStrength">Blur strength — <span id="czBlurVal"><?= (int) $s['bg_blur_strength'] ?></span>px</label>
                <input class="cz-range" type="range" id="czBlurStrength" min="0" max="40" step="1" value="<?= (int) $s['bg_blur_strength'] ?>" style="width:100%;">
            </div>
        </div>

        <!-- 4. FONT + 5. LARGE TEXT -->
        <div class="cz-card">
            <h2>🔤 Font &amp; text size</h2>
            <p class="cz-sub">Choose the typeface and make all text larger.</p>
            <div class="cz-field">
                <label for="czFont">Font family</label>
                <select id="czFont">
                    <?php foreach (['Nunito', 'Inter', 'Roboto', 'Open Sans', 'Lato', 'Georgia', 'Verdana', 'Courier New'] as $f): ?>
                        <option value="<?= $f ?>" <?= $s['font_family'] === $f ? 'selected' : '' ?>><?= $f ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="cz-toggle">
                <span>Large text<small>Increases the base size by about 18%</small></span>
                <label class="cz-switch"><input type="checkbox" id="czLargeText" <?= !empty($s['large_text']) ? 'checked' : '' ?>><i></i></label>
            </div>
        </div>

        <!-- 6. HEADER + SIDEBAR -->
        <div class="cz-card">
            <h2>🧱 Header &amp; sidebar</h2>
            <p class="cz-sub">Colour and texture for the top bar and the side navigation.</p>
            <div class="cz-field">
                <label for="czHeaderColor">Header colour</label>
                <div class="cz-inline">
                    <input class="cz-swatch" type="color" id="czHeaderColor" value="<?= htmlspecialchars((string) $s['header_color'], ENT_QUOTES, 'UTF-8') ?>">
                    <span class="cz-file" id="czHeaderHex"><?= htmlspecialchars((string) $s['header_color'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
            <div class="cz-field">
                <label for="czHeaderTexture">Header texture</label>
                <select id="czHeaderTexture">
                    <?php foreach (['none' => 'None (flat)', 'dots' => 'Dots', 'gradient' => 'Gradient', 'diagonal' => 'Diagonal stripes', 'grain' => 'Fine grain'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $s['header_texture'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="cz-field">
                <label for="czSidebarColor">Sidebar colour</label>
                <div class="cz-inline">
                    <input class="cz-swatch" type="color" id="czSidebarColor" value="<?= htmlspecialchars((string) $s['sidebar_color'], ENT_QUOTES, 'UTF-8') ?>">
                    <span class="cz-file" id="czSidebarHex"><?= htmlspecialchars((string) $s['sidebar_color'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
            <div class="cz-field">
                <label for="czSidebarTexture">Sidebar texture</label>
                <select id="czSidebarTexture">
                    <?php foreach (['none' => 'None (flat)', 'dots' => 'Dots', 'gradient' => 'Gradient', 'diagonal' => 'Diagonal stripes', 'grain' => 'Fine grain'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $s['sidebar_texture'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="cz-actions">
            <span class="cz-msg" id="czMsg"></span>
            <button type="submit" class="cz-btn" id="czSave">Save appearance</button>
        </div>
    </form>
</div>
</div>

<script>
(function () {
    'use strict';
    var API  = '<?= APP_URL ?>/api/ui-settings.php';
    var BG   = '<?= APP_URL ?>/api/ui-background.php';
    var CSRF = <?= json_encode($csrf) ?>;
    var msgEl = document.getElementById('czMsg');
    var saveBtn = document.getElementById('czSave');
    var bgColor = document.getElementById('czBgColor');
    var bgHex = document.getElementById('czBgColorHex');
    var headerColor = document.getElementById('czHeaderColor');
    var sidebarColor = document.getElementById('czSidebarColor');
    var blurChk = document.getElementById('czBlur');
    var blurRange = document.getElementById('czBlurStrength');
    var blurVal = document.getElementById('czBlurVal');

    function msg(text, ok) { msgEl.textContent = text; msgEl.className = 'cz-msg ' + (ok ? 'ok' : 'err'); }

    bgColor.addEventListener('input', function () { bgHex.value = bgColor.value.toUpperCase(); });
    bgHex.addEventListener('input', function () {
        if (/^#[0-9a-fA-F]{6}$/.test(bgHex.value)) { bgColor.value = bgHex.value; }
    });
    headerColor.addEventListener('input', function () { document.getElementById('czHeaderHex').textContent = headerColor.value.toUpperCase(); });
    sidebarColor.addEventListener('input', function () { document.getElementById('czSidebarHex').textContent = sidebarColor.value.toUpperCase(); });
    blurRange.addEventListener('input', function () { blurVal.textContent = blurRange.value; });

    /* -------- image upload: single click, no duplicates -------- */
    var file = document.getElementById('czBgFile');
    var upMsg = document.getElementById('czUpMsg');
    file.addEventListener('change', function () {
        if (!file.files || !file.files[0]) return;
        var f = file.files[0];
        if (f.size > 4 * 1024 * 1024) { upMsg.textContent = 'Image must be 4 MB or smaller.'; upMsg.style.color = '#b91c1c'; file.value = ''; return; }
        var fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('background', f);
        upMsg.textContent = 'Uploading…';
        upMsg.style.color = '#4b5563';
        fetch(BG, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (r.success) {
                    upMsg.textContent = 'Uploaded. Reloading the preview…';
                    upMsg.style.color = '#047857';
                    setTimeout(function () { location.reload(); }, 700);
                } else {
                    upMsg.textContent = r.message || 'Upload failed.';
                    upMsg.style.color = '#b91c1c';
                    file.value = '';
                }
            })
            .catch(function () { upMsg.textContent = 'Network error while uploading.'; upMsg.style.color = '#b91c1c'; file.value = ''; });
    });

    var rm = document.getElementById('czRemoveBg');
    if (rm) {
        rm.addEventListener('click', function () {
            rm.disabled = true;
            var fd = new FormData();
            fd.append('csrf_token', CSRF);
            fd.append('action', 'save');
            fd.append('bg_image_url', '');
            fetch(API + '?action=save', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function () { location.reload(); })
                .catch(function () { rm.disabled = false; });
        });
    }

    /* -------- save (button disabled after first click) -------- */
    document.getElementById('czForm').addEventListener('submit', function (e) {
        e.preventDefault();
        if (saveBtn.disabled) return;
        var fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('bg_color', bgHex.value || bgColor.value);
        fd.append('bg_image_fit', document.getElementById('czBgFit').value);
        fd.append('bg_blur', blurChk.checked ? '1' : '0');
        fd.append('bg_blur_strength', blurRange.value);
        fd.append('font_family', document.getElementById('czFont').value);
        fd.append('large_text', document.getElementById('czLargeText').checked ? '1' : '0');
        fd.append('header_color', headerColor.value);
        fd.append('header_texture', document.getElementById('czHeaderTexture').value);
        fd.append('sidebar_color', sidebarColor.value);
        fd.append('sidebar_texture', document.getElementById('czSidebarTexture').value);

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving…';
        fetch(API + '?action=save', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (r.success) {
                    msg(r.message || 'Saved.', true);
                    setTimeout(function () { location.reload(); }, 600);
                } else {
                    msg(r.message || 'Could not save.', false);
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save appearance';
                }
            })
            .catch(function () {
                msg('Network error while saving.', false);
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save appearance';
            });
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
