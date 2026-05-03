(function () {
    const config = window.AppRealtimeNotifications;

    if (!config || !config.latestUrl) {
        return;
    }

    const state = {
        refreshing: false,
        lastToastId: null,
    };

    const elements = {};

    function cacheElements() {
        elements.badge = document.getElementById('notifBadge');
        elements.headerBadge = document.getElementById('notifHeaderBadge');
        elements.list = document.getElementById('notifList');
        elements.readAll = document.getElementById('notifReadAllContainer');
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function updateBadge(count) {
        const unreadCount = Number(count || 0);

        if (elements.badge) {
            elements.badge.textContent = unreadCount;
            elements.badge.classList.toggle('d-none', unreadCount <= 0);
        }

        if (elements.headerBadge) {
            elements.headerBadge.textContent = unreadCount + ' Baru';
            elements.headerBadge.classList.toggle('d-none', unreadCount <= 0);
        }

        if (elements.readAll) {
            elements.readAll.classList.toggle('d-none', unreadCount <= 0);
        }
    }

    function notificationItemTemplate(item) {
        const unreadClass = item.unread ? 'bg-light' : '';
        const unreadDot = item.unread
            ? '<span class="badge bg-primary ms-2 realtime-notif-dot"></span>'
            : '';

        return `
            <a href="${escapeHtml(item.read_url)}"
                class="dropdown-item d-flex align-items-start py-3 border-bottom realtime-notif-item ${unreadClass}">
                <div class="flex-grow-1">
                    <div class="fw-semibold small">${escapeHtml(item.title)}</div>
                    <div class="text-muted small">${escapeHtml(item.message)}</div>
                    <div class="text-secondary small mt-1">${escapeHtml(item.created_at_human)}</div>
                </div>
                ${unreadDot}
            </a>
        `;
    }

    function renderNotifications(payload) {
        updateBadge(payload.unread_count);

        if (!elements.list) {
            return;
        }

        if (!payload.items || payload.items.length === 0) {
            elements.list.innerHTML = `
                <div class="text-center text-muted py-4 realtime-notif-empty">
                    Belum ada notifikasi
                </div>
            `;
            return;
        }

        elements.list.innerHTML = payload.items.map(notificationItemTemplate).join('');
    }

    function refreshNotifications(force) {
        if (state.refreshing) {
            return;
        }

        if (!force && document.hidden) {
            return;
        }

        state.refreshing = true;

        window.axios.get(config.latestUrl)
            .then(function (response) {
                renderNotifications(response.data || {});
            })
            .catch(function () {
                // Fallback polling should stay quiet; users still have the inbox page.
            })
            .then(function () {
                state.refreshing = false;
            });
    }

    function showToast(notification) {
        const title = notification.judul || notification.title || 'Notifikasi Baru';
        const message = notification.pesan || notification.message || 'Ada update baru di kotak masuk.';
        const notificationId = notification.id || title + message;

        if (state.lastToastId === notificationId) {
            return;
        }

        state.lastToastId = notificationId;

        if (window.$ && window.$.notify) {
            window.$.notify({
                icon: 'fa fa-bell',
                title: '<strong>' + escapeHtml(title) + '</strong><br>',
                message: escapeHtml(message),
            }, {
                type: 'info',
                placement: {
                    from: 'top',
                    align: 'right',
                },
                delay: 4500,
                timer: 800,
                z_index: 1080,
            });
        }
    }

    function subscribeRealtime() {
        if (!config.enabled || !window.Echo || !config.userId) {
            return;
        }

        window.Echo
            .private('App.Models.User.' + config.userId)
            .notification(function (notification) {
                showToast(notification || {});
                refreshNotifications(true);
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        cacheElements();
        subscribeRealtime();
        refreshNotifications(true);

        window.setInterval(function () {
            refreshNotifications(false);
        }, Number(config.fallbackInterval || 60000));
    });
})();
