<?php
/**
 * PawAdopt — Google AdSense slot (dashboard placement).
 *
 * No publisher ID is invented anywhere. Set these environment variables on the
 * host (Render → Environment) to go live:
 *     ADSENSE_CLIENT_ID   e.g. ca-pub-0000000000000000
 *     ADSENSE_SLOT_ID     e.g. 0000000000
 *
 * While they are unset the slot renders a neutral placeholder so the layout is
 * visible but nothing is loaded from a third party.
 */

$paAdClient = trim((string) (getenv('ADSENSE_CLIENT_ID') ?: ($_ENV['ADSENSE_CLIENT_ID'] ?? '')));
$paAdSlot   = trim((string) (getenv('ADSENSE_SLOT_ID') ?: ($_ENV['ADSENSE_SLOT_ID'] ?? '')));
$paAdLive   = $paAdClient !== '' && $paAdSlot !== '' && preg_match('/^ca-pub-\d{10,20}$/', $paAdClient);
?>
<section class="pa-ad-slot" aria-label="Advertisement">
    <span class="pa-ad-label">Advertisement</span>
    <?php if ($paAdLive): ?>
        <ins class="adsbygoogle pa-ad-ins"
             style="display:block"
             data-ad-client="<?= htmlspecialchars($paAdClient, ENT_QUOTES, 'UTF-8') ?>"
             data-ad-slot="<?= htmlspecialchars($paAdSlot, ENT_QUOTES, 'UTF-8') ?>"
             data-ad-format="auto"
             data-full-width-responsive="true"></ins>
        <script>
            (function () {
                try { (window.adsbygoogle = window.adsbygoogle || []).push({}); } catch (e) {}
            })();
        </script>
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= htmlspecialchars($paAdClient, ENT_QUOTES, 'UTF-8') ?>" crossorigin="anonymous"></script>
    <?php else: ?>
        <div class="pa-ad-placeholder">
            <i class="fas fa-rectangle-ad" aria-hidden="true"></i>
            <div>
                <strong>Ad slot ready</strong>
                <span>Add <code>ADSENSE_CLIENT_ID</code> and <code>ADSENSE_SLOT_ID</code> in your hosting
                environment variables to display a live Google AdSense unit here.</span>
            </div>
        </div>
    <?php endif; ?>
</section>
