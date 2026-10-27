<?php

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (file_exists(__DIR__ . '/config/database.php')) {
    require_once __DIR__ . '/config/database.php';
}

/* ---------------------------------------------------------------
   URL SHORTCUTS — change these to match your existing filenames
   --------------------------------------------------------------- */
$homeUrl        = 'index.php';
$aboutUrl       = '#about';
$storiesUrl     = '#stories';
$browsePetsUrl  = 'login.php';
$journeyUrl     = 'journey.php';
$contactUrl     = 'contact.php';
$profileUrl     = 'profile.php';
$favoritesUrl   = 'favorites.php';
$applicationsUrl= 'applications.php';
$messagesUrl    = 'messages.php';
$notificationsUrl = 'notifications.php';
$vetPortalUrl   = 'https://www.facebook.com/paranaqueveterinaryservicesoffice';

$loginUrl       = 'login.php';
$logoutUrl      = 'logout.php';
$privacyUrl     = 'privacy.php';
$termsUrl       = 'terms.php';
$supportEmail   = 'pawsadopt.pup@gmail.com';
$supportFacebook= 'https://facebook.com/pawadopt.pup';

/* ---------------------------------------------------------------
   IMAGE PATHS — replace with your local assets when ready
   --------------------------------------------------------------- */
$heroImage  = '';
$aboutImage = '';
$finalCtaImage = '';
$vetImage    = '';



/* ---------------------------------------------------------------
   USER SESSION — reuse whatever your existing auth already sets.
   Reads gracefully; never fabricates data.
   --------------------------------------------------------------- */
$displayName = null;
foreach (['user_name', 'username', 'full_name', 'name', 'first_name'] as $k) {
    if (!empty($_SESSION[$k])) { $displayName = $_SESSION[$k]; break; }
}
if (!$displayName && !empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    foreach (['name', 'full_name', 'username', 'first_name'] as $k) {
        if (!empty($_SESSION['user'][$k])) { $displayName = $_SESSION['user'][$k]; break; }
    }
}
$isAuthenticated = (bool) $displayName || !empty($_SESSION['user_id']) || !empty($_SESSION['uid']);
$initial = $displayName ? strtoupper(mb_substr($displayName, 0, 1)) : 'P';

$landingStories = [];
if (class_exists('Database')) {
    try {
        $landingDb = Database::getInstance();
        $landingStories = $landingDb->fetchAll(
            "SELECT uf.feedback_id, uf.title, uf.message, uf.rating, uf.created_at,
                    COALESCE(ap.full_name, u.username) AS author_name
             FROM user_feedback uf
             JOIN users u ON uf.user_id = u.user_id
             LEFT JOIN adopter_profiles ap ON ap.adopter_id = u.user_id
             WHERE uf.is_public = 1 AND uf.rating >= 4
             ORDER BY uf.created_at DESC
             LIMIT 6"
        );
    } catch (Throwable $e) {
        $landingStories = [];
    }
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Paw-Adopt — Give a Paw. Give a Home.</title>
<meta name="description" content="Discover loving pets looking for their forever homes. Paw-Adopt connects responsible adopters with animals through a safer, more organized adoption experience." />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Alfa+Slab+One&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
/* =========================================================
   PAW-ADOPT DESIGN TOKENS  — matched to the login screen
   ========================================================= */
:root{
    --bg-1:       #3c9281;   /* page gradient top            */
    --bg-2:       #235c51;   /* page gradient bottom         */
    --teal:       #0c806c;   /* primary buttons / accents    */
    --teal-hi:    #0f957e;
    --teal-panel: #0f7f6c;   /* dark teal side panels        */
    --teal-deep:  #0a6b5a;
    --red:        #7c0f0f;   /* "Paw" logo red               */
    --green:      #0d5423;   /* "adopt" logo green           */
    --card:       #fcfcfc;   /* white cards                  */
    --soft:       #f2f5fb;   /* input / chip background      */
    --ink:        #16302b;   /* primary text                 */
    --muted:      #5f7a74;   /* secondary text               */
    --line:       #e5eeeb;   /* hairlines                    */

    --script: 'Pacifico', cursive;
    --slab:   'Alfa Slab One', 'Cooper Black', serif;
    --sans:   'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;

    --r-pill: 999px;
    --r-card: 32px;
    --r-md:   20px;

    --ease: cubic-bezier(.22,.61,.36,1);
    --shadow-card: 0 24px 60px rgba(8, 48, 42, .28);
    --shadow-soft: 0 12px 30px rgba(8, 48, 42, .18);
}

*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
    font-family: var(--sans);
    color: var(--ink);
    background: linear-gradient(160deg, var(--bg-1) 0%, var(--bg-2) 100%) fixed;
    background-color: var(--bg-2);
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
    overflow-x: hidden;
    min-height: 100vh;
}
a{color:inherit;text-decoration:none}
img{max-width:100%;display:block}
button{font-family:inherit;cursor:pointer;border:none;background:none;color:inherit}

/* faint paw watermark, same as login background */
.paw-decor{
    position:absolute; color:rgba(255,255,255,.07);
    pointer-events:none; user-select:none;
    transform:rotate(-18deg);
}

/* =========================================================
   LOGO — script "Paw" + slab "adopt" + paw print
   ========================================================= */
