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

    function notify(icon, message) {
        if (window.Swal) {
            Swal.fire({ icon: icon, title: icon === 'error' ? 'Gagal' : 'Informasi', text: message });
        }
    }

    function errorMessage(xhr) {
        if (xhr.status === 401 || xhr.status === 419) { return 'Sesi login berakhir. Silakan login ulang.'; }
        if (xhr.status === 403) { return 'Anda tidak memiliki akses untuk tindakan ini.'; }
        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
            var key = Object.keys(xhr.responseJSON.errors)[0];
            return xhr.responseJSON.errors[key][0];
        }
        if (xhr.status === 0) { return 'Koneksi bermasalah. Silakan cek jaringan Anda.'; }
        return (xhr.responseJSON && xhr.responseJSON.message) || 'Request gagal diproses.';
    }

    function stopPolling() { terminal = true; }

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
            if (button.prop('disabled')) { return; }

            function submitConfirmation() {
                button.data('label', original).prop('disabled', true).html('Memasukkan antrean...');
                $.ajax({
                    url: confirmForm.attr('action'), method: 'POST', data: confirmForm.serialize(), headers: { Accept: 'application/json' }
                }).done(function (response) {
                    if (response.success) {
                        terminal = false;
                        started = Date.now();
                        window.setTimeout(poll, 0);
                        return;
                    }
                    notify('error', response.message || 'Import gagal dimasukkan ke antrean.');
                    button.prop('disabled', false).html(original);
                }).fail(function (xhr) {
                    notify('error', errorMessage(xhr));
                    button.prop('disabled', false).html(original);
                });
            }

            if (window.Swal) {
                Swal.fire({
                    icon: 'warning', title: 'Konfirmasi import?', text: 'Jadwal akan diproses di antrean.',
                    showCancelButton: true, confirmButtonText: 'Ya, proses', cancelButtonText: 'Batal'
                }).then(function (result) { if (result.isConfirmed) { submitConfirmation(); } });
                return;
            }

            submitConfirmation();
        });
    }

    function poll() {
        if (!statusUrl || terminal || Date.now() - started > 720000) { stopPolling(); return; }
        $.getJSON(statusUrl).done(function (response) {
            var data = response.data || {};
            pollingFailures = 0;
            $('[data-import-status]').text(data.status || '-');
            $.each(data.summary || {}, function (key, value) { $('[data-summary="' + key + '"]').text(value); });
            terminal = !!data.terminal;
            if (!terminal) { window.setTimeout(poll, 5000); }
        }).fail(function (xhr) {
            pollingFailures += 1;
            if (pollingFailures >= maxPollingFailures || xhr.status === 401 || xhr.status === 403 || xhr.status === 419) {
                notify('error', errorMessage(xhr));
                stopPolling();
                return;
            }
            window.setTimeout(poll, 5000);
        });
    }

    poll();
}(jQuery));
