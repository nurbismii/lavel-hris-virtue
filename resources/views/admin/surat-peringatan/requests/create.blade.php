@extends('layouts.app')
@section('title', 'Buat Pelanggaran')
@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/admin-warning-letters.css') }}">
@endpush
@section('content')
<div class="container-fluid warning-letter-shell" data-action-loading-scope="ignore"><div class="page-inner">
    <h3 class="fw-bold">Buat Pelanggaran</h3><p class="text-muted">Cari karyawan berdasarkan NIK atau nama, lengkapi laporan, lalu ajukan untuk approval HR/Super Admin.</p>
    @include('admin.surat-peringatan.requests.errors')
    <form action="{{ route('warning-letter-requests.store') }}" method="POST" id="warning-create-form" data-warning-submit data-loading-text="Mengajukan...">
        @csrf<input type="hidden" name="submission_token" value="{{ old('submission_token', $token) }}">
        <div class="row g-4">
            <div class="col-lg-8"><div class="card"><div class="card-body">
                <h5 class="fw-semibold mb-3">Karyawan dan laporan</h5>
                <label for="employee-query" class="form-label">Karyawan <span class="text-danger">*</span></label>
                <p class="small text-muted mb-2">Cari dengan NIK atau nama. Hasil dibatasi sesuai departemen/divisi yang menjadi cakupan akun Anda. Karyawan tidak harus memiliki akun login.</p>
                <input type="hidden" id="employee-nik" name="nik" value="{{ old('nik') }}">
                <div class="warning-employee-search mb-2"><input type="search" maxlength="100" id="employee-query" class="form-control flex-grow-1" value="{{ old('nik') }}" placeholder="Masukkan minimal 2 karakter NIK atau nama" autocomplete="off" aria-controls="employee-options"><button type="button" id="find-employee" class="btn btn-outline-primary" data-url="{{ route('warning-letter-requests.employees') }}">Cari karyawan</button></div>
                <div id="employee-feedback" class="small text-muted mb-2" role="status" aria-live="polite">Masukkan minimal 2 karakter. NIK yang cocok akan dipilih otomatis; untuk pencarian nama, pilih karyawan dari daftar.</div>
                <div id="employee-options" class="warning-employee-options mb-3" role="listbox" aria-label="Hasil pencarian karyawan" hidden></div>
                <dl id="employee-result" class="row bg-light rounded p-3 mx-0" hidden>
                    @foreach(['name' => 'Nama', 'department' => 'Departemen', 'division' => 'Divisi', 'position' => 'Jabatan'] as $field => $label)<div class="col-sm-6 mb-2"><dt class="small text-muted">{{ $label }}</dt><dd class="mb-0 text-break" data-employee-field="{{ $field }}"></dd></div>@endforeach
                </dl>
                <div class="row g-3 mt-1">
                    <div class="col-md-6"><label for="hod_name" class="form-label">Nama HOD *</label><input id="hod_name" name="hod_name" value="{{ old('hod_name') }}" class="form-control" maxlength="180" required></div>
                    <div class="col-md-6"><label for="level_sp" class="form-label">Level pelanggaran *</label><select id="level_sp" name="level_sp" class="form-select" required><option value="">Pilih level SP</option>@foreach(\App\Models\WarningLetterRequest::levels() as $value => $label)<option value="{{ $value }}" @selected(old('level_sp') === $value)>{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label for="tgl_mulai" class="form-label">Mulai berlaku *</label><input type="date" id="tgl_mulai" name="tgl_mulai" class="form-control" value="{{ old('tgl_mulai', now()->format('Y-m-d')) }}" required></div>
                    <div class="col-md-6"><label for="tgl_berakhir" class="form-label">Akhir berlaku (otomatis)</label><input type="date" id="tgl_berakhir" class="form-control bg-light" data-validity-months="{{ \App\Models\WarningLetterRequest::VALIDITY_MONTHS }}" aria-describedby="validity-help" readonly><small id="validity-help" class="text-muted">Otomatis {{ \App\Models\WarningLetterRequest::VALIDITY_MONTHS }} bulan setelah tanggal mulai.</small></div>
                    <div class="col-12"><label for="keterangan" class="form-label">Keterangan pelanggaran *</label><textarea id="keterangan" name="keterangan" rows="6" class="form-control" maxlength="4000" required>{{ old('keterangan') }}</textarea><small class="text-muted">Tuliskan kejadian dan tanggalnya secara jelas. Maksimal 4.000 karakter; teks ditampilkan sesuai input.</small></div>
                    <div class="col-12"><label for="pelapor" class="form-label">Nama pelapor *</label><input id="pelapor" name="pelapor" class="form-control" maxlength="128" value="{{ old('pelapor') }}" required></div>
                </div>
            </div></div></div>
            <div class="col-lg-4"><div class="card"><div class="card-body">
                <h5 class="fw-semibold">Penerbit surat</h5><p class="mb-1 text-break">{{ optional($signer)->signer_name ?: 'Master pihak pertama belum tersedia' }}</p><p class="text-muted text-break">{{ optional($signer)->signer_position ?: '-' }}</p>
                <p class="small text-muted">Nama dan tanda tangan HR diambil dari master pihak pertama saat approval. Kolom tanda tangan HOD disediakan untuk ditandatangani manual.</p>
            </div></div></div>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-4"><button type="submit" id="submit-warning" class="btn btn-primary" disabled title="Cari dan pilih karyawan terlebih dahulu">Ajukan pelanggaran</button><a href="{{ route('warning-letter-requests.index') }}" class="btn btn-outline-secondary">Kembali</a></div>
    </form>
</div></div>
@endsection
@push('scripts')<script src="{{ versioned_asset('assets/js/admin-warning-letters.js') }}"></script>@endpush
