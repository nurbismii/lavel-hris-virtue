@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/attendance-settings.css') }}">
@endpush

@section('content')
<div class="container-fluid">
    <div
        class="page-inner attendance-settings-page"
        data-divisions-url="{{ route('ajax.divisi.by.departemen') }}"
        data-update-url="{{ route('set-kehadiran.update') }}"
        data-csrf-token="{{ csrf_token() }}">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-3">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-cogs text-primary me-2"></i>
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
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalBulkFaceReference">
                        Bulk Foto Referensi Presensi
                    </button>
                </div>
            </div>
        </div>

        <form method="GET" class="row g-2 mb-3 align-items-end attendance-filter">
            <div class="col-md-2">
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
            <div class="col-md-3">
                <label class="form-label">Divisi</label>
                <input type="text" class="form-control" value="{{ optional($divisis->first())->nama_divisi ?? '-' }}" readonly>
                <input type="hidden" id="filter_divisi" name="divisi" value="{{ $selectedDivisiId }}">
            </div>
            @else
            <div class="col-md-3">
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

        @if($requiresDivisionFilter)
        <div class="alert alert-info">
            Pilih divisi terlebih dahulu agar tabel setting hari off tidak memuat terlalu banyak karyawan sekaligus.
        </div>
        @endif

        @if($employeeLimitExceeded)
        <div class="alert alert-warning">
            Data dibatasi {{ $matrixEmployeeLimit }} karyawan pertama untuk menjaga halaman tetap ringan. Gunakan filter divisi yang lebih spesifik jika data yang dicari belum tampil.
        </div>
        @endif

        <div class="attendance-legend">
            <span><strong>Centang</strong> berarti OFF.</span>
            <span><span class="attendance-legend-dot is-sunday"></span> Minggu</span>
            <span><span class="attendance-legend-dot is-national-holiday"></span> Libur nasional</span>
            <span class="ms-md-auto">{{ $employees->count() }} karyawan ditampilkan</span>
        </div>

        <div class="card border-0 attendance-card">
            <div class="card-body p-0">
                <div class="attendance-table-wrap">
                    <table id="table-set-kehadiran" class="table table-bordered table-sm align-middle mb-0 attendance-matrix-table">
                        <thead>
                            <tr>
                                <th class="sticky-col sticky-no text-center">No</th>
                                <th class="sticky-col sticky-name">Karyawan</th>
                                <th class="sticky-col sticky-pattern">Pola</th>

                                @foreach($dates as $date)
                                @php
                                    $dateString = $date->toDateString();
                                    $holiday = $nationalHolidaysByDate->get($dateString);
                                    $isNationalHoliday = filled($holiday);
                                    $isSunday = $date->isSunday();
                                @endphp
                                <th
                                    class="text-center attendance-date-head {{ $isSunday ? 'is-sunday' : '' }} {{ $isNationalHoliday ? 'is-national-holiday' : '' }}"
                                    title="{{ $isNationalHoliday ? $holiday->holiday_name : ($isSunday ? 'Minggu' : '') }}">
                                    <div>{{ $date->format('d') }}</div>
                                    <small>{{ $date->translatedFormat('D') }}</small>
                                    @if($isNationalHoliday)
                                        <span class="holiday-chip" title="{{ $holiday->holiday_name }}">L</span>
                                    @elseif($isSunday)
                                        <span class="holiday-chip holiday-chip--sunday">M</span>
                                    @endif
                                </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employees as $index => $employee)
                            <tr>
                                <td class="sticky-col sticky-no text-center">{{ ++$index }}</td>
                                <td class="sticky-col sticky-name">
                                    <div class="employee-cell">
                                        <strong>{{ $employee->nama_karyawan }}</strong>
                                        <span>{{ $employee->nik }}</span>
                                        <small>{{ optional($employee->divisi)->nama_divisi ?? '-' }}</small>
                                    </div>
                                </td>
                                <td class="sticky-col sticky-pattern">
                                    @if($employee->workPattern)
                                        <div class="pattern-cell">
                                            <strong>{{ $employee->workPattern->code }}</strong>
                                            <span>{{ optional($employee->work_pattern_start_date)->format('d-m-Y') ?: 'Mulai belum diatur' }}</span>
                                        </div>
                                    @else
                                        <span class="text-muted small">Belum ada</span>
                                    @endif
                                </td>

                                @foreach($dates as $date)
                                @php
                                $dateString = $date->toDateString();
                                $holiday = $nationalHolidaysByDate->get($dateString);
                                $isNationalHoliday = filled($holiday);
                                $isSunday = $date->isSunday();
                                $schedule = $scheduleMap[$employee->nik][$dateString] ?? [
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

                                <td
                                    class="attendance-cell {{ $cellClass }} {{ $isSunday ? 'is-sunday' : '' }} {{ $isNationalHoliday ? 'is-national-holiday' : '' }}"
                                    title="{{ $isNationalHoliday ? $holiday->holiday_name : ($isSunday ? 'Minggu' : '') }}">
                                    <input
                                        type="checkbox"
                                        class="attendance-checkbox"
                                        data-employee="{{ $employee->nik }}"
                                        data-date="{{ $dateString }}"
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
                                <td colspan="{{ 3 + count($dates) }}" class="text-center text-muted py-4">
                                    {{ $requiresDivisionFilter ? 'Pilih divisi untuk mulai menampilkan data.' : ($selectedDepartemenId ? 'Tidak ada data karyawan untuk filter yang dipilih.' : 'Pilih departemen untuk mulai menampilkan data.') }}
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
                        <div class="progress bulk-upload-feedback__progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bulk-upload-feedback__bar" role="progressbar" data-upload-progress-bar></div>
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
<script src="{{ versioned_asset('assets/js/attendance-settings.js') }}"></script>
@endpush

@endsection
