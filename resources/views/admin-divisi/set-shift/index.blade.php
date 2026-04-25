@extends('layouts.app')

@section('title', 'Pengaturan Shift')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/shift-settings.css') }}">
@endpush

@section('content')
<div class="container-fluid">
    <div
        class="page-inner shift-settings-page"
        data-divisions-url="{{ route('ajax.divisi.by.departemen') }}"
        data-update-url="{{ route('shift-settings.update') }}"
        data-csrf-token="{{ csrf_token() }}">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-3">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-user-clock text-primary me-2"></i>
                    Pengaturan Shift
                </h4>
                <small class="text-muted d-block">
                    Hari kerja dan hari off tetap mengikuti master pola kerja.
                    Shift hanya mengatur jam kerja pada tanggal tertentu.
                    (Cut Off {{ formatDateIndonesia($start) }} - {{ formatDateIndonesia($end) }})
                </small>
                <small class="text-muted d-block">Pilih <strong>AUTO</strong> jika jam kerja harus kembali mengikuti master pola kerja.</small>
            </div>
        </div>

        <form method="GET" class="row g-2 mb-3 align-items-end shift-filter">
            <div class="col-md-2">
                <label class="form-label">Periode</label>
                <input type="month" name="periode" value="{{ $periode }}" class="form-control">
            </div>

            @if($isDepartmentReadonly)
            <div class="col-md-3">
                <label class="form-label">Departemen</label>
                <input type="text" class="form-control" value="{{ optional($departemens->first())->departemen ?? '-' }}" readonly>
            </div>
            @else
            <div class="col-md-3">
                <label class="form-label">Departemen</label>
                <select id="filter_departemen" name="departemen" class="form-select">
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
                <select id="filter_divisi" name="divisi" class="form-select" {{ !$selectedDepartemenId ? 'disabled' : '' }}>
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
            Akun Admin Divisi ini memiliki akses ke beberapa divisi. Pilih divisi yang ingin diatur pada periode ini.
        </div>
        @endif

        @if($shifts->isEmpty())
        <div class="alert alert-warning">
            Master shift belum tersedia. Buat dulu di
            <a href="{{ route('shifts.index') }}">Master Shift</a>.
        </div>
        @endif

        @if(!$selectedDepartemenId)
        <div class="alert alert-info">
            Pilih departemen terlebih dahulu untuk menampilkan pengaturan shift.
        </div>
        @endif

        @if($requiresDivisionFilter)
        <div class="alert alert-info">
            Pilih divisi terlebih dahulu agar tabel pengaturan shift tidak memuat terlalu banyak karyawan sekaligus.
        </div>
        @endif

        @if($employeeLimitExceeded)
        <div class="alert alert-warning">
            Data dibatasi {{ $matrixEmployeeLimit }} karyawan pertama untuk menjaga halaman tetap ringan. Gunakan filter divisi yang lebih spesifik jika data yang dicari belum tampil.
        </div>
        @endif

        <div class="shift-legend">
            <span><strong>AUTO</strong> mengikuti master pola kerja.</span>
            <span>Pilih kode shift untuk override jam kerja tanggal tersebut.</span>
            <span><span class="shift-legend-dot is-sunday"></span> Minggu</span>
            <span><span class="shift-legend-dot is-national-holiday"></span> Libur nasional</span>
            <span class="ms-md-auto">{{ $employees->count() }} karyawan ditampilkan</span>
        </div>

        <div class="card border-0 shift-card">
            <div class="card-body p-0">
                <div class="shift-table-wrap">
                    <table class="table table-bordered table-sm align-middle mb-0 shift-matrix-table">
                        <thead>
                            <tr>
                                <th class="sticky-col sticky-no text-center">No</th>
                                <th class="sticky-col sticky-name">Karyawan</th>
                                <th class="sticky-col sticky-pattern">Pola</th>
                                @foreach($dates as $date)
                                    @php
                                        $dateString = $date->toDateString();
                                        $nationalHoliday = $nationalHolidayMap->get($dateString);
                                        $isNationalHoliday = filled($nationalHoliday);
                                        $isSunday = $date->isSunday();
                                    @endphp
                                    <th
                                        class="text-center shift-date-head {{ $isSunday ? 'is-sunday' : '' }} {{ $isNationalHoliday ? 'is-national-holiday' : '' }}"
                                        title="{{ $isNationalHoliday ? $nationalHoliday->holiday_name : ($isSunday ? 'Minggu' : '') }}">
                                        <div>{{ $date->format('d') }}</div>
                                        <small>{{ $date->translatedFormat('D') }}</small>
                                        @if($isNationalHoliday)
                                            <span class="holiday-chip" title="{{ $nationalHoliday->holiday_name }}">L</span>
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
                                                <span>{{ $employee->workPattern->work_time_range_text }}</span>
                                            </div>
                                        @else
                                            <span class="text-muted small">Belum ada</span>
                                        @endif
                                    </td>
                                    @foreach($dates as $date)
                                        @php
                                            $dateString = $date->toDateString();
                                            $nationalHoliday = $nationalHolidayMap->get($dateString);
                                            $isNationalHoliday = filled($nationalHoliday);
                                            $isSunday = $date->isSunday();
                                            $assignment = $shiftAssignmentMap[$employee->nik][$date->toDateString()] ?? [
                                                'shift_id' => null,
                                                'shift' => null,
                                            ];
                                            $selectedShift = $assignment['shift'] ?? null;
                                        @endphp
                                        <td
                                            class="shift-cell {{ $isSunday ? 'is-sunday' : '' }} {{ $isNationalHoliday ? 'is-national-holiday' : '' }}"
                                            title="{{ $isNationalHoliday ? $nationalHoliday->holiday_name : ($isSunday ? 'Minggu' : '') }}">
                                            <select
                                                class="form-select form-select-sm shift-assignment-select"
                                                data-employee="{{ $employee->nik }}"
                                                data-date="{{ $dateString }}"
                                                data-shift-id="{{ $assignment['shift_id'] ?? '' }}"
                                                {{ $shifts->isEmpty() ? 'disabled' : '' }}>
                                                <option value="">AUTO</option>
                                                @foreach($shifts as $shift)
                                                    <option value="{{ $shift->id }}" {{ (string) ($assignment['shift_id'] ?? '') === (string) $shift->id ? 'selected' : '' }}>
                                                        {{ $shift->code }}{{ $shift->is_active ? '' : ' [Nonaktif]' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="shift-cell-label">
                                                {{ $selectedShift ? $selectedShift->type_label : 'Pola Kerja' }}
                                            </small>
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
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/js/shift-settings.js') }}"></script>
@endpush
