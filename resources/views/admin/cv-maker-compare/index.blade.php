@extends('layouts.app')

@section('title', __('navigation.cv_maker_compare'))

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/admin-cv-maker-compare.css') }}">
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner ui-page cv-compare-page" data-cv-workspace>
        <div class="ui-page-header cv-compare-header">
            <div class="ui-page-heading">
                <div class="ui-page-icon" aria-hidden="true">
                    <i class="fas fa-not-equal"></i>
                </div>
                <div>
                    <h4 class="ui-page-title">{{ __('navigation.cv_maker_compare') }}</h4>
                    <p class="ui-page-subtitle">{{ __('Review perbedaan data CV Maker dan master HRIS sesuai scope akses karyawan.') }}</p>
                </div>
            </div>
        </div>

        @if(!$integrationAvailable)
        <div class="alert ui-alert ui-alert--warning cv-compare-alert mb-3">
            <i class="fas fa-exclamation-triangle me-2"></i>
            {{ __('Koneksi CV Maker belum dikonfigurasi. Set env') }} <code>CV_MAKER_DB_*</code> {{ __('dan') }} <code>CV_MAKER_NIK_HASH_KEY</code>.
        </div>
        @endif

        @if(!auth()->user()->canAccessAllEmployees())
        <div class="alert ui-alert cv-compare-alert mb-3">
            <i class="fas fa-lock me-2"></i>
            Data dibatasi sesuai scope role Anda: {{ auth()->user()->role->scope_label ?? 'Akun sendiri' }}.
        </div>
        @endif

        @include('admin.cv-maker-compare.partials.workspace-nav', ['workspaceTabs' => ['employees' => ['users', 'Daftar karyawan', 'Cari dan review data'], 'downloads' => ['file-pdf', 'Unduhan PDF', 'Batch dan riwayat unduhan']]])

        <section class="ui-panel cv-compare-panel" aria-labelledby="cvMakerCompareTableTitle">
            <div class="ui-panel__header">
                <div>
                    <h5 class="ui-panel__title" id="cvMakerCompareTableTitle">{{ __('Pencarian & filter') }}</h5>
                    <p class="ui-panel__meta">{{ __('Filter berlaku untuk daftar karyawan, export Excel, email reminder, dan batch PDF.') }}</p>
                </div>
                <button type="button" class="btn btn-sm btn-light border ui-btn-icon" id="btnResetCvCompareFilter">
                    <i class="fas fa-undo"></i>
                    {{ __('Reset Filter') }}
                </button>
            </div>

            <div class="ui-panel__body">
                <details class="cv-compare-filter-panel cv-filter-disclosure">
                    <summary><span><i class="fas fa-sliders-h me-2" aria-hidden="true"></i>{{ __('Sesuaikan filter') }} <span class="cv-filter-count" data-cv-filter-count></span></span><span class="cv-filter-disclosure__hint">{{ __('Buka / tutup') }}</span></summary>
                    <div class="cv-filter-disclosure__body">
                    <div class="row g-3 align-items-end">
                        <div class="col-12">
                            <div class="small fw-semibold text-uppercase text-muted">{{ __('Filter HRIS') }}</div>
                        </div>

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cvCompanyFilterDropdown">{{ __('Perusahaan') }}</label>
                            <div class="company-filter">
                                <button class="btn btn-light border dropdown-toggle company-filter__toggle" type="button" id="cvCompanyFilterDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                    <span id="cvFilterAreaLabel">{{ __('Semua perusahaan') }}</span>
                                </button>
                                <div class="dropdown-menu company-filter__menu" aria-labelledby="cvCompanyFilterDropdown">
                                    <div class="company-filter__menu-header">
                                        <span>{{ __('Pilih perusahaan') }}</span>
                                        <button type="button" class="btn btn-link btn-sm p-0" id="btnClearCvAreaFilter">{{ __('Kosongkan') }}</button>
                                    </div>
                                    @forelse ($areas as $area)
                                    <label class="company-filter__option">
                                        <input type="checkbox" class="form-check-input cv-filter-area-check" value="{{ $area->kode_perusahaan }}">
                                        <span>{{ $area->kode_perusahaan }}</span>
                                    </label>
                                    @empty
                                    <div class="company-filter__empty">{{ __('Tidak ada perusahaan tersedia.') }}</div>
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
                            <label class="form-label" for="cv_filter_departemen">{{ __('Departemen') }}</label>
                            <select id="cv_filter_departemen" class="form-select">
                                <option value="">{{ __('Semua Departemen') }}</option>
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
                            <label class="form-label" for="cv_filter_divisi">{{ __('Divisi') }}</label>
                            <select id="cv_filter_divisi" class="form-select">
                                <option value="">{{ __('Semua Divisi') }}</option>
                                @foreach ($divisis as $division)
                                <option value="{{ $division->id }}">{{ $division->nama_divisi }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cv_filter_posisi">{{ __('Posisi HRIS') }}</label>
                            <select id="cv_filter_posisi" class="form-select cv-position-filter" multiple data-placeholder="{{ __('Cari dan pilih posisi HRIS') }}"></select>
                        </div>

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cvHrisJobTitleFilterDropdown">{{ __('Jabatan HRIS') }}</label>
                            <div class="company-filter">
                                <button class="btn btn-light border dropdown-toggle company-filter__toggle" type="button" id="cvHrisJobTitleFilterDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                    <span id="cvFilterHrisJobTitleLabel">{{ __('Semua jabatan HRIS') }}</span>
                                </button>
                                <div class="dropdown-menu company-filter__menu" aria-labelledby="cvHrisJobTitleFilterDropdown">
                                    <div class="company-filter__menu-header">
                                        <span>{{ __('Pilih awalan posisi HRIS') }}</span>
                                        <button type="button" class="btn btn-link btn-sm p-0" id="btnClearCvHrisJobTitleFilter">{{ __('Kosongkan') }}</button>
                                    </div>
                                    @forelse ($hrisJobTitles as $value => $label)
                                    <label class="company-filter__option">
                                        <input type="checkbox" class="form-check-input cv-filter-hris-job-title-check" value="{{ $value }}">
                                        <span>{{ $label }}</span>
                                    </label>
                                    @empty
                                    <div class="company-filter__empty">{{ __('Tidak ada jabatan HRIS tersedia.') }}</div>
                                    @endforelse
                                </div>
                            </div>
                            <select id="cv_filter_jabatan_hris" class="d-none" multiple aria-hidden="true">
                                @foreach ($hrisJobTitles as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cv_filter_resign">{{ __('Status') }}</label>
                            <select id="cv_filter_resign" class="form-select">
                                <option value="">{{ __('Semua Status') }}</option>
                                <option value="AKTIF" selected>{{ __('Aktif') }}</option>
                                <option value="RESIGN SESUAI PROSEDUR">{{ __('Resign Sesuai Prosedur') }}</option>
                                <option value="RESIGN TIDAK SESUAI PROSEDUR">{{ __('Resign Tidak Sesuai Prosedur') }}</option>
                                <option value="RESIGN TIDAK SESUAI PROSEDUR-PENGAJUAN">{{ __('Resign Tidak Sesuai Prosedur-Pengajuan') }}</option>
                                <option value="RESIGN TIDAK SESUAI PROSEDUR-KABUR">{{ __('Resign Tidak Sesuai Prosedur-Kabur') }}</option>
                                <option value="RESIGN TIDAK SESUAI PROSEDUR-PAYROLL">{{ __('Resign Tidak Sesuai Prosedur-Payroll') }}</option>
                                <option value="PB RESIGN">{{ __('PB Resign') }}</option>
                                <option value="PUTUS KONTRAK">{{ __('Putus Kontrak') }}</option>
                                <option value="PHK">{{ __('PHK') }}</option>
                                <option value="PHK PENSIUN">{{ __('PHK Pensiun') }}</option>
                                <option value="PHK PENSIUN DINI">{{ __('PHK Pensiun Dini') }}</option>
                                <option value="PHK PIDANA">{{ __('PHK Pidana') }}</option>
                                <option value="PHK MENINGGAL DUNIA">{{ __('PHK Meninggal Dunia') }}</option>
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cv_filter_hris_skill_category">{{ __('Kategori Skill HRIS') }}</label>
                            <select id="cv_filter_hris_skill_category" class="form-select">
                                <option value="">{{ __('Semua Kategori') }}</option>
                                @foreach ($skillCategories as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cv_filter_hris_managerial_category">{{ __('Kategori Manajerial HRIS') }}</label>
                            <select id="cv_filter_hris_managerial_category" class="form-select">
                                <option value="">{{ __('Semua Kategori') }}</option>
                                @foreach ($managerialCategories as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <div class="small fw-semibold text-uppercase text-muted border-top pt-3">{{ __('Filter CV Maker') }}</div>
                        </div>

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cvJobTitleFilterDropdown">{{ __('Jabatan CV Maker') }}</label>
                            <div class="company-filter">
                                <button class="btn btn-light border dropdown-toggle company-filter__toggle" type="button" id="cvJobTitleFilterDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                    <span id="cvFilterJobTitleLabel">{{ __('Semua jabatan') }}</span>
                                </button>
                                <div class="dropdown-menu company-filter__menu" aria-labelledby="cvJobTitleFilterDropdown">
                                    <div class="company-filter__menu-header">
                                        <span>{{ __('Pilih jabatan dari CV Maker') }}</span>
                                        <button type="button" class="btn btn-link btn-sm p-0" id="btnClearCvJobTitleFilter">{{ __('Kosongkan') }}</button>
                                    </div>
                                    @forelse ($jobTitles as $jobTitle)
                                    <label class="company-filter__option">
                                        <input type="checkbox" class="form-check-input cv-filter-job-title-check" value="{{ $jobTitle }}">
                                        <span>{{ $jobTitle }}</span>
                                    </label>
                                    @empty
                                    <div class="company-filter__empty">{{ __('Tidak ada jabatan tersedia.') }}</div>
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
                            <label class="form-label" for="cv_filter_skill_category">{{ __('Kategori Skill CV Maker') }}</label>
                            <select id="cv_filter_skill_category" class="form-select">
                                <option value="">{{ __('Semua Kategori') }}</option>
                                @foreach ($skillCategories as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cv_filter_managerial_category">{{ __('Kategori Manajerial CV Maker') }}</label>
                            <select id="cv_filter_managerial_category" class="form-select">
                                <option value="">{{ __('Semua Kategori') }}</option>
                                @foreach ($managerialCategories as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cv_filter_reminder">{{ __('Reminder CV') }}</label>
                            <select id="cv_filter_reminder" class="form-select">
                                <option value="">{{ __('Semua Reminder') }}</option>
                                <option value="needs_reminder">{{ __('Perlu Diingatkan') }}</option>
                                <option value="not_needed">{{ __('Tidak Perlu Diingatkan') }}</option>
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cv_filter_progress_status">{{ __('Status Progress') }}</label>
                            <select id="cv_filter_progress_status" class="form-select">
                                <option value="">{{ __('Semua Progress') }}</option>
                                <option value="not_complete">{{ __('Belum Input / Belum Lengkap (termasuk belum diketahui)') }}</option>
                                <option value="not_synced">{{ __('Snapshot Belum Tersedia') }}</option>
                                <option value="no_account">{{ __('Belum Memiliki Akun CV') }}</option>
                                <option value="no_profile">{{ __('Profil CV Belum Dibuat') }}</option>
                                <option value="in_progress">{{ __('Dalam Progress') }}</option>
                                <option value="complete">{{ __('Sudah Lengkap') }}</option>
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cvProgressStepDropdown">{{ __('Tahap Progress') }}</label>
                            <div class="company-filter">
                                <button class="btn btn-light border dropdown-toggle company-filter__toggle" type="button" id="cvProgressStepDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                    <span id="cvProgressStepLabel">{{ __('Semua tahap') }}</span>
                                </button>
                                <div class="dropdown-menu company-filter__menu" aria-labelledby="cvProgressStepDropdown">
                                    <div class="company-filter__menu-header">
                                        <span>{{ __('Pilih tahap progress') }}</span>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-link btn-sm p-0" id="btnSelectAllCvProgressSteps">{{ __('Pilih semua') }}</button>
                                            <button type="button" class="btn btn-link btn-sm p-0" id="btnClearCvProgressSteps">{{ __('Kosongkan') }}</button>
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
                                <option value="1">{{ __('1 - Data Pribadi') }}</option>
                                <option value="2">{{ __('2 - Ringkasan Profil') }}</option>
                                <option value="3">{{ __('3 - Pendidikan') }}</option>
                                <option value="4">{{ __('4 - Pengalaman') }}</option>
                                <option value="5">{{ __('5 - Keahlian') }}</option>
                                <option value="6">{{ __('6 - Sertifikasi') }}</option>
                                <option value="7">{{ __('7 - Tambahan') }}</option>
                                <option value="8">{{ __('8 - Dokumen') }}</option>
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cv_filter_review_status">{{ __('Status Pemeriksaan') }}</label>
                            <select id="cv_filter_review_status" class="form-select">
                                <option value="">{{ __('Semua Pemeriksaan') }}</option>
                                <option value="unreviewed">{{ __('Belum Diperiksa') }}</option>
                                <option value="in_review">{{ __('Sedang Diperiksa') }}</option>
                                <option value="needs_employee_confirmation">{{ __('Perlu Konfirmasi Karyawan') }}</option>
                                <option value="completed">{{ __('Selesai Diperiksa') }}</option>
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-6 ui-field">
                            <label class="form-label" for="cv_filter_pdf_status">{{ __('Status Download PDF') }}</label>
                            <select id="cv_filter_pdf_status" class="form-select">
                                <option value="">{{ __('Semua Status PDF') }}</option>
                                <option value="not_downloaded">{{ __('Belum Diunduh (Tidak Dalam Batch)') }}</option>
                                <option value="processing">{{ __('Dalam Batch / Siap Diunduh') }}</option>
                                <option value="downloaded">{{ __('Sudah Diunduh') }}</option>
                            </select>
                        </div>
                    </div>
                    </div>
                </details>

            </div>
        </section>

        <section id="cv-workspace-employees" data-cv-pane="employees" class="ui-panel cv-workspace-panel" aria-labelledby="cv-tab-employees">
            <div class="ui-panel__header"><div><h5 class="ui-panel__title">{{ __('Daftar karyawan') }}</h5><p class="ui-panel__meta">{{ __('Cari karyawan, periksa progres, lalu buka Detail untuk meninjau CV.') }}</p></div></div>
            <div class="ui-panel__body">
                <div class="cv-compare-export-toolbar mt-3 mb-3">
                    <button type="button" class="btn btn-outline-primary ui-btn-icon" id="btnCvIncompleteSupervisors">
                        <i class="fas fa-filter"></i> {{ __('Pengawas ke Atas — Belum Lengkap') }}
                    </button>
                    <button type="button" class="btn btn-success ui-btn-icon" id="btnCvExport">
                        <i class="fas fa-file-excel"></i> {{ __('Export Excel Hasil Filter') }}
                    </button>
                </div>

                @if(!auth()->user()->hasRole('Audit CV'))
                <details class="cv-reminder-disclosure"><summary><i class="fas fa-envelope me-2" aria-hidden="true"></i>{{ __('Email pengingat') }} <span class="small text-muted">{{ __('— pilih penerima dari tabel') }}</span></summary>
                <div class="d-flex flex-wrap gap-2 align-items-center mt-3 mb-2">
                    <button type="button" class="btn btn-sm btn-primary ui-btn-icon" id="btnCvReminderSelected" disabled>
                        <i class="fas fa-envelope"></i>
                        {{ __('Email Pilihan (') }}<span id="cvReminderSelectedCount">0</span>)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary ui-btn-icon" id="btnCvReminderFiltered">
                        <i class="fas fa-mail-bulk"></i>
                        {{ __('Email Semua Hasil Filter') }}
                    </button>
                    <span class="small text-muted">{{ __('Hanya karyawan berstatus Perlu Diingatkan yang akan diproses. Cooldown pengiriman tetap diperiksa oleh server.') }}</span>
                </div>

                </details>
                <div class="alert ui-alert d-none mb-3" id="cvReminderBatchStatus" role="status" aria-live="polite"></div>
                @endif



                <div class="cv-compare-table-section ui-table-wrap">
                    <table id="cvMakerCompareTable" class="table table-bordered table-striped table-sm small text-sm nowrap align-middle ui-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 42px"><input type="checkbox" class="form-check-input" id="cvReminderSelectPage" aria-label="{{ __('Pilih semua reminder pada halaman ini') }}"></th>
                                <th>NIK</th>
                                <th>{{ __('Karyawan') }}</th>
                                <th>CV Maker</th>
                                <th>{{ __('Hasil') }}</th>
                                <th>{{ __('Download PDF') }}</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </section>
        @if(\App\Services\CvMaker\CvMakerPdfExportService::canAccess(auth()->user()))
        <div id="cv-workspace-downloads" data-cv-pane="downloads" aria-labelledby="cv-tab-downloads">
            @include('admin.cv-maker-compare.partials.pdf-panel')
        </div>
        @endif
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/js/admin-cv-maker-workspace.js') }}"></script>
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

    function selectedCvHrisJobTitles() {
        return $('.cv-filter-hris-job-title-check:checked').map(function() {
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

    function syncCvHrisJobTitleFilter() {
        const jobTitles = selectedCvHrisJobTitles();
        const label = jobTitles.length
            ? (jobTitles.length === 1 ? jobTitles[0] : `${jobTitles.length} jabatan dipilih`)
            : 'Semua jabatan HRIS';

        $('#cv_filter_jabatan_hris').val(jobTitles);
        $('#cvFilterHrisJobTitleLabel').text(label);
        $('#cvHrisJobTitleFilterDropdown').toggleClass('is-active', jobTitles.length > 0);
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
            message = @json(__('Sesi login berakhir. Silakan login ulang.'));
        }

        if (xhr.status === 403) {
            message = @json(__('Anda tidak memiliki akses untuk membuka data compare.'));
        }

        if (xhr.status === 0) {
            message = @json(__('Koneksi bermasalah atau request diblokir. Silakan cek jaringan Anda.'));
        }

        window.CvMakerDialog.fire({
            icon: 'error',
            title: @json(__('Gagal')),
            text: message,
            confirmButtonText: @json(__('OK'))
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
                processing: @json(__('Memuat data compare...')),
                search: @json(__('Cari:')),
                lengthMenu: 'Tampilkan _MENU_ data',
                info: @json(__('Menampilkan _START_ sampai _END_ dari _TOTAL_ data')),
                infoEmpty: @json(__('Tidak ada data')),
                infoFiltered: @json(__('(difilter dari _MAX_ total data)')),
                zeroRecords: @json(__('Data tidak ditemukan')),
                emptyTable: @json(__('Belum ada data compare')),
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
                    data.jabatan_hris = selectedCvHrisJobTitles();
                    data.jabatan = selectedCvJobTitles();
                    data.hris_skill_category = $('#cv_filter_hris_skill_category').val();
                    data.hris_managerial_category = $('#cv_filter_hris_managerial_category').val();
                    data.cv_skill_category = $('#cv_filter_skill_category').val();
                    data.cv_managerial_category = $('#cv_filter_managerial_category').val();
                    data.status_resign = $('#cv_filter_resign').val();
                    data.cv_reminder = $('#cv_filter_reminder').val();
                    data.cv_progress_status = $('#cv_filter_progress_status').val();
                    data.cv_progress_step = $('#cv_filter_progress_step').val();
                    data.cv_review_status = $('#cv_filter_review_status').val();
                    data.pdf_status = $('#cv_filter_pdf_status').val();
                },
                error: function(xhr) {
                    showCvCompareAjaxError(xhr, 'Data compare gagal dimuat.');
                }
            },
            columns: [
                { data: 'select', visible: @json(!auth()->user()->hasRole('Audit CV')), orderable: false, searchable: false, width: '42px', className: 'text-center' },
                { data: 'nik', width: '90px' },
                { data: 'employee', orderable: true },
                { data: 'cv_status', orderable: false, searchable: false, width: '120px' },
                { data: 'result', orderable: false, searchable: false, width: '190px' },
                { data: 'pdf', orderable: false, searchable: false, width: '180px' }
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
            jabatan_hris: selectedCvHrisJobTitles(),
            jabatan: selectedCvJobTitles(),
            hris_skill_category: $('#cv_filter_hris_skill_category').val(),
            hris_managerial_category: $('#cv_filter_hris_managerial_category').val(),
            cv_skill_category: $('#cv_filter_skill_category').val(),
            cv_managerial_category: $('#cv_filter_managerial_category').val(),
            status_resign: $('#cv_filter_resign').val(),
            cv_reminder: 'needs_reminder',
            cv_progress_status: $('#cv_filter_progress_status').val(),
            cv_progress_step: $('#cv_filter_progress_step').val() || [],
            cv_review_status: $('#cv_filter_review_status').val(),
            pdf_status: $('#cv_filter_pdf_status').val(),
            search: cvCompareTable.search()
        };
    }

    $('#btnCvIncompleteSupervisors').on('click', function() {
        $('#btnResetCvCompareFilter').trigger('click');
        $('.cv-filter-hris-job-title-check').prop('checked', true);
        syncCvHrisJobTitleFilter();
        $('#cv_filter_progress_status').val('not_complete');
        cvCompareTable.search('').draw();
    });

    $('#btnCvExport').on('click', async function() {
        const button = $(this);
        if (button.prop('disabled')) return;
        const original = button.html();
        const filters = cvReminderFilterPayload();
        filters.cv_reminder = $('#cv_filter_reminder').val();
        button.prop('disabled', true).text(@json(__('Membuat Excel...')));
        try {
            const response = await fetch("{{ route('cv-maker-compare.export') }}?" + $.param(filters), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            if (!response.ok) {
                const body = await response.json().catch(() => ({}));
                showCvCompareAjaxError({ status: response.status, responseJSON: body }, 'Export gagal. Silakan coba lagi.');
                return;
            }
            if (!(response.headers.get('Content-Type') || '').includes('spreadsheetml')) {
                showCvCompareAjaxError({ status: 419 }, 'Sesi berakhir.');
                return;
            }
            const url = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = url;
            link.download = 'progress-cv-maker-' + new Date().toISOString().slice(0, 10) + '.xlsx';
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
            window.CvMakerDialog.fire({ icon: 'success', title: @json(__('File siap')), text: @json(__('File Excel berhasil dibuat dan unduhan dimulai.')) });
        } catch (error) {
            showCvCompareAjaxError({ status: 0 }, 'Export gagal diunduh.');
        } finally {
            button.prop('disabled', false).html(original);
        }
    });

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
            title: @json(__('Kirim reminder CV?')),
            text: `Sistem akan memvalidasi ${targetLabel}, email, scope akses, dan cooldown sebelum memasukkan email ke antrean.`,
            showCancelButton: true,
            confirmButtonText: @json(__('Masukkan ke Antrean')),
            cancelButtonText: @json(__('Batal'))
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
                        title: @json(__('Antrean dibuat')),
                        text: response.message,
                        confirmButtonText: @json(__('OK'))
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
    syncCvHrisJobTitleFilter();
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

    $('.cv-filter-hris-job-title-check').on('change', function() {
        syncCvHrisJobTitleFilter();
        $('#cv_filter_jabatan_hris').trigger('change');
    });

    $('#btnClearCvHrisJobTitleFilter').on('click', function() {
        $('.cv-filter-hris-job-title-check').prop('checked', false);
        syncCvHrisJobTitleFilter();
        $('#cv_filter_jabatan_hris').trigger('change');
    });

    $('#cv_filter_jabatan_hris').on('change', function() {
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

    $('#cv_filter_pdf_status, #cv_filter_divisi, #cv_filter_posisi, #cv_filter_hris_skill_category, #cv_filter_hris_managerial_category, #cv_filter_skill_category, #cv_filter_managerial_category, #cv_filter_resign, #cv_filter_reminder, #cv_filter_progress_status, #cv_filter_progress_step, #cv_filter_review_status').on('change', function() {
        cvCompareTable.draw();
    });

    $('#btnResetCvCompareFilter').on('click', function() {
        $('.cv-filter-area-check').prop('checked', false);
        syncCvAreaFilter();
        resetCvDepartmentAndDivision(true);
        $('#cv_filter_posisi').val(null).trigger('change.select2');
        $('.cv-filter-hris-job-title-check').prop('checked', false);
        syncCvHrisJobTitleFilter();
        $('.cv-filter-job-title-check').prop('checked', false);
        syncCvJobTitleFilter();
        $('#cv_filter_hris_skill_category').val('');
        $('#cv_filter_hris_managerial_category').val('');
        $('#cv_filter_skill_category').val('');
        $('#cv_filter_managerial_category').val('');
        $('#cv_filter_resign').val('AKTIF');
        $('#cv_filter_reminder').val('');
        $('#cv_filter_progress_status').val('');
        $('.cv-progress-step-check').prop('checked', false);
        syncCvProgressStepFilter();
        $('#cv_filter_review_status').val('');
        $('#cv_filter_pdf_status').val('');
        $('#cvPdfAllowDownloaded').prop('checked', false);
        cvCompareTable.draw();
    });
</script>
<script src="{{ versioned_asset('assets/js/admin-cv-maker-pdf.js') }}"></script>
@endpush