.logo{
    display:inline-flex; align-items:baseline; gap:2px;
    line-height:1; user-select:none;
}
.logo .paw-word{
    font-family: var(--script);
    color: var(--red);
    font-size: 1.55em;
    transform: rotate(-4deg) translateY(2px);
    display:inline-block;
}
.logo .adopt-word{
    font-family: var(--slab);
    color: var(--green);
    font-size: 1.15em;
    letter-spacing:.5px;
}
.logo i{ color: var(--green); font-size:.55em; margin-left:2px; transform:translateY(-4px); }
.logo.on-dark .paw-word{ color:#ffd9d9; }
.logo.on-dark .adopt-word{ color:#c9f2e4; }
.logo.on-dark i{ color:#c9f2e4; }

/* =========================================================
   NAVIGATION — floating white pill
   ========================================================= */
.nav{
    position:fixed; top:18px; left:50%; transform:translateX(-50%);
    z-index:100;
    width:min(1180px, calc(100% - 32px));
    background: var(--card);
    border-radius: var(--r-pill);
    box-shadow: var(--shadow-soft);
    padding: 10px 14px 10px 22px;
    display:flex; align-items:center; justify-content:space-between; gap:16px;
    transition: box-shadow .3s var(--ease);
}
.nav.scrolled{ box-shadow: 0 18px 44px rgba(8,48,42,.35); }
.nav .logo{ font-size:20px; }

.nav-links{
    display:flex; align-items:center; gap:4px;
    background: var(--soft);
    border-radius: var(--r-pill);
    padding: 5px;
}
.nav-links a{
    padding: 9px 18px; border-radius: var(--r-pill);
    font-size: 12.5px; font-weight:600; letter-spacing:.6px;
    color: var(--muted);
    transition: all .25s var(--ease);
}
.nav-links a:hover{ color: var(--ink); }
.nav-links a.active{
    background: var(--teal); color:#fff;
    box-shadow: 0 6px 14px rgba(12,128,108,.35);
}

.nav-right{ display:flex; align-items:center; gap:10px; }
.btn{
    display:inline-flex; align-items:center; gap:9px;
    padding: 13px 24px; border-radius: var(--r-pill);
    font-size: 12px; font-weight:700; letter-spacing:1.2px; text-transform:uppercase;
    transition: all .3s var(--ease);
}
.btn-primary{
    background: var(--teal); color:#fff;
    box-shadow: 0 8px 20px rgba(12,128,108,.35);
}
.btn-primary:hover{ background: var(--teal-hi); transform: translateY(-2px); box-shadow: 0 14px 28px rgba(12,128,108,.45); }
.btn-ghost{
    background: transparent; color: var(--ink);
    border: 1.5px solid var(--line);
}
.btn-ghost:hover{ border-color: var(--teal); color: var(--teal); transform: translateY(-2px); }
.btn-light{
    background:#fff; color: var(--teal-deep);
    box-shadow: 0 8px 20px rgba(0,0,0,.18);
}
.btn-light:hover{ transform: translateY(-2px); }
.btn i{ transition: transform .3s var(--ease); }
.btn:hover i{ transform: translateX(4px); }

.user-chip{
    display:flex; align-items:center; gap:9px;
    padding: 5px 16px 5px 5px; border-radius: var(--r-pill);
    background: var(--soft);
    font-size:12.5px; font-weight:600; color: var(--ink);
    cursor:pointer; position:relative;
    transition: background .25s;
}
.user-chip:hover{ background:#e8eef5; }
.user-chip .avatar{
    width:32px; height:32px; border-radius:50%;
    background: linear-gradient(135deg, var(--teal), var(--teal-deep));
    display:grid; place-items:center;
    font-weight:700; font-size:12px; color:#fff;
}
.user-menu{
    position:absolute; top: calc(100% + 12px); right:0;
    min-width: 220px;
    background: var(--card);
    border-radius: 18px;
    padding: 8px;
    box-shadow: var(--shadow-card);
    opacity:0; visibility:hidden; transform: translateY(-6px);
    transition: all .25s var(--ease);
}
.user-chip.open .user-menu{ opacity:1; visibility:visible; transform: translateY(0); }
.user-menu a{
    display:flex; align-items:center; gap:12px;
    padding: 10px 14px; border-radius: 12px;
    font-size:13px; font-weight:500; color: var(--muted);
    transition: background .2s, color .2s;
}
.user-menu a:hover{ background: var(--soft); color: var(--ink); }
.user-menu a i{ width:14px; color: var(--teal); }
.user-menu .divider{ height:1px; background: var(--line); margin: 6px 4px; }

.hamburger{ display:none; }

/* =========================================================
   SHARED SECTION SCAFFOLD
   ========================================================= */
.wrap{ width:min(1180px, calc(100% - 32px)); margin: 0 auto; }
section{ position:relative; padding: 40px 0; }

.eyebrow{
    display:inline-block;
    font-size:11px; letter-spacing:3px; text-transform:uppercase;
    font-weight:700; color: var(--teal);
    margin-bottom: 14px;
}
.eyebrow.on-dark{ color:#a9e8d8; }

h1,h2{ font-family: var(--slab); font-weight:400; color: var(--ink); }
h2{ font-size: clamp(30px, 3.4vw, 46px); line-height:1.15; letter-spacing:.3px; }
h2 em{ font-family: var(--script); font-style:normal; color: var(--red); font-size:.92em; }

.section-head{ margin-bottom: 34px; display:flex; justify-content:space-between; align-items:flex-end; gap:30px; }
.section-head .sub{ max-width: 360px; font-size:14px; color: var(--muted); }

.section-tab-link{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:10px 18px;
    border-radius:var(--r-pill);
    background:rgba(255,255,255,.12);
    color:#fff;
    font-size:11px;
    font-weight:800;
    letter-spacing:1.8px;
    text-transform:uppercase;
}

.section-tab-link:hover{ background:rgba(255,255,255,.18); }

/* white card shell — the login-card look */
.card{
    background: var(--card);
    border-radius: var(--r-card);
    box-shadow: var(--shadow-card);
    overflow:hidden;
}

/* teal info panel with glassmorphism list (login right side) */
.teal-panel{
    background: var(--teal-panel);
    color:#fff;
    padding: 40px 36px;
    position:relative;
    overflow:hidden;
}
.teal-panel h3{
    font-size:13px; letter-spacing:4px; text-transform:uppercase;
    font-weight:800; margin-bottom: 12px;
}
.teal-panel p{ font-size:14px; color: rgba(255,255,255,.85); max-width: 340px; }
.glass-list{ margin-top: 26px; display:flex; flex-direction:column; gap:12px; }
.glass-item{
    display:flex; align-items:center; gap:14px;
    background: rgba(255,255,255,.12);
    border-radius: 16px;
    padding: 14px 18px;
    font-size:13.5px; font-weight:600;
    transition: background .25s, transform .3s var(--ease);
}
.glass-item:hover{ background: rgba(255,255,255,.2); transform: translateX(4px); }
.glass-item .gi{
    width:30px; height:30px; flex:none; border-radius:10px;
    background: rgba(255,255,255,.18);
    display:grid; place-items:center; font-size:14px;
}

/* =========================================================
   HERO — split white / teal card, like the login screen
   ========================================================= */
.hero{ padding: 120px 0 30px; }
.hero .paw-decor.p1{ top:6%; right:4%; font-size:170px; }
.hero .paw-decor.p2{ bottom:2%; left:3%; font-size:120px; }

.hero-card{
    display:grid; grid-template-columns: 1.15fr 1fr;
    min-height: 560px;
}
.hero-left{
    padding: 64px 56px;
    display:flex; flex-direction:column; justify-content:center;
}
.hero-left .logo{ font-size:44px; margin-bottom: 26px; }
.hero-title{
    font-size: clamp(38px, 4vw, 58px);
    line-height: 1.12; letter-spacing:.3px;
    margin-bottom: 18px;
}
.hero-title em{
    font-family: var(--script); font-style:normal;
    color: var(--red); font-size:.95em;
}
.hero-sub{
    font-size:15px; color: var(--muted);
    max-width: 440px; margin-bottom: 30px;
}
.hero-ctas{ display:flex; gap:12px; flex-wrap:wrap; margin-bottom: 34px; }

/* segmented pill row — echoes the login "Sign in as" control */
.role-pills{
    display:inline-flex; gap:4px;
    background: var(--soft); border-radius: var(--r-pill);
    padding: 5px; width:fit-content;
}
.role-pills a{
    display:inline-flex; align-items:center; gap:8px;
    padding: 9px 18px; border-radius: var(--r-pill);
    font-size:12.5px; font-weight:600; color: var(--muted);
    transition: all .25s var(--ease);
}
.role-pills a:hover{ color: var(--ink); }
.role-pills a.active{
    background: var(--teal); color:#fff;
    box-shadow: 0 6px 14px rgba(12,128,108,.35);
}
.role-label{
    font-size:10px; letter-spacing:2.6px; text-transform:uppercase;
    color: var(--muted); font-weight:700; margin-bottom: 10px;
}

.hero-right{ display:flex; flex-direction:column; }
.hero-photo{
    margin: 22px 22px 0;
    border-radius: 22px;
    overflow:hidden;
    aspect-ratio: 16/9.5;
    background:
      radial-gradient(circle at 30% 25%, rgba(255,255,255,.22), transparent 55%),
      linear-gradient(135deg, #14967f, #0a5f52);
    position:relative;
    max-height: min(88vh, 760px);
    overflow:auto;
}
.hero-photo img{ width:100%; height:100%; object-fit:cover; }
.hero-photo .ph{
    position:absolute; inset:0; display:grid; place-items:center;
    color: rgba(255,255,255,.85); font-size:54px;
}
.hero-photo .ph small{
    position:absolute; bottom:14px;
    font-size:10px; letter-spacing:2.4px; text-transform:uppercase; font-weight:700;
}
.hero-right .teal-panel{ flex:1; display:flex; flex-direction:column; justify-content:center; }

/* =========================================================
   FEATURED PETS
   ========================================================= */
.pets-grid{
    display:grid; grid-template-columns: repeat(4, 1fr); gap: 20px;
}
.pet-card{
    background: var(--card);
    border-radius: 24px;
    overflow:hidden;
    box-shadow: var(--shadow-soft);
    cursor:pointer;
    transition: transform .4s var(--ease), box-shadow .4s var(--ease);
}
.pet-card:hover{ transform: translateY(-8px); box-shadow: var(--shadow-card); }
.pet-cover{
    position:relative; aspect-ratio: 4/3.4;
    background:
      radial-gradient(circle at 70% 20%, rgba(255,255,255,.25), transparent 55%),
      linear-gradient(135deg, #3c9281, #1e5a4e);
    display:grid; place-items:center;
    color: rgba(255,255,255,.8); font-size:44px;
    background-size: cover; background-position:center;
}
.pet-cover.has-img{ background-size:cover; }
.pet-status{
    position:absolute; top:12px; left:12px;
    display:inline-flex; align-items:center; gap:7px;
    padding: 6px 13px; border-radius: var(--r-pill);
    background: var(--teal);
    font-size:9.5px; letter-spacing:1.4px; font-weight:700; text-transform:uppercase;
    color:#fff;
    box-shadow: 0 4px 12px rgba(12,128,108,.4);
}
.pet-status::before{
    content:''; width:6px; height:6px; border-radius:50%; background:#fff;
    animation: pulse 2s infinite;
}
@keyframes pulse{
    0%,100%{ box-shadow: 0 0 0 3px rgba(255,255,255,.25); }
    50%{ box-shadow: 0 0 0 6px rgba(255,255,255,.05); }
}
.pet-fav{
    position:absolute; top:12px; right:12px;
    width:36px; height:36px; border-radius:50%;
    background: rgba(252,252,252,.92);
    display:grid; place-items:center;
    color: var(--teal); font-size:14px;
    transition: all .3s var(--ease);
}
.pet-fav:hover{ transform: scale(1.12); }
.pet-fav.active{ color:#e0455a; }
.pet-body{ padding: 18px 20px 20px; }
.pet-body h3{
    font-family: var(--slab); font-weight:400;
    font-size: 24px; color: var(--green);
    margin-bottom: 4px;
}
.pet-meta{
    font-size:11.5px; letter-spacing:1px; text-transform:uppercase;
    color: var(--muted); font-weight:600; margin-bottom: 10px;
}
.pet-loc{
    display:flex; align-items:center; gap:7px;
    font-size:12px; color: var(--muted); margin-bottom: 14px;
}
.pet-loc i{ color: var(--teal); }
.pet-cta{
    display:inline-flex; align-items:center; gap:8px;
    font-size:11px; letter-spacing:1.6px; font-weight:700; text-transform:uppercase;
    color: var(--teal);
}
.pet-cta i{ transition: transform .3s; }
.pet-card:hover .pet-cta i{ transform: translateX(5px); }

/* =========================================================
   JOURNEY — numbered white pills
   ========================================================= */
.journey-grid{
    display:grid; grid-template-columns: repeat(4, 1fr); gap: 18px;
}
.journey-step{
    background: var(--card);
    border-radius: 24px;
    padding: 30px 26px;
    box-shadow: var(--shadow-soft);
    transition: all .4s var(--ease);
}
.journey-step:hover{ transform: translateY(-6px); box-shadow: var(--shadow-card); }
.journey-step .num{
    font-family: var(--slab);
    font-size: 44px; line-height:1;
    color: transparent;
    -webkit-text-stroke: 1.5px var(--teal);
    display:block; margin-bottom: 18px;
    transition: all .3s;
}
.journey-step:hover .num{ color: var(--teal); -webkit-text-stroke: 1.5px transparent; }
.journey-step h4{
    font-size:13px; letter-spacing:2.4px; text-transform:uppercase;
    font-weight:800; color: var(--ink); margin-bottom: 10px;
}
.journey-step p{ font-size:13.5px; color: var(--muted); }

/* =========================================================
   ABOUT — exact mirror of the login split card
   ========================================================= */
.about-card{ display:grid; grid-template-columns: 1.15fr 1fr; min-height: 440px; }
.about-left{
    padding: 60px 56px;
    display:flex; flex-direction:column; justify-content:center;
}
.about-left h2{ margin-bottom: 16px; }
.about-left p{ font-size:14.5px; color: var(--muted); max-width: 440px; margin-bottom: 28px; }
.about-photo{
    margin-top: 8px;
    border-radius: 20px; overflow:hidden; aspect-ratio: 16/7;
    background:
      radial-gradient(circle at 25% 30%, rgba(255,255,255,.2), transparent 55%),
      linear-gradient(135deg, #e9f3ef, #cfe6df);
    display:grid; place-items:center;
    color: var(--teal); font-size:40px;
    background-size:cover; background-position:center;
}
.link-arrow{
    display:inline-flex; align-items:center; gap:12px;
    font-size:12px; letter-spacing:2px; font-weight:700; text-transform:uppercase;
    color: var(--teal);
    padding-bottom: 5px; width:fit-content;
    border-bottom: 1.5px solid var(--teal);
    transition: gap .3s;
}
.link-arrow:hover{ gap: 18px; }
.about-right{ display:flex; }
.about-right .teal-panel{ flex:1; display:flex; flex-direction:column; justify-content:center; }
.about-emoji{ display:flex; gap:10px; margin-top: 24px; font-size: 30px; }

.about-story{
    display:grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(320px, .85fr);
    gap: 24px;
    margin-top: 24px;
}
.about-story-main,
.about-story-side,
.about-team,
.about-belief,
.about-goal{
    background: var(--card);
    border-radius: var(--r-card);
    box-shadow: var(--shadow-card);
}
.about-story-main{
    padding: 42px 38px;
}
.about-story-main p,
.about-team-intro,
.about-belief p,
.about-goal p,
.timeline-copy,
.team-member p{
    font-size: 14.5px;
    color: var(--muted);
}
.about-quote{
    margin: 22px 0;
    padding: 24px 26px;
    border-left: 5px solid var(--teal);
    border-radius: 0 22px 22px 0;
    background: linear-gradient(135deg, #edf7f4, #f9fbff);
    color: var(--ink);
    font-family: var(--slab);
    font-size: clamp(24px, 2vw, 32px);
    line-height: 1.25;
}
.about-story-side{
    background: var(--teal-panel);
    color: #fff;
    padding: 38px 34px;
    position: relative;
    overflow: hidden;
}
.about-story-side .eyebrow{ color: #a9e8d8; }
.about-story-side h3,
.about-belief h3,
.about-goal h3{
    font-family: var(--slab);
    font-size: clamp(30px, 3vw, 42px);
    line-height: 1.1;
    margin-bottom: 14px;
}
.about-story-side p{ color: rgba(255,255,255,.84); font-size: 14px; }
.about-story-side .paw-decor{ right: -8px; bottom: -12px; font-size: 140px; }
.mission-points{
    margin-top: 24px;
    display: grid;
    gap: 12px;
}
.mission-points .glass-item{ font-size: 13px; }
.about-team{
    margin-top: 24px;
    padding: 42px 38px;
}
.about-team-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-end;
    gap: 20px;
    margin-bottom: 24px;
}
.team-grid-merged{
    display:grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
}
.team-member{
    background: linear-gradient(180deg, #ffffff 0%, #f6fbf9 100%);
    border: 1px solid var(--line);
    border-radius: 24px;
    padding: 24px 22px;
    box-shadow: var(--shadow-soft);
    transition: transform .3s var(--ease), box-shadow .3s var(--ease);
}
.team-member:hover{ transform: translateY(-6px); box-shadow: var(--shadow-card); }
.team-role{
    display:inline-flex;
    align-items:center;
    padding: 7px 14px;
    border-radius: var(--r-pill);
    background: var(--soft);
    color: var(--teal);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    margin-bottom: 14px;
}
.team-member h4{
    font-family: var(--slab);
    font-weight: 400;
    font-size: 23px;
    color: var(--ink);
    line-height: 1.15;
    margin-bottom: 10px;
}
.about-mission{
    margin-top: 24px;
    display:grid;
    grid-template-columns: minmax(0, 1fr) minmax(300px, .9fr);
    gap: 24px;
}
.timeline-card,
.mission-card{
    background: var(--card);
    border-radius: var(--r-card);
    box-shadow: var(--shadow-card);
    padding: 40px 36px;
}
.timeline{
    margin-top: 24px;
    position: relative;
    padding-left: 34px;
}
.timeline::before{
    content:'';
    position:absolute;
    left:10px;
    top:8px;
    bottom:8px;
    width:3px;
    border-radius: 999px;
    background: linear-gradient(180deg, var(--teal-hi), var(--teal-deep));
}
.t-item{ position:relative; margin-bottom: 22px; }
.t-item:last-child{ margin-bottom: 0; }
.t-item::before{
    content:'';
    position:absolute;
    left:-31px;
    top:6px;
    width:14px;
    height:14px;
    border-radius:50%;
    background: var(--teal);
    border: 3px solid #fff;
    box-shadow: 0 0 0 3px rgba(12,128,108,.18);
}
.t-item h4{
    font-size: 15px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    font-weight: 800;
    color: var(--ink);
    margin-bottom: 8px;
}
.t-item p{ font-size: 14px; color: var(--muted); }
.mission-card{
    background: linear-gradient(180deg, #0f7f6c 0%, #0a6b5a 100%);
    color:#fff;
    position:relative;
    overflow:hidden;
}
.mission-card .eyebrow{ color:#a9e8d8; }
.mission-card p{ color: rgba(255,255,255,.86); }
.mission-card .paw-decor{ right:-10px; top:-14px; font-size: 130px; }
.about-belief,
.about-goal{
    margin-top: 24px;
    padding: 40px 38px;
}
.about-belief{
    background: linear-gradient(135deg, #ffffff 0%, #f3fbf7 100%);
}
.about-goal{
    text-align:center;
    padding: 54px 36px;
}
.about-goal p{ max-width: 760px; margin: 0 auto 28px; }
@media (max-width: 1080px){
    .team-grid-merged{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .about-story,
    .about-mission{ grid-template-columns: 1fr; }
}
@media (max-width: 720px){
    .about-story-main,
    .about-story-side,
    .about-team,
    .timeline-card,
    .mission-card,
    .about-belief,
    .about-goal{ padding: 30px 24px; }
    .team-grid-merged{ grid-template-columns: 1fr; }
    .about-team-head{ align-items:flex-start; flex-direction:column; }
    .about-quote{ padding: 20px 18px; font-size: 22px; }
}

/* =========================================================
   COMMUNITY STORIES / FEEDBACK
   ========================================================= */
.stories-shell{
    background:linear-gradient(180deg, rgba(255,255,255,.1) 0%, rgba(255,255,255,.06) 100%);
    border:1px solid rgba(255,255,255,.12);
    border-radius:var(--r-card);
    padding:28px;
    box-shadow:var(--shadow-soft);
}
.story-grid{
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:18px;
}
.story-card{
    background:var(--card);
    border-radius:26px;
    box-shadow:var(--shadow-card);
    padding:24px 22px;
    display:flex;
    flex-direction:column;
    gap:14px;
    min-height:220px;
}
.story-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:14px;
}
.story-badge{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 12px;
    border-radius:var(--r-pill);
    background:#edf7f4;
    color:var(--teal);
    font-size:10px;
    font-weight:800;
    letter-spacing:1.5px;
    text-transform:uppercase;
}
.story-stars{ color:#f59e0b; font-size:13px; letter-spacing:1px; }
.story-card h3{
    font-family:var(--slab);
    font-weight:400;
    color:var(--green);
    font-size:24px;
    line-height:1.15;
}
.story-body{
    color:var(--muted);
    font-size:14px;
    line-height:1.7;
    flex:1;
}
.story-foot{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    font-size:12px;
    color:var(--muted);
}
.story-empty{
    background:rgba(255,255,255,.1);
    color:#fff;
    border-radius:24px;
    padding:26px 22px;
    text-align:center;
    font-size:14px;
}
@media (max-width: 1080px){
    .story-grid{ grid-template-columns:repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 720px){
    .stories-shell{ padding:20px; }
    .story-grid{ grid-template-columns:1fr; }
}

/* =========================================================
   VET BAND — teal panel card
   ========================================================= */
.vet-card{
    background: var(--teal-panel);
    border-radius: var(--r-card);
    box-shadow: var(--shadow-card);
    padding: 70px 64px;
    color:#fff;
    position:relative; overflow:hidden;
}
.vet-card .paw-decor{ right:5%; top:10%; font-size:180px; color:rgba(255,255,255,.08); }
.vet-card h2{ color:#fff; margin-bottom: 16px; }
.vet-card h2 em{ color:#ffd9d9; }
.vet-card p{ font-size:15px; color: rgba(255,255,255,.85); max-width: 520px; margin-bottom: 30px; }
.vet-card .btn-ghost{ color:#fff; border-color: rgba(255,255,255,.4); }
.vet-card .btn-ghost:hover{ border-color:#fff; color:#fff; background: rgba(255,255,255,.08); }

/* =========================================================
   FINAL CTA
   ========================================================= */
.final-card{
    background: var(--card);
    border-radius: var(--r-card);
    box-shadow: var(--shadow-card);
    padding: 90px 40px;
    text-align:center;
    position:relative; overflow:hidden;
}
.final-card .logo{ font-size:34px; margin-bottom: 22px; }
.final-card h2{ font-size: clamp(36px, 4.5vw, 62px); margin-bottom: 16px; }
.final-card p{ font-size:15.5px; color: var(--muted); max-width: 460px; margin: 0 auto 36px; }
.final-ctas{ display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }

/* =========================================================
   FOOTER — minimal deep teal
   ========================================================= */
footer{
    margin-top: 60px;
    background: var(--bg-2);
    color: rgba(255,255,255,.8);
    padding: 64px 0 28px;
}
.footer-grid{
    display:grid; grid-template-columns: 1.5fr 1fr 1fr 1fr;
    gap: 48px; margin-bottom: 48px;
}
.footer-brand .logo{ font-size:26px; }
.footer-brand p{
    margin-top: 16px; font-size:14px; color: rgba(255,255,255,.65);
    max-width: 260px;
}
.foot-social{ display:flex; gap:10px; margin-top: 20px; }
.foot-social a{
    width:38px; height:38px; border-radius:50%;
    background: rgba(255,255,255,.1);
    display:grid; place-items:center;
    color: rgba(255,255,255,.85);
    transition: all .3s var(--ease);
}
.foot-social a:hover{ background: var(--teal); transform: translateY(-3px); }
.foot-col h5{
    font-size:11px; letter-spacing:2.4px; text-transform:uppercase;
    color:#a9e8d8; font-weight:800; margin-bottom: 16px;
}
.foot-col ul{ list-style:none; display:flex; flex-direction:column; gap:9px; }
.foot-col a{ font-size:13px; color: rgba(255,255,255,.65); transition: color .2s, padding-left .2s; }
.foot-col a:hover{ color:#fff; padding-left: 4px; }
.foot-bottom{
    display:flex; justify-content:space-between; align-items:center;
    padding-top: 24px; border-top: 1px solid rgba(255,255,255,.12);
    font-size:12px; color: rgba(255,255,255,.5);
}
.foot-bottom em{ font-style:normal; color:#ff8d8d; }

/* =========================================================
   SCROLL REVEAL
   ========================================================= */
.reveal{ opacity:0; transform: translateY(26px); transition: opacity .8s var(--ease), transform .8s var(--ease); }
.reveal.in{ opacity:1; transform:none; }
.reveal.d1{ transition-delay:.08s } .reveal.d2{ transition-delay:.16s }
.reveal.d3{ transition-delay:.24s } .reveal.d4{ transition-delay:.32s }

/* =========================================================
   RESPONSIVE
   ========================================================= */
@media (max-width: 1080px){
    .hero-card, .about-card{ grid-template-columns: 1fr; }
    .hero-left, .about-left{ padding: 48px 36px; }
    .pets-grid{ grid-template-columns: repeat(2, 1fr); }
    .journey-grid{ grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 820px){
    .nav{ padding: 8px 10px 8px 16px; }
    .nav-links{ display:none; }
    .hamburger{
        display:grid; place-items:center;
        width:42px; height:42px; border-radius:50%;
        background: var(--soft); color: var(--ink);
    }
    .section-head{ flex-direction:column; align-items:flex-start; gap:14px; }
    .pets-grid, .journey-grid{ grid-template-columns: 1fr; }
    .vet-card{ padding: 48px 30px; }
    .footer-grid{ grid-template-columns: 1fr 1fr; gap:36px; }
    .foot-bottom{ flex-direction:column; gap:10px; text-align:center; }
}

/* Mobile menu overlay */
.mobile-menu{
    position:fixed; inset:0; z-index:200;
    background: linear-gradient(160deg, var(--bg-1), var(--bg-2));
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    gap: 22px;
    opacity:0; visibility:hidden;
    transition: opacity .3s var(--ease);
}
.mobile-menu.open{ opacity:1; visibility:visible; }
.mobile-menu a{
    font-family: var(--slab);
    font-size: 30px; color:#fff;
}
.mobile-menu a:hover{ color:#a9e8d8; }
.mm-close{
    position:absolute; top:22px; right:22px;
    width:44px; height:44px; border-radius:50%;
    background: rgba(255,255,255,.15); color:#fff; font-size:17px;
}



/* =========================================================
   POPUP MODALS
   ========================================================= */
.modal{
    position:fixed; inset:0; z-index:200;
    display:flex; align-items:center; justify-content:center;
    padding:24px;
    background: rgba(7, 28, 24, .56);
    opacity:0; visibility:hidden;
    pointer-events:none;
    transition: all .28s var(--ease);
}
.modal.open{
    opacity:1; visibility:visible; pointer-events:auto;
}
.modal-card{
    width:min(560px, calc(100vw - 32px));
    background: var(--card);
    border-radius: 28px;
    box-shadow: var(--shadow-card);
    padding: 30px;
    position:relative;
}
.modal-close{
    position:absolute; top:16px; right:16px;
    width:42px; height:42px; border-radius:50%;
    background: var(--soft); color: var(--ink);
    display:grid; place-items:center;
    transition: all .25s var(--ease);
}
.modal-close:hover{ background: var(--teal); color:#fff; }
.modal-card h3{
    font-family: var(--slab);
    font-size: 28px;
    line-height:1.15;
    margin-bottom: 10px;
}
.modal-card p{
    font-size:14px;
    color: var(--muted);
    margin-bottom: 14px;
}
.modal-stack{ display:grid; gap:14px; margin-top:18px; }
.modal-info{
    background: var(--soft);
    border-radius: 20px;
    padding: 16px 18px;
}
.modal-info strong{
    display:block;
    color: var(--ink);
    font-size: 13px;
    margin-bottom: 6px;
}
.modal-actions{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    margin-top: 22px;
}
.modal-actions .btn{ justify-content:center; }

@media (prefers-reduced-motion: reduce){
    *{ animation:none !important; transition:none !important; }
    html{ scroll-behavior:auto; }
}
</style>
</head>
<body>

<!-- =====================  NAV  ===================== -->
<nav class="nav" id="nav">
    <a href="<?= e($homeUrl) ?>" class="logo" aria-label="Paw-Adopt">
        <span class="paw-word">Paw</span><span class="adopt-word">adopt</span><i class="fa-solid fa-paw"></i>
    </a>

    <div class="nav-links" role="navigation" aria-label="Primary">
        <a href="<?= e($homeUrl) ?>" class="active">Home</a>
        <a href="<?= e($aboutUrl) ?>">About Us</a>
        <a href="<?= e($storiesUrl) ?>">Stories</a>
        <a href="<?= e($browsePetsUrl) ?>">Pets</a>
        <a href="#contact-modal" data-modal-open="contact-modal">Contact</a>
    </div>

    <div class="nav-right">


        <?php if ($isAuthenticated && $displayName): ?>
      
        <?php else: ?>
        <a href="<?= e($loginUrl) ?>" class="user-chip" style="text-decoration:none">
            <div class="avatar"><i class="fa-regular fa-user" style="font-size:12px"></i></div>
            <span>Sign In</span>
        </a>
        <?php endif; ?>

        <button class="hamburger" id="hamburger" aria-label="Open menu">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>
</nav>

<!-- Mobile menu -->
<div class="mobile-menu" id="mobileMenu" aria-hidden="true">
    <button class="mm-close" id="mmClose" aria-label="Close menu"><i class="fa-solid fa-xmark"></i></button>
    <a href="<?= e($homeUrl) ?>">Home</a>
    <a href="<?= e($aboutUrl) ?>">About Us</a>
    <a href="<?= e($storiesUrl) ?>">Stories</a>
    <a href="<?= e($browsePetsUrl) ?>">Pets</a>
    <a href="#contact-modal" data-modal-open="contact-modal">Contact</a>
</div>

<!-- =====================  HERO  ===================== -->
<header class="hero" id="hero">
    <i class="fa-solid fa-paw paw-decor p1"></i>
    <i class="fa-solid fa-paw paw-decor p2"></i>

    <div class="wrap">
        <div class="card hero-card reveal">
            <div class="hero-left">
                <span class="logo" aria-hidden="true">
                    <span class="paw-word">Paw</span><span class="adopt-word">adopt</span><i class="fa-solid fa-paw"></i>
                </span>
                <h1 class="hero-title">
                    Find a <em>friend,</em><br/>give a home.
                </h1>
                <p class="hero-sub">
                    Paw-Adopt connects loving adopters with Veterinary. Browse,
                    apply, and change a life today — backed by veterinary
                    personnel who care.
                </p>
                <div class="hero-ctas">
                    <a href="<?= e($browsePetsUrl) ?>" class="btn btn-primary">
                        Explore Pets <i class="fa-solid fa-arrow-right"></i>
                    </a>
                    <a href="<?= e($aboutUrl) ?>" class="btn btn-ghost">
                        Learn More <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>


            </div>

            <div class="hero-right">
                <div class="teal-panel" style="padding-top:28px">
                    <h3>Why Paw-Adopt</h3>
                    <div class="glass-list">
                        <div class="glass-item"><span class="gi">✅</span> Verified pet profiles</div>
                        <div class="glass-item"><span class="gi">🩺</span> Veterinary-supported</div>
                        <div class="glass-item"><span class="gi">❤️</span> Responsible adoption</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- =====================  FEATURED PETS  ===================== -->
<section id="pets">
    <div class="wrap">
        <div class="section-head">
            <div class="reveal">
                <span class="eyebrow" style="color:#a9e8d8">Featured Companions</span>
                <h2 style="color:#fff">Meet your next <em>best friend.</em></h2>
            </div>
            <p class="sub reveal d1" style="color:rgba(255,255,255,.75)">
                Every profile is reviewed by veterinary partners,
                so every match starts on the right side of trust.
            </p>
        </div>

</section>

<!-- =====================  ABOUT  ===================== -->
<section id="about">
    <div class="wrap">
        <div class="section-head">
            <div class="reveal">
                <span class="eyebrow" style="color:#a9e8d8">About Paw-Adopt</span>
                <h2 style="color:#fff">Our story, our team, and the <em>purpose</em> behind Paw-Adopt.</h2>
            </div>
            <p class="sub reveal d1" style="color:rgba(255,255,255,.75)">
                The design stays true to the index page while bringing your full About content directly into the homepage.
            </p>
        </div>

        <div class="about-story">
            <article class="about-story-main reveal">
                <span class="eyebrow">Our Journey</span>
                <h2>Where it all <em>began.</em></h2>
                <p>Paw-Adopt began with a simple question:</p>
                <div class="about-quote">“What happens to the stray animals rescued by our local veterinary clinic when they have nowhere to go?”</div>
                <p>Every day, local veterinary clinics and animal shelters encounter stray dogs and cats that need more than medical care. They need a safe place to stay, someone who will care for them, and most importantly, a family they can call their own.</p>
                <br>
                <p>We saw an opportunity to help bridge that gap. Instead of letting rescued animals remain in shelters without knowing when their forever home would come, we envisioned a platform that could connect <strong>rescued pets with responsible adopters</strong>. That idea became <strong>Paw-Adopt</strong>.</p>
            </article>

            <aside class="about-story-side reveal d1">
                <i class="fa-solid fa-paw paw-decor"></i>
                <span class="eyebrow">The Mission</span>
                <h3>From strays to companions.</h3>
                <p>Our journey is not only about building a website. It is about creating a bridge between local veterinary clinics and shelters caring for rescued animals and the people searching for a companion and a chance to give an animal a loving home.</p>
                <div class="mission-points glass-list">
                    <div class="glass-item"><span class="gi"><i class="fa-solid fa-shield-dog"></i></span> Responsible adoption supported by real people</div>
                    <div class="glass-item"><span class="gi"><i class="fa-solid fa-stethoscope"></i></span> Veterinary-backed pet profiles and care</div>
                    <div class="glass-item"><span class="gi"><i class="fa-solid fa-house"></i></span> A second chance for every rescued animal</div>
                </div>
            </aside>
        </div>

        <div class="about-team reveal">
            <div class="about-team-head">
                <div>
                    <span class="eyebrow">The Team</span>
                    <h2>Four students, <em>one purpose.</em></h2>
                </div>
                <p class="about-team-intro">Paw-Adopt started as an initiative by <strong>four IT students from PUP Parañaque</strong> who wanted to use technology for something meaningful: helping rescued stray animals find responsible and loving families.</p>
            </div>

            <div class="team-grid-merged">
                <article class="team-member reveal">
                    <span class="team-role">Project Leader</span>
                    <h4>Mark Clarence Adlao</h4>
                    <p>Mark leads the team and keeps the project focused on its purpose — coordinating the team, guiding the direction, and making sure every part of Paw-Adopt helps both the animals and the people who care for them.</p>
                </article>
                <article class="team-member reveal d1">
                    <span class="team-role">Main Developer</span>
                    <h4>John Red Lopez</h4>
                    <p>John turns the team’s ideas into a working system. As the main developer, he builds the core features of Paw-Adopt and transforms the vision into a platform people can actually use.</p>
                </article>
                <article class="team-member reveal d2">
                    <span class="team-role">Backup Developer</span>
                    <h4>James Mathieu Paulite</h4>
                    <p>James supports the development process and keeps the system moving forward — assisting with implementation, testing, and development whenever the team needs extra support.</p>
                </article>
                <article class="team-member reveal d3">
                    <span class="team-role">Troubleshooter</span>
                    <h4>Martin Kenyon Tacda</h4>
                    <p>Martin identifies and resolves problems throughout the project. From unexpected errors to system issues, he works with the team to find solutions and keep Paw-Adopt running smoothly.</p>
                </article>
            </div>
        </div>

        <div class="about-mission">
            <article class="timeline-card reveal">
                <span class="eyebrow">The Adoption Flow</span>
                <h2>How the journey <em>continues.</em></h2>
                <p class="timeline-copy">Paw-Adopt connects rescued pets, veterinary support, and responsible adopters through one guided path.</p>
                <div class="timeline">
                    <div class="t-item">
                        <h4>Rescued</h4>
                        <p>Local veterinary clinics and shelters take in stray dogs and cats and give them the medical care they need.</p>
                    </div>
                    <div class="t-item">
                        <h4>Discovered</h4>
                        <p>Through Paw-Adopt, rescued pets are introduced to potential adopters with verified profiles and veterinary support.</p>
                    </div>
                    <div class="t-item">
                        <h4>Adopted</h4>
                        <p>Adopters learn about the animals they are interested in and begin the adoption process responsibly — giving each pet a second chance at a forever home.</p>
                    </div>
                </div>
            </article>

            <article class="mission-card reveal d1">
                <i class="fa-solid fa-paw paw-decor"></i>
                <span class="eyebrow">What We Believe</span>
                <h3>More than adoption.</h3>
                <p>We believe adoption should not simply mean taking an animal home. It means accepting responsibility — giving a rescued animal patience, care, protection, and a place in the family.</p>
                <br>
                <p>That is why Paw-Adopt promotes <strong>responsible adoption</strong>, supported by veterinary personnel and verified pet information. Every profile represents more than a name and a picture. <strong>It represents a life waiting for a second chance.</strong></p>
            </article>
        </div>

        <div class="about-goal reveal">
            <span class="eyebrow">Our Goal</span>
            <h3>The journey <em>continues.</em></h3>
            <p>What started as an idea from <strong>four IT students at PUP Parañaque</strong> continues to grow through technology, teamwork, and compassion. Because every stray deserves a chance, every rescued pet deserves a home, and every loving family has the opportunity to give one.</p>
            <a href="<?= e($browsePetsUrl) ?>" class="btn btn-primary">Explore Pets <i class="fa-solid fa-arrow-right"></i></a>
        </div>
    </div>
</section>

<!-- =====================  COMMUNITY STORIES  ===================== -->
<section id="stories">
    <div class="wrap">
        <div class="section-head">
            <div class="reveal">
                <span class="eyebrow" style="color:#a9e8d8">Community Feedback</span>
                <h2 style="color:#fff">Good adoption <em>stories</em> from real adopters.</h2>
            </div>
            <a href="<?= e($loginUrl) ?>" class="section-tab-link reveal d1">
                Share Feedback <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <div class="stories-shell reveal d1">
            <?php if (!empty($landingStories)): ?>
            <div class="story-grid">
                <?php foreach ($landingStories as $story): ?>
                <article class="story-card">
                    <div class="story-head">
                        <span class="story-badge"><i class="fa-solid fa-heart"></i> Adopter Story</span>
                        <div class="story-stars" aria-label="<?= (int) $story['rating'] ?> star rating"><?= str_repeat('★', max(0, min(5, (int) $story['rating']))) ?></div>
                    </div>
                    <h3><?= e($story['title'] ?: 'Happy Paw-Adopt Experience') ?></h3>
                    <p class="story-body"><?= e($story['message']) ?></p>
                    <div class="story-foot">
                        <strong><?= e($story['author_name']) ?></strong>
                        <span><?= !empty($story['created_at']) ? e(date('M j, Y', strtotime($story['created_at']))) : '' ?></span>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="story-empty">No public adopter feedback has been shared yet. Positive feedback submitted by adopters will appear here once marked for public display.</div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- =====================  VETERINARY / SHELTER  ===================== -->
<section id="vet">
    <div class="wrap">
        <div class="vet-card reveal">
            <i class="fa-solid fa-paw paw-decor"></i>
            <span class="eyebrow on-dark">For Veterinarians &amp;</span>
            <h2>Help them <em>find home.</em></h2>
            <p>
                Veterinary professionals and authorized personels play an important
                role in helping animals find responsible families. Join Paw-Adopt
                and give more paws the chance they deserve.
            </p>
            <div class="hero-ctas">
                <a href="<?= e($vetPortalUrl) ?>" class="btn btn-light" data-popup-link="vet">
                   Contact Vet Now! <i class="fa-solid fa-arrow-right"></i>
                </a>

            </div>
        </div>
    </div>
</section>

<!-- =====================  FINAL CTA  ===================== -->
<section id="start">
    <div class="wrap">
        <div class="final-card reveal">
            <span class="logo" aria-hidden="true">
                <span class="paw-word">Paw</span><span class="adopt-word">adopt</span><i class="fa-solid fa-paw"></i>
            </span>
            <h2>Someone is <em>waiting</em> for you.</h2>
            <p>Your next best friend could be one click away.</p>
            <div class="final-ctas">
                <a href="<?= e($browsePetsUrl) ?>" class="btn btn-primary">
                    Explore Available Pets <i class="fa-solid fa-arrow-right"></i>
                </a>
               
            </div>
        </div>
    </div>
</section>

<!-- =====================  FOOTER  ===================== -->
<footer>
    <div class="wrap">
        <div class="footer-grid">
            <div class="footer-brand">
                <span class="logo on-dark">
                    <span class="paw-word">Paw</span><span class="adopt-word">adopt</span><i class="fa-solid fa-paw"></i>
                </span>
                <p>“Give a Paw. Give a Home.”</p>
                <div class="foot-social">
                    <a href="<?= e($supportFacebook) ?>" aria-label="Facebook" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-facebook-f"></i></a>

                </div>
            </div>

            <div class="foot-col">
                <h5>Explore</h5>
                <ul>
                    <li><a href="<?= e($homeUrl) ?>">Home</a></li>
                    <li><a href="<?= e($aboutUrl) ?>">About Us</a></li>
                    <li><a href="<?= e($storiesUrl) ?>">Stories</a></li>
                    <li><a href="<?= e($browsePetsUrl) ?>">Browse Pets</a></li>
                    <li><a href="#contact-modal" data-modal-open="contact-modal">Contact</a></li>
                </ul>
            </div>

            <div class="foot-col">
                <h5>Account</h5>
                <ul>
                    <?php if ($isAuthenticated): ?>
                    <li><a href="<?= e($profileUrl) ?>">My Profile</a></li>
                    <li><a href="<?= e($applicationsUrl) ?>">My Applications</a></li>
                    <li><a href="<?= e($favoritesUrl) ?>">My Favorites</a></li>
                    <li><a href="<?= e($messagesUrl) ?>">Messages</a></li>
                    <?php else: ?>
                    <li><a href="#login-required-modal" data-modal-open="login-required-modal">My Profile</a></li>
                    <li><a href="#login-required-modal" data-modal-open="login-required-modal">My Applications</a></li>
                    <li><a href="#login-required-modal" data-modal-open="login-required-modal">My Favorites</a></li>
                    <li><a href="#login-required-modal" data-modal-open="login-required-modal">Messages</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="foot-col">
                <h5>Legal</h5>
                <ul>
                    <li><a href="#privacy-modal" data-modal-open="privacy-modal">Privacy Policy</a></li>
                    <li><a href="#terms-modal" data-modal-open="terms-modal">Terms &amp; Conditions</a></li>
                    <li><a href="<?= e($vetPortalUrl) ?>" data-popup-link="vet">Veterinary Portal</a></li>
                    <li><a href="#contact-modal" data-modal-open="contact-modal">Support</a></li>
                </ul>
            </div>
        </div>

        <div class="foot-bottom">
            <div>© 2026 Paw-Adopt. All rights reserved.</div>
            <div>Made with <em>♥</em> for animals who deserve a home.</div>
        </div>
    </div>
</footer>


<div class="modal" id="privacy-modal" aria-hidden="true" role="dialog" aria-labelledby="privacyModalTitle">
    <div class="modal-card">
        <button class="modal-close" type="button" data-modal-close aria-label="Close privacy modal">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <span class="eyebrow">Privacy Policy</span>
        <h3 id="privacyModalTitle">How Paw-Adopt protects your information</h3>
        <p>Paw-Adopt only collects the details needed to support pet adoption, application review, user communication, and animal welfare coordination.</p>
        <div class="modal-stack">
            <div class="modal-info">
                <strong>Information we collect</strong>
                Basic account details, adoption application information, and support messages may be stored to process requests and improve the platform experience.
            </div>
            <div class="modal-info">
                <strong>How we use it</strong>
                Your information may be reviewed by authorized Paw-Adopt staff, veterinary partners, or adoption coordinators only for legitimate adoption and support purposes.
            </div>
            <div class="modal-info">
                <strong>Protection and access</strong>
                Paw-Adopt aims to keep personal information secure and limits access to people handling support, applications, and welfare-related coordination.
            </div>
            <div class="modal-info">
                <strong>Contact about privacy</strong>
                For privacy questions or requests, contact Paw-Adopt through the support email or official Facebook page listed in the contact modal.
            </div>
        </div>
    </div>
</div>

<div class="modal" id="login-required-modal" aria-hidden="true" role="dialog" aria-labelledby="loginRequiredTitle">
    <div class="modal-card">
        <button class="modal-close" type="button" data-modal-close aria-label="Close login required modal">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <span class="eyebrow">Login Required</span>
        <h3 id="loginRequiredTitle">Please sign in first</h3>
        <p>You need to log in before opening account pages like Profile, Applications, Favorites, or Messages.</p>
        <div class="modal-stack">
            <div class="modal-info">
                <strong>Why this is restricted</strong>
                These pages contain your personal account information and adoption activity, so access is only available after sign in.
            </div>
        </div>
        <div class="modal-actions">
            <a href="<?= e($loginUrl) ?>" class="btn btn-primary">
                Go to Login <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </div>
</div>

<div class="modal" id="terms-modal" aria-hidden="true" role="dialog" aria-labelledby="termsModalTitle">
    <div class="modal-card">
        <button class="modal-close" type="button" data-modal-close aria-label="Close terms modal">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <span class="eyebrow">Terms &amp; Conditions</span>
        <h3 id="termsModalTitle">Paw-Adopt Terms of Use</h3>
        <p>By browsing, applying, or contacting Paw-Adopt, users agree to use the platform respectfully and provide accurate information during the adoption process.</p>
        <div class="modal-stack">
            <div class="modal-info">
                <strong>Responsible use</strong>
                Applications, inquiries, and account details must be truthful. Fraudulent, abusive, or harmful activity may be removed or blocked.
            </div>
            <div class="modal-info">
                <strong>Adoption decisions</strong>
                Veterinary partners and authorized personnel may review applications, request clarifications, and approve or decline matches based on animal welfare.
            </div>
            <div class="modal-info">
                <strong>Animal welfare first</strong>
                Paw-Adopt promotes safe, humane, and responsible rehoming. Users should not use the platform for resale, neglect, or unlawful purposes.
            </div>
            <div class="modal-info">
                <strong>Communication</strong>
                Support replies and veterinary coordination may happen through email, Facebook, or the official channels listed by Paw-Adopt.
            </div>
        </div>
    </div>
</div>

<div class="modal" id="contact-modal" aria-hidden="true" role="dialog" aria-labelledby="contactModalTitle">
    <div class="modal-card">
        <button class="modal-close" type="button" data-modal-close aria-label="Close contact modal">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <span class="eyebrow">Contact Paw-Adopt</span>
        <h3 id="contactModalTitle">Chat with our support team</h3>
        <p>Need help with adoption, application updates, or veterinary concerns? Reach us directly through email or Facebook chat.</p>
        <div class="modal-stack">
            <div class="modal-info">
                <strong>Email support</strong>
                <a href="https://mail.google.com/mail/?view=cm&fs=1&to=<?= e($supportEmail) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--teal);font-weight:700;word-break:break-word;"><?= e($supportEmail) ?></a>
            </div>
            <div class="modal-info">
                <strong>Facebook page</strong>
                facebook.com/pawadopt.pup
            </div>
        </div>
        <div class="modal-actions">
            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=<?= e($supportEmail) ?>" class="btn btn-primary" target="_blank" rel="noopener noreferrer">
                Message Us <i class="fa-solid fa-envelope"></i>
            </a>
            <a href="<?= e($supportFacebook) ?>" class="btn btn-ghost" target="_blank" rel="noopener noreferrer">
                Open Facebook Chat <i class="fa-brands fa-facebook-messenger"></i>
            </a>
        </div>
    </div>
</div>

<script>
/* ============================================================
   SCROLL-BASED NAV STATE
   ============================================================ */
const nav = document.getElementById('nav');
function onScroll(){
    if (window.scrollY > 40) nav.classList.add('scrolled');
    else nav.classList.remove('scrolled');
}
window.addEventListener('scroll', onScroll, { passive: true });
onScroll();

/* ============================================================
   REVEAL ON SCROLL (with fallback — content always reveals)
   ============================================================ */
const revealEls = document.querySelectorAll('.reveal');
function revealAll(){ revealEls.forEach(el => el.classList.add('in')); }
if ('IntersectionObserver' in window){
    const io = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting){
                entry.target.classList.add('in');
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    revealEls.forEach(el => io.observe(el));
    /* safety net: if nothing revealed shortly after load, reveal all */
    /* safety net: reveal everything still hidden after 2.5s
       (covers headless renders / edge cases without scroll) */
    setTimeout(revealAll, 2500);
} else {
    revealAll();
}

/* ============================================================
   USER CHIP DROPDOWN
   ============================================================ */
const userChip = document.getElementById('userChip');
if (userChip){
    userChip.addEventListener('click', (e) => {
        e.stopPropagation();
        userChip.classList.toggle('open');
        userChip.setAttribute('aria-expanded', userChip.classList.contains('open'));
    });
    document.addEventListener('click', () => userChip.classList.remove('open'));
    userChip.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' '){
            e.preventDefault(); userChip.click();
        }
        if (e.key === 'Escape') userChip.classList.remove('open');
    });
}

/* ============================================================
   MOBILE MENU
   ============================================================ */
const ham = document.getElementById('hamburger');
const mm  = document.getElementById('mobileMenu');
const mmClose = document.getElementById('mmClose');
if (ham && mm){
    ham.addEventListener('click', () => {
        mm.classList.add('open');
        mm.setAttribute('aria-hidden','false');
    });
    mmClose.addEventListener('click', () => {
        mm.classList.remove('open');
        mm.setAttribute('aria-hidden','true');
    });
    mm.querySelectorAll('a').forEach(a => a.addEventListener('click', () => mm.classList.remove('open')));
}

/* ============================================================
   MODALS + POPUP LINKS
   ============================================================ */
const modalTriggers = document.querySelectorAll('[data-modal-open]');
const modalClosers = document.querySelectorAll('[data-modal-close]');
const modals = document.querySelectorAll('.modal');

function closeModal(modal){
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function openModal(modal){
    if (!modal) return;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

modalTriggers.forEach(trigger => {
    trigger.addEventListener('click', (e) => {
        e.preventDefault();
        const modal = document.getElementById(trigger.getAttribute('data-modal-open'));
        openModal(modal);
    });
});

modalClosers.forEach(btn => {
    btn.addEventListener('click', () => closeModal(btn.closest('.modal')));
});

modals.forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal(modal);
    });
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const activeModal = document.querySelector('.modal.open');
        if (activeModal) closeModal(activeModal);
    }
});

document.querySelectorAll('[data-popup-link="vet"]').forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        const popup = window.open(
            link.href,
            'pawAdoptVetPortal',
            'width=980,height=720,menubar=no,toolbar=no,location=yes,status=no,scrollbars=yes,resizable=yes'
        );
        if (popup) popup.focus();
    });
});
</script>
</body>
</html>
