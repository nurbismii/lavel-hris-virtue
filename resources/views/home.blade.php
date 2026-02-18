@extends('layouts.app')

@section('content')

<div class="container">
    <div class="page-inner">
        <h4 class="mb-2 mt-4">
            Dashboard
            <small class="text-muted">
                ({{ formatDateIndonesia($start) }} - {{ formatDateIndonesia($end) }})
            </small>

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
        </h4>

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
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {

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
                    backgroundColor: ['#1d7af3', '#ee22dd']
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
                    backgroundColor: ['#1d7af3', '#f3545d']
                }]
            },
            options: {
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true, // 🔥 INI KUNCI UTAMA
                            stepSize: 10
                        }
                    }]
                }
            }
        });

    });
</script>
@endpush