@extends('layouts.app-auth')

@section('content')
<div class="container-fluid min-vh-100">
    <div class="row min-vh-100">

        {{-- LEFT SIDE (Branding) --}}
        <div class="col-lg-7 d-none d-lg-flex align-items-center justify-content-center bg-primary text-white">
            <div class="text-left px-5">
                <h1 class="fw-bold mb-3">PT VDNI | V-People</h1>
                <p class="fs-5 opacity-75">
                    Manage gaji karyawan, data pribadi dan pengajuan<br>
                    dalam satu platform modern.
                </p>
            </div>
        </div>

        {{-- RIGHT SIDE (Form) --}}
        <div class="col-lg-5 d-flex align-items-center justify-content-center">
            <div class="w-100" style="max-width: 420px;">

                {{-- Logo --}}
                <div class="text-center mb-4">
                    <img src="{{ asset('assets/img/kaiadmin/favicon-1.png') }}" height="100" width="120" class="mb-3" />
                    <h4 class="fw-bold mb-1">Masuk ke akun kamu</h4>
                    <p class="text-muted mb-0">
                        Mari kita bantu kamu kembali bekerja
                    </p>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">

                        <form method="POST" action="{{ route('login') }}">
                            @csrf

                            {{-- Email --}}
                            <div class="mb-3">
                                <label class="form-label small text-muted">
                                    Email address
                                </label>
                                <input
                                    type="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    class="form-control form-control rounded-3 @error('email') is-invalid @enderror"
                                    placeholder="you@company.com"
                                    required
                                    autofocus>
                                @error('email')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- Password --}}
                            <div class="mb-3">
                                <label class="form-label small text-muted">
                                    Password
                                </label>
                                <input
                                    type="password"
                                    name="password"
                                    class="form-control form-control rounded-3 @error('password') is-invalid @enderror"
                                    placeholder="••••••••"
                                    required>
                                @error('password')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- Options --}}
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="remember"
                                        id="remember"
                                        {{ old('remember') ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="remember">
                                        Remember me
                                    </label>
                                </div>

                                @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="small text-decoration-none">
                                    Forgot password?
                                </a>
                                @endif
                            </div>

                            {{-- Button --}}
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary rounded-3">
                                    Sign In
                                </button>
                            </div>

                            {{-- Register Link --}}
                            @if (Route::has('register'))
                            <div class="text-center small">
                                Belum punya akun?
                                <a href="{{ route('register') }}">
                                    Daftar disini
                                </a>
                            </div>
                            @endif
                        </form>

                    </div>
                </div>

                {{-- Footer --}}
                <div class="text-center mt-4 small text-muted">
                    © {{ date('Y') }} PT Virtue Dragon Nickel Industry
                </div>

            </div>
        </div>

    </div>
</div>
@endsection