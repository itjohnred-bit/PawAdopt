<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/functions.php';

if (!defined('APP_URL')) {
    // Detect protocol: check Render's reverse proxy header FIRST
    $protocol = 'http';
    if (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||          // Direct HTTPS
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&                          // Behind Render/Vercel/etc.
         $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) &&                            // Some proxies
         $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
    ) {
        $protocol = 'https';
    }
    
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    
    // Detect app subfolder (local XAMPP uses /PawAdopt, Render deploys at root)
    if (preg_match('#(/[Pp][Aa][Ww][Aa]dopt)#', $scriptName, $m)) {
        $appRoot = $m[1];
    } else {
        $appRoot = '';  // Production: deployed at domain root
    }
    
    define('APP_URL', "$protocol://$host$appRoot");
}

if (function_exists('requireRole')) {
    requireRole('ADOPTER');
}

$pageTitle = 'Dashboard';

$user = function_exists('getCurrentUser') ? getCurrentUser() : null;
if (!$user || empty($user['user_id'])) {
    header('Location: ' . APP_URL . '/pages/auth/login.php');
    exit;
}

if (!class_exists('Database')) {
    die('Database class not found. Check includes/functions.php');
}
$db = Database::getInstance();

$favRow   = $db->fetch("SELECT COUNT(*) AS c FROM favorites WHERE adopter_id = ?", [$user['user_id']]);
$appRow   = $db->fetch("SELECT COUNT(*) AS c FROM adoption_applications WHERE adopter_id = ?", [$user['user_id']]);
$petRow   = $db->fetch("SELECT COUNT(*) AS c FROM pets WHERE status = 'Available'");

$favCount = (int)($favRow['c'] ?? 0);
$appCount = (int)($appRow['c'] ?? 0);
$petCount = (int)($petRow['c'] ?? 0);
$msgCount = function_exists('getUnreadMessageCount') ? (int)getUnreadMessageCount($user['user_id']) : 0;

$profile = $db->fetch("SELECT * FROM adopter_profiles WHERE adopter_id = ?", [$user['user_id']]);
if (!is_array($profile)) {
    $profile = [];
}

$recentApps = $db->fetchAll(
    "SELECT aa.*, p.name AS pet_name, p.species, sp.shelter_name,
     (SELECT pp.photo_url FROM pet_photos pp WHERE pp.pet_id = p.pet_id AND pp.is_primary = 1 LIMIT 1) AS pet_photo
     FROM adoption_applications aa
     JOIN pets p ON aa.pet_id = p.pet_id
     JOIN shelter_profiles sp ON aa.shelter_id = sp.shelter_id
     WHERE aa.adopter_id = ?
     ORDER BY aa.submitted_at DESC LIMIT 5",
    [$user['user_id']]
);
if (!is_array($recentApps)) {
    $recentApps = [];
}

$featuredPets = $db->fetchAll(
    "SELECT p.*, sp.shelter_name, sp.city AS shelter_city,
     (SELECT pp.photo_url FROM pet_photos pp WHERE pp.pet_id = p.pet_id AND pp.is_primary = 1 LIMIT 1) AS primary_photo
     FROM pets p
     JOIN shelter_profiles sp ON p.shelter_id = sp.shelter_id
     WHERE p.status = 'Available'
     ORDER BY RAND() LIMIT 4"
);
if (!is_array($featuredPets)) {
    $featuredPets = [];
}

