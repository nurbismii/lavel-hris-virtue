@extends('layouts.app')

@section('title', 'Akun Saya')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="fas fa-file-signature text-primary me-2"></i>
                Profil saya
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
            <!-- Account Information -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold">
                    Informasi Akun
                </div>
                <div class="card-body">

                    <div class="row mb-3">
                        <div class="col-md-4 fw-semibold">User ID</div>
                        <div class="col-md-8">{{ auth()->user()->id }}</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-semibold">Email</div>
                        <div class="col-md-8">
                            {{ auth()->user()->email }}
                            @if(auth()->user()->email_verified_at)
                            <span class="badge bg-success ms-2">Terverifikasi</span>
                            @else
                            <span class="badge bg-danger ms-2">Belum Verifikasi</span>
                            @endif
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-semibold">Terakhir Login</div>
                        <div class="col-md-8">
                            {{ auth()->user()->terakhir_login ?? '-' }}
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-semibold">Tanggal Dibuat</div>
                        <div class="col-md-8">
                            {{ formatDateIndonesia(auth()->user()->created_at) }}
                        </div>
                    </div>

                </div>
            </div>

            <!-- Employee Information -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold">
                    Informasi Karyawan
                </div>
                <div class="card-body">

                    <div class="row mb-3">
                        <div class="col-md-4 fw-semibold">NIK</div>
                        <div class="col-md-8">
                            {{ auth()->user()->employee->nik ?? '-' }}
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-semibold">Nama Karyawan</div>
                        <div class="col-md-8">
                            {{ auth()->user()->employee->nama_karyawan ?? '-' }}
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-semibold">Divisi</div>
                        <div class="col-md-8">
                            {{ auth()->user()->employee->divisi->nama_divisi ?? '-' }}
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-semibold">Departemen</div>
                        <div class="col-md-8">
                            {{ auth()->user()->employee->divisi->departemen->departemen ?? '-' }}
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-semibold">Posisi</div>
                        <div class="col-md-8">
                            {{ auth()->user()->employee->posisi ?? '-' }}
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-semibold">Kepala Departemen</div>
                        <div class="col-md-8">
                            {{ auth()->user()->employee->divisi->departemen->kepala_dept ?? '-' }}
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-semibold">Cuti Tersedia</div>
                        <div class="col-md-8">
                            <span class="badge bg-info text-dark">
                                {{ auth()->user()->employee->sisa_cuti ?? 0 }} Hari
                            </span>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
@endsection


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