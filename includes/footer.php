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
</body>
</html>