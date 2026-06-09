@extends('layouts.app')

@section('title', 'Monitoring Kontrak')

@push('styles')
<style>
    .contract-monitor-page .monitor-toolbar {
        align-items: end;
        display: grid;
        gap: 10px;
        grid-template-columns: repeat(6, minmax(0, 1fr));
    }

    .contract-monitor-page .monitor-card {
        border: 0;
        border-radius: 8px;
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
    }

    .contract-monitor-page .metric-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .contract-monitor-page .metric-card {
        background: #fff;
        border: 1px solid #e8edf5;
        border-radius: 8px;
        padding: 16px;
    }

    .contract-monitor-page .metric-label {
        color: #64748b;
        font-size: 0.78rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .contract-monitor-page .metric-value {
        color: #0f172a;
        font-size: 1.85rem;
        font-weight: 700;
        line-height: 1.1;
    }

    .contract-monitor-page .metric-meta {
        color: #64748b;
        font-size: 0.82rem;
    }

    .contract-monitor-page .monitor-grid {
        display: grid;
        gap: 16px;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    }

    .contract-monitor-page .status-list {
        display: grid;
        gap: 8px;
    }

    .contract-monitor-page .status-row {
        align-items: center;
        border-bottom: 1px solid #eef2f7;
        display: flex;
        gap: 10px;
        justify-content: space-between;
        padding: 8px 0;
    }

    .contract-monitor-page .table-sm td,
    .contract-monitor-page .table-sm th {
        vertical-align: middle;
    }

    @media (max-width: 1200px) {
        .contract-monitor-page .monitor-toolbar,
        .contract-monitor-page .metric-grid,
        .contract-monitor-page .monitor-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 768px) {
        .contract-monitor-page .monitor-toolbar,
        .contract-monitor-page .metric-grid,
        .contract-monitor-page .monitor-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
@php
    $summary = $dashboard['summary'];
    $period = $dashboard['period'];
    $signatureLabels = \App\Models\EmployeeContract::signatureStatusOptions();
    $renewalLabels = \App\Models\EmployeeContractRenewal::statusLabels();
    $renewalBadges = \App\Models\EmployeeContractRenewal::statusBadgeClasses();
    $typeLabels = \App\Models\ContractTemplate::typeOptions();
    $canOpenElectronicContract = auth()->user()
        && auth()->user()->hasRole(['Super Admin', 'HR'])
        && auth()->user()->hasMenuAccess('electronic_contract_admin');

    $statusBadge = function (?string $status) {
        return [
            \App\Models\EmployeeContract::SIGNATURE_STATUS_DRAFT => 'secondary',
            \App\Models\EmployeeContract::SIGNATURE_STATUS_WAITING => 'warning',
            \App\Models\EmployeeContract::SIGNATURE_STATUS_SIGNED => 'success',
            \App\Models\EmployeeContract::SIGNATURE_STATUS_REJECTED => 'danger',
            \App\Models\EmployeeContract::SIGNATURE_STATUS_CANCELLED => 'dark',
        ][$status] ?? 'secondary';
    };

    $daysLabel = function ($days) {
        if ($days === null) {
            return '-';
        }

        if ((int) $days < 0) {
            return 'Lewat ' . abs((int) $days) . ' hari';
        }

        if ((int) $days === 0) {
            return 'Hari ini';
        }

        return 'H-' . (int) $days;
    };
@endphp

<div class="container-fluid contract-monitor-page">
    <div class="page-inner">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h3 class="text-primary mb-1">Monitoring Kontrak</h3>
                <small class="text-muted">
                    Ringkasan kontrak elektronik, tanda tangan, workflow perpanjangan, dan kontrak yang akan berakhir.
                    Update {{ $period['updated_at'] }} WITA.
                </small>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('contract-renewals.index', request()->query()) }}" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-tasks me-1"></i> Workflow Perpanjangan
                </a>
                @if($canOpenElectronicContract)
                    <a href="{{ route('electronic-contracts.index', ['quick_filter' => 'waiting_signature']) }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-signature me-1"></i> Kontrak Menunggu TTD
                    </a>
                @endif
            </div>
        </div>

        <div class="card monitor-card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('contract-renewals.dashboard') }}" class="monitor-toolbar">
                    <div>
                        <label class="form-label small mb-1">Area</label>
                        <select name="area" class="form-select form-select-sm js-contract-area-filter">
                            <option value="">Semua area</option>
                            @foreach($filterOptions['areas'] as $areaOption)
                                <option value="{{ $areaOption['code'] }}" {{ ($filters['area'] ?? null) === $areaOption['code'] ? 'selected' : '' }}>
                                    {{ $areaOption['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label small mb-1">Departemen</label>
                        <select name="departemen_id" class="form-select form-select-sm js-contract-department-filter">
                            <option value="">Semua departemen</option>
                            @php
                                $groupedDepartments = [];
                                foreach ($filterOptions['departemens'] as $departemen) {
                                    $groupedDepartments[optional($departemen->perusahaan)->nama_perusahaan ?? 'Lainnya'][] = $departemen;
                                }
                            @endphp
                            @foreach($groupedDepartments as $companyName => $departemenItems)
                                <optgroup label="{{ $companyName }}">
                                    @foreach($departemenItems as $departemen)
                                        <option
                                            value="{{ $departemen->id }}"
                                            data-area="{{ optional($departemen->perusahaan)->kode_perusahaan }}"
                                            {{ (string) ($filters['departemen_id'] ?? '') === (string) $departemen->id ? 'selected' : '' }}
                                        >
                                            {{ $departemen->departemen }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label small mb-1">Divisi</label>
                        <select name="divisi_id" class="form-select form-select-sm js-contract-division-filter">
                            <option value="">Semua divisi</option>
                            @foreach($filterOptions['divisis'] as $divisi)
                                <option
                                    value="{{ $divisi->id }}"
                                    data-area="{{ optional(optional($divisi->departemen)->perusahaan)->kode_perusahaan }}"
                                    data-departemen="{{ $divisi->departemen_id }}"
                                    {{ (string) ($filters['divisi_id'] ?? '') === (string) $divisi->id ? 'selected' : '' }}
                                >
                                    {{ $divisi->nama_divisi }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label small mb-1">Window</label>
                        <select name="days" class="form-select form-select-sm">
                            @foreach([14, 30, 45, 60, 90, 180] as $option)
                                <option value="{{ $option }}" {{ (int) $days === $option ? 'selected' : '' }}>{{ $option }} hari</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label small mb-1">Cari</label>
                        <input type="text" name="search" class="form-control form-control-sm" value="{{ $search }}" placeholder="Nama, NIK, nomor kontrak">
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-fill">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <a href="{{ route('contract-renewals.dashboard') }}" class="btn btn-light border btn-sm">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="metric-grid mb-4">
            <div class="metric-card">
                <div class="metric-label">Menunggu TTD</div>
                <div class="metric-value">{{ number_format($summary['waiting_signature']) }}</div>
                <div class="metric-meta">{{ number_format($summary['unsigned_overdue']) }} sudah lewat tanggal akhir</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Akan Berakhir</div>
                <div class="metric-value">{{ number_format($summary['upcoming_without_workflow']) }}</div>
                <div class="metric-meta">Belum dibuat workflow dalam {{ $period['label'] }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Pending Approval</div>
                <div class="metric-value">{{ number_format($summary['pending_hod'] + $summary['pending_hrd']) }}</div>
                <div class="metric-meta">HOD {{ number_format($summary['pending_hod']) }} / HRD {{ number_format($summary['pending_hrd']) }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">TTD Bulan Ini</div>
                <div class="metric-value">{{ number_format($summary['signed_this_month']) }}</div>
                <div class="metric-meta">Dari {{ number_format($summary['total_contracts']) }} kontrak elektronik</div>
            </div>
        </div>

        <div class="monitor-grid mb-4">
            <div class="card monitor-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <h5 class="fw-semibold mb-1">Status Tanda Tangan</h5>
                            <small class="text-muted">Ringkasan status kontrak elektronik dalam scope filter.</small>
                        </div>
                    </div>
                    <div class="status-list">
                        @foreach($signatureLabels as $statusKey => $label)
                            @php($count = $dashboard['signature_status_counts'][$statusKey] ?? 0)
                            <div class="status-row">
                                <span><span class="badge bg-{{ $statusBadge($statusKey) }} me-2">&nbsp;</span>{{ $label }}</span>
                                <strong>{{ number_format($count) }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card monitor-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <h5 class="fw-semibold mb-1">Tipe Kontrak</h5>
                            <small class="text-muted">Komposisi PKWT, translator, dan adendum.</small>
                        </div>
                    </div>
                    <div class="status-list">
                        @foreach($typeLabels as $typeKey => $label)
                            @php($count = $dashboard['contract_type_counts'][$typeKey] ?? 0)
                            <div class="status-row">
                                <span>{{ $label }}</span>
                                <strong>{{ number_format($count) }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="card monitor-card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h5 class="fw-semibold mb-1">Workflow Perpanjangan</h5>
                        <small class="text-muted">Jumlah workflow berdasarkan status saat ini.</small>
                    </div>
                    <span class="badge bg-light text-dark border">{{ number_format($summary['renewal_workflows']) }} workflow</span>
                </div>
                <div class="row g-2">
                    @foreach($renewalLabels as $statusKey => $label)
                        @php($count = $dashboard['renewal_status_counts'][$statusKey] ?? 0)
                        <div class="col-xl-3 col-md-4 col-sm-6">
                            <a href="{{ route('contract-renewals.index', array_merge(request()->query(), ['status' => $statusKey])) }}" class="text-decoration-none">
                                <div class="border rounded p-3 h-100">
                                    <span class="badge bg-{{ $renewalBadges[$statusKey] ?? 'secondary' }}">{{ $label }}</span>
                                    <div class="fs-4 fw-bold text-dark mt-2">{{ number_format($count) }}</div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="monitor-grid">
            <div class="card monitor-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <h5 class="fw-semibold mb-1">Menunggu Tanda Tangan</h5>
                            <small class="text-muted">Kontrak elektronik yang perlu difollow up.</small>
                        </div>
                        @if($canOpenElectronicContract)
                            <a href="{{ route('electronic-contracts.index', ['quick_filter' => 'waiting_signature']) }}" class="btn btn-outline-primary btn-sm">Buka daftar</a>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Karyawan</th>
                                    <th>Kontrak</th>
                                    <th>Tanggal Akhir</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($dashboard['waiting_signature_contracts'] as $row)
                                    @php($contract = $row['contract'])
                                    <tr>
                                        <td>
                                            <strong>{{ $contract->display_employee_name }}</strong>
                                            <small class="d-block text-muted">{{ $contract->nik ?: $contract->employee_nik ?: '-' }}</small>
                                        </td>
                                        <td>
                                            @if($canOpenElectronicContract)
                                                <a href="{{ route('electronic-contracts.show', $contract) }}">{{ $contract->display_number }}</a>
                                            @else
                                                {{ $contract->display_number }}
                                            @endif
                                            <small class="d-block text-muted">{{ $contract->type_label }}</small>
                                        </td>
                                        <td>
                                            {{ optional($row['due_date'])->format('d M Y') ?: '-' }}
                                            <small class="d-block text-muted">{{ $daysLabel($row['days_to_due']) }}</small>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">Tidak ada kontrak menunggu tanda tangan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card monitor-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <h5 class="fw-semibold mb-1">Kontrak Akan Berakhir</h5>
                            <small class="text-muted">History terbaru yang belum dibuat workflow perpanjangan.</small>
                        </div>
                        <a href="{{ route('contract-renewals.index', request()->query()) }}" class="btn btn-outline-primary btn-sm">Proses</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Karyawan</th>
                                    <th>History</th>
                                    <th>Akhir</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($dashboard['upcoming_histories'] as $row)
                                    @php($history = $row['history'])
                                    <tr>
                                        <td>
                                            <strong>{{ optional($history->employee)->nama_karyawan ?: $history->employee_name ?: '-' }}</strong>
                                            <small class="d-block text-muted">{{ $history->nik }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">{{ $history->raw_history_type }}</span>
                                            <small class="d-block text-muted">{{ $history->contract_number ?: '-' }}</small>
                                        </td>
                                        <td>
                                            {{ optional($history->contract_end_date)->format('d M Y') ?: '-' }}
                                            <small class="d-block text-muted">{{ $daysLabel($row['days_to_due']) }}</small>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">Tidak ada kontrak akan berakhir pada filter ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card monitor-card">
                <div class="card-body">
                    <h5 class="fw-semibold mb-1">Menunggu Approval HOD</h5>
                    <small class="text-muted d-block mb-3">Antrean rekomendasi perpanjangan di HOD.</small>
                    @include('admin.contract-renewals.partials.pending-renewal-table', [
                        'renewals' => $dashboard['pending_hod_renewals'],
                        'emptyText' => 'Tidak ada antrean HOD.',
                    ])
                </div>
            </div>

            <div class="card monitor-card">
                <div class="card-body">
                    <h5 class="fw-semibold mb-1">Menunggu Approval HRD</h5>
                    <small class="text-muted d-block mb-3">Antrean final sebelum kontrak dibuat atau diputus.</small>
                    @include('admin.contract-renewals.partials.pending-renewal-table', [
                        'renewals' => $dashboard['pending_hrd_renewals'],
                        'emptyText' => 'Tidak ada antrean HRD.',
                    ])
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const areaFilter = document.querySelector('.js-contract-area-filter');
        const departmentFilter = document.querySelector('.js-contract-department-filter');
        const divisionFilter = document.querySelector('.js-contract-division-filter');

        function syncOrganizationFilters() {
            if (!areaFilter || !departmentFilter || !divisionFilter) {
                return;
            }

            const selectedArea = areaFilter.value;
            let resetDepartment = false;

            Array.prototype.forEach.call(departmentFilter.options, function (option) {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                const visible = !selectedArea || option.dataset.area === selectedArea;
                option.hidden = !visible;

                if (!visible && option.selected) {
                    resetDepartment = true;
                }
            });

            if (resetDepartment) {
                departmentFilter.value = '';
            }

            const selectedDepartment = departmentFilter.value;
            let resetDivision = false;

            Array.prototype.forEach.call(divisionFilter.options, function (option) {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                const areaMatches = !selectedArea || option.dataset.area === selectedArea;
                const departmentMatches = !selectedDepartment || option.dataset.departemen === selectedDepartment;
                const visible = areaMatches && departmentMatches;
                option.hidden = !visible;

                if (!visible && option.selected) {
                    resetDivision = true;
                }
            });

            if (resetDivision) {
                divisionFilter.value = '';
            }
        }

        if (areaFilter && departmentFilter && divisionFilter) {
            areaFilter.addEventListener('change', syncOrganizationFilters);
            departmentFilter.addEventListener('change', syncOrganizationFilters);
            syncOrganizationFilters();
        }
    });
</script>
@endpush
