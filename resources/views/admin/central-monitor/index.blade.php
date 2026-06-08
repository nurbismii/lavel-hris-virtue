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
    <div class="page-inner ui-page central-monitor-page">
        <div class="ui-page-header">
            <div class="ui-page-heading">
                <span class="ui-page-icon" aria-hidden="true">
                    <i class="fas fa-desktop"></i>
                </span>
                <div>
                    <h4 class="ui-page-title">{{ __('navigation.central_monitor') }}</h4>
                    <p class="ui-page-subtitle">
                        Periode {{ $dashboard['period']['label'] }} &middot; Update {{ $health['updated_at'] }} WITA
                    </p>
                </div>
            </div>

            <form method="GET" class="central-monitor-toolbar" data-loading-text="Memuat monitor...">
                <div class="ui-field central-monitor-period-field">
                    <label class="form-label" for="period_month">Periode</label>
                    <input type="month" id="period_month" name="period_month" class="form-control form-control-sm" value="{{ $dashboard['period']['month'] }}">
                </div>
                <button type="submit" class="btn btn-primary ui-btn-icon" data-loading-text="Memuat monitor...">
                    <i class="fas fa-sync-alt" aria-hidden="true"></i>
                    <span>Refresh</span>
                </button>
            </form>
        </div>

        @if($errors->any())
            <div class="alert ui-alert central-monitor-alert central-monitor-alert--danger">
                <strong>Filter tidak valid.</strong> {{ $errors->first() }}
            </div>
        @endif

        @if($inactiveModules->isNotEmpty())
            <div class="alert ui-alert central-monitor-alert central-monitor-alert--warning">
                <strong>Modul monitor belum lengkap.</strong>
                {{ $inactiveModules->join(', ') }} belum aktif. Jalankan migrasi terkait agar semua indikator tersedia.
            </div>
        @endif

        <section class="central-monitor-health central-monitor-health--{{ $healthMeta['class'] }}" aria-label="Status operasional">
            <div class="central-monitor-health__main">
                <span class="central-monitor-health__icon" aria-hidden="true">
                    <i class="{{ $healthMeta['icon'] }}"></i>
                </span>
                <div>
                    <div class="central-monitor-label">Status operasional</div>
                    <div class="central-monitor-health__title">{{ $health['label'] }}</div>
                    <div class="central-monitor-health__subtitle">Ringkasan dari approval, presensi, import, queue, dan audit.</div>
                </div>
            </div>
            <div class="central-monitor-health__stats">
                <span>
                    <strong>{{ number_format($health['critical_count']) }}</strong>
                    kritis
                </span>
                <span>
                    <strong>{{ number_format($health['warning_count']) }}</strong>
                    warning
                </span>
            </div>
        </section>

        <div class="central-monitor-card-grid">
            @foreach($dashboard['cards'] as $card)
                @php
                    $meta = $statusMeta[$card['status']] ?? $statusMeta[\App\Services\Monitoring\CentralMonitorService::STATUS_OK];
                @endphp
                <section class="central-monitor-card central-monitor-card--{{ $meta['class'] }}" aria-labelledby="central-monitor-card-{{ $loop->index }}">
                    <div class="central-monitor-card__header">
                        <div>
                            <div class="central-monitor-card__title" id="central-monitor-card-{{ $loop->index }}">{{ $card['title'] }}</div>
                            <div class="central-monitor-card__value">{{ number_format((int) $card['primary_value']) }}</div>
                            <div class="central-monitor-card__label">{{ $card['primary_label'] }}</div>
                        </div>
                        <span class="central-monitor-card__icon" aria-hidden="true">
                            <i class="{{ $card['icon'] }}"></i>
                        </span>
                    </div>

                    <p class="central-monitor-card__description">
                        {{ $card['description'] }}
                    </p>

                    <div class="central-monitor-metrics">
                        @foreach($card['metrics'] as $metric)
                            <div class="central-monitor-metric central-monitor-metric--{{ $metric['tone'] ?? 'neutral' }}">
                                <span>{{ $metric['label'] }}</span>
                                <strong>{{ number_format((int) $metric['value']) }}</strong>
                            </div>
                        @endforeach
                    </div>

                    <div class="central-monitor-card__actions">
                        @if(!empty($card['url']))
                            <a href="{{ $card['url'] }}" class="btn btn-sm btn-outline-primary ui-btn-icon" data-loading-text="Membuka...">
                                <i class="fas fa-arrow-right" aria-hidden="true"></i>
                                <span>Detail</span>
                            </a>
                        @endif
                        @if(!empty($card['secondary_url']))
                            <a href="{{ $card['secondary_url'] }}" class="btn btn-sm btn-outline-secondary ui-btn-icon" data-loading-text="Membuka...">
                                <i class="fas fa-lock" aria-hidden="true"></i>
                                <span>Closing</span>
                            </a>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>

        <section class="ui-panel mt-3" aria-labelledby="centralMonitorReadinessTitle">
            <div class="ui-panel__header">
                <div>
                    <h5 class="ui-panel__title" id="centralMonitorReadinessTitle">Kesiapan Modul Monitor</h5>
                    <p class="ui-panel__meta">Status ketersediaan tabel dan konfigurasi pendukung dashboard.</p>
                </div>
            </div>
            <div class="ui-panel__body">
                <div class="central-monitor-readiness">
                    @foreach($readinessLabels as $key => $label)
                        @php($isReady = !empty($dashboard['readiness'][$key]))
                        <div class="central-monitor-readiness__item {{ $isReady ? 'is-ready' : 'is-missing' }}">
                            <span class="central-monitor-readiness__icon" aria-hidden="true">
                                <i class="fas {{ $isReady ? 'fa-check' : 'fa-exclamation' }}"></i>
                            </span>
                            <span>{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <div class="central-monitor-detail-grid">
            <section class="ui-panel" aria-labelledby="recentImportTitle">
                <div class="ui-panel__header">
                    <div>
                        <h5 class="ui-panel__title" id="recentImportTitle">Import Terbaru</h5>
                        <p class="ui-panel__meta">Lima proses import terakhir.</p>
                    </div>
                    @if(auth()->user()->hasMenuAccess('import_history'))
                        <a href="{{ route('import-histories.index') }}" class="btn btn-sm btn-outline-primary ui-btn-icon" data-loading-text="Membuka...">
                            <i class="fas fa-history" aria-hidden="true"></i>
                            <span>History</span>
                        </a>
                    @endif
                </div>
                <div class="ui-panel__body">
                    <div class="ui-table-wrap">
                        <table class="table table-sm align-middle mb-0 ui-table">
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
                                        <td colspan="3">
                                            <div class="ui-empty-state">
                                                <i class="fas fa-file-import" aria-hidden="true"></i>
                                                <span>Belum ada riwayat import.</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="ui-panel" aria-labelledby="recentAuditTitle">
                <div class="ui-panel__header">
                    <div>
                        <h5 class="ui-panel__title" id="recentAuditTitle">Audit Terbaru</h5>
                        <p class="ui-panel__meta">Lima aktivitas sistem terakhir.</p>
                    </div>
                    @if(auth()->user()->hasMenuAccess('audit_trail'))
                        <a href="{{ route('audit-trails.index') }}" class="btn btn-sm btn-outline-primary ui-btn-icon" data-loading-text="Membuka...">
                            <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                            <span>Audit</span>
                        </a>
                    @endif
                </div>
                <div class="ui-panel__body">
                    <div class="ui-table-wrap">
                        <table class="table table-sm align-middle mb-0 ui-table">
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
                                            <small class="text-muted">
                                                {{ $audit->module }}
                                                @if($audit->employee_nik)
                                                    &middot; {{ $audit->employee_nik }}
                                                @endif
                                            </small>
                                        </td>
                                        <td>{{ $audit->actor_name ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3">
                                            <div class="ui-empty-state">
                                                <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                                                <span>Belum ada audit terbaru.</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
