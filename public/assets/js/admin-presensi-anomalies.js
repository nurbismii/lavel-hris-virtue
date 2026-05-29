(function($) {
    'use strict';

    const page = document.querySelector('.attendance-anomaly-page');

    if (!page) {
        return;
    }

    const sourceUrl = page.dataset.sourceUrl;
    const form = $('#anomalyFilterForm');
    const tableSelector = '#attendance-anomaly-table';
    const filterButton = form.find('button[type="submit"]');
    let table = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showError(xhr, fallbackMessage) {
        let message = fallbackMessage || 'Data anomali gagal dimuat.';

        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
            message = xhr.responseJSON.message;
        }

        if (xhr && xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
            const firstKey = Object.keys(xhr.responseJSON.errors)[0];

            if (firstKey && xhr.responseJSON.errors[firstKey][0]) {
                message = xhr.responseJSON.errors[firstKey][0];
            }
        }

        if (xhr && (xhr.status === 401 || xhr.status === 419)) {
            message = 'Sesi login berakhir. Silakan login ulang.';
        }

        if (xhr && xhr.status === 403) {
            message = 'Anda tidak memiliki akses untuk membuka dashboard anomali presensi.';
        }

        if (xhr && xhr.status === 0) {
            message = 'Koneksi bermasalah atau request diblokir. Silakan cek jaringan Anda.';
        }

        if (window.Swal) {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: message,
                confirmButtonText: 'OK',
            });

            return;
        }

        alert(message);
    }

    function formFilters() {
        const data = {};
        const areaValue = form.find('[name="area[]"]').val();

        form.serializeArray().forEach(item => {
            if (item.name === 'area[]') {
                return;
            }

            data[item.name] = item.value;
        });

        data.area = Array.isArray(areaValue) ? areaValue : [];

        return data;
    }

    function initCompanySelect() {
        const areaSelect = $('.js-anomaly-company-select');

        if (!$.fn.select2 || !areaSelect.length || areaSelect.data('select2')) {
            return;
        }

        areaSelect.select2({
            placeholder: areaSelect.data('placeholder') || 'Pilih perusahaan',
            width: '100%',
            closeOnSelect: false,
            allowClear: true,
        });
    }

    function formatDateIndonesia(value) {
        if (!value) {
            return '-';
        }

        const parts = value.split('-').map(Number);
        const date = new Date(parts[0], parts[1] - 1, parts[2]);

        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }).format(date);
    }

    function updatePeriodLabel() {
        const filters = formFilters();
        const label = `Periode ${formatDateIndonesia(filters.date_from)} - ${formatDateIndonesia(filters.date_to)}`;

        $('#anomalyPeriodLabel').text(label);
    }

    function updateSummary(summary) {
        if (!summary) {
            return;
        }

        $('[data-anomaly-summary-key]').each(function() {
            const key = this.dataset.anomalySummaryKey;
            const value = Number(summary[key] || 0);

            this.textContent = value.toLocaleString('id-ID');
        });
    }

    function severityClass(severity) {
        const map = {
            danger: 'bg-danger',
            warning: 'bg-warning text-dark',
            secondary: 'bg-secondary',
            primary: 'bg-primary',
        };

        return map[severity] || 'bg-secondary';
    }

    function renderClock(data) {
        const jam = data || {};

        return `
            <div class="anomaly-clock-grid">
                <span><b class="anomaly-clock-label">M</b>${escapeHtml(jam.masuk || '-')}</span>
                <span><b class="anomaly-clock-label">I</b>${escapeHtml(jam.istirahat || '-')}</span>
                <span><b class="anomaly-clock-label">K</b>${escapeHtml(jam.kembali || '-')}</span>
                <span><b class="anomaly-clock-label">P</b>${escapeHtml(jam.pulang || '-')}</span>
            </div>
        `;
    }

    function renderStatus(row) {
        const score = row.security_score === null || row.security_score === undefined ?
            '-' :
            escapeHtml(row.security_score);

        return `
            <div>${escapeHtml(row.status_presensi || '-')}</div>
            <small class="text-muted">${escapeHtml(row.status_absen_label || '-')}</small>
            <div><small class="text-muted">Skor ${score}</small></div>
        `;
    }

    function renderOrganization(row) {
        return `
            <div class="anomaly-org-cell">
                <div><strong>${escapeHtml(row.area_kerja || '-')}</strong></div>
                <div>${escapeHtml(row.departemen || '-')}</div>
                <div class="text-muted">${escapeHtml(row.divisi || '-')}</div>
            </div>
        `;
    }

    function renderAnomalies(anomalies) {
        if (!Array.isArray(anomalies) || anomalies.length === 0) {
            return '<span class="text-muted">-</span>';
        }

        return `
            <div class="anomaly-badge-list">
                ${anomalies.map(item => `
                    <span class="badge ${severityClass(item.severity)}">${escapeHtml(item.label)}</span>
                `).join('')}
            </div>
        `;
    }

    function renderGps(gps) {
        if (!gps || !gps.summary || gps.summary === '-') {
            return '<span class="text-muted">-</span>';
        }

        const mapLink = gps.map_url ?
            `<a href="${escapeHtml(gps.map_url)}" target="_blank" rel="noopener">Map</a>` :
            '';

        return `
            <div class="anomaly-gps-cell">
                <div>${escapeHtml(gps.summary)}</div>
                ${mapLink}
            </div>
        `;
    }

    function renderAction(row) {
        if (row.review_url) {
            return `
                <a href="${escapeHtml(row.review_url)}" class="btn btn-sm btn-outline-primary">
                    Review
                </a>
            `;
        }

        return '<span class="text-muted small">-</span>';
    }

    function setLoading(isLoading) {
        const originalText = filterButton.data('original-text') || 'Tampilkan';

        filterButton
            .prop('disabled', isLoading)
            .html(isLoading ? 'Memuat...' : `<i class="fas fa-search me-1"></i> ${originalText}`);
    }

    function initTable() {
        if ($.fn.dataTable) {
            $.fn.dataTable.ext.errMode = 'none';
        }

        table = $(tableSelector).DataTable({
            processing: true,
            serverSide: true,
            pageLength: 25,
            lengthMenu: [
                [10, 25, 50, 100],
                [10, 25, 50, 100],
            ],
            ordering: false,
            searching: true,
            ajax: {
                url: sourceUrl,
                type: 'GET',
                data: function(data) {
                    return Object.assign(data, formFilters());
                },
                error: function(xhr) {
                    setLoading(false);
                    showError(xhr, 'Data anomali presensi gagal dimuat.');
                },
            },
            columns: [
                {
                    data: 'tanggal_label',
                    name: 'tanggal',
                    render: data => `<span class="fw-semibold">${escapeHtml(data)}</span>`,
                },
                {
                    data: 'nik_karyawan',
                    name: 'nik_karyawan',
                    render: data => `<span class="fw-semibold">${escapeHtml(data)}</span>`,
                },
                {
                    data: null,
                    name: 'nama_karyawan',
                    render: row => `
                        <div><strong>${escapeHtml(row.nama_karyawan || '-')}</strong></div>
                        <small class="text-muted anomaly-device-meta" title="${escapeHtml(row.device_info || '-')}">${escapeHtml(row.device_info || '-')}</small>
                    `,
                },
                {
                    data: null,
                    name: 'organisasi',
                    render: renderOrganization,
                },
                {
                    data: 'jam',
                    name: 'jam',
                    render: renderClock,
                },
                {
                    data: null,
                    name: 'status',
                    render: renderStatus,
                },
                {
                    data: 'anomalies',
                    name: 'anomalies',
                    render: renderAnomalies,
                },
                {
                    data: 'gps',
                    name: 'gps',
                    render: renderGps,
                },
                {
                    data: null,
                    name: 'action',
                    searchable: false,
                    render: renderAction,
                },
            ],
            language: {
                processing: 'Memuat data...',
                search: 'Cari:',
                lengthMenu: 'Tampilkan _MENU_ data',
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ anomali',
                infoEmpty: 'Tidak ada data',
                infoFiltered: '(difilter dari _MAX_ total)',
                emptyTable: 'Tidak ada anomali untuk filter ini.',
                zeroRecords: 'Data tidak ditemukan.',
                paginate: {
                    first: 'Pertama',
                    previous: 'Sebelumnya',
                    next: 'Berikutnya',
                    last: 'Terakhir',
                },
            },
        });

        $(tableSelector)
            .on('preXhr.dt', function() {
                setLoading(true);
            })
            .on('xhr.dt', function(event, settings, json) {
                setLoading(false);
                updateSummary(json ? json.summary : null);
            });
    }

    form.on('submit', function(event) {
        event.preventDefault();
        updatePeriodLabel();

        if (table) {
            table.ajax.reload();
        }
    });

    initCompanySelect();
    updatePeriodLabel();
    initTable();
})(jQuery);
