@extends('layouts.app')
@section('title', __('Edit Akun Karyawan'))

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="page-header">
            <h3 class="fw-bold mb-3">{{ __('Edit Akun Karyawan') }}</h3>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('user.update', $user->nik_karyawan) }}" method="POST" data-submit-lock>
                    @csrf
                    @method('PUT')
                    @include('partials.form-errors')

                    {{-- DATA UTAMA --}}
                    <h5 class="fw-bold mb-3">{{ __('Data Utama') }}</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="account-nik" class="form-label">NIK</label>
                            <input id="account-nik" type="text" class="form-control" value="{{ $user->nik_karyawan }}" readonly>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="account-name" class="form-label">{{ __('Nama Karyawan') }}</label>
                            <input id="account-name" type="text" class="form-control" value="{{ $user->employee->nama_karyawan ?? '-' }}" readonly>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="account-email" class="form-label">{{ __('Email') }} <span class="text-danger">*</span></label>
                            <input id="account-email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" maxlength="255" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                            @error('email')<div id="email-error" class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="account-status" class="form-label">{{ __('Status') }} <span class="text-danger">*</span></label>
                            <select id="account-status" name="status" class="form-control form-select @error('status') is-invalid @enderror" required @error('status') aria-invalid="true" aria-describedby="status-error" @enderror>
                                <option value="aktif" {{ old('status', $user->status) === 'aktif' ? 'selected' : '' }}>{{ __('Aktif') }}</option>
                                <option value="tidak aktif" {{ old('status', $user->status) === 'tidak aktif' ? 'selected' : '' }}>{{ __('Tidak Aktif') }}</option>
                            </select>
                            @error('status')<div id="status-error" class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- BUTTON --}}
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary" data-loading-text="{{ __('Menyimpan...') }}">
                                <i class="fas fa-save me-1"></i> {{ __('Update') }}
                            </button>
                            <a href="{{ route('user.index') }}" class="btn btn-secondary">
                                {{ __('Kembali') }}
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