if (!function_exists('sanitize')) {
    function sanitize($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('statusBadge')) {
    function statusBadge($status) {
        $s = sanitize($status ?? '');
        return '<span class="badge">' . $s . '</span>';
    }
}

$placeholderImg = APP_URL . '/assets/images/pet-placeholder.png';

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="icon">🏠</span> Welcome back, <?= sanitize($profile['full_name'] ?? ($user['username'] ?? 'Adopter')) ?>!</h1>
    <a href="<?= APP_URL ?>/pages/adopter/browse.php" class="btn btn-primary">
        <i class="fas fa-search"></i> Browse Pets
    </a>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon teal"><span>🐾</span></div>
        <div>
            <div class="stat-value"><?= $petCount ?></div>
            <div class="stat-label">Pets Available</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><span>❤️</span></div>
        <div>
            <div class="stat-value"><?= $favCount ?></div>
            <div class="stat-label">Favorites Saved</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><span>📋</span></div>
        <div>
            <div class="stat-value"><?= $appCount ?></div>
            <div class="stat-label">Applications</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><span>💬</span></div>
        <div>
            <div class="stat-value"><?= $msgCount ?></div>
            <div class="stat-label">New Messages</div>
        </div>
    </div>
</div>

<div class="quick-actions mb-3">
    <a href="<?= APP_URL ?>/pages/adopter/browse.php" class="quick-action-btn">
        <span class="qa-icon">🔍</span><span class="qa-label">Browse Pets</span>
    </a>
    <a href="<?= APP_URL ?>/pages/adopter/favorites.php" class="quick-action-btn">
        <span class="qa-icon">❤️</span><span class="qa-label">Favorites</span>
    </a>
    <a href="<?= APP_URL ?>/pages/adopter/applications.php" class="quick-action-btn">
        <span class="qa-icon">📋</span><span class="qa-label">Applications</span>
    </a>
    <a href="<?= APP_URL ?>/pages/adopter/messages.php" class="quick-action-btn">
        <span class="qa-icon">💬</span><span class="qa-label">Messages</span>
    </a>
    <a href="<?= APP_URL ?>/pages/adopter/profile.php" class="quick-action-btn">
        <span class="qa-icon">👤</span><span class="qa-label">Profile</span>
    </a>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title"><i class="fas fa-file-alt"></i> Recent Applications</div>
        <?php if (empty($recentApps)): ?>
        <div class="empty-state" style="padding:30px 0">
            <div class="empty-icon">🐾</div>
            <p>No applications yet. <a href="<?= APP_URL ?>/pages/adopter/browse.php">Browse pets</a> to get started!</p>
        </div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Pet</th><th>Shelter</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($recentApps as $app): ?>
                <?php
                    $photo = !empty($app['pet_photo'])
                        ? APP_URL . '/' . ltrim($app['pet_photo'], '/')
                        : $placeholderImg;
                    $submittedAt = !empty($app['submitted_at']) ? strtotime($app['submitted_at']) : false;
                ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <img src="<?= sanitize($photo) ?>" class="pet-thumb" alt="<?= sanitize($app['pet_name'] ?? 'Pet') ?>" onerror="this.src='<?= sanitize($placeholderImg) ?>'">
                            <div>
                                <div class="fw-bold"><?= sanitize($app['pet_name'] ?? 'Unknown') ?></div>
                                <div class="text-muted" style="font-size:.78rem"><?= sanitize($app['species'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= sanitize($app['shelter_name'] ?? '') ?></td>
                    <td><?= statusBadge($app['status'] ?? '') ?></td>
                    <td style="font-size:.82rem;color:var(--gray-mid)"><?= $submittedAt ? date('M j', $submittedAt) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-2">
            <a href="<?= APP_URL ?>/pages/adopter/applications.php" class="btn btn-outline btn-sm">View All Applications</a>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><i class="fas fa-star"></i> Featured Pets</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <?php if (empty($featuredPets)): ?>
                <div class="empty-state" style="grid-column:span 2;padding:20px">
                    <p>No pets available right now.</p>
                </div>
            <?php else: ?>
                <?php foreach ($featuredPets as $p): ?>
                <?php
                    $photo = !empty($p['primary_photo'])
                        ? APP_URL . '/' . ltrim($p['primary_photo'], '/')
                        : $placeholderImg;
                    $petId = $p['pet_id'] ?? 0;
                ?>
                <a href="<?= APP_URL ?>/pages/adopter/pet-detail.php?id=<?= (int)$petId ?>" style="text-decoration:none;color:inherit;">
                    <div class="pet-card" style="cursor:pointer">
                        <div class="pet-card-img" style="height:130px">
                            <img src="<?= sanitize($photo) ?>" alt="<?= sanitize($p['name'] ?? 'Pet') ?>" onerror="this.src='<?= sanitize($placeholderImg) ?>'" style="height:130px;object-fit:cover;width:100%">
                            <div class="pet-species-badge"><?= sanitize($p['species'] ?? '') ?></div>
                        </div>
                        <div class="pet-card-body" style="padding:10px">
                            <div class="pet-card-name" style="font-size:.95rem"><?= sanitize($p['name'] ?? 'Unnamed') ?></div>
                            <div class="pet-card-meta">
                                <span>🏠 <?= sanitize($p['shelter_city'] ?? '') ?></span>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="mt-2">
            <a href="<?= APP_URL ?>/pages/adopter/browse.php" class="btn btn-primary btn-sm">View All Pets 🐾</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
