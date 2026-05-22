@extends('layouts.app-auth')

@section('content')

<div class="container-fluid auth-wrapper p-0">
    <div class="row g-0 min-vh-100">

        {{-- LEFT SIDE --}}
        <div class="col-lg-7 d-none d-lg-flex align-items-center auth-brand-panel">
            <div class="brand-content">
                <div class="brand-logo-box">
                    <img src="{{ asset('assets/img/kaiadmin/icon-2.PNG') }}" alt="V-People">
                </div>

                <div class="brand-badge">
                    <i class="fas fa-key"></i>
                    Password Recovery
                </div>

                <h1 class="brand-title">
                    Pulihkan akses akun dengan aman.
                </h1>

                <p class="brand-text">
                    Masukkan email yang terdaftar pada akun V-People.
                    Sistem akan mengirimkan link reset password agar Anda dapat membuat password baru.
                </p>

                <div class="feature-card">
                    <div class="feature-item">
                        <i class="fas fa-envelope-open-text"></i>
                        <strong>Email Terdaftar</strong>
                        <span>Link reset dikirim ke email akun Anda.</span>
                    </div>

                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <strong>Aman</strong>
                        <span>Reset dilakukan melalui token validasi.</span>
                    </div>

                    <div class="feature-item">
                        <i class="fas fa-sign-in-alt"></i>
                        <strong>Akses Ulang</strong>
                        <span>Login kembali setelah password diganti.</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT SIDE --}}
        <div class="col-lg-5 auth-form-panel">
            <div class="reset-box">

                {{-- MOBILE LOGO --}}
                <div class="d-lg-none text-center mb-4">
                    <div class="mobile-logo">
                        <img src="{{ asset('assets/img/kaiadmin/icon-2.PNG') }}" alt="V-People">
                    </div>
                    <h4 class="fw-bold mb-1">V-People</h4>
                    <p class="text-muted small mb-0">PT Virtue Dragon Nickel Industry</p>
                </div>

                <div class="card reset-card">
                    <div class="card-body">

                        <div class="mb-4">
                            <div class="auth-badge">
                                <i class="fas fa-lock-open"></i>
                                Reset Password
                            </div>

                            <h3 class="reset-title mb-2">Lupa Password?</h3>
                            <p class="reset-subtitle mb-0">
                                Masukkan email akun Anda untuk menerima link reset password.
                            </p>
                        </div>

                        @if (session('status'))
                        <div class="alert-modern">
                            <i class="fas fa-check-circle"></i>
                            <span>{{ session('status') }}</span>
                        </div>
                        @endif

                        <div class="reset-info">
                            <i class="fas fa-info-circle"></i>
                            <span>
                                Pastikan email yang dimasukkan sesuai dengan email yang digunakan saat registrasi akun V-People.
                            </span>
                        </div>

                        <form method="POST" action="{{ route('password.email') }}">
                            @csrf

                            {{-- Email --}}
                            <div class="mb-4">
                                <label for="email" class="form-label">
                                    Email Address
                                </label>

                                <div class="input-group-modern">
                                    <i class="fas fa-envelope input-icon"></i>
                                    <input
                                        id="email"
                                        type="email"
                                        name="email"
                                        value="{{ old('email') }}"
                                        class="form-control form-control-modern @error('email') is-invalid @enderror"
                                        placeholder="nama@company.com"
                                        autocomplete="email"
                                        required>

                                    @error('email')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                            </div>

                            {{-- Button --}}
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-reset">
                                    Kirim Link Reset
                                    <i class="fas fa-paper-plane ms-2"></i>
                                </button>
                            </div>

                            <div class="text-center small">
                                <a href="{{ route('login') }}" class="auth-link">
                                    <i class="fas fa-arrow-left me-1"></i>
                                    Kembali ke Login
                                </a>
                            </div>
                        </form>

                    </div>
                </div>

                <div class="text-center mt-4 auth-footer">
                    © {{ date('Y') }} PT Virtue Dragon Nickel Industry. All rights reserved.
                </div>

            </div>
        </div>
    </div>
</div>
@endsection