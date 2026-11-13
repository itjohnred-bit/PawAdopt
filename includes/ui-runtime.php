<?php
/**
 * PawAdopt — UI customization runtime.
 *
 * Included by includes/header.php AFTER the layout is known. Reads the
 * signed-in user's own row from user_preferences (ownership enforced by the
 * WHERE clause) and prints ONLY CSS variables + two body classes.
 *
 * Nothing user-supplied is ever echoed raw: colours go through a hex regex,
 * the font through an allowlist, the image path through a strict local-upload
 * regex, and the numeric blur through a cast + clamp.
 */

if (!function_exists('pa_ui_runtime_settings')) {

    function pa_ui_runtime_settings(int $userId): array
    {
        $defaults = [
            'bg_color'         => '',
            'bg_image_url'     => '',
            'bg_blur'          => 0,
            'bg_blur_strength' => 14,
            'bg_image_fit'     => 'cover',
            'font_family'      => '',
            'large_text'       => 0,
            'header_color'     => '',
            'header_texture'   => 'none',
            'sidebar_color'    => '',
            'sidebar_texture'  => 'none',
        ];
        try {
            $db  = Database::getInstance();
            $row = $db->fetch('SELECT * FROM user_preferences WHERE user_id = ?', [$userId]);
            if (!$row) {
                return $defaults;
            }
            foreach ($defaults as $k => $v) {
                if (array_key_exists($k, $row) && $row[$k] !== null) {
                    $defaults[$k] = $row[$k];
                }
            }
        } catch (Throwable $e) {
            // Column missing (migration not run yet) or DB hiccup → defaults.
            return $defaults;
        }
        return $defaults;
    }

    function pa_ui_runtime_css(array $s): string
    {
        $hex  = static fn($v) => (is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v)) ? strtoupper($v) : '';
        $font = ['Nunito', 'Inter', 'Roboto', 'Open Sans', 'Lato', 'Georgia', 'Verdana', 'Courier New'];
        $tex  = ['none', 'dots', 'gradient', 'diagonal', 'grain'];
        $fit  = ['cover', 'contain', 'tile'];

        $css = [];

        $bg = $hex($s['bg_color'] ?? '');
        if ($bg) { $css[] = '--pa-bg: ' . $bg . ';'; }

        $img = (string) ($s['bg_image_url'] ?? '');
        if (preg_match('#^uploads/ui/[a-f0-9]{32}\.(jpg|png|webp)$#', $img)) {
            $css[] = "--pa-bg-image: url('" . APP_URL . '/' . $img . "');";
            $f = in_array($s['bg_image_fit'] ?? 'cover', $fit, true) ? $s['bg_image_fit'] : 'cover';
            $css[] = '--pa-bg-size: ' . ($f === 'tile' ? 'auto' : $f) . ';';
            $css[] = '--pa-bg-repeat: ' . ($f === 'tile' ? 'repeat' : 'no-repeat') . ';';
        } else {
            $css[] = '--pa-bg-image: none;';
        }

        $blurOn = !empty($s['bg_blur']);
        $blur   = max(0, min(40, (int) ($s['bg_blur_strength'] ?? 14)));
        $css[]  = '--pa-bg-blur: ' . ($blurOn ? $blur . 'px' : '0px') . ';';

        $fontFam = in_array($s['font_family'] ?? '', $font, true) ? $s['font_family'] : '';
        if ($fontFam !== '') {
            $css[] = "--pa-font: '" . $fontFam . "', 'Nunito', sans-serif;";
        }

        $hc = $hex($s['header_color'] ?? '');
        if ($hc) { $css[] = '--pa-header-bg: ' . $hc . ';'; }
        $sc = $hex($s['sidebar_color'] ?? '');
        if ($sc) { $css[] = '--pa-sidebar-bg: ' . $sc . ';'; }

        $ht = in_array($s['header_texture'] ?? 'none', $tex, true) ? $s['header_texture'] : 'none';
        $st = in_array($s['sidebar_texture'] ?? 'none', $tex, true) ? $s['sidebar_texture'] : 'none';
        $css[] = '--pa-header-texture: ' . $ht . ';';
        $css[] = '--pa-sidebar-texture: ' . $st . ';';

        return implode(' ', $css);
    }
}

if (!function_exists('pa_ui_is_dark_hex')) {
    /** True when a #RRGGBB colour is dark enough to need light text on it. */
    function pa_ui_is_dark_hex(string $hex): bool
    {
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) { return false; }
        $r = hexdec(substr($hex, 1, 2));
        $g = hexdec(substr($hex, 3, 2));
        $b = hexdec(substr($hex, 5, 2));
        // Perceived luminance (ITU-R BT.601)
        $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        return $lum < 0.55;
    }
}
