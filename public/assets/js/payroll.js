(function ($) {
    'use strict';
    $(function () {
        function error(xhr) {
            const body = xhr.responseJSON || {};
            let message = body.message || 'Permintaan belum berhasil. Silakan coba lagi.';
            if (xhr.status === 422 && body.errors) { const key = Object.keys(body.errors)[0]; if (key) { message = body.errors[key][0]; } }
            if (xhr.status === 401 || xhr.status === 419) { message = 'Sesi login berakhir. Silakan login ulang.'; }
            if (xhr.status === 403) { message = 'Anda tidak memiliki akses payroll.'; }
            if (xhr.status === 0) { message = 'Koneksi bermasalah. Periksa jaringan dan coba lagi.'; }
            if (xhr.status >= 500) { message = 'Layanan belum tersedia. Muat ulang status sebelum mencoba lagi atau hubungi admin.'; }
            window.AppDialog.alert('Proses belum berhasil', message, 'error');
            return message;
        }
        $('[data-payroll-prepare]').on('click', function () {
            const employer = this.form.elements.employer_id;
            const period = this.form.elements.period;
            if (!employer.reportValidity() || !period.reportValidity()) { return; }
            const query = 'employer_id=' + encodeURIComponent(employer.value) + '&period=' + encodeURIComponent(period.value);
            window.location.assign(this.getAttribute('data-payroll-prepare') + '?' + query);
        });
        $('[name="verify_profile"]').on('change', function () {
            this.form.querySelector('[data-payroll-profile]').disabled = !this.checked;
        });
        $('[name="save_recurring"]').on('change', function () {
            this.form.querySelector('[data-payroll-recurring]').disabled = !this.checked;
        });
        $('[name="save_custom_recurring"]').on('change', function () {
            this.form.querySelector('[data-custom-recurring]').disabled = !this.checked;
        });
        $('[data-payroll-submit]').on('submit', function (event) {
            event.preventDefault();
            const form = $(this);
            if (form.data('busy')) { return; }
            form.data('busy', true);
            const button = form.find('[type="submit"]');
            const original = button.text();
            button.prop('disabled', true).text(button.attr('data-loading') || 'Memproses...');
            function reset() { form.data('busy', false); button.prop('disabled', false).text(original); }
            function submit() {
                $.ajax({ url: form.attr('action'), method: 'POST', data: new FormData(form[0]), processData: false, contentType: false,
                    dataType: 'json', headers: { Accept: 'application/json' },
                    success: function (response) {
                        if (!response.success) { error({ responseJSON: response }); return; }
                        Promise.resolve(window.AppDialog.alert('Berhasil', response.message, 'success')).then(function () {
                            if (response.data && response.data.redirect_url) { window.location.assign(response.data.redirect_url); }
                            else { window.location.reload(); }
                        });
                    }, error: error, complete: reset
                });
            }
            if (form.attr('data-confirm') === 'true') {
                window.AppDialog.confirm({ title: 'Finalisasi payroll?', text: 'Seluruh hasil akan dikunci sebagai arsip final. Periksa semua halaman terlebih dahulu.' }).then(function (confirmed) { if (confirmed) { submit(); } else { reset(); } });
            } else { submit(); }
        });
        const process = $('#payroll-process');
        if (!process.length) { return; }
        let started = Date.now(); let timer; let busy = false;
        const initial = process.attr('data-initial-status');
        function poll() {
            if (busy) { return; }
            clearTimeout(timer); busy = true;
            $('#payroll-refresh').prop('disabled', true).text('Memeriksa...');
            $.ajax({ url: process.attr('data-status-url'), dataType: 'json', headers: { Accept: 'application/json' },
                success: function (response) {
                    const state = response.data;
                    $('#payroll-status').text(state.status_label);
                    $('#payroll-progress').text(state.total + ' baris dibaca · ' + state.success + ' valid · ' + state.failed + ' gagal.');
                    $('#payroll-error').text(state.error_message || '');
                    if (!state.running && state.status !== initial) { window.location.reload(); return; }
                    if (state.running && Date.now() - started < 600000) { timer = setTimeout(poll, 5000); }
                    else if (state.running) { $('#payroll-error').text('Pemeriksaan otomatis berhenti setelah 10 menit. Gunakan Refresh status untuk melanjutkan.'); }
                }, error: function (xhr) { $('#payroll-error').text(error(xhr)); },
                complete: function () { busy = false; $('#payroll-refresh').prop('disabled', false).text('Refresh status'); }
            });
        }
        $('#payroll-refresh').on('click', function () { started = Date.now(); poll(); });
        if (initial === 'queued' || initial === 'processing') { poll(); }
    });
})(jQuery);
