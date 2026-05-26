@extends('layouts.app')

@section('title', 'Lokasi Presensi')

@section('content')
@php
    $selectedLocation = (string) old('bulk_lokasi_absen_id', request('bulk_lokasi_absen_id', ''));
    $selectedPerusahaan = (string) old('bulk_perusahaan_id', request('bulk_perusahaan_id', ''));
    $selectedDepartemen = (string) old('bulk_departemen_id', request('bulk_departemen_id', ''));
    $selectedDivisi = (string) old('bulk_divisi_id', request('bulk_divisi_id', ''));
    $selectedEffectiveFrom = old('bulk_effective_from', request('bulk_effective_from', now()->toDateString()));
    $selectedEffectiveUntil = old('bulk_effective_until', request('bulk_effective_until', ''));
    $selectedNote = old('bulk_note', request('bulk_note', ''));
    $selectedEmployeeNiks = old('bulk_employee_niks', request('bulk_employee_niks', ''));
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
                <h3 class="fw-bold mb-1">Lokasi Presensi</h3>
                <small class="text-muted">Kelola titik presensi dan assignment lokasi massal untuk karyawan aktif.</small>
            </div>

            <div class="ms-md-auto py-2 py-md-0">
                <a href="{{ route('setting-lokasi-presensi.create') }}" class="btn btn-sm btn-secondary">
                    <span class="btn-label">
                        <i class="fa fa-plus"></i>
                    </span>
                    Lokasi presensi
                </a>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                    <div>
                        <h5 class="mb-1">Assign Lokasi Presensi Massal</h5>
                        <small class="text-muted">Gunakan filter organisasi atau daftar NIK spesifik untuk membagi karyawan dalam divisi yang sama ke lokasi berbeda.</small>
                    </div>
                    <span class="badge bg-light text-dark border align-self-start align-self-md-center">
                        Karyawan aktif saja
                    </span>
                </div>

                @error('bulk_filter')
                    <div class="alert alert-warning">{{ $message }}</div>
                @enderror

                <form action="{{ route('setting-lokasi-presensi.index') }}" method="GET" class="row g-3" id="bulkLocationPreviewForm">
                    <input type="hidden" name="bulk_preview" value="1">

                    <div class="col-lg-4">
                        <label class="form-label">Lokasi Tujuan</label>
                        <select name="bulk_lokasi_absen_id" class="form-select @error('bulk_lokasi_absen_id') is-invalid @enderror">
                            <option value="">-- Pilih Lokasi Presensi --</option>
                            @foreach ($lokasi as $lok)
                                <option value="{{ $lok->id }}" {{ $selectedLocation === (string) $lok->id ? 'selected' : '' }}>
                                    {{ $lok->display_name }}
                                    | {{ $lok->lat }}, {{ $lok->long }} | {{ $lok->radius }}m
                                </option>
                            @endforeach
                        </select>
                        @error('bulk_lokasi_absen_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-lg-2">
                        <label class="form-label">Area</label>
                        <select name="bulk_perusahaan_id" id="bulkLocationPerusahaan" class="form-select @error('bulk_perusahaan_id') is-invalid @enderror">
                            <option value="">Semua Area</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" {{ $selectedPerusahaan === (string) $company->id ? 'selected' : '' }}>
                                    {{ $company->kode_perusahaan ?? $company->nama_perusahaan ?? $company->id }}
                                </option>
                            @endforeach
                        </select>
                        @error('bulk_perusahaan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-lg-3">
                        <label class="form-label">Departemen</label>
                        <select name="bulk_departemen_id" id="bulkLocationDepartemen" class="form-select @error('bulk_departemen_id') is-invalid @enderror">
                            <option value="">Semua Departemen</option>
                            @foreach ($departemens as $departemen)
                                <option
                                    value="{{ $departemen->id }}"
                                    data-perusahaan-id="{{ optional($departemen->perusahaan)->id }}"
                                    {{ $selectedDepartemen === (string) $departemen->id ? 'selected' : '' }}>
                                    {{ $departemen->departemen }}
                                </option>
                            @endforeach
                        </select>
                        @error('bulk_departemen_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-lg-3">
                        <label class="form-label">Divisi</label>
                        <select name="bulk_divisi_id" id="bulkLocationDivisi" class="form-select @error('bulk_divisi_id') is-invalid @enderror">
                            <option value="">Semua Divisi</option>
                            @foreach ($divisions as $division)
                                <option
                                    value="{{ $division->id }}"
                                    data-departemen-id="{{ optional($division->departemen)->id }}"
                                    data-perusahaan-id="{{ optional(optional($division->departemen)->perusahaan)->id }}"
                                    {{ $selectedDivisi === (string) $division->id ? 'selected' : '' }}>
                                    {{ optional($division->departemen)->departemen ? optional($division->departemen)->departemen . ' - ' : '' }}{{ $division->nama_divisi }}
                                    ({{ $division->active_employee_count }} aktif)
                                </option>
                            @endforeach
                        </select>
                        @error('bulk_divisi_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-lg-2">
                        <label class="form-label">Mulai Berlaku</label>
                        <input type="date" name="bulk_effective_from" class="form-control @error('bulk_effective_from') is-invalid @enderror" value="{{ $selectedEffectiveFrom }}">
                        @error('bulk_effective_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-lg-2">
                        <label class="form-label">Selesai Berlaku</label>
                        <input type="date" name="bulk_effective_until" class="form-control @error('bulk_effective_until') is-invalid @enderror" value="{{ $selectedEffectiveUntil }}">
                        @error('bulk_effective_until')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-lg-5">
                        <label class="form-label">Catatan</label>
                        <input type="text" name="bulk_note" class="form-control @error('bulk_note') is-invalid @enderror" value="{{ $selectedNote }}" maxlength="255" placeholder="Contoh: Penempatan Gudang B periode Mei">
                        @error('bulk_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-lg-9">
                        <label class="form-label">NIK Spesifik</label>
                        <textarea
                            name="bulk_employee_niks"
                            rows="4"
                            class="form-control @error('bulk_employee_niks') is-invalid @enderror"
                            placeholder="Opsional. Isi jika dalam departemen/divisi yang sama perlu dibagi ke beberapa lokasi. Pisahkan NIK dengan baris baru, koma, titik koma, atau spasi.">{{ $selectedEmployeeNiks }}</textarea>
                        @error('bulk_employee_niks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted d-block mt-1">Jika diisi, assignment hanya berlaku untuk NIK yang cocok dengan scope akses dan filter di atas.</small>
                    </div>

                    <div class="col-lg-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i>
                            Preview Assignment
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @if ($bulkPreview)
            <div class="card mb-4 border-primary">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3">
                        <div>
                            <h5 class="mb-1">Preview Assignment Massal</h5>
                            <small class="text-muted">
                                Target: {{ $bulkPreview['selected_location']->display_name }}
                                | Mulai {{ $bulkPreview['effective_from'] }}
                                @if($bulkPreview['effective_until'])
                                    sampai {{ $bulkPreview['effective_until'] }}
                                @endif
                            </small>
                        </div>
                        <div class="text-md-end">
                            <div class="fs-4 fw-bold">{{ number_format($bulkPreview['total']) }}</div>
                            <small class="text-muted">
                                karyawan aktif terdampak
                                @if(!empty($bulkPreview['requested_niks']))
                                    dari {{ number_format(count($bulkPreview['requested_niks'])) }} NIK diminta
                                @endif
                            </small>
                        </div>
                    </div>

                    @if(!empty($bulkPreview['requested_niks']))
                        <div class="alert alert-light border">
                            <strong>Mode NIK spesifik aktif.</strong>
                            Sistem hanya memproses NIK yang ada di daftar, aktif, berada dalam scope akses, dan cocok dengan filter organisasi yang dipilih.
                        </div>
                    @endif

                    @if(!empty($bulkPreview['unmatched_niks']))
                        <div class="alert alert-warning">
                            <strong>{{ count($bulkPreview['unmatched_niks']) }} NIK tidak akan diproses</strong>
                            karena tidak ditemukan, tidak aktif, di luar scope akses, atau tidak cocok dengan filter:
                            {{ collect($bulkPreview['unmatched_niks'])->take(30)->join(', ') }}
                            @if(count($bulkPreview['unmatched_niks']) > 30)
                                , dan {{ count($bulkPreview['unmatched_niks']) - 30 }} lainnya
                            @endif
                        </div>
                    @endif

                    @if ($bulkPreview['total'] < 1)
                        <div class="alert alert-warning mb-0">
                            Tidak ada karyawan aktif yang cocok dengan filter ini.
                        </div>
                    @else
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('tables.nik') }}</th>
                                        <th>{{ __('tables.name') }}</th>
                                        <th>{{ __('tables.department') }}</th>
                                        <th>{{ __('tables.division') }}</th>
                                        <th>{{ __('tables.assignment_active_now') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($bulkPreview['employees'] as $employee)
                                        @php($currentAssignment = $bulkPreview['current_assignments']->get($employee->nik))
                                        <tr>
                                            <td>{{ $employee->nik }}</td>
                                            <td>{{ $employee->nama_karyawan }}</td>
                                            <td>{{ optional($employee->departemen)->departemen ?? '-' }}</td>
                                            <td>{{ optional($employee->divisi)->nama_divisi ?? '-' }}</td>
                                            <td>
                                                @if($currentAssignment)
                                                    {{ optional($currentAssignment->location)->display_name ?? 'Lokasi #' . $currentAssignment->lokasi_absen_id }}
                                                    <small class="text-muted d-block">
                                                        Sejak {{ optional($currentAssignment->effective_from)->format('Y-m-d') }}
                                                    </small>
                                                @else
                                                    <span class="text-muted">Default divisi / belum ada assignment karyawan</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($bulkPreview['total'] > $bulkPreview['employees']->count())
                            <div class="alert alert-info">
                                Tabel hanya menampilkan {{ $bulkPreview['employees']->count() }} karyawan pertama untuk preview. Saat disimpan, sistem akan memproses semua {{ number_format($bulkPreview['total']) }} karyawan aktif yang cocok dengan filter/daftar NIK.
                            </div>
                        @endif

                        <form action="{{ route('setting-lokasi-presensi.bulk-assign') }}" method="POST" class="border rounded p-3 bg-light">
                            @csrf
                            <input type="hidden" name="bulk_lokasi_absen_id" value="{{ $selectedLocation }}">
                            <input type="hidden" name="bulk_perusahaan_id" value="{{ $selectedPerusahaan }}">
                            <input type="hidden" name="bulk_departemen_id" value="{{ $selectedDepartemen }}">
                            <input type="hidden" name="bulk_divisi_id" value="{{ $selectedDivisi }}">
                            <input type="hidden" name="bulk_effective_from" value="{{ $selectedEffectiveFrom }}">
                            <input type="hidden" name="bulk_effective_until" value="{{ $selectedEffectiveUntil }}">
                            <input type="hidden" name="bulk_note" value="{{ $selectedNote }}">
                            <textarea name="bulk_employee_niks" class="d-none">{{ $selectedEmployeeNiks }}</textarea>

                            <div class="form-check mb-3">
                                <input class="form-check-input @error('confirm_bulk_assignment') is-invalid @enderror" type="checkbox" value="1" id="confirmBulkAssignment" name="confirm_bulk_assignment">
                                <label class="form-check-label" for="confirmBulkAssignment">
                                    Saya sudah memeriksa preview dan memahami assignment ini akan diterapkan massal.
                                </label>
                                @error('confirm_bulk_assignment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check me-1"></i>
                                Terapkan Assignment Massal
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="table-lokasi-presensi" class="table table-bordered table-striped mb-0 table-sm small text-sm nowrap">
                        <thead>
                            <tr>
                                <th>{{ __('tables.no') }}</th>
                                <th>{{ __('tables.location_name') }}</th>
                                <th>{{ __('tables.area') }}</th>
                                <th>{{ __('tables.department') }}</th>
                                <th>{{ __('tables.old_default_division') }}</th>
                                <th>{{ __('tables.latitude') }}</th>
                                <th>{{ __('tables.longitude') }}</th>
                                <th>{{ __('tables.radius_meter') }}</th>
                                <th>{{ __('tables.assignment_active') }}</th>
                                <th>{{ __('tables.action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lokasi as $key => $lok)
                                <tr>
                                    <td>{{ ++$key }}</td>
                                    <td>{{ $lok->display_name }}</td>
                                    <td>{{ optional(optional(optional($lok->divisi)->departemen)->perusahaan)->kode_perusahaan ?? '-' }}</td>
                                    <td>{{ optional(optional($lok->divisi)->departemen)->departemen ?? '-' }}</td>
                                    <td>{{ optional($lok->divisi)->nama_divisi ?? '-' }}</td>
                                    <td>{{ $lok->lat }}</td>
                                    <td>{{ $lok->long }}</td>
                                    <td>{{ $lok->radius }}</td>
                                    <td>{{ number_format($lok->active_employee_assignment_count ?? 0) }}</td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('setting-lokasi-presensi.edit', $lok->id) }}" class="btn btn-sm btn-primary btn-icon-split">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-edit"></i>
                                            </span>
                                            <span class="text">Edit</span>
                                        </a>
                                        <form action="{{ route('setting-lokasi-presensi.destroy', $lok->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm btn-icon-split" data-confirm-delete="true">
                                                <span class="icon text-white-50">
                                                    <i class="fas fa-trash"></i>
                                                </span>
                                                <span class="text">Hapus</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
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
    $(document).ready(function() {
        $("#table-lokasi-presensi").DataTable({
            responsive: true,
        });
    });
</script>

<script>
    (function () {
        const perusahaanSelect = document.getElementById('bulkLocationPerusahaan');
        const departemenSelect = document.getElementById('bulkLocationDepartemen');
        const divisiSelect = document.getElementById('bulkLocationDivisi');

        if (!perusahaanSelect || !departemenSelect || !divisiSelect) {
            return;
        }

        const departemenOptions = Array.from(departemenSelect.querySelectorAll('option'));
        const divisiOptions = Array.from(divisiSelect.querySelectorAll('option'));

        function toggleOptions(select, options, predicate) {
            options.forEach(function (option, index) {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }

                const visible = predicate(option);
                option.hidden = !visible;

                if (!visible && option.selected) {
                    option.selected = false;
                }
            });
        }

        function filterDepartemen() {
            const perusahaanId = perusahaanSelect.value;

            toggleOptions(departemenSelect, departemenOptions, function (option) {
                return !perusahaanId || option.dataset.perusahaanId === perusahaanId;
            });
        }

        function filterDivisi() {
            const perusahaanId = perusahaanSelect.value;
            const departemenId = departemenSelect.value;

            toggleOptions(divisiSelect, divisiOptions, function (option) {
                const matchesPerusahaan = !perusahaanId || option.dataset.perusahaanId === perusahaanId;
                const matchesDepartemen = !departemenId || option.dataset.departemenId === departemenId;

                return matchesPerusahaan && matchesDepartemen;
            });
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
