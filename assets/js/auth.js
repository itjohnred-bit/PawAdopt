const API_BASE = window.location.origin + '/api/';

/* PAWAdopt – Auth Page JavaScript (privacy/login/register flow) */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const loginPanel = document.getElementById('loginPanel');
    const registerPanel = document.getElementById('registerPanel');
    const forgotPanel = document.getElementById('forgotPanel');
    const authLeft = document.querySelector('.auth-left');

    function showPanel(panel) {
        [loginPanel, registerPanel, forgotPanel].forEach(p => p?.classList.remove('active'));
        panel?.classList.add('active');

        if (panel === registerPanel || panel === forgotPanel) {
            authLeft?.classList.add('dark-mode');
        } else {
            authLeft?.classList.remove('dark-mode');
        }

        document.dispatchEvent(new CustomEvent('pawadopt:panelchange', {
            detail: { panelId: panel?.id || null }
        }));
    }

    function requestRegisterPanel(event) {
        event?.preventDefault();
        showPanel(registerPanel);
        setTimeout(() => document.getElementById('regUsername')?.focus(), 80);
    }

    window.pawAuthShowPanel = showPanel;
    window.pawAuthShowLogin = () => showPanel(loginPanel);
    window.pawAuthShowRegister = () => showPanel(registerPanel);
    window.pawAuthShowForgot = () => showPanel(forgotPanel);

    document.getElementById('goRegister')?.addEventListener('click', requestRegisterPanel);
    document.getElementById('goLogin')?.addEventListener('click', (e) => {
        e.preventDefault();
        showPanel(loginPanel);
    });
    document.getElementById('goForgot')?.addEventListener('click', (e) => {
        e.preventDefault();
        showPanel(forgotPanel);
    });
    document.getElementById('forgotBack')?.addEventListener('click', (e) => {
        e.preventDefault();
        showPanel(loginPanel);
    });

    showPanel(loginPanel);

    document.querySelectorAll('.role-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const group = btn.parentElement;
            group?.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const role = btn.getAttribute('data-role');
            const activePanel = btn.closest('.auth-panel') || document.querySelector('.auth-panel.active');
            const roleInput = activePanel?.querySelector('input[name="role"]');

            if (roleInput) {
                roleInput.value = role;
            }
        });
    });

    document.querySelectorAll('.eye-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.target);
            if (!target) return;

            if (target.type === 'password') {
                target.type = 'text';
                btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                target.type = 'password';
                btn.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    });

    const loginForm = document.getElementById('loginForm');
    loginForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors();

        const fd = new FormData(loginForm);
        const btn = loginForm.querySelector('[type=submit]');

        if (!fd.get('role')) {
            const activeRoleBtn = loginPanel?.querySelector('.role-btn.active');
            if (activeRoleBtn) fd.set('role', activeRoleBtn.dataset.role);
        }

        btn.disabled = true;
        btn.textContent = 'Signing in…';
        const res = await postForm('api/auth.php?action=login', fd);
        btn.disabled = false;
        btn.textContent = 'Sign In';

        if (res.success) {
            showAlert(loginPanel, 'Logged in! Redirecting…', 'success');
            setTimeout(() => window.location.href = res.data.redirect, 800);
        } else {
            showAlert(loginPanel, res.message, 'error');
        }
    });

    const registerForm = document.getElementById('registerForm');
    registerForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors();

        const hidden = document.getElementById('privacyAcceptedField');
        if (hidden && hidden.value !== '1') {
            if (typeof window.openPrivacyGate === 'function') {
                window.openPrivacyGate({ openRegister: true });
            }
            showAlert(registerPanel, 'Please agree to the Privacy Policy first.', 'error');
            return;
        }

        const fd = new FormData(registerForm);
        const btn = registerForm.querySelector('[type=submit]');
        btn.disabled = true;
        btn.textContent = 'Creating account…';

        const res = await postForm('api/auth.php?action=register', fd);
        btn.disabled = false;
        btn.textContent = 'Sign Up';

        if (res.success) {
            showAlert(registerPanel, 'Account created!', 'success');
            setTimeout(() => window.location.href = res.data.redirect, 1000);
        } else {
            showAlert(registerPanel, res.message, 'error');
        }
    });
});

async function postForm(url, formData) {
    try {
        const res = await fetch(url, {
            method: 'POST',
            body: formData
        });
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON from server:', text);
            return { success: false, message: 'Server error. Please check console.' };
        }
    } catch (err) {
        return { success: false, message: 'Network error.' };
    }
}

function showAlert(panel, message, type) {
    let existing = panel?.querySelector('.auth-alert');
    if (!existing) {
        existing = document.createElement('div');
        panel?.insertBefore(existing, panel.firstChild);
    }
    existing.className = `auth-alert ${type}`;
    existing.innerHTML = `${type === 'success' ? '✓' : '⚠'} ${message}`;
}

function clearErrors() {
    document.querySelectorAll('.auth-alert').forEach(el => el.remove());
}
