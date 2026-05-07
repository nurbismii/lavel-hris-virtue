@extends('layouts.app')

@section('title', 'Audit Trail')

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
                    Audit Trail
                </h4>
                <small class="text-muted">Pantau riwayat approval HOD dan HR secara terpusat.</small>
            </div>
        </div>

        @if(!$isTableReady)
            <div class="alert alert-warning">
                Fitur audit trail belum aktif karena tabel <code>audit_trails</code> belum tersedia. Jalankan <code>php artisan migrate</code> terlebih dahulu.
            </div>
        @else
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label">Dari Tanggal</label>
                            <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sampai Tanggal</label>
                            <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Modul</label>
                            <select name="module" class="form-select">
                                <option value="">Semua Modul</option>
                                @foreach($moduleOptions as $value => $label)
                                    <option value="{{ $value }}" {{ ($filters['module'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Event</label>
                            <select name="event" class="form-select">
                                <option value="">Semua Event</option>
                                @foreach($eventLabels as $value => $label)
                                    <option value="{{ $value }}" {{ ($filters['event'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">NIK</label>
                            <input type="text" name="employee_nik" class="form-control" value="{{ $filters['employee_nik'] ?? '' }}" placeholder="NIK karyawan">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Aktor</label>
                            <input type="text" name="actor" class="form-control" value="{{ $filters['actor'] ?? '' }}" placeholder="Nama atau ID">
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i> Tampilkan
                            </button>
                            <a href="{{ route('audit-trails.index') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">Riwayat Aktivitas</h5>
                            <small class="text-muted">Menampilkan 50 log per halaman, terbaru terlebih dahulu.</small>
                        </div>
                        <div class="text-muted small">
                            Total: {{ number_format($auditTrails->total()) }} log
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm audit-trail-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 145px;">Waktu</th>
                                    <th style="width: 180px;">Event</th>
                                    <th style="width: 150px;">Referensi</th>
                                    <th style="width: 120px;">NIK</th>
                                    <th style="width: 190px;">Aktor</th>
                                    <th>Catatan</th>
                                    <th style="width: 260px;">Perubahan</th>
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
                                                <summary class="small text-primary">Lihat detail</summary>
                                                <div class="mt-2">
                                                    <strong class="small">Sebelum</strong>
                                                    <pre class="audit-trail-json bg-light border rounded p-2 mb-2 small">{{ json_encode($oldValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    <strong class="small">Sesudah</strong>
                                                    <pre class="audit-trail-json bg-light border rounded p-2 mb-2 small">{{ json_encode($newValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    @if(!empty($metadata))
                                                        <strong class="small">Metadata</strong>
                                                        <pre class="audit-trail-json bg-light border rounded p-2 mb-0 small">{{ json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    @endif
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            Belum ada audit trail untuk filter ini.
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
