@extends('layouts.app')

@section('title', __('self_service.account.my_account_title'))

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/user-account.css') }}">
@endpush

@section('content')
@php
    $currentUser = auth()->user();
    $employee = $currentUser->employee;
    $employeePhotoUrl = optional($employee)->document_photo_url;
    $employeeInitials = $currentUser->avatar_initials;
@endphp
<div class="container-fluid">
    <div class="page-inner">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-file-signature text-primary me-2"></i>
                    {{ __('self_service.account.profile_title') }}
                </h4>
                <small class="text-muted">
                    {{ __('self_service.account.subtitle') }}
                </small>
            </div>
            <a href="{{ route('dashboard.karyawan') }}" class="btn btn-sm btn-light">
                <i class="fas fa-arrow-left me-1"></i> {{ __('self_service.common.back_to_dashboard') }}
            </a>
        </div>

        <div class="row justify-content-center">

            <div class="col-lg-4 mb-4">
                <!-- Profile Card -->
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center">

                        <div class="mb-3">
                            <div class="avatar-circle bg-primary text-white mx-auto">
                                @if($employeePhotoUrl)
                                    <img src="{{ $employeePhotoUrl }}" alt="{{ $employee->nama_karyawan ?? $currentUser->name ?? __('self_service.common.user_fallback') }}">
                                @else
                                    {{ $employeeInitials }}
                                @endif
                            </div>
                        </div>

                        <h5 class="mb-1">
                            {{ $employee->nama_karyawan ?? '-' }}
                        </h5>

                        <p class="text-muted mb-2">
                            {{ $employee->divisi->nama_divisi ?? '-' }}
                        </p>

                        <span class="badge bg-success">
                            {{ ucfirst($currentUser->status) }}
                        </span>

                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <!-- Account Information -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white fw-bold">
                        {{ __('self_service.account.account_information') }}
                    </div>
                    <div class="card-body">

                <div class="row mb-3">
                    <div class="col-md-4 fw-semibold">User ID</div>
                    <div class="col-md-8">{{ $currentUser->id }}</div>
                </div>

                        <div class="row mb-3">
                            <div class="col-md-4 fw-semibold">Email</div>
                            <div class="col-md-8">
                                {{ $currentUser->email }}
                                @if($currentUser->email_verified_at)
                                <span class="badge bg-success ms-2">{{ __('self_service.account.verified') }}</span>
                                @else
                                <span class="badge bg-danger ms-2">{{ __('self_service.account.not_verified') }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4 fw-semibold">{{ __('self_service.account.last_login') }}</div>
                            <div class="col-md-8">
                                {{ $currentUser->terakhir_login ?? '-' }}
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4 fw-semibold">{{ __('self_service.account.created_date') }}</div>
                            <div class="col-md-8">
                                {{ formatDateIndonesia($currentUser->created_at) }}
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Employee Information -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">
                        {{ __('self_service.account.employee_information') }}
                    </div>
                    <div class="card-body">

                        <div class="row mb-3">
                            <div class="col-md-4 fw-semibold">NIK</div>
                            <div class="col-md-8">
                                {{ $employee->nik ?? '-' }}
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4 fw-semibold">{{ __('tables.employee') }}</div>
                            <div class="col-md-8">
                                {{ $employee->nama_karyawan ?? '-' }}
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4 fw-semibold">{{ __('tables.division') }}</div>
                            <div class="col-md-8">
                                {{ $employee->divisi->nama_divisi ?? '-' }}
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4 fw-semibold">{{ __('tables.department') }}</div>
                            <div class="col-md-8">
                                {{ $employee->divisi->departemen->departemen ?? '-' }}
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4 fw-semibold">{{ __('tables.position') }}</div>
                            <div class="col-md-8">
                                {{ $employee->posisi ?? '-' }}
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4 fw-semibold">{{ __('self_service.account.department_head') }}</div>
                            <div class="col-md-8">
                                {{ $employee->divisi->departemen->kepala_dept ?? '-' }}
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4 fw-semibold">{{ __('self_service.account.available_leave') }}</div>
                            <div class="col-md-8">
                                <span class="badge bg-info text-dark">
                                    {{ $employee->sisa_cuti ?? 0 }} {{ __('self_service.common.day') }}
                                </span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

