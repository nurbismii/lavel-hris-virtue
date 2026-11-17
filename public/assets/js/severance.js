(function ($) {
    'use strict';
    $(function () {
        function error(xhr, form) {
            const body = xhr.responseJSON || {};
            let message = body.message || 'Permintaan belum berhasil. Silakan coba lagi.';
            if (xhr.status === 422 && body.errors) { message = Object.values(body.errors).flat().join('\n'); }
            if (xhr.status === 401 || xhr.status === 419) { message = 'Sesi login berakhir. Silakan login ulang.'; }
            if (xhr.status === 403) { message = 'Anda tidak memiliki akses untuk tindakan ini.'; }
            if (xhr.status === 0) { message = 'Koneksi bermasalah. Periksa jaringan, lalu coba lagi.'; }
            if (xhr.status >= 500) { message = 'Layanan belum tersedia. Periksa status pengajuan sebelum mencoba ulang atau hubungi admin.'; }
            if (form) { form.find('[data-form-error]').text(message); }
            window.AppDialog.alert('Proses belum berhasil', message, 'error');
            return message;
        }

        function preview(form, data) {
            const container = form.find('[data-hi-preview]');
            const lines = container.find('[data-preview-lines]').empty();
            const currency = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            data.calculation.lines.forEach(function (line) {
                const row = $('<tr>');
                $('<td>').text(line.label).appendTo(row);
                $('<td>').attr('data-label', 'Perhitungan').text(line.formula).appendTo(row);
                $('<td>').attr('data-label', 'Nominal (Rp)').addClass('text-end').text(currency.format(line.amount)).appendTo(row);
                lines.append(row);
            });
            container.find('[data-preview-total]').text(currency.format(data.calculation.total));
            container.find('[data-preview-total-label]').text(data.calculation.total_label);
            container.find('[data-preview-tenure]').text('Masa perhitungan: ' + data.calculation.years + ' tahun ' + data.calculation.months + ' bulan ' + data.calculation.days + ' hari.');
            form.find('[name="preview_hash"]').val(data.preview_hash);
            container.prop('hidden', false);
            form.find('[data-hi-approve]').prop('disabled', false);
        }

        $('[data-hi-form] [name="leave_days"]').on('input', function () {
            const form = $(this.form);
            form.find('[name="preview_hash"]').val('');
            form.find('[data-hi-preview]').prop('hidden', true);
            form.find('[data-hi-approve]').prop('disabled', true);
        });

        $('[data-severance-form]').on('submit', function (event) {
            event.preventDefault();
            const form = $(this);
            if (form.data('busy')) { return; }
            const submitter = event.originalEvent && event.originalEvent.submitter;
            const data = new FormData(this);
            if (submitter && submitter.name) { data.set(submitter.name, submitter.value); }
            const all = data.get('all') === '1';
            if (form.find('[name="ids[]"]').length && !all && !data.getAll('ids[]').length) {
                error({ status: 422, responseJSON: { message: 'Pilih setidaknya satu pengajuan final.' } }, form); return;
            }
            if (submitter && submitter.hasAttribute('data-print-url')) {
                const query = new URLSearchParams();
                data.getAll('ids[]').forEach(function (id) { query.append('ids[]', id); });
                window.location.assign(submitter.getAttribute('data-print-url') + '?' + query.toString());
                return;
            }
            form.data('busy', true);
            form.find('[data-form-error]').text('');
            const controls = Array.from(this.elements).filter(function (element) { return !element.disabled; });
            controls.forEach(function (element) { element.disabled = true; });
            const button = submitter ? $(submitter) : form.find('[type="submit"]').first();
            const original = button.text();
            button.text(button.attr('data-loading') || 'Memproses...');
            function reset() {
                form.data('busy', false);
                controls.forEach(function (element) { element.disabled = false; });
                button.text(original);
                if (form.is('[data-hi-form]') && !form.find('[name="preview_hash"]').val()) { form.find('[data-hi-approve]').prop('disabled', true); }
            }
            function send() {
                $.ajax({ url: form.attr('action'), method: 'POST', data: data, processData: false, contentType: false,
                    dataType: 'json', headers: { Accept: 'application/json' },
                    success: function (response) {
                        if (!response.success) { error({ responseJSON: response }, form); return; }
                        if (response.data && response.data.preview) { preview(form, response.data); return; }
                        Promise.resolve(window.AppDialog.alert('Berhasil', response.message, 'success')).then(function () {
                            if (response.data && response.data.redirect_url) { window.location.assign(response.data.redirect_url); }
                            else { window.location.reload(); }
                        });
                    }, error: function (xhr) { error(xhr, form); }, complete: reset
                });
            }
            const confirmation = data.get('intent') === 'approve'
                ? 'Setujui hasil ini sebagai arsip final? Staf HI akan dapat melihat, mencetak, dan mengexport hasilnya.' : form.attr('data-confirm');
            if (confirmation) {
                Promise.resolve(window.AppDialog.confirm({ title: 'Konfirmasi pengajuan', text: confirmation }))
                    .then(function (confirmed) { if (confirmed) { send(); } else { reset(); } })
                    .catch(function () { reset(); error({ responseJSON: { message: 'Konfirmasi belum berhasil. Silakan coba lagi.' } }, form); });
            } else { send(); }
        });

        $('[data-severance-process]').each(function () {
            const element = $(this);
            const initial = element.attr('data-status');
            let started = Date.now(); let timer; let busy = false;
            function poll() {
                if (busy) { return; }
                clearTimeout(timer); busy = true;
                const button = element.find('[data-process-refresh]').prop('disabled', true).text('Memeriksa...');
                $.ajax({ url: element.attr('data-status-url'), dataType: 'json', headers: { Accept: 'application/json' },
                    success: function (response) {
                        const state = response.data;
                        element.find('[data-process-label]').text(state.status_label);
                        element.find('[data-process-progress]').text(state.total + ' data; ' + state.success + ' berhasil, ' + state.failed + ' gagal.');
                        element.find('[data-process-error]').text(state.error_message || '');
                        if (!state.running && state.status !== initial) { window.location.reload(); return; }
                        if (state.running && Date.now() - started < 600000) { timer = setTimeout(poll, 5000); }
                        else if (state.running) { element.find('[data-process-error]').text('Pemeriksaan otomatis berhenti setelah 10 menit. Gunakan Refresh status untuk melanjutkan.'); }
                    }, error: function (xhr) { element.find('[data-process-error]').text(error(xhr)); },
                    complete: function () { busy = false; button.prop('disabled', false).text('Refresh status'); }
                });
            }
            element.find('[data-process-refresh]').on('click', function () { started = Date.now(); poll(); });
            if (initial === 'queued' || initial === 'processing') { poll(); }
        });
    });
})(jQuery);
