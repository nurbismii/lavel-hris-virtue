@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">

        {{-- HEADER --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">
                    <i class="fas fa-home text-primary me-2"></i>
                    Dashboard Pengguna
                </h3>
                <small class="text-muted">
                    Selamat datang kembali, {{ $user->employee->nama_karyawan }}
                </small>
            </div>
        </div>

        {{-- WELCOME CARD --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <h5 class="fw-semibold">
                    Halo 👋
                </h5>
                <p class="mb-0 text-muted">
                    Anda login sebagai
                    <strong>{{ auth()->user()->email }}</strong>.
                    Silakan gunakan menu di bawah untuk mengakses fitur sistem.
                </p>
            </div>
        </div>

        {{-- STATISTIC CARDS --}}
        <div class="row g-3 mb-4">

            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-muted">Status Akun</h6>
                        <h4 class="fw-bold">
                            <span class="badge bg-{{ auth()->user()->status == 'aktif' ? 'success' : 'secondary' }}">
                                {{ ucfirst(auth()->user()->status) }}
                            </span>
                        </h4>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-muted">Terakhir Login</h6>
                        <h5 class="fw-semibold">
                            {{ auth()->user()->terakhir_login ?? '-' }}
                        </h5>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-muted">Email Verified</h6>
                        <h5 class="fw-semibold">
                            {{ auth()->user()->email_verified_at ? 'Sudah Verifikasi' : 'Belum Verifikasi' }}
                        </h5>
                    </div>
                </div>
            </div>

        </div>

        {{-- QUICK MENU --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-semibold">
                Menu Cepat
            </div>
            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-3">
                        <a href="{{ route('presensi.index') }}" class="text-decoration-none">
                            <div class="card text-center border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <i class="fas fa-map-pin fa-2x text-danger mb-2"></i>
                                    <h6 class="fw-semibold">Presensi</h6>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-md-3">
                        <a href="{{ route('cuti.index') }}" class="text-decoration-none">
                            <div class="card text-center border-0 shadow-sm h-100 hover-shadow">
                                <div class="card-body">
                                    <i class="fas fa-sign-out-alt fa-2x text-primary mb-2"></i>
                                    <h6 class="fw-semibold">Cuti tahunan</h6>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-md-3">
                        <a href="#" class="text-decoration-none">
                            <div class="card text-center border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <i class="fas fa-file-signature fa-2x text-warning mb-2"></i>
                                    <h6 class="fw-semibold">Izin (Paid & Unpaid)</h6>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-md-3">
                        <a href="{{ route('roster.index') }}" class="text-decoration-none">
                            <div class="card text-center border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <i class="fas fa-plane-departure fa-2x text-ligth mb-2"></i>
                                    <h6 class="fw-semibold">Roster</h6>
                                </div>
                            </div>
                        </a>
                    </div>

                </div>
            </div>
        </div>

        {{-- RECENT ACTIVITY --}}
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold">
                Aktivitas Terakhir
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item">
                        Login ke sistem : 
                        <span class="text-muted float-end">
                            {{ auth()->user()->terakhir_login ?? '-' }}
                        </span>
                    </li>
                    <li class="list-group-item">
                        Role Anda :
                        <strong>{{ auth()->user()->role->permission_role ?? 'User' }}</strong>
                    </li>
                </ul>
            </div>
        </div>

    </div>
</div>
@endsection