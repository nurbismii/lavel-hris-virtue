@extends('layouts.app')

@section('title', __('navigation.attendance_anomaly'))

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/admin-presensi-anomalies.css') }}">
@endpush

@php
    $summaryItems = array_merge([
        'total' => [
            'label' => 'Total anomali',
            'severity' => 'primary',
        ],
    ], $anomalyTypes);
@endphp

@section('content')
<div class="container-fluid">
    <div
        class="page-inner attendance-anomaly-page"
        data-source-url="{{ route('attendance-anomalies.data') }}">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-3">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                    {{ __('navigation.attendance_anomaly') }}
                </h4>
                <small class="text-muted d-block" id="anomalyPeriodLabel">
                    Periode {{ $filters['period_label'] }}
                </small>
            </div>

            <div class="ms-md-auto pt-3 pt-md-0">
                <a href="{{ route('data-presensi.index') }}" class="btn btn-outline-primary btn-sm me-2">
                    <i class="fas fa-table me-1"></i>
                    Data Presensi
                </a>
                <a href="{{ route('data-presensi.face-review.index') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-user-check me-1"></i>
                    Review Wajah
                </a>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <strong>Filter tidak valid.</strong>
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="anomalyFilterForm" class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label">Tanggal Awal</label>
                        <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tanggal Akhir</label>
                        <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Jenis Anomali</label>
                        <select name="anomaly" class="form-select form-control">
                            <option value="all" {{ $filters['anomaly'] === 'all' ? 'selected' : '' }}>Semua anomali</option>
                            @foreach($anomalyTypes as $key => $type)
                                <option value="{{ $key }}" {{ $filters['anomaly'] === $key ? 'selected' : '' }}>
                                    {{ $type['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Perusahaan</label>
                        <select
                            name="area[]"
                            class="form-select form-control js-anomaly-company-select"
                            multiple
                            data-placeholder="Pilih perusahaan">
                            @foreach($areas as $area)
                                <option value="{{ $area->kode_perusahaan }}" {{ in_array($area->kode_perusahaan, $filters['area'], true) ? 'selected' : '' }}>
                                    {{ $area->kode_perusahaan }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Departemen</label>
                        <select name="departemen_id" class="form-select form-control">
                            <option value="">Semua departemen</option>
                            @foreach($departemens as $departemen)
                                <option value="{{ $departemen->id }}" {{ (string) $filters['departemen_id'] === (string) $departemen->id ? 'selected' : '' }}>
                                    {{ $departemen->departemen }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Divisi</label>
                        <select name="divisi_id" class="form-select form-control">
                            <option value="">Semua divisi</option>
                            @foreach($divisis as $divisi)
                                <option value="{{ $divisi->id }}" {{ (string) $filters['divisi_id'] === (string) $divisi->id ? 'selected' : '' }}>
                                    {{ $divisi->nama_divisi }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary" data-original-text="Tampilkan">
                            <i class="fas fa-search me-1"></i>
                            Tampilkan
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div class="row g-3 mb-3 anomaly-summary">
            @foreach($summaryItems as $key => $item)
                @php
                    $value = (int) ($summary[$key] ?? 0);
                    $severity = $item['severity'] ?? 'secondary';
                @endphp
                <div class="col-6 col-md-3 col-xl-2">
                    <div class="anomaly-summary-card anomaly-summary-card--{{ $severity }}">
                        <span>{{ $item['label'] }}</span>
                        <strong data-anomaly-summary-key="{{ $key }}">{{ number_format($value) }}</strong>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm align-middle w-100" id="attendance-anomaly-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 105px;">Tanggal</th>
                                <th style="width: 120px;">NIK</th>
                                <th>Karyawan</th>
                                <th>Organisasi</th>
                                <th style="width: 190px;">Jam</th>
                                <th style="width: 150px;">Status</th>
                                <th>Anomali</th>
                                <th style="width: 180px;">GPS</th>
                                <th style="width: 90px;">Aksi</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/js/plugin/select2/select2.full.min.js') }}"></script>
<script src="{{ versioned_asset('assets/js/admin-presensi-anomalies.js') }}"></script>
@endpush
