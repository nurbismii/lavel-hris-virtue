@extends('layouts.app')

@push('styles')
<style>
    .attendance-select {
        min-width: 85px !important;
        font-size: 12px;
        padding: 2px 6px;
    }

    .attendance-select option {
        color: #000;
    }

    .dataTables_filter {
        position: sticky;
        top: 0;
        background: white;
        z-index: 1050;
        padding: 10px 0;
    }

    .dataTables_paginate {
        position: sticky;
        bottom: 0;
        background: white;
        z-index: 1050;
        padding: 10px 0;
    }

    .bulk-upload-feedback {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #f8fafc;
        padding: 14px 16px;
    }

    .bulk-upload-feedback__title {
        font-size: 0.92rem;
        font-weight: 600;
        color: #0f172a;
    }

    .bulk-upload-feedback__text {
        font-size: 0.84rem;
        color: #475569;
    }

    .bulk-upload-feedback__error {
        font-size: 0.84rem;
        color: #b91c1c;
    }

    .attendance-date-header {
        min-width: 78px;
        vertical-align: top;
    }

    .attendance-date-header__date {
        font-size: 0.95rem;
        font-weight: 700;
        color: #0f172a;
    }

    .attendance-date-header__day {
        font-size: 11px;
        color: #64748b;
    }

    .attendance-date-header__holiday {
        margin-top: 4px;
        font-size: 10px;
        line-height: 1.3;
        color: #b91c1c;
        font-weight: 600;
        white-space: normal;
    }

    .attendance-date-header--holiday {
        background: #fef2f2 !important;
    }

    .attendance-date-header--holiday .attendance-date-header__date,
    .attendance-date-header--holiday .attendance-date-header__day {
        color: #b91c1c;
    }

    .attendance-date-header--sunday .attendance-date-header__date,
    .attendance-date-header--sunday .attendance-date-header__day {
        color: #dc2626;
    }

    .holiday-manager-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #f8fafc;
        padding: 14px 16px;
    }

    .holiday-manager-table td,
    .holiday-manager-table th {
        vertical-align: middle;
    }

    .schedule-legend {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #f8fafc;
        padding: 14px 16px;
    }

    .schedule-legend__items {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .schedule-legend__item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: #334155;
    }

    .schedule-legend__swatch {
        width: 14px;
        height: 14px;
        border-radius: 4px;
        border: 1px solid rgba(15, 23, 42, 0.12);
    }

    .schedule-cell--auto-off {
        background: #f1f5f9;
    }

    .schedule-cell--manual-off {
        background: #fff7ed;
    }

    .schedule-cell--manual-hadir {
        background: #eff6ff;
    }

    .schedule-cell__meta {
        display: block;
        margin-top: 6px;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.4px;
    }

    .schedule-cell__meta--auto {
        color: #64748b;
    }

    .schedule-cell__meta--manual {
        color: #0f766e;
    }
</style>

