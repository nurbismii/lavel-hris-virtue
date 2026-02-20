@extends('layouts.app')

@section('title', 'Pengaturan Akun')

@push('styles')
<style>
    .avatar-circle {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        font-size: 36px;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>
@endpush

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="fas fa-cog text-primary me-2"></i>
                Ganti Kata Sandi
            </h4>
            <small class="text-muted">
                Tetap jaga kerahasiaan data kamu
            </small>
        </div>
        <a href="{{ route('dashboard.karyawan') }}" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <div class="row justify-content-center">

        <div class="col-lg-4 mb-4">
            <!-- Profile Card -->
            <div class="card shadow-sm border-0">
                <div class="card-body text-center">

                    <div class="mb-3">
                        <div class="avatar-circle bg-primary text-white mx-auto">
                            {{ strtoupper(substr(auth()->user()->employee->nama_karyawan ?? 'U',0,1)) }}
                        </div>
                    </div>

                    <h5 class="mb-1">
                        {{ auth()->user()->employee->nama_karyawan ?? '-' }}
                    </h5>

                    <p class="text-muted mb-2">
                        {{ auth()->user()->employee->divisi->nama_divisi ?? '-' }}
                    </p>

                    <span class="badge bg-success">
                        {{ ucfirst(auth()->user()->status) }}
                    </span>

                </div>
            </div>
        </div>

        <div class="col-lg-8">

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold">
                    Ganti Password
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
                            <label class="form-label">Password Lama</label>
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
                            <label class="form-label">Password Baru</label>
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
                            <label class="form-label">Konfirmasi Password Baru</label>
                            <input type="password"
                                name="password_confirmation"
                                class="form-control"
                                required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                Simpan Password Baru
                            </button>
                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>

</div>
@endsection