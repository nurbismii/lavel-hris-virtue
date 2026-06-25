@extends('layouts.app')

@section('title', __('navigation.account_settings'))

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
                    <i class="fas fa-cog text-primary me-2"></i>
                    {{ __('self_service.account.password_title') }}
                </h4>
                <small class="text-muted">
                    {{ __('self_service.account.subtitle') }}
                </small>
            </div>
            <a href="{{ route('dashboard.karyawan') }}" class="btn btn-sm btn-light">
                <i class="fas fa-arrow-left me-1"></i> {{ __('self_service.actions.back') }}
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

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">
                        {{ __('self_service.account.password_card_title') }}
                    </div>

                    <div class="card-body">

                        @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                        @endif

                        <form action="{{ route('pengaturan-akun.store') }}" method="POST">
                            @csrf

                            <!-- Password Lama -->
                            <div class="mb-3">
                                <label class="form-label">{{ __('self_service.account.old_password') }}</label>
                                <input type="password"
                                    name="current_password"
                                    class="form-control @error('current_password') is-invalid @enderror"
                                    required>

                                @error('current_password')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            <!-- Password Baru -->
                            <div class="mb-3">
                                <label class="form-label">{{ __('self_service.account.new_password') }}</label>
                                <input type="password"
                                    name="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    required>

                                @error('password')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            <!-- Konfirmasi Password -->
                            <div class="mb-3">
                                <label class="form-label">{{ __('self_service.account.confirm_new_password') }}</label>
                                <input type="password"
                                    name="password_confirmation"
                                    class="form-control"
                                    required>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('self_service.actions.save_new_password') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
