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
        elements.desktopPanel = document.getElementById('desktopNotifPermissionPanel');
        elements.desktopButton = document.getElementById('desktopNotifPermissionButton');
        elements.desktopText = document.getElementById('desktopNotifPermissionText');
        elements.desktopHint = document.getElementById('desktopNotifPermissionHint');
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function plainText(value) {
        return String(value || '')
            .replace(/<[^>]*>/g, '')
            .replace(/\s+/g, ' ')
            .trim();
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

    function localSecureHost() {
        return ['localhost', '127.0.0.1', '::1'].includes(window.location.hostname);
    }

    function supportsDesktopNotifications() {
        return 'Notification' in window && (window.isSecureContext || localSecureHost());
    }

    function setDesktopPermissionState(options) {
        if (!elements.desktopPanel || !elements.desktopButton || !elements.desktopText) {
            return;
        }

        elements.desktopPanel.classList.toggle('d-none', !options.visible);
        elements.desktopButton.disabled = Boolean(options.disabled);
        elements.desktopText.textContent = options.text || 'Aktifkan Notifikasi Desktop';

        elements.desktopButton.classList.toggle('btn-outline-primary', options.variant !== 'muted');
        elements.desktopButton.classList.toggle('btn-light', options.variant === 'muted');

        if (elements.desktopHint) {
            elements.desktopHint.textContent = options.hint || '';
            elements.desktopHint.classList.toggle('d-none', !options.hint);
        }
    }

    function updateDesktopPermissionUi() {
        if (!elements.desktopPanel) {
            return;
        }

        if (!('Notification' in window)) {
            setDesktopPermissionState({
                visible: true,
                disabled: true,
                variant: 'muted',
                text: 'Notifikasi Desktop Tidak Didukung',
                hint: 'Browser ini belum mendukung notifikasi desktop.',
            });
            return;
        }

        if (!supportsDesktopNotifications()) {
            setDesktopPermissionState({
                visible: true,
                disabled: true,
                variant: 'muted',
                text: 'Butuh HTTPS untuk Notifikasi Desktop',
                hint: 'Aktifkan HTTPS di hosting agar browser mengizinkan notifikasi desktop.',
            });
            return;
        }

        if (window.Notification.permission === 'granted') {
            setDesktopPermissionState({ visible: false });
            return;
        }

        if (window.Notification.permission === 'denied') {
            setDesktopPermissionState({
                visible: true,
                disabled: true,
                variant: 'muted',
                text: 'Notifikasi Desktop Diblokir',
                hint: 'Ubah izin notifikasi dari pengaturan situs di browser.',
            });
            return;
        }

        setDesktopPermissionState({
            visible: true,
            disabled: false,
            text: 'Aktifkan Notifikasi Desktop',
            hint: 'Browser akan meminta izin satu kali sebelum notifikasi tampil di desktop.',
        });
    }

    function requestDesktopPermission(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        if (!supportsDesktopNotifications() || window.Notification.permission !== 'default') {
            updateDesktopPermissionUi();
            return;
        }

        setDesktopPermissionState({
            visible: true,
            disabled: true,
            variant: 'muted',
            text: 'Meminta Izin...',
            hint: 'Lanjutkan pada dialog izin dari browser.',
        });

        const permissionRequest = window.Notification.requestPermission(function () {
            updateDesktopPermissionUi();
        });

        if (permissionRequest && typeof permissionRequest.then === 'function') {
            permissionRequest
                .then(updateDesktopPermissionUi)
                .catch(updateDesktopPermissionUi);
        }
    }

    function showDesktopNotification(notification) {
        if (!supportsDesktopNotifications() || window.Notification.permission !== 'granted') {
            return;
        }

        const title = plainText(notification.judul || notification.title || 'Notifikasi Baru');
        const message = plainText(notification.pesan || notification.message || 'Ada update baru di kotak masuk.');
        const notificationId = notification.id || title + message;
        const targetUrl = notification.url || notification.read_url || config.inboxUrl;

        try {
            const desktopNotification = new window.Notification(title, {
                body: message,
                icon: config.desktopIconUrl,
                badge: config.desktopBadgeUrl || config.desktopIconUrl,
                tag: 'vpeople-' + notificationId,
                renotify: true,
                data: {
                    url: targetUrl,
                },
            });

            desktopNotification.onclick = function (clickEvent) {
                clickEvent.preventDefault();
                window.focus();

                if (targetUrl) {
                    window.location.href = targetUrl;
                }

                desktopNotification.close();
            };

            window.setTimeout(function () {
                desktopNotification.close();
            }, 9000);
        } catch (error) {
            // Browser notifications should never block the in-app notification flow.
        }
    }

    function showToast(notification) {
        const title = notification.judul || notification.title || 'Notifikasi Baru';
        const message = notification.pesan || notification.message || 'Ada update baru di kotak masuk.';
        const notificationId = notification.id || title + message;

        if (state.lastToastId === notificationId) {
            return false;
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

        return true;
    }

    function subscribeRealtime() {
        if (!config.enabled || !window.Echo || !config.userId) {
            return;
        }

        window.Echo
            .private('App.Models.User.' + config.userId)
            .notification(function (notification) {
                const payload = notification || {};
                const displayed = showToast(payload);

                if (displayed) {
                    showDesktopNotification(payload);
                }

                refreshNotifications(true);
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        cacheElements();
        updateDesktopPermissionUi();
        subscribeRealtime();
        refreshNotifications(true);

        if (elements.desktopButton) {
            elements.desktopButton.addEventListener('click', requestDesktopPermission);
        }

        window.setInterval(function () {
            refreshNotifications(false);
        }, Number(config.fallbackInterval || 60000));
    });
})();
