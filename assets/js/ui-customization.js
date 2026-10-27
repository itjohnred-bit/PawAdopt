/**
 * PawAdopt — UI Customization runtime injection.
 * Loads the signed-in user's saved customization (api/ui-settings.php) and
 * injects it as CSS variables on :root. No page reload needed; falls back
 * silently to the default theme when not logged in or on error.
 */
(function () {
    'use strict';

    var THEMES = {
        pixel:               { accent: '#5865F2', radius: '0px',  shadow: '6px 6px 0 rgba(0,0,0,.85)', font: "'Pixelify', 'Courier Prime', monospace" },
        minimalist:          { accent: '#111111', radius: '4px',  shadow: 'none',                      font: "'Inter', Arial, sans-serif" },
        greenish_minimalist: { accent: '#4A7C59', radius: '8px',  shadow: '0 4px 14px rgba(74,124,89,.18)', font: "'Open Sans', Roboto, sans-serif" },
        cozy_warmth:         { accent: '#E07A5F', radius: '16px', shadow: '0 8px 22px rgba(224,122,95,.20)', font: "'Nunito', sans-serif" }
    };

    function hexToRgb(hex) {
        var m = /^#?([0-9a-f]{6})$/i.exec(hex || '');
        if (!m) return '13, 148, 136';
        return parseInt(m[1].slice(0, 2), 16) + ', ' + parseInt(m[1].slice(2, 4), 16) + ', ' + parseInt(m[1].slice(4, 6), 16);
    }

    function apply(p) {
        if (!p) return;
        var theme = THEMES[p.ui_theme] || THEMES.cozy_warmth;
        var root = document.documentElement.style;
        var accent = p.accent_color_hex || theme.accent;

        root.setProperty('--accent-color', accent);
        root.setProperty('--accent-color-rgb', hexToRgb(accent));
        root.setProperty('--base-font-family', p.font_family ? "'" + p.font_family + "', sans-serif" : theme.font);
        root.setProperty('--base-font-size', p.base_font_size || '8pt');
        root.setProperty('--header-scale', (parseFloat(p.header_scale) || 1.25) + '');
        root.setProperty('--border-radius', (parseInt(p.border_radius_px, 10) || 0) + 'px');
        root.setProperty('--modal-bg-color', p.modal_bg_color || '#FFFFFF');
        root.setProperty('--overlay-backdrop', p.overlay_backdrop || 'rgba(0, 0, 0, 0.5)');

        var shadows = {
            none: 'none',
            soft: '0 6px 18px rgba(15, 23, 42, .10)',
            hard: '6px 6px 0 rgba(0, 0, 0, .9)',
            retro: '6px 6px 0 rgba(0, 0, 0, .85)'
        };
        root.setProperty('--shadow-elevation', shadows[p.shadow_elevation] || shadows.soft);
        root.setProperty('--font-family-base', p.font_family ? "'" + p.font_family + "', sans-serif" : theme.font);

        if (document.body) {
            document.body.style.fontFamily = p.font_family ? "'" + p.font_family + "', sans-serif" : theme.font;
        }
        if (p.high_contrast) {
            root.setProperty('--text-color', '#111111');
            document.body && document.body.classList.add('pa-high-contrast');
        }
        if (p.disable_animations) {
            document.body && document.body.classList.add('pa-no-anim');
        }
    }

    // Inject supporting utility classes once.
    var style = document.createElement('style');
    style.textContent =
        '.pa-no-anim *, .pa-no-anim *::before, .pa-no-anim *::after { transition: none !important; animation: none !important; }' +
        '.pa-high-contrast { filter: contrast(1.08); }' +
        '.pa-high-contrast .card, .pa-high-contrast .modal-content { color: #111 !important; }';
    document.head.appendChild(style);

    // Best-guess API base — window.BASE_URL is set by assets/js/app.js.
    var base = (window.BASE_URL || window.location.origin);
    fetch(base + '/api/ui-settings.php?action=get', { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (res) { if (res && res.success) apply(res.data); })
        .catch(function () { /* keep defaults */ });

    // Re-apply after live saves from a customizer panel.
    window.paApplyUiCustomization = apply;
})();
