@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/attendance-settings.css') }}">
<link rel="stylesheet" href="{{ versioned_asset('assets/css/admin-presensi.css') }}">
@endpush

@section('content')
<div class="container-fluid">
    <div
        class="page-inner attendance-settings-page presensi-page"
        data-fetch-url="{{ route('fetch.data-presensi') }}"
        data-export-url="{{ route('presensi.export') }}"
        data-area-url="{{ route('ajax.departemen.by.area') }}"
        data-divisi-url="{{ route('ajax.divisi.by.departemen') }}">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-3">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-calendar-check text-primary me-2"></i>
                    Data Presensi Keseluruhan
                </h4>

                <small id="cutoffLabel" class="text-muted d-block">
                    Pilih cutoff untuk melihat periode presensi.
                </small>
                <small class="text-muted d-block">
                    Tampilan matriks dibuat selaras dengan Setting Hari Off agar jam, status cuti, izin, off, dan alpa mudah dipindai.
                </small>
            </div>

            <div class="ms-md-auto pt-3 pt-md-0">
                @if(auth()->user()->hasRole(['Super Admin', 'HR']))
                @if(auth()->user()->hasMenuAccess('attendance_anomaly'))
                <a href="{{ route('attendance-anomalies.index') }}" class="btn btn-outline-warning btn-sm me-2">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Anomali
                </a>
                @endif
                @if(auth()->user()->hasMenuAccess('attendance_period_lock'))
                <a href="{{ route('attendance-period-locks.index') }}" class="btn btn-outline-danger btn-sm me-2">
                    <i class="fas fa-lock me-1"></i>
                    Closing
                </a>
                @endif
                <a href="{{ route('data-presensi.face-review.index') }}" class="btn btn-primary btn-sm me-2">
                    <i class="fas fa-user-check me-1"></i>
                    Review Wajah
                </a>
                @endif
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnExport">
                    <i class="fas fa-file-export me-1"></i>
                    Export Excel
                </button>
            </div>
        </div>

        <form class="row g-2 mb-3 align-items-end attendance-filter presensi-filter">
            <div class="col-md-2">
                <label class="form-label">Perusahaan</label>
                <select
                    id="filter_area"
                    class="form-select form-control js-presensi-company-select"
                    multiple
                    data-placeholder="Pilih perusahaan">
                    @foreach ($areas as $area)
                    <option value="{{ $area->kode_perusahaan }}">{{ $area->kode_perusahaan }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Departemen</label>
                <select id="filter_departemen" class="form-select form-control" disabled>
                    <option value="">Pilih Perusahaan Dahulu</option>
                    @php
                        $groupedDepartments = [];
                        foreach ($departemens as $department) {
                            $companyName = optional($department->perusahaan)->nama_perusahaan ?? 'Lainnya';
                            $groupedDepartments[$companyName][] = $department;
                        }
                    @endphp

                    @foreach($groupedDepartments as $companyName => $departmentItems)
                    <optgroup label="{{ $companyName }}">
                        @foreach($departmentItems as $department)
                        <option value="{{ $department->id }}">{{ $department->departemen }}</option>
                        @endforeach
                    </optgroup>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Divisi</label>
                <select id="filter_divisi" class="form-select form-control" disabled>
                    <option value="">Semua Divisi</option>
                    @foreach ($divisis as $division)
                    <option value="{{ $division->id }}">{{ $division->nama_divisi }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Cutoff</label>
                <input type="month" id="cutoff_month" class="form-control">
            </div>

            <div class="col-md-2 d-grid">
                <button type="button" id="btnFilter" class="btn btn-primary btn-sm">
                    Tampilkan
                </button>
            </div>
        </form>

        <div class="attendance-legend presensi-legend">
            <span><strong>M/I/K/P</strong> = Masuk, Istirahat, Kembali, Pulang</span>
            <span><span class="attendance-legend-dot is-sunday"></span> Minggu</span>
            <span><span class="attendance-legend-dot is-national-holiday"></span> Libur nasional</span>
            <span><span class="presensi-status-pill presensi-status-pill--ct">CT</span> Cuti Tahunan</span>
            <span><span class="presensi-status-pill presensi-status-pill--cr">CR</span> Cuti Roster</span>
            <span><span class="presensi-status-pill presensi-status-pill--ip">I/P</span> Izin Berbayar</span>
            <span><span class="presensi-status-pill presensi-status-pill--iu">I/U</span> Izin Tidak Berbayar</span>
            <span><span class="presensi-verification-chip is-verified">SV</span> Verifikasi server</span>
            <span><span class="presensi-verification-chip is-review">RV</span> Review per jam</span>
            <span><span class="presensi-verification-chip is-rejected">RJ</span> Ditolak</span>
        </div>

        <div class="alert alert-light border small presensi-empty-hint" id="presensiHint">
            Pilih perusahaan, departemen, dan cutoff terlebih dahulu. Setelah itu data presensi akan dimuat dengan server-side DataTables agar tetap ringan untuk data besar.
        </div>

        <div class="card border-0 attendance-card presensi-card">
            <div class="card-body p-0">
                <div class="presensi-table-shell">
                    <table class="table table-bordered table-sm align-middle mb-0 presensi-data-table" id="table-presensi"></table>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ versioned_asset('assets/js/plugin/select2/select2.full.min.js') }}"></script>
<script src="{{ versioned_asset('assets/js/admin-presensi.js') }}"></script>
@endpush

@endsection
