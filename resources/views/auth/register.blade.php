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
                    <i class="fas fa-user-plus"></i>
                    V-People Registration
                </div>

                <h1 class="brand-title">
                    Buat akun dan mulai akses layanan HR.
                </h1>

                <p class="brand-text">
                    Daftarkan akun V-People menggunakan data karyawan yang valid untuk mengakses
                    layanan presensi, slip gaji, pengajuan, dan informasi karyawan secara aman.
                </p>

                <div class="feature-card">
                    <div class="feature-item">
                        <i class="fas fa-id-card"></i>
                        <strong>Validasi NIK</strong>
                        <span>Akun terhubung dengan data karyawan.</span>
                    </div>

                    <div class="feature-item">
                        <i class="fas fa-lock"></i>
                        <strong>Akses Aman</strong>
                        <span>Data login digunakan untuk sistem internal.</span>
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
            <div class="register-box">

                {{-- MOBILE LOGO --}}
                <div class="d-lg-none text-center mb-4">
                    <div class="mobile-logo">
                        <img src="{{ asset('assets/img/kaiadmin/icon-2.PNG') }}" alt="V-People">
                    </div>
                    <h4 class="fw-bold mb-1">V-People</h4>
                    <p class="text-muted small mb-0">PT Virtue Dragon Nickel Industry</p>
                </div>

                <div class="card register-card">
                    <div class="card-body">

                        <div class="mb-4">
                            <div class="auth-badge">
                                <i class="fas fa-shield-alt"></i>
                                Secure Registration
                            </div>

                            <h3 class="register-title mb-2">Daftar Akun Baru</h3>
                            <p class="register-subtitle mb-0">
                                Isi data akun dengan benar agar dapat terhubung dengan data karyawan.
                            </p>
                        </div>

                        <form method="POST" action="{{ route('register') }}">
                            @csrf

                            {{-- NIK --}}
                            <div class="mb-3">
                                <label for="nik_karyawan" class="form-label">
                                    Nomor Induk Karyawan (NIK)
                                </label>

                                <div class="input-group-modern">
                                    <i class="fas fa-id-badge input-icon"></i>
                                    <input
                                        id="nik_karyawan"
                                        type="text"
                                        name="nik_karyawan"
                                        class="form-control form-control-modern @error('nik_karyawan') is-invalid @enderror"
                                        value="{{ old('nik_karyawan') }}"
                                        placeholder="Masukkan NIK karyawan"
                                        autocomplete="off"
                                        required>

                                    @error('nik_karyawan')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                            </div>

                            {{-- Email --}}
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    Email
                                </label>

                                <div class="input-group-modern">
                                    <i class="fas fa-envelope input-icon"></i>
                                    <input
                                        id="email"
                                        type="email"
                                        name="email"
                                        class="form-control form-control-modern @error('email') is-invalid @enderror"
                                        value="{{ old('email') }}"
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
                                    Password
                                </label>

                                <div class="input-group-modern">
                                    <i class="fas fa-key input-icon"></i>
                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        class="form-control form-control-modern pe-5 @error('password') is-invalid @enderror"
                                        placeholder="Masukkan password"
                                        autocomplete="new-password"
                                        required>

                                    <button
                                        type="button"
                                        class="password-toggle"
                                        onclick="toggleRegisterPassword('password', 'passwordIcon')"
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

                            {{-- Konfirmasi Password --}}
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
                                        placeholder="Ulangi password"
                                        autocomplete="new-password"
                                        required>

                                    <button
                                        type="button"
                                        class="password-toggle"
                                        onclick="toggleRegisterPassword('password_confirmation', 'passwordConfirmIcon')"
                                        aria-label="Tampilkan konfirmasi password">
                                        <i id="passwordConfirmIcon" class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            {{-- Button --}}
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-register">
                                    Daftar Sekarang
                                    <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </div>

                            <div class="text-center small text-muted">
                                Sudah punya akun?
                                <a href="{{ route('login') }}" class="auth-link">
                                    Masuk di sini
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
    function toggleRegisterPassword(inputId, iconId) {
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