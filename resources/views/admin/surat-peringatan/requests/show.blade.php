@extends('layouts.app')
@section('title', 'Detail Pengajuan Pelanggaran')
@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/admin-warning-letters.css') }}">
@endpush
@section('content')
<div class="container-fluid warning-letter-shell" data-action-loading-scope="ignore"><div class="page-inner">
    <div class="d-flex justify-content-between flex-wrap gap-3 mb-4"><div><h3 class="fw-bold">Pengajuan Pelanggaran #{{ $letter->id }}</h3><span class="badge bg-{{ ['pending' => 'warning text-dark', 'approved' => 'success', 'rejected' => 'danger'][$letter->status] }}">{{ \App\Models\WarningLetterRequest::statuses()[$letter->status] }}</span></div><a href="{{ route('warning-letter-requests.index') }}" class="btn btn-outline-secondary align-self-start">Kembali</a></div>
    @include('admin.surat-peringatan.requests.errors')
    <div class="row g-4"><div class="col-lg-8"><div class="card"><div class="card-body">
        <h5 class="fw-semibold">{{ \App\Models\WarningLetterRequest::levels()[$letter->level_sp] }}</h5><p class="text-muted text-break">{{ $letter->letter_number ?: 'Nomor surat belum diterbitkan' }}</p>
        <dl class="row">
            @foreach(['nik' => 'NIK', 'name' => 'Nama karyawan', 'department' => 'Departemen', 'division' => 'Divisi', 'position' => 'Jabatan'] as $field => $label)<div class="col-sm-6 mb-3"><dt class="small text-muted">{{ $label }}</dt><dd class="mb-0 text-break">{{ $letter->employee_snapshot[$field] }}</dd></div>@endforeach
            <div class="col-sm-6 mb-3"><dt class="small text-muted">Nama HOD</dt><dd class="mb-0 text-break">{{ $letter->hod_name }}</dd></div>
            <div class="col-sm-6 mb-3"><dt class="small text-muted">Masa berlaku</dt><dd class="mb-0">{{ $letter->tgl_mulai->format('d/m/Y') }} – {{ $letter->tgl_berakhir->format('d/m/Y') }}</dd></div>
            <div class="col-sm-6 mb-3"><dt class="small text-muted">Pelapor</dt><dd class="mb-0 text-break">{{ $letter->pelapor }}</dd></div>
        </dl>
        <h6 class="fw-semibold">Keterangan pelanggaran</h6><div class="warning-description">{{ $letter->keterangan }}</div>
        @if($letter->status === \App\Models\WarningLetterRequest::REJECTED)<div class="alert alert-danger mt-3 mb-0"><strong>Alasan penolakan</strong><div class="warning-description">{{ $letter->rejection_reason }}</div></div>@endif
    </div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-body">
        <h5 class="fw-semibold">Jejak proses</h5><p class="small text-break">Diajukan oleh <strong>{{ $letter->created_by_name }}</strong><br>{{ $letter->created_at->format('d/m/Y H:i') }}</p>
        @if($letter->reviewed_at)<p class="small text-break">Diperiksa oleh <strong>{{ $letter->reviewed_by_name }}</strong><br>{{ $letter->reviewed_at->format('d/m/Y H:i') }}</p>@endif
        <h6 class="fw-semibold mt-3">Pihak penandatangan</h6><p class="small text-break">{{ $letter->letter_snapshot['signer_name'] ?? optional($signer)->signer_name ?? 'Belum tersedia' }}<br>{{ $letter->letter_snapshot['signer_position'] ?? optional($signer)->signer_position ?? '-' }}</p>
        @if($letter->status === \App\Models\WarningLetterRequest::APPROVED)<a class="btn btn-primary w-100" href="{{ route('warning-letter-requests.download', $letter) }}" data-warning-download>Download Surat PDF</a><p class="small text-muted mt-2 mb-0">Surat menggunakan salinan data dan tanda tangan saat penerbitan.</p>@endif
    </div></div>
    @if($letter->status === \App\Models\WarningLetterRequest::PENDING)
        @can('review', $letter)
        <div class="card"><div class="card-body"><h5 class="fw-semibold">Approval HR / Super Admin</h5><p class="small text-muted">Periksa data sebelum menerbitkan. Surat yang telah terbit tidak dapat diubah melalui menu edit pelanggaran.</p>
            <form method="POST" action="{{ route('warning-letter-requests.review', $letter) }}" data-warning-submit data-warning-review data-loading-text="Memproses...">@csrf
                <label for="decision" class="form-label">Keputusan</label><select id="decision" name="decision" class="form-select mb-3" required><option value="">Pilih keputusan</option><option value="approve" @selected(old('decision') === 'approve')>Setujui dan terbitkan SP</option><option value="reject" @selected(old('decision') === 'reject')>Tolak pengajuan</option></select>
                <label for="reason" class="form-label">Alasan penolakan</label><textarea id="reason" name="reason" class="form-control mb-3" rows="3" maxlength="2000">{{ old('reason') }}</textarea><button type="submit" class="btn btn-primary w-100">Simpan keputusan</button>
            </form>
        </div></div>
        @else<div class="alert alert-info">Menunggu pemeriksaan HR/Super Admin.</div>@endcan
    @endif
    </div></div>
</div></div>
@endsection
@push('scripts')<script src="{{ versioned_asset('assets/js/admin-warning-letters.js') }}"></script>@endpush
