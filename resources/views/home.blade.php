@extends('layouts.app')

@push('styles')
<style>
    .upload-progress-card {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
        height: 100%;
    }

    .upload-progress-card__meta {
        color: #64748b;
        font-size: 0.82rem;
    }

    .upload-progress-card__counts {
        font-size: 0.82rem;
        color: #475569;
    }

    .upload-progress-delete {
        text-decoration: none;
    }
</style>
@endpush

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

<script>
    (function() {
        const container = document.getElementById('dashboard-upload-progress-list');

        if (!container) {
            return;
        }

        const progressUrl = container.dataset.progressUrl;
        const deleteConfirmMessage = container.dataset.deleteConfirm || 'Hapus progress ini?';

        function statusBadge(item) {
            return `<span class="badge bg-${item.status_class}">${item.status_label}</span>`;
        }

        function deleteButton(item) {
            if (!item.delete_url) {
                return '';
            }

            return `
                <button
                    type="button"
                    class="btn btn-sm btn-link text-muted p-0 upload-progress-delete"
                    data-delete-url="${item.delete_url}"
                    aria-label="Hapus progress ${item.label}">
                    <i class="fas fa-times"></i>
                </button>
            `;
        }

        function renderItems(items) {
            if (!Array.isArray(items) || items.length === 0) {
                container.innerHTML = '<div class="alert alert-light border mb-0 small text-muted">Belum ada progress upload yang berjalan atau baru selesai.</div>';
                return;
            }

            container.innerHTML = `<div class="row g-3">${items.map((item) => `
                <div class="col-md-6 col-xl-4">
                    <div class="upload-progress-card p-3">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <div class="fw-semibold">${item.label}</div>
                                <div class="upload-progress-card__meta">Update ${item.updated_at_human}</div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                ${statusBadge(item)}
                                ${deleteButton(item)}
                            </div>
                        </div>
                        <div class="progress mb-2" style="height: 8px;">
                            <div class="progress-bar bg-${item.status_class}" role="progressbar" style="width: ${item.progress_percentage}%;" aria-valuenow="${item.progress_percentage}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between upload-progress-card__counts">
                            <span>${item.processed_entries}/${item.total_entries || 0} file</span>
                            <span>${item.progress_percentage}%</span>
                        </div>
                        <div class="upload-progress-card__meta mt-2">
                            Berhasil ${item.success_count} file, dilewati ${item.skipped_count} file.
                        </div>
                    </div>
                </div>
            `).join('')}</div>`;
        }

        async function refreshProgress() {
            try {
                const response = await fetch(progressUrl, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    }
                });

                if (!response.ok) {
                    return;
                }

                const data = await response.json();
                renderItems(data.items || []);
            } catch (error) {
                console.error('Gagal memuat progress upload dashboard.', error);
            }
        }

        container.addEventListener('click', async function(event) {
            const button = event.target.closest('.upload-progress-delete');

            if (!button) {
                return;
            }

            event.preventDefault();

            if (!window.confirm(deleteConfirmMessage)) {
                return;
            }

            try {
                const response = await fetch(button.dataset.deleteUrl, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    }
                });

                if (!response.ok) {
                    throw new Error('Gagal menghapus progress.');
                }

                await refreshProgress();
            } catch (error) {
                console.error(error);
                alert('Gagal menghapus progress upload.');
            }
        });

        window.setInterval(refreshProgress, 5000);
    })();
</script>
@endpush
