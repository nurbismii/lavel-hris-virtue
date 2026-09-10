@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="page-header">
            <h3 class="fw-bold mb-3">{{ __('Edit Karyawan') }}</h3>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('user.update', $user->nik_karyawan) }}" method="POST">
                    @csrf
                    @method('PUT')

                    {{-- DATA UTAMA --}}
                    <h5 class="fw-bold mb-3">{{ __('Data Utama') }}</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">NIK</label>
                            <input type="text" class="form-control" value="{{ $user->nik_karyawan }}" readonly>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Nama Karyawan') }}</label>
                            <input type="text" class="form-control" value="{{ $user->employee->nama_karyawan ?? '-' }}" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Email') }}</label>
                            <input type="email" name="email" class="form-control" value="{{ $user->email }}" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Status') }}</label>
                            <select name="status" class="form-control form-select" required>
                                <option value="aktif" {{ $user->status == 'aktif' ? 'selected' : '' }}>{{ __('Aktif') }}</option>
                                <option value="tidak aktif" {{ $user->status == '' ? 'selected' : '' }}>{{ __('Tidak Aktif') }}</option>
                            </select>
                        </div>

                        {{-- BUTTON --}}
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
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