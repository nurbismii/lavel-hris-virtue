@extends('layouts.app')

@section('title', __('navigation.cv_maker_compare'))

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/admin-cv-maker-compare.css') }}">
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner ui-page cv-compare-page">
        <div class="ui-page-header cv-compare-header">
            <div class="ui-page-heading">
                <div class="ui-page-icon" aria-hidden="true">
                    <i class="fas fa-not-equal"></i>
                </div>
                <div>
                    <h4 class="ui-page-title">{{ __('navigation.cv_maker_compare') }}</h4>
                    <p class="ui-page-subtitle">Review perbedaan data CV Maker dan master HRIS sesuai scope akses karyawan.</p>
                </div>
            </div>
        </div>

        @if(!$integrationAvailable)
        <div class="alert ui-alert ui-alert--warning cv-compare-alert mb-3">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Koneksi CV Maker belum dikonfigurasi. Set env <code>CV_MAKER_DB_*</code> dan <code>CV_MAKER_NIK_HASH_KEY</code>.
        </div>
        @endif

        @if(!auth()->user()->canAccessAllEmployees())
        <div class="alert ui-alert cv-compare-alert mb-3">
            <i class="fas fa-lock me-2"></i>
            Data dibatasi sesuai scope role Anda: {{ auth()->user()->role->scope_label ?? 'Akun sendiri' }}.
        </div>
        @endif

        <section class="ui-panel cv-compare-panel" aria-labelledby="cvMakerCompareTableTitle">
            <div class="ui-panel__header">
                <div>
                    <h5 class="ui-panel__title" id="cvMakerCompareTableTitle">Daftar Compare</h5>
                    <p class="ui-panel__meta">Field merah berarti nilai HRIS dan CV Maker sama-sama tersedia tetapi berbeda.</p>
                </div>
                <button type="button" class="btn btn-sm btn-light border ui-btn-icon" id="btnResetCvCompareFilter">
                    <i class="fas fa-undo"></i>
                    Reset Filter
                </button>
            </div>

            <div class="ui-panel__body">
                <div class="cv-compare-filter-panel">
                    <div class="row g-3 align-items-end">
                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cvCompanyFilterDropdown">Perusahaan</label>
                            <div class="company-filter">
                                <button class="btn btn-light border dropdown-toggle company-filter__toggle" type="button" id="cvCompanyFilterDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                    <span id="cvFilterAreaLabel">Semua perusahaan</span>
                                </button>
                                <div class="dropdown-menu company-filter__menu" aria-labelledby="cvCompanyFilterDropdown">
                                    <div class="company-filter__menu-header">
                                        <span>Pilih perusahaan</span>
                                        <button type="button" class="btn btn-link btn-sm p-0" id="btnClearCvAreaFilter">Kosongkan</button>
                                    </div>
                                    @forelse ($areas as $area)
                                    <label class="company-filter__option">
                                        <input type="checkbox" class="form-check-input cv-filter-area-check" value="{{ $area->kode_perusahaan }}">
                                        <span>{{ $area->kode_perusahaan }}</span>
                                    </label>
                                    @empty
                                    <div class="company-filter__empty">Tidak ada perusahaan tersedia.</div>
                                    @endforelse
                                </div>
                            </div>
                            <select id="cv_filter_area" class="d-none" multiple aria-hidden="true">
                                @foreach ($areas as $area)
                                <option value="{{ $area->kode_perusahaan }}">{{ $area->kode_perusahaan }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cv_filter_departemen">Departemen</label>
                            <select id="cv_filter_departemen" class="form-select">
                                <option value="">Semua Departemen</option>
                                @php
                                $groupedDepts = [];
                                foreach ($departemens as $department) {
                                    $groupedDepts[optional($department->perusahaan)->nama_perusahaan ?? 'Lainnya'][] = $department;
                                }
                                @endphp

                                @foreach($groupedDepts as $company => $departmentItems)
                                <optgroup label="{{ $company }}">
                                    @foreach($departmentItems as $department)
                                    <option value="{{ $department->id }}">{{ $department->departemen }}</option>
                                    @endforeach
                                </optgroup>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cv_filter_divisi">Divisi</label>
                            <select id="cv_filter_divisi" class="form-select">
                                <option value="">Semua Divisi</option>
                                @foreach ($divisis as $division)
                                <option value="{{ $division->id }}">{{ $division->nama_divisi }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cv_filter_resign">Status</label>
                            <select id="cv_filter_resign" class="form-select">
                                <option value="">Semua Status</option>
                                <option value="AKTIF" selected>Aktif</option>
                                <option value="RESIGN SESUAI PROSEDUR">Resign Sesuai Prosedur</option>
                                <option value="RESIGN TIDAK SESUAI PROSEDUR">Resign Tidak Sesuai Prosedur</option>
                                <option value="RESIGN TIDAK SESUAI PROSEDUR-PENGAJUAN">Resign Tidak Sesuai Prosedur-Pengajuan</option>
                                <option value="RESIGN TIDAK SESUAI PROSEDUR-KABUR">Resign Tidak Sesuai Prosedur-Kabur</option>
                                <option value="RESIGN TIDAK SESUAI PROSEDUR-PAYROLL">Resign Tidak Sesuai Prosedur-Payroll</option>
                                <option value="PB RESIGN">PB Resign</option>
                                <option value="PUTUS KONTRAK">Putus Kontrak</option>
                                <option value="PHK">PHK</option>
                                <option value="PHK PENSIUN">PHK Pensiun</option>
                                <option value="PHK PENSIUN DINI">PHK Pensiun Dini</option>
                                <option value="PHK PIDANA">PHK Pidana</option>
                                <option value="PHK MENINGGAL DUNIA">PHK Meninggal Dunia</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="cv-compare-table-section ui-table-wrap">
                    <table id="cvMakerCompareTable" class="table table-bordered table-striped table-sm small text-sm nowrap align-middle ui-table">
                        <thead>
                            <tr>
                                <th>NIK</th>
                                <th>Karyawan HRIS</th>
                                <th>CV Maker</th>
                                <th>Hasil</th>
                                <th>Identitas</th>
                                <th>Organisasi</th>
                                <th>Wilayah</th>
                                <th>Pendidikan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="cvUpdatePreviewModal" tabindex="-1" aria-labelledby="cvUpdatePreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content cv-update-modal">
            <div class="modal-header">
                <div>
                    <h1 class="modal-title fs-5" id="cvUpdatePreviewModalLabel">Update HRIS dari CV Maker</h1>
                    <small class="text-muted" id="cvUpdatePreviewEmployee">-</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div id="cvUpdatePreviewLoading" class="cv-update-state">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    Memeriksa perubahan dari CV Maker...
                </div>
                <div id="cvUpdatePreviewError" class="alert ui-alert ui-alert--warning d-none mb-0"></div>
                <div id="cvUpdatePreviewEmpty" class="alert ui-alert d-none mb-0"></div>
                <div id="cvUpdatePreviewContent" class="d-none">
                    <div class="cv-update-warning mb-3">
                        <i class="fas fa-info-circle"></i>
                        Sistem hanya mengisi nilai CV Maker yang valid dan tidak kosong. Departemen, divisi, dan perusahaan tidak di-update otomatis karena memakai foreign key dan berdampak ke scope akses.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle cv-update-table mb-0">
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>HRIS sekarang</th>
                                    <th>CV Maker</th>
                                </tr>
                            </thead>
                            <tbody id="cvUpdatePreviewRows"></tbody>
                        </table>
                    </div>
                    <div id="cvUpdatePreviewSkipped" class="cv-update-skipped d-none"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border ui-btn-icon" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i>
                    Batal
                </button>
                <button type="button" class="btn btn-danger ui-btn-icon" id="cvUpdateConfirmButton" disabled>
                    <i class="fas fa-sync-alt"></i>
                    Update HRIS
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function selectedCvAreaCodes() {
        return $('.cv-filter-area-check:checked').map(function() {
            return this.value;
        }).get();
    }

    function syncCvAreaFilter() {
        const areas = selectedCvAreaCodes();
        const label = areas.length
            ? (areas.length <= 2 ? areas.join(', ') : `${areas.length} perusahaan dipilih`)
            : 'Semua perusahaan';

        $('#cv_filter_area').val(areas);
        $('#cvFilterAreaLabel').text(label);
        $('#cvCompanyFilterDropdown').toggleClass('is-active', areas.length > 0);
    }

    function resetCvDepartmentAndDivision(disableDepartment = true) {
        $('#cv_filter_departemen')
            .html('<option value="">Semua Departemen</option>')
            .val('')
            .prop('disabled', disableDepartment);

        $('#cv_filter_divisi')
            .html('<option value="">Semua Divisi</option>')
            .val('')
            .prop('disabled', true);
    }

    function showCvCompareAjaxError(xhr, fallbackMessage) {
        let message = fallbackMessage || 'Request gagal diproses. Silakan coba lagi.';

        if (xhr.responseJSON && xhr.responseJSON.message) {
            message = xhr.responseJSON.message;
        }

        if (xhr.status === 401 || xhr.status === 419) {
            message = 'Sesi login berakhir. Silakan login ulang.';
        }

        if (xhr.status === 403) {
            message = 'Anda tidak memiliki akses untuk membuka data compare.';
        }

        if (xhr.status === 0) {
            message = 'Koneksi bermasalah atau request diblokir. Silakan cek jaringan Anda.';
        }

        Swal.fire({
            icon: 'error',
            title: 'Gagal',
            text: message,
            confirmButtonText: 'OK'
        });
    }

    $.fn.dataTable.ext.errMode = 'none';

    const cvCompareTable = $('#cvMakerCompareTable')
        .on('error.dt', function(event, settings, techNote, message) {
            showCvCompareAjaxError({}, message || 'Data compare gagal dimuat.');
        })
        .DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            autoWidth: false,
            searchDelay: 450,
            language: {
                processing: 'Memuat data compare...',
                search: 'Cari:',
                lengthMenu: 'Tampilkan _MENU_ data',
                info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
                infoEmpty: 'Tidak ada data',
                infoFiltered: '(difilter dari _MAX_ total data)',
                zeroRecords: 'Data tidak ditemukan',
                emptyTable: 'Belum ada data compare',
                paginate: {
                    first: 'Pertama',
                    last: 'Terakhir',
                    next: 'Berikutnya',
                    previous: 'Sebelumnya'
                }
            },
            dom: "<'row mb-2'<'col-md-6'l><'col-md-6 text-end'f>>" +
                "<'table-scroll-wrapper'tr>" +
                "<'row mt-2'<'col-md-6'i><'col-md-6 text-end'p>>",
            ajax: {
                url: "{{ route('cv-maker-compare.data') }}",
                data: function(data) {
                    data.area = selectedCvAreaCodes();
                    data.departemen = $('#cv_filter_departemen').val();
                    data.divisi = $('#cv_filter_divisi').val();
                    data.status_resign = $('#cv_filter_resign').val();
                },
                error: function(xhr) {
                    showCvCompareAjaxError(xhr, 'Data compare gagal dimuat.');
                }
            },
            columns: [
                { data: 'nik', width: '90px' },
                { data: 'employee', orderable: true },
                { data: 'cv_status', orderable: false, searchable: false, width: '120px' },
                { data: 'mismatch_summary', orderable: false, searchable: false, width: '130px' },
                { data: 'identity', orderable: false, searchable: false },
                { data: 'work', orderable: false, searchable: false },
                { data: 'location', orderable: false, searchable: false },
                { data: 'education', orderable: false, searchable: false },
                { data: 'action', orderable: false, searchable: false, width: '70px' }
            ]
        });

    const cvUpdateModalEl = document.getElementById('cvUpdatePreviewModal');
    const cvUpdateModal = cvUpdateModalEl ? new bootstrap.Modal(cvUpdateModalEl) : null;
    const cvUpdateEmployee = document.getElementById('cvUpdatePreviewEmployee');
    const cvUpdateLoading = document.getElementById('cvUpdatePreviewLoading');
    const cvUpdateError = document.getElementById('cvUpdatePreviewError');
    const cvUpdateEmpty = document.getElementById('cvUpdatePreviewEmpty');
    const cvUpdateContent = document.getElementById('cvUpdatePreviewContent');
    const cvUpdateRows = document.getElementById('cvUpdatePreviewRows');
    const cvUpdateSkipped = document.getElementById('cvUpdatePreviewSkipped');
    const cvUpdateConfirm = document.getElementById('cvUpdateConfirmButton');
    let pendingCvUpdateUrl = null;

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined || value === '' ? '-' : value).html();
    }

    function resetCvUpdateModal() {
        pendingCvUpdateUrl = null;
        cvUpdateEmployee.textContent = '-';
        cvUpdateLoading.classList.remove('d-none');
        cvUpdateError.classList.add('d-none');
        cvUpdateEmpty.classList.add('d-none');
        cvUpdateContent.classList.add('d-none');
        cvUpdateSkipped.classList.add('d-none');
        cvUpdateError.textContent = '';
        cvUpdateEmpty.textContent = '';
        cvUpdateRows.innerHTML = '';
        cvUpdateSkipped.innerHTML = '';
        cvUpdateConfirm.disabled = true;
    }

    function renderCvUpdatePreview(payload, updateUrl) {
        cvUpdateLoading.classList.add('d-none');

        const changes = payload.changes || [];
        const skipped = payload.skipped || [];

        if (!changes.length) {
            cvUpdateEmpty.textContent = payload.message || 'Tidak ada perubahan yang bisa diperbarui dari CV Maker.';
            cvUpdateEmpty.classList.remove('d-none');

            if (skipped.length) {
                cvUpdateSkipped.innerHTML = renderSkippedItems(skipped);
                cvUpdateSkipped.classList.remove('d-none');
                cvUpdateContent.classList.remove('d-none');
            }

            return;
        }

        cvUpdateRows.innerHTML = changes.map(function(change) {
            return `<tr>
                <td>${escapeHtml(change.label)}</td>
                <td>${escapeHtml(change.old)}</td>
                <td class="cv-update-table__new">${escapeHtml(change.new)}</td>
            </tr>`;
        }).join('');

        if (skipped.length) {
            cvUpdateSkipped.innerHTML = renderSkippedItems(skipped);
            cvUpdateSkipped.classList.remove('d-none');
        }

        pendingCvUpdateUrl = updateUrl;
        cvUpdateConfirm.disabled = false;
        cvUpdateContent.classList.remove('d-none');
    }

    function renderSkippedItems(items) {
        const rows = items.map(function(item) {
            return `<li><strong>${escapeHtml(item.label)}</strong>: ${escapeHtml(item.reason)}</li>`;
        }).join('');

        return `<div class="cv-update-skipped__title">Field dilewati</div><ul>${rows}</ul>`;
    }

    $(document).on('click', '.js-cv-update-preview', function() {
        if (!cvUpdateModal) {
            return;
        }

        const button = $(this);
        const previewUrl = button.data('preview-url');
        const updateUrl = button.data('update-url');
        const employeeName = button.data('employee-name') || 'Karyawan';
        const originalHtml = button.html();

        resetCvUpdateModal();
        cvUpdateEmployee.textContent = employeeName;
        cvUpdateModal.show();
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: previewUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(payload) {
                renderCvUpdatePreview(payload, updateUrl);
            },
            error: function(xhr) {
                cvUpdateLoading.classList.add('d-none');
                cvUpdateError.text(xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Preview update gagal dimuat.');
                cvUpdateError.classList.remove('d-none');
            },
            complete: function() {
                button.prop('disabled', false).html(originalHtml);
            }
        });
    });

    $('#cvUpdateConfirmButton').on('click', function() {
        if (!pendingCvUpdateUrl) {
            return;
        }

        const button = $(this);
        const originalHtml = button.html();

        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Mengupdate...');

        $.ajax({
            url: pendingCvUpdateUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(payload) {
                cvUpdateModal.hide();
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: payload.message || 'Data HRIS berhasil diperbarui dari CV Maker.',
                    confirmButtonText: 'OK'
                }).then(function() {
                    cvCompareTable.ajax.reload(null, false);
                });
            },
            error: function(xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'Data HRIS gagal diperbarui.',
                    confirmButtonText: 'OK'
                });
            },
            complete: function() {
                button.prop('disabled', false).html(originalHtml);
            }
        });
    });

    syncCvAreaFilter();
    resetCvDepartmentAndDivision(true);

    $('.cv-filter-area-check').on('change', function() {
        syncCvAreaFilter();
        $('#cv_filter_area').trigger('change');
    });

    $('#btnClearCvAreaFilter').on('click', function() {
        $('.cv-filter-area-check').prop('checked', false);
        syncCvAreaFilter();
        $('#cv_filter_area').trigger('change');
    });

    $('#cv_filter_area').on('change', function() {
        const areas = selectedCvAreaCodes();
        resetCvDepartmentAndDivision(!areas.length);

        if (!areas.length) {
            cvCompareTable.draw();
            return;
        }

        $('#cv_filter_departemen').html('<option value="">Loading...</option>');

        $.get("{{ route('ajax.departemen.by.area') }}", {
            area: areas
        }, function(response) {
            let options = '<option value="">Semua Departemen</option>';
            response.forEach(function(item) {
                options += `<option value="${item.id}">${item.departemen}</option>`;
            });
            $('#cv_filter_departemen').html(options).prop('disabled', false);
            cvCompareTable.draw();
        }).fail(function(xhr) {
            resetCvDepartmentAndDivision(true);
            showCvCompareAjaxError(xhr, 'Departemen gagal dimuat.');
        });
    });

    $('#cv_filter_departemen').on('change', function() {
        const departemen = $(this).val();

        $('#cv_filter_divisi').html('<option value="">Loading...</option>').prop('disabled', true);

        if (!departemen) {
            $('#cv_filter_divisi').html('<option value="">Semua Divisi</option>').prop('disabled', true);
            cvCompareTable.draw();
            return;
        }

        $.get("{{ route('ajax.divisi.by.departemen') }}", {
            departemen
        }, function(response) {
            let options = '<option value="">Semua Divisi</option>';
            response.forEach(function(item) {
                options += `<option value="${item.id}">${item.nama_divisi}</option>`;
            });
            $('#cv_filter_divisi').html(options).prop('disabled', false);
            cvCompareTable.draw();
        }).fail(function(xhr) {
            $('#cv_filter_divisi').html('<option value="">Divisi gagal dimuat</option>').prop('disabled', true);
            showCvCompareAjaxError(xhr, 'Divisi gagal dimuat.');
        });
    });

    $('#cv_filter_divisi, #cv_filter_resign').on('change', function() {
        cvCompareTable.draw();
    });

    $('#btnResetCvCompareFilter').on('click', function() {
        $('.cv-filter-area-check').prop('checked', false);
        syncCvAreaFilter();
        resetCvDepartmentAndDivision(true);
        $('#cv_filter_resign').val('AKTIF');
        cvCompareTable.draw();
    });
</script>
@endpush
