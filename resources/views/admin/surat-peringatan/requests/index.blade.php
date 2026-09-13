@extends('layouts.app')
@section('title', 'Pengajuan Pelanggaran')
@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/admin-warning-letters.css') }}">
@endpush
@section('content')
<div class="container-fluid warning-letter-shell" data-action-loading-scope="ignore"><div class="page-inner">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div><h3 class="fw-bold mb-1">Pengajuan Pelanggaran</h3><p class="text-muted mb-0">Tinjau pengajuan dan pantau penerbitan surat peringatan.</p></div>
        <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-secondary" href="{{ route('surat-peringatan.index') }}">Data pelanggaran</a><a class="btn btn-primary" href="{{ route('warning-letter-requests.create') }}">Buat pelanggaran</a></div>
    </div>
    @include('admin.surat-peringatan.requests.errors')
    <div class="card"><div class="card-body">
        <form method="GET" class="row g-3 align-items-end mb-4">
            <div class="col-md-4"><label for="status" class="form-label">Status</label><select id="status" name="status" class="form-select"><option value="">Semua status</option>@foreach(\App\Models\WarningLetterRequest::statuses() as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-4"><label for="nik" class="form-label">NIK karyawan</label><input id="nik" name="nik" class="form-control" value="{{ request('nik') }}" maxlength="32" placeholder="Masukkan NIK lengkap"></div>
            <div class="col-md-4 d-flex gap-2"><button class="btn btn-primary" type="submit">Terapkan</button><a href="{{ route('warning-letter-requests.index') }}" class="btn btn-outline-secondary">Reset</a></div>
        </form>
        <div class="row g-3">
        @forelse($letters as $letter)
            <div class="col-md-6 col-xl-4"><article class="border rounded p-3 h-100 d-flex flex-column gap-2">
                <div class="d-flex flex-wrap gap-2 justify-content-between"><span class="badge bg-{{ ['pending' => 'warning text-dark', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary'][$letter->status] }}">{{ \App\Models\WarningLetterRequest::statuses()[$letter->status] }}</span><small class="text-muted">#{{ $letter->id }}</small></div>
                <h5 class="mb-0 text-break">{{ $letter->employee_snapshot['name'] }}</h5><div class="text-muted small">{{ $letter->nik }} · {{ $letter->level_sp }}</div>
                <div class="small text-break">{{ $letter->letter_number ?: 'Nomor diberikan setelah approval' }}</div>
                <div class="small text-muted text-break">Diajukan {{ $letter->created_at->format('d/m/Y H:i') }} oleh {{ $letter->created_by_name }}</div>
                <a class="btn btn-sm btn-outline-primary mt-auto align-self-start" href="{{ route('warning-letter-requests.show', $letter) }}">Lihat detail</a>
            </article></div>
        @empty
            <div class="col-12 text-center text-muted py-5"><i class="fas fa-clipboard-list fa-2x mb-3"></i><p class="mb-0">Belum ada pengajuan yang sesuai filter.</p></div>
        @endforelse
        </div>
        <div class="mt-4">{{ $letters->links() }}</div>
    </div></div>
</div></div>
@endsection
