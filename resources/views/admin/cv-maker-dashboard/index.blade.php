@extends('layouts.app')
@section('title', __('Dashboard CV Maker'))
@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/admin-cv-maker-dashboard.css') }}">
@endpush
@section('content')
<div class="container-fluid"><div class="page-inner ui-page">
    <div class="ui-page-header mb-3"><div class="ui-page-heading">
        <div class="ui-page-icon"><i class="fas fa-chart-pie" aria-hidden="true"></i></div>
        <div><h4 class="ui-page-title">{{ __('Dashboard CV Maker') }}</h4>
        <p class="ui-page-subtitle">{{ __('Monitoring profil karyawan VDNI dan VDNIP sesuai cakupan akses Anda.') }}</p></div>
    </div></div>
    <form id="cvDashboardFilters" class="cv-dashboard-panel mb-3">
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label" for="cv_filter_employment_status">{{ __('Status karyawan') }}</label>
                <select id="cv_filter_employment_status" name="employment_status" class="form-select">
                    <option value="active" selected>{{ __('Aktif') }}</option>
                    <option value="inactive">{{ __('Tidak aktif') }}</option>
                    <option value="all">{{ __('Semua status') }}</option>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label" for="cv_filter_area">{{ __('Perusahaan') }}</label>
                <select id="cv_filter_area" name="area" class="form-select"><option value="">{{ __('VDNI dan VDNIP') }}</option>
                    @foreach($companies as $company)<option value="{{ $company }}">{{ $company }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="form-label" for="cv_filter_departemen">{{ __('Departemen') }}</label>
                <select id="cv_filter_departemen" name="departemen" class="form-select"><option value="">{{ __('Semua departemen') }}</option>
                    @foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->departemen }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="form-label" for="cv_filter_progress_status">{{ __('Kelengkapan CV') }}</label>
                <select id="cv_filter_progress_status" name="cv_progress_status" class="form-select">
                    <option value="">{{ __('Semua status') }}</option><option value="complete">{{ __('CV lengkap') }}</option><option value="in_progress">{{ __('Dalam pengisian') }}</option>
                    <option value="not_synced">{{ __('Belum tersinkronisasi') }}</option><option value="no_account">{{ __('Akun tidak ditemukan') }}</option><option value="no_profile">{{ __('Belum membuat profil') }}</option>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label" for="cv_filter_review_status">{{ __('Pemeriksaan HR') }}</label>
                <select id="cv_filter_review_status" name="cv_review_status" class="form-select"><option value="">{{ __('Semua pemeriksaan') }}</option>
                    @foreach(\App\Models\CvMakerProgressStatus::reviewLabels() as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="form-label" for="cv_filter_reminder">{{ __('Reminder') }}</label>
                <select id="cv_filter_reminder" name="cv_reminder" class="form-select"><option value="">{{ __('Semua') }}</option><option value="needs_reminder">{{ __('Perlu reminder') }}</option><option value="not_needed">{{ __('Tidak perlu reminder') }}</option></select>
            </div>
            <div class="col-md-3"><label class="form-label" for="cv_filter_progress_step">{{ __('Tahap pengisian') }}</label>
                <select id="cv_filter_progress_step" name="cv_progress_step[]" class="form-select"><option value="">{{ __('Semua tahap') }}</option>
                    @foreach(['Data Pribadi', 'Ringkasan Profil', 'Pendidikan', 'Pengalaman', 'Keahlian', 'Sertifikasi', 'Tambahan', 'Dokumen'] as $label)
                    <option value="{{ $loop->iteration }}">{{ $loop->iteration }} — {{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end"><button type="reset" class="btn btn-light border">{{ __('Reset filter') }}</button></div>
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
