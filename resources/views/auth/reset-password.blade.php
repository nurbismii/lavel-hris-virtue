@extends('layouts.app-auth')

@section('content')
<div class="container-fluid auth-wrapper p-0">
    <div class="row g-0 min-vh-100">

        {{-- LEFT SIDE --}}
        <div class="col-lg-7 d-none d-lg-flex align-items-center auth-brand-panel">
            <div class="brand-content">
                <div class="brand-logo-box">
                    <img src="{{ asset('assets/img/kaiadmin/icon-2.png') }}" alt="V-People">
                </div>

                <div class="brand-badge">
                    <i class="fas fa-shield-alt"></i>
                    New Password Setup
                </div>

                <h1 class="brand-title">
                    Buat password baru yang lebih aman.
                </h1>

                <p class="brand-text">
                    Gunakan password yang kuat untuk melindungi akses akun V-People.
                    Setelah password berhasil diperbarui, Anda dapat login kembali menggunakan password baru.
                </p>

                <div class="feature-card">
                    <div class="feature-item">
                        <i class="fas fa-key"></i>
                        <strong>Password Baru</strong>
                        <span>Ganti password lama dengan yang lebih aman.</span>
                    </div>

                    <div class="feature-item">
                        <i class="fas fa-user-lock"></i>
                        <strong>Akun Aman</strong>
                        <span>Token reset digunakan untuk validasi akses.</span>
                    </div>

                    <div class="feature-item">
                        <i class="fas fa-sign-in-alt"></i>
                        <strong>Login Ulang</strong>
                        <span>Masuk kembali setelah reset berhasil.</span>
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
                        <img src="{{ asset('assets/img/kaiadmin/icon-2.png') }}" alt="V-People">
                    </div>
                    <h4 class="fw-bold mb-1">V-People</h4>
                    <p class="text-muted small mb-0">PT Virtue Dragon Nickel Industry</p>
                </div>

                <div class="card reset-card">
                    <div class="card-body">

                        <div class="mb-4">
                            <div class="auth-badge">
                                <i class="fas fa-lock"></i>
                                Reset Password
                            </div>

                            <h3 class="reset-title mb-2">Buat Password Baru</h3>
                            <p class="reset-subtitle mb-0">
                                Masukkan email dan password baru untuk memulihkan akses akun.
                            </p>
                        </div>

                        <div class="password-info">
                            <i class="fas fa-info-circle"></i>
                            <span>
                                Gunakan password minimal 8 karakter dan hindari memakai password yang mudah ditebak.
                            </span>
                        </div>

                        <form method="POST" action="{{ route('password.update') }}">
                            @csrf

                            <input type="hidden" name="token" value="{{ $token }}">

                            {{-- Email --}}
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    Email Address
                                </label>

                                <div class="input-group-modern">
                                    <i class="fas fa-envelope input-icon"></i>
                                    <input
                                        id="email"
                                        type="email"
                                        name="email"
                                        value="{{ $email ?? old('email') }}"
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

                            {{-- Password --}}
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    Password Baru
                                </label>

                                <div class="input-group-modern">
                                    <i class="fas fa-key input-icon"></i>
                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        class="form-control form-control-modern pe-5 @error('password') is-invalid @enderror"
                                        placeholder="Masukkan password baru"
                                        autocomplete="new-password"
                                        required>

                                    <button
                                        type="button"
                                        class="password-toggle"
                                        onclick="toggleResetPassword('password', 'passwordIcon')"
                                        aria-label="Tampilkan password">
                                        <i id="passwordIcon" class="fas fa-eye"></i>
                                    </button>

                                    @error('password')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                            </div>

                            {{-- Confirm Password --}}
                            <div class="mb-4">
                                <label for="password_confirmation" class="form-label">
                                    Konfirmasi Password
                                </label>

                                <div class="input-group-modern">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input
                                        id="password_confirmation"
                                        type="password"
                                        name="password_confirmation"
                                        class="form-control form-control-modern pe-5"
                                        placeholder="Ulangi password baru"
                                        autocomplete="new-password"
                                        required>

                                    <button
                                        type="button"
                                        class="password-toggle"
                                        onclick="toggleResetPassword('password_confirmation', 'passwordConfirmIcon')"
                                        aria-label="Tampilkan konfirmasi password">
                                        <i id="passwordConfirmIcon" class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            {{-- Button --}}
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-reset">
                                    Reset Password
                                    <i class="fas fa-arrow-right ms-2"></i>
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

<script>
    function toggleResetPassword(inputId, iconId) {
        const passwordInput = document.getElementById(inputId);
        const passwordIcon = document.getElementById(iconId);

        if (!passwordInput || !passwordIcon) return;

        const isPassword = passwordInput.type === 'password';

        passwordInput.type = isPassword ? 'text' : 'password';
        passwordIcon.classList.toggle('fa-eye', !isPassword);
        passwordIcon.classList.toggle('fa-eye-slash', isPassword);
    }
</script>
@endsection