@extends('layouts.app')

@section('title', 'Master Jadwal Kerja')

@section('content')
@php
    $selectedPerusahaan = (string) old('perusahaan_id', '');
    $selectedDepartemen = (string) old('departemen_id', '');
    $selectedDivisi = (string) old('divisi_id', '');
    $companies = $divisions
        ->map(fn($division) => optional(optional($division->departemen)->perusahaan))
        ->filter(fn($company) => filled(optional($company)->id))
        ->unique('id')
        ->sortBy(fn($company) => $company->kode_perusahaan ?? $company->nama_perusahaan ?? $company->id)
        ->values();
    $departemens = $divisions
        ->map(fn($division) => optional($division->departemen))
        ->filter(fn($departemen) => filled(optional($departemen)->id))
        ->unique('id')
        ->sortBy('departemen')
        ->values();
@endphp
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">Master Jadwal Kerja</h4>
                <small class="text-muted">Kelola pola kerja dasar seperti 5:1, 6:1, atau 10 bulan kerja dan 2 minggu off.</small>
            </div>
            <div class="ms-md-auto py-2 py-md-0">
                <a href="{{ route('work-patterns.create') }}" class="btn btn-primary">
                    Tambah Pola Kerja
                </a>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                    <div>
                        <h5 class="mb-1">Assign Massal Per Divisi</h5>
                        <small class="text-muted">Pola kerja yang dipilih akan diterapkan ke semua karyawan aktif dalam divisi tersebut.</small>
                    </div>
                </div>

                <form action="{{ route('work-patterns.bulk-assign') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-lg-3">
                        <label class="form-label">Perusahaan</label>
                        <select
                            name="perusahaan_id"
                            id="bulkAssignPerusahaan"
                            class="form-select">
                            <option value="">-- Pilih Perusahaan --</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" {{ $selectedPerusahaan === (string) $company->id ? 'selected' : '' }}>
                                    {{ $company->kode_perusahaan ?? $company->nama_perusahaan ?? $company->id }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label">Departemen</label>
                        <select
                            name="departemen_id"
                            id="bulkAssignDepartemen"
                            class="form-select">
                            <option value="">-- Pilih Departemen --</option>
                            @foreach ($departemens as $departemen)
                                <option
                                    value="{{ $departemen->id }}"
                                    data-perusahaan-id="{{ optional($departemen->perusahaan)->id }}"
                                    {{ $selectedDepartemen === (string) $departemen->id ? 'selected' : '' }}>
                                    {{ $departemen->departemen }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label">Divisi</label>
                        <select
                            name="divisi_id"
                            id="bulkAssignDivisi"
                            class="form-select @error('divisi_id') is-invalid @enderror">
                            <option value="">-- Pilih Divisi --</option>
                            @foreach ($divisions as $division)
                                <option
                                    value="{{ $division->id }}"
                                    data-departemen-id="{{ optional($division->departemen)->id }}"
                                    data-perusahaan-id="{{ optional(optional($division->departemen)->perusahaan)->id }}"
                                    {{ $selectedDivisi === (string) $division->id ? 'selected' : '' }}>
                                    {{ optional($division->departemen)->departemen ? optional($division->departemen)->departemen . ' - ' : '' }}{{ $division->nama_divisi }} ({{ $division->active_employee_count }} aktif)
                                </option>
                            @endforeach
                        </select>
                        @error('divisi_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label">Master Pola Kerja</label>
                        <select name="work_pattern_id" class="form-select @error('work_pattern_id') is-invalid @enderror">
                            <option value="">-- Pilih Pola Kerja --</option>
                            @foreach ($activeWorkPatterns as $workPattern)
                                <option value="{{ $workPattern->id }}" {{ (string) old('work_pattern_id') === (string) $workPattern->id ? 'selected' : '' }}>
                                    {{ $workPattern->code }} - {{ $workPattern->name }} ({{ $workPattern->cycle_summary }} | {{ $workPattern->work_time_range_text }})
                                </option>
                            @endforeach
                        </select>
                        @error('work_pattern_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label">Mulai Berlaku</label>
                        <input type="date" name="work_pattern_start_date" class="form-control @error('work_pattern_start_date') is-invalid @enderror" value="{{ old('work_pattern_start_date', now()->toDateString()) }}">
                        @error('work_pattern_start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Assign Massal</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama</th>
                                <th>Siklus</th>
                                <th>Jam Kerja</th>
                                <th>Tanggal Merah</th>
                                <th>Status</th>
                                <th>Dipakai</th>
                                <th>Keterangan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($workPatterns as $workPattern)
                                <tr>
                                    <td>{{ $workPattern->code }}</td>
                                    <td>{{ $workPattern->name }}</td>
                                    <td>{{ $workPattern->cycle_summary }}</td>
                                    <td>
                                        <div>{{ $workPattern->work_time_range_text }}</div>
                                        <small class="text-muted d-block">Istirahat: {{ $workPattern->break_time_range_text }}</small>
                                        <small class="text-muted d-block">Efektif: {{ $workPattern->expected_work_duration_text }}</small>
                                        @if($workPattern->hasSixthDaySchedule())
                                            <small class="text-muted d-block">Hari ke-6: {{ $workPattern->sixth_day_work_time_range_text }} | Istirahat {{ $workPattern->sixth_day_break_time_range_text }} | Efektif {{ $workPattern->sixth_day_expected_work_duration_text }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $workPattern->national_holiday_rule_label }}</td>
                                    <td>
                                        <span class="badge bg-{{ $workPattern->is_active ? 'success' : 'secondary' }}">
                                            {{ $workPattern->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td>{{ $workPattern->employees_count }}</td>
                                    <td>{{ $workPattern->description ?: '-' }}</td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('work-patterns.edit', $workPattern->id) }}" class="btn btn-warning">
                                            Edit
                                        </a>
                                        <form action="{{ route('work-patterns.destroy', $workPattern->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger">
                                                Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">Belum ada master jadwal kerja.</td>
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
<script>
    (function () {
        const perusahaanSelect = document.getElementById('bulkAssignPerusahaan');
        const departemenSelect = document.getElementById('bulkAssignDepartemen');
        const divisiSelect = document.getElementById('bulkAssignDivisi');

        if (!perusahaanSelect || !departemenSelect || !divisiSelect) {
            return;
        }

        const allDepartemenOptions = Array.from(departemenSelect.querySelectorAll('option'));
        const allDivisiOptions = Array.from(divisiSelect.querySelectorAll('option'));

        function toggleOptions(select, options, predicate) {
            options.forEach(function (option, index) {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }

                const shouldShow = predicate(option);
                option.hidden = !shouldShow;

                if (!shouldShow && option.selected) {
                    option.selected = false;
                }
            });
        }

        function filterDepartemen() {
            const perusahaanId = perusahaanSelect.value;

            toggleOptions(departemenSelect, allDepartemenOptions, function (option) {
                return !perusahaanId || option.dataset.perusahaanId === perusahaanId;
            });

            const selectedDepartemen = departemenSelect.selectedOptions[0];

            if (selectedDepartemen && selectedDepartemen.hidden) {
                departemenSelect.value = '';
            }
        }

        function filterDivisi() {
            const perusahaanId = perusahaanSelect.value;
            const departemenId = departemenSelect.value;

            toggleOptions(divisiSelect, allDivisiOptions, function (option) {
                const matchesPerusahaan = !perusahaanId || option.dataset.perusahaanId === perusahaanId;
                const matchesDepartemen = !departemenId || option.dataset.departemenId === departemenId;

                return matchesPerusahaan && matchesDepartemen;
            });

            const selectedDivisi = divisiSelect.selectedOptions[0];

            if (selectedDivisi && selectedDivisi.hidden) {
                divisiSelect.value = '';
            }
        }

        perusahaanSelect.addEventListener('change', function () {
            departemenSelect.value = '';
            divisiSelect.value = '';
            filterDepartemen();
            filterDivisi();
        });

        departemenSelect.addEventListener('change', function () {
            divisiSelect.value = '';
            filterDivisi();
        });

        filterDepartemen();
        filterDivisi();
    })();
</script>
@endpush
