@extends('layouts.app')

@section('title', 'Dashboard Approval HOD')

@push('styles')
<style>
    .hod-approval-dashboard .dashboard-toolbar {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: space-between;
    }

    .hod-approval-dashboard .metric-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .hod-approval-dashboard .metric-card,
    .hod-approval-dashboard .module-card,
    .hod-approval-dashboard .insight-card {
        background: #fff;
        border: 1px solid #e7edf5;
        border-radius: 8px;
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
        padding: 16px;
    }

    .hod-approval-dashboard .metric-label,
    .hod-approval-dashboard .module-meta {
        color: #64748b;
        font-size: 0.78rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .hod-approval-dashboard .metric-value {
        color: #0f172a;
        font-size: 1.9rem;
        font-weight: 700;
        line-height: 1.1;
    }

    .hod-approval-dashboard .module-grid,
    .hod-approval-dashboard .insight-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }

    .hod-approval-dashboard .content-grid {
        display: grid;
        gap: 16px;
        grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr);
    }

    .hod-approval-dashboard .module-icon {
        align-items: center;
        border-radius: 8px;
        display: inline-flex;
        height: 36px;
        justify-content: center;
        width: 36px;
    }

    .hod-approval-dashboard .table-sm td,
    .hod-approval-dashboard .table-sm th {
        vertical-align: middle;
    }

    @media (max-width: 1200px) {
        .hod-approval-dashboard .metric-grid,
        .hod-approval-dashboard .module-grid,
        .hod-approval-dashboard .insight-grid,
        .hod-approval-dashboard .content-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 768px) {
        .hod-approval-dashboard .metric-grid,
        .hod-approval-dashboard .module-grid,
        .hod-approval-dashboard .insight-grid,
        .hod-approval-dashboard .content-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
@php
    $summary = $dashboard['summary'];
    $canOpenHrdQueue = auth()->user() && auth()->user()->hasMenuAccess('approval_hr');
    $formatDate = function ($date) {
        return $date ? $date->format('d M Y') : '-';
    };
    $ageLabel = function (?int $hours, ?int $days) {
        if ($hours === null) {
            return '-';
        }

        if ($hours < 24) {
            return $hours . ' jam';
        }

        return $days . ' hari';
    };
@endphp

<div class="container-fluid hod-approval-dashboard">
    <div class="page-inner">
        <div class="dashboard-toolbar mb-4">
            <div>
                <h3 class="text-primary mb-1">Dashboard Approval HOD</h3>
                <small class="text-muted">
                    Pantau antrean HOD, pengajuan yang sudah naik ke HRD, dan prioritas approval dalam scope karyawan Anda.
                    Update {{ $dashboard['generated_at'] }} WITA.
                </small>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('approval.cuti.hod') }}" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-calendar-check me-1"></i> Cuti
                </a>
                <a href="{{ route('approval.izin.hod') }}" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-file-signature me-1"></i> Izin
                </a>
                <a href="{{ route('approval.roster.hod') }}" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-plane-departure me-1"></i> Roster
                </a>
            </div>
        </div>

        <div class="metric-grid mb-4">
            <div class="metric-card">
                <div class="metric-label">Menunggu HOD</div>
                <div class="metric-value">{{ number_format($summary['pending_hod']) }}</div>
                <small class="text-muted">Siap diproses di level HOD</small>
            </div>
            <div class="metric-card">
                <div class="metric-label">Menunggu HRD</div>
                <div class="metric-value">{{ number_format($summary['pending_hrd']) }}</div>
                <small class="text-muted">Sudah disetujui HOD, belum final HRD</small>
            </div>
            <div class="metric-card">
                <div class="metric-label">Lewat SLA HOD</div>
                <div class="metric-value">{{ number_format($summary['over_sla']) }}</div>
                <small class="text-muted">SLA HOD {{ $dashboard['sla_hours']['hod'] }} jam</small>
            </div>
            <div class="metric-card">
                <div class="metric-label">Tanggal Dekat</div>
                <div class="metric-value">{{ number_format($summary['due_soon'] + $summary['effective_overdue']) }}</div>
                <small class="text-muted">Tanggal efektif lewat atau <= 7 hari</small>
            </div>
        </div>

        <div class="insight-grid mb-4">
            @foreach($dashboard['insights'] as $insight)
                <div class="insight-card">
                    <span class="badge bg-{{ $insight['class'] }} mb-2">{{ $insight['title'] }}</span>
                    <div class="fs-4 fw-bold text-dark">{{ number_format($insight['value']) }}</div>
                    <small class="text-muted">{{ $insight['description'] }}</small>
                </div>
            @endforeach
        </div>

        <div class="module-grid mb-4">
            @foreach($dashboard['modules'] as $module)
                <div class="module-card">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <span class="module-icon bg-{{ $module['badge'] }} text-white">
                                <i class="{{ $module['icon'] }}"></i>
                            </span>
                        </div>
                        <span class="badge bg-light text-dark border">{{ number_format($module['pending_hod'] + $module['pending_hrd']) }}</span>
                    </div>
                    <h6 class="fw-semibold mb-2">{{ $module['label'] }}</h6>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Menunggu HOD</span>
                        <strong>{{ number_format($module['pending_hod']) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Menunggu HRD</span>
                        <strong>{{ number_format($module['pending_hrd']) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between small mb-3">
                        <span>Delegasi</span>
                        <strong>{{ number_format($module['delegation_pending']) }}</strong>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route($module['route_hod']) }}" class="btn btn-sm btn-outline-primary flex-fill">HOD</a>
                        @if($canOpenHrdQueue)
                            <a href="{{ route($module['route_hrd']) }}" class="btn btn-sm btn-light border flex-fill">HRD</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="content-grid mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <h5 class="fw-semibold mb-1">Prioritas Approval HOD</h5>
                            <small class="text-muted">Diurutkan dari antrean paling lama.</small>
                        </div>
                        <span class="badge bg-warning text-dark">{{ number_format($summary['pending_hod']) }} pending</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Karyawan</th>
                                    <th>Jenis</th>
                                    <th>Tanggal</th>
                                    <th>Umur</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($dashboard['priority_items'] as $item)
                                    <tr>
                                        <td>
                                            <strong>{{ $item['employee_name'] }}</strong>
                                            <small class="d-block text-muted">{{ $item['nik'] }} / {{ $item['division'] }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $item['badge'] }}">{{ $item['short_module'] }}</span>
                                            @if($item['amount'])
                                                <small class="d-block text-muted">{{ $item['amount'] }} hari</small>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $formatDate($item['effective_start']) }}
                                            @if($item['effective_end'] && $item['effective_end']->toDateString() !== optional($item['effective_start'])->toDateString())
                                                <small class="d-block text-muted">s/d {{ $formatDate($item['effective_end']) }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $ageLabel($item['age_hours'], $item['age_days']) }}</td>
                                        <td class="text-end">
                                            <a href="{{ $item['route'] }}" class="btn btn-sm btn-outline-primary">Buka</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">Tidak ada antrean HOD saat ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="fw-semibold mb-1">Menunggu HRD</h5>
                    <small class="text-muted d-block mb-3">Pengajuan dalam scope Anda yang sudah disetujui HOD dan belum final HRD.</small>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Karyawan</th>
                                    <th>Jenis</th>
                                    <th>Umur</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($dashboard['hrd_waiting_items'] as $item)
                                    <tr>
                                        <td>
                                            <strong>{{ $item['employee_name'] }}</strong>
                                            <small class="d-block text-muted">{{ $item['nik'] }}</small>
                                        </td>
                                        <td><span class="badge bg-{{ $item['badge'] }}">{{ $item['short_module'] }}</span></td>
                                        <td>{{ $ageLabel($item['age_hours'], $item['age_days']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">Tidak ada antrean HRD dalam scope Anda.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="fw-semibold mb-1">Area Perlu Perhatian</h5>
                <small class="text-muted d-block mb-3">Departemen/divisi dengan antrean approval terbanyak dalam scope Anda.</small>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Departemen</th>
                                <th>Divisi</th>
                                <th>Menunggu HOD</th>
                                <th>Menunggu HRD</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dashboard['organization_breakdown'] as $row)
                                <tr>
                                    <td>{{ $row['department'] }}</td>
                                    <td>{{ $row['division'] }}</td>
                                    <td>{{ number_format($row['pending_hod']) }}</td>
                                    <td>{{ number_format($row['pending_hrd']) }}</td>
                                    <td><strong>{{ number_format($row['total']) }}</strong></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">Belum ada antrean approval.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
