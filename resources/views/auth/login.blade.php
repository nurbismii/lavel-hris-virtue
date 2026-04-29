@extends('layouts.app-auth')

@section('content')

<div class="container-fluid auth-wrapper p-0">
    <div class="row g-0 min-vh-100">

        {{-- LEFT SIDE --}}
        <div class="col-lg-7 d-none d-lg-flex align-items-center auth-brand-panel">
            <div class="brand-content">
                <div class="brand-logo-box">
                    <img src="{{ asset('assets/img/kaiadmin/favicon-1.png') }}" alt="V-People">
                </div>

                <div class="mb-3">
                    <span class="badge rounded-pill bg-light text-primary px-3 py-2">
                        PT VDNI Employee Platform
                    </span>
                </div>

                <h1 class="brand-title">
                    Kelola HR lebih cepat, rapi, dan terintegrasi.
                </h1>

                <p class="brand-text">
                    V-People membantu proses administrasi karyawan, presensi,
                    pengajuan, dokumen, dan data personal dalam satu sistem yang aman
                    serta mudah digunakan.
                </p>

                <div class="feature-card">
                    <div class="feature-item">
                        <i class="fas fa-user-shield"></i>
                        <strong>Secure Access</strong>
                        <span>Login aman untuk pengguna terdaftar.</span>
                    </div>

                    <div class="feature-item">
                        <i class="fas fa-clock"></i>
                        <strong>Real-time</strong>
                        <span>Data HR diperbarui lebih cepat.</span>
                    </div>

                    <div class="feature-item">
                        <i class="fas fa-mobile-alt"></i>
                        <strong>Mobile Ready</strong>
                        <span>Nyaman digunakan dari perangkat mobile.</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT SIDE --}}
        <div class="col-lg-5 auth-form-panel">
            <div class="login-box">

                {{-- MOBILE LOGO --}}
                <div class="d-lg-none text-center mb-4">
                    <div class="mobile-logo">
                        <img src="{{ asset('assets/img/kaiadmin/favicon-1.png') }}" alt="V-People">
                    </div>
                    <h4 class="fw-bold mb-1">V-People</h4>
                    <p class="text-muted small mb-0">PT Virtue Dragon Nickel Industry</p>
                </div>

                <div class="card login-card">
                    <div class="card-body">

                        <div class="mb-4">
                            <div class="auth-badge">
                                <i class="fas fa-lock"></i>
                                Secure Login
                            </div>

                            <h3 class="login-title mb-2">Selamat datang kembali</h3>
                            <p class="login-subtitle mb-0">
                                Masuk menggunakan akun V-People kamu untuk melanjutkan pekerjaan.
                            </p>
                        </div>

                        <form method="POST" action="{{ route('login') }}">
                            @csrf

                            {{-- Email --}}
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
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
                                        required
                                        autofocus>
                                    @error('email')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                            </div>

                            {{-- Password --}}
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group-modern">
                                    <i class="fas fa-key input-icon"></i>
                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        class="form-control form-control-modern pe-5 @error('password') is-invalid @enderror"
                                        placeholder="Masukkan password"
                                        autocomplete="current-password"
                                        required>

                                    <button
                                        type="button"
                                        class="password-toggle"
                                        onclick="togglePassword()"
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

                            {{-- Options --}}
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="remember"
                                        id="remember"
                                        {{ old('remember') ? 'checked' : '' }}>
                                    <label class="form-check-label small text-muted" for="remember">
                                        Ingat saya
                                    </label>
                                </div>

                                @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="auth-link small">
                                    Lupa password?
                                </a>
                                @endif
                            </div>

                            {{-- Button --}}
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-login">
                                    Masuk Sekarang
                                    <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </div>

                            {{-- Register Link --}}
                            @if (Route::has('register'))
                            <div class="text-center small text-muted">
                                Belum punya akun?
                                <a href="{{ route('register') }}" class="auth-link">
                                    Daftar di sini
                                </a>
                            </div>
                            @endif
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
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const passwordIcon = document.getElementById('passwordIcon');

        if (!passwordInput || !passwordIcon) return;

        const isPassword = passwordInput.type === 'password';

        passwordInput.type = isPassword ? 'text' : 'password';
        passwordIcon.classList.toggle('fa-eye', !isPassword);
        passwordIcon.classList.toggle('fa-eye-slash', isPassword);
    }
</script>
@endsection