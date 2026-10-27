<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';
startSession();

if (isLoggedIn()) {
    $user = getCurrentUser();
    $role = strtoupper($user['role']);
    
    if ($role === 'ADOPTER') {
        header('Location: ' . APP_URL . '/pages/adopter/dashboard.php');
    } else if ($role === 'SHELTER' || $role === 'VETERINARY') {
        header('Location: ' . APP_URL . '/pages/shelter/dashboard.php');
    } else if ($role === 'ADMIN') {
        header('Location: ' . APP_URL . '/pages/admin/dashboard.php');
    } else {

        header('Location: ' . APP_URL . '/pages/' . strtolower($role) . '/dashboard.php');
    }
    exit;
}

$db = Database::getInstance();
$aboutContent = $db->fetch("SELECT content_value FROM site_content WHERE content_key = 'about_text'");
$aboutText = $aboutContent ? $aboutContent['content_value'] : 'Paw-Adopt connects loving adopters with shelters. Browse, apply, and change a life today!';

$urlError = $_GET['error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pawbot_chat') {
    header('Content-Type: application/json; charset=UTF-8');

    $message = trim((string)($_POST['message'] ?? ''));
    if ($message === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Please enter a message first.']);
        exit;
    }

    // SECURITY: the Gemini key lives only in a server-side environment variable
    // (GEMINI_API_KEY). It is never hardcoded and never sent to the browser.
    $apiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? $_SERVER['GEMINI_API_KEY'] ?? '');
    if ($apiKey === '') {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'AI assistant is not configured. Add GEMINI_API_KEY to the server environment.']);
        exit;
    }



    $systemPrompt = "You are PawBot, the AI assistant for Paw-Adopt. Keep replies short, warm, and practical. Only answer about Paw-Adopt, pet adoption, login/registration help, adopter workflows, veterinary workflows, and the content visible on this page. If asked something unrelated, politely redirect back to Paw-Adopt. Use plain text only. Mention when login is required for account-only features. Current About text: " . strip_tags((string)$aboutText);

    $payload = [
        'systemInstruction' => [
            'parts' => [
                ['text' => $systemPrompt]
            ]
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $message]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 280
        ]
    ];

    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . rawurlencode($apiKey);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 25,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Gemini request failed: ' . $curlError]);
        exit;
    }

    $decoded = json_decode($response, true);
    $reply = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if ($reply === '') {
        $apiError = $decoded['error']['message'] ?? 'No response returned from Gemini.';
        http_response_code($statusCode > 0 ? $statusCode : 502);
        echo json_encode(['ok' => false, 'error' => $apiError]);
        exit;
    }

    echo json_encode(['ok' => true, 'reply' => trim($reply)]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAWAdopt – Find Your Forever Friend</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/dark-mode.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="auth-body">

<!-- Background decorative paws -->
<div class="bg-paws">
    <?php for ($i = 0; $i < 12; $i++):
        $tops  = [5,15,25,35,45,55,65,75,85,10,30,70];
        $lefts = [3,12,22,35,48,60,73,82,90,8,55,40];
        $anims = [0,2,4,6,8,3,5,1,7,9,2,6];
    ?>
    <div class="bg-paw" style="top:<?= $tops[$i] ?>%;left:<?= $lefts[$i] ?>%;animation-delay:<?= $anims[$i] ?>s">🐾</div>
    <?php endfor; ?>
</div>

<div class="auth-container">

    <!-- ════ LEFT PANEL ════ -->
    <div class="auth-left" id="authLeft">

        <!-- ── LOGIN PANEL ── -->
        <div class="auth-panel active" id="loginPanel">
            <div class="auth-logo">
                <img src="assets/images/Paw-Adopt Logo.png" alt="Paw-Adopt Logo">
            </div>

            <?php if ($urlError === 'unauthorized'): ?>
            <div class="auth-alert error"><i class="fas fa-exclamation-circle"></i> You are not authorized for that page.</div>
            <?php endif; ?>

            <div class="auth-role-label">Sign in as</div>
            <div class="role-toggle">
                <button type="button" class="role-btn active" data-role="ADOPTER">🐶 Adopter</button>
                <button type="button" class="role-btn"        data-role="SHELTER">🏠 Veterinary</button>
                <button type="button" class="role-btn"        data-role="ADMIN">🛡️ Admin</button>
            </div>

            <form id="loginForm" autocomplete="on" style="width:100%;display:flex;flex-direction:column;align-items:center;">
                <input type="hidden" name="role" id="loginRole" value="ADOPTER">
                
                <div class="auth-input-group">
                    <i class="fas fa-user auth-input-icon"></i>
                    <input type="text" name="username" id="loginUser" class="auth-input" placeholder="Username or Email" required autocomplete="username">
                </div>
                <div class="auth-input-group">
                    <i class="fas fa-lock auth-input-icon"></i>
                    <input type="password" name="password" id="loginPass" class="auth-input" placeholder="Password" required autocomplete="current-password">
                    <button type="button" class="eye-toggle" data-target="loginPass"><i class="fas fa-eye"></i></button>
                </div>
                <div class="auth-row">
                    <label class="remember-label">
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                    <a href="#" id="goForgot" style="font-size:.85rem;font-weight:700;color:#0f766e;text-decoration:none;">Forgot Password?</a>
                </div>
                <button type="submit" class="auth-submit-btn">Sign In</button>
            </form>

            <div class="auth-footer-links">
                <a href="#" id="goRegister">✨ New User? Create Account</a>
                <a href="#" onclick="showTerms(); return false;">Terms &amp; Conditions</a>
                <a href="#" onclick="openPrivacyGate(); return false;">Privacy Policy</a>
                <a href="#" onclick="showContact(); return false;">Contact</a>
            </div>
            <div class="auth-decor-bone"></div>
        </div>

            <!-- ── REGISTER PANEL ── -->
        <div class="auth-panel" id="registerPanel">
            <div class="auth-logo-label">Create Account</div>

            <div class="auth-role-label">Register as</div>
            <div class="role-toggle">
                <button type="button" class="role-btn active" data-role="ADOPTER">🐶 Adopter</button>
              
            </div>

            <form id="registerForm" novalidate style="width:100%;display:flex;flex-direction:column;align-items:center;">
                <input type="hidden" name="role" id="registerRole" value="ADOPTER">
                <!-- Server should also log this; the form is rejected if it isn't "1" -->
                <input type="hidden" name="privacy_accepted" id="privacyAcceptedField" value="0">

                <div class="auth-input-group">
                    <i class="fas fa-user auth-input-icon"></i>
                    <input type="text" name="username" id="regUsername" class="auth-input"
                           placeholder="Username (3–20 chars, letters/numbers/_)" required
                           minlength="3" maxlength="20" pattern="^[A-Za-z0-9_]{3,20}$" disabled>
                </div>

                <div class="auth-input-group">
                    <i class="fas fa-envelope auth-input-icon"></i>
                    <input type="email" name="email" id="regEmail" class="auth-input"
                           placeholder="Email Address" required disabled>
                    <small id="regEmailMsg" class="pa-field-msg"></small>
                </div>

                <div class="auth-input-group">
                    <i class="fas fa-lock auth-input-icon"></i>
                    <input type="password" name="password" id="regPass" class="auth-input"
                           placeholder="Password" required minlength="8" disabled>
                    <button type="button" class="eye-toggle" data-target="regPass"><i class="fas fa-eye"></i></button>
                </div>

                <!-- Strength meter + rule checklist (aria-live so screen-readers update) -->
                <div class="pw-meter-wrap" aria-live="polite">
                    <div class="pw-meter"><span id="pwMeterBar" class="pw-meter-bar"></span></div>
                    <div class="pw-meter-label" id="pwMeterLabel">Strength: —</div>
                    <ul class="pw-rules" id="pwRules">
                        <li class="pw-rule" data-rule="length">8+ characters</li>
                        <li class="pw-rule" data-rule="upper">1 uppercase letter</li>
                        <li class="pw-rule" data-rule="lower">1 lowercase letter</li>
                        <li class="pw-rule" data-rule="digit">1 number</li>
                        <li class="pw-rule" data-rule="symbol">1 symbol (!@#$…)</li>
                    </ul>
                </div>

                <div class="auth-input-group">
                    <i class="fas fa-lock auth-input-icon"></i>
                    <input type="password" name="confirm_password" id="regConfirmPass" class="auth-input"
                           placeholder="Confirm Password" required minlength="8" disabled>
                    <button type="button" class="eye-toggle" data-target="regConfirmPass"><i class="fas fa-eye"></i></button>
                    <small id="regMatchMsg" class="pa-field-msg"></small>
                </div>

                <button type="submit" class="auth-submit-btn" id="regSubmit" disabled>Sign Up 🐾</button>
            </form>

            <div class="auth-footer-links">
                <a href="#" id="goLogin">← Back to Sign In</a>
                <a href="#" onclick="showTerms(); return false;">Terms &amp; Conditions</a>
                <a href="#" onclick="openPrivacyGate(); return false;">Privacy Policy</a>
                <a href="#" onclick="showContact(); return false;">Contact</a>
            </div>
        </div>

        <!-- ── FORGOT PASSWORD PANEL ── -->
        <div class="auth-panel" id="forgotPanel">
            <div class="auth-logo">🔑</div>
            <div class="auth-logo-label">Reset Password</div>
            <p style="text-align:center;color:#6b7280;margin-bottom:20px;font-size:.9rem;">Enter your email, receive a 6-digit OTP, then create a new password.</p>
            <form id="forgotForm" style="width:100%;display:flex;flex-direction:column;align-items:center;">
                <div class="auth-input-group">
                    <i class="fas fa-envelope auth-input-icon"></i>
                    <input type="email" name="email" id="forgotEmail" class="auth-input" placeholder="Your email address" required>
                </div>
                <button type="button" class="auth-submit-btn" id="sendOtpBtn">Send OTP</button>

                <div id="forgotOtpFields" style="display:none;width:100%;">
                    <div class="auth-input-group">
                        <i class="fas fa-key auth-input-icon"></i>
                        <input type="text" name="otp" id="forgotOtp" class="auth-input" placeholder="6-digit OTP" inputmode="numeric" maxlength="6" pattern="\d{6}">
                    </div>
                    <div class="auth-input-group">
                        <i class="fas fa-lock auth-input-icon"></i>
                        <input type="password" name="password" id="forgotNewPass" class="auth-input" placeholder="New password" minlength="8">
                        <button type="button" class="eye-toggle" data-target="forgotNewPass"><i class="fas fa-eye"></i></button>
                    </div>
                    <div class="auth-input-group">
                        <i class="fas fa-lock auth-input-icon"></i>
                        <input type="password" name="confirm_password" id="forgotConfirmPass" class="auth-input" placeholder="Confirm new password" minlength="8">
                        <button type="button" class="eye-toggle" data-target="forgotConfirmPass"><i class="fas fa-eye"></i></button>
                    </div>
                    <small id="forgotResetMsg" class="pa-field-msg"></small>
                    <button type="submit" class="auth-submit-btn" id="resetPasswordBtn">Reset Password</button>
                </div>
            </form>
            <div class="auth-footer-links">
                <a href="#" id="forgotBack">← Back to Sign In</a>
                <a href="#" onclick="showContact(); return false;">Contact</a>
            </div>
        </div>

    </div>

    <!-- ════ RIGHT PANEL ════ -->
    <div class="auth-right">
        <h1 class="auth-right-title">ABOUT</h1>
        <p class="auth-right-text"><?= htmlspecialchars($aboutText) ?></p>
        <div class="auth-right-features">
            <div class="auth-right-feat"><span class="feat-icon">🔍</span> Browse thousands of adoptable pets</div>
            <div class="auth-right-feat"><span class="feat-icon">❤️</span> Save your favorite animals</div>
            <div class="auth-right-feat"><span class="feat-icon">📋</span> Easy adoption applications</div>
            <div class="auth-right-feat"><span class="feat-icon">💬</span> Chat directly with Veterinary Clinic</div>
        </div>
        <div class="auth-right-pets-row">🐶 🐱 </div>
    </div>

</div>

<!-- Terms Modal -->
<div id="termsModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:20px;max-width:560px;width:94%;padding:32px;max-height:80vh;overflow-y:auto;">
        <h2 style="color:#0f766e;margin-bottom:8px;">Terms &amp; Conditions</h2>
        <h3 style="color:#134e4a;margin-bottom:14px;font-size:1.2rem;">Paw-Adopt Terms of Use</h3>
        <p style="color:#374151;line-height:1.7;font-size:.92rem;margin-bottom:16px;">By browsing, applying, or contacting Paw-Adopt, users agree to use the platform respectfully and provide accurate information during the adoption process.</p>
        <div style="display:grid;gap:14px;">
            <div style="padding:15px 16px;background:#f0fdfa;border-radius:16px;">
                <strong style="display:block;color:#0f766e;margin-bottom:6px;">Responsible use</strong>
                <span style="color:#374151;line-height:1.65;font-size:.9rem;">Applications, inquiries, and account details must be truthful. Fraudulent, abusive, or harmful activity may be removed or blocked.</span>
            </div>
            <div style="padding:15px 16px;background:#f0fdfa;border-radius:16px;">
                <strong style="display:block;color:#0f766e;margin-bottom:6px;">Adoption decisions</strong>
                <span style="color:#374151;line-height:1.65;font-size:.9rem;">Veterinary partners and authorized personnel may review applications, request clarifications, and approve or decline matches based on animal welfare.</span>
            </div>
            <div style="padding:15px 16px;background:#f0fdfa;border-radius:16px;">
                <strong style="display:block;color:#0f766e;margin-bottom:6px;">Animal welfare first</strong>
                <span style="color:#374151;line-height:1.65;font-size:.9rem;">Paw-Adopt promotes safe, humane, and responsible rehoming. Users should not use the platform for resale, neglect, or unlawful purposes.</span>
            </div>
            <div style="padding:15px 16px;background:#f0fdfa;border-radius:16px;">
                <strong style="display:block;color:#0f766e;margin-bottom:6px;">Communication</strong>
                <span style="color:#374151;line-height:1.65;font-size:.9rem;">Support replies and veterinary coordination may happen through email, Facebook, or the official channels listed by Paw-Adopt.</span>
            </div>
        </div>
        <button type="button" onclick="closeTerms()" style="margin-top:20px;padding:10px 24px;background:#0d9488;color:#fff;border:none;border-radius:99px;font-weight:800;cursor:pointer;font-size:.95rem;">Close</button>
    </div>
</div>

<!-- Contact Modal -->
<div id="contactModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:20px;max-width:520px;width:94%;padding:32px;max-height:80vh;overflow-y:auto;">
        <h2 style="color:#0f766e;margin-bottom:16px;">Contact Paw-Adopt</h2>
        <p style="color:#374151;line-height:1.7;font-size:.9rem;">Need help with your account, adoption questions, or support concerns? Reach Paw-Adopt directly below.</p>
        <div style="margin-top:18px;padding:16px 18px;background:#f0fdfa;border-radius:16px;color:#134e4a;">
            <strong style="display:block;margin-bottom:6px;">Email</strong>
            pawsadopt.pup@gmail.com
        </div>
        <div style="margin-top:12px;padding:16px 18px;background:#f0fdfa;border-radius:16px;color:#134e4a;">
            <strong style="display:block;margin-bottom:6px;">Facebook</strong>
            facebook.com/pawadopt.pup
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:22px;">
            <button type="button" onclick="closeContact()" style="padding:10px 24px;background:#e5e7eb;color:#374151;border:none;border-radius:99px;font-weight:800;cursor:pointer;font-size:.95rem;">Close</button>
            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=pawsadopt.pup@gmail.com" target="_blank" rel="noopener noreferrer" style="padding:10px 24px;background:#0d9488;color:#fff;border-radius:99px;font-weight:800;font-size:.95rem;text-decoration:none;display:inline-flex;align-items:center;">Message via Email</a>
        </div>
    </div>
</div>

<script>
function showTerms() {
    document.getElementById('termsModal').style.display = 'flex';
}
function closeTerms() {
    document.getElementById('termsModal').style.display = 'none';
}
function showContact() {
    document.getElementById('contactModal').style.display = 'flex';
}
function closeContact() {
    document.getElementById('contactModal').style.display = 'none';
}
document.addEventListener('DOMContentLoaded', function(){
    ['termsModal', 'contactModal'].forEach(function(id){
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.addEventListener('click', function(e){
            if (e.target === modal) modal.style.display = 'none';
        });
    });
});
</script>

<script>
const API_BASE = window.location.origin + '/api/';

/*  PAWAdopt – Auth Page JavaScript (privacy/login/register flow) */
'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const loginPanel = document.getElementById('loginPanel');
    const registerPanel = document.getElementById('registerPanel');
    const forgotPanel = document.getElementById('forgotPanel');
    const authLeft = document.querySelector('.auth-left');

    function paintEyeButton(button, isVisible) {
        const icon = button?.querySelector('i');
        if (!button || !icon) return;
        button.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
        button.setAttribute('title', isVisible ? 'Hide password' : 'Show password');
        button.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
        icon.classList.remove('fa-eye', 'fa-eye-slash');
        icon.classList.add(isVisible ? 'fa-eye-slash' : 'fa-eye');
    }

    function bindSingleEyeToggle(targetId) {
        const input = document.getElementById(targetId);
        const button = document.querySelector('.eye-toggle[data-target="' + targetId + '"]');
        if (!input || !button) return;

        paintEyeButton(button, input.type === 'text');
        button.addEventListener('click', () => {
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            paintEyeButton(button, reveal);
        });
    }

    function bindPairedEyeToggle(firstId, secondId) {
        const first = document.getElementById(firstId);
        const second = document.getElementById(secondId);
        const buttons = [
            document.querySelector('.eye-toggle[data-target="' + firstId + '"]'),
            document.querySelector('.eye-toggle[data-target="' + secondId + '"]')
        ].filter(Boolean);

        if (!first || !second || !buttons.length) return;

        const sync = (visible) => buttons.forEach((button) => paintEyeButton(button, visible));
        sync(first.type === 'text');

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const reveal = first.type === 'password';
                first.type = reveal ? 'text' : 'password';
                second.type = reveal ? 'text' : 'password';
                sync(reveal);
            });
        });
    }

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

    bindSingleEyeToggle('loginPass');
    bindPairedEyeToggle('regPass', 'regConfirmPass');
    bindPairedEyeToggle('forgotNewPass', 'forgotConfirmPass');

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

    const forgotForm = document.getElementById('forgotForm');
    const forgotEmail = document.getElementById('forgotEmail');
    const sendOtpBtn = document.getElementById('sendOtpBtn');
    const forgotOtpFields = document.getElementById('forgotOtpFields');
    const forgotOtp = document.getElementById('forgotOtp');
    const forgotNewPass = document.getElementById('forgotNewPass');
    const forgotConfirmPass = document.getElementById('forgotConfirmPass');
    const forgotResetMsg = document.getElementById('forgotResetMsg');

    function paintForgotMatch() {
        if (!forgotResetMsg || !forgotNewPass || !forgotConfirmPass) return true;
        if (!forgotConfirmPass.value) {
            forgotResetMsg.textContent = '';
            forgotResetMsg.className = 'pa-field-msg';
            return false;
        }
        const ok = forgotNewPass.value === forgotConfirmPass.value;
        forgotResetMsg.textContent = ok ? '✓ Passwords match' : '✗ Passwords do not match';
        forgotResetMsg.className = 'pa-field-msg ' + (ok ? 'ok' : 'err');
        return ok;
    }

    forgotNewPass?.addEventListener('input', paintForgotMatch);
    forgotConfirmPass?.addEventListener('input', paintForgotMatch);

    sendOtpBtn?.addEventListener('click', async () => {
        clearErrors();
        const emailValue = forgotEmail?.value.trim() || '';
        if (!emailValue) {
            showAlert(forgotPanel, 'Enter your email address first.', 'error');
            return;
        }
        const fd = new FormData();
        fd.set('email', emailValue);
        sendOtpBtn.disabled = true;
        sendOtpBtn.textContent = 'Sending OTP…';
        const res = await postForm('api/auth.php?action=request_reset_otp', fd);
        sendOtpBtn.disabled = false;
        sendOtpBtn.textContent = 'Send OTP';
        if (res.success) {
            forgotOtpFields.style.display = 'block';
            showAlert(forgotPanel, res.message || 'If that email exists, an OTP has been sent.', 'success');
            setTimeout(() => forgotOtp?.focus(), 80);
        } else {
            showAlert(forgotPanel, res.message, 'error');
        }
    });

    forgotForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors();
        if (!forgotOtpFields || forgotOtpFields.style.display === 'none') {
            showAlert(forgotPanel, 'Send the OTP first.', 'error');
            return;
        }
        if (!paintForgotMatch()) {
            showAlert(forgotPanel, 'Passwords do not match.', 'error');
            return;
        }

        const fd = new FormData(forgotForm);
        const resetBtn = document.getElementById('resetPasswordBtn');
        resetBtn.disabled = true;
        resetBtn.textContent = 'Resetting…';
        const res = await postForm('api/auth.php?action=reset_password_otp', fd);
        resetBtn.disabled = false;
        resetBtn.textContent = 'Reset Password';

        if (res.success) {
            showAlert(forgotPanel, res.message || 'Password reset successful.', 'success');
            forgotForm.reset();
            forgotOtpFields.style.display = 'none';
            forgotResetMsg.textContent = '';
            setTimeout(() => showPanel(loginPanel), 1200);
        } else {
            showAlert(forgotPanel, res.message, 'error');
        }
    });
});

