<?php
require_once __DIR__ . '/../../includes/functions.php';
requireRole('ADMIN');
$pageTitle = 'User Management';
$user = getCurrentUser();
$db   = Database::getInstance();

$search = trim($_GET['search'] ?? '');
$role   = $_GET['role'] ?? '';
$where  = []; $params = [];
if ($search) { $where[] = '(username LIKE ? OR email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($role)   { $where[] = 'role = ?'; $params[] = $role; }
$whereStr = $where ? 'WHERE '.implode(' AND ',$where) : '';

$users = $db->fetchAll("SELECT * FROM users $whereStr ORDER BY created_at DESC", $params);
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="icon">👥</span> User Management</h1>
    <span class="text-muted"><?= count($users) ?> user<?= count($users)!==1?'s':'' ?></span>
</div>

<form method="GET">
<div class="filter-bar">
    <div class="search-input-wrap">
        <i class="fas fa-search search-icon"></i>
        <input type="text" name="search" class="form-control" placeholder="Search username or email…" value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="role" class="form-control" onchange="this.form.submit()">
        <option value="">All Roles</option>
        <option value="ADOPTER" <?= $role==='ADOPTER'?'selected':'' ?>>Adopter</option>
        <option value="SHELTER" <?= $role==='SHELTER'?'selected':'' ?>>Shelter</option>
        <option value="ADMIN"   <?= $role==='ADMIN'  ?'selected':'' ?>>Admin</option>
    </select>
    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
    <?php if ($search||$role): ?><a href="?" class="btn btn-secondary">Clear</a><?php endif; ?>
</div>
</form>

<div class="card" style="padding:0">
<div class="table-wrap">
<table class="data-table">
    <thead><tr><th>#</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
    <tr id="user-<?= $u['user_id'] ?>">
        <td class="text-muted"><?= $u['user_id'] ?></td>
        <td>
            <div style="display:flex;align-items:center;gap:8px">
                <div class="avatar-circle" style="width:32px;height:32px;font-size:.85rem"><?= strtoupper(substr($u['username'],0,1)) ?></div>
                <span class="fw-bold"><?= sanitize($u['username']) ?></span>
            </div>
        </td>
        <td class="text-muted"><?= sanitize($u['email']) ?></td>
        <td><?= statusBadge($u['role']) ?></td>
        <td><?= $u['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>' ?></td>
        <td class="text-muted" style="font-size:.82rem"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
        <td>
            <div style="display:flex;gap:6px">
                <?php if ($u['user_id'] !== $user['user_id']): ?>
                <button onclick="toggleUser(<?= $u['user_id'] ?>,<?= $u['is_active'] ?>)"
                        class="btn btn-sm <?= $u['is_active']?'btn-warning':'btn-success' ?>">
                    <?= $u['is_active']?'Deactivate':'Activate' ?>
                </button>
                <button onclick="deleteUser(<?= $u['user_id'] ?>)" class="btn btn-danger btn-sm">Delete</button>
                <?php else: ?>
                <span class="text-muted" style="font-size:.8rem">You</span>
                <?php endif; ?>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<script>
/**
 * MISSING HELPERS ADDED BELOW
 */

// 1. Fixed confirmAction to accept a callback
function confirmAction(message, callback) {
    if (window.confirm(message)) {
        callback();
    }
}

// 2. Fixed apiRequest helper
async function apiRequest(url, method, body) {
    try {
        const res = await fetch(url, { method, body });
        return await res.json();
    } catch (err) {
        console.error("Fetch Error:", err);
        return { success: false, message: 'Network error.' };
    }
}

// 3. Simple showToast alert
function showToast(message, type = 'success') {
    alert((type === 'error' ? '❌ ' : '✅ ') + message);
}

/**
 * ORIGINAL FUNCTIONS
 */
async function toggleUser(userId, isActive) {
    const actionText = isActive ? 'Deactivate' : 'Activate';
    confirmAction(`${actionText} this user?`, async () => {
        const res = await apiRequest('/PAWAdopt/api/admin.php','POST',new URLSearchParams({action:'toggle_user',user_id:userId}));
        if (res.success) { 
            showToast('User updated.'); 
            setTimeout(()=>location.reload(), 800); 
        }
        else showToast(res.message,'error');
    });
}

async function deleteUser(userId) {
    confirmAction('Permanently delete this user? This cannot be undone!', async () => {
        const res = await apiRequest('/PAWAdopt/api/admin.php','POST',new URLSearchParams({action:'delete_user',user_id:userId}));
        if (res.success) { 
            document.getElementById('user-'+userId)?.remove(); 
            showToast('User deleted.'); 
        }
        else showToast(res.message,'error');
    });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
