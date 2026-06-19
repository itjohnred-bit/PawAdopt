<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

startSession();

if (isLoggedIn()) {
    $user    = getCurrentUser();
    $roleKey = strtolower($user['role'] ?? '');

    $dashByRole = [
        'adopter'    => '/pages/adopter/dashboard.php',
        'shelter'    => '/pages/shelter/dashboard.php',
        'veterinary' => '/pages/shelter/dashboard.php',
        'admin'      => '/pages/admin/dashboard.php',
    ];
    $path = $dashByRole[$roleKey] ?? ('/pages/' . $roleKey . '/dashboard.php');

    header('Location: ' . APP_URL . $path);
    exit;
}

$db = Database::getInstance();

$aboutContent = $db->fetch("SELECT content_value FROM site_content WHERE content_key = 'about_text'");
$aboutText    = $aboutContent ? $aboutContent['content_value']
    : 'Paw-Adopt connects loving adopters with shelters. Browse, apply, and change a life today!';

$termsContent = $db->fetch("SELECT content_value FROM site_content WHERE content_key = 'terms_text'");
$termsText    = $termsContent ? $termsContent['content_value'] : 'Standard terms apply.';

$urlError = $_GET['error'] ?? '';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAWAdopt – Find Your Forever Friend</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(APP_URL) ?>/assets/css/auth.css">
</head>
<body class="auth-body">

<div class="bg-paws">
    <?php
    $tops  = [5,15,25,35,45,55,65,75,85,10,30,70];
    $lefts = [3,12,22,35,48,60,73,82,90,8,55,40];
    $anims = [0,2,4,6,8,3,5,1,7,9,2,6];
    for ($i = 0; $i < 12; $i++): ?>
        <div class="bg-paw"
             style="top:<?= $tops[$i] ?>%;left:<?= $lefts[$i] ?>%;animation-delay:<?= $anims[$i] ?>s">🐾</div>
    <?php endfor; ?>
</div>

