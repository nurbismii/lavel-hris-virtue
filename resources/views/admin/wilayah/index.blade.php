@extends('layouts.app')

@php
    $exportQuery = ['area_kerja' => $filters['area_kerja']];

    foreach (['provinsi_id', 'kabupaten_id', 'kecamatan_id', 'kelurahan_id'] as $filterKey) {
        if (filled($filters[$filterKey])) {
            $exportQuery[$filterKey] = $filters[$filterKey];
        }
    }

    $sultraRegionTotal = data_get(collect($regionBreakdown)->firstWhere('label', 'Sulawesi Tenggara'), 'total', 0);
    $totalGender = max(1, $summary['laki_laki'] + $summary['perempuan']);
    $malePercent = round(($summary['laki_laki'] / $totalGender) * 100, 1);
    $femalePercent = round(($summary['perempuan'] / $totalGender) * 100, 1);
@endphp

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/admin-wilayah.css') }}">
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner wilayah-page">
        <div class="d-flex align-items-start align-items-md-center flex-column flex-md-row gap-3">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-map-marked-alt text-primary me-2"></i>
                    Distribusi Wilayah
                </h4>
                <small class="text-muted">Analisis asal wilayah karyawan aktif dengan fokus Sulawesi Tenggara dan kecamatan prioritas.</small>
            </div>
            <div class="ms-md-auto d-flex flex-wrap gap-2">
                <a href="{{ route('distribusi.export', $exportQuery) }}" class="btn btn-outline-primary">
                    <i class="fas fa-file-export me-1"></i>
                    Export CSV
                </a>
                <a href="{{ route('distribusi.export-excel', $exportQuery) }}" class="btn btn-primary">
                    <i class="fas fa-file-excel me-1"></i>
                    Export Excel
                </a>
                <a href="{{ route('distribusi.index') }}" class="btn btn-light border">
                    Reset Filter
                </a>
            </div>
        </div>

        <div class="wilayah-filter-card p-4">
            <form method="GET" action="{{ route('distribusi.index') }}" class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold">Area Kerja</label>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($areaKerjaOptions as $area)
                            <label class="wilayah-filter-chip">
                                <input type="checkbox" name="area_kerja[]" value="{{ $area }}" {{ in_array($area, $filters['area_kerja'], true) ? 'checked' : '' }}>
                                <span>{{ $area }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Provinsi</label>
                    <select name="provinsi_id" id="wilayah_provinsi" class="form-select">
                        <option value="">Semua Provinsi</option>
                        @foreach($provinsiOptions as $provinsi)
                            <option value="{{ $provinsi->id }}" {{ (string) $filters['provinsi_id'] === (string) $provinsi->id ? 'selected' : '' }}>
                                {{ $provinsi->provinsi }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Kabupaten</label>
                    <select name="kabupaten_id" id="wilayah_kabupaten" class="form-select" {{ !$filters['provinsi_id'] ? 'disabled' : '' }}>
                        <option value="">Semua Kabupaten</option>
                        @foreach($kabupatenOptions as $kabupaten)
                            <option value="{{ $kabupaten->id }}" {{ (string) $filters['kabupaten_id'] === (string) $kabupaten->id ? 'selected' : '' }}>
                                {{ $kabupaten->kabupaten }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Kecamatan</label>
                    <select name="kecamatan_id" id="wilayah_kecamatan" class="form-select" {{ !$filters['kabupaten_id'] ? 'disabled' : '' }}>
                        <option value="">Semua Kecamatan</option>
                        @foreach($kecamatanOptions as $kecamatan)
                            <option value="{{ $kecamatan->id }}" {{ (string) $filters['kecamatan_id'] === (string) $kecamatan->id ? 'selected' : '' }}>
                                {{ $kecamatan->kecamatan }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Kelurahan</label>
                    <select name="kelurahan_id" id="wilayah_kelurahan" class="form-select" {{ !$filters['kecamatan_id'] ? 'disabled' : '' }}>
                        <option value="">Semua Kelurahan</option>
                        @foreach($kelurahanOptions as $kelurahan)
                            <option value="{{ $kelurahan->id }}" {{ (string) $filters['kelurahan_id'] === (string) $kelurahan->id ? 'selected' : '' }}>
                                {{ $kelurahan->kelurahan }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i>
                        Terapkan Filter
                    </button>
                    <a href="{{ route('distribusi.index') }}" class="btn btn-light border">Hapus Filter</a>
                </div>
            </form>
        </div>

        <div class="row g-3">
            <div class="col-md-3">
                <div class="wilayah-summary-card p-4 h-100">
                    <div class="wilayah-summary-card__label">Total Karyawan Aktif</div>
                    <div class="wilayah-summary-card__value">{{ number_format($summary['total']) }}</div>
                    <div class="wilayah-summary-card__meta">Sesuai filter area dan wilayah yang dipilih.</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="wilayah-summary-card p-4 h-100">
                    <div class="wilayah-summary-card__label">Porsi Sulawesi Tenggara</div>
                    <div class="wilayah-summary-card__value">{{ number_format($insights['share_sultra'], 1) }}%</div>
                    <div class="wilayah-summary-card__meta">{{ number_format($sultraRegionTotal) }} karyawan berasal dari Sulawesi Tenggara.</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="wilayah-summary-card p-4 h-100">
                    <div class="wilayah-summary-card__label">Wilayah Lengkap</div>
                    <div class="wilayah-summary-card__value">{{ number_format($summary['wilayah_lengkap_persen'], 1) }}%</div>
                    <div class="wilayah-summary-card__meta">{{ number_format($summary['wilayah_lengkap']) }} lengkap, {{ number_format($summary['wilayah_belum_lengkap']) }} masih perlu dilengkapi.</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="wilayah-summary-card p-4 h-100">
                    <div class="wilayah-summary-card__label">Kabupaten Sultra Terbesar</div>
                    <div class="wilayah-summary-card__value" style="font-size: 1.25rem;">{{ $insights['leading_sultra_kabupaten'] }}</div>
                    <div class="wilayah-summary-card__meta">{{ number_format($insights['leading_sultra_kabupaten_total']) }} karyawan pada kabupaten terbesar di Sultra.</div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="wilayah-summary-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="wilayah-summary-card__label">{{ $kabupatenSummary['title'] }}</div>
                            <div class="wilayah-summary-card__value" style="font-size: 1.35rem;">{{ $kabupatenSummary['label'] }}</div>
                        </div>
                        <span class="wilayah-badge">{{ $kabupatenSummary['badge'] }}</span>
                    </div>
                    <div class="wilayah-summary-card__meta mt-2">
                        Provinsi asal: {{ $kabupatenSummary['parent_label'] }}
                    </div>
                    <div class="wilayah-summary-card__tagline mt-1">
                        Ringkasan kabupaten paling relevan sesuai filter aktif saat ini.
                    </div>
                    <div class="wilayah-summary-card__stat-grid">
                        <div class="wilayah-summary-card__stat-item">
                            <div class="wilayah-summary-card__stat-label">Total Karyawan</div>
                            <div class="wilayah-summary-card__stat-value">{{ number_format($kabupatenSummary['total']) }}</div>
                        </div>
                        <div class="wilayah-summary-card__stat-item">
                            <div class="wilayah-summary-card__stat-label">{{ $kabupatenSummary['coverage_label'] }}</div>
                            <div class="wilayah-summary-card__stat-value">{{ number_format($kabupatenSummary['coverage_total']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="wilayah-summary-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="wilayah-summary-card__label">{{ $kecamatanSummary['title'] }}</div>
                            <div class="wilayah-summary-card__value" style="font-size: 1.35rem;">{{ $kecamatanSummary['label'] }}</div>
                        </div>
                        <span class="wilayah-badge">{{ $kecamatanSummary['badge'] }}</span>
                    </div>
                    <div class="wilayah-summary-card__meta mt-2">
                        Kabupaten asal: {{ $kecamatanSummary['parent_label'] }} · {{ $kecamatanSummary['province_label'] }}
                    </div>
                    <div class="wilayah-summary-card__tagline mt-1">
                        Ringkasan kecamatan yang paling dominan atau sedang dipilih pada filter.
                    </div>
                    <div class="wilayah-summary-card__stat-grid">
                        <div class="wilayah-summary-card__stat-item">
                            <div class="wilayah-summary-card__stat-label">Total Karyawan</div>
                            <div class="wilayah-summary-card__stat-value">{{ number_format($kecamatanSummary['total']) }}</div>
                        </div>
                        <div class="wilayah-summary-card__stat-item">
                            <div class="wilayah-summary-card__stat-label">{{ $kecamatanSummary['coverage_label'] }}</div>
                            <div class="wilayah-summary-card__stat-value">{{ number_format($kecamatanSummary['coverage_total']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="wilayah-chart-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="mb-1 fw-semibold">Pie Regional</h5>
                            <div class="wilayah-chart-card__hint">Distribusi tiga kelompok besar: Sulawesi, Sulawesi Tenggara, dan Non Sulawesi.</div>
                        </div>
                        <span class="wilayah-badge">Regional</span>
                    </div>
                    <div class="wilayah-chart-stage">
                        <canvas id="chartRegionalPie"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="wilayah-chart-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="mb-1 fw-semibold">Top 5 Kabupaten Sultra</h5>
                            <div class="wilayah-chart-card__hint">Kabupaten di Provinsi Sulawesi Tenggara dengan jumlah karyawan terbanyak.</div>
                        </div>
                        <span class="wilayah-badge">Top 5</span>
                    </div>
                    <div class="wilayah-chart-stage">
                        <canvas id="chartSultraKabupaten"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="wilayah-chart-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="mb-1 fw-semibold">3 Kecamatan Fokus</h5>
                            <div class="wilayah-chart-card__hint">Bondoala, Morosi, dan Kapoiala untuk memantau konsentrasi karyawan di area operasional utama.</div>
                        </div>
                        <span class="wilayah-badge">Fokus</span>
                    </div>
                    <div class="wilayah-chart-stage">
                        <canvas id="chartFocusKecamatan"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="wilayah-chart-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="mb-1 fw-semibold">Seluruh Sulawesi + Gorontalo</h5>
                            <div class="wilayah-chart-card__hint">Distribusi karyawan aktif dari enam provinsi utama yang paling relevan dengan operasional wilayah timur.</div>
                        </div>
                        <span class="wilayah-badge">Provinsi</span>
                    </div>
                    <div class="wilayah-chart-stage">
                        <canvas id="chartSulawesiGorontalo"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="wilayah-insight-card p-4 h-100">
                    <h5 class="mb-3 fw-semibold">Insight Tambahan</h5>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="wilayah-insight-card__meta">Komposisi Gender</span>
                            <span class="wilayah-insight-card__meta">{{ number_format($summary['laki_laki']) }} L / {{ number_format($summary['perempuan']) }} P</span>
                        </div>
                        <div class="wilayah-gender-progress d-flex">
                            <div class="wilayah-gender-progress__male" style="width: {{ $malePercent }}%;"></div>
                            <div class="wilayah-gender-progress__female" style="width: {{ $femalePercent }}%;"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <small class="text-muted">Laki-laki {{ number_format($malePercent, 1) }}%</small>
                            <small class="text-muted">Perempuan {{ number_format($femalePercent, 1) }}%</small>
                        </div>
                    </div>

                    <div class="wilayah-insight-list">
                        <div class="wilayah-insight-item">
                            <div class="wilayah-insight-item__label">Fokus 3 Kecamatan</div>
                            <div class="wilayah-insight-item__value">{{ number_format($insights['focus_kecamatan_total']) }} karyawan</div>
                            <div class="wilayah-insight-card__meta">Total karyawan yang saat ini tercatat di Bondoala, Morosi, dan Kapoiala.</div>
                        </div>
                        <div class="wilayah-insight-item">
                            <div class="wilayah-insight-item__label">Area Kerja Aktif</div>
                            <div class="wilayah-insight-item__value">{{ implode(', ', $filters['area_kerja']) }}</div>
                            <div class="wilayah-insight-card__meta">Chart dan export selalu mengikuti kombinasi area kerja yang aktif saat ini.</div>
                        </div>
                        <div class="wilayah-insight-item">
                            <div class="wilayah-insight-item__label">Sulawesi + Gorontalo</div>
                            <div class="wilayah-insight-item__value">{{ number_format($insights['sulawesi_gorontalo_total']) }} karyawan</div>
                            <div class="wilayah-insight-card__meta">Akumulasi karyawan yang berasal dari seluruh Sulawesi dan Gorontalo pada hasil filter aktif.</div>
                        </div>
                        <div class="wilayah-insight-item">
                            <div class="wilayah-insight-item__label">Saran Tindak Lanjut</div>
                            <div class="wilayah-insight-item__value">Lengkapi data wilayah yang kosong</div>
                            <div class="wilayah-insight-card__meta">Masih ada {{ number_format($summary['wilayah_belum_lengkap']) }} karyawan yang wilayahnya belum lengkap.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wilayah-table-card p-4">
            <div class="d-flex justify-content-between align-items-start flex-column flex-md-row gap-2 mb-3">
                <div>
                    <h5 class="mb-1 fw-semibold">Ringkasan Distribusi Detail</h5>
                    <div class="text-muted small">Tabel ini mengikuti filter aktif. Gunakan tombol export untuk mengunduh detail data karyawan per wilayah.</div>
                </div>
                <span class="wilayah-badge">{{ number_format(count($distributionRows)) }} baris agregat</span>
            </div>

            <div class="table-responsive">
                <table id="wilayah-aggregate-table" class="table table-bordered table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Provinsi</th>
                            <th>Kabupaten</th>
                            <th>Kecamatan</th>
                            <th>Kelurahan</th>
                            <th class="text-end">L</th>
                            <th class="text-end">P</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($distributionRows as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $row['provinsi'] }}</td>
                                <td>{{ $row['kabupaten'] }}</td>
                                <td>{{ $row['kecamatan'] }}</td>
                                <td>{{ $row['kelurahan'] }}</td>
                                <td class="text-end">{{ number_format($row['laki_laki']) }}</td>
                                <td class="text-end">{{ number_format($row['perempuan']) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($row['total']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Belum ada data yang cocok dengan filter yang dipilih.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const regionData = @json($regionBreakdown);
        const sultraKabupatenData = @json($topSultraKabupaten);
        const focusKecamatanData = @json($focusKecamatan);
        const sulawesiGorontaloData = @json($sulawesiGorontaloBreakdown);
        const formatAxisLabel = function(label) {
            return String(label).includes(' ') ? String(label).split(' ') : String(label);
        };
        const calculateTotal = function(values) {
            return values.reduce((carry, value) => carry + (Number(value) || 0), 0);
        };
        const formatPercentLabel = function(value, total) {
            if (!total || !value) {
                return null;
            }

            const percentage = (Number(value) / total) * 100;

            return `${percentage.toFixed(1)}%`;
        };
        const softGridOptions = {
            color: 'rgba(148, 163, 184, 0.18)',
            zeroLineColor: 'rgba(148, 163, 184, 0.22)',
            drawBorder: false
        };

        Chart.plugins.register({
            afterDatasetsDraw: function(chart) {
                const percentageOptions = chart.config.options.percentageLabels;

                if (!percentageOptions || percentageOptions.display === false) {
                    return;
                }

                const datasetIndex = percentageOptions.datasetIndex || 0;
                const dataset = chart.config.data.datasets[datasetIndex];
                const meta = chart.getDatasetMeta(datasetIndex);

                if (!dataset || !meta || !meta.data || !meta.data.length) {
                    return;
                }

                const total = percentageOptions.total || calculateTotal(dataset.data || []);

                if (!total) {
                    return;
                }

                const ctx = chart.chart.ctx;
                const chartType = chart.config.type;

                ctx.save();
                ctx.font = percentageOptions.font || '600 11px sans-serif';
                ctx.fillStyle = percentageOptions.color || '#0f172a';
                ctx.strokeStyle = percentageOptions.strokeColor || 'rgba(255, 255, 255, 0.85)';
                ctx.lineWidth = percentageOptions.strokeWidth || 3;

                meta.data.forEach(function(element, index) {
                    const value = Number(dataset.data[index]) || 0;
                    const label = formatPercentLabel(value, total);

                    if (!label) {
                        return;
                    }

                    const position = element.tooltipPosition();

                    if (chartType === 'horizontalBar') {
                        ctx.textAlign = 'left';
                        ctx.textBaseline = 'middle';
                        ctx.strokeText(label, position.x + 10, position.y);
                        ctx.fillText(label, position.x + 10, position.y);
                        return;
                    }

                    if (chartType === 'pie') {
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.strokeText(label, position.x, position.y);
                        ctx.fillText(label, position.x, position.y);
                        return;
                    }

                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'bottom';
                    ctx.strokeText(label, position.x, position.y - 8);
                    ctx.fillText(label, position.x, position.y - 8);
                });

                ctx.restore();
            }
        });

        new Chart(document.getElementById('chartRegionalPie'), {
            type: 'pie',
            data: {
                labels: regionData.map(item => item.label),
                datasets: [{
                    data: regionData.map(item => item.total),
                    backgroundColor: ['#0f766e', '#0ea5e9', '#f97316'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                percentageLabels: {
                    display: true,
                    total: calculateTotal(regionData.map(item => item.total)),
                    color: '#ffffff',
                    strokeColor: 'rgba(15, 23, 42, 0.45)',
                    strokeWidth: 4,
                    font: '700 12px sans-serif'
                },
                tooltips: {
                    callbacks: {
                        label: function(tooltipItem, data) {
                            const value = Number(data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index]) || 0;
                            const total = calculateTotal(data.datasets[tooltipItem.datasetIndex].data || []);
                            const percent = formatPercentLabel(value, total);

                            return `${data.labels[tooltipItem.index]}: ${value} (${percent})`;
                        }
                    }
                }
            }
        });

        new Chart(document.getElementById('chartSultraKabupaten'), {
            type: 'horizontalBar',
            data: {
                labels: sultraKabupatenData.map(item => item.label),
                datasets: [{
                    label: 'Jumlah Karyawan',
                    data: sultraKabupatenData.map(item => item.total),
                    backgroundColor: '#1d4ed8',
                    borderColor: 'transparent',
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: {
                        right: 54
                    }
                },
                percentageLabels: {
                    display: true,
                    total: calculateTotal(sultraKabupatenData.map(item => item.total))
                },
                scales: {
                    xAxes: [{
                        gridLines: softGridOptions,
                        ticks: {
                            beginAtZero: true,
                            precision: 0
                        }
                    }],
                    yAxes: [{
                        gridLines: {
                            display: false
                        }
                    }]
                },
                legend: {
                    display: false
                },
                tooltips: {
                    callbacks: {
                        label: function(tooltipItem, data) {
                            const value = Number(data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index]) || 0;
                            const total = calculateTotal(data.datasets[tooltipItem.datasetIndex].data || []);
                            const percent = formatPercentLabel(value, total);

                            return `${value} karyawan (${percent})`;
                        }
                    }
                }
            }
        });

        new Chart(document.getElementById('chartFocusKecamatan'), {
            type: 'bar',
            data: {
                labels: focusKecamatanData.map(item => item.label),
                datasets: [{
                    label: 'Jumlah Karyawan',
                    data: focusKecamatanData.map(item => item.total),
                    backgroundColor: ['#f59e0b', '#ef4444', '#8b5cf6'],
                    borderColor: 'transparent',
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: {
                        top: 24
                    }
                },
                percentageLabels: {
                    display: true,
                    total: calculateTotal(focusKecamatanData.map(item => item.total))
                },
                scales: {
                    xAxes: [{
                        gridLines: {
                            display: false
                        }
                    }],
                    yAxes: [{
                        gridLines: softGridOptions,
                        ticks: {
                            beginAtZero: true,
                            precision: 0
                        }
                    }]
                },
                legend: {
                    display: false
                },
                tooltips: {
                    callbacks: {
                        label: function(tooltipItem, data) {
                            const value = Number(data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index]) || 0;
                            const total = calculateTotal(data.datasets[tooltipItem.datasetIndex].data || []);
                            const percent = formatPercentLabel(value, total);

                            return `${value} karyawan (${percent})`;
                        }
                    }
                }
            }
        });

        new Chart(document.getElementById('chartSulawesiGorontalo'), {
            type: 'bar',
            data: {
                labels: sulawesiGorontaloData.map(item => formatAxisLabel(item.label)),
                datasets: [{
                    label: 'Jumlah Karyawan',
                    data: sulawesiGorontaloData.map(item => item.total),
                    backgroundColor: '#0f766e',
                    borderColor: 'transparent',
                    borderWidth: 0,
                    barPercentage: 0.62,
                    categoryPercentage: 0.74
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: {
                        top: 28
                    }
                },
                percentageLabels: {
                    display: true,
                    total: calculateTotal(sulawesiGorontaloData.map(item => item.total))
                },
                scales: {
                    xAxes: [{
                        gridLines: {
                            display: false
                        },
                        ticks: {
                            autoSkip: false,
                            maxRotation: 0,
                            minRotation: 0,
                            fontSize: 11,
                            padding: 8
                        }
                    }],
                    yAxes: [{
                        gridLines: softGridOptions,
                        ticks: {
                            beginAtZero: true,
                            precision: 0
                        }
                    }]
                },
                legend: {
                    display: false
                },
                tooltips: {
                    callbacks: {
                        label: function(tooltipItem, data) {
                            const value = Number(data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index]) || 0;
                            const total = calculateTotal(data.datasets[tooltipItem.datasetIndex].data || []);
                            const percent = formatPercentLabel(value, total);

                            return `${value} karyawan (${percent})`;
                        }
                    }
                }
            }
        });

        const provinsiSelect = document.getElementById('wilayah_provinsi');
        const kabupatenSelect = document.getElementById('wilayah_kabupaten');
        const kecamatanSelect = document.getElementById('wilayah_kecamatan');
        const kelurahanSelect = document.getElementById('wilayah_kelurahan');
        const selectedKabupaten = @json($filters['kabupaten_id']);
        const selectedKecamatan = @json($filters['kecamatan_id']);
        const selectedKelurahan = @json($filters['kelurahan_id']);

        function resetSelect(element, placeholder) {
            element.innerHTML = `<option value="">${placeholder}</option>`;
            element.disabled = true;
        }

        function fillSelect(element, items, placeholder, valueKey, labelKey, selectedValue) {
            let options = `<option value="">${placeholder}</option>`;

            items.forEach((item) => {
                const value = String(item[valueKey]);
                const selected = selectedValue && String(selectedValue) === value ? 'selected' : '';
                options += `<option value="${value}" ${selected}>${item[labelKey]}</option>`;
            });

            element.innerHTML = options;
            element.disabled = items.length === 0;
        }

        provinsiSelect.addEventListener('change', function() {
            const provinsiId = this.value;
            resetSelect(kabupatenSelect, 'Semua Kabupaten');
            resetSelect(kecamatanSelect, 'Semua Kecamatan');
            resetSelect(kelurahanSelect, 'Semua Kelurahan');

            if (!provinsiId) {
                return;
            }

            fetch(`{{ url('wilayah/kabupatens') }}/${provinsiId}`)
                .then(response => response.json())
                .then(data => {
                    fillSelect(kabupatenSelect, data, 'Semua Kabupaten', 'id', 'kabupaten', null);
                });
        });

        kabupatenSelect.addEventListener('change', function() {
            const kabupatenId = this.value;
            resetSelect(kecamatanSelect, 'Semua Kecamatan');
            resetSelect(kelurahanSelect, 'Semua Kelurahan');

            if (!kabupatenId) {
                return;
            }

            fetch(`{{ url('wilayah/kecamatans') }}/${kabupatenId}`)
                .then(response => response.json())
                .then(data => {
                    fillSelect(kecamatanSelect, data, 'Semua Kecamatan', 'id', 'kecamatan', null);
                });
        });

        kecamatanSelect.addEventListener('change', function() {
            const kecamatanId = this.value;
            resetSelect(kelurahanSelect, 'Semua Kelurahan');

            if (!kecamatanId) {
                return;
            }

            fetch(`{{ url('wilayah/kelurahans') }}/${kecamatanId}`)
                .then(response => response.json())
                .then(data => {
                    fillSelect(kelurahanSelect, data, 'Semua Kelurahan', 'id', 'kelurahan', null);
                });
        });

        if (provinsiSelect.value && selectedKabupaten && kabupatenSelect.options.length <= 1) {
            fetch(`{{ url('wilayah/kabupatens') }}/${provinsiSelect.value}`)
                .then(response => response.json())
                .then(data => {
                    fillSelect(kabupatenSelect, data, 'Semua Kabupaten', 'id', 'kabupaten', selectedKabupaten);

                    if (selectedKabupaten && selectedKecamatan) {
                        return fetch(`{{ url('wilayah/kecamatans') }}/${selectedKabupaten}`);
                    }
                })
                .then(response => response ? response.json() : [])
                .then(data => {
                    if (!selectedKabupaten || !selectedKecamatan) {
                        return;
                    }

                    fillSelect(kecamatanSelect, data, 'Semua Kecamatan', 'id', 'kecamatan', selectedKecamatan);

                    if (selectedKelurahan) {
                        return fetch(`{{ url('wilayah/kelurahans') }}/${selectedKecamatan}`);
                    }
                })
                .then(response => response ? response.json() : [])
                .then(data => {
                    if (!selectedKecamatan || !selectedKelurahan) {
                        return;
                    }

                    fillSelect(kelurahanSelect, data, 'Semua Kelurahan', 'id', 'kelurahan', selectedKelurahan);
                });
        }

        $('#wilayah-aggregate-table').DataTable({
            pageLength: 15,
            lengthChange: false,
            ordering: true,
            responsive: true,
            order: [[7, 'desc']],
            language: {
                search: 'Cari:',
                paginate: {
                    previous: 'Sebelumnya',
                    next: 'Berikutnya'
                },
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ baris',
                infoEmpty: 'Belum ada data',
                zeroRecords: 'Data tidak ditemukan'
            }
        });
    });
</script>
@endpush
