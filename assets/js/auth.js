'use strict';

(function () {

    const API_BASE = window.location.origin + '/api/';

    document.addEventListener('DOMContentLoaded', () => {

        const loginPanel    = document.getElementById('loginPanel');
        const registerPanel = document.getElementById('registerPanel');
        const forgotPanel   = document.getElementById('forgotPanel');
        const authLeft      = document.querySelector('.auth-left');

        function showPanel(panel) {
            if (!panel) return;
            [loginPanel, registerPanel, forgotPanel].forEach((p) => {
                if (p) p.classList.remove('active');
            });
            panel.classList.add('active');
            if (panel === registerPanel || panel === forgotPanel) {
                authLeft?.classList.add('dark-mode');
            } else {
                authLeft?.classList.remove('dark-mode');
            }
            syncRoleToActivePanel();
        }

        function syncRoleToActivePanel() {
            const activePanel = document.querySelector('.auth-panel.active');
            if (!activePanel) return;
            const roleInput = activePanel.querySelector('input[name="role"]');
            const activeBtn = activePanel.querySelector('.role-btn.active') || activePanel.querySelector('.role-btn');
            if (roleInput && activeBtn) {
                roleInput.value = activeBtn.dataset.role || '';
            }
        }

        document.getElementById('goRegister')?.addEventListener('click', () => showPanel(registerPanel));
        document.getElementById('goLogin')?.addEventListener('click',    () => showPanel(loginPanel));
        document.getElementById('goForgot')?.addEventListener('click',   () => showPanel(forgotPanel));
        document.getElementById('forgotBack')?.addEventListener('click', () => showPanel(loginPanel));

        showPanel(loginPanel);

        document.querySelectorAll('.role-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const panel = btn.closest('.auth-panel');
                if (!panel) return;
                panel.querySelectorAll('.role-btn').forEach((b) => b.classList.remove('active'));
                btn.classList.add('active');
                const roleInput = panel.querySelector('input[name="role"]');
                if (roleInput) roleInput.value = btn.dataset.role || '';
            });
        });

        document.querySelectorAll('.eye-toggle').forEach((btn) => {
            btn.addEventListener('click', () => {
                const target = document.getElementById(btn.dataset.target);
                if (!target) return;
                const icon = btn.querySelector('i');

                if (target.type === 'password') {
                    target.type = 'text';
                    if (icon) {
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    }
                } else {
                    target.type = 'password';
                    if (icon) {
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                }
            });
        });

        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                clearErrors();
                syncRoleToActivePanel();

                const fd   = new FormData(loginForm);
                const btn  = loginForm.querySelector('[type=submit]');
                const text = btn ? btn.textContent : 'Sign In';

                if (btn) { btn.disabled = true; btn.textContent = 'Signing in…'; }

                const res = await postForm(API_BASE + 'auth.php?action=login', fd);

                if (btn) { btn.disabled = false; btn.textContent = text; }

                if (res && res.success && res.data && res.data.redirect) {
                    showAlert(loginPanel, 'Logged in! Redirecting…', 'success');
                    setTimeout(() => { window.location.href = res.data.redirect; }, 800);
                } else if (res && res.success) {
                    showAlert(loginPanel, 'Logged in, but no redirect target was provided.', 'error');
                } else {
                    showAlert(loginPanel, (res && res.message) || 'Login failed.', 'error');
                }
            });
        }

        const registerForm = document.getElementById('registerForm');
        if (registerForm) {
            registerForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                clearErrors();
                syncRoleToActivePanel();

                const fd   = new FormData(registerForm);
                const btn  = registerForm.querySelector('[type=submit]');
                const text = btn ? btn.textContent : 'Sign Up';

                if (btn) { btn.disabled = true; btn.textContent = 'Creating account…'; }

                const res = await postForm(API_BASE + 'auth.php?action=register', fd);

                if (btn) { btn.disabled = false; btn.textContent = text; }

                if (res && res.success && res.data && res.data.redirect) {
                    showAlert(registerPanel, 'Account created!', 'success');
                    setTimeout(() => { window.location.href = res.data.redirect; }, 1000);
                } else if (res && res.success) {
                    showAlert(registerPanel, 'Account created, but no redirect target was provided.', 'error');
                } else {
                    showAlert(registerPanel, (res && res.message) || 'Registration failed.', 'error');
                }
            });
        }

        const forgotForm = document.getElementById('forgotForm');
        if (forgotForm) {
            forgotForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                clearErrors();

                const fd   = new FormData(forgotForm);
                fd.append('action', 'forgot');
                const btn  = forgotForm.querySelector('[type=submit]');
                const text = btn ? btn.textContent : 'Send';

                if (btn) { btn.disabled = true; btn.textContent = 'Sending…'; }

                const res = await postForm(API_BASE + 'auth.php?action=forgot', fd);

                if (btn) { btn.disabled = false; btn.textContent = text; }

                if (res && res.success) {
                    showAlert(forgotPanel, res.message || 'Check your inbox.', 'success');
                } else {
                    showAlert(forgotPanel, (res && res.message) || 'Request failed.', 'error');
                }
            });
        }
    });

    async function postForm(url, formData) {
        try {
            const res  = await fetch(url, { method: 'POST', body: formData, credentials: 'same-origin' });
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (err) {
                console.error('Invalid JSON from server:', text);
                return { success: false, message: 'Server error. Please check console.' };
            }
        } catch (err) {
            console.error('Network error:', err);
            return { success: false, message: 'Network error.' };
        }
    }

    function showAlert(panel, message, type) {
        if (!panel) return;
        clearErrors();
        const el = document.createElement('div');
        el.className = `auth-alert auth-alert-${type}`;
        const icon = document.createElement('span');
        icon.className = 'auth-alert-icon';
        icon.textContent = type === 'success' ? '✓' : '⚠';
        const txt = document.createElement('span');
        txt.className   = 'auth-alert-text';
        txt.textContent = message || '';
        el.appendChild(icon);
        el.appendChild(txt);
        panel.insertBefore(el, panel.firstChild);
    }

    function clearErrors() {
        document.querySelectorAll('.auth-alert').forEach((el) => el.remove());
    }

})();
// ============================================
// FORGOT PASSWORD HANDLER
// Add this to the END of assets/js/auth.js
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const forgotForm = document.getElementById('forgotForm');
    if (!forgotForm) return; // Exit if form not on this page
    
    forgotForm.addEventListener('submit', async function(e) {
        e.preventDefault(); // ⭐ CRITICAL: prevents the GET to current page
        
        const emailField = document.getElementById('forgotEmail');
        const email = emailField.value.trim();
        const submitBtn = document.getElementById('forgotSubmitBtn');
        
        // Validate email
        if (!email) {
            showErrorModal('Please enter your email address.');
            return;
        }
        
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            showErrorModal('Please enter a valid email address.');
            return;
        }
        
        // Loading state
        submitBtn.disabled = true;
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Sending...';
        
        try {
            const formData = new FormData();
            formData.append('action', 'forgot_password');
            formData.append('email', email);
            
            // ⭐ Use relative path 'api/auth.php' - browser resolves from page URL
            // Since your auth.js is at /assets/js/auth.js loaded from root URL,
            // browser baseURL is the page URL, so 'api/auth.php' works
            const response = await fetch('api/auth.php', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error('Server returned status ' + response.status);
            }
            
            const text = await response.text();
            console.log('Raw response:', text); // Debug log
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (parseErr) {
                throw new Error('Invalid JSON response. Server said: ' + text.substring(0, 200));
            }
            
            if (data.success) {
                forgotForm.reset();
                const msgEl = document.getElementById('modalMessage');
                if (msgEl) {
                    msgEl.textContent = 'A password reset link has been sent to ' + email + '. Please check your inbox and spam folder.';
                }
                showSuccessModal();
            } else {
                showErrorModal(data.message || 'Failed to send reset link. Please try again.');
            }
        } catch (err) {
            console.error('Forgot password error:', err);
            showErrorModal('Network error: ' + err.message + '. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
    
    // Modal close handlers
    document.getElementById('closeModal')?.addEventListener('click', hideSuccessModal);
    document.getElementById('closeErrorModal')?.addEventListener('click', hideErrorModal);
    
    // Close on outside click
    document.getElementById('successModal')?.addEventListener('click', function(e) {
        if (e.target === this) hideSuccessModal();
    });
    document.getElementById('errorModal')?.addEventListener('click', function(e) {
        if (e.target === this) hideErrorModal();
    });
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideSuccessModal();
            hideErrorModal();
        }
    });
});

function showSuccessModal() {
    const modal = document.getElementById('successModal');
    if (modal) modal.style.display = 'flex';
}

function hideSuccessModal() {
    const modal = document.getElementById('successModal');
    if (modal) modal.style.display = 'none';
}

function showErrorModal(message) {
    const modal = document.getElementById('errorModal');
    const msgEl = document.getElementById('errorMessage');
    if (msgEl) msgEl.textContent = message;
    if (modal) modal.style.display = 'flex';
}

function hideErrorModal() {
    const modal = document.getElementById('errorModal');
    if (modal) modal.style.display = 'none';
}
