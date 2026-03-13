@extends('layouts.app')

@section('content')
<div class="container-fluid px-3">
    <div class="page-inner">

        {{-- HEADER --}}
        <div class="mb-3">
            <h5 class="fw-bold mb-0">
                <i class="fas fa-home text-primary me-2"></i>
                Dashboard
            </h5>
            <small class="text-muted d-block">
                Halo, {{ $user->employee->nama_karyawan }}
            </small>
        </div>

        {{-- WELCOME --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-3">
                <h6 class="fw-semibold mb-1">Selamat Datang 👋</h6>
                <small class="text-muted">
                    Anda login sebagai <strong>{{ auth()->user()->email }}</strong>
                </small>
            </div>
        </div>

        {{-- STATISTICS --}}
        <div class="row g-2 mb-3">

            <div class="col-6 col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3 text-center">
                        <small class="text-muted d-block">Status</small>
                        <span class="badge bg-{{ auth()->user()->status == 'aktif' ? 'success' : 'secondary' }}">
                            {{ ucfirst(auth()->user()->status) }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3 text-center">
                        <small class="text-muted d-block">Login Terakhir</small>
                        <small class="fw-semibold">
                            {{ auth()->user()->terakhir_login ?? '-' }}
                        </small>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3 text-center">
                        <small class="text-muted d-block">Email</small>
                        <small class="fw-semibold">
                            {{ auth()->user()->email_verified_at ? 'Verified' : 'Belum Verifikasi' }}
                        </small>
                    </div>
                </div>
            </div>

        </div>

        {{-- QUICK MENU --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold py-2">
                Menu Utama
            </div>

            <div class="card-body">
                <div class="row g-3 text-center">

                    <div class="col-6 col-md-3">
                        <a href="{{ route('presensi.index') }}" class="text-decoration-none text-dark">
                            <div class="p-3 border rounded-3 h-100">
                                <i class="fas fa-map-pin fa-lg text-danger mb-2"></i>
                                <div class="small fw-semibold">Presensi</div>
                            </div>
                        </a>
                    </div>

                    <div class="col-6 col-md-3">
                        <a href="{{ route('cuti.index') }}" class="text-decoration-none text-dark">
                            <div class="p-3 border rounded-3 h-100">
                                <i class="fas fa-sign-out-alt fa-lg text-primary mb-2"></i>
                                <div class="small fw-semibold">Cuti</div>
                            </div>
                        </a>
                    </div>

                    <div class="col-6 col-md-3">
                        <a href="#" class="text-decoration-none text-dark">
                            <div class="p-3 border rounded-3 h-100">
                                <i class="fas fa-file-signature fa-lg text-warning mb-2"></i>
                                <div class="small fw-semibold">Izin</div>
                            </div>
                        </a>
                    </div>

                    <div class="col-6 col-md-3">
                        <a href="{{ route('roster.index') }}" class="text-decoration-none text-dark">
                            <div class="p-3 border rounded-3 h-100">
                                <i class="fas fa-plane-departure fa-lg text-info mb-2"></i>
                                <div class="small fw-semibold">Roster</div>
                            </div>
                        </a>
                    </div>

                </div>
            </div>
        </div>

        {{-- RECENT ACTIVITY --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold py-2">
                Aktivitas
            </div>

            <div class="card-body p-3">
                <ul class="list-group list-group-flush small">

                    <li class="list-group-item px-0">
                        Login Terakhir
                        <span class="text-muted float-end mx-1">
                            {{ auth()->user()->terakhir_login ?? '-' }}
                        </span>
                    </li>

                    <li class="list-group-item px-0">
                        Role
                        <span class="float-end fw-semibold mx-1">
                            {{ auth()->user()->role->permission_role ?? 'User' }}
                        </span>
                    </li>

                </ul>
            </div>
        </div>

    </div>
</div>
@endsection