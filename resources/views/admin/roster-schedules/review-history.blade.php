@extends('layouts.app')

@section('title', 'Review Riwayat Roster')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">Review Riwayat Roster</h4>
                <small class="text-muted">{{ optional($history->employee)->nama_karyawan }} · {{ $history->employee_nik }} · {{ $history->period_label }}</small>
            </div>
            <div class="ms-md-auto"><a href="{{ route('roster-schedules.history', ['search' => $history->employee_nik]) }}" class="btn btn-light border">Kembali</a></div>
        </div>

        <div class="row justify-content-center">
            <div class="col-xl-9">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-light"><strong>Sumber Excel</strong></div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-md-3">Jadwal Off</dt>
                            <dd class="col-md-9">{{ $history->scheduled_off_start->format('d M Y') }} – {{ $history->scheduled_off_end->format('d M Y') }}</dd>
                            <dt class="col-md-3">Bagian Periode</dt>
                            <dd class="col-md-9">{{ $history->remark_segment ?: 'Tidak ada keterangan khusus untuk periode ini.' }}</dd>
                            <dt class="col-md-3">Keterangan Asli Tahunan</dt>
                            <dd class="col-md-9" style="white-space:pre-wrap">{{ $history->raw_remark ?: '-' }}</dd>
                            <dt class="col-md-3">Lokasi Sel</dt>
                            <dd class="col-md-9 mb-0">
                                {{ $history->source_file }} · Jadwal {{ $history->source_sheet }}!{{ $history->source_column }}{{ $history->source_row }}
                                @if($history->source_remark_column) · Remark {{ $history->source_remark_column }}{{ $history->source_row }} @endif
                            </dd>
                        </dl>
                    </div>
                </div>

                <form method="POST" action="{{ route('roster-schedules.history.update', $history) }}" id="historyReviewForm">
                    @csrf
                    @method('PUT')
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Hasil realisasi</label>
                                    <select name="classification" class="form-select @error('classification') is-invalid @enderror" required>
                                        @foreach($classificationOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('classification', $history->classification) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('classification')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Catatan review HR</label>
                                    <textarea name="review_note" rows="4" maxlength="500" class="form-control @error('review_note') is-invalid @enderror" required placeholder="Tuliskan dasar konfirmasi, misalnya arsip pengajuan atau konfirmasi HR sebelumnya.">{{ old('review_note', $history->review_note) }}</textarea>
                                    @error('review_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-white text-end">
                            <button type="submit" class="btn btn-success" data-loading-text="Menyimpan review...">Konfirmasi Riwayat</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('historyReviewForm').addEventListener('submit', function () {
        const button = this.querySelector('button[type="submit"]');
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + button.dataset.loadingText;
    });
});
</script>
@endpush
