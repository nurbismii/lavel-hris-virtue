(function ($) {
    'use strict';
    $(function () {
        function showError(xhr) {
            const body = xhr.responseJSON || {};
            let message = body.message || 'Proses gagal. Silakan coba lagi.';
            if (xhr.status === 422 && body.errors) {
                const first = Object.keys(body.errors)[0];
                if (first) { message = body.errors[first][0]; }
            }
            if (xhr.status === 401 || xhr.status === 419) { message = 'Sesi login berakhir. Silakan login ulang.'; }
            if (xhr.status === 403) { message = 'Anda tidak memiliki akses untuk tindakan ini.'; }
            if (xhr.status === 0) { message = 'Koneksi bermasalah. Periksa jaringan lalu coba lagi.'; }
            if (xhr.status >= 500) { message = 'Layanan belum dapat memproses permintaan. Hubungi admin atau coba lagi.'; }
            window.AppDialog.alert('Proses belum berhasil', message, 'error');
            return message;
        }

        $('#salary-upload, #salary-confirm').on('submit', function (event) {
            event.preventDefault();
            const form = $(this);
            if (form.data('busy')) { return; }
            form.data('busy', true);
            const button = form.find('button[type="submit"]');
            const original = button.text();
            button.prop('disabled', true).text('Memproses...');
            const submit = function () {
                $.ajax({
                    url: form.attr('action'), method: 'POST', data: new FormData(form[0]),
                    processData: false, contentType: false, dataType: 'json',
                    headers: { Accept: 'application/json' },
                    success: function (response) {
                        if (!response.success) { showError({ responseJSON: response }); return; }
                        Promise.resolve(window.AppDialog.alert('Permintaan diterima', response.message, 'success')).then(function () {
                            if (response.data && response.data.redirect_url) { window.location.assign(response.data.redirect_url); }
                            else { window.location.reload(); }
                        });
                    },
                    error: showError,
                    complete: function () { form.data('busy', false); button.prop('disabled', false).text(original); }
                });
            };
            if (form.attr('id') === 'salary-confirm') {
                window.AppDialog.confirm({ title: 'Terapkan pembaruan gaji?', text: 'Seluruh baris yang valid akan menjadi riwayat upah yang disetujui.' }).then(function (confirmed) {
                    if (confirmed) { submit(); }
                    else { form.data('busy', false); button.prop('disabled', false).text(original); }
                });
            } else { submit(); }
        });

        const process = $('#salary-process');
        if (!process.length) { return; }
        let timer;
        let started = Date.now();
        let busy = false;
        const initial = process.attr('data-initial-status');
        function poll() {
            if (busy) { return; }
            clearTimeout(timer);
            busy = true;
            $('#salary-refresh').prop('disabled', true).text('Memeriksa...');
            $.ajax({
                url: process.attr('data-status-url'), dataType: 'json', headers: { Accept: 'application/json' },
                success: function (response) {
                    const state = response.data;
                    $('#salary-status').text(state.status_label);
                    $('#salary-progress').text((state.phase === 'commit' ? 'Penerapan perubahan' : 'Validasi file') + ' · ' + state.total + ' baris · ' + state.success + ' diterapkan · ' + state.failed + ' gagal.');
                    $('#salary-error').text(state.error_message || '');
                    if (!state.running && state.status !== initial) { window.location.reload(); return; }
                    if (state.running && Date.now() - started < 600000) { timer = setTimeout(poll, 5000); }
                    else if (state.running) { $('#salary-error').text('Pemeriksaan otomatis dihentikan setelah 10 menit. Gunakan Refresh status untuk melanjutkan.'); }
                },
                error: function (xhr) { $('#salary-error').text(showError(xhr)); },
                complete: function () { busy = false; $('#salary-refresh').prop('disabled', false).text('Refresh status'); }
            });
        }
        $('#salary-refresh').on('click', function () { started = Date.now(); poll(); });
        if (initial === 'queued' || initial === 'processing') { poll(); }
    });
})(jQuery);
