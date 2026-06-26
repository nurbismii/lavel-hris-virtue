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

                <div class="mb-3">
                    <span class="badge rounded-pill bg-light text-primary px-3 py-2">
                        {{ __('auth_ui.employee_platform') }}
                    </span>
                </div>

                <h1 class="brand-title">
                    {{ __('auth_ui.hero_title') }}
                </h1>

                <p class="brand-text">
                    {{ __('auth_ui.hero_description') }}
                </p>

                <div class="feature-card">
                    <div class="feature-item">
                        <i class="fas fa-user-shield"></i>
                        <strong>{{ __('auth_ui.feature_secure_title') }}</strong>
                        <span>{{ __('auth_ui.feature_secure_text') }}</span>
                    </div>

                    <div class="feature-item">
                        <i class="fas fa-clock"></i>
                        <strong>{{ __('auth_ui.feature_realtime_title') }}</strong>
                        <span>{{ __('auth_ui.feature_realtime_text') }}</span>
                    </div>

                    <div class="feature-item">
                        <i class="fas fa-mobile-alt"></i>
                        <strong>{{ __('auth_ui.feature_mobile_title') }}</strong>
                        <span>{{ __('auth_ui.feature_mobile_text') }}</span>
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
                        <img src="{{ asset('assets/img/kaiadmin/icon-2.png') }}" alt="V-People">
                    </div>
                    <h4 class="fw-bold mb-1">V-People</h4>
                    <p class="text-muted small mb-0">PT Virtue Dragon Nickel Industry</p>
                </div>

                <div class="card login-card">
                    <div class="card-body">

                        <div class="mb-4">
                            <div class="auth-badge">
                                <i class="fas fa-lock"></i>
                                {{ __('auth_ui.secure_login') }}
                            </div>

                            <h3 class="login-title mb-2">{{ __('auth_ui.welcome_back') }}</h3>
                            <p class="login-subtitle mb-0">
                                {{ __('auth_ui.login_subtitle') }}
                            </p>
                        </div>

                        <form method="POST" action="{{ route('login') }}">
                            @csrf
                            @if(!empty($redirect))
                            <input type="hidden" name="redirect" value="{{ $redirect }}">
                            @endif

                            {{-- Email --}}
                            <div class="mb-3">
                                <label for="email" class="form-label">{{ __('auth_ui.email_address') }}</label>
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
                                <label for="password" class="form-label">{{ __('auth_ui.password') }}</label>
                                <div class="input-group-modern">
                                    <i class="fas fa-key input-icon"></i>
                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        class="form-control form-control-modern pe-5 @error('password') is-invalid @enderror"
                                        placeholder="{{ __('auth_ui.password_placeholder') }}"
                                        autocomplete="current-password"
                                        required>

                                    <button
                                        type="button"
                                        class="password-toggle"
                                        onclick="togglePassword()"
                                        aria-label="{{ __('auth_ui.show_password') }}">
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
                                        value="1"
                                        {{ old('remember') ? 'checked' : '' }}>
                                    <label class="form-check-label small text-muted" for="remember">
                                        {{ __('auth_ui.remember_me') }}
                                    </label>
                                </div>

                                @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="auth-link small">
                                    {{ __('auth_ui.forgot_password') }}
                                </a>
                                @endif
                            </div>

                            {{-- Button --}}
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-login">
                                    {{ __('auth_ui.login_now') }}
                                    <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </div>

                            {{-- Register Link --}}
                            @if (Route::has('register'))
                            <div class="text-center small text-muted">
                                {{ __('auth_ui.no_account') }}
                                <a href="{{ route('register') }}" class="auth-link">
                                    {{ __('auth_ui.register_here') }}
                                </a>
                            </div>
                            @endif
                        </form>
                    </div>
                </div>

                <div class="text-center mt-4 auth-footer">
                    &copy; {{ date('Y') }} PT Virtue Dragon Nickel Industry. {{ __('auth_ui.all_rights_reserved') }}
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
