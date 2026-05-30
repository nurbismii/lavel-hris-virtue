@extends('layouts.app')

@section('title', __('navigation.central_monitor'))

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/central-monitor.css') }}">
@endpush

@php
    $statusMeta = [
        \App\Services\Monitoring\CentralMonitorService::STATUS_OK => [
            'label' => 'Normal',
            'class' => 'success',
            'icon' => 'fas fa-check-circle',
        ],
        \App\Services\Monitoring\CentralMonitorService::STATUS_WARNING => [
            'label' => 'Perlu dipantau',
            'class' => 'warning',
            'icon' => 'fas fa-exclamation-circle',
        ],
        \App\Services\Monitoring\CentralMonitorService::STATUS_CRITICAL => [
            'label' => 'Perlu tindakan',
            'class' => 'danger',
            'icon' => 'fas fa-exclamation-triangle',
        ],
    ];
    $health = $dashboard['health'];
    $healthMeta = $statusMeta[$health['status']] ?? $statusMeta[\App\Services\Monitoring\CentralMonitorService::STATUS_OK];
    $readinessLabels = [
        'attendance_period_locks' => 'Closing Presensi',
        'approval_sla_logs' => 'Log SLA Approval',
        'attendance_anomaly' => 'Anomali Presensi',
        'import_histories' => 'History Import',
        'audit_trails' => 'Audit Trail',
        'jobs' => 'Queue Job',
        'failed_jobs' => 'Failed Job',
    ];
    $inactiveModules = collect($readinessLabels)
        ->filter(fn($label, $key) => empty($dashboard['readiness'][$key]))
        ->values();
@endphp

@section('content')
<div class="container-fluid">
    <div class="page-inner central-monitor-page">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-3 gap-2">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-desktop text-primary me-2"></i>
                    {{ __('navigation.central_monitor') }}
                </h4>
                <small class="text-muted">
                    Periode {{ $dashboard['period']['label'] }} · Update {{ $health['updated_at'] }} WITA
                </small>
            </div>
            <div class="ms-md-auto pt-3 pt-md-0">
                <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
                    <input type="month" name="period_month" class="form-control form-control-sm central-monitor-period" value="{{ $dashboard['period']['month'] }}">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-sync-alt me-1"></i>
                        Refresh
                    </button>
                </form>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <strong>Filter tidak valid.</strong> {{ $errors->first() }}
            </div>
        @endif

        @if($inactiveModules->isNotEmpty())
            <div class="alert alert-warning">
                Modul monitor belum lengkap: {{ $inactiveModules->join(', ') }}. Jalankan migrasi terkait agar semua indikator aktif.
            </div>
        @endif

        <div class="central-monitor-health central-monitor-health--{{ $healthMeta['class'] }}">
            <div class="central-monitor-health__icon">
                <i class="{{ $healthMeta['icon'] }}"></i>
            </div>
            <div>
                <div class="small text-muted">Status operasional</div>
                <div class="h5 mb-0">{{ $health['label'] }}</div>
            </div>
            <div class="central-monitor-health__stats">
                <span>{{ number_format($health['critical_count']) }} kritis</span>
                <span>{{ number_format($health['warning_count']) }} warning</span>
            </div>
        </div>

        <div class="row g-3 mt-1">
            @foreach($dashboard['cards'] as $card)
                @php
                    $meta = $statusMeta[$card['status']] ?? $statusMeta[\App\Services\Monitoring\CentralMonitorService::STATUS_OK];
                @endphp
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="central-monitor-card central-monitor-card--{{ $meta['class'] }}">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <div class="central-monitor-card__title">{{ $card['title'] }}</div>
                                <div class="central-monitor-card__value">{{ number_format((int) $card['primary_value']) }}</div>
                                <div class="small text-muted">{{ $card['primary_label'] }}</div>
                            </div>
                            <span class="central-monitor-card__icon">
                                <i class="{{ $card['icon'] }}"></i>
                            </span>
                        </div>

                        <div class="central-monitor-card__description">
                            {{ $card['description'] }}
                        </div>

                        <div class="central-monitor-metrics">
                            @foreach($card['metrics'] as $metric)
                                <div class="central-monitor-metric central-monitor-metric--{{ $metric['tone'] ?? 'neutral' }}">
                                    <span>{{ $metric['label'] }}</span>
                                    <strong>{{ number_format((int) $metric['value']) }}</strong>
                                </div>
                            @endforeach
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            @if(!empty($card['url']))
                                <a href="{{ $card['url'] }}" class="btn btn-sm btn-outline-primary">
                                    Detail
                                </a>
                            @endif
                            @if(!empty($card['secondary_url']))
                                <a href="{{ $card['secondary_url'] }}" class="btn btn-sm btn-outline-secondary">
                                    Closing
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-3 mt-1">
            <div class="col-12 col-xl-6">
                <div class="central-monitor-panel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="mb-1">Import Terbaru</h5>
                            <small class="text-muted">Lima proses import terakhir.</small>
                        </div>
                        @if(auth()->user()->hasMenuAccess('import_history'))
                            <a href="{{ route('import-histories.index') }}" class="btn btn-sm btn-outline-primary">History</a>
                        @endif
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>File</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($dashboard['recent_imports'] as $import)
                                    <tr>
                                        <td>{{ optional($import->created_at)->format('d M H:i') }}</td>
                                        <td>
                                            <div class="fw-semibold text-truncate central-monitor-file">{{ $import->file_name ?: $import->import_id }}</div>
                                            <small class="text-muted">{{ $import->import_type_label }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $import->status_badge_class }}">{{ $import->status_label }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-muted text-center py-4">Belum ada riwayat import.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="central-monitor-panel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="mb-1">Audit Terbaru</h5>
                            <small class="text-muted">Lima aktivitas sistem terakhir.</small>
                        </div>
                        @if(auth()->user()->hasMenuAccess('audit_trail'))
                            <a href="{{ route('audit-trails.index') }}" class="btn btn-sm btn-outline-primary">Audit</a>
                        @endif
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>Event</th>
                                    <th>Actor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($dashboard['recent_audits'] as $audit)
                                    <tr>
                                        <td>{{ \Illuminate\Support\Carbon::parse($audit->created_at)->format('d M H:i') }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $audit->event }}</div>
                                            <small class="text-muted">{{ $audit->module }}{{ $audit->employee_nik ? ' · ' . $audit->employee_nik : '' }}</small>
                                        </td>
                                        <td>{{ $audit->actor_name ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-muted text-center py-4">Belum ada audit terbaru.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
