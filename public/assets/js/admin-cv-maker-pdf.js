(function ($) {
    'use strict';
    if (!document.getElementById('cvPdfPanel')) return;

    let historyUrl = $('#cvPdfPanel').data('index-url');
    let timer = null;
    let polls = 0;
    let loading = false;
    let creating = false;
    let lastSignature = '';
    const requests = new Map();
    const downloading = new Set();
    const labels = { pending: 'Menunggu antrean', processing: 'Sedang diproses', completed: 'Siap diunduh',
        partial_failed: 'Siap, sebagian gagal', failed: 'Gagal', cancelled: 'Dibatalkan', expired: 'Kedaluwarsa' };

    function feedback(message, error) {
        $('#cvPdfFeedback').removeClass('d-none alert-info alert-danger').addClass(error ? 'alert-danger' : 'alert-info').text(message);
    }
    function refreshEmployees() {
        if (typeof cvCompareTable !== 'undefined') cvCompareTable.ajax.reload(null, false);
    }
    async function request(url, body, binary) {
        const controller = new AbortController();
        const timeout = setTimeout(function () { controller.abort(); }, binary ? 300000 : 30000);
        try {
            const response = await fetch(url, {
                method: body ? 'POST' : 'GET', credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                headers: { Accept: binary ? 'application/octet-stream, application/json' : 'application/json',
                    'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                body: body ? JSON.stringify(body) : undefined
            });
            if (response.redirected) throw new Error('Sesi login berakhir. Silakan login ulang.');
            if (!response.ok) {
                const data = await response.json().catch(function () { return {}; });
                let message = response.status < 500 && data.message ? data.message : 'Proses gagal di server. Coba kembali melalui riwayat PDF.';
                if (response.status === 422 && data.errors) message = Object.values(data.errors)[0][0];
                if ([401, 419].includes(response.status)) message = 'Sesi login berakhir. Silakan login ulang.';
                if (response.status === 403) message = data.message || 'Anda tidak memiliki akses ke CV atau batch ini.';
                throw new Error(message);
            }
            return binary ? await response.blob() : await response.json();
        } catch (error) {
            if (error.name === 'AbortError') throw new Error('Koneksi melewati batas waktu. Cek riwayat PDF; proses antrean tetap berjalan.');
            if (error instanceof TypeError) throw new Error('Koneksi bermasalah. Cek jaringan lalu refresh riwayat PDF.');
            throw error;
        } finally {
            clearTimeout(timeout);
        }
    }
    function uuid() {
        if (window.crypto && window.crypto.randomUUID) return window.crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 3 | 8)).toString(16);
        });
    }
    async function confirmAction(text) {
        if (window.AppDialog && typeof window.AppDialog.confirm === 'function') {
            return window.AppDialog.confirm({ title: 'Konfirmasi PDF', text: text, icon: 'warning',
                confirmButtonText: 'Lanjutkan', cancelButtonText: 'Batal' });
        }
        feedback('Dialog konfirmasi belum tersedia. Muat ulang halaman.', true);
        return false;
    }
    async function create(button, nik) {
        if (creating || button.prop('disabled')) return;
        creating = true;
        const original = button.html();
        button.prop('disabled', true).text('Memasukkan ke antrean...');
        try {
            const allow = $('#cvPdfAllowDownloaded').prop('checked') || button.attr('data-downloaded') === '1';
            if (allow && !await confirmAction('Sertakan CV yang pernah diunduh? Ini akan membuat salinan PDF baru.')) return;
            const input = nik ? { mode: 'single', employee_nik: nik } : Object.assign({}, cvReminderFilterPayload(), {
                mode: 'filtered', cv_reminder: $('#cv_filter_reminder').val()
            });
            input.allow_downloaded = allow ? 1 : 0;
            const signature = JSON.stringify(input);
            if (!requests.has(signature)) requests.set(signature, uuid());
            input.idempotency_key = requests.get(signature);
            const result = await request($('#cvPdfPanel').data('store-url'), input);
            requests.delete(signature);
            feedback(result.message, false);
            polls = 0;
            historyUrl = $('#cvPdfPanel').data('index-url');
            await loadHistory();
            refreshEmployees();
        } catch (error) {
            feedback(error.message, true);
        } finally {
            creating = false;
            button.prop('disabled', false).html(original);
        }
    }
    function render(batch) {
        const card = $('<div>').addClass('border rounded p-3 mb-2');
        $('<strong>').text((batch.mode === 'single' ? 'Single PDF' : 'Batch PDF') + ' · ' + batch.created_at).appendTo(card);
        $('<span>').addClass('badge bg-light text-dark border ms-2').text(labels[batch.status] || batch.status).appendTo(card);
        $('<div>').addClass('small text-muted').text('Referensi ' + batch.uuid.slice(0, 8)).appendTo(card);
        $('<div>').addClass('small mt-2').text(batch.processed + '/' + batch.total + ' diproses · ' + batch.success + ' berhasil · ' + batch.failed + ' gagal').appendTo(card);
        const bar = $('<div>').addClass('progress my-2').css('height', '7px').appendTo(card);
        $('<div>').addClass('progress-bar').css('width', Math.round(batch.processed / Math.max(1, batch.total) * 100) + '%').appendTo(bar);
        if (batch.started_at) $('<div>').addClass('small text-muted').text('Mulai ' + batch.started_at + (batch.finished_at ? ' · Selesai ' + batch.finished_at : '')).appendTo(card);
        $('<div>').addClass('small text-muted').text('Tersedia sampai ' + batch.expires_at + (batch.downloaded_at ? ' · Diunduh ' + batch.downloaded_at : '')).appendTo(card);
        if (batch.error_message) $('<div>').addClass('small text-danger mt-1').text(batch.error_message).appendTo(card);
        const actions = $('<div>').addClass('d-flex flex-wrap gap-2 mt-2').appendTo(card);
        if (batch.download_url) {
            $('<button type="button">').addClass('btn btn-sm btn-success').prop('disabled', downloading.has(batch.uuid))
                .text(downloading.has(batch.uuid) ? 'Mengunduh...' : (batch.downloaded_at ? 'Unduh ulang hasil' : 'Download ' + (batch.mode === 'single' ? 'PDF' : 'ZIP')))
                .on('click', function () { download($(this), batch); }).appendTo(actions);
        }
        if (batch.failed) {
            $('<button type="button">').addClass('btn btn-sm btn-outline-secondary').text('Detail gagal')
                .on('click', async function () {
                    const button = $(this).prop('disabled', true).text('Memuat...');
                    try {
                        const result = await request(batch.status_url);
                        card.find('.cv-pdf-failures').remove();
                        const details = $('<div>').addClass('cv-pdf-failures small mt-2').appendTo(card);
                        (result.data.failed_items || []).forEach(function (item) {
                            $('<div>').text(item.employee_nik + ': ' + item.error_message).appendTo(details);
                        });
                        $('<div>').text('Unduh hasil yang berhasil, lalu gunakan filter Belum Diunduh untuk mengajukan CV yang gagal.').appendTo(details);
                    } catch (error) { feedback(error.message, true); }
                    finally { button.prop('disabled', false).text('Detail gagal'); }
                }).appendTo(actions);
        }
        if (['pending', 'processing', 'completed', 'partial_failed', 'failed'].includes(batch.status)) {
            $('<button type="button">').addClass('btn btn-sm btn-outline-danger').text('Batalkan / Lepaskan batch')
                .on('click', async function () {
                    const button = $(this);
                    if (!await confirmAction('Batalkan batch ini? CV yang belum diunduh bisa diajukan kembali.')) return;
                    button.prop('disabled', true).text('Membatalkan...');
                    try {
                        const result = await request(batch.cancel_url, {});
                        feedback(result.message, false);
                        await loadHistory(); refreshEmployees();
                    } catch (error) { feedback(error.message, true); }
                    finally { button.prop('disabled', false).text('Batalkan / Lepaskan batch'); }
                }).appendTo(actions);
        }
        return card;
    }
    async function download(button, batch) {
        if (button.prop('disabled') || downloading.has(batch.uuid)) return;
        downloading.add(batch.uuid);
        const original = button.text();
        button.prop('disabled', true).text('Mengunduh...');
        try {
            const blob = await request(batch.download_url, {}, true);
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url; link.download = 'CV-' + batch.uuid + (batch.mode === 'single' ? '.pdf' : '.zip');
            document.body.appendChild(link); link.click(); link.remove();
            setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
            feedback('File telah diterima browser. Jika penyimpanan gagal, unduh ulang dari riwayat yang sama.', false);
            refreshEmployees();
        } catch (error) { feedback(error.message, true); }
        finally { downloading.delete(batch.uuid); button.prop('disabled', false).text(original); await loadHistory(); }
    }
    async function loadHistory() {
        if (loading) return;
        loading = true;
        clearTimeout(timer);
        $('#cvPdfRefresh').prop('disabled', true).text('Memuat...');
        try {
            const result = await request(historyUrl);
            const container = $('#cvPdfHistory').empty();
            if (!result.data.length) $('<p>').addClass('small text-muted mb-0').text('Belum ada riwayat PDF.').appendTo(container);
            result.data.forEach(function (batch) { container.append(render(batch)); });
            const signature = JSON.stringify(result.data.map(function (b) { return [b.uuid, b.status, b.downloaded_at]; }));
            if (lastSignature && signature !== lastSignature) refreshEmployees();
            lastSignature = signature;
            $('#cvPdfPrevious').toggleClass('d-none', !result.prev_page_url).data('url', result.prev_page_url);
            $('#cvPdfNext').toggleClass('d-none', !result.next_page_url).data('url', result.next_page_url);
            if (result.data.some(function (b) { return ['pending', 'processing'].includes(b.status); })) {
                if (++polls < 60) timer = setTimeout(loadHistory, 5000);
                else feedback('Pemantauan otomatis dijeda setelah 5 menit. Antrean tetap berjalan; gunakan Refresh riwayat untuk melanjutkan.', false);
            }
        } catch (error) { feedback(error.message, true); }
        finally { loading = false; $('#cvPdfRefresh').prop('disabled', false).text('Refresh riwayat'); }
    }
    $(document).on('click', '#cvPdfBatchCreate', function () { create($(this)); });
    $(document).on('click', '.js-cv-pdf-single', function () { create($(this), String($(this).attr('data-nik'))); });
    $(document).on('click', '#cvPdfRefresh', function () { polls = 0; loadHistory(); });
    $(document).on('click', '#cvPdfPrevious, #cvPdfNext', function () { historyUrl = $(this).data('url'); polls = 0; loadHistory(); });
    loadHistory();
})(jQuery);
