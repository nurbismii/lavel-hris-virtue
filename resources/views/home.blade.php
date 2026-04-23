@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/home-dashboard.css') }}">
@endpush

@section('content')

<div class="container-fluid dashboard-home-page"
    data-age-labels='@json(collect($rentangUmur)->pluck('label')->values())'
    data-age-totals='@json(collect($rentangUmur)->pluck('total')->values())'
    data-monthly-labels='@json(collect($summaryBulanan)->pluck('label')->values())'
    data-monthly-masuk='@json(collect($summaryBulanan)->pluck('masuk')->values())'
    data-monthly-keluar='@json(collect($summaryBulanan)->pluck('keluar')->values())'
    data-gender-l="{{ $gender['L'] ?? 0 }}"
    data-gender-p="{{ $gender['P'] ?? 0 }}"
    data-masuk="{{ $masuk }}"
    data-keluar="{{ $keluar }}">
    <div class="page-inner">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">
                    <i class="fas fa-home text-primary me-2"></i>
                    Dashboard
                </h3>
                <small class="text-muted">
                    Dashboard pertanggal :{{ formatDateIndonesia($start) }} - {{ formatDateIndonesia($end) }}
                </small>
            </div>
        </div>

        <form method="GET" action="{{ route('home') }}" class="mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">Tanggal Mulai</label>
                    <input
                        type="date"
                        name="start"
                        class="form-control"
                        value="{{ request('start', $start) }}"
                        required>
                </div>

                <div class="col-md-5">
                    <label class="form-label">Tanggal Akhir</label>
                    <input
                        type="date"
                        name="end"
                        class="form-control"
                        value="{{ request('end', $end) }}"
                        required>
                </div>

                <div class="col-md-2">
                    <button class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Terapkan
                    </button>
                </div>
            </div>
        </form>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                    <div>
                        <h5 class="mb-1 fw-semibold">Progress Upload Dokumen</h5>
                        <small class="text-muted">Semua proses bulk upload ditampilkan di sini dan diperbarui otomatis setiap 5 detik.</small>
                    </div>
                    <span class="badge bg-light text-dark border">Queue {{ config('queue.connections.' . config('queue.default') . '.queue', 'default') }}</span>
                </div>
                <div id="dashboard-upload-progress-list"
                    data-progress-url="{{ route('home.upload-progress') }}"
                    data-delete-confirm="Hapus progress ini dari dashboard?">
                    @include('admin.karyawan.partials.upload-progress-cards', [
                        'items' => $uploadProgressStatuses,
                        'emptyMessage' => 'Belum ada progress upload yang berjalan atau baru selesai.'
                    ])
                </div>
            </div>
        </div>

        {{-- SUMMARY --}}
        <div class="row">
            <div class="col-md-3">
                <div class="card card-stats card-primary">
                    <div class="card-body">
                        <p>Total Karyawan Aktif</p>
                        <h4>{{ $totalAktif }}</h4>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card card-stats card-success">
                    <div class="card-body">
                        <p>Karyawan Masuk</p>
                        <h4>{{ $masuk }}</h4>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card card-stats card-danger">
                    <div class="card-body">
                        <p>Karyawan Keluar</p>
                        <h4>{{ $keluar }}</h4>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card card-stats card-warning">
                    <div class="card-body">
                        <p>Turnover</p>
                        <h4>{{ $turnover }}%</h4>
                    </div>
                </div>
            </div>
        </div>

        {{-- CHART --}}
        <div class="row">

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Jenis Kelamin</div>
                    <div class="card-body">
                        <canvas id="chartGender"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Masuk vs Keluar</div>
                    <div class="card-body">
                        <canvas id="chartMutasi"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Rentang Umur Karyawan</div>
                    <div class="card-body">
                        <canvas id="chartAgeRange"></canvas>
                        <small class="text-muted d-block mt-3">
                            Rentang umur ditampilkan per kelipatan 5 tahun mulai dari 17 tahun sampai 57 tahun ke atas.
                        </small>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Summary Masuk dan Keluar Bulanan {{ $summaryYear }}</div>
                    <div class="card-body">
                        <canvas id="chartMonthlyMutasi"></canvas>
                        <small class="text-muted d-block mt-3">
                            Cut off bulanan menggunakan periode tanggal 16 bulan sebelumnya sampai 15 bulan berjalan untuk setiap label bulan.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/js/home-dashboard.js') }}"></script>
@endpush
