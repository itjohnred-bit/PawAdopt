</main><footer class="app-footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <span class="brand-icon">🐾</span>
            <strong>PawAdopt</strong>
        </div>
        <p class="footer-tagline">Finding forever homes, one paw at a time. 🐶🐱🐰</p>
        <div class="footer-links">
            <a href="<?= APP_URL ?>/index.php?page=about">About</a>
            <a href="<?= APP_URL ?>/index.php?page=terms">Terms &amp; Conditions</a>
        </div>
        <p class="footer-copy">&copy; <?= date('Y') ?> PawAdopt. All rights reserved.</p>
    </div>
    <div class="footer-decor">
        <div class="pixel-bone-footer">🦴</div>
        <div class="pixel-fish-footer">🐟</div>
    </div>
</footer>

<div class="modal fade" id="applicationDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content" style="border-radius: 15px; border: none; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
            <div class="modal-header" style="background-color: #f8fbf9;">
                <h5 class="modal-title" style="color: #2d5a4c; font-weight: 600;">Application Summary</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-6">
                        <label class="text-muted small fw-bold">ADOPTER</label>
                        <p id="modal_adopter_name" class="fw-bold mb-0">-</p>
                    </div>
                    <div class="col-6">
                        <label class="text-muted small fw-bold">PET</label>
                        <p id="modal_pet_name" class="fw-bold text-primary mb-0">-</p>
                    </div>
                    <hr class="my-2 opacity-25">
                    <div class="col-12">
                        <label class="text-muted small fw-bold mb-2">MESSAGE</label>
                        <div id="modal_message" class="p-3 bg-light rounded" style="min-height: 80px; white-space: pre-wrap; font-style: italic;">-</div>
                    </div>
                    <div class="col-12">
                        <label class="text-muted small fw-bold">STATUS</label><br>
                        <span id="modal_status" class="badge bg-info text-dark">PENDING</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
<script src="<?= APP_URL ?>/assets/js/dark-mode.js"></script>
<style>
.sidebar,
.side-nav,
aside.sidebar {
    position: relative;
    transition: transform .28s ease, opacity .28s ease;
}
.sidebar.collapsed,
.side-nav.collapsed,
aside.sidebar.collapsed {
    transform: translateX(-100%);
    opacity: 0;
    pointer-events: none;
}

#sbCloseBtn {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    background: rgba(13, 148, 136, .12);
    color: #0f766e;
    cursor: pointer;
    font-size: 14px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all .2s ease;
    z-index: 5;
}
#sbCloseBtn:hover {
    background: #e11d48;
    color: #fff;
    transform: rotate(90deg);
}

#sbOpenBtn {
    position: fixed;
    top: 50%;
    left: 0;
    transform: translateY(-50%);
    width: 36px;
    height: 72px;
    border: none;
    border-radius: 0 14px 14px 0;
    background: linear-gradient(135deg, #0d9488, #0f766e);
    color: #fff;
    cursor: pointer;
    font-size: 18px;
    font-weight: 900;
    box-shadow: 4px 4px 14px rgba(13, 148, 136, .35);
    z-index: 9998;
    display: none;
    align-items: center;
    justify-content: center;
    transition: width .15s ease;
}
#sbOpenBtn:hover { width: 44px; }
#sbOpenBtn.show { display: flex; }

.main-content,
.page-wrap,
main,
.content-area {
    transition: margin-left .28s ease;
}
body.sidebar-closed .main-content,
body.sidebar-closed .page-wrap,
body.sidebar-closed main,
body.sidebar-closed .content-area {
    margin-left: 0 !important;
}

.sidebar::before,
.side-nav::before,
aside.sidebar::before {
    content: "";
    display: none;
}
</style>

<button id="sbOpenBtn" aria-label="Open sidebar" title="Open sidebar">☰</button>

<script>
(function () {
    'use strict';

    var sidebar =
        document.querySelector('.sidebar') ||
        document.querySelector('.side-nav') ||
        document.querySelector('aside.sidebar');
    if (!sidebar) {
        console.warn('[ClosableSidebar] No sidebar found — widget skipped.');
        return;
    }

    if (document.getElementById('sbCloseBtn')) return;

    var closeBtn = document.createElement('button');
    closeBtn.id = 'sbCloseBtn';
    closeBtn.setAttribute('aria-label', 'Close sidebar');
    closeBtn.setAttribute('title', 'Close sidebar (Esc)');
    closeBtn.innerHTML = '✕';
    sidebar.appendChild(closeBtn);

    var openBtn = document.getElementById('sbOpenBtn');
    var KEY = 'pawadopt_sidebar_closed';

    function applyState(closed) {
        sidebar.classList.toggle('collapsed', closed);
        document.body.classList.toggle('sidebar-closed', closed);
        openBtn.classList.toggle('show', closed);
    }

    try {
        applyState(localStorage.getItem(KEY) === '1');
    } catch (e) {}

    function setClosed(closed) {
        applyState(closed);
        try { localStorage.setItem(KEY, closed ? '1' : '0'); } catch (e) {}
    }

    closeBtn.addEventListener('click', function () { setClosed(true); });
    openBtn.addEventListener('click', function () { setClosed(false); });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !sidebar.classList.contains('collapsed')) {
            var tag = (document.activeElement && document.activeElement.tagName) || '';
            if (tag !== 'INPUT' && tag !== 'TEXTAREA') {
                setClosed(true);
            }
        }
    });

    window.addEventListener('beforeunload', function () {
        try { localStorage.setItem(KEY, sidebar.classList.contains('collapsed') ? '1' : '0'); } catch (e) {}
    });
})();
</script>
</body>
</html>
