<?php
require_once __DIR__ . '/../../../includes/functions.php';
requireRole('ADMIN');
$pageTitle = 'veterinary Verification';
$user = getCurrentUser();
$db   = Database::getInstance();

$statusFilter = $_GET['status'] ?? '';
$where  = []; $params = [];
if ($statusFilter) { $where[] = 'sv.status = ?'; $params[] = $statusFilter; }
$whereStr = $where ? 'WHERE '.implode(' AND ',$where) : '';

$veterinarys = $db->fetchAll(
    "SELECT sv.*, sp.veterinary_name, sp.city, sp.phone, sp.is_verified, sp.description, u.email, u.username, u.created_at as joined_at
     FROM veterinary_verifications sv
     JOIN veterinary_profiles sp ON sv.veterinary_id = sp.veterinary_id
     JOIN users u ON sv.veterinary_id = u.user_id
     $whereStr ORDER BY sv.submitted_at DESC",
    $params
);

include __DIR__ . '/../../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><span class="icon">🏠</span> veterinary Verification</h1>
    <span class="text-muted"><?= count($veterinarys) ?> veterinary<?= count($veterinarys)!==1?'s':'' ?></span>
</div>

<div class="tabs">
    <?php $statuses = ['','PENDING','APPROVED','REJECTED'];
    $labels = ['All','Pending','Approved','Rejected'];
    foreach ($statuses as $i => $s): ?>
    <a href="?status=<?= $s ?>" class="tab-btn <?= $statusFilter===$s?'active':'' ?>" style="text-decoration:none"><?= $labels[$i] ?></a>
    <?php endforeach; ?>
</div>

<?php if (empty($veterinarys)): ?>
<div class="empty-state">
    <div class="empty-icon">🏠</div>
    <h3>No veterinarys found</h3>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:14px">
<?php foreach ($veterinarys as $sv): ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div style="flex:1">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                <div class="avatar-circle" style="background:var(--teal-light);color:var(--teal-dark)">🏠</div>
                <div>
                    <div class="fw-bold" style="font-size:1.05rem"><?= sanitize($sv['veterinary_name']) ?></div>
                    <div class="text-muted" style="font-size:.82rem"><?= sanitize($sv['email']) ?> <?= $sv['city'] ? '· '.sanitize($sv['city']) : '' ?></div>
                </div>
            </div>

            <?php if ($sv['description']): ?>
            <div style="font-size:.85rem;color:var(--gray-dark);margin-bottom:8px"><?= sanitize(substr($sv['description'],0,180)) ?>…</div>
            <?php endif; ?>

            <div style="display:flex;gap:10px;flex-wrap:wrap;font-size:.8rem;color:var(--gray-mid)">
                <span>Submitted: <?= date('M j, Y', strtotime($sv['submitted_at'])) ?></span>
                <?php if ($sv['reviewed_at']): ?><span>Reviewed: <?= date('M j, Y', strtotime($sv['reviewed_at'])) ?></span><?php endif; ?>
                <?php if ($sv['note']): ?><span>Note: <?= sanitize($sv['note']) ?></span><?php endif; ?>
            </div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px">
            <?= statusBadge($sv['status']) ?>
            <?php if ($sv['status'] !== 'APPROVED' && $sv['status'] !== 'REJECTED' || $sv['status'] === 'PENDING'): ?>
            <div style="display:flex;gap:6px">
                <button onclick="verifyveterinary(<?= $sv['veterinary_id'] ?>,'APPROVED')" class="btn btn-success btn-sm">✅ Approve</button>
                <button onclick="showRejectModal(<?= $sv['veterinary_id'] ?>)" class="btn btn-danger btn-sm">❌ Reject</button>
            </div>
            <?php elseif ($sv['status'] === 'REJECTED'): ?>
            <button onclick="verifyveterinary(<?= $sv['veterinary_id'] ?>,'APPROVED')" class="btn btn-success btn-sm">Re-Approve</button>
            <?php elseif ($sv['status'] === 'APPROVED'): ?>
            <button onclick="showRejectModal(<?= $sv['veterinary_id'] ?>)" class="btn btn-danger btn-sm">Revoke</button>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">❌ Reject / Revoke veterinary</span>
            <button class="modal-close" onclick="closeModal('rejectModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Reason (shown to veterinary)</label>
                <textarea id="rejectNote" class="form-control" rows="3" placeholder="Reason for rejection…"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
            <button class="btn btn-danger" onclick="confirmReject()">Reject</button>
        </div>
    </div>
</div>

<script>
let rejectShelId = null;
function showRejectModal(veterinaryId) { rejectShelId = veterinaryId; openModal('rejectModal'); }
async function confirmReject() {
    const note = document.getElementById('rejectNote').value;
    closeModal('rejectModal');
    await verifyveterinary(rejectShelId, 'REJECTED', note);
}
async function verifyveterinary(veterinaryId, status, note = '') {
    const res = await apiRequest('/PAWAdopt/api/admin.php','POST',new URLSearchParams({action:'verify_veterinary',veterinary_id:veterinaryId,status:status,note:note||'Reviewed by admin'}));
    if (res.success) { showToast('veterinary '+status+'!'); setTimeout(()=>location.reload(),900); }
    else showToast(res.message,'error');
}
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
