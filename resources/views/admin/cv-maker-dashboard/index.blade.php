@extends('layouts.app')
@section('title', 'Dashboard CV Maker')
@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/admin-cv-maker-dashboard.css') }}">
@endpush
@section('content')
<div class="container-fluid"><div class="page-inner ui-page">
    <div class="ui-page-header mb-3"><div class="ui-page-heading">
        <div class="ui-page-icon"><i class="fas fa-chart-pie" aria-hidden="true"></i></div>
        <div><h4 class="ui-page-title">Dashboard CV Maker</h4>
        <p class="ui-page-subtitle">Monitoring profil karyawan VDNI dan VDNIP sesuai cakupan akses Anda.</p></div>
    </div></div>
    <form id="cvDashboardFilters" class="cv-dashboard-panel mb-3">
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label" for="cv_filter_employment_status">Status karyawan</label>
                <select id="cv_filter_employment_status" name="employment_status" class="form-select">
                    <option value="active" selected>Aktif</option>
                    <option value="inactive">Tidak aktif</option>
                    <option value="all">Semua status</option>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label" for="cv_filter_area">Perusahaan</label>
                <select id="cv_filter_area" name="area" class="form-select"><option value="">VDNI dan VDNIP</option>
                    @foreach($companies as $company)<option value="{{ $company }}">{{ $company }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="form-label" for="cv_filter_departemen">Departemen</label>
                <select id="cv_filter_departemen" name="departemen" class="form-select"><option value="">Semua departemen</option>
                    @foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->departemen }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="form-label" for="cv_filter_progress_status">Kelengkapan CV</label>
                <select id="cv_filter_progress_status" name="cv_progress_status" class="form-select">
                    <option value="">Semua status</option><option value="complete">CV lengkap</option><option value="in_progress">Dalam pengisian</option>
                    <option value="not_synced">Belum tersinkronisasi</option><option value="no_account">Akun tidak ditemukan</option><option value="no_profile">Belum membuat profil</option>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label" for="cv_filter_review_status">Pemeriksaan HR</label>
                <select id="cv_filter_review_status" name="cv_review_status" class="form-select"><option value="">Semua pemeriksaan</option>
                    @foreach(\App\Models\CvMakerProgressStatus::reviewLabels() as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="form-label" for="cv_filter_reminder">Reminder</label>
                <select id="cv_filter_reminder" name="cv_reminder" class="form-select"><option value="">Semua</option><option value="needs_reminder">Perlu reminder</option><option value="not_needed">Tidak perlu reminder</option></select>
            </div>
            <div class="col-md-3"><label class="form-label" for="cv_filter_progress_step">Tahap pengisian</label>
                <select id="cv_filter_progress_step" name="cv_progress_step[]" class="form-select"><option value="">Semua tahap</option>
                    @foreach(['Data Pribadi', 'Ringkasan Profil', 'Pendidikan', 'Pengalaman', 'Keahlian', 'Sertifikasi', 'Tambahan', 'Dokumen'] as $label)
                    <option value="{{ $loop->iteration }}">{{ $loop->iteration }} — {{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end"><button type="reset" class="btn btn-light border">Reset filter</button></div>
        </div>
    </form>
    @include('admin.cv-maker-dashboard.dashboard')
</div></div>
@endsection
@push('scripts')
<script>
$(function() {
    @include('admin.cv-maker-dashboard.scripts')
});
</script>
@endpush
