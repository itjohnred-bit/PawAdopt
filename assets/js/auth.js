'use strict';                                          // 1. MUST be the first statement

const API_BASE = window.location.origin + '/api/';

document.addEventListener('DOMContentLoaded', () => {

    const loginPanel    = document.getElementById('loginPanel');
    const registerPanel = document.getElementById('registerPanel');
    const forgotPanel   = document.getElementById('forgotPanel');
    const authLeft      = document.querySelector('.auth-left');

    function showPanel(panel) {
        [loginPanel, registerPanel, forgotPanel].forEach(p => p?.classList.remove('active'));
        panel?.classList.add('active');
        if (panel === registerPanel || panel === forgotPanel) {
            authLeft?.classList.add('dark-mode');
        } else {
            authLeft?.classList.remove('dark-mode');
        }
    }

    document.getElementById('goRegister')?.addEventListener('click', () => showPanel(registerPanel));
    document.getElementById('goLogin')?.addEventListener('click',    () => showPanel(loginPanel));
    document.getElementById('goForgot')?.addEventListener('click',   () => showPanel(forgotPanel));
    document.getElementById('forgotBack')?.addEventListener('click', () => showPanel(loginPanel));

    showPanel(loginPanel);

    // ---- role selector ----
    document.querySelectorAll('.role-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const role = btn.dataset.role;
            const activePanel = document.querySelector('.auth-panel.active');
            const roleInput = activePanel?.querySelector('input[name="role"]'); // 2. added ?.

            if (roleInput) {
                roleInput.value = role;
                console.log("Role changed to:", role);
            }
        });
    });

    // ---- eye toggle (SINGLE listener — removed the duplicate block at the bottom) ----
    document.querySelectorAll('.eye-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.target);
            if (!target) return;
            const icon = btn.querySelector('i');

            if (target.type === 'password') {
                target.type = 'text';
                icon?.classList.remove('fa-eye');
                icon?.classList.add('fa-eye-slash');
            } else {
                target.type = 'password';
                icon?.classList.remove('fa-eye-slash');
                icon?.classList.add('fa-eye');
            }
        });
    });

    // ---- login ----
    const loginForm = document.getElementById('loginForm');
    loginForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors();

        const fd = new FormData(loginForm);
        const btn = loginForm.querySelector('[type=submit]');

        if (!fd.get('role')) {
            const activeRoleBtn = document.querySelector('.role-btn.active');
            if (activeRoleBtn) fd.set('role', activeRoleBtn.dataset.role);
        }

        btn.disabled = true;
        btn.textContent = 'Signing in…';

        const res = await postForm(API_BASE + 'auth.php?action=login', fd);  // 3. consistent URL

        btn.disabled = false;
        btn.textContent = 'Sign In';

        if (res.success) {
            showAlert(loginPanel, 'Logged in! Redirecting…', 'success');
            setTimeout(() => window.location.href = res.data.redirect, 800);
        } else {
            showAlert(loginPanel, res.message, 'error');
        }
    });

    // ---- register ----
    const registerForm = document.getElementById('registerForm');
    registerForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors();

        const fd = new FormData(registerForm);
        const btn = registerForm.querySelector('[type=submit]');

        btn.disabled = true;
        btn.textContent = 'Creating account…';

        const res = await postForm(API_BASE + 'auth.php?action=register', fd);  // 3. consistent URL

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
        const res = await fetch(url, { method: 'POST', body: formData });
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
