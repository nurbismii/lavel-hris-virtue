    function setCvDashboardState(message, busy) {
        $('#cvDashboard').attr('aria-busy', busy ? 'true' : 'false');
        $('#cvDashboardStatus').text(message);
        $('#cvDashboardRefresh').prop('disabled', busy);
        $('#cvDashboardContent').prop('hidden', true);
    }

    function renderCvDashboard(payload) {
        if (!payload) {
            setCvDashboardState(@json(__('Ringkasan belum tersedia. Klik Muat ulang untuk mencoba kembali.')), false);
            return;
        }
        const summary = payload.summary;
        const total = Number(summary.total) || 0;
        const format = value => (Number(value) || 0).toLocaleString(@json(app()->getLocale() === 'zh_CN' ? 'zh-CN' : 'id-ID'));
        const percent = (value, denominator) => denominator ? Math.round(Number(value) / denominator * 100) : 0;
        const cards = [
            [@json(__('Karyawan sesuai filter')), total, @json(__('Karyawan VDNI dan VDNIP')), null, null],
            [@json(__('CV lengkap')), summary.complete, percent(summary.complete, total) + @json(__('% dari karyawan sesuai filter')), 'progress_status', 'complete'],
            [@json(__('CV dalam pengisian')), summary.in_progress, @js(__('Profil tersedia, belum lengkap')), 'progress_status', 'in_progress'],
            [@json(__('Perlu reminder')), summary.reminder, @json(__('Berdasarkan snapshot terakhir')), 'reminder', 'needs_reminder'],
            [@json(__('Belum tersinkronisasi')), summary.not_synced, @json(__('Status pengisian belum diketahui')), 'progress_status', 'not_synced'],
            [@json(__('Akun tidak ditemukan')), summary.no_account, @json(__('Menurut sinkronisasi terakhir')), 'progress_status', 'no_account'],
            [@json(__('Belum membuat profil')), summary.no_profile, @js(__('Akun tersedia, profil belum ada')), 'progress_status', 'no_profile']
        ];
        const completedReview = payload.reviews.find(row => row.key === 'completed');
        cards.push([@json(__('Selesai diperiksa')), completedReview ? completedReview.total : 0, @json(__('Status pemeriksaan')), 'review_status', 'completed']);
        const cardContainer = $('#cvDashboardCards').empty();
        cards.forEach((card, index) => {
            const element = $(card[3] ? '<button type="button">' : '<div>')
                .addClass('cv-dashboard-card').toggleClass('cv-dashboard-card--accent', index === 1);
            element.append($('<span>').text(card[0]), $('<strong>').text(format(card[1])), $('<small>').text(card[2]));
            if (card[3]) element.attr({'data-cv-dashboard-filter': card[3], 'data-value': card[4]});
            cardContainer.append(element);
        });
        $('#cvDashboardSync').text(summary.latest_sync
            ? @json(__('Snapshot lokal • Sinkronisasi tertua: ')) + (summary.oldest_sync || '—') + @json(__(' • Terbaru: ')) + summary.latest_sync + @json(__('. Waktu mengikuti server; muat ulang tidak menjalankan sinkronisasi.'))
            : @json(__('Belum ada waktu sinkronisasi untuk hasil ini. Status CV belum dapat dipastikan.')));

        function bar(container, label, value, denominator, filter, filterValue, suffix) {
            const button = $('<button type="button" class="cv-dashboard-bar">')
                .attr({'data-cv-dashboard-filter': filter, 'data-value': filterValue});
            button.append($('<div class="cv-dashboard-bar-label">').append(
                $('<span>').text(label), $('<strong>').text(suffix || format(value))));
            button.append($('<div class="cv-dashboard-track">').append(
                $('<div class="cv-dashboard-fill">').css('width', Math.min(100, percent(value, denominator)) + '%')));
            container.append(button);
        }
        const departments = $('#cvDashboardDepartments').empty();
        payload.departments.forEach(row => {
            const label = row.departemen || @json(__('Tanpa departemen'));
            if (row.departemen_id) {
                bar(departments, label, row.complete, Number(row.total), 'departemen', row.departemen_id,
                    format(row.complete) + '/' + format(row.total) + ' · ' + percent(row.complete, Number(row.total)) + '%');
            } else {
                departments.append($('<p class="cv-dashboard-empty">').text(label + ': ' + format(row.complete) + '/' + format(row.total) + @json(__(' CV lengkap'))));
            }
        });
        const reviews = $('#cvDashboardReviews').empty();
        payload.reviews.forEach(row => bar(reviews, row.label, row.total, total, 'review_status', row.key));
        const steps = $('#cvDashboardSteps').empty();
        payload.steps.forEach(row => bar(steps, row.current_step_label || (@json(__('Tahap ')) + row.current_step), row.total,
            Number(summary.in_progress), 'progress_step', row.current_step));
        const priorities = $('#cvDashboardPriorities').empty();
        payload.priorities.forEach(row => {
            priorities.append($('<div class="cv-dashboard-priority">').append(
                $('<div>').append($('<strong>').text(row.name), $('<small>').text(row.reason),
                    $('<small>').text(@json(__('Aktivitas: ')) + (row.last_activity || @json(__('Belum diketahui'))))),
                row.url ? $('<a class="btn btn-sm btn-light border">').attr('href', row.url).text(@json(__('Detail'))) : null));
        });
        [departments, steps, priorities].forEach(container => {
            if (!container.children().length) container.append($('<div class="cv-dashboard-empty">').text(@json(__('Tidak ada data untuk filter ini.'))));
        });
        $('#cvDashboard').attr('aria-busy', 'false');
        $('#cvDashboardRefresh').prop('disabled', false);
        $('#cvDashboardStatus').text(total ? @json(__('Ringkasan berhasil diperbarui. Klik indikator untuk memfilter dashboard.')) : @json(__('Tidak ada karyawan yang sesuai filter. Sesuaikan atau reset filter di atas.')));
        $('#cvDashboardContent').prop('hidden', false);
    }

    let dashboardRequest = null;
    let dashboardRequestId = 0;
    function loadCvDashboard() {
        const requestId = ++dashboardRequestId;
        if (dashboardRequest) dashboardRequest.abort();
        setCvDashboardState(@json(__('Memuat ringkasan...')), true);
        const data = $('#cvDashboardFilters').serializeArray().filter(item => item.value !== '');
        dashboardRequest = $.ajax({
            url: @json(route('cv-maker-dashboard.data')),
            data: data,
            dataType: 'json'
        }).done(function(response) {
            if (requestId === dashboardRequestId) renderCvDashboard(response.data);
        }).fail(function(xhr) {
            if (xhr.statusText === 'abort' || requestId !== dashboardRequestId) return;
            let message = @json(__('Dashboard gagal dimuat. Klik Muat ulang untuk mencoba kembali.'));
            if (xhr.status === 401 || xhr.status === 419 || xhr.status === 200) message = @json(__('Sesi login berakhir atau respons tidak valid. Silakan login ulang.'));
            else if (xhr.status === 403) message = @json(__('Anda tidak memiliki akses Dashboard CV Maker.'));
            else if (xhr.status === 422) message = @json(__('Filter tidak valid. Reset filter lalu coba kembali.'));
            else if (xhr.status === 0) message = @json(__('Koneksi bermasalah. Periksa jaringan lalu klik Muat ulang.'));
            setCvDashboardState(message, false);
        }).always(function() {
            if (requestId === dashboardRequestId) {
                dashboardRequest = null;
                $('#cvDashboard').attr('aria-busy', 'false');
                $('#cvDashboardRefresh').prop('disabled', false);
            }
        });
    }
    $('#cvDashboardRefresh').on('click', loadCvDashboard);
    $('#cvDashboardFilters').on('change', 'select', loadCvDashboard)
        .on('submit', function(event) { event.preventDefault(); loadCvDashboard(); })
        .on('reset', function() { setTimeout(loadCvDashboard, 0); });
    $('#cvDashboard').on('click', '[data-cv-dashboard-filter]', function() {
        const key = $(this).attr('data-cv-dashboard-filter');
        const value = $(this).attr('data-value');
        if (key === 'progress_step') {
            $('#cv_filter_progress_status').val('in_progress');
        }
        $('#cv_filter_' + key).val(value).trigger('change');
    });
    loadCvDashboard();
