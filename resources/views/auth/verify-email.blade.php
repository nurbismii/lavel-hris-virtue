@extends('layouts.app-auth')

@section('content')
<div class="container d-flex align-items-center justify-content-center min-vh-100">
    <div class="card border-0 shadow-sm rounded-4" style="max-width: 500px;">
        <div class="card-body p-5 text-center">

            <h4 class="fw-bold mb-3">Verifikasi Email Anda</h4>

            <p class="text-muted mb-4">
                Kami telah mengirimkan link verifikasi ke email Anda.
                Silakan cek inbox atau folder spam.
            </p>

            @if (session('message'))
            <div class="alert alert-success">
                {{ session('message') }}
            </div>
            @endif

            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="btn btn-primary rounded-3">
                    Kirim Ulang Email Verifikasi
                </button>
            </form>

            <div class="mt-3">
                <a href="{{ route('logout') }}"
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                    class="small text-muted">
                    Logout
                </a>
            </div>

            <form id="logout-form" method="POST" action="{{ route('logout') }}">
                @csrf
            </form>

        </div>
    </div>
</div>
@endsection