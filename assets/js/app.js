(function () {
    'use strict';

    window.BASE_URL = (window.location.hostname.includes('onrender.com') || 
                       window.location.protocol === 'https:')
    ? window.location.origin                       // Production → no subfolder
    : window.location.origin + '/PawAdopt';        // Local XAMPP → with subfolder

    async function apiRequest(url, method = 'GET', data = null) {
        const opts = { method, credentials: 'same-origin', headers: {} };
        if (data) {
            if (data instanceof FormData) opts.body = data;
            else if (data instanceof URLSearchParams) {
                opts.headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
                opts.body = data.toString();
            } else {
                opts.headers['Content-Type'] = 'application/json';
                opts.body = JSON.stringify(data);
            }
        }
        try {
            const res = await fetch(url, opts);
            const ct = res.headers.get('content-type');
            if (ct && ct.indexOf('application/json') !== -1) return await res.json();
            const text = await res.text();
            console.error('Server returned non-JSON response:', text);
            return { success: false, message: 'Server Error: Check console/network tab.' };
        } catch (e) {
            console.error('API error:', e);
            return { success: false, message: 'Network or System error' };
        }
    }

    async function submitAddPet(form) {
        const fd = new FormData(form);
        fd.append('action', 'add');
        const btn = form.querySelector('[type=submit]');
        const original = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Posting...';
        try {
            const res = await apiRequest(window.BASE_URL + '/api/pets.php', 'POST', fd);
            if (res.success) {
                showToast(res.message);
                // Dynamically prefixes the correct base path ensuring no 404s
                setTimeout(() => window.location.href = window.BASE_URL + '/pages/shelter/pets.php', 2000);
            } else {
                showToast(res.message, 'error');
            }
        } catch {
            showToast('An unexpected error occurred', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = original;
        }
    }

    function showToast(message, type = 'success', duration = 3500) {
        const toast = document.createElement('div');
        const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
        toast.className = 'flash-message flash-' + type;
        toast.style.cssText = 'position:fixed;top:72px;right:20px;z-index:9999;';
        toast.innerHTML = '<span>' + icon + '</span> ' + escapeHtml(message) +
            '<button type="button" class="flash-close">&times;</button>';
        toast.querySelector('.flash-close').addEventListener('click', () => toast.remove());
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), duration);
    }

    function escapeHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    async function loadNotifications() {
        const list = document.getElementById('notifList');
        if (!list) return;
        list.innerHTML = '<div class="notif-loading"><i class="fas fa-spinner fa-spin"></i></div>';
        try {
            const data = await apiRequest(window.BASE_URL + '/api/notifications.php?action=list');
            if (data.success && data.data && data.data.length > 0) {
                list.innerHTML = data.data.map(n => {
                    const body = n.message || n.content || n.body || '';
                    const link = n.link || '';
                    return '<div class="notif-item ' + (n.is_read == 0 ? 'unread' : '') + '"'
                         + ' data-notif-id="' + n.notification_id + '"'
                         + ' data-notif-link="' + encodeURIComponent(link) + '">'
                         + '<div class="notif-title">' + escapeHtml(n.title) + '</div>'
                         + '<div class="notif-body">'  + escapeHtml(body)   + '</div>'
                         + '</div>';
                }).join('');
                list.addEventListener('click', notifClickDelegator);
            } else {
                list.innerHTML = '<div class="notif-empty">🐾 No notifications yet!</div>';
            }
        } catch {
            list.innerHTML = '<div class="notif-empty">Failed to load.</div>';
        }
    }

    async function notifClickDelegator(e) {
        const item = e.target.closest('.notif-item');
        if (!item) return;
        await markRead(item.dataset.notifId, decodeURIComponent(item.dataset.notifLink || ''));
    }

    async function markRead(id, link) {
        await apiRequest(window.BASE_URL + '/api/notifications.php?action=mark_read&id=' + encodeURIComponent(id));
        if (link && link !== '' && link !== 'undefined') window.location.href = link;
    }

    async function toggleFavorite(petId, btn) {
        btn.disabled = true;
        const data = new URLSearchParams();
        data.append('pet_id', petId);
        data.append('action', 'toggle');
        const res = await apiRequest(window.BASE_URL + '/api/favorites.php', 'POST', data);
        if (res.success) {
            btn.classList.toggle('active', !!res.data.favorited);
            btn.innerHTML = res.data.favorited ? '<i class="fas fa-heart"></i>' : '<i class="far fa-heart"></i>';
            showToast(res.message);
        }
        btn.disabled = false;
    }

    function openModal(id) { document.getElementById(id)?.classList.add('show'); }
    function closeModal(event, modalId) {
        if (event) { event.preventDefault(); event.stopPropagation(); }
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.remove('show');
        modal.style.display = 'none';
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) backdrop.remove();
    }

    async function viewApplication(appId) {
        try {
            const res = await fetch(window.BASE_URL + '/api/applications.php?action=get_detail&id=' + encodeURIComponent(appId), { credentials: 'same-origin' });
            const result = await res.json();
            if (!result.success) { showToast(result.message || 'Could not load data', 'error'); return; }
            const d = result.data;

            const setText = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v || 'N/A'; };
            setText('modal_adopter_name', d.adopter_name || d.adopter_username);
            setText('modal_pet_name',     d.pet_name);
            setText('modal_message',      d.message_to_shelter || 'No extra message provided.');
            setText('modal_status',       d.status);

            const container = document.getElementById('modal_responses_container');
            if (container) {
                container.innerHTML = '';
                if (d.screening_responses) {
                    try {
                        const responses = typeof d.screening_responses === 'string'
                                          ? JSON.parse(d.screening_responses)
                                          : d.screening_responses;
                        let grid = '<div class="row g-0" style="background:#fff;">';
                        for (const [key, value] of Object.entries(responses)) {
                            if (key === 'pet_id' || key === 'action') continue;
                            const label = key.replace(/_/g, ' ').toUpperCase();
                            const isLong = key.includes('address') || String(value).length > 40;
                            const col = isLong ? 'col-12' : 'col-6';
                            grid += '<div class="' + col + ' border-bottom py-3 px-2">'
                                  + '<label class="text-muted fw-bold d-block" style="font-size:10px;letter-spacing:.5px;">' + escapeHtml(label) + '</label>'
                                  + '<div class="text-dark fw-semibold" style="font-size:14px;">' + escapeHtml(value || '—') + '</div>'
                                  + '</div>';
                        }
                        grid += '</div>';
                        container.innerHTML = grid;
                    } catch {
                        container.innerHTML = '<div class="p-3 text-danger">Error: Could not parse form data.</div>';
                    }
                } else {
                    container.innerHTML = '<div class="p-4 text-center text-muted">No screening form data available.</div>';
                }
            }

            const modalEl = document.getElementById('applicationModal');
            if (modalEl) {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                } else {
                    modalEl.classList.add('show');
                    modalEl.style.display = 'block';
                }
            }
        } catch (err) {
            console.error('View Error:', err);
            showToast('System error: Connection failed.', 'error');
        }
    }

    let currentConvId = null;
    let pollInterval = null;

    function selectConversation(convId, otherName) {
        currentConvId = convId;
        document.querySelectorAll('.conv-item').forEach(i => i.classList.remove('active'));
        document.querySelector('.conv-item[data-conv="' + cssEscape(convId) + '"]')?.classList.add('active');
        const header = document.getElementById('chatPartnerName');
        if (header) header.textContent = otherName;
        loadMessages(convId);
        if (pollInterval) clearInterval(pollInterval);
        pollInterval = setInterval(() => {
            if (document.visibilityState !== 'visible') return;
            loadMessages(convId);
        }, 5000);
    }

    function cssEscape(value) {
        if (window.CSS && CSS.escape) return CSS.escape(String(value));
        return String(value).replace(/[^a-zA-Z0-9_-]/g, c => '\\' + c);
    }

    async function loadMessages(convId) {
        const container = document.getElementById('chatMessages');
        if (!container) return;
        const res = await apiRequest(window.BASE_URL + '/api/messages.php?action=get_messages&conv_id=' + encodeURIComponent(convId));
        if (!res.success) { container.innerHTML = '<div class="text-danger text-center mt-3">⚠️ ' + escapeHtml(res.message || 'Failed') + '</div>'; return; }

        const meId = document.body.dataset.userId || '';
        const msgs = res.data || [];
        container.innerHTML = msgs.map(m => {
            const isMine = String(m.sender_user_id) === String(meId);
            return '<div class="chat-bubble-wrap ' + (isMine ? 'mine' : 'theirs') + '">'
                 + '<div class="chat-bubble">'
                 + escapeHtml(m.message_text)
                 + '<div class="chat-bubble-time">' + escapeHtml(m.time_ago || '') + '</div>'
                 + '</div></div>';
        }).join('');
        container.scrollTop = container.scrollHeight;
    }

    async function sendMessage() {
        if (!currentConvId) return;
        const input = document.getElementById('messageInput');
        const text = (input?.value || '').trim();
        if (!text) return;
        input.value = '';
        const res = await apiRequest(window.BASE_URL + '/api/messages.php', 'POST', {
            action: 'send', conv_id: currentConvId, message: text
        });
        if (res.success) loadMessages(currentConvId);
    }

    let keydownDelegated = false;
    function wireKeydownDelegator() {
        if (keydownDelegated) return;
        keydownDelegated = true;
        document.addEventListener('keydown', e => {
            if (e.key !== 'Enter') return;
            const a = document.activeElement;
            if (a && a.id === 'messageInput') { e.preventDefault(); sendMessage(); }
        });
    }

    async function reviewApplication(appId, status) {
        if (!confirm('Are you sure you want to set this application to ' + status + '?')) return;
        const data = new URLSearchParams();
        data.append('action', 'review');
        data.append('application_id', appId);
        data.append('status', status);
        data.append('decision_note', 'Updated via dashboard');
        try {
            const res = await apiRequest(window.BASE_URL + '/api/applications.php', 'POST', data);
            if (res.success) {
                showToast('Application ' + status + ' successfully! 🐾');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showToast(res.message || 'Failed to update status', 'error');
            }
        } catch (err) {
            console.error('Review Error:', err);
            showToast('System error: Could not process review.', 'error');
        }
    }

    window.currentStep = window.currentStep || 1;
    const totalSteps = 7;
    const stepTitles = [
        'Applicant Information', 'Address & Contact', 'Financial Information',
        'Pet Ownership History', 'Current Pets', 'Veterinarian Information', 'Review & Submit'
    ];

    function changeStep(n) {
        const sections = document.querySelectorAll('.wizard-section');
        if (!sections.length) return;
        sections[window.currentStep - 1].style.display = 'none';
        window.currentStep += n;
        if (window.currentStep > totalSteps) { submitWizard(); return; }
        sections[window.currentStep - 1].style.display = 'block';
        const titleEl = document.getElementById('step-title');
        if (titleEl) titleEl.textContent = 'Section ' + window.currentStep + ': ' + stepTitles[window.currentStep - 1];
        updateProgress();
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        if (prevBtn) prevBtn.style.display = window.currentStep === 1 ? 'none' : 'block';
        if (nextBtn) nextBtn.textContent = window.currentStep === totalSteps ? 'Submit Application' : 'Next Section';
    }

    function updateProgress() {
        for (let i = 1; i <= totalSteps; i++) {
            const ind = document.getElementById('step-ind-' + i);
            if (!ind) continue;
            ind.classList.remove('active', 'completed');
            if (i < window.currentStep) ind.classList.add('completed');
            else if (i === window.currentStep) ind.classList.add('active');
        }
    }

    async function submitWizard() {
        const form = document.getElementById('adoptionWizardForm');
        if (!form) return;
        const fd = new FormData(form);
        fd.set('action', 'submit');
        const btn = document.getElementById('nextBtn');
        if (btn) { btn.disabled = true; btn.textContent = 'Submitting...'; }
        try {
            const res = await apiRequest(window.BASE_URL + '/api/applications.php', 'POST', fd);
            if (res.success) {
                showToast('Application Submitted! 🐾');
                const petId = fd.get('pet_id');
                if (petId) localStorage.removeItem('screening_progress_' + petId);
                setTimeout(() => window.location.reload(), 2000);
            } else {
                showToast(res.message, 'error');
                window.currentStep = Math.max(1, window.currentStep - 1);
            }
        } catch {
            showToast('System Error', 'error');
        } finally {
            if (btn) { btn.disabled = false; }
        }
    }

    function confirmAction(message, callback) {
        if (confirm(message)) callback();
    }
    function deletePhoto(buttonElement) {
        const photoId = buttonElement.getAttribute('data-photo-id');
        
        if (!photoId) {
            alert("Error: Photo ID not found.");
            return;
        }

        if (confirm("Are you sure you want to remove this picture?")) {
            const params = new URLSearchParams();
            params.append('action', 'delete_photo');
            params.append('photo_id', photoId);

            fetch(window.BASE_URL + '/api/pets.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: params,
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const wrapper = buttonElement.closest('div[style*="position:relative"]') || buttonElement.parentElement;
                    wrapper.remove();
                    
                    if (typeof showToast === 'function') {
                        showToast('Photo removed successfully! 🐾');
                    } else {
                        alert("Photo removed successfully!");
                    }
                } else {
                    alert("Failed to delete photo: " + (data.message || "Unknown error"));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("A network error occurred while trying to delete the photo.");
            });
        }
    }
    function bootstrapUI() {
        const userMenuToggle = document.getElementById('userMenuToggle');
        const userDropdown   = document.getElementById('userDropdown');
        if (userMenuToggle && userDropdown) {
            userMenuToggle.addEventListener('click', e => {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
                document.getElementById('notifDropdown')?.classList.remove('show');
            });
        }
        const notifToggle   = document.getElementById('notifToggle');
        const notifDropdown = document.getElementById('notifDropdown');
        if (notifToggle && notifDropdown) {
            notifToggle.addEventListener('click', e => {
                e.stopPropagation();
                const isOpen = notifDropdown.classList.toggle('show');
                document.getElementById('userDropdown')?.classList.remove('show');
                if (isOpen) loadNotifications();
            });
        }
        document.addEventListener('click', () => {
            document.getElementById('userDropdown')?.classList.remove('show');
            document.getElementById('notifDropdown')?.classList.remove('show');
        });
        const hamburger     = document.getElementById('hamburger');
        const mobileDrawer  = document.getElementById('mobileDrawer');
        const mobileOverlay = document.getElementById('mobileOverlay');
        const drawerClose   = document.getElementById('drawerClose');
        const openDrawer  = () => { mobileDrawer?.classList.add('open'); mobileOverlay?.classList.add('show'); };
        const closeDrawer = () => { mobileDrawer?.classList.remove('open'); mobileOverlay?.classList.remove('show'); };
        hamburger?.addEventListener('click', openDrawer);
        drawerClose?.addEventListener('click', closeDrawer);
        mobileOverlay?.addEventListener('click', closeDrawer);
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.dataset.tab;
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById(target)?.classList.add('active');
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrapUI);
    } else {
        bootstrapUI();
    }
    wireKeydownDelegator();

    window.BASE_URL      = window.BASE_URL;
    window.apiRequest    = apiRequest;
    window.submitAddPet  = submitAddPet;
    window.showToast     = showToast;
    window.escapeHtml    = escapeHtml;
    window.loadNotifications = loadNotifications;
    window.markRead      = markRead;
    window.toggleFavorite = toggleFavorite;
    window.openModal     = openModal;
    window.closeModal    = closeModal;
    window.viewApplication = viewApplication;
    window.selectConversation = selectConversation;
    window.loadMessages     = loadMessages;
    window.sendMessage      = sendMessage;
    window.reviewApplication = reviewApplication;
    window.changeStep       = changeStep;
    window.updateProgress   = updateProgress;
    window.submitWizard     = submitWizard;
    window.confirmAction    = confirmAction;
    window.deletePhoto      = deletePhoto;
})();