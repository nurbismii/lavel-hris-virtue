@extends('layouts.app-auth')

@section('content')
<div class="container-fluid min-vh-100">
    <div class="row min-vh-100">

        {{-- LEFT SIDE --}}
        <div class="col-lg-7 d-none d-lg-flex align-items-center justify-content-center bg-primary text-white">
            <div class="text-left px-5">
                <h1 class="fw-bold mb-3">PT VDNI | V-People</h1>
                <p class="fs-5 opacity-75">
                    Buat akun baru dan mulai kelola data<br>
                    karyawan secara profesional.
                </p>
            </div>
        </div>

        {{-- RIGHT SIDE --}}
        <div class="col-lg-5 d-flex align-items-center justify-content-center">
            <div class="w-100" style="max-width: 420px;">

                <div class="text-center mb-4">
                    <img src="{{ asset('assets/img/kaiadmin/favicon-1.png') }}" height="100" width="120" class="mb-3" />
                    <h4 class="fw-bold mb-1">Daftar Akun Baru</h4>
                    <p class="text-muted mb-0">
                        Isi data dengan lengkap dan benar
                    </p>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">

                        <form method="POST" action="{{ route('register') }}">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label small text-muted">Nomor Induk Karyawan (NIK)</label>
                                <input type="text" name="nik_karyawan"
                                    class="form-control rounded-3 @error('nik_karyawan') is-invalid @enderror"
                                    value="{{ old('name') }}" required>
                                @error('nik_karyawan')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">Email</label>
                                <input type="email" name="email"
                                    class="form-control rounded-3 @error('email') is-invalid @enderror"
                                    value="{{ old('email') }}" required>
                                @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">Password</label>
                                <input type="password" name="password"
                                    class="form-control rounded-3 @error('password') is-invalid @enderror"
                                    required>
                                @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">Konfirmasi Password</label>
                                <input type="password" name="password_confirmation"
                                    class="form-control rounded-3" required>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary rounded-3">
                                    Daftar
                                </button>
                            </div>

                            <div class="text-center mt-3 small">
                                Sudah punya akun?
                                <a href="{{ route('login') }}">Masuk disini</a>
                            </div>

                        </form>

                    </div>
                </div>

                <div class="text-center mt-4 small text-muted">
                    © {{ date('Y') }} PT Virtue Dragon Nickel Industry
                </div>

            </div>
        </div>

    </div>
</div>
@endsection