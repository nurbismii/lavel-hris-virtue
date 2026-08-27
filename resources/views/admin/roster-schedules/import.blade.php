@extends('layouts.app')

@section('title', 'Import Riwayat Roster')

@section('content')
<div class="container-fluid" id="roster-import-page"
    @if(isset($history))
        data-status-url="{{ route('roster-schedules.import.status', $history) }}"
        data-terminal="{{ in_array($history->status, ['completed', 'failed', 'validation_failed', 'expired'], true) ? '1' : '0' }}"
    @endif>
    <div class="page-inner">
        <div class="d-flex justify-content-between align-items-center pt-2 pb-4">
            <div><h4 class="fw-bold mb-1">Import Riwayat Roster</h4><small class="text-muted">Workbook diproses di penyimpanan private dan belum mengubah jadwal.</small></div>
            <a class="btn btn-outline-secondary" href="{{ route('roster-schedules.index') }}">Kembali</a>
        </div>
        @if(!isset($history))
        <div class="card border-0 shadow-sm"><div class="card-body">
            <form id="roster-import-form" method="POST" action="{{ route('roster-schedules.import.store') }}" enctype="multipart/form-data">
                @csrf
                <label class="form-label" for="roster-file">File roster (.xlsx)</label>
                <input class="form-control @error('file') is-invalid @enderror" id="roster-file" name="file" type="file" accept=".xlsx" required>
                @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <button class="btn btn-primary mt-3" type="submit">Unggah dan Validasi</button>
            </form>
        </div></div>
        @else
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <div class="d-flex justify-content-between"><strong>Status</strong><span class="badge bg-secondary" data-import-status>{{ $history->status_label }}</span></div>
            <div class="row g-3 mt-1">
                @foreach(['total_rows' => 'Baris', 'blocker_count' => 'Blocker', 'warning_count' => 'Peringatan'] as $key => $label)
                <div class="col-md-4"><div class="border rounded p-3"><small class="text-muted">{{ $label }}</small><div class="fs-4" data-summary="{{ $key }}">{{ $history->summary[$key] ?? 0 }}</div></div></div>
                @endforeach
            </div>
            @if($history->status === 'validation_failed')
            <a class="btn btn-outline-danger mt-3" href="{{ route('roster-schedules.import.failure', $history) }}">Unduh File Kegagalan</a>
            @endif
        </div></div>
        <div class="alert alert-info">Pratinjau rinci tersedia saat upload selesai. Konfirmasi import belum tersedia pada tahap ini.</div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/roster-schedule-import.js') }}"></script>
@endpush
