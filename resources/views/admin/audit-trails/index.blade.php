@extends('layouts.app')

@section('title', __('Audit Trail'))

@push('styles')
<style>
    .audit-trail-table th,
    .audit-trail-table td {
        vertical-align: top;
    }

    .audit-trail-json {
        max-height: 180px;
        overflow: auto;
        white-space: pre-wrap;
        word-break: break-word;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-clipboard-list text-primary me-2"></i>
                    {{ __('Audit Trail') }}
                </h4>
                <small class="text-muted">{{ __('Pantau riwayat approval HOD dan HR secara terpusat.') }}</small>
            </div>
        </div>

        @if(!$isTableReady)
            <div class="alert alert-warning">
                {{ __('Fitur audit trail belum aktif karena tabel') }} <code>audit_trails</code> {{ __('belum tersedia. Jalankan') }} <code>php artisan migrate</code> {{ __('terlebih dahulu.') }}
            </div>
        @else
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label">{{ __('Dari Tanggal') }}</label>
                            <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ __('Sampai Tanggal') }}</label>
                            <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ __('Modul') }}</label>
                            <select name="module" class="form-select">
                                <option value="">{{ __('Semua Modul') }}</option>
                                @foreach($moduleOptions as $value => $label)
                                    <option value="{{ $value }}" {{ ($filters['module'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ __('Event') }}</label>
                            <select name="event" class="form-select">
                                <option value="">{{ __('Semua Event') }}</option>
                                @foreach($eventLabels as $value => $label)
                                    <option value="{{ $value }}" {{ ($filters['event'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">NIK</label>
                            <input type="text" name="employee_nik" class="form-control" value="{{ $filters['employee_nik'] ?? '' }}" placeholder="{{ __('NIK karyawan') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ __('Aktor') }}</label>
                            <input type="text" name="actor" class="form-control" value="{{ $filters['actor'] ?? '' }}" placeholder="{{ __('Nama atau ID') }}">
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i> {{ __('Tampilkan') }}
                            </button>
                            <a href="{{ route('audit-trails.index') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i> {{ __('Reset') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">{{ __('Riwayat Aktivitas') }}</h5>
                            <small class="text-muted">{{ __('Menampilkan 50 log per halaman, terbaru terlebih dahulu.') }}</small>
                        </div>
                        <div class="text-muted small">
                            Total: {{ number_format($auditTrails->total()) }} log
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm audit-trail-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 145px;">{{ __('tables.time') }}</th>
                                    <th style="width: 180px;">{{ __('tables.event') }}</th>
                                    <th style="width: 150px;">{{ __('tables.reference') }}</th>
                                    <th style="width: 120px;">{{ __('tables.nik') }}</th>
                                    <th style="width: 190px;">{{ __('tables.actor') }}</th>
                                    <th>{{ __('tables.note') }}</th>
                                    <th style="width: 260px;">{{ __('tables.changes') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($auditTrails as $trail)
                                    @php
                                        $eventLabel = $eventLabels[$trail->event] ?? $trail->event;
                                        $badgeClass = strpos($trail->event, 'rejected') !== false ? 'danger' : 'success';
                                        $oldValues = $trail->old_values ?: [];
                                        $newValues = $trail->new_values ?: [];
                                        $metadata = $trail->metadata ?: [];
                                    @endphp
                                    <tr>
                                        <td>
                                            <div>{{ optional($trail->created_at)->format('d M Y') }}</div>
                                            <small class="text-muted">{{ optional($trail->created_at)->format('H:i:s') }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $badgeClass }}">{{ $eventLabel }}</span>
                                            <div class="small text-muted mt-1">{{ $trail->module }}</div>
                                        </td>
                                        <td>
                                            <div>{{ $trail->reference_table ?: '-' }}</div>
                                            <small class="text-muted">ID: {{ $trail->reference_id ?: '-' }}</small>
                                        </td>
                                        <td>{{ $trail->employee_nik ?: '-' }}</td>
                                        <td>
                                            <div>{{ $trail->actor_name ?: '-' }}</div>
                                            <small class="text-muted">{{ $trail->actor_role ?: '-' }}</small>
                                            @if($trail->ip_address)
                                                <div class="small text-muted">IP: {{ $trail->ip_address }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $trail->note ?: '-' }}</td>
                                        <td>
                                            <details>
                                                <summary class="small text-primary">{{ __('Lihat detail') }}</summary>
                                                <div class="mt-2">
                                                    <strong class="small">{{ __('Sebelum') }}</strong>
                                                    <pre class="audit-trail-json bg-light border rounded p-2 mb-2 small">{{ json_encode($oldValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    <strong class="small">{{ __('Sesudah') }}</strong>
                                                    <pre class="audit-trail-json bg-light border rounded p-2 mb-2 small">{{ json_encode($newValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    @if(!empty($metadata))
                                                        <strong class="small">{{ __('Metadata') }}</strong>
                                                        <pre class="audit-trail-json bg-light border rounded p-2 mb-0 small">{{ json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    @endif
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            {{ __('Belum ada audit trail untuk filter ini.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $auditTrails->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
