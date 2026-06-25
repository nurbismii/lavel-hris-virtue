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

    <div class="page-inner dashboard-inner">

        {{-- HERO HEADER --}}
        <div class="dashboard-hero mb-4">
            <div class="dashboard-hero-content">
                <div>
                    <span class="dashboard-badge">
                        <i class="fas fa-chart-line"></i>
                        V-People Dashboard
                    </span>

                    <h3 class="dashboard-title">
                        Dashboard HR
                    </h3>

                    <p class="dashboard-subtitle mb-0">
                        Ringkasan data karyawan aktif, mutasi, turnover, dan progress upload dokumen.
                    </p>

                    <div class="dashboard-period mt-3">
                        <i class="fas fa-calendar-alt"></i>
                        Dashboard pertanggal:
                        <strong>{{ formatDateIndonesia($start) }}</strong>
                        <span>-</span>
                        <strong>{{ formatDateIndonesia($end) }}</strong>
                    </div>
                </div>

                <div class="dashboard-hero-icon d-none d-md-flex">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>

        {{-- FILTER --}}
        <div class="card dashboard-card filter-card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('home') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label">Tanggal Mulai</label>
                            <div class="input-modern">
                                <i class="fas fa-calendar-check"></i>
                                <input
                                    type="date"
                                    name="start"
                                    class="form-control"
                                    value="{{ request('start', $start) }}"
                                    required>
                            </div>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label">Tanggal Akhir</label>
                            <div class="input-modern">
                                <i class="fas fa-calendar-check"></i>
                                <input
                                    type="date"
                                    name="end"
                                    class="form-control"
                                    value="{{ request('end', $end) }}"
                                    required>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <button class="btn btn-dashboard-primary w-100">
                                <i class="fas fa-filter me-1"></i>
                                Terapkan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- UPLOAD PROGRESS --}}
        <div class="card dashboard-card upload-card mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                    <div>
                        <div class="section-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            Upload Monitor
                        </div>

                        <h5 class="section-title mb-1">
                            Progress Upload Dokumen
                        </h5>

                        <small class="text-muted">
                            Semua proses bulk upload ditampilkan di sini dan diperbarui otomatis setiap 5 detik.
                        </small>
                    </div>

                    <span class="queue-badge">
                        <i class="fas fa-layer-group"></i>
                        Queue {{ config('queue.connections.' . config('queue.default') . '.queue', 'default') }}
                    </span>
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
        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="summary-card summary-primary">
                    <div class="summary-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div>
                        <p>Total Karyawan Aktif</p>
                        <h4>{{ $totalAktif }}</h4>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="summary-card summary-success">
                    <div class="summary-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div>
                        <p>Karyawan Masuk</p>
                        <h4>{{ $masuk }}</h4>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="summary-card summary-danger">
                    <div class="summary-icon">
                        <i class="fas fa-user-minus"></i>
                    </div>
                    <div>
                        <p>Karyawan Keluar</p>
                        <h4>{{ $keluar }}</h4>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="summary-card summary-warning">
                    <div class="summary-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div>
                        <p>Turnover</p>
                        <h4>{{ $turnover }}%</h4>
                    </div>
                </div>
            </div>
        </div>

        {{-- CHART ROW 1 --}}
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card dashboard-card chart-card">
                    <div class="card-header">
                        <div>
                            <span class="chart-label">Komposisi</span>
                            <h6>Jenis Kelamin</h6>
                        </div>
                        <i class="fas fa-venus-mars"></i>
                    </div>
                    <div class="card-body">
                        <div class="chart-wrapper">
                            <canvas id="chartGender"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card dashboard-card chart-card">
                    <div class="card-header">
                        <div>
                            <span class="chart-label">Periode Terpilih</span>
                            <h6>Masuk vs Keluar</h6>
                        </div>
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="card-body">
                        <div class="chart-wrapper">
                            <canvas id="chartMutasi"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- CHART ROW 2 --}}
        <div class="row g-3 mt-1">
            <div class="col-md-6">
                <div class="card dashboard-card chart-card">
                    <div class="card-header">
                        <div>
                            <span class="chart-label">Demografi</span>
                            <h6>Rentang Umur Karyawan</h6>
                        </div>
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="card-body">
                        <div class="chart-wrapper">
                            <canvas id="chartAgeRange"></canvas>
                        </div>

                        <small class="chart-note">
                            Rentang umur ditampilkan per kelipatan 5 tahun mulai dari 17 tahun sampai 57 tahun ke atas.
                        </small>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card dashboard-card chart-card">
                    <div class="card-header">
                        <div>
                            <span class="chart-label">Tahun {{ $summaryYear }}</span>
                            <h6>Summary Masuk dan Keluar Bulanan</h6>
                        </div>
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="card-body">
                        <div class="chart-wrapper">
                            <canvas id="chartMonthlyMutasi"></canvas>
                        </div>

                        <small class="chart-note">
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
