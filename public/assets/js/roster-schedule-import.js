(function ($) {
    'use strict';

    var page = $('#roster-import-page');
    var form = $('#roster-import-form');
    var confirmForm = $('#roster-import-confirm-form');
    var terminal = page.data('terminal') === 1 || page.data('terminal') === '1';
    var statusUrl = page.data('status-url');
    var started = Date.now();
    var pollingFailures = 0;
    var maxPollingFailures = 3;
    var confirming = false;
    var pollTimer = null;
    var polling = false;

    function notify(icon, message) {
        return window.AppDialog.alert(icon === 'error' ? 'Gagal' : 'Informasi', message, icon);
    }

    function errorMessage(xhr) {
        if (xhr.status === 401 || xhr.status === 419) { return 'Sesi login berakhir. Silakan login ulang.'; }
        if (xhr.status === 403) { return 'Anda tidak memiliki akses untuk tindakan ini.'; }
        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
            var key = Object.keys(xhr.responseJSON.errors)[0];
            if (key && xhr.responseJSON.errors[key][0]) { return xhr.responseJSON.errors[key][0]; }
        }
        if (xhr.status === 0) { return 'Koneksi bermasalah. Silakan cek jaringan Anda.'; }
        return (xhr.responseJSON && xhr.responseJSON.message) || 'Request gagal diproses.';
    }

    function stopPolling() { terminal = true; window.clearTimeout(pollTimer); }

    function schedulePoll(delay) {
        window.clearTimeout(pollTimer);
        pollTimer = window.setTimeout(poll, delay);
    }

    function showPollingNotice(message) {
        $('[data-polling-notice]').removeClass('d-none').find('[data-polling-message]').text(message);
    }

    if (form.length) {
        form.on('submit', function (event) {
            event.preventDefault();
            var button = form.find('button[type="submit"]');
            var original = button.data('label') || button.html();
            if (button.prop('disabled')) { return; }

            button.data('label', original).prop('disabled', true).html('Mengunggah...');
            $.ajax({
                url: form.attr('action'), method: 'POST', data: new FormData(form[0]),
                processData: false, contentType: false, headers: { Accept: 'application/json' }
            }).done(function (response) {
                if (response.success && response.data && response.data.redirect_url) {
                    window.location.assign(response.data.redirect_url);
                    return;
                }
                notify('error', response.message || 'File gagal diproses.');
            }).fail(function (xhr) {
                notify('error', errorMessage(xhr));
            }).always(function () {
                button.prop('disabled', false).html(original);
            });
        });
    }

    if (confirmForm.length) {
        confirmForm.on('submit', function (event) {
            event.preventDefault();
            var button = confirmForm.find('button[type="submit"]');
            var original = button.data('label') || button.html();
            if (button.prop('disabled') || confirming) { return; }
            confirming = true;

            function submitConfirmation() {
                button.data('label', original).prop('disabled', true).html('Memasukkan antrean...');
                $.ajax({
                    url: confirmForm.attr('action'), method: 'POST', data: confirmForm.serialize(), headers: { Accept: 'application/json' }
                }).done(function (response) {
                    if (response.success) {
                        confirmForm.addClass('d-none');
                        terminal = false;
                        started = Date.now();
                        pollingFailures = 0;
                        $('[data-import-status]').text(response.message || 'Import masuk antrean.');
                        schedulePoll(0);
                        return;
                    }
                    notify('error', response.message || 'Import gagal dimasukkan ke antrean.');
                    button.prop('disabled', false).html(original);
                }).fail(function (xhr) {
                    notify('error', errorMessage(xhr));
                    button.prop('disabled', false).html(original);
                });
            }

            window.AppDialog.confirm({
                icon: 'warning', title: 'Konfirmasi import?', text: 'Jadwal akan diproses di antrean.',
                confirmButtonText: 'Ya, proses', cancelButtonText: 'Batal'
            }).then(function (confirmed) {
                if (confirmed) { submitConfirmation(); }
            }).catch(function () {
                notify('error', 'Konfirmasi tidak dapat dibuka. Silakan muat ulang halaman.');
            }).finally(function () { confirming = false; });
        });
    }

    function poll() {
        if (!statusUrl || terminal) { return; }
        if (Date.now() - started > 720000) {
            stopPolling();
            showPollingNotice('Pembaruan otomatis dihentikan. Tekan Refresh Status untuk memeriksa hasil terbaru.');
            return;
        }
        if (polling) { schedulePoll(5000); return; }
        polling = true;
        $.getJSON(statusUrl).done(function (response) {
            var data = response.data || {};
            pollingFailures = 0;
            $('[data-import-status]').text(data.status_label || response.message || data.status || '-')
                .removeClass('bg-secondary bg-info bg-success bg-warning bg-danger')
                .addClass('bg-' + (data.status_class || 'secondary'));
            $.each(data.summary || {}, function (key, value) { $('[data-summary="' + key + '"]').text(value); });
            terminal = !!data.terminal;
            if (terminal) {
                stopPolling();
                showPollingNotice('Proses telah berhenti. Tekan Refresh Status untuk melihat hasil dan catatan terbaru.');
                notify(['failed', 'validation_failed', 'expired'].indexOf(data.status) !== -1 ? 'error' : 'success', data.status_label || response.message);
            } else if (data.status !== 'awaiting_confirmation') { schedulePoll(5000); }
        }).fail(function (xhr) {
            pollingFailures += 1;
            if (pollingFailures >= maxPollingFailures || xhr.status === 401 || xhr.status === 403 || xhr.status === 419) {
                notify('error', errorMessage(xhr));
                showPollingNotice(errorMessage(xhr));
                stopPolling();
                return;
            }
            schedulePoll(5000);
        }).always(function () { polling = false; });
    }

    poll();
}(jQuery));