async function postForm(url, formData) {
    try {
        const res = await fetch(url, { method: 'POST', body: formData });
        const bodyText = await res.text();
        try {
            return JSON.parse(bodyText);
        } catch (e) {
            console.error('Invalid JSON from server:', bodyText);
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
</script>

<!-- ════ PAW-ADOPT AI ASSISTANT — GEMINI POWERED ════ -->
<style>
#pawAssistant{
    position:fixed !important;
    bottom:24px;
    right:24px;
    z-index:20000 !important;
    pointer-events:auto !important;
    isolation:isolate;
    font-family:'Nunito',sans-serif;
}
#pawAssistant *{ pointer-events:auto; }
#paLauncher{
    width:74px;
    height:74px;
    border-radius:24px;
    border:4px solid #0f766e;
    background:#fff;
    cursor:pointer;
    padding:6px;
    position:relative;
    box-shadow:0 12px 28px rgba(13,148,136,.35);
    transition:transform .2s, box-shadow .2s;
    touch-action:manipulation;
}
#paLauncher:hover{transform:translateY(-2px) scale(1.05);box-shadow:0 18px 36px rgba(13,148,136,.42)}
#paLauncher img,#paWindow .pa-header img{width:100%!important;height:100%!important;max-width:56px;max-height:56px;object-fit:contain;display:block}
.pa-notif-dot{position:absolute;top:-4px;right:-4px;width:16px;height:16px;background:#e11d48;border-radius:50%;border:2px solid #fff;animation:paPulse 1.6s infinite}
@keyframes paPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.25)}}
#paWindow{display:none;flex-direction:column;position:absolute;bottom:90px;right:0;width:360px;max-width:calc(100vw - 32px);height:520px;max-height:72vh;background:#fff;border-radius:24px;overflow:hidden;box-shadow:0 18px 56px rgba(0,0,0,.24);border:2px solid #99f6e4}
#paWindow.open{display:flex}
.pa-header{display:flex;align-items:center;gap:10px;padding:12px 14px;background:linear-gradient(135deg,#0d9488,#0f766e);color:#fff}
.pa-header img{width:42px;height:42px;border-radius:12px;background:#fff;padding:4px}
.pa-header-text{flex:1;display:flex;flex-direction:column;line-height:1.2;font-size:.9rem}
.pa-status{font-size:.72rem;opacity:.92}
.pa-badge{display:inline-flex;align-items:center;gap:6px;margin-top:3px;font-size:.66rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#ccfbf1}
#paClose{background:rgba(255,255,255,.15);border:none;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer}
.pa-messages{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:10px;background:#f0fdfa}
.pa-msg{max-width:84%;padding:10px 14px;border-radius:16px;font-size:.87rem;line-height:1.55;white-space:pre-line}
.pa-msg.bot{background:#fff;color:#1f2937;border:1px solid #ccfbf1;border-bottom-left-radius:4px;align-self:flex-start}
.pa-msg.user{background:#0d9488;color:#fff;border-bottom-right-radius:4px;align-self:flex-end}
.pa-msg.error{background:#fff1f2;color:#9f1239;border:1px solid #fecdd3}
.pa-quick{display:flex;flex-wrap:wrap;gap:6px;padding:8px 12px;background:#f0fdfa;border-top:1px solid #ccfbf1}
.pa-chip{font-size:.75rem;font-weight:700;color:#0f766e;background:#fff;border:1.5px solid #5eead4;border-radius:99px;padding:5px 12px;cursor:pointer}
.pa-chip:hover{background:#0d9488;color:#fff}
.pa-input-row{display:flex;gap:8px;padding:10px 12px;border-top:1px solid #e5e7eb;background:#fff}
.pa-input-row input{flex:1;border:2px solid #ccfbf1;border-radius:99px;padding:9px 16px;font-size:.88rem;outline:none}
.pa-input-row button{width:42px;height:42px;border:none;border-radius:50%;background:#0d9488;color:#fff;cursor:pointer}
.pa-input-row button:disabled,.pa-chip:disabled{opacity:.6;cursor:not-allowed}
.pa-note{padding:0 14px 10px;font-size:.72rem;color:#6b7280;background:#fff}
</style>

<div id="pawAssistant" role="complementary" aria-label="PawBot assistant">
    <button id="paLauncher" type="button" aria-label="Open Paw-Adopt Assistant">
        <img src="<?= APP_URL ?>/assets/images/assistant.png" alt="Assistant">
        <span class="pa-notif-dot"></span>
    </button>
    <div id="paWindow">
        <div class="pa-header">
            <img src="<?= APP_URL ?>/assets/images/assistant.png" alt="PawBot">
            <div class="pa-header-text">
                <strong>PawBot</strong>
                <span class="pa-status">🟢 Online — ask me anything!</span>
                <span class="pa-badge"><i class="fa-solid fa-sparkles"></i> Gemini AI powered</span>
            </div>
            <button id="paClose" type="button" aria-label="Close">✕</button>
        </div>
        <div class="pa-messages" id="paMessages"></div>
        <div class="pa-quick" id="paQuick"></div>
        <form class="pa-input-row" id="paForm" autocomplete="off">
            <input type="text" id="paInput" placeholder="Ask about adoption, login, or support..." maxlength="240">
            <button type="submit" id="paSendBtn" aria-label="Send"><i class="fa-solid fa-paper-plane"></i></button>
        </form>
        <div class="pa-note">Server-side Gemini setup required: add <strong>GEMINI_API_KEY</strong> to your server environment.</div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const FAQ = [
        { q: ['adopt', 'adoption process', 'how to adopt', 'apply'], a: '🐾 Adoption is easy!\n1. Create an Adopter account\n2. Browse available pets\n3. Click a pet and submit an adoption application\n4. The veterinary or shelter reviews it\n5. Once approved, arrange the meet-up and take your friend home!' },
        { q: ['register', 'sign up', 'create account', 'new user'], a: 'Click Create Account on the login panel. Review the privacy notice first, then complete the Adopter or Veterinary registration form.' },
        { q: ['login', 'sign in', 'log in'], a: 'Select your role, enter your username or email and password, then press Sign In.' },
        { q: ['forgot', 'password', 'reset'], a: 'Use the Reset Password option, enter your email, request a 6-digit OTP, then submit the OTP with your new password.' },
        { q: ['role', 'roles', 'adopter', 'veterinary', 'shelter', 'admin'], a: 'Paw-Adopt has 3 roles: Adopter, Veterinary or Shelter, and Admin.' },
        { q: ['browse', 'search', 'find pet', 'pets', 'dog', 'cat'], a: 'After signing in as an Adopter, go to your dashboard to browse available pets and save favorites.' },
        { q: ['favorite', 'save', 'heart'], a: 'Tap the heart icon on any pet listing to save it to your favorites.' },
        { q: ['chat', 'message', 'contact', 'talk'], a: 'Adopters can chat directly with the Veterinary Clinic through the built-in messaging feature once logged in.' },
        { q: ['application', 'status', 'track'], a: 'You can track your adoption applications from your Adopter dashboard.' },
        { q: ['terms', 'conditions'], a: 'You can open the Terms and Conditions popup from the login page links.' },
        { q: ['privacy', 'privacy policy', 'data'], a: 'The Privacy Policy popup appears when the page opens and before account creation.' },
        { q: ['about', 'what is', 'paw adopt', 'pawadopt'], a: 'Paw-Adopt connects loving adopters with shelters and veterinary partners.' },
        { q: ['hi', 'hello', 'hey'], a: 'Hello! I’m PawBot 🐾 Ask me about adoption, login, registration, support, or Paw-Adopt features.' },
        { q: ['thank', 'thanks'], a: 'You’re welcome! 🐾' }
    ];
    const FALLBACK = 'I’m not sure about that yet. Try asking about adoption, registration, login, privacy, terms, or applications.';
    const QUICK = ['How do I adopt?', 'Create account', 'Forgot password', 'User roles', 'Track application'];

    const launcher = document.getElementById('paLauncher');
    const win = document.getElementById('paWindow');
    const msgs = document.getElementById('paMessages');
    const quick = document.getElementById('paQuick');
    const form = document.getElementById('paForm');
    const input = document.getElementById('paInput');
    const closeBtn = document.getElementById('paClose');
    const sendBtn = document.getElementById('paSendBtn');
    if (!launcher || !win || !msgs || !quick || !form || !input || !closeBtn || !sendBtn) return;

    const dot = launcher.querySelector('.pa-notif-dot');
    const endpoint = window.location.pathname + window.location.search;
    let greeted = false;
    let busy = false;
    let warnedFallback = false;

    function addMsg(text, who) {
        const d = document.createElement('div');
        d.className = 'pa-msg ' + who;
        d.textContent = text;
        msgs.appendChild(d);
        msgs.scrollTop = msgs.scrollHeight;
        return d;
    }

    function answer(query) {
        const norm = String(query || '').toLowerCase().replace(/[^\w\s]/g, '');
        const words = norm.split(/\s+/).filter(Boolean);
        let best = null;
        let bestScore = 0;
        for (const item of FAQ) {
            let score = 0;
            for (const key of item.q) {
                if (norm.includes(key)) score += key.length * 2;
                for (const w of key.split(/\s+/)) if (words.includes(w)) score += w.length;
            }
            if (score > bestScore) { bestScore = score; best = item; }
        }
        return bestScore >= 3 ? best.a : FALLBACK;
    }

    function setBusy(state) {
        busy = !!state;
        input.disabled = busy;
        sendBtn.disabled = busy;
        quick.querySelectorAll('button').forEach(btn => { btn.disabled = busy; });
    }

    function runLocalAction(text) {
        const q = String(text || '').toLowerCase();
        if (q.includes('create account') || q.includes('register') || q.includes('sign up')) {
            document.getElementById('goRegister')?.click();
        } else if (q.includes('terms')) {
            showTerms();
        } else if (q.includes('contact') || q.includes('support')) {
            showContact();
        }
    }

    async function askGemini(message) {
        const typing = addMsg('PawBot is thinking…', 'bot');
        setBusy(true);
        try {
            const body = new URLSearchParams();
            body.set('action', 'pawbot_chat');
            body.set('message', message);
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body.toString()
            });
            const responseText = await res.text();
            let data;
            try { data = JSON.parse(responseText); }
            catch (error) { throw new Error('Invalid JSON response'); }
            typing.remove();
            if (!res.ok || !data.ok) throw new Error(data?.error || 'Gemini is unavailable right now.');
            addMsg(data.reply, 'bot');
        } catch (error) {
            typing.remove();
            addMsg(answer(message), 'bot');
            if (!warnedFallback) {
                warnedFallback = true;
                addMsg('Gemini is not available yet, so I answered using PawBot’s built-in help responses.', 'error');
            }
        } finally {
            runLocalAction(message);
            setBusy(false);
            input.focus();
        }
    }

    function toggleBot(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        win.classList.toggle('open');
        if (dot) dot.style.display = 'none';
        if (win.classList.contains('open') && !greeted) {
            greeted = true;
            addMsg('Hi! I’m PawBot 🐾 Ask me about adoption, registration, support, or Paw-Adopt features.', 'bot');
        }
        if (win.classList.contains('open')) input.focus();
        return false;
    }

    window.togglePawBot = toggleBot;
    launcher.addEventListener('click', toggleBot);
    launcher.addEventListener('touchstart', toggleBot, { passive: false });
    closeBtn.addEventListener('click', function (event) {
        event.preventDefault();
        win.classList.remove('open');
    });
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        const text = input.value.trim();
        if (!text || busy) return;
        addMsg(text, 'user');
        input.value = '';
        askGemini(text);
    });
    QUICK.forEach(function (label) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'pa-chip';
        b.textContent = label;
        b.addEventListener('click', function () {
            if (busy) return;
            addMsg(label, 'user');
            askGemini(label);
        });
        quick.appendChild(b);
    });
})();
</script>
<!-- ════ END ASSISTANT ════ -->
<!-- ════ REGISTER VALIDATION + PRIVACY GATE (self-contained) ════ -->
<style>
.pa-field-msg{display:block;width:90%;font-size:.75rem;font-weight:700;margin-top:4px;min-height:14px}
.pa-field-msg.ok{color:#0f766e}.pa-field-msg.err{color:#e11d48}
.eye-toggle{display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border:none;border-radius:12px;background:transparent;color:#64748b;cursor:pointer;transition:all .2s ease}
.eye-toggle:hover{background:rgba(13,148,136,.08);color:#0f766e}
.eye-toggle i{pointer-events:none}

.pw-meter-wrap{width:90%;margin:6px 0 10px;font-size:.78rem;font-family:'Nunito',sans-serif}
.pw-meter{height:8px;background:#e5e7eb;border-radius:99px;overflow:hidden;margin-bottom:6px}
.pw-meter-bar{display:block;height:100%;width:0%;border-radius:99px;transition:width .25s ease,background .25s ease}
.pw-meter-bar.weak{background:#e11d48}.pw-meter-bar.fair{background:#f59e0b}
.pw-meter-bar.good{background:#0d9488}.pw-meter-bar.strong{background:#10b981}
.pw-meter-label{font-weight:700;color:#1f2937;margin-bottom:6px}
.pw-rules{list-style:none;padding:0;margin:0;display:grid;grid-template-columns:1fr 1fr;gap:2px 8px;font-size:.72rem}
.pw-rule{color:#9ca3af;transition:color .15s}.pw-rule.ok{color:#0f766e}

.pa-privacy-gate{position:fixed;inset:0;background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;
  z-index:10001;padding:20px;font-family:'Nunito',sans-serif}
.pa-privacy-gate.show{display:flex;animation:paFade .2s ease}
@keyframes paFade{from{opacity:0}to{opacity:1}}
.pa-privacy-card{background:#fff;border-radius:20px;max-width:460px;width:100%;padding:28px;
  box-shadow:0 20px 50px rgba(0,0,0,.35);max-height:88vh;overflow-y:auto}
.pa-privacy-head{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.pa-privacy-icon{font-size:26px}
.pa-privacy-head h2{margin:0;color:#0f766e;font-size:1.2rem}
.pa-privacy-list{padding-left:18px;font-size:.85rem;line-height:1.55;color:#374151}
.pa-privacy-list li{margin-bottom:6px}
.pa-privacy-check{display:flex;gap:10px;align-items:flex-start;margin-top:14px;
  font-size:.82rem;color:#374151;line-height:1.4;cursor:pointer}
.pa-privacy-check input{margin-top:3px}
.pa-privacy-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px}
.pa-btn{padding:9px 18px;border-radius:99px;font-weight:800;font-size:.85rem;border:none;cursor:pointer}
.pa-btn-ghost{background:#f3f4f6;color:#374151}.pa-btn-ghost:hover{background:#e5e7eb}
.pa-btn-primary{background:#0d9488;color:#fff}.pa-btn-primary:hover:not(:disabled){background:#0f766e}
.pa-btn-primary:disabled{background:#9ca3af;cursor:not-allowed}
.pa-mini-note{margin-top:12px;padding:12px 14px;border-radius:14px;background:#ecfeff;color:#155e75;font-size:.8rem;line-height:1.55}
</style>

<script>
(function(){
'use strict';

var VERIFIED_EMAIL_DOMAINS = [
  'gmail.com','icloud.com','outlook.com','yahoo.com','hotmail.com',
  'aol.com','proton.me','protonmail.com','zoho.com','comcast.net','msn.com'
];

var DISPOSABLE_DOMAINS = [
  'mailinator.com','tempmail.com','temp-mail.org','10minutemail.com',
  'guerrillamail.com','yopmail.com','sharklasers.com','trashmail.com',
  'trashmail.de','trashmail.io','fakeinbox.com','getnada.com','maildrop.cc',
  'dispostable.com','mintemail.com','spamgourmet.com','mailnesia.com',
  'tempr.email','tempinbox.com','throwawaymail.com','jetable.org',
  'spam4.me','grr.la','mailcatch.com','mohmal.com','tempemail.com','spambox.us'
];

/* ─── Find the register panel (bail out cleanly if absent) ─ */
var panel = document.getElementById('registerPanel');
if (!panel) return;
var form       = document.getElementById('registerForm');
if (!form) return;
var email      = document.getElementById('regEmail');
var emailMsg   = document.getElementById('regEmailMsg');
var pass       = document.getElementById('regPass');
var confirm    = document.getElementById('regConfirmPass');
var matchMsg   = document.getElementById('regMatchMsg');
var submitBtn  = document.getElementById('regSubmit');
var meterBar   = document.getElementById('pwMeterBar');
var meterLabel = document.getElementById('pwMeterLabel');
var rules      = document.querySelectorAll('#pwRules .pw-rule');
var consent    = document.getElementById('privacyAcceptedField');
var loginPanel = document.getElementById('loginPanel');
var registerViewEmailNoticeShown = false;
var registerAfterConsent = false;

/* ─── 1. EMAIL VALIDATION  (regex + giant-provider whitelist) ───────── */
var EMAIL_RE = /^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/;
function validateEmail(raw){
  var v = (raw || '').trim().toLowerCase();
  if (!v)             return {ok:false, msg:''};
  if (v.length>254)   return {ok:false, msg:'Email is too long.'};
  if (!EMAIL_RE.test(v)) return {ok:false, msg:'Please enter a valid email address.'};
  var dom = v.split('@')[1];
  if (DISPOSABLE_DOMAINS.indexOf(dom) !== -1)
    return {ok:false, msg:'Disposable / temporary email addresses are not allowed.'};
  if (VERIFIED_EMAIL_DOMAINS.indexOf(dom) === -1)
    return {ok:false, msg:'Use a supported provider only: ' + VERIFIED_EMAIL_DOMAINS.join(', ')};
  return {ok:true, msg:'✓ Email provider verified'};
}
function paintEmail(){
  var r = validateEmail(email.value);
  emailMsg.textContent = r.msg;
  emailMsg.className   = 'pa-field-msg ' + (r.ok ? 'ok' : (email.value ? 'err' : ''));
  return r.ok;
}

/* ─── 2. PASSWORD STRENGTH METER  (5 rules, live) ────────── */
function scorePassword(p){
  var s = {length:false,upper:false,lower:false,digit:false,symbol:false};
  if (p.length >= 8)              s.length = true;
  if (/[A-Z]/.test(p))            s.upper  = true;
  if (/[a-z]/.test(p))            s.lower  = true;
  if (/\d/.test(p))               s.digit  = true;
  if (/[^A-Za-z0-9]/.test(p))     s.symbol = true;
  var passed = (s.length?1:0)+(s.upper?1:0)+(s.lower?1:0)+(s.digit?1:0)+(s.symbol?1:0);
  return {rules:s, score:passed, total:5};
}
function paintStrength(){
  var r = scorePassword(pass.value);
  meterBar.style.width = (r.score/r.total*100)+'%';
  meterBar.className   = 'pw-meter-bar '+
    ['weak','weak','fair','fair','good','strong'][r.score];
  meterLabel.textContent = 'Strength: '+
    ['—','Very Weak','Weak','Fair','Good','Strong'][r.score];
  rules.forEach(function(li){
    var rule = li.getAttribute('data-rule');
    var on   = !!r.rules[rule];
    li.classList.toggle('ok', on);
    var label = li.getAttribute('data-rule')==='length' ? '8+ characters'
             : li.getAttribute('data-rule')==='upper'  ? '1 uppercase letter'
             : li.getAttribute('data-rule')==='lower'  ? '1 lowercase letter'
             : li.getAttribute('data-rule')==='digit'  ? '1 number'
             : '1 symbol (!@#$…)';
    li.textContent = (on ? '✓ ' : '✗ ') + label;
  });
  paintMatch();
  gateSubmit();
  return r;
}
function paintMatch(){
  if (!confirm.value) { matchMsg.textContent=''; matchMsg.className='pa-field-msg'; return false; }
  var ok = pass.value === confirm.value;
  matchMsg.textContent = ok ? '✓ Passwords match' : '✗ Passwords do not match';
  matchMsg.className   = 'pa-field-msg ' + (ok ? 'ok' : 'err');
  return ok;
}

/* ─── 3. SUBMIT GATING ────────────────────────────────────── */
function gateSubmit(){
  var pw     = scorePassword(pass.value).score >= 4;   // length + 3 of 4 classes
  var mail   = email.value ? validateEmail(email.value).ok : false;
  var match  = pass.value && pass.value === confirm.value;
  var agreed = consent.value === '1';
  submitBtn.disabled = !(pw && mail && match && agreed);
}

/* ─── 4. PRIVACY + ASSURANCE MODALS ──────────────────────── */
function ensureGate(){
  if (document.getElementById('privacyGateModal')) return document.getElementById('privacyGateModal');
  var m = document.createElement('div');
  m.id = 'privacyGateModal';
  m.className = 'pa-privacy-gate';
  m.innerHTML =
    '<div class="pa-privacy-card">'+
      '<div class="pa-privacy-head"><span class="pa-privacy-icon">🛡️</span><h2>Privacy &amp; Data Notice</h2></div>'+
      '<p>Before creating an account, please review how Paw-Adopt handles your data.</p>'+
      '<ul class="pa-privacy-list">'+
        '<li><b>Account info</b> — username, email, and a hashed password (never stored in plain text).</li>'+
        '<li><b>Adoption data</b> — applications, favorites, and messages are persisted so shelters can act on them.</li>'+
        '<li><b>Cookies</b> — a session cookie keeps you signed in. No third-party tracking.</li>'+
        '<li><b>Your rights</b> — request export or deletion of your data anytime from your Profile page.</li>'+
      '</ul>'+
      '<div class="pa-mini-note">Your email is used only for sign-in, account recovery, adoption updates, and essential Paw-Adopt communication.</div>'+
      '<label class="pa-privacy-check"><input type="checkbox" id="ppAccept"> '+
        'I have read and accept the Privacy Policy. Paw-Adopt may store and process my data as described above.'+
      '</label>'+
      '<div class="pa-privacy-actions">'+
        '<button type="button" id="ppDecline" class="pa-btn pa-btn-ghost">Decline</button>'+
        '<button type="button" id="ppAcceptBtn" class="pa-btn pa-btn-primary" disabled>I Agree</button>'+
      '</div>'+
    '</div>';
  document.body.appendChild(m);
  return m;
}
function ensureLoginPrivacyNotice(){
  if (document.getElementById('privacyLoginNotice')) return document.getElementById('privacyLoginNotice');
  var m = document.createElement('div');
  m.id = 'privacyLoginNotice';
  m.className = 'pa-privacy-gate';
  m.innerHTML =
    '<div class="pa-privacy-card">'+
      '<div class="pa-privacy-head"><span class="pa-privacy-icon">🔐</span><h2>Privacy Policy Notice</h2></div>'+
      '<p>The login page always shows this notice so users are reminded how their account data is protected.</p>'+
      '<ul class="pa-privacy-list">'+
        '<li><b>Passwords</b> are stored as secure hashes, never plain text.</li>'+
        '<li><b>Email and profile data</b> stay inside the Paw-Adopt account system.</li>'+
        '<li><b>Sessions</b> are used only to keep users signed in securely.</li>'+
        '<li><b>Support communication</b> is limited to account recovery, adoption updates, and platform notices.</li>'+
      '</ul>'+
      '<div class="pa-privacy-actions">'+
        '<button type="button" id="privacyLoginClose" class="pa-btn pa-btn-primary">Continue</button>'+
      '</div>'+
    '</div>';
  document.body.appendChild(m);
  return m;
}
function ensureEmailAssuranceModal(){
  if (document.getElementById('emailAssuranceModal')) return document.getElementById('emailAssuranceModal');
  var m = document.createElement('div');
  m.id = 'emailAssuranceModal';
  m.className = 'pa-privacy-gate';
  m.innerHTML =
    '<div class="pa-privacy-card">'+
      '<div class="pa-privacy-head"><span class="pa-privacy-icon">🛡️</span><h2>Your Email Is Protected</h2></div>'+
      '<p>Paw-Adopt keeps your email for secure account access and important adoption-related communication only.</p>'+
      '<ul class="pa-privacy-list">'+
        '<li><b>Used for</b> sign-in, password recovery, and application status updates.</li>'+
        '<li><b>Protected by</b> account security controls and hashed password storage.</li>'+
        '<li><b>Not accepted</b> if the domain is disposable or outside the verified provider list.</li>'+
      '</ul>'+
      '<div class="pa-privacy-actions">'+
        '<button type="button" id="emailAssuranceClose" class="pa-btn pa-btn-primary">Got it</button>'+
      '</div>'+
    '</div>';
  document.body.appendChild(m);
  return m;
}
function openGate(){
  var m = ensureGate();
  m.classList.add('show');
  var cb  = document.getElementById('ppAccept');
  var btn = document.getElementById('ppAcceptBtn');
  cb.checked = false; btn.disabled = true;
  cb.onchange = function(){ btn.disabled = !cb.checked; };
  btn.onclick = function(){
    consent.value = '1';
    enableInputs(true);
    m.classList.remove('show');
    gateSubmit();
    if (registerAfterConsent && typeof window.pawAuthShowRegister === 'function') {
      window.pawAuthShowRegister();
      setTimeout(function(){ var firstField = document.getElementById('regUsername'); if (firstField) firstField.focus(); }, 80);
    }
    registerAfterConsent = false;
  };
  document.getElementById('ppDecline').onclick = function(){
    consent.value = '0'; m.classList.remove('show'); enableInputs(false); registerAfterConsent = false;
  };
}
function openLoginPrivacyNotice(){
  var m = ensureLoginPrivacyNotice();
  m.classList.add('show');
  document.getElementById('privacyLoginClose').onclick = function(){
    m.classList.remove('show');
  };
}
function openEmailAssurance(){
  var m = ensureEmailAssuranceModal();
  m.classList.add('show');
  document.getElementById('emailAssuranceClose').onclick = function(){
    m.classList.remove('show');
    email.focus();
  };
}
function enableInputs(on){
  panel.querySelectorAll('.auth-input').forEach(function(el){ el.disabled = !on; });
}

/* ─── 5. WIRE EVENTS ──────────────────────────────────────── */
email.addEventListener('focus', function(){
  if (!registerViewEmailNoticeShown && !email.disabled) {
    registerViewEmailNoticeShown = true;
    openEmailAssurance();
  }
});
email.addEventListener('input',   function(){ paintEmail(); gateSubmit(); });
pass.addEventListener('input',    paintStrength);
confirm.addEventListener('input', function(){ paintMatch(); gateSubmit(); });
form.addEventListener('submit', function(e){
  if (!paintEmail() ||
      scorePassword(pass.value).score < 4 ||
      pass.value !== confirm.value ||
      consent.value !== '1') {
    e.preventDefault();
    submitBtn.disabled = false;
    return false;
  }
});

/* ─── 6. PANEL-BASED MODAL BEHAVIOR ───────────────────────── */
document.addEventListener('pawadopt:panelchange', function(event){
  var panelId = event && event.detail ? event.detail.panelId : '';
  if (panelId === 'loginPanel') {
    openLoginPrivacyNotice();
  }
  if (panelId === 'registerPanel') {
    registerViewEmailNoticeShown = false;
    if (consent.value !== '1') {
      enableInputs(false);
      openGate();
    } else {
      enableInputs(true);
      gateSubmit();
    }
  }
});

window.openPrivacyGate = function(options){ registerAfterConsent = !!(options && options.openRegister); openGate(); };
window.openPrivacyNotice = function(){ openLoginPrivacyNotice(); };

/* Initial state on page load */
if (panel.classList.contains('active')) {
  if (consent.value === '1') {
    enableInputs(true);
  } else {
    enableInputs(false);
    openGate();
  }
} else {
  enableInputs(false);
}
if (loginPanel && loginPanel.classList.contains('active')) {
  setTimeout(openLoginPrivacyNotice, 180);
}
})();
</script>
<!-- ════ END REGISTER WIDGET ════ -->


<script src="assets/js/dark-mode.js"></script>

</body>
</html>
