@extends('layouts.app')

@section('title', 'History Import')

@push('styles')
<style>
    .import-history-table th,
    .import-history-table td {
        vertical-align: top;
    }

    .import-history-file {
        max-width: 240px;
        word-break: break-word;
    }

    .import-history-samples {
        max-height: 180px;
        overflow: auto;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-file-import text-primary me-2"></i>
                    History Import
                </h4>
                <small class="text-muted">Pantau import karyawan, resign, pelanggaran, foto, dokumen, dan referensi presensi secara terpusat.</small>
            </div>
        </div>

        @if(!$isTableReady)
            <div class="alert alert-warning">
                Fitur history import belum aktif karena tabel <code>import_histories</code> belum tersedia. Jalankan <code>php artisan migrate</code> terlebih dahulu.
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
                            <label class="form-label">Jenis Import</label>
                            <select name="import_type" class="form-select">
                                <option value="">Semua Jenis</option>
                                @foreach($typeOptions as $value => $label)
                                    <option value="{{ $value }}" {{ ($filters['import_type'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">Semua Status</option>
                                @foreach($statusOptions as $value => $label)
                                    <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sumber</label>
                            <select name="source" class="form-select">
                                <option value="">Semua Sumber</option>
                                @foreach($sourceOptions as $value => $label)
                                    <option value="{{ $value }}" {{ ($filters['source'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Aktor</label>
                            <input type="text" name="actor" class="form-control" value="{{ $filters['actor'] ?? '' }}" placeholder="Nama, email, ID">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cari</label>
                            <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Nama file, import ID, atau error">
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i> Tampilkan
                            </button>
                            <a href="{{ route('import-histories.index') }}" class="btn btn-outline-secondary">
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
                            <h5 class="mb-1">Riwayat Import</h5>
                            <small class="text-muted">Menampilkan 50 import per halaman, terbaru terlebih dahulu.</small>
                        </div>
                        <div class="text-muted small">
                            Total: {{ number_format($importHistories->total()) }} import
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm import-history-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 145px;">{{ __('tables.time') }}</th>
                                    <th style="width: 210px;">{{ __('tables.type_kind') }}</th>
                                    <th style="width: 260px;">{{ __('tables.file') }}</th>
                                    <th style="width: 170px;">{{ __('tables.status') }}</th>
                                    <th style="width: 220px;">{{ __('tables.summary') }}</th>
                                    <th style="width: 190px;">{{ __('tables.actor') }}</th>
                                    <th>{{ __('tables.note') }}</th>
                                    <th style="width: 145px;">Export Excel</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($importHistories as $history)
                                    @php
                                        $samples = $history->failure_samples ?: [];
                                        $summary = $history->summary ?: [];
                                        $duration = $history->started_at && $history->finished_at
                                            ? $history->started_at->diffForHumans($history->finished_at, true)
                                            : null;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div>{{ optional($history->created_at)->format('d M Y') }}</div>
                                            <small class="text-muted">{{ optional($history->created_at)->format('H:i:s') }}</small>
                                            @if($duration)
                                                <div class="small text-muted mt-1">Durasi: {{ $duration }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="fw-semibold">{{ $history->import_type_label }}</div>
                                            <small class="text-muted">{{ $history->source_label }}{{ $history->module ? ' - ' . $history->module : '' }}</small>
                                            @if($history->import_id)
                                                <div class="small text-muted mt-1">ID: {{ $history->import_id }}</div>
                                            @endif
                                        </td>
                                        <td class="import-history-file">
                                            <div>{{ $history->file_name ?: '-' }}</div>
                                            @if($history->file_size)
                                                <small class="text-muted">{{ number_format($history->file_size / 1024, 1) }} KB</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $history->status_badge_class }}">{{ $history->status_label }}</span>
                                            @if($history->started_at)
                                                <div class="small text-muted mt-1">Mulai: {{ $history->started_at->format('d M Y H:i') }}</div>
                                            @endif
                                            @if($history->finished_at)
                                                <div class="small text-muted">Selesai: {{ $history->finished_at->format('d M Y H:i') }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div>Total: {{ number_format($history->total_rows) }}</div>
                                            <div class="text-success">Berhasil: {{ number_format($history->success_count) }}</div>
                                            <div class="text-danger">Gagal: {{ number_format($history->failed_count) }}</div>
                                            <div class="text-warning">Dilewati: {{ number_format($history->skipped_count) }}</div>
                                            @if($history->inserted_count || $history->updated_count)
                                                <small class="text-muted">Baru: {{ number_format($history->inserted_count) }}, Update: {{ number_format($history->updated_count) }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <div>{{ optional($history->actor)->name ?: '-' }}</div>
                                            @if(optional($history->actor)->email)
                                                <small class="text-muted">{{ $history->actor->email }}</small>
                                            @else
                                                <small class="text-muted">{{ $history->created_by ?: '-' }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($history->error_message)
                                                <div class="alert alert-danger py-2 px-3 mb-2 small">
                                                    {{ $history->error_message }}
                                                </div>
                                            @endif

                                            @if(!empty($samples))
                                                <details>
                                                    <summary class="small text-primary">Lihat sampel catatan</summary>
                                                    <div class="import-history-samples mt-2">
                                                        @foreach($samples as $sample)
                                                            <div class="border rounded p-2 mb-2 small bg-light">
                                                                <div>
                                                                    <strong>{{ strtoupper($sample['status'] ?? 'INFO') }}</strong>
                                                                    @if(!empty($sample['nik']))
                                                                        <span class="text-muted">NIK: {{ $sample['nik'] }}</span>
                                                                    @endif
                                                                    @if(!empty($sample['file']))
                                                                        <span class="text-muted">File: {{ $sample['file'] }}</span>
                                                                    @endif
                                                                </div>
                                                                <div>{{ $sample['message'] ?? '-' }}</div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </details>
                                            @elseif(!empty($summary['items']))
                                                <details>
                                                    <summary class="small text-primary">Lihat ringkasan ZIP</summary>
                                                    <div class="import-history-samples mt-2">
                                                        @foreach(array_slice($summary['items'], 0, 10) as $item)
                                                            <div class="border rounded p-2 mb-2 small bg-light">
                                                                <strong>{{ strtoupper($item['status'] ?? 'INFO') }}</strong>
                                                                <span>{{ $item['file'] ?? '-' }}</span>
                                                                <div>{{ $item['message'] ?? '-' }}</div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </details>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-grid gap-1">
                                                <a href="{{ route('import-histories.export', [$history, 'failed']) }}"
                                                   class="btn btn-sm btn-outline-danger {{ $history->failed_count < 1 ? 'disabled' : '' }}"
                                                   @if($history->failed_count < 1) aria-disabled="true" tabindex="-1" @endif>
                                                    <i class="fas fa-file-excel me-1"></i> Gagal ({{ number_format($history->failed_count) }})
                                                </a>
                                                <a href="{{ route('import-histories.export', [$history, 'skipped']) }}"
                                                   class="btn btn-sm btn-outline-warning {{ $history->skipped_count < 1 ? 'disabled' : '' }}"
                                                   @if($history->skipped_count < 1) aria-disabled="true" tabindex="-1" @endif>
                                                    <i class="fas fa-file-excel me-1"></i> Dilewati ({{ number_format($history->skipped_count) }})
                                                </a>
                                                <a href="{{ route('import-histories.export', [$history, 'updated']) }}"
                                                   class="btn btn-sm btn-outline-primary {{ $history->updated_count < 1 ? 'disabled' : '' }}"
                                                   @if($history->updated_count < 1) aria-disabled="true" tabindex="-1" @endif>
                                                    <i class="fas fa-file-excel me-1"></i> Update ({{ number_format($history->updated_count) }})
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            Belum ada history import untuk filter ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $importHistories->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
