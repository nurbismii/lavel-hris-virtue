@extends('layouts.app')
@section('title', __('self_service.permission.create_title'))
@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/user-izin.css') }}">
@endpush
@section('content')
<div class="container-fluid"><div class="page-inner ui-page izin-page">
    <div class="ui-page-header">
        <div><h4 class="ui-page-title">{{ __('self_service.permission.create_title') }}</h4><p class="ui-page-subtitle">{{ __('self_service.permission.create_subtitle') }}</p></div>
        <a href="{{ route('izin.index') }}" class="btn btn-outline-secondary ui-btn-icon"><i class="fas fa-arrow-left" aria-hidden="true"></i> {{ __('self_service.actions.back') }}</a>
    </div>
    <section class="ui-panel"><div class="ui-panel__body">
        <form id="izin-form" action="{{ route('izin.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @include('user.izin._fields')
            <div class="ui-actions ui-actions--sm-stack mt-4"><button type="submit" class="btn btn-primary ui-btn-icon" data-loading-text="{{ __('Mengirim pengajuan...') }}">{{ __('self_service.actions.submit_permission') }}</button></div>
        </form>
    </div></section>
</div></div>
@endsection
@push('scripts')
<script src="{{ versioned_asset('assets/js/user-izin.js') }}"></script>
@endpush
