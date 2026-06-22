<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
startSession();
requireLogin();

$db     = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$user   = getCurrentUser();

function handleCertificateUpload($file) {
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    
    $basePath = dirname(__DIR__); 
    $uploadDir = $basePath . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'certificates' . DIRECTORY_SEPARATOR;

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $newName = 'cert_' . bin2hex(random_bytes(8)) . '.pdf';
    $destPath = $uploadDir . $newName;

    if (move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['url' => 'uploads/certificates/' . $newName];
    }

    return ['error' => 'Could not save the certificate. Check folder permissions.'];
}

switch ($action) {

    case 'list':
        $where  = ['p.status = ?'];
        $params = ['Available'];

        if (!empty($_GET['species']))  { $where[] = 'p.species = ?';  $params[] = $_GET['species']; }
        if (!empty($_GET['sex']))      { $where[] = 'p.sex = ?';      $params[] = $_GET['sex']; }
        if (!empty($_GET['size']))     { $where[] = 'p.size = ?';     $params[] = $_GET['size']; }
        if (!empty($_GET['city']))     { $where[] = 'sp.city LIKE ?'; $params[] = '%'.$_GET['city'].'%'; }
        if (!empty($_GET['search']))   {
            $where[] = '(p.name LIKE ? OR p.breed LIKE ? OR p.description LIKE ?)';
            $params[] = '%'.$_GET['search'].'%';
            $params[] = '%'.$_GET['search'].'%';
            $params[] = '%'.$_GET['search'].'%';
        }
        if (!empty($_GET['age_min'])) { $where[] = 'p.age_months >= ?'; $params[] = (int)$_GET['age_min']; }
        if (!empty($_GET['age_max'])) { $where[] = 'p.age_months <= ?'; $params[] = (int)$_GET['age_max']; }

        $whereStr = implode(' AND ', $where);
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 12;
        $offset = ($page - 1) * $limit;

        $countRes = $db->fetch(
            "SELECT COUNT(*) as total FROM pets p
             JOIN shelter_profiles sp ON p.shelter_id = sp.shelter_id
             WHERE $whereStr", $params
        );
        $total = $countRes ? (int)$countRes['total'] : 0;

        $pets = $db->fetchAll(
            "SELECT p.*, sp.shelter_name, sp.city as shelter_city,
                    (SELECT pp.photo_url FROM pet_photos pp WHERE pp.pet_id = p.pet_id AND pp.is_primary = 1 LIMIT 1) as primary_photo
             FROM pets p
             JOIN shelter_profiles sp ON p.shelter_id = sp.shelter_id
             WHERE $whereStr
             ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset",
            $params
        );

        foreach ($pets as &$p) {
            $p['age_display']  = formatAge($p['age_months']);
            if (!empty($p['primary_photo'])) {
                $p['photo_url'] = 'data:image/jpeg;base64,' . base64_encode($p['primary_photo']);
            } else {
                $p['photo_url'] = APP_URL . '/assets/images/pet-placeholder.png';
            }
        }
        if ($user['role'] === 'ADOPTER') {
            $favPetIds = $db->fetchAll("SELECT pet_id FROM favorites WHERE adopter_id = ?", [$user['user_id']]);
            $favIds = array_column($favPetIds, 'pet_id');
            foreach ($pets as &$p) {
                $p['is_favorited'] = in_array($p['pet_id'], $favIds);
            }
        }
        jsonSuccess(['pets' => $pets, 'total' => $total, 'pages' => ceil($total / $limit), 'current_page' => $page]);
        break;

    case 'get':
        $petId = (int)($_GET['id'] ?? 0);
        if (!$petId) { jsonError('Pet ID required.'); break; }
        $pet = $db->fetch(
            "SELECT p.*, sp.shelter_name, sp.city as shelter_city, sp.phone as shelter_phone
             FROM pets p
             JOIN shelter_profiles sp ON p.shelter_id = sp.shelter_id
             WHERE p.pet_id = ?",
            [$petId]
        );
        if (!$pet) { jsonError('Pet not found.', 404); break; }
        
        $rawPhotos = $db->fetchAll("SELECT * FROM pet_photos WHERE pet_id = ? ORDER BY is_primary DESC", [$petId]);
        foreach ($rawPhotos as &$rp) {
            if (!empty($rp['photo_url'])) {
                $rp['photo_url'] = 'data:image/jpeg;base64,' . base64_encode($rp['photo_url']);
            }
        }
        $pet['photos'] = $rawPhotos;
        $pet['age_display'] = formatAge($pet['age_months']);
        if ($user['role'] === 'ADOPTER') {
            $fav = $db->fetch("SELECT favorite_id FROM favorites WHERE adopter_id = ? AND pet_id = ?", [$user['user_id'], $petId]);
            $pet['is_favorited'] = (bool)$fav;
        }
        jsonSuccess($pet);
        break;

    case 'add':
        if ($user['role'] !== 'SHELTER') { jsonError('Shelter only.', 403); break; }
        if ($method !== 'POST') { jsonError('POST only.', 405); break; }

        $name    = trim($_POST['name'] ?? '');
        $species = $_POST['species'] ?? 'Dog';
        $breed   = trim($_POST['breed'] ?? '');

        if (!$name) { jsonError('Pet name is required.'); break; }

        $duplicate = $db->fetch(
            "SELECT pet_id FROM pets 
             WHERE name = ? AND species = ? AND breed = ? AND shelter_id = ? AND status != 'Removed' LIMIT 1",
            [$name, $species, $breed, $user['user_id']]
        );

        if ($duplicate) { 
            jsonError('You have already listed a pet with this exact name, species, and breed.'); 
            break; 
        }

        $certPath = null;
        if (isset($_FILES['medical_cert']) && $_FILES['medical_cert']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = handleCertificateUpload($_FILES['medical_cert']);
            if (isset($uploadResult['error'])) {
                jsonError($uploadResult['error']);
                exit;
            }
            $certPath = $uploadResult['url'];
        }

        $db->execute(
            "INSERT INTO pets (shelter_id, name, species, breed, age_months, sex, size, color, temperament, medical_notes, description, medical_certificate)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $user['user_id'], $name, $species, $breed, 
                (int)($_POST['age_months'] ?? 0), $_POST['sex'] ?? 'Unknown', $_POST['size'] ?? 'Medium', 
                trim($_POST['color'] ?? ''), trim($_POST['temperament'] ?? ''), trim($_POST['medical_notes'] ?? ''), 
                trim($_POST['description'] ?? ''), $certPath
            ]
        );
        $petId = (int)$db->lastInsertId();

        // BLOB Photo Storage Handler
        if (!empty($_FILES['photos']) && is_array($_FILES['photos']['tmp_name'])) {
            $files = $_FILES['photos']; 
            $isPrimary = 1;
            $conn = $db->getConnection();
            
            for ($i = 0; $i < count($files['tmp_name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $imgBinaryData = file_get_contents($files['tmp_name'][$i]);
                    
                    $stmt = $conn->prepare("INSERT INTO pet_photos (pet_id, photo_url, is_primary) VALUES (?, ?, ?)");
                    $stmt->bindParam(1, $petId, PDO::PARAM_INT);
                    $stmt->bindParam(2, $imgBinaryData, PDO::PARAM_LOB);
                    $stmt->bindParam(3, $isPrimary, PDO::PARAM_INT);
                    $stmt->execute();
                    
                    $isPrimary = 0;
                }
            }
        }
        jsonSuccess(['pet_id' => $petId], 'Pet listing created successfully!');
        break;

    case 'edit':
        if ($user['role'] !== 'SHELTER' && $user['role'] !== 'ADMIN') { jsonError('Unauthorized.', 403); break; }
        $petId = (int)($_POST['pet_id'] ?? 0);
        if (!$petId) { jsonError('Pet ID required.'); break; }

        if ($user['role'] === 'SHELTER') {
            $owner = $db->fetch("SELECT pet_id FROM pets WHERE pet_id = ? AND shelter_id = ?", [$petId, $user['user_id']]);
            if (!$owner) { jsonError('Not your pet listing.', 403); break; }
        }

        $fields = ['name','species','breed','sex','size','color','temperament','medical_notes','description','status'];
        $sets = []; $params = [];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) { $sets[] = "$f = ?"; $params[] = trim($_POST[$f]); }
        }
        if (isset($_POST['age_months'])) { $sets[] = "age_months = ?"; $params[] = (int)$_POST['age_months']; }

        if (isset($_FILES['medical_cert']) && $_FILES['medical_cert']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = handleCertificateUpload($_FILES['medical_cert']);
            if (isset($uploadResult['error'])) {
                jsonError($uploadResult['error']);
                exit;
            }
            $sets[] = "medical_certificate = ?";
            $params[] = $uploadResult['url'];
        }

        if (!empty($sets)) {
            $params[] = $petId;
            $db->execute("UPDATE pets SET " . implode(',', $sets) . " WHERE pet_id = ?", $params);
        }

        // BLOB Edit Photo Storage Handler
        if (!empty($_FILES['photos']) && is_array($_FILES['photos']['tmp_name'])) {
            $files = $_FILES['photos'];
            $conn = $db->getConnection();
            
            for ($i = 0; $i < count($files['tmp_name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $imgBinaryData = file_get_contents($files['tmp_name'][$i]);
                    
                    $stmt = $conn->prepare("INSERT INTO pet_photos (pet_id, photo_url, is_primary) VALUES (?, ?, 0)");
                    $stmt->bindParam(1, $petId, PDO::PARAM_INT);
                    $stmt->bindParam(2, $imgBinaryData, PDO::PARAM_LOB);
                    $stmt->execute();
                }
            }
        }

        // --- AUTOMATIC PRIMARY PHOTO RECOVERY ---
        // If the pet has photos but none are flagged as primary, promote the first one
        $hasPrimary = $db->fetch(
            "SELECT photo_id FROM pet_photos WHERE pet_id = ? AND is_primary = 1 LIMIT 1",
            [$petId]
        );

        if (!$hasPrimary) {
            $fallbackPhoto = $db->fetch(
                "SELECT photo_id FROM pet_photos WHERE pet_id = ? ORDER BY photo_id ASC LIMIT 1",
                [$petId]
            );
            if ($fallbackPhoto) {
                $db->execute(
                    "UPDATE pet_photos SET is_primary = 1 WHERE photo_id = ?",
                    [$fallbackPhoto['photo_id']]
                );
            }
        }
        // ----------------------------------------

        jsonSuccess([], 'Pet updated successfully!');
        break;

    case 'delete':
        if ($user['role'] !== 'SHELTER' && $user['role'] !== 'ADMIN') { jsonError('Unauthorized.', 403); break; }
        $petId = (int)($_GET['id'] ?? $_POST['pet_id'] ?? 0);
        if (!$petId) { jsonError('Pet ID required.'); break; }

        if ($user['role'] === 'SHELTER') {
            $owner = $db->fetch("SELECT pet_id FROM pets WHERE pet_id = ? AND shelter_id = ?", [$petId, $user['user_id']]);
            if (!$owner) { jsonError('Not your pet listing.', 403); break; }
        }
        $db->execute("UPDATE pets SET status = 'Removed' WHERE pet_id = ?", [$petId]);
        jsonSuccess([], 'Pet listing removed.');
        break;

    case 'delete_photo':
        if ($user['role'] !== 'SHELTER' && $user['role'] !== 'ADMIN') { 
            jsonError('Unauthorized.', 403); 
            break; 
        }

        $photoId = (int)($_POST['photo_id'] ?? 0);
        if (!$photoId) { 
            jsonError('Photo ID required.'); 
            break; 
        }

        if ($user['role'] === 'SHELTER') {
            $check = $db->fetch(
                "SELECT pp.photo_id, pp.pet_id FROM pet_photos pp
                 JOIN pets p ON pp.pet_id = p.pet_id
                 WHERE pp.photo_id = ? AND p.shelter_id = ?", 
                [$photoId, $user['user_id']]
            );
            if (!$check) { 
                jsonError('Unauthorized: This photo does not belong to your shelter listings.', 403); 
                break; 
            }
            $petId = $check['pet_id'];
        } else {
            $check = $db->fetch("SELECT pet_id FROM pet_photos WHERE photo_id = ?", [$photoId]);
            $petId = $check ? $check['pet_id'] : 0;
        }

        // Delete the requested photo row
        $db->execute("DELETE FROM pet_photos WHERE photo_id = ?", [$photoId]);
        
        // Also safeguard 'delete_photo' so that if the user deletes the active primary image, 
        // it auto-assigns primary status to the next picture in line immediately
        if ($petId) {
            $hasPrimary = $db->fetch("SELECT photo_id FROM pet_photos WHERE pet_id = ? AND is_primary = 1 LIMIT 1", [$petId]);
            if (!$hasPrimary) {
                $nextPhoto = $db->fetch("SELECT photo_id FROM pet_photos WHERE pet_id = ? ORDER BY photo_id ASC LIMIT 1", [$petId]);
                if ($nextPhoto) {
                    $db->execute("UPDATE pet_photos SET is_primary = 1 WHERE photo_id = ?", [$nextPhoto['photo_id']]);
                }
            }
        }

        jsonSuccess([], 'Photo removed successfully!');
        break;

    case 'my_pets':
        if ($user['role'] !== 'SHELTER') { jsonError('Shelter only.', 403); break; }
        $pets = $db->fetchAll(
            "SELECT p.*,
             (SELECT pp.photo_url FROM pet_photos pp WHERE pp.pet_id = p.pet_id AND pp.is_primary = 1 LIMIT 1) as primary_photo,
             (SELECT COUNT(*) FROM adoption_applications aa WHERE aa.pet_id = p.pet_id) as app_count
             FROM pets p
             WHERE p.shelter_id = ? AND p.status != 'Removed'
             ORDER BY p.created_at DESC",
            [$user['user_id']]
        );
        foreach ($pets as &$p) {
            $p['age_display'] = formatAge($p['age_months']);
            if (!empty($p['primary_photo'])) {
                $p['photo_url'] = 'data:image/jpeg;base64,' . base64_encode($p['primary_photo']);
            } else {
                $p['photo_url'] = APP_URL . '/assets/images/pet-placeholder.png';
            }
        }
        jsonSuccess($pets);
        break;

    default:
        jsonError('Unknown action.', 400);
}
?>