<div class="auth-container">

    <div class="auth-left" id="authLeft">

        <div class="auth-panel active" id="loginPanel">
            <div class="auth-logo">
                <img src="<?= htmlspecialchars(APP_URL) ?>/assets/images/Paw-Adopt Logo.png" alt="Paw-Adopt Logo">
            </div>

            <?php if ($urlError === 'unauthorized'): ?>
                <div class="auth-alert error">
                    <i class="fas fa-exclamation-circle"></i> You are not authorized for that page.
                </div>
            <?php endif; ?>

            <div class="auth-role-label">Sign in as</div>
            <div class="role-toggle">
                <button type="button" class="role-btn active" data-role="adopter">🐶 Adopter</button>
                <button type="button" class="role-btn"        data-role="shelter">🏠 Veterinary</button>
                <button type="button" class="role-btn"        data-role="admin">🛡️ Admin</button>
            </div>

            <form id="loginForm" autocomplete="on" style="width:100%;display:flex;flex-direction:column;align-items:center;">
                <input type="hidden" name="role" id="loginRole" value="adopter">

                <div class="auth-input-group">
                    <i class="fas fa-user auth-input-icon"></i>
                    <input type="text" name="username" id="loginUser" class="auth-input"
                           placeholder="Username or Email" required autocomplete="username">
                </div>
                <div class="auth-input-group">
                    <i class="fas fa-lock auth-input-icon"></i>
                    <input type="password" name="password" id="loginPass" class="auth-input"
                           placeholder="Password" required autocomplete="current-password">
                    <button type="button" class="eye-toggle" data-target="loginPass"><i class="fas fa-eye"></i></button>
                </div>
                <div class="auth-row">
                    <label class="remember-label"><input type="checkbox" name="remember"> Remember me</label>
                    <a href="#" class="forgot-link" id="goForgot">Forgot Password?</a>
                </div>
                <button type="submit" class="auth-submit-btn">Sign In</button>
            </form>

            <div class="auth-footer-links">
                <a href="#" id="goRegister">✨ New User? Create Account</a>
                <a href="#" id="goTerms">Terms &amp; Conditions</a>
            </div>
            <div class="auth-decor-bone"></div>
        </div>

        <div class="auth-panel" id="registerPanel">
            <div class="auth-logo-label">Create Account</div>

            <div class="auth-role-label">Register as</div>
            <div class="role-toggle">
                <button type="button" class="role-btn active" data-role="adopter">🐶 Adopter</button>
                <button type="button" class="role-btn"        data-role="shelter">🏠 Veterinary</button>
            </div>

            <form id="registerForm" style="width:100%;display:flex;flex-direction:column;align-items:center;">
                <input type="hidden" name="role" id="registerRole" value="adopter">
                <div class="auth-input-group">
                    <i class="fas fa-user auth-input-icon"></i>
                    <input type="text" name="username" class="auth-input" placeholder="Username" required minlength="3">
                </div>
                <div class="auth-input-group">
                    <i class="fas fa-envelope auth-input-icon"></i>
                    <input type="email" name="email" class="auth-input" placeholder="Email Address" required>
                </div>
                <div class="auth-input-group">
                    <i class="fas fa-lock auth-input-icon"></i>
                    <input type="password" name="password" id="regPass" class="auth-input"
                           placeholder="Password (min 8 chars)" required minlength="8">
                    <button type="button" class="eye-toggle" data-target="regPass"><i class="fas fa-eye"></i></button>
                </div>
                <div class="auth-input-group">
                    <i class="fas fa-lock auth-input-icon"></i>
                    <input type="password" name="confirm_password" id="regConfirm" class="auth-input"
                           placeholder="Confirm Password" required>
                    <button type="button" class="eye-toggle" data-target="regConfirm"><i class="fas fa-eye"></i></button>
                </div>
                <button type="submit" class="auth-submit-btn">Sign Up 🐾</button>
            </form>

            <div class="auth-footer-links">
                <a href="#" id="goLogin">← Back to Sign In</a>
                <a href="#" id="registerTerms">Terms &amp; Conditions</a>
            </div>
        </div>

        <div class="auth-panel" id="forgotPanel">
            <div class="auth-logo">🔑</div>
            <div class="auth-logo-label">Reset Password</div>
            <p style="text-align:center;color:#6b7280;margin-bottom:20px;font-size:.9rem;">
                Enter your email and we'll send a reset link.
            </p>
            <form id="forgotForm" style="width:100%;display:flex;flex-direction:column;align-items:center;">
                <div class="auth-input-group">
                    <i class="fas fa-envelope auth-input-icon"></i>
                    <input type="email" name="email" class="auth-input" placeholder="Your email address" required>
                </div>
                <button type="submit" class="auth-submit-btn">Send Reset Link</button>
            </form>
            <div class="auth-footer-links">
                <a href="#" id="forgotBack">← Back to Sign In</a>
            </div>
        </div>

    </div>

    <div class="auth-right">
        <h1 class="auth-right-title">ABOUT</h1>
        <p class="auth-right-text"><?= htmlspecialchars($aboutText) ?></p>
        <div class="auth-right-features">
            <div class="auth-right-feat"><span class="feat-icon">🔍</span> Browse thousands of adoptable pets</div>
            <div class="auth-right-feat"><span class="feat-icon">❤️</span> Save your favorite animals</div>
            <div class="auth-right-feat"><span class="feat-icon">📋</span> Easy adoption applications</div>
            <div class="auth-right-feat"><span class="feat-icon">💬</span> Chat directly with Veterinary Clinic</div>
        </div>
        <div class="auth-right-pets-row">🐶 🐱</div>
    </div>

</div>

<div id="termsModal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:20px;max-width:520px;width:94%;padding:32px;max-height:80vh;overflow-y:auto;">
        <h2 style="color:#0f766e;margin-bottom:16px;">Terms &amp; Conditions</h2>
        <p style="color:#374151;line-height:1.7;font-size:.9rem;"><?= htmlspecialchars($termsText) ?></p>
        <button type="button" id="termsCloseBtn"
                style="margin-top:20px;padding:10px 24px;background:#0d9488;color:#fff;border:none;border-radius:99px;font-weight:800;cursor:pointer;font-size:.95rem;">
            Close
        </button>
    </div>
</div>

<script src="<?= htmlspecialchars(APP_URL) ?>/assets/js/auth.js" defer></script>
</body>
</html>
