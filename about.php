

<?php

//
// ============================================================
//  journey.php — PawAdopt "Journey" page
//  Place this file in your PawAdopt project root.
//  Put your slideshow images in an /images folder next to it.
// ============================================================

// ---- Slideshow images (add/remove as many as you like) ----
$slides = [
    'images/slide1.jpg',
    'images/slide2.jpg',
    'images/slide3.jpg',
    'images/slide4.jpg',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PawAdopt — Our Journey</title>

<!-- Fonts: Pacifico (script logo), Playfair Display (headings), Nunito (body) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Playfair+Display:ital,wght@0,700;0,800;1,700&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">

<style>
:root{
    --green-900:#0b4d3a;
    --green-800:#0f5f47;
    --green-700:#137a5b;
    --green-600:#1a8f6c;
    --green-500:#27a57d;
    --cream:#f7f5ef;
    --ink:#1e2b26;
    --red:#c0392b;
    --radius:22px;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{
    font-family:'Nunito',sans-serif;
    color:var(--ink);
    background:linear-gradient(160deg,var(--green-800) 0%,var(--green-700) 55%,var(--green-600) 100%);
    min-height:100vh;
}

/* ---------- subtle paw watermark ---------- */
body::before{
    content:'';
    position:fixed;inset:0;pointer-events:none;z-index:0;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cellipse cx='50' cy='68' rx='20' ry='16'/%3E%3Cellipse cx='26' cy='44' rx='9' ry='12'/%3E%3Cellipse cx='44' cy='32' rx='9' ry='12'/%3E%3Cellipse cx='62' cy='34' rx='9' ry='12'/%3E%3Cellipse cx='76' cy='48' rx='9' ry='12'/%3E%3C/g%3E%3C/svg%3E");
    background-size:340px 340px;
}

/* ======================= NAVBAR ======================= */
.nav-wrap{display:flex;justify-content:center;padding:22px 16px 0;position:relative;z-index:5;}
.navbar{
    width:min(1150px,100%);
    background:var(--cream);
    border-radius:60px;
    display:flex;align-items:center;justify-content:space-between;
    padding:10px 26px;
    box-shadow:0 8px 24px rgba(0,0,0,.18);
}
.logo{font-family:'Pacifico',cursive;font-size:1.45rem;color:var(--red);text-decoration:none;}
.logo span{color:var(--green-800);}
.logo small{font-family:'Nunito',sans-serif;color:var(--green-800);font-size:.9rem;}
.nav-links{display:flex;gap:6px;list-style:none;}
.nav-links a{
    text-decoration:none;color:var(--ink);font-weight:700;font-size:.85rem;
    padding:8px 18px;border-radius:40px;transition:.25s;
}
.nav-links a:hover{background:#e6f0ea;}
.nav-links a.active{background:var(--green-700);color:#fff;}
.nav-actions{display:flex;align-items:center;gap:12px;}
.btn-pill{
    display:inline-flex;align-items:center;gap:8px;
    background:var(--green-700);color:#fff;text-decoration:none;
    font-weight:800;font-size:.8rem;letter-spacing:.05em;text-transform:uppercase;
    padding:11px 24px;border-radius:40px;border:none;cursor:pointer;
    box-shadow:0 4px 12px rgba(15,95,71,.35);transition:.25s;
}
.btn-pill:hover{background:var(--green-800);transform:translateY(-1px);}
.signin{
    display:inline-flex;align-items:center;gap:6px;text-decoration:none;
    color:var(--green-800);font-weight:700;font-size:.85rem;
}
.signin .avatar{
    width:26px;height:26px;border-radius:50%;background:var(--green-700);
    display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:.75rem;
}

/* ======================= HERO SLIDESHOW ======================= */
.hero{
    position:relative;z-index:2;
    width:min(1150px,94%);
    margin:28px auto 0;
    border-radius:var(--radius);
    overflow:hidden;
    box-shadow:0 18px 40px rgba(0,0,0,.28);
    background:var(--green-900);
}
.slideshow{position:relative;width:100%;height:420px;}
.slide{
    position:absolute;inset:0;opacity:0;transition:opacity 1.2s ease-in-out;
}
.slide.active{opacity:1;}
.slide img{width:100%;height:100%;object-fit:cover;display:block;}
.slide .caption{
    position:absolute;left:0;right:0;bottom:0;
    padding:60px 40px 34px;
    background:linear-gradient(transparent,rgba(6,40,30,.85));
    color:#fff;
}
.slide .caption h2{font-family:'Playfair Display',serif;font-size:2rem;font-weight:800;}
.slide .caption p{opacity:.9;font-weight:600;margin-top:4px;}

/* arrows + dots */
.arrow{
    position:absolute;top:50%;transform:translateY(-50%);
    width:44px;height:44px;border-radius:50%;border:none;cursor:pointer;
    background:rgba(247,245,239,.9);color:var(--green-800);
    font-size:1.2rem;font-weight:800;z-index:3;transition:.25s;
}
.arrow:hover{background:#fff;transform:translateY(-50%) scale(1.08);}
.arrow.prev{left:18px;}
.arrow.next{right:18px;}
.dots{position:absolute;bottom:16px;left:50%;transform:translateX(-50%);display:flex;gap:8px;z-index:3;}
.dot{width:10px;height:10px;border-radius:50%;background:rgba(255,255,255,.45);border:none;cursor:pointer;transition:.3s;}
.dot.active{background:#fff;width:26px;border-radius:6px;}

/* ======================= PAGE CARD ======================= */
.page-card{
    position:relative;z-index:2;
    width:min(1150px,94%);
    margin:26px auto 60px;
    background:var(--cream);
    border-radius:var(--radius);
    padding:56px min(7%,70px);
    box-shadow:0 18px 40px rgba(0,0,0,.25);
}
.eyebrow{
    text-transform:uppercase;letter-spacing:.22em;font-size:.72rem;
    font-weight:800;color:var(--green-600);margin-bottom:10px;
}
h1.section-title{
    font-family:'Playfair Display',serif;font-weight:800;
    font-size:clamp(1.9rem,3.4vw,2.6rem);color:var(--green-900);
    margin-bottom:18px;line-height:1.15;
}
.lead{font-size:1.05rem;line-height:1.75;margin-bottom:14px;}
.quote{
    font-family:'Playfair Display',serif;font-style:italic;font-weight:700;
    font-size:1.35rem;color:var(--green-800);
    border-left:5px solid var(--green-500);
    padding:14px 22px;margin:22px 0;background:#eef5f0;border-radius:0 14px 14px 0;
}
.divider{border:none;height:2px;background:linear-gradient(90deg,var(--green-500),transparent);margin:44px 0;}

/* ---------- team grid ---------- */
.team-grid{
    display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
    gap:20px;margin-top:26px;
}
.member{
    background:#fff;border-radius:18px;padding:26px 22px;
    box-shadow:0 6px 18px rgba(15,95,71,.10);
    border-top:5px solid var(--green-600);transition:.3s;
}
.member:hover{transform:translateY(-5px);box-shadow:0 12px 26px rgba(15,95,71,.18);}
.member .role-badge{
    display:inline-block;background:var(--green-700);color:#fff;
    font-size:.68rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;
    padding:5px 14px;border-radius:30px;margin-bottom:14px;
}
.member h3{font-family:'Playfair Display',serif;font-size:1.15rem;color:var(--green-900);margin-bottom:8px;}
.member p{font-size:.9rem;line-height:1.65;color:#41524a;}

/* ---------- timeline ---------- */
.timeline{margin-top:30px;position:relative;padding-left:34px;}
.timeline::before{
    content:'';position:absolute;left:10px;top:6px;bottom:6px;width:3px;
    background:linear-gradient(var(--green-500),var(--green-700));border-radius:3px;
}
.t-item{position:relative;margin-bottom:26px;}
.t-item::before{
    content:'';position:absolute;left:-32px;top:5px;width:15px;height:15px;
    border-radius:50%;background:var(--green-600);border:3px solid var(--cream);
    box-shadow:0 0 0 3px var(--green-500);
}
.t-item h4{font-family:'Playfair Display',serif;color:var(--green-800);font-size:1.1rem;margin-bottom:4px;}
.t-item p{font-size:.95rem;line-height:1.65;color:#41524a;}

/* ---------- CTA strip ---------- */
.cta{
    margin-top:48px;background:var(--green-800);color:#fff;border-radius:18px;
    padding:38px 34px;text-align:center;
}
.cta h3{font-family:'Pacifico',cursive;font-size:1.7rem;font-weight:400;margin-bottom:8px;}
.cta p{opacity:.9;margin-bottom:22px;}
.cta .btn-pill{background:#fff;color:var(--green-800);}
.cta .btn-pill:hover{background:var(--cream);}

/* ======================= FOOTER ======================= */
footer{
    text-align:center;color:rgba(255,255,255,.75);
    font-size:.78rem;letter-spacing:.18em;text-transform:uppercase;
    padding:0 16px 34px;position:relative;z-index:2;
}

/* ======================= RESPONSIVE ======================= */
@media(max-width:820px){
    .nav-links{display:none;}
    .slideshow{height:300px;}
    .slide .caption h2{font-size:1.4rem;}
    .page-card{padding:40px 24px;}
}
</style>
</head>
<body>

<!-- ================= NAVBAR ================= -->
<div class="nav-wrap">
    <nav class="navbar">
        <a class="logo" href="index.php">Paw<span>adopt</span> <small>🐾</small></a>
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="about.php">About Us</a></li>
            <li><a href="login.php">Pets</a></li>

            <li><a href="contact.php">Contact</a></li>
        </ul>
        <div class="nav-actions">
            <a class="btn-pill" href="login.php">Explore Pets ➜</a>

        </div>
    </nav>
</div>

<!-- ================= HERO SLIDESHOW ================= -->
<header class="hero">
    <div class="slideshow" id="slideshow">
        <?php foreach ($slides as $i => $src): ?>
        <div class="slide <?= $i === 0 ? 'active' : '' ?>">
            <img src="<?= htmlspecialchars($src) ?>" alt="PawAdopt featured companion <?= $i + 1 ?>">
            <div class="caption">
                <h2>Find a friend, give a home.</h2>
                <p>Featured companions waiting for their second chance.</p>
            </div>
        </div>
        <?php endforeach; ?>

        <button class="arrow prev" onclick="changeSlide(-1)" aria-label="Previous">‹</button>
        <button class="arrow next" onclick="changeSlide(1)" aria-label="Next">›</button>

        <div class="dots" id="dots">
            <?php foreach ($slides as $i => $src): ?>
            <button class="dot <?= $i === 0 ? 'active' : '' ?>" onclick="goSlide(<?= $i ?>)" aria-label="Slide <?= $i + 1 ?>"></button>
            <?php endforeach; ?>
        </div>
    </div>
</header>

<!-- ================= JOURNEY CONTENT ================= -->
<main class="page-card">

    <p class="eyebrow">Our Journey</p>
    <h1 class="section-title">Where It All Began</h1>
    <p class="lead">Paw-Adopt began with a simple question:</p>
    <div class="quote">“What happens to the stray animals rescued by our local veterinary clinic when they have nowhere to go?”</div>
    <p class="lead">Every day, local veterinary clinics and animal shelters encounter stray dogs and cats that need more than medical care. They need a safe place to stay, someone who will care for them, and most importantly, a family they can call their own.</p>
    <p class="lead">We saw an opportunity to help bridge that gap. Instead of letting rescued animals remain in shelters without knowing when their forever home would come, we envisioned a platform that could connect <strong>rescued pets with responsible adopters</strong>. That idea became <strong>Paw-Adopt</strong>.</p>

    <hr class="divider">

    <p class="eyebrow">The Team</p>
    <h1 class="section-title">Four Students, One Purpose</h1>
    <p class="lead">Paw-Adopt started as an initiative by <strong>four IT students from PUP Parañaque</strong> who wanted to use technology for something meaningful. We combined our different skills and worked toward one shared goal: <strong>to help rescued stray animals find responsible and loving families.</strong></p>

    <div class="team-grid">
        <div class="member">
            <span class="role-badge">Project Leader</span>
            <h3>Mark Clarence Adlao</h3>
            <p>Mark leads the team and keeps the project focused on its purpose — coordinating the team, guiding our direction, and making sure every part of Paw-Adopt helps both the animals and the people who care for them.</p>
        </div>
        <div class="member">
            <span class="role-badge">Main Developer</span>
            <h3>John Red Lopez</h3>
            <p>John turns the team's ideas into a working system. As the main developer, he builds the core features of Paw-Adopt, transforming our vision into a platform people can actually use.</p>
        </div>
        <div class="member">
            <span class="role-badge">Backup Developer</span>
            <h3>James Mathieu Paulite</h3>
            <p>James supports the development process and keeps the system moving forward — assisting with implementation, testing, and development whenever the team needs additional support.</p>
        </div>
        <div class="member">
            <span class="role-badge">Troubleshooter</span>
            <h3>Martin Kenyon Tacda</h3>
            <p>Martin identifies and resolves problems throughout the project. From unexpected errors to system issues, he works with the team to find solutions and keep Paw-Adopt running smoothly.</p>
        </div>
    </div>

    <hr class="divider">

    <p class="eyebrow">The Mission</p>
    <h1 class="section-title">From Strays to Companions</h1>
    <p class="lead">Our journey is not only about building a website. It is about creating a <strong>bridge</strong> — on one side, the local veterinary clinics and shelters caring for rescued animals; on the other, people searching for a companion and a chance to give an animal a loving home. <strong>Paw-Adopt connects them.</strong></p>

    <div class="timeline">
        <div class="t-item">
            <h4>🐾 Rescued</h4>
            <p>Local veterinary clinics and shelters take in stray dogs and cats and give them the medical care they need.</p>
        </div>
        <div class="t-item">
            <h4>🔍 Discovered</h4>
            <p>Through Paw-Adopt, rescued pets are introduced to potential adopters with verified profiles and veterinary support.</p>
        </div>
        <div class="t-item">
            <h4>🏡 Adopted</h4>
            <p>Adopters learn about the animals they are interested in and begin the adoption process responsibly — giving each pet a second chance at a forever home.</p>
        </div>
    </div>

    <hr class="divider">

    <p class="eyebrow">Our Belief</p>
    <h1 class="section-title">More Than Adoption</h1>
    <p class="lead">We believe adoption should not simply mean taking an animal home. It means accepting responsibility — giving a rescued animal patience, care, protection, and a place in the family.</p>
    <p class="lead">That is why Paw-Adopt promotes <strong>responsible adoption</strong>, supported by veterinary personnel and verified pet information. Every profile represents more than a name and a picture. <strong>It represents a life waiting for a second chance.</strong></p>

    <hr class="divider">

    <p class="eyebrow">Our Goal</p>
    <h1 class="section-title">The Journey Continues</h1>
    <p class="lead">What started as an idea from <strong>four IT students at PUP Parañaque</strong> continues to grow through technology, teamwork, and compassion. Because every stray deserves a chance. Every rescued pet deserves a home. And every loving family has the opportunity to give one.</p>

    <div class="cta">
        <h3>This is Paw-Adopt 🐾</h3>
        <p>From being rescued... to being discovered... to finding a family.<br><strong>Find a friend. Give a home.</strong></p>
        <a class="btn-pill" href="login.php">Explore Pets ➜</a>
    </div>

</main>

<footer>Featured Companions · PawAdopt © <?= date('Y') ?></footer>

<!-- ================= SLIDESHOW SCRIPT ================= -->
<script>
let current = 0;
const slides = document.querySelectorAll('.slide');
const dots   = document.querySelectorAll('.dot');
let timer    = setInterval(() => changeSlide(1), 5000); // auto-loop every 5s

function show(n){
    slides[current].classList.remove('active');
    dots[current].classList.remove('active');
    current = (n + slides.length) % slides.length;      // loops around
    slides[current].classList.add('active');
    dots[current].classList.add('active');
}
function changeSlide(step){ show(current + step); resetTimer(); }
function goSlide(n){ show(n); resetTimer(); }
function resetTimer(){ clearInterval(timer); timer = setInterval(() => changeSlide(1), 5000); }

// Pause on hover (optional nicety)
document.getElementById('slideshow').addEventListener('mouseenter', () => clearInterval(timer));
document.getElementById('slideshow').addEventListener('mouseleave', resetTimer);

</script>

</body>
</html>


