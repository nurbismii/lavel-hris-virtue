@extends('layouts.app')

@section('title', __('self_service.account.profile_title'))

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/user-account.css') }}">
@endpush

@section('content')
@php
    $currentUser = auth()->user();
    $employee = $currentUser->employee;
    $employeePhotoUrl = optional($employee)->document_photo_url;
    $employeeName = $employee->nama_karyawan ?? $currentUser->name ?? __('self_service.common.user_fallback');
    $employeeFields = [
        ['NIK', $employee->nik ?? '-'],
        [__('tables.employee'), $employee->nama_karyawan ?? '-'],
        [__('tables.division'), $employee->divisi->nama_divisi ?? '-'],
        [__('tables.department'), $employee->divisi->departemen->departemen ?? '-'],
        [__('tables.position'), $employee->posisi ?? '-'],
        [__('self_service.account.department_head'), $employee->divisi->departemen->kepala_dept ?? '-'],
    ];
@endphp
<div class="page-inner profile-page">
    <header class="profile-page__heading">
        <div>
            <span class="profile-page__eyebrow">V-People</span>
            <h1>{{ __('self_service.account.profile_title') }}</h1>
            <p>{{ __('self_service.account.subtitle') }}</p>
        </div>
        <a href="{{ route($currentUser->preferredHomeRouteName()) }}" class="btn btn-light profile-page__back">
            <i class="fas fa-arrow-left me-2" aria-hidden="true"></i>{{ __('self_service.common.back_to_dashboard') }}
        </a>
    </header>

    <div class="profile-page__layout">
        <aside class="profile-page__identity">
            <section class="card profile-card profile-card--identity">
                <div class="profile-card__cover" aria-hidden="true"></div>
                <div class="card-body">
                    <div class="avatar-circle profile-page__avatar">
                        @if($employeePhotoUrl)
                            <img src="{{ $employeePhotoUrl }}" alt="{{ $employeeName }}">
                        @else
                            {{ $currentUser->avatar_initials }}
                        @endif
                    </div>
                    <h2 class="profile-page__name">{{ $employeeName }}</h2>
                    <p class="profile-page__position">{{ $employee->posisi ?? '-' }}</p>
                    <span class="profile-page__status">{{ ucfirst($currentUser->status ?? '-') }}</span>
                    <div class="profile-page__division">
                        <i class="fas fa-building" aria-hidden="true"></i>
                        <span>{{ $employee->divisi->nama_divisi ?? '-' }}</span>
                    </div>
                    <a href="{{ route('update.akun') }}" class="btn btn-primary profile-page__settings">
                        <i class="fas fa-cog me-2" aria-hidden="true"></i>{{ __('navigation.account_settings') }}
                    </a>
                </div>
            </section>
            <section class="profile-page__leave">
                <span class="profile-page__leave-icon"><i class="fas fa-calendar-check" aria-hidden="true"></i></span>
                <div>
                    <span class="profile-page__leave-label">{{ __('self_service.account.available_leave') }}</span>
                    <div><strong>{{ $employee->sisa_cuti ?? 0 }}</strong> {{ __('self_service.common.day') }}</div>
                </div>
            </section>
        </aside>

        <div class="profile-page__details">
            <section class="card profile-card" aria-labelledby="profile-account-heading">
                <div class="card-header">
                    <span class="profile-card__icon"><i class="fas fa-user-shield" aria-hidden="true"></i></span>
                    <h2 id="profile-account-heading">{{ __('self_service.account.account_information') }}</h2>
                </div>
                <div class="card-body">
                    <dl class="profile-page__fields">
                        <div class="profile-page__field">
                            <dt>{{ __('User ID') }}</dt>
                            <dd>{{ $currentUser->id }}</dd>
                        </div>
                        <div class="profile-page__field">
                            <dt>{{ __('Email') }}</dt>
                            <dd>
                                <span>{{ $currentUser->email }}</span>
                                <span class="profile-page__verification {{ $currentUser->email_verified_at ? 'is-verified' : 'is-pending' }}">
                                    <i class="fas {{ $currentUser->email_verified_at ? 'fa-check-circle' : 'fa-clock' }}" aria-hidden="true"></i>
                                    {{ __($currentUser->email_verified_at ? 'self_service.account.verified' : 'self_service.account.not_verified') }}
                                </span>
                            </dd>
                        </div>
                        <div class="profile-page__field">
                            <dt>{{ __('self_service.account.last_login') }}</dt>
                            <dd>{{ $currentUser->terakhir_login ?? '-' }}</dd>
                        </div>
                        <div class="profile-page__field">
                            <dt>{{ __('self_service.account.created_date') }}</dt>
                            <dd>{{ formatDateIndonesia($currentUser->created_at) }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="card profile-card" aria-labelledby="profile-employee-heading">
                <div class="card-header">
                    <span class="profile-card__icon"><i class="fas fa-id-card" aria-hidden="true"></i></span>
                    <h2 id="profile-employee-heading">{{ __('self_service.account.employee_information') }}</h2>
                </div>
                <div class="card-body">
                    <dl class="profile-page__fields">
                        @foreach($employeeFields as $field)
                            <div class="profile-page__field">
                                <dt>{{ $field[0] }}</dt>
                                <dd>{{ $field[1] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
