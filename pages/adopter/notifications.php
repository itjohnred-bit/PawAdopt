<?php
require_once __DIR__ . '/../../includes/header.php';
// The header already handles session and role check
?>

<div class="page-header">
    <h1 class="page-title"><span class="icon">🔔</span> My Notifications</h1>
    <a href="<?= APP_URL ?>/api/notifications.php?action=mark_all_read" class="btn btn-outline btn-sm">Mark All as Read</a>
</div>

<div class="card">
    <div id="notif-container">
        <!-- Notifications will load here -->
        <div style="padding: 40px; text-align: center;">Loading notifications...</div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    fetch('<?= APP_URL ?>/api/notifications.php?action=list')
        .then(response => response.json())
        .then(res => {
            const container = document.getElementById('notif-container');
            if (res.success && res.data.length > 0) {
                let html = '';
                res.data.forEach(n => {
                    const bgColor = n.is_read == 0 ? '#f0f9f8' : '#ffffff';
                    html += `
                        <div style="padding: 15px; border-bottom: 1px solid #eee; display: flex; align-items: flex-start; gap: 15px; background: ${bgColor};">
                            <div style="font-size: 1.5rem;">${n.type === 'MESSAGE' ? '💬' : '📢'}</div>
                            <div style="flex: 1;">
                                <div style="font-weight: 700;">${n.title}</div>
                                <div style="color: #666; font-size: 0.9rem;">${n.message}</div>
                                <div style="font-size: 0.8rem; color: #999;">${n.time_ago}</div>
                            </div>
                            ${n.link ? `<a href="${n.link}" class="btn btn-sm btn-primary">View</a>` : ''}
                        </div>`;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div style="padding: 40px; text-align: center;">No notifications found.</div>';
            }
        });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
