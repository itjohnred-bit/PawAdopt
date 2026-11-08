<?php
/**
 * PawAdopt — UI Customization page (all roles).
 *
 * Lets the signed-in user personalize the interface: theme, accent color,
 * typography, modal styling and accessibility options. Settings are stored
 * per user in the user_preferences table via api/ui-settings.php
 * (GET ?action=get / POST ?action=save — both scoped to the session user
 * server-side; see api/ui-settings.php).
 *
 * Access: requireLogin() runs inside includes/header.php before any output.
 * Available to ADOPTER, SHELTER (incl. veterinary accounts) and ADMIN alike.
 */

require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$pageTitle = 'Customize UI';

include __DIR__ . '/../includes/header.php';
?>

<style>
    .cz-wrap { max-width: 860px; margin: 0 auto; }
    .cz-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 26px; margin-bottom: 22px; }
    .cz-card h3 { font-size: 1.05rem; font-weight: 800; color: #1f2937; margin: 0 0 16px; display: flex; align-items: center; gap: 8px; }
    .cz-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
    .cz-field label { display: block; font-size: .78rem; font-weight: 800; color: #4b5563; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .04em; }
    .cz-field select, .cz-field input[type="text"], .cz-field input[type="color"] {
        width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 10px; background: #fff;
        font: inherit; color: #111827; box-sizing: border-box;
    }
    .cz-themes { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
    .cz-theme { border: 2px solid #e5e7eb; border-radius: 14px; padding: 14px; cursor: pointer; background: #fff; transition: border-color .15s ease, box-shadow .15s ease; }
    .cz-theme:hover { box-shadow: 0 6px 16px rgba(15,23,42,.08); }
    .cz-theme.active { border-color: var(--accent-color, #0d9488); box-shadow: 0 0 0 3px rgba(13,148,136,.15); }
    .cz-swatch { height: 34px; border-radius: 8px; margin-bottom: 8px; }
    .cz-theme-name { font-weight: 800; font-size: .9rem; color: #111827; }
    .cz-theme-desc { font-size: .76rem; color: #6b7280; margin-top: 2px; }
    .cz-toggles { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
    .cz-toggle { display: flex; align-items: center; justify-content: space-between; gap: 10px; border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 14px; }
    .cz-toggle span { font-size: .88rem; color: #1f2937; font-weight: 600; }
    .cz-actions { display: flex; gap: 10px; justify-content: flex-end; align-items: center; }
    .cz-msg { font-size: .88rem; font-weight: 700; display: none; }
    .cz-msg.ok { color: #047857; display: inline; }
    .cz-msg.err { color: #b91c1c; display: inline; }
    .cz-preview { border: 1px dashed #cbd5e1; border-radius: 12px; padding: 16px; background: var(--modal-bg-color, #fff); box-shadow: var(--shadow-elevation, none); border-radius: var(--border-radius, 12px); }
</style>

<div class="page-header">
    <h1 class="page-title"><span class="icon">🎨</span> Customize UI</h1>
</div>

<div class="cz-wrap">

    <div class="cz-card">
        <h3><i class="fas fa-swatchbook"></i> Theme</h3>
        <div class="cz-themes" id="czThemes">
            <button type="button" class="cz-theme" data-theme="pixel">
                <div class="cz-swatch" style="background:#5865F2"></div>
                <div class="cz-theme-name">Pixel</div>
                <div class="cz-theme-desc">Retro 8-bit, crisp block borders</div>
            </button>
            <button type="button" class="cz-theme" data-theme="minimalist">
                <div class="cz-swatch" style="background:#111111"></div>
                <div class="cz-theme-name">Minimalist</div>
                <div class="cz-theme-desc">High-contrast monochrome, clean lines</div>
            </button>
            <button type="button" class="cz-theme" data-theme="greenish_minimalist">
                <div class="cz-swatch" style="background:#4A7C59"></div>
                <div class="cz-theme-name">Greenish Minimalist</div>
                <div class="cz-theme-desc">Soft sage &amp; earth tones</div>
            </button>
            <button type="button" class="cz-theme active" data-theme="cozy_warmth">
                <div class="cz-swatch" style="background:#E07A5F"></div>
                <div class="cz-theme-name">Cozy Warmth</div>
                <div class="cz-theme-desc">Warm cream, rounded, pastel</div>
            </button>
        </div>
    </div>

    <div class="cz-card">
        <h3><i class="fas fa-sliders-h"></i> Typography &amp; Colors</h3>
        <div class="cz-grid">
            <div class="cz-field">
                <label for="czFont">Font family</label>
                <select id="czFont">
                    <option value="Nunito">Nunito (default)</option>
                    <option value="Arial">Arial</option>
                    <option value="Inter">Inter</option>
                    <option value="Roboto">Roboto</option>
                    <option value="Open Sans">Open Sans</option>
                    <option value="Courier Prime">Courier Prime</option>
                    <option value="Pixelify">Pixelify</option>
                </select>
            </div>
            <div class="cz-field">
                <label for="czSize">Base font size</label>
                <select id="czSize">
                    <option value="7pt">Small (7pt)</option>
                    <option value="8pt" selected>Regular (8pt)</option>
                    <option value="9pt">Large (9pt)</option>
                    <option value="10pt">Extra Large (10pt)</option>
                </select>
            </div>
            <div class="cz-field">
                <label for="czScale">Header scaling</label>
                <select id="czScale">
                    <option value="1.10">Compact (1.1x)</option>
                    <option value="1.25" selected>Standard (1.25x)</option>
                    <option value="1.40">Prominent (1.4x)</option>
                </select>
            </div>
            <div class="cz-field">
                <label for="czAccent">Accent color</label>
                <input type="color" id="czAccent" value="#E07A5F">
            </div>
        </div>
    </div>

    <div class="cz-card">
        <h3><i class="fas fa-window-restore"></i> Window &amp; Modal</h3>
        <div class="cz-grid">
            <div class="cz-field">
                <label for="czModalBg">Modal background</label>
                <input type="color" id="czModalBg" value="#FFFFFF">
            </div>
            <div class="cz-field">
                <label for="czBackdrop">Overlay backdrop (rgba)</label>
                <input type="text" id="czBackdrop" value="rgba(0, 0, 0, 0.5)" placeholder="rgba(0, 0, 0, 0.5)">
            </div>
            <div class="cz-field">
                <label for="czRadius">Border radius</label>
                <select id="czRadius">
                    <option value="0">0px — Sharp</option>
                    <option value="4">4px — Slight</option>
                    <option value="8">8px — Rounded</option>
                    <option value="16" selected>16px — Cozy</option>
                </select>
            </div>
            <div class="cz-field">
                <label for="czShadow">Shadow elevation</label>
                <select id="czShadow">
                    <option value="none">None</option>
                    <option value="soft" selected>Soft blur</option>
                    <option value="hard">Hard drop shadow</option>
                    <option value="retro">Retro offset</option>
                </select>
            </div>
        </div>
    </div>

    <div class="cz-card">
        <h3><i class="fas fa-universal-access"></i> Accessibility &amp; Page Settings</h3>
        <div class="cz-toggles">
            <label class="cz-toggle"><span>High-contrast text</span>
                <input type="checkbox" id="czHighContrast"></label>
            <label class="cz-toggle"><span>Disable animations</span>
                <input type="checkbox" id="czNoAnim"></label>
            <label class="cz-toggle"><span>Notification badges</span>
                <input type="checkbox" id="czBadges" checked></label>
            <label class="cz-toggle"><span>Show phone (public contact)</span>
                <input type="checkbox" id="czPhone" checked></label>
            <label class="cz-toggle"><span>Show email (public contact)</span>
                <input type="checkbox" id="czEmail" checked></label>
            <label class="cz-toggle"><span>Show address (public contact)</span>
                <input type="checkbox" id="czAddress" checked></label>
        </div>
    </div>

    <div class="cz-card">
        <div class="cz-preview" id="czPreview">
            <strong style="font-size: calc(1rem * var(--header-scale, 1.25));">Live preview</strong>
            <p style="margin:8px 0 0; color:#4b5563;">This card follows your accent color, radius, shadow and font choices. Saving applies them across the whole portal instantly.</p>
        </div>
        <div class="cz-actions" style="margin-top:16px;">
            <span class="cz-msg" id="czMsg"></span>
            <button type="button" class="btn btn-secondary" id="czReset">Reset to defaults</button>
            <button type="button" class="btn btn-primary" id="czSave">
                <i class="fas fa-save"></i> Save customization
            </button>
        </div>
    </div>

</div>

<script>
(function () {
    'use strict';
    var API = '<?= APP_URL ?>/api/ui-settings.php';
    var $ = function (id) { return document.getElementById(id); };

    var state = {
        ui_theme: 'cozy_warmth',
        accent_color_hex: '#E07A5F',
        font_family: 'Nunito',
        base_font_size: '8pt',
        header_scale: '1.25',
        modal_bg_color: '#FFFFFF',
        overlay_backdrop: 'rgba(0, 0, 0, 0.5)',
        border_radius_px: '16',
        shadow_elevation: 'soft',
        grid_density: 'standard',
        show_contact_phone: 1, show_contact_email: 1, show_contact_address: 1,
        notif_badges: 1, high_contrast: 0, disable_animations: 0
    };

    function paint() {
        // theme buttons
        document.querySelectorAll('.cz-theme').forEach(function (b) {
            b.classList.toggle('active', b.getAttribute('data-theme') === state.ui_theme);
        });
        $('czFont').value = state.font_family;
        $('czSize').value = state.base_font_size;
        $('czScale').value = String(state.header_scale);
        $('czAccent').value = state.accent_color_hex;
        $('czModalBg').value = state.modal_bg_color;
        $('czBackdrop').value = state.overlay_backdrop;
        $('czRadius').value = String(state.border_radius_px);
        $('czShadow').value = state.shadow_elevation;
        $('czHighContrast').checked = Number(state.high_contrast) === 1;
        $('czNoAnim').checked = Number(state.disable_animations) === 1;
        $('czBadges').checked = Number(state.notif_badges) === 1;
        $('czPhone').checked = Number(state.show_contact_phone) === 1;
        $('czEmail').checked = Number(state.show_contact_email) === 1;
        $('czAddress').checked = Number(state.show_contact_address) === 1;
        // live preview via the global applier from ui-customization.js
        if (typeof window.paApplyUiCustomization === 'function') {
            window.paApplyUiCustomization(Object.assign({}, state));
        }
    }

    function msg(text, ok) {
        var el = $('czMsg');
        el.textContent = text;
        el.className = 'cz-msg ' + (ok ? 'ok' : 'err');
        setTimeout(function () { el.className = 'cz-msg'; }, 4000);
    }

    function collect() {
        state.ui_theme          = (document.querySelector('.cz-theme.active') || { getAttribute: function () { return 'cozy_warmth'; } }).getAttribute('data-theme');
        state.font_family       = $('czFont').value;
        state.base_font_size    = $('czSize').value;
        state.header_scale      = $('czScale').value;
        state.accent_color_hex  = $('czAccent').value;
        state.modal_bg_color    = $('czModalBg').value;
        state.overlay_backdrop  = $('czBackdrop').value.trim();
        state.border_radius_px  = $('czRadius').value;
        state.shadow_elevation  = $('czShadow').value;
        state.high_contrast        = $('czHighContrast').checked ? 1 : 0;
        state.disable_animations   = $('czNoAnim').checked ? 1 : 0;
        state.notif_badges         = $('czBadges').checked ? 1 : 0;
        state.show_contact_phone   = $('czPhone').checked ? 1 : 0;
        state.show_contact_email   = $('czEmail').checked ? 1 : 0;
        state.show_contact_address = $('czAddress').checked ? 1 : 0;
    }

    // Load current settings
    fetch(API + '?action=get', { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(new Error('load failed')); })
        .then(function (res) {
            if (res && res.success && res.data) {
                Object.keys(state).forEach(function (k) {
                    if (res.data[k] !== undefined && res.data[k] !== null && res.data[k] !== '') {
                        state[k] = res.data[k];
                    }
                });
            }
            paint();
        })
        .catch(function () { paint(); /* defaults */ });

    // Theme picker
    document.querySelectorAll('.cz-theme').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.cz-theme').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            state.ui_theme = btn.getAttribute('data-theme');
            // theme presets (mirror api/ui-settings.php $themeDefaults)
            var presets = {
                pixel:               { accent: '#5865F2', radius: '0',  shadow: 'retro' },
                minimalist:          { accent: '#111111', radius: '4',  shadow: 'none' },
                greenish_minimalist: { accent: '#4A7C59', radius: '8',  shadow: 'soft' },
                cozy_warmth:         { accent: '#E07A5F', radius: '16', shadow: 'soft' }
            };
            var p = presets[state.ui_theme];
            if (p) { state.accent_color_hex = p.accent; state.border_radius_px = p.radius; state.shadow_elevation = p.shadow; }
            paint();
        });
    });

    // Live preview on any control change
    ['czFont','czSize','czScale','czAccent','czModalBg','czRadius','czShadow','czHighContrast','czNoAnim']
        .forEach(function (id) { $(id).addEventListener('input', function () { collect(); paint(); }); });
    $('czBackdrop').addEventListener('change', function () { collect(); paint(); });

    $('czSave').addEventListener('click', function () {
        var btn = this;
        if (btn.disabled) return;
        collect();
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
        var body = new URLSearchParams();
        Object.keys(state).forEach(function (k) { body.append(k, state[k]); });
        body.append('action', 'save');
        fetch(API, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res && res.success) {
                msg('Customization saved — applied across your portal.', true);
                if (typeof window.paApplyUiCustomization === 'function' && res.data) {
                    window.paApplyUiCustomization(res.data);
                }
            } else {
                msg((res && res.message) || 'Save failed.', false);
            }
        })
        .catch(function () { msg('Network error while saving.', false); })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save customization';
        });
    });

    $('czReset').addEventListener('click', function () {
        state = {
            ui_theme: 'cozy_warmth', accent_color_hex: '#E07A5F', font_family: 'Nunito',
            base_font_size: '8pt', header_scale: '1.25', modal_bg_color: '#FFFFFF',
            overlay_backdrop: 'rgba(0, 0, 0, 0.5)', border_radius_px: '16',
            shadow_elevation: 'soft', grid_density: 'standard',
            show_contact_phone: 1, show_contact_email: 1, show_contact_address: 1,
            notif_badges: 1, high_contrast: 0, disable_animations: 0
        };
        paint();
        msg('Defaults loaded — press Save to apply.', true);
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
