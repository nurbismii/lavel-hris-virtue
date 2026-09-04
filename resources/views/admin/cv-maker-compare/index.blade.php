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
                    <p class="ui-panel__meta">Klik Detail pada kolom Hasil untuk melihat identitas, organisasi, wilayah, pendidikan, dan opsi update HRIS.</p>
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
                            <label class="form-label" for="cvJobTitleFilterDropdown">Jabatan CV Maker</label>
                            <div class="company-filter">
                                <button class="btn btn-light border dropdown-toggle company-filter__toggle" type="button" id="cvJobTitleFilterDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                    <span id="cvFilterJobTitleLabel">Semua jabatan</span>
                                </button>
                                <div class="dropdown-menu company-filter__menu" aria-labelledby="cvJobTitleFilterDropdown">
                                    <div class="company-filter__menu-header">
                                        <span>Pilih jabatan dari CV Maker</span>
                                        <button type="button" class="btn btn-link btn-sm p-0" id="btnClearCvJobTitleFilter">Kosongkan</button>
                                    </div>
                                    @forelse ($jobTitles as $jobTitle)
                                    <label class="company-filter__option">
                                        <input type="checkbox" class="form-check-input cv-filter-job-title-check" value="{{ $jobTitle }}">
                                        <span>{{ $jobTitle }}</span>
                                    </label>
                                    @empty
                                    <div class="company-filter__empty">Tidak ada jabatan tersedia.</div>
                                    @endforelse
                                </div>
                            </div>
                            <select id="cv_filter_jabatan" class="d-none" multiple aria-hidden="true">
                                @foreach ($jobTitles as $jobTitle)
                                <option value="{{ $jobTitle }}">{{ $jobTitle }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cv_filter_posisi">Posisi HRIS</label>
                            <select id="cv_filter_posisi" class="form-select cv-position-filter" multiple data-placeholder="Cari dan pilih posisi HRIS"></select>
                        </div>

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cv_filter_skill_category">Kategori Posisi CV Maker</label>
                            <select id="cv_filter_skill_category" class="form-select">
                                <option value="">Semua Kategori</option>
                                @foreach ($skillCategories as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
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

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cv_filter_reminder">Reminder CV</label>
                            <select id="cv_filter_reminder" class="form-select">
                                <option value="">Semua Reminder</option>
                                <option value="needs_reminder">Perlu Diingatkan</option>
                                <option value="not_needed">Tidak Perlu Diingatkan</option>
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cv_filter_progress_status">Status Progress</label>
                            <select id="cv_filter_progress_status" class="form-select">
                                <option value="">Semua Progress</option>
                                <option value="not_synced">Snapshot Belum Tersedia</option>
                                <option value="no_account">Belum Memiliki Akun CV</option>
                                <option value="no_profile">Profil CV Belum Dibuat</option>
                                <option value="in_progress">Dalam Progress</option>
                                <option value="complete">Sudah Lengkap</option>
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cvProgressStepDropdown">Tahap Progress</label>
                            <div class="company-filter">
                                <button class="btn btn-light border dropdown-toggle company-filter__toggle" type="button" id="cvProgressStepDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                    <span id="cvProgressStepLabel">Semua tahap</span>
                                </button>
                                <div class="dropdown-menu company-filter__menu" aria-labelledby="cvProgressStepDropdown">
                                    <div class="company-filter__menu-header">
                                        <span>Pilih tahap progress</span>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-link btn-sm p-0" id="btnSelectAllCvProgressSteps">Pilih semua</button>
                                            <button type="button" class="btn btn-link btn-sm p-0" id="btnClearCvProgressSteps">Kosongkan</button>
                                        </div>
                                    </div>
                                    @foreach([
                                        1 => 'Data Pribadi',
                                        2 => 'Ringkasan Profil',
                                        3 => 'Pendidikan',
                                        4 => 'Pengalaman',
                                        5 => 'Keahlian',
                                        6 => 'Sertifikasi',
                                        7 => 'Tambahan',
                                        8 => 'Dokumen',
                                    ] as $stepNumber => $stepLabel)
                                    <label class="company-filter__option">
                                        <input type="checkbox" class="form-check-input cv-progress-step-check" value="{{ $stepNumber }}" data-label="{{ $stepLabel }}">
                                        <span>{{ $stepNumber }} - {{ $stepLabel }}</span>
                                    </label>
                                    @endforeach
                                </div>
                            </div>
                            <select id="cv_filter_progress_step" class="d-none" multiple aria-hidden="true">
                                <option value="1">1 - Data Pribadi</option>
                                <option value="2">2 - Ringkasan Profil</option>
                                <option value="3">3 - Pendidikan</option>
                                <option value="4">4 - Pengalaman</option>
                                <option value="5">5 - Keahlian</option>
                                <option value="6">6 - Sertifikasi</option>
                                <option value="7">7 - Tambahan</option>
                                <option value="8">8 - Dokumen</option>
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cv_filter_review_status">Status Pemeriksaan</label>
                            <select id="cv_filter_review_status" class="form-select">
                                <option value="">Semua Pemeriksaan</option>
                                <option value="unreviewed">Belum Diperiksa</option>
                                <option value="in_review">Sedang Diperiksa</option>
                                <option value="needs_employee_confirmation">Perlu Konfirmasi Karyawan</option>
                                <option value="completed">Selesai Diperiksa</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 align-items-center mt-3 mb-2">
                    <button type="button" class="btn btn-sm btn-primary ui-btn-icon" id="btnCvReminderSelected" disabled>
                        <i class="fas fa-envelope"></i>
                        Email Pilihan (<span id="cvReminderSelectedCount">0</span>)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary ui-btn-icon" id="btnCvReminderFiltered">
                        <i class="fas fa-mail-bulk"></i>
                        Email Semua Hasil Filter
                    </button>
                    <span class="small text-muted">Hanya karyawan berstatus Perlu Diingatkan yang akan diproses. Cooldown pengiriman tetap diperiksa oleh server.</span>
                </div>

                <div class="alert ui-alert d-none mb-3" id="cvReminderBatchStatus" role="status" aria-live="polite"></div>

                <div class="cv-compare-table-section ui-table-wrap">
                    <table id="cvMakerCompareTable" class="table table-bordered table-striped table-sm small text-sm nowrap align-middle ui-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 42px"><input type="checkbox" class="form-check-input" id="cvReminderSelectPage" aria-label="Pilih semua reminder pada halaman ini"></th>
                                <th>NIK</th>
                                <th>Karyawan</th>
                                <th>CV Maker</th>
                                <th>Hasil</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>

@endsection

@push('scripts')
@include('admin.cv-maker-compare.partials.dialog-scripts')
<script src="{{ versioned_asset('assets/js/plugin/select2/select2.full.min.js') }}"></script>
<script>
    const selectedCvReminderNiks = new Set();

    function updateCvReminderSelectionUi() {
        $('#cvReminderSelectedCount').text(selectedCvReminderNiks.size);
        $('#btnCvReminderSelected').prop('disabled', selectedCvReminderNiks.size < 1);
        const eligibleCount = $('.js-cv-reminder-row').length;
        const checkedCount = $('.js-cv-reminder-row:checked').length;
        $('#cvReminderSelectPage').prop('checked', eligibleCount > 0 && checkedCount === eligibleCount);
    }

    function clearCvReminderSelection() {
        selectedCvReminderNiks.clear();
        $('#cvReminderSelectPage').prop('checked', false);
        updateCvReminderSelectionUi();
    }

    function selectedCvAreaCodes() {
        return $('.cv-filter-area-check:checked').map(function() {
            return this.value;
        }).get();
    }

    function selectedCvJobTitles() {
        return $('.cv-filter-job-title-check:checked').map(function() {
            return this.value;
        }).get();
    }

    function syncCvJobTitleFilter() {
        const jobTitles = selectedCvJobTitles();
        const label = jobTitles.length
            ? (jobTitles.length === 1 ? jobTitles[0] : `${jobTitles.length} jabatan dipilih`)
            : 'Semua jabatan';

        $('#cv_filter_jabatan').val(jobTitles);
        $('#cvFilterJobTitleLabel').text(label);
        $('#cvJobTitleFilterDropdown').toggleClass('is-active', jobTitles.length > 0);
    }

    function syncCvProgressStepFilter() {
        const checkedSteps = $('.cv-progress-step-check:checked');
        const values = checkedSteps.map(function() {
            return $(this).val();
        }).get();
        let label = 'Semua tahap';

        if (values.length === 1) {
            const checkbox = checkedSteps.first();
            label = `Tahap ${checkbox.val()} - ${checkbox.data('label')}`;
        } else if (values.length > 1) {
            label = `${values.length} tahap dipilih`;
        }

        $('#cv_filter_progress_step').val(values);
        $('#cvProgressStepLabel').text(label);
        $('#cvProgressStepDropdown').toggleClass('is-active', values.length > 0);
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

        window.CvMakerDialog.fire({
            icon: 'error',
            title: 'Gagal',
            text: message,
            confirmButtonText: 'OK'
        });
    }

    $.fn.dataTable.ext.errMode = 'none';

    $('#cv_filter_posisi').select2({
        width: '100%',
        placeholder: $('#cv_filter_posisi').data('placeholder'),
        minimumInputLength: 0,
        closeOnSelect: false,
        ajax: {
            url: "{{ route('cv-maker-compare.positions') }}",
            dataType: 'json',
            delay: 300,
            data: function(params) {
                return {
                    q: params.term || '',
                    page: params.page || 1
                };
            },
            processResults: function(response) {
                return response;
            },
            error: function(xhr) {
                if (xhr.statusText === 'abort') return;
                showCvCompareAjaxError(xhr, 'Daftar posisi HRIS gagal dimuat.');
            }
        },
        language: {
            searching: function() {
                return 'Mencari posisi...';
            },
            loadingMore: function() {
                return 'Memuat posisi berikutnya...';
            },
            noResults: function() {
                return 'Posisi tidak ditemukan';
            }
        }
    });

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
            order: [[2, 'asc']],
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
                    data.posisi = $('#cv_filter_posisi').val() || [];
                    data.jabatan = selectedCvJobTitles();
                    data.cv_skill_category = $('#cv_filter_skill_category').val();
                    data.status_resign = $('#cv_filter_resign').val();
                    data.cv_reminder = $('#cv_filter_reminder').val();
                    data.cv_progress_status = $('#cv_filter_progress_status').val();
                    data.cv_progress_step = $('#cv_filter_progress_step').val();
                    data.cv_review_status = $('#cv_filter_review_status').val();
                },
                error: function(xhr) {
                    showCvCompareAjaxError(xhr, 'Data compare gagal dimuat.');
                }
            },
            columns: [
                { data: 'select', orderable: false, searchable: false, width: '42px', className: 'text-center' },
                { data: 'nik', width: '90px' },
                { data: 'employee', orderable: true },
                { data: 'cv_status', orderable: false, searchable: false, width: '120px' },
                { data: 'result', orderable: false, searchable: false, width: '190px' }
            ],
            drawCallback: function() {
                clearCvReminderSelection();
            }
        });

    $(document).on('change', '.js-cv-reminder-row', function() {
        const nik = String($(this).val());
        if (this.checked) selectedCvReminderNiks.add(nik);
        else selectedCvReminderNiks.delete(nik);
        updateCvReminderSelectionUi();
    });

    $('#cvReminderSelectPage').on('change', function() {
        const checked = this.checked;
        $('.js-cv-reminder-row').each(function() {
            $(this).prop('checked', checked);
            if (checked) selectedCvReminderNiks.add(String($(this).val()));
            else selectedCvReminderNiks.delete(String($(this).val()));
        });
        updateCvReminderSelectionUi();
    });

    function cvReminderRequestId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(character) {
            const random = Math.random() * 16 | 0;
            return (character === 'x' ? random : (random & 0x3 | 0x8)).toString(16);
        });
    }

    function cvReminderFilterPayload() {
        return {
            area: selectedCvAreaCodes(),
            departemen: $('#cv_filter_departemen').val(),
            divisi: $('#cv_filter_divisi').val(),
            posisi: $('#cv_filter_posisi').val() || [],
            jabatan: selectedCvJobTitles(),
            cv_skill_category: $('#cv_filter_skill_category').val(),
            status_resign: $('#cv_filter_resign').val(),
            cv_reminder: 'needs_reminder',
            cv_progress_status: $('#cv_filter_progress_status').val(),
            cv_progress_step: $('#cv_filter_progress_step').val() || [],
            cv_review_status: $('#cv_filter_review_status').val(),
            search: cvCompareTable.search()
        };
    }

    function renderCvReminderBatchStatus(data) {
        const terminal = ['completed', 'partial_failed', 'failed'].includes(data.status);
        const statusText = terminal ? 'Proses selesai' : 'Reminder sedang diproses';
        $('#cvReminderBatchStatus')
            .removeClass('d-none alert-danger alert-warning alert-success')
            .addClass(data.failed_count > 0 ? 'alert-warning' : (terminal ? 'alert-success' : 'alert-info'))
            .html(`<strong>${statusText} (${data.progress}%)</strong><br>` +
                `${data.processed_count} dari ${data.total_count} diproses — ` +
                `${data.sent_count} terkirim, ${data.skipped_count} dilewati, ${data.failed_count} gagal.`);
        return terminal;
    }

    function pollCvReminderBatch(statusUrl, attempt = 0) {
        if (!statusUrl || attempt >= 120) return;
        $.get(statusUrl).done(function(response) {
            if (!renderCvReminderBatchStatus(response.data || {})) {
                window.setTimeout(function() { pollCvReminderBatch(statusUrl, attempt + 1); }, 5000);
            } else {
                cvCompareTable.ajax.reload(null, false);
            }
        }).fail(function(xhr) {
            showCvCompareAjaxError(xhr, 'Status pengiriman reminder gagal diperbarui.');
        });
    }

    function queueCvReminder(selectionMode) {
        const selectedNiks = Array.from(selectedCvReminderNiks);
        if (selectionMode === 'selected' && !selectedNiks.length) return;

        const targetLabel = selectionMode === 'selected'
            ? `${selectedNiks.length} karyawan terpilih`
            : 'semua karyawan Perlu Diingatkan pada hasil filter';

        window.CvMakerDialog.fire({
            icon: 'question',
            title: 'Kirim reminder CV?',
            text: `Sistem akan memvalidasi ${targetLabel}, email, scope akses, dan cooldown sebelum memasukkan email ke antrean.`,
            showCancelButton: true,
            confirmButtonText: 'Masukkan ke Antrean',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (!result.isConfirmed) return;

            const buttons = $('#btnCvReminderSelected, #btnCvReminderFiltered');
            const payload = Object.assign(cvReminderFilterPayload(), {
                _token: $('meta[name="csrf-token"]').attr('content'),
                idempotency_key: cvReminderRequestId(),
                selection_mode: selectionMode,
                employee_niks: selectedNiks
            });
            buttons.prop('disabled', true);

            $.ajax({
                url: "{{ route('cv-maker-compare.reminders.store') }}",
                method: 'POST',
                dataType: 'json',
                data: payload,
                success: function(response) {
                    clearCvReminderSelection();
                    renderCvReminderBatchStatus(response.data || {});
                    window.CvMakerDialog.fire({
                        icon: 'success',
                        title: 'Antrean dibuat',
                        text: response.message,
                        confirmButtonText: 'OK'
                    });
                    pollCvReminderBatch(response.status_url);
                },
                error: function(xhr) {
                    showCvCompareAjaxError(xhr, 'Bulk reminder gagal dibuat.');
                },
                complete: function() {
                    buttons.prop('disabled', false);
                    updateCvReminderSelectionUi();
                }
            });
        });
    }

    $('#btnCvReminderSelected').on('click', function() { queueCvReminder('selected'); });
    $('#btnCvReminderFiltered').on('click', function() { queueCvReminder('filtered'); });

    syncCvAreaFilter();
    syncCvJobTitleFilter();
    syncCvProgressStepFilter();
    resetCvDepartmentAndDivision(true);

    $('.cv-progress-step-check').on('change', function() {
        syncCvProgressStepFilter();
        $('#cv_filter_progress_step').trigger('change');
    });

    $('#btnSelectAllCvProgressSteps').on('click', function() {
        $('.cv-progress-step-check').prop('checked', true);
        syncCvProgressStepFilter();
        $('#cv_filter_progress_step').trigger('change');
    });

    $('#btnClearCvProgressSteps').on('click', function() {
        $('.cv-progress-step-check').prop('checked', false);
        syncCvProgressStepFilter();
        $('#cv_filter_progress_step').trigger('change');
    });

    $('.cv-filter-area-check').on('change', function() {
        syncCvAreaFilter();
        $('#cv_filter_area').trigger('change');
    });

    $('#btnClearCvAreaFilter').on('click', function() {
        $('.cv-filter-area-check').prop('checked', false);
        syncCvAreaFilter();
        $('#cv_filter_area').trigger('change');
    });

    $('.cv-filter-job-title-check').on('change', function() {
        syncCvJobTitleFilter();
        $('#cv_filter_jabatan').trigger('change');
    });

    $('#btnClearCvJobTitleFilter').on('click', function() {
        $('.cv-filter-job-title-check').prop('checked', false);
        syncCvJobTitleFilter();
        $('#cv_filter_jabatan').trigger('change');
    });

    $('#cv_filter_jabatan').on('change', function() {
        cvCompareTable.draw();
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

    $('#cv_filter_divisi, #cv_filter_posisi, #cv_filter_skill_category, #cv_filter_resign, #cv_filter_reminder, #cv_filter_progress_status, #cv_filter_progress_step, #cv_filter_review_status').on('change', function() {
        cvCompareTable.draw();
    });

    $('#btnResetCvCompareFilter').on('click', function() {
        $('.cv-filter-area-check').prop('checked', false);
        syncCvAreaFilter();
        resetCvDepartmentAndDivision(true);
        $('#cv_filter_posisi').val(null).trigger('change.select2');
        $('.cv-filter-job-title-check').prop('checked', false);
        syncCvJobTitleFilter();
        $('#cv_filter_skill_category').val('');
        $('#cv_filter_resign').val('AKTIF');
        $('#cv_filter_reminder').val('');
        $('#cv_filter_progress_status').val('');
        $('.cv-progress-step-check').prop('checked', false);
        syncCvProgressStepFilter();
        $('#cv_filter_review_status').val('');
        cvCompareTable.draw();
    });
</script>
@endpush
