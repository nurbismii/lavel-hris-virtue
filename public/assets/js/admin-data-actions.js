(function ($) {
    'use strict';
    $(function () {
        $('[data-admin-submit]').on('submit', function (event) {
            event.preventDefault();
            const form = $(this);
            if (form.data('busy')) { return; }
            form.data('busy', true);
            form.find('[data-form-error]').text('');
            const button = form.find('[type="submit"]');
            const original = button.text();
            button.prop('disabled', true).text('Memproses...');
            const reset = function () { form.data('busy', false); button.prop('disabled', false).text(original); };
            const submit = function () {
                $.ajax({url: form.attr('action'), method: 'POST', data: new FormData(form[0]), processData: false, contentType: false,
                    dataType: 'json', headers: {Accept: 'application/json'},
                    success: function (response) {
                        if (!response.success) { window.AppDialog.alert('Gagal', response.message || 'Permintaan belum berhasil.', 'error'); return; }
                        Promise.resolve(window.AppDialog.alert('Berhasil', response.message, 'success')).then(function () { window.location.reload(); });
                    },
                    error: function (xhr) {
                        const body = xhr.responseJSON || {};
                        let message = body.message || 'Permintaan belum berhasil. Silakan coba lagi.';
                        if (xhr.status === 422 && body.errors) { const key = Object.keys(body.errors)[0]; if (key) { message = body.errors[key][0]; } }
                        if (xhr.status === 401 || xhr.status === 419) { message = 'Sesi login berakhir. Silakan login ulang.'; }
                        if (xhr.status === 403) { message = 'Anda tidak memiliki akses untuk tindakan ini.'; }
                        if (xhr.status === 404) { message = 'Data sudah berubah atau tidak tersedia. Muat ulang daftar sebelum mencoba lagi.'; }
                        if (xhr.status === 0) { message = 'Koneksi bermasalah. Periksa jaringan dan coba lagi.'; }
                        if (xhr.status >= 500) { message = 'Layanan belum tersedia. Coba lagi atau hubungi admin.'; }
                        form.find('[data-form-error]').text(message);
                        window.AppDialog.alert('Gagal', message, 'error');
                    }, complete: reset
                });
            };
            if (form.attr('data-confirm-text')) {
                window.AppDialog.confirm({title: 'Konfirmasi tindakan', text: form.attr('data-confirm-text')}).then(function (confirmed) { if (confirmed) { submit(); } else { reset(); } });
            } else { submit(); }
        });
    });
})(jQuery);
