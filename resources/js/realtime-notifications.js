(function () {
    const config = window.AppRealtimeNotifications;

    if (!config || !config.latestUrl) {
        return;
    }

    const state = {
        refreshing: false,
        refreshPromise: null,
        clearing: false,
        hasItems: false,
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
        elements.nativePanel = document.querySelector('[data-native-notifications]');
        elements.refreshButton = document.querySelector('[data-notification-refresh]');
        elements.clearButton = document.querySelector('[data-notification-clear]');
        elements.status = document.querySelector('[data-notification-status]');
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
        state.hasItems = Boolean(payload.items && payload.items.length);
        if (elements.clearButton) elements.clearButton.disabled = state.clearing || !state.hasItems;
        if (elements.status) elements.status.hidden = true;

        if (!elements.list) {
            return;
        }

        if (!payload.items || payload.items.length === 0) {
            elements.list.innerHTML = `
                <div class="text-center text-muted py-4 realtime-notif-empty">
                    ${escapeHtml((config.labels || {}).empty || 'Belum ada notifikasi')}
                </div>
            `;
            return;
        }

        elements.list.innerHTML = payload.items.map(notificationItemTemplate).join('');
    }

    function refreshNotifications(force) {
        if (state.clearing) return Promise.resolve();
        if (state.refreshing) {
            return state.refreshPromise;
        }

        if (!force && document.hidden) {
            return Promise.resolve();
        }

        state.refreshing = true;
        if (elements.refreshButton) elements.refreshButton.disabled = true;
        if (elements.list) elements.list.setAttribute('aria-busy', 'true');

        state.refreshPromise = window.axios.get(config.latestUrl, {timeout: 15000})
            .then(function (response) {
                renderNotifications(response.data || {});
            })
            .catch(function (error) {
                if (elements.status) {
                    if (!state.hasItems && elements.list) elements.list.innerHTML = '';
                    elements.status.textContent = notificationError(error, (config.labels || {}).load_failed || 'Notifikasi belum dapat dimuat. Pilih Refresh untuk mencoba lagi.');
                    elements.status.hidden = false;
                }
            })
            .then(function () {
                state.refreshing = false;
                if (elements.refreshButton) elements.refreshButton.disabled = state.clearing;
                if (elements.list) elements.list.setAttribute('aria-busy', 'false');
            });
        return state.refreshPromise;
    }

    function notificationError(error, fallback) {
        const status = error.response && error.response.status;
        const data = error.response && error.response.data;
        if (!error.response) return 'Koneksi bermasalah. Periksa jaringan Anda.';
        if (status === 401 || status === 419) return 'Sesi login berakhir. Silakan login ulang.';
        if (status === 403) return 'Anda tidak memiliki akses untuk tindakan ini.';
        if (status === 422 && data && data.errors) {
            const first = Object.keys(data.errors)[0];
            return data.errors[first] && data.errors[first][0] || fallback;
        }
        return status < 500 && data && data.message || fallback;
    }

    async function clearNotifications() {
        if (state.clearing || !state.hasItems) return;
        state.clearing = true;
        const button = elements.clearButton;
        const label = button.textContent;
        button.disabled = true;
        if (elements.refreshButton) elements.refreshButton.disabled = true;
        try {
            const confirmed = await window.AppDialog.confirm({
                title: button.dataset.confirmTitle,
                text: button.dataset.confirmText,
                icon: 'warning',
                dangerMode: true,
            });
            if (!confirmed) return;
            button.textContent = button.dataset.loadingText;
            // Wait for the earlier GET so its response cannot restore deleted notifications.
            if (state.refreshPromise) await state.refreshPromise;
            const response = await window.axios.delete(button.dataset.url, {timeout: 15000});
            if (!response.data || response.data.success !== true) {
                await window.AppDialog.alert('Gagal', 'Notifikasi belum berhasil dibersihkan. Coba lagi.', 'error');
                return;
            }
            renderNotifications({unread_count: 0, items: []});
            await window.AppDialog.alert('Berhasil', response.data.message, 'success');
        } catch (error) {
            const message = notificationError(error, 'Notifikasi gagal dibersihkan. Silakan coba lagi.');
            await window.AppDialog.alert('Gagal', message, 'error');
        } finally {
            state.clearing = false;
            button.textContent = label;
            button.disabled = !state.hasItems;
            if (elements.refreshButton) elements.refreshButton.disabled = false;
            refreshNotifications(true);
        }
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
        if (elements.refreshButton) elements.refreshButton.addEventListener('click', function () { refreshNotifications(true); });
        if (elements.nativePanel) elements.nativePanel.addEventListener('shown.bs.dropdown', function () { refreshNotifications(true); });
        if (elements.clearButton) elements.clearButton.addEventListener('click', clearNotifications);

        if (elements.desktopButton) {
            elements.desktopButton.addEventListener('click', requestDesktopPermission);
        }

        window.setInterval(function () {
            refreshNotifications(false);
        }, Number(config.fallbackInterval || 60000));
    });
})();
