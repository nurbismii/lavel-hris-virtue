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
    $selectedAssignmentMode = old('bulk_assignment_mode', request('bulk_assignment_mode', 'replace'));
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
    <div class="page-inner ui-page">
        <div class="ui-page-header">
            <div class="ui-page-heading">
                <span class="ui-page-icon" aria-hidden="true">
                    <i class="fas fa-map-marker-alt"></i>
                </span>
                <div>
                    <h3 class="ui-page-title">Lokasi Presensi</h3>
                    <p class="ui-page-subtitle">Kelola titik presensi dan assignment lokasi massal untuk karyawan aktif.</p>
                </div>
            </div>

            <div class="ui-page-actions">
                <a href="{{ route('setting-lokasi-presensi.create') }}" class="btn btn-primary ui-btn-icon" data-loading-text="Membuka...">
                    <i class="fa fa-plus" aria-hidden="true"></i>
                    <span>Tambah Lokasi</span>
                </a>
            </div>
        </div>

        <section class="ui-panel mb-4" aria-labelledby="bulkLocationTitle">
            <div class="ui-panel__header">
                <div>
                    <h5 class="ui-panel__title" id="bulkLocationTitle">Assign Lokasi Presensi Massal</h5>
                    <p class="ui-panel__meta">Gunakan filter organisasi atau daftar NIK spesifik untuk membagi karyawan dalam divisi yang sama ke lokasi berbeda.</p>
                </div>
                <span class="ui-status-pill ui-status-pill--success">
                    <i class="fas fa-user-check" aria-hidden="true"></i>
                    Karyawan aktif saja
                </span>
            </div>

            <div class="ui-panel__body">
                @error('bulk_filter')
                    <div class="alert ui-alert ui-alert--warning">{{ $message }}</div>
                @enderror

                <form action="{{ route('setting-lokasi-presensi.index') }}" method="GET" class="row g-3" id="bulkLocationPreviewForm" data-loading-text="Memuat preview...">
                    <input type="hidden" name="bulk_preview" value="1">

                    <div class="col-lg-4 ui-field">
                        <label class="form-label" for="bulk_lokasi_absen_id">Lokasi Tujuan</label>
                        <select id="bulk_lokasi_absen_id" name="bulk_lokasi_absen_id" class="form-select @error('bulk_lokasi_absen_id') is-invalid @enderror">
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

                    <div class="col-lg-2 ui-field">
                        <label class="form-label" for="bulkLocationPerusahaan">Area</label>
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

                    <div class="col-lg-3 ui-field">
                        <label class="form-label" for="bulkLocationDepartemen">Departemen</label>
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

                    <div class="col-lg-3 ui-field">
                        <label class="form-label" for="bulkLocationDivisi">Divisi</label>
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

                    <div class="col-lg-2 ui-field">
                        <label class="form-label" for="bulk_effective_from">Mulai Berlaku</label>
                        <input type="date" id="bulk_effective_from" name="bulk_effective_from" class="form-control @error('bulk_effective_from') is-invalid @enderror" value="{{ $selectedEffectiveFrom }}">
                        @error('bulk_effective_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-lg-2 ui-field">
                        <label class="form-label" for="bulk_effective_until">Selesai Berlaku</label>
                        <input type="date" id="bulk_effective_until" name="bulk_effective_until" class="form-control @error('bulk_effective_until') is-invalid @enderror" value="{{ $selectedEffectiveUntil }}">
                        @error('bulk_effective_until')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-lg-5 ui-field">
                        <label class="form-label" for="bulk_note">Catatan</label>
                        <input type="text" id="bulk_note" name="bulk_note" class="form-control @error('bulk_note') is-invalid @enderror" value="{{ $selectedNote }}" maxlength="255" placeholder="Contoh: Penempatan Gudang B periode Mei">
                        @error('bulk_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-lg-3 ui-field">
                        <label class="form-label" for="bulk_assignment_mode">Mode Assignment</label>
                        <select id="bulk_assignment_mode" name="bulk_assignment_mode" class="form-select @error('bulk_assignment_mode') is-invalid @enderror">
                            <option value="replace" {{ $selectedAssignmentMode === 'replace' ? 'selected' : '' }}>
                                Replace lokasi aktif lama
                            </option>
                            <option value="append" {{ $selectedAssignmentMode === 'append' ? 'selected' : '' }}>
                                Tambahkan lokasi aktif
                            </option>
                        </select>
                        @error('bulk_assignment_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted d-block mt-1">Pilih tambahkan jika karyawan boleh presensi di beberapa titik dalam periode yang sama.</small>
                    </div>

                    <div class="col-lg-9 ui-field">
                        <label class="form-label" for="bulk_employee_niks">NIK Spesifik</label>
                        <textarea
                            id="bulk_employee_niks"
                            name="bulk_employee_niks"
                            rows="4"
                            class="form-control @error('bulk_employee_niks') is-invalid @enderror"
                            placeholder="Opsional. Isi jika dalam departemen/divisi yang sama perlu dibagi ke beberapa lokasi. Pisahkan NIK dengan baris baru, koma, titik koma, atau spasi.">{{ $selectedEmployeeNiks }}</textarea>
                        @error('bulk_employee_niks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted d-block mt-1">Jika diisi, assignment hanya berlaku untuk NIK yang cocok dengan scope akses dan filter di atas.</small>
                    </div>

                    <div class="col-lg-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary ui-btn-icon w-100" data-loading-text="Memuat preview...">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <span>Preview Assignment</span>
                        </button>
                    </div>
                </form>
            </div>
        </section>

        @if ($bulkPreview)
            <section class="ui-panel mb-4" aria-labelledby="bulkLocationPreviewTitle">
                <div class="ui-panel__header">
                    <div>
                        <h5 class="ui-panel__title" id="bulkLocationPreviewTitle">Preview Assignment Massal</h5>
                        <p class="ui-panel__meta">
                            Target: {{ $bulkPreview['selected_location']->display_name }}
                            | Mulai {{ $bulkPreview['effective_from'] }}
                            @if($bulkPreview['effective_until'])
                                sampai {{ $bulkPreview['effective_until'] }}
                            @endif
                            | Mode: {{ ($bulkPreview['assignment_mode'] ?? 'replace') === 'append' ? 'Tambah lokasi aktif' : 'Replace lokasi aktif lama' }}
                        </p>
                    </div>
                    <div class="ui-metric">
                        <span class="ui-metric__value">{{ number_format($bulkPreview['total']) }}</span>
                        <span class="ui-metric__label">
                            karyawan aktif
                            @if(!empty($bulkPreview['requested_niks']))
                                dari {{ number_format(count($bulkPreview['requested_niks'])) }} NIK
                            @endif
                        </span>
                    </div>
                </div>

                <div class="ui-panel__body">
                    @if(!empty($bulkPreview['requested_niks']))
                        <div class="ui-help-panel mb-3">
                            <strong>Mode NIK spesifik aktif.</strong>
                            Sistem hanya memproses NIK yang ada di daftar, aktif, berada dalam scope akses, dan cocok dengan filter organisasi yang dipilih.
                        </div>
                    @endif

                    @if(!empty($bulkPreview['unmatched_niks']))
                        <div class="alert ui-alert ui-alert--warning">
                            <strong>{{ count($bulkPreview['unmatched_niks']) }} NIK tidak akan diproses</strong>
                            karena tidak ditemukan, tidak aktif, di luar scope akses, atau tidak cocok dengan filter:
                            {{ collect($bulkPreview['unmatched_niks'])->take(30)->join(', ') }}
                            @if(count($bulkPreview['unmatched_niks']) > 30)
                                , dan {{ count($bulkPreview['unmatched_niks']) - 30 }} lainnya
                            @endif
                        </div>
                    @endif

                    @if ($bulkPreview['total'] < 1)
                        <div class="alert ui-alert ui-alert--warning mb-0">
                            Tidak ada karyawan aktif yang cocok dengan filter ini.
                        </div>
                    @else
                        <div class="ui-table-wrap mb-3">
                            <table class="table table-sm table-bordered align-middle ui-table">
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
                                                @if($currentAssignment && $currentAssignment->isNotEmpty())
                                                    @foreach($currentAssignment as $assignment)
                                                        <div class="{{ $loop->last ? '' : 'mb-2' }}">
                                                            {{ optional($assignment->location)->display_name ?? 'Lokasi #' . $assignment->lokasi_absen_id }}
                                                            <small class="ui-table-note d-block">
                                                                Sejak {{ optional($assignment->effective_from)->format('Y-m-d') }}
                                                                @if($assignment->effective_until)
                                                                    sampai {{ optional($assignment->effective_until)->format('Y-m-d') }}
                                                                @endif
                                                            </small>
                                                        </div>
                                                    @endforeach
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
                            <div class="ui-help-panel mb-3">
                                Tabel hanya menampilkan {{ $bulkPreview['employees']->count() }} karyawan pertama untuk preview. Saat disimpan, sistem akan memproses semua {{ number_format($bulkPreview['total']) }} karyawan aktif yang cocok dengan filter/daftar NIK.
                            </div>
                        @endif

                        <form
                            action="{{ route('setting-lokasi-presensi.bulk-assign') }}"
                            method="POST"
                            class="ui-help-panel"
                            data-loading-text="Menyimpan assignment..."
                            data-swal-confirm="Assignment lokasi akan diterapkan ke seluruh karyawan pada preview. Lanjutkan?"
                            data-swal-confirm-button="Ya, terapkan">
                            @csrf
                            <input type="hidden" name="bulk_lokasi_absen_id" value="{{ $selectedLocation }}">
                            <input type="hidden" name="bulk_perusahaan_id" value="{{ $selectedPerusahaan }}">
                            <input type="hidden" name="bulk_departemen_id" value="{{ $selectedDepartemen }}">
                            <input type="hidden" name="bulk_divisi_id" value="{{ $selectedDivisi }}">
                            <input type="hidden" name="bulk_effective_from" value="{{ $selectedEffectiveFrom }}">
                            <input type="hidden" name="bulk_effective_until" value="{{ $selectedEffectiveUntil }}">
                            <input type="hidden" name="bulk_note" value="{{ $selectedNote }}">
                            <input type="hidden" name="bulk_assignment_mode" value="{{ $selectedAssignmentMode }}">
                            <textarea name="bulk_employee_niks" class="d-none">{{ $selectedEmployeeNiks }}</textarea>

                            <div class="form-check mb-3">
                                <input class="form-check-input @error('confirm_bulk_assignment') is-invalid @enderror" type="checkbox" value="1" id="confirmBulkAssignment" name="confirm_bulk_assignment">
                                <label class="form-check-label" for="confirmBulkAssignment">
                                    Saya sudah memeriksa preview dan memahami assignment ini akan diterapkan massal.
                                </label>
                                @error('confirm_bulk_assignment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <button type="submit" class="btn btn-success ui-btn-icon" data-loading-text="Menyimpan assignment...">
                                <i class="fas fa-check" aria-hidden="true"></i>
                                <span>Terapkan Assignment Massal</span>
                            </button>
                        </form>
                    @endif
                </div>
            </section>
        @endif

        <section class="ui-panel" aria-labelledby="locationTableTitle">
            <div class="ui-panel__header">
                <div>
                    <h5 class="ui-panel__title" id="locationTableTitle">Daftar Titik Presensi</h5>
                    <p class="ui-panel__meta">Pantau titik koordinat, radius, dan jumlah assignment karyawan aktif.</p>
                </div>
            </div>

            <div class="ui-panel__body">
                <div class="ui-table-wrap">
                    <table id="table-lokasi-presensi" class="table table-bordered table-striped table-sm small text-sm nowrap align-middle ui-table">
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
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $lok->display_name }}</td>
                                    <td>{{ optional(optional(optional($lok->divisi)->departemen)->perusahaan)->kode_perusahaan ?? '-' }}</td>
                                    <td>{{ optional(optional($lok->divisi)->departemen)->departemen ?? '-' }}</td>
                                    <td>{{ optional($lok->divisi)->nama_divisi ?? '-' }}</td>
                                    <td>{{ $lok->lat }}</td>
                                    <td>{{ $lok->long }}</td>
                                    <td>{{ number_format($lok->radius) }}</td>
                                    <td>{{ number_format($lok->active_employee_assignment_count ?? 0) }}</td>
                                    <td class="text-nowrap">
                                        <div class="ui-actions">
                                            <a href="{{ route('setting-lokasi-presensi.edit', $lok->id) }}" class="btn btn-sm btn-primary ui-btn-icon" data-loading-text="Membuka...">
                                                <i class="fas fa-edit" aria-hidden="true"></i>
                                                <span>Edit</span>
                                            </a>
                                            <form
                                                action="{{ route('setting-lokasi-presensi.destroy', $lok->id) }}"
                                                method="POST"
                                                data-loading-text="Menghapus..."
                                                data-swal-confirm="Lokasi presensi akan dihapus jika belum dipakai assignment karyawan. Lanjutkan?"
                                                data-swal-confirm-button="Ya, hapus">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm ui-btn-icon" data-loading-text="Menghapus...">
                                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                                    <span>Hapus</span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $("#table-lokasi-presensi").DataTable({
            responsive: true,
            language: {
                emptyTable: 'Belum ada lokasi presensi.',
                zeroRecords: 'Tidak ada lokasi presensi yang cocok dengan pencarian.'
            }
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
