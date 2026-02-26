@extends('layouts.app-auth')

@section('content')
<div class="container-fluid min-vh-100">
    <div class="row min-vh-100">

        {{-- LEFT SIDE (Branding) --}}
        <div class="col-lg-7 d-none d-lg-flex align-items-center justify-content-center bg-primary text-white">
            <div class="text-left px-5">
                <h1 class="fw-bold mb-3">PT VDNI | V-People</h1>
                <p class="fs-5 opacity-75">
                    Reset password akun kamu dengan aman<br>
                    melalui email terdaftar.
                </p>
            </div>
        </div>

        {{-- RIGHT SIDE (Form) --}}
        <div class="col-lg-5 d-flex align-items-center justify-content-center">
            <div class="w-100" style="max-width: 420px;">

                {{-- Logo --}}
                <div class="text-center mb-4">
                    <img src="{{ asset('assets/img/kaiadmin/favicon-1.png') }}" height="100" width="120" class="mb-3" />
                    <h4 class="fw-bold mb-1">Lupa Password?</h4>
                    <p class="text-muted mb-0">
                        Masukkan email untuk menerima link reset
                    </p>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">

                        @if (session('status'))
                        <div class="alert alert-success rounded-3">
                            {{ session('status') }}
                        </div>
                        @endif

                        <form method="POST" action="{{ route('password.email') }}">
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
                                    class="form-control rounded-3 @error('email') is-invalid @enderror"
                                    placeholder="you@company.com"
                                    required>

                                @error('email')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            {{-- Button --}}
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary rounded-3">
                                    Kirim Link Reset
                                </button>
                            </div>

                            <div class="text-center small">
                                <a href="{{ route('login') }}" class="text-decoration-none">
                                    Kembali ke Login
                                </a>
                            </div>

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