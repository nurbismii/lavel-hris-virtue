@extends('layouts.app')

@section('content')

<div class="container-fluid">
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
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ageLabels = @json(collect($rentangUmur)->pluck('label')->values());
        const ageTotals = @json(collect($rentangUmur)->pluck('total')->values());
        const monthlyLabels = @json(collect($summaryBulanan)->pluck('label')->values());
        const monthlyMasuk = @json(collect($summaryBulanan)->pluck('masuk')->values());
        const monthlyKeluar = @json(collect($summaryBulanan)->pluck('keluar')->values());
        const softGridOptions = {
            color: 'rgba(148, 163, 184, 0.18)',
            zeroLineColor: 'rgba(148, 163, 184, 0.24)',
            drawBorder: false
        };

        // ===== CHART GENDER =====
        new Chart(document.getElementById('chartGender'), {
            type: 'pie',
            data: {
                labels: ['Laki-laki', 'Perempuan'],
                datasets: [{
                    data: [
                        {{ $gender['L'] ?? 0 }},
                        {{ $gender['P'] ?? 0 }}
                    ],
                    backgroundColor: ['#1d7af3', '#ee22dd'],
                    borderWidth: 0,
                    hoverBorderWidth: 0
                }]
            }
        });

        // ===== CHART MUTASI =====
        new Chart(document.getElementById('chartMutasi'), {
            type: 'bar',
            data: {
                labels: ['Masuk', 'Keluar'],
                datasets: [{
                    label: 'Jumlah Karyawan',
                    data: [
                        {{ $masuk }},
                        {{ $keluar }}
                    ],
                    backgroundColor: ['#1d7af3', '#f3545d'],
                    borderColor: 'transparent',
                    borderWidth: 0,
                    hoverBorderWidth: 0
                }]
            },
            options: {
                scales: {
                    xAxes: [{
                        gridLines: softGridOptions
                    }],
                    yAxes: [{
                        gridLines: softGridOptions,
                        ticks: {
                            beginAtZero: true, // 🔥 INI KUNCI UTAMA
                            precision: 0,
                            maxTicksLimit: 6
                        }
                    }]
                }
            }
        });

        // ===== CHART AGE RANGE =====
        new Chart(document.getElementById('chartAgeRange'), {
            type: 'bar',
            data: {
                labels: ageLabels,
                datasets: [{
                    label: 'Jumlah Karyawan',
                    data: ageTotals,
                    backgroundColor: '#1572e8',
                    borderColor: 'transparent',
                    borderWidth: 0,
                    hoverBorderWidth: 0
                }]
            },
            options: {
                scales: {
                    xAxes: [{
                        gridLines: softGridOptions
                    }],
                    yAxes: [{
                        gridLines: softGridOptions,
                        ticks: {
                            beginAtZero: true,
                            precision: 0,
                            maxTicksLimit: 7
                        }
                    }]
                }
            }
        });

        // ===== CHART MONTHLY MUTATION =====
        new Chart(document.getElementById('chartMonthlyMutasi'), {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [{
                        label: 'Masuk',
                        data: monthlyMasuk,
                        backgroundColor: '#31ce36',
                        borderColor: 'transparent',
                        borderWidth: 0,
                        hoverBorderWidth: 0
                    },
                    {
                        label: 'Keluar',
                        data: monthlyKeluar,
                        backgroundColor: '#f3545d',
                        borderColor: 'transparent',
                        borderWidth: 0,
                        hoverBorderWidth: 0
                    }
                ]
            },
            options: {
                scales: {
                    xAxes: [{
                        gridLines: softGridOptions
                    }],
                    yAxes: [{
                        gridLines: softGridOptions,
                        ticks: {
                            beginAtZero: true,
                            precision: 0,
                            maxTicksLimit: 6
                        }
                    }]
                }
            }
        });

    });
</script>
@endpush