<link rel="stylesheet" href="https://cdn.datatables.net/fixedheader/3.4.0/css/fixedHeader.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css">
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold">
                    <i class="fas fa-cog text-primary me-2"></i>
                    Setting Hari Off
                </h4>

                <small class="text-muted d-block">
                    Generator pola kerja otomatis aktif pada periode ini.
                    Checkbox tetap bisa dipakai untuk override manual per tanggal.
                    (Cut Off {{ formatDateIndonesia($start) }} - {{ formatDateIndonesia($end) }})
                </small>
                <small class="text-muted d-block">Centang = OFF, tidak dicentang = HADIR. Jika kembali sama dengan pola otomatis, override manual akan dihapus.</small>
            </div>

            <div class="ms-md-auto pt-3 pt-md-0">
                <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                    @if($canManageNationalHolidays)
                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalNationalHoliday">
                        Tanggal Merah Nasional
                    </button>
                    @endif
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalBulkFaceReference">
                        Bulk Foto Referensi Presensi
                    </button>
                </div>
            </div>
        </div>

        <form method="GET" class="row g-2 mb-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Periode</label>
                <input type="month" name="periode" value="{{ $periode }}" class="form-control">
            </div>

            @if($isDepartmentReadonly)
            <div class="col-md-3">
                <label class="form-label">Departemen</label>
                <input type="text" class="form-control" value="{{ optional($departemen)->departemen ?? '-' }}" readonly>
            </div>
            @else
            <div class="col-md-3">
                <label class="form-label">Departemen</label>
                <select id="filter_departemen" name="departemen" class="form-select form-control">
                    <option value="">Pilih Departemen</option>
                    @php
                    $groupedDepts = [];
                    foreach ($departemens as $dept) {
                    $groupedDepts[optional($dept->perusahaan)->nama_perusahaan ?? 'Lainnya'][] = $dept;
                    }
                    @endphp

                    @foreach($groupedDepts as $perusahaan => $deptItems)
                    <optgroup label="{{ $perusahaan }}">
                        @foreach($deptItems as $dept)
                        <option value="{{ $dept->id }}" {{ (string) $selectedDepartemenId === (string) $dept->id ? 'selected' : '' }}>
                            {{ $dept->departemen }}
                        </option>
                        @endforeach
                    </optgroup>
                    @endforeach
                </select>
            </div>
            @endif

            @if($isDivisionReadonly)
            <div class="col-md-4">
                <label class="form-label">Divisi</label>
                <input type="text" class="form-control" value="{{ optional($divisis->first())->nama_divisi ?? '-' }}" readonly>
                <input type="hidden" id="filter_divisi" name="divisi" value="{{ $selectedDivisiId }}">
            </div>
            @else
            <div class="col-md-4">
                <label class="form-label">Divisi</label>
                <select id="filter_divisi" name="divisi" class="form-select form-control" {{ !$selectedDepartemenId ? 'disabled' : '' }}>
                    <option value="">Semua Divisi</option>
                    @foreach ($divisis as $v)
                    <option value="{{ $v->id }}" {{ (string) $selectedDivisiId === (string) $v->id ? 'selected' : '' }}>
                        {{ $v->nama_divisi }}
                    </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="col-md-2 d-grid">
                <button class="btn btn-primary">
                    Tampilkan
                </button>
            </div>
        </form>

        @if($isDivisionScoped && !$isDivisionReadonly)
        <div class="alert alert-light border small">
            Akun Admin Divisi ini memiliki akses ke beberapa divisi. Pilih divisi yang ingin ditampilkan pada periode ini.
        </div>
        @endif

        @if(!$selectedDepartemenId)
        <div class="alert alert-info">
            Pilih departemen terlebih dahulu untuk menampilkan setting hari off.
        </div>
        @endif

        @php
            $currentPeriodHolidays = collect($dates)->map(function ($date) use ($nationalHolidaysByDate) {
                return $nationalHolidaysByDate->get($date->toDateString());
            })->filter();
        @endphp

        @if($currentPeriodHolidays->isNotEmpty())
        <div class="alert alert-danger border small">
            <div class="fw-semibold mb-1">Tanggal merah nasional pada periode ini</div>
            {{ $currentPeriodHolidays->map(function ($holiday) {
                return formatDateIndonesia($holiday->holiday_date) . ' - ' . $holiday->holiday_name;
            })->implode(' | ') }}
        </div>
        @endif

        <div class="schedule-legend mb-3">
            <div class="fw-semibold mb-2">Mode jadwal</div>
            <div class="schedule-legend__items">
                <div class="schedule-legend__item">
                    <span class="schedule-legend__swatch schedule-cell--auto-off"></span>
                    <span>OFF otomatis dari master pola kerja</span>
                </div>
                <div class="schedule-legend__item">
                    <span class="schedule-legend__swatch schedule-cell--manual-off"></span>
                    <span>OFF manual override</span>
                </div>
                <div class="schedule-legend__item">
                    <span class="schedule-legend__swatch schedule-cell--manual-hadir"></span>
                    <span>HADIR manual override di atas pola otomatis OFF</span>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="table-set-kehadiran" class="table table-hover table-striped mb-0 table-xs small text-sm nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama</th>
                                <th>NIK</th>
                                <th>Divisi</th>
                                <th>Departemen</th>
                                <th>Posisi</th>
                                <th>Pola Kerja</th>

                                @foreach($dates as $date)
                                @php
                                    $holiday = $nationalHolidaysByDate->get($date->toDateString());
                                    $isSunday = $date->dayOfWeek === \Carbon\Carbon::SUNDAY;
                                @endphp
                                <th class="text-center attendance-date-header {{ $holiday ? 'attendance-date-header--holiday' : '' }} {{ $isSunday ? 'attendance-date-header--sunday' : '' }}" title="{{ $holiday ? $holiday->holiday_name : '' }}">
                                    <div class="attendance-date-header__date">{{ $date->format('d') }}</div>
                                    <div class="attendance-date-header__day">
                                        {{ $date->translatedFormat('D') }}
                                    </div>
                                    @if($holiday)
                                    <div class="attendance-date-header__holiday">
                                        {{ \Illuminate\Support\Str::limit($holiday->holiday_name, 26) }}
                                    </div>
                                    @endif
                                </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employees as $index => $employee)
                            <tr>
                                <td>{{ ++$index }}</td>
                                <td>{{ $employee->nama_karyawan }}</td>
                                <td>{{ $employee->nik }}</td>
                                <td>{{ optional($employee->divisi)->nama_divisi ?? '-' }}</td>
                                <td>{{ optional($employee->departemen)->departemen ?? '-' }}</td>
                                <td>{{ optional($employee)->posisi ?? '-' }}</td>
                                <td>
                                    @if($employee->workPattern)
                                        <div class="fw-semibold">{{ $employee->workPattern->code }}</div>
                                        <div class="small text-muted">{{ optional($employee->work_pattern_start_date)->format('d-m-Y') ?: 'Mulai belum diatur' }}</div>
                                    @else
                                        <span class="text-muted">Belum ada pola</span>
                                    @endif
                                </td>

                                @foreach($dates as $date)
                                @php
                                $schedule = $scheduleMap[$employee->nik][$date->toDateString()] ?? [
                                    'auto_status' => 'HADIR',
                                    'manual_status' => null,
                                    'final_status' => 'HADIR',
                                    'is_manual' => false,
                                ];
                                $checked = $schedule['final_status'] === 'OFF' ? 'checked' : '';
                                $cellClass = '';

                                if ($schedule['is_manual'] && $schedule['manual_status'] === 'OFF') {
                                    $cellClass = 'schedule-cell--manual-off';
                                } elseif ($schedule['is_manual'] && $schedule['manual_status'] === 'HADIR') {
                                    $cellClass = 'schedule-cell--manual-hadir';
                                } elseif ($schedule['auto_status'] === 'OFF') {
                                    $cellClass = 'schedule-cell--auto-off';
                                }

                                $metaLabel = $schedule['is_manual']
                                    ? 'MANUAL'
                                    : ($schedule['auto_status'] === 'OFF' ? 'AUTO' : '');
                                @endphp

                                <td class="text-center {{ $cellClass }}">
                                    <input
                                        type="checkbox"
                                        class="attendance-checkbox"
                                        data-employee="{{ $employee->nik }}"
                                        data-date="{{ $date->toDateString() }}"
                                        data-auto-status="{{ $schedule['auto_status'] }}"
                                        data-manual-status="{{ $schedule['manual_status'] }}"
                                        data-status="{{ $schedule['final_status'] }}"
                                        {{ $checked }}>
                                    @if($metaLabel)
                                        <span class="schedule-cell__meta {{ $schedule['is_manual'] ? 'schedule-cell__meta--manual' : 'schedule-cell__meta--auto' }}">
                                            {{ $metaLabel }}
                                        </span>
                                    @endif
                                </td>
                                @endforeach
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ 7 + count($dates) }}" class="text-center text-muted py-4">
                                    {{ $selectedDepartemenId ? 'Tidak ada data karyawan untuk filter yang dipilih.' : 'Pilih departemen untuk mulai menampilkan data.' }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@if($canManageNationalHolidays)
<div class="modal fade" id="modalNationalHoliday" tabindex="-1" aria-labelledby="modalNationalHolidayLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="modalNationalHolidayLabel">Kelola Tanggal Merah Nasional</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @if(!$isNationalHolidayTableReady)
                <div class="alert alert-warning mb-0">
                    Fitur ini belum aktif karena tabel tanggal merah nasional belum tersedia. Jalankan <code>php artisan migrate</code> terlebih dahulu.
                </div>
                @else
                <div class="holiday-manager-card mb-3">
                    <div class="fw-semibold mb-1">Input atau perbarui tanggal merah</div>
                    <div class="small text-muted mb-3">
                        Jika tanggal yang sama diinput ulang, nama libur nasionalnya akan diperbarui.
                    </div>

                    <form action="{{ route('set-kehadiran.national-holidays.store') }}" method="POST" class="row g-3">
                        @csrf
                        <input type="hidden" name="periode" value="{{ $periode }}">
                        <input type="hidden" name="departemen" value="{{ $selectedDepartemenId }}">
                        <input type="hidden" name="divisi" value="{{ $selectedDivisiId }}">

                        <div class="col-md-4">
                            <label class="form-label">Tanggal</label>
                            <input type="date" name="holiday_date" class="form-control @error('holiday_date') is-invalid @enderror" value="{{ old('holiday_date') }}" required>
                            @error('holiday_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Nama Tanggal Merah</label>
                            <input type="text" name="holiday_name" class="form-control @error('holiday_name') is-invalid @enderror" value="{{ old('holiday_name') }}" maxlength="150" placeholder="Contoh: Hari Raya Idul Fitri" required>
                            @error('holiday_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-danger">
                                Simpan Tanggal Merah
                            </button>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered holiday-manager-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">No</th>
                                <th style="width: 160px;">Tanggal</th>
                                <th>Nama Libur Nasional</th>
                                <th style="width: 120px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($nationalHolidays as $index => $holiday)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ formatDateIndonesia($holiday->holiday_date) }}</td>
                                <td>{{ $holiday->holiday_name }}</td>
                                <td>
                                    <form action="{{ route('set-kehadiran.national-holidays.destroy', ['nationalHoliday' => $holiday->id] + request()->only(['periode', 'departemen', 'divisi'])) }}" method="POST" onsubmit="return confirm('Hapus tanggal merah ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Belum ada tanggal merah nasional yang diinput.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

<div class="modal fade" id="modalBulkFaceReference" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="modalBulkFaceReferenceLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="modalBulkFaceReferenceLabel">Bulk Upload Foto Referensi Presensi</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('set-kehadiran.bulk-upload-face-reference') }}" method="POST" enctype="multipart/form-data" class="js-bulk-upload-form" data-redirect-url="{{ route('set-kehadiran.index', request()->only(['periode', 'departemen', 'divisi'])) }}">
                @csrf
                <input type="hidden" name="periode" value="{{ $periode }}">
                <input type="hidden" name="departemen" value="{{ $selectedDepartemenId }}">
                <input type="hidden" name="divisi" value="{{ $selectedDivisiId }}">

                <div class="modal-body">
                    <div class="alert alert-light border small">
                        Upload satu file ZIP per jenis dokumen.
                        Isi ZIP harus memakai nama file yang mengandung NIK karyawan, misalnya <code>2200112233.jpg</code>, <code>2200112233.jpeg</code>, atau <code>2200112233.pdf</code>.
                        ZIP akan diproses di background dan hasil akhirnya dikirim ke notifikasi.
                    </div>

                    <div class="alert alert-warning small">
                        <div class="fw-semibold mb-1">Panduan cepat</div>
                        Batas upload ZIP dari aplikasi ini disiapkan sampai sekitar <code>500MB</code> per file ZIP. Pastikan worker queue aktif agar proses berjalan di background.
                        <div class="mt-2">
                            <a href="{{ asset('upload-templates/contoh-zip-dokumen-karyawan.txt') }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                Download Template ZIP
                            </a>
                        </div>
                    </div>

                    <label class="form-label">Pilih ZIP Foto Referensi</label>
                    <input type="file" name="face_reference_zip" class="form-control" accept=".zip,application/zip" required>
                    <small class="text-muted d-block mt-2">
                        Satu ZIP ini hanya akan dipasangkan ke karyawan dalam scope akses Anda. Maksimal sekitar 500MB per ZIP.
                    </small>

                    <div class="bulk-upload-feedback mt-3 d-none" data-upload-feedback>
                        <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
                            <div class="bulk-upload-feedback__title">Upload sedang berjalan</div>
                            <div class="bulk-upload-feedback__text" data-upload-percent>0%</div>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" data-upload-progress-bar></div>
                        </div>
                        <div class="bulk-upload-feedback__text mt-2 mb-0" data-upload-status>Menyiapkan upload ZIP ke server...</div>
                        <div class="bulk-upload-feedback__error mt-2 d-none" data-upload-error></div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary" data-submit-label="Upload ZIP">Upload ZIP</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function() {
        function setUploadState(form, state) {
            const feedback = form.querySelector('[data-upload-feedback]');
            const progressBar = form.querySelector('[data-upload-progress-bar]');
            const percentLabel = form.querySelector('[data-upload-percent]');
            const statusLabel = form.querySelector('[data-upload-status]');
            const errorLabel = form.querySelector('[data-upload-error]');
            const submitButton = form.querySelector('button[type="submit"]');
            const submitLabel = submitButton ? (submitButton.dataset.submitLabel || submitButton.textContent.trim()) : 'Upload';

            if (!feedback || !progressBar || !percentLabel || !statusLabel || !errorLabel || !submitButton) {
                return;
            }

            if (state.mode === 'idle') {
                feedback.classList.add('d-none');
                errorLabel.classList.add('d-none');
                errorLabel.textContent = '';
                progressBar.style.width = '0%';
                progressBar.classList.add('progress-bar-animated', 'progress-bar-striped');
                progressBar.classList.remove('bg-danger', 'bg-success');
                percentLabel.textContent = '0%';
                statusLabel.textContent = 'Menyiapkan upload ZIP ke server...';
                submitButton.disabled = false;
                submitButton.textContent = submitLabel;
                return;
            }

            feedback.classList.remove('d-none');
            progressBar.style.width = `${state.percent}%`;
            percentLabel.textContent = `${state.percent}%`;
            statusLabel.textContent = state.message;
            submitButton.disabled = true;
            submitButton.textContent = state.buttonLabel || 'Sedang Upload...';

            if (state.mode === 'error') {
                progressBar.classList.remove('progress-bar-animated', 'progress-bar-striped');
                progressBar.classList.add('bg-danger');
                errorLabel.textContent = state.error || 'Upload gagal diproses.';
                errorLabel.classList.remove('d-none');
                submitButton.disabled = false;
                submitButton.textContent = submitLabel;
                return;
            }

            if (state.mode === 'success') {
                progressBar.classList.remove('progress-bar-animated', 'progress-bar-striped');
                progressBar.classList.add('bg-success');
                errorLabel.classList.add('d-none');
                return;
            }

            progressBar.classList.remove('bg-danger', 'bg-success');
            errorLabel.classList.add('d-none');
        }

        document.querySelectorAll('.js-bulk-upload-form').forEach((form) => {
            const modal = form.closest('.modal');

            if (modal) {
                modal.addEventListener('hidden.bs.modal', function() {
                    setUploadState(form, {
                        mode: 'idle'
                    });
                });
            }

            form.addEventListener('submit', function(event) {
                if (!window.FormData || !window.XMLHttpRequest) {
                    return;
                }

                event.preventDefault();

                const xhr = new XMLHttpRequest();
                xhr.open(form.method || 'POST', form.action);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.setRequestHeader('Accept', 'application/json');

                setUploadState(form, {
                    mode: 'uploading',
                    percent: 0,
                    message: 'Mengunggah file ZIP ke server. Jangan tutup halaman ini.',
                    buttonLabel: 'Mengunggah...'
                });

                xhr.upload.addEventListener('progress', function(progressEvent) {
                    if (!progressEvent.lengthComputable) {
                        return;
                    }

                    const percent = Math.max(1, Math.min(99, Math.round((progressEvent.loaded / progressEvent.total) * 100)));

                    setUploadState(form, {
                        mode: 'uploading',
                        percent: percent,
                        message: `Upload berjalan ${percent}%. Setelah selesai, file akan dimasukkan ke antrean background.`,
                        buttonLabel: 'Mengunggah...'
                    });
                });

                xhr.addEventListener('load', function() {
                    let payload = {};

                    try {
                        payload = xhr.responseText ? JSON.parse(xhr.responseText) : {};
                    } catch (error) {
                        payload = {};
                    }

                    if (xhr.status >= 200 && xhr.status < 300) {
                        setUploadState(form, {
                            mode: 'success',
                            percent: 100,
                            message: payload.message || 'Upload selesai dikirim. Halaman akan dimuat ulang.',
                            buttonLabel: 'Selesai'
                        });

                        window.setTimeout(function() {
                            window.location.href = payload.redirect_url || form.dataset.redirectUrl || window.location.href;
                        }, 900);

                        return;
                    }

                    const validationMessage = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
                    const errorMessage = validationMessage
                        || payload.message
                        || 'Upload gagal atau server tidak memberikan respons yang valid.';

                    setUploadState(form, {
                        mode: 'error',
                        percent: 100,
                        message: 'Upload berhenti sebelum selesai diproses.',
                        error: errorMessage
                    });
                });

                xhr.addEventListener('error', function() {
                    setUploadState(form, {
                        mode: 'error',
                        percent: 100,
                        message: 'Koneksi ke server terputus saat upload berlangsung.',
                        error: 'Server tidak merespons. Kemungkinan batas upload atau timeout di hosting masih terlalu kecil.'
                    });
                });

                xhr.addEventListener('abort', function() {
                    setUploadState(form, {
                        mode: 'error',
                        percent: 100,
                        message: 'Upload dibatalkan.',
                        error: 'Proses upload dibatalkan sebelum selesai.'
                    });
                });

                xhr.send(new FormData(form));
            });
        });
    })();
</script>

<script>
    $(document).ready(function() {
        @if($canManageNationalHolidays && ($errors->has('holiday_date') || $errors->has('holiday_name')))
        const nationalHolidayModalElement = document.getElementById('modalNationalHoliday');
        if (nationalHolidayModalElement) {
            new bootstrap.Modal(nationalHolidayModalElement).show();
        }
        @endif

        $('#table-set-kehadiran').DataTable({
            processing: true,
            scrollX: true,
            scrollY: "65vh",
            scrollCollapse: true,
            paging: true,
            fixedHeader: true,
            fixedColumns: {
                leftColumns: 4
            },
            pageLength: 10,
            ordering: false
        });

        const filterDepartemen = $('#filter_departemen');
        const filterDivisi = $('#filter_divisi');

        if (filterDepartemen.length) {
            filterDepartemen.on('change', function() {
                const departemen = $(this).val();

                filterDivisi.prop('disabled', true).html('<option value="">Loading...</option>');

                if (!departemen) {
                    filterDivisi.html('<option value="">Semua Divisi</option>').prop('disabled', true);
                    return;
                }

                $.get("{{ route('ajax.divisi.by.departemen') }}", {
                    departemen: departemen
                }).done(function(response) {
                    let options = '<option value="">Semua Divisi</option>';

                    response.forEach(function(item) {
                        options += `<option value="${item.id}">${item.nama_divisi}</option>`;
                    });

                    filterDivisi.html(options).prop('disabled', false);
                }).fail(function() {
                    filterDivisi.html('<option value="">Gagal memuat divisi</option>').prop('disabled', true);
                });
            });
        }
    });
</script>

<script>
    let dirtyCells = new Map();
    let debounceTimer = null;

    $(document).on('change', '.attendance-checkbox', function() {
        let checkbox = $(this);

        let employee = checkbox.data('employee');
        let date = checkbox.data('date');

        let newStatus = checkbox.is(':checked') ? 'OFF' : 'HADIR';
        let oldStatus = checkbox.data('status');
        let autoStatus = checkbox.data('auto-status');

        if (newStatus === oldStatus) return;

        let key = employee + '_' + date;

        dirtyCells.set(key, {
            employee_id: employee,
            tanggal: date,
            status: newStatus,
            auto_status: autoStatus,
            element: checkbox
        });

        checkbox.closest('td').addClass('table-warning');

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(sendBatch, 700);
    });

    async function sendBatch() {
        let payload = Array.from(dirtyCells.values());

        if (payload.length === 0) return;

        try {
            let response = await fetch("{{ route('set-kehadiran.update') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}"
                },
                body: JSON.stringify({
                    data: payload.map(p => ({
                        employee_id: p.employee_id,
                        tanggal: p.tanggal,
                        status: p.status,
                        auto_status: p.auto_status
                    }))
                })
            });

            if (!response.ok) throw new Error();

            payload.forEach(item => {
                const shouldResetToAuto = item.status === item.auto_status;
                const finalStatus = shouldResetToAuto ? item.auto_status : item.status;

                item.element.data('status', finalStatus);
                item.element.data('manual-status', shouldResetToAuto ? '' : item.status);
                item.element.closest('td').removeClass('table-warning')
                    .addClass('table-success')
                    .removeClass('schedule-cell--auto-off schedule-cell--manual-off schedule-cell--manual-hadir');

                item.element.siblings('.schedule-cell__meta').remove();

                if (shouldResetToAuto && item.auto_status === 'OFF') {
                    item.element.closest('td').addClass('schedule-cell--auto-off');
                    item.element.after('<span class="schedule-cell__meta schedule-cell__meta--auto">AUTO</span>');
                } else if (!shouldResetToAuto && item.status === 'OFF') {
                    item.element.closest('td').addClass('schedule-cell--manual-off');
                    item.element.after('<span class="schedule-cell__meta schedule-cell__meta--manual">MANUAL</span>');
                } else if (!shouldResetToAuto && item.status === 'HADIR') {
                    item.element.closest('td').addClass('schedule-cell--manual-hadir');
                    item.element.after('<span class="schedule-cell__meta schedule-cell__meta--manual">MANUAL</span>');
                }

                setTimeout(() => {
                    item.element.closest('td').removeClass('table-success');
                }, 800);
            });

            dirtyCells.clear();
        } catch (e) {
            payload.forEach(item => {
                let oldStatus = item.element.data('status');

                item.element.prop('checked', oldStatus === 'OFF');
                item.element.closest('td').removeClass('table-warning');
            });

            alert('Update gagal');
        }
    }
</script>

<script src="https://cdn.datatables.net/fixedheader/3.4.0/js/dataTables.fixedHeader.min.js"></script>
<script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>
@endpush

@endsection
