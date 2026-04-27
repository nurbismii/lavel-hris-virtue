(function($) {
    'use strict';

    const page = document.querySelector('.presensi-page');

    if (!page) {
        return;
    }

    const fetchUrl = page.dataset.fetchUrl;
    const exportUrl = page.dataset.exportUrl;
    const areaUrl = page.dataset.areaUrl;
    const divisiUrl = page.dataset.divisiUrl;
    const tableSelector = '#table-presensi';
    let table = null;

    const elements = {
        area: $('#filter_area'),
        departemen: $('#filter_departemen'),
        divisi: $('#filter_divisi'),
        cutoff: $('#cutoff_month'),
        cutoffLabel: $('#cutoffLabel'),
        filterButton: $('#btnFilter'),
        exportButton: $('#btnExport'),
        hint: $('#presensiHint'),
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showWarning(message) {
        if (window.Swal) {
            Swal.fire({
                icon: 'warning',
                title: 'Periksa filter',
                text: message,
            });

            return;
        }

        alert(message);
    }

    function showError(message) {
        if (window.Swal) {
            Swal.fire({
                icon: 'error',
                title: 'Data gagal dimuat',
                text: message,
            });

            return;
        }

        alert(message);
    }

    function formatMonthValue(date) {
        const month = String(date.getMonth() + 1).padStart(2, '0');

        return `${date.getFullYear()}-${month}`;
    }

    function formatDateIndonesia(date) {
        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }).format(date);
    }

    function updateCutoffLabel() {
        const month = elements.cutoff.val();

        if (!month) {
            elements.cutoffLabel.text('Pilih cutoff untuk melihat periode presensi.');
            return;
        }

        const [year, monthNumber] = month.split('-').map(Number);
        const start = new Date(year, monthNumber - 2, 16);
        const end = new Date(year, monthNumber - 1, 15);

        elements.cutoffLabel.text(`Cutoff ${formatDateIndonesia(start)} - ${formatDateIndonesia(end)}`);
    }

    function setDepartmentOptions(optionsHtml, disabled = false) {
        elements.departemen.html(optionsHtml).prop('disabled', disabled);
    }

    function setDivisionOptions(optionsHtml, disabled = false) {
        elements.divisi.html(optionsHtml).prop('disabled', disabled);
    }

    function renderDateHeader(date, meta = {}) {
        const dayLabel = escapeHtml(meta.day || '');
        const holidayName = escapeHtml(meta.holiday_name || '');
        const holidayChip = meta.is_national_holiday ?
            `<span class="holiday-chip" title="${holidayName}">L</span>` :
            (meta.is_sunday ? '<span class="holiday-chip holiday-chip--sunday" title="Minggu">M</span>' : '');

        return `
            <div>${escapeHtml(date.slice(-2))}</div>
            <small>${dayLabel}</small>
            ${holidayChip}
        `;
    }

    function decorateDateHeader(th, meta = {}) {
        th.classList.add('presensi-date-head');

        if (meta.is_sunday) {
            th.classList.add('is-sunday');
            th.title = 'Minggu';
        }

        if (meta.is_national_holiday) {
            th.classList.add('is-national-holiday');
            th.title = meta.holiday_name || 'Libur nasional';
        }
    }

    function formatTime(value) {
        if (!value) {
            return '-';
        }

        const text = String(value);

        if (/^\d{2}:\d{2}(\s\+1)?$/.test(text)) {
            return text;
        }

        if (text.length >= 16) {
            return text.substring(11, 16);
        }

        if (text.length >= 5) {
            return text.substring(0, 5);
        }

        return text;
    }

    function statusClass(status) {
        const normalized = String(status || '').toUpperCase().replace(/\s+/g, '');
        const map = {
            'CT': 'is-ct',
            'CR': 'is-cr',
            'I/P': 'is-ip',
            'IP': 'is-ip',
            'I/U': 'is-iu',
            'IU': 'is-iu',
            'OFF': 'is-off',
            'L': 'is-l',
            'A': 'is-a',
            'ALPA': 'is-a',
        };

        return map[normalized] || '';
    }

    function renderAttendanceCell(data) {
        if (!data) {
            return '<span class="presensi-empty-cell">-</span>';
        }

        if (data.status) {
            const status = escapeHtml(data.status);

            return `<span class="presensi-status-badge ${statusClass(status)}">${status}</span>`;
        }

        const verification = renderVerificationMarker(data.verification);

        return `
            <div class="presensi-time-grid">
                <span class="presensi-time-item"><span class="presensi-time-label">M</span>${escapeHtml(formatTime(data.m))}</span>
                <span class="presensi-time-item"><span class="presensi-time-label">I</span>${escapeHtml(formatTime(data.i))}</span>
                <span class="presensi-time-item"><span class="presensi-time-label">K</span>${escapeHtml(formatTime(data.k))}</span>
                <span class="presensi-time-item"><span class="presensi-time-label">P</span>${escapeHtml(formatTime(data.p))}</span>
            </div>
            ${verification}
        `;
    }

    function renderVerificationMarker(status) {
        const map = {
            verified: {
                className: 'is-verified',
                label: 'Server verified',
                icon: 'SV',
            },
            pending_review: {
                className: 'is-review',
                label: 'Menunggu review server',
                icon: 'RV',
            },
            rejected: {
                className: 'is-rejected',
                label: 'Ditolak verifier',
                icon: 'RJ',
            },
        };
        const item = map[String(status || '')];

        if (!item) {
            return '';
        }

        return `<span class="presensi-verification-chip ${item.className}" title="${escapeHtml(item.label)}">${item.icon}</span>`;
    }

    function buildColumns(response) {
        const tanggalMeta = response.tanggalMeta || {};
        const columns = [{
                data: 'nik_karyawan',
                title: 'NIK',
                className: 'presensi-sticky-col presensi-sticky-nik',
                width: '112px',
                orderable: false,
                render: data => `<span class="fw-semibold">${escapeHtml(data)}</span>`,
            },
            {
                data: 'nama_karyawan',
                title: 'Karyawan',
                className: 'presensi-sticky-col presensi-sticky-name',
                width: '230px',
                orderable: false,
                render: data => `
                    <div class="presensi-employee-cell">
                        <strong>${escapeHtml(data)}</strong>
                    </div>
                `,
            },
        ];

        (response.tanggalHeaders || []).forEach(date => {
            const meta = tanggalMeta[date] || {};
            const dateClasses = [
                'presensi-date-cell',
                meta.is_sunday ? 'is-sunday' : '',
                meta.is_national_holiday ? 'is-national-holiday' : '',
            ].filter(Boolean).join(' ');

            columns.push({
                data: `tanggal_data.${date}`,
                title: renderDateHeader(date, meta),
                orderable: false,
                searchable: false,
                className: dateClasses,
                width: '82px',
                dateMeta: meta,
                render: renderAttendanceCell,
            });
        });

        return columns;
    }

    function buildTable(columns) {
        if ($.fn.DataTable.isDataTable(tableSelector)) {
            table.destroy();
            $(tableSelector).empty();
            table = null;
        }

        table = $(tableSelector).DataTable({
            processing: true,
            serverSide: true,
            pageLength: 50,
            lengthMenu: [
                [25, 50, 100],
                [25, 50, 100],
            ],
            scrollX: true,
            scrollY: 'calc(100vh - 350px)',
            scrollCollapse: true,
            fixedHeader: false,
            autoWidth: false,
            ordering: false,
            ajax: {
                url: fetchUrl,
                type: 'GET',
                data: function(data) {
                    data.cutoff_month = elements.cutoff.val();
                    data.departemen = elements.departemen.val();
                    data.divisi = elements.divisi.val();
                },
                error: function() {
                    showError('Request data presensi tidak berhasil. Coba ulangi atau kecilkan filter data.');
                },
            },
            columns: columns,
            headerCallback: function(thead) {
                $(thead).find('th').each(function(index) {
                    const column = columns[index] || {};

                    if (index === 0) {
                        this.classList.add('presensi-sticky-col', 'presensi-sticky-nik');
                    }

                    if (index === 1) {
                        this.classList.add('presensi-sticky-col', 'presensi-sticky-name');
                    }

                    if (column.dateMeta) {
                        decorateDateHeader(this, column.dateMeta);
                    }
                });
            },
            language: {
                processing: 'Memuat data...',
                search: 'Cari:',
                lengthMenu: 'Tampilkan _MENU_ karyawan',
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ karyawan',
                infoEmpty: 'Tidak ada data',
                infoFiltered: '(difilter dari _MAX_ total)',
                emptyTable: 'Tidak ada data presensi untuk filter ini.',
                zeroRecords: 'Data tidak ditemukan.',
                paginate: {
                    first: 'Pertama',
                    previous: 'Sebelumnya',
                    next: 'Berikutnya',
                    last: 'Terakhir',
                },
            },
        });

        elements.hint.addClass('d-none');
    }

    function loadData() {
        if (!elements.departemen.val()) {
            showWarning('Silakan pilih departemen terlebih dahulu.');
            return;
        }

        if (!elements.cutoff.val()) {
            showWarning('Silakan pilih cutoff terlebih dahulu.');
            return;
        }

        elements.filterButton.prop('disabled', true).text('Memuat...');

        $.get(fetchUrl, {
            cutoff_month: elements.cutoff.val(),
            departemen: elements.departemen.val(),
            divisi: elements.divisi.val(),
            length: 1,
        }).done(function(response) {
            if (!response.tanggalHeaders || !response.tanggalHeaders.length) {
                showWarning('Header tanggal tidak tersedia untuk filter ini.');
                return;
            }

            buildTable(buildColumns(response));
        }).fail(function() {
            showError('Request header presensi tidak berhasil. Silakan coba lagi.');
        }).always(function() {
            elements.filterButton.prop('disabled', false).text('Tampilkan');
        });
    }

    function loadDepartmentsByArea(area) {
        setDepartmentOptions('<option value="">Loading...</option>', true);
        setDivisionOptions('<option value="">Semua Divisi</option>', true);

        if (!area) {
            setDepartmentOptions('<option value="">Pilih Perusahaan Dahulu</option>', true);
            return;
        }

        $.get(areaUrl, {
            area,
        }).done(function(response) {
            let options = '<option value="">Pilih Departemen</option>';

            response.forEach(item => {
                options += `<option value="${escapeHtml(item.id)}">${escapeHtml(item.departemen)}</option>`;
            });

            setDepartmentOptions(options, false);
        }).fail(function() {
            setDepartmentOptions('<option value="">Departemen gagal dimuat</option>', true);
            showError('Departemen gagal dimuat.');
        });
    }

    function loadDivisionsByDepartment(departemen) {
        setDivisionOptions('<option value="">Loading...</option>', true);

        if (!departemen) {
            setDivisionOptions('<option value="">Semua Divisi</option>', true);
            return;
        }

        $.get(divisiUrl, {
            departemen,
        }).done(function(response) {
            let options = '<option value="">Semua Divisi</option>';

            response.forEach(item => {
                options += `<option value="${escapeHtml(item.id)}">${escapeHtml(item.nama_divisi)}</option>`;
            });

            setDivisionOptions(options, false);
        }).fail(function() {
            setDivisionOptions('<option value="">Divisi gagal dimuat</option>', true);
            showError('Divisi gagal dimuat.');
        });
    }

    function exportData() {
        if (!elements.departemen.val()) {
            showWarning('Pilih departemen terlebih dahulu sebelum export.');
            return;
        }

        if (!elements.cutoff.val()) {
            showWarning('Pilih cutoff terlebih dahulu sebelum export.');
            return;
        }

        const params = new URLSearchParams({
            departemen: elements.departemen.val(),
            divisi: elements.divisi.val() || '',
            cutoff_month: elements.cutoff.val(),
        });

        window.open(`${exportUrl}?${params.toString()}`, '_blank');
    }

    elements.area.on('change', function() {
        loadDepartmentsByArea(this.value);
    });

    elements.departemen.on('change', function() {
        loadDivisionsByDepartment(this.value);
    });

    elements.cutoff.on('change', updateCutoffLabel);
    elements.filterButton.on('click', loadData);
    elements.exportButton.on('click', exportData);

    $(document).ready(function() {
        elements.cutoff.val(formatMonthValue(new Date()));
        updateCutoffLabel();
    });
})(jQuery);
