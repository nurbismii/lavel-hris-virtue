@extends('layouts.app')

@section('title', 'Kotak Masuk')

@section('content')
<div class="container">
    <div class="page-inner">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-inbox text-primary me-2"></i>
                    Kotak Masuk
                </h4>
                <small class="text-muted">
                    Informasi terkait pengajuan atau pemberitahuan dari perusahaan akan masuk kesini
                </small>
            </div>

            <a href="{{ route('izin.create') }}" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Dashboard
            </a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">
                Kotak Masuk
            </div>

            <div class="card-body">

                @forelse($notifications as $notif)
                <div class="border-bottom py-3">

                    <div class="d-flex justify-content-between">
                        <div>
                            <strong>{{ $notif->data['judul'] }}</strong>
                            <p class="mb-1 text-muted">
                                {{ $notif->data['pesan'] }}
                            </p>
                            <small class="text-secondary">
                                {{ $notif->created_at->diffForHumans() }}
                            </small>
                        </div>

                        <div>
                            @if(is_null($notif->read_at))
                            <span class="badge bg-danger">Baru</span>
                            @endif
                        </div>
                    </div>

                    <a href="{{ $notif->data['url'] }}"
                        class="stretched-link"
                        onclick="event.preventDefault(); 
                                document.getElementById('mark-{{ $notif->id }}').submit();">
                    </a>

                    <form id="mark-{{ $notif->id }}"
                        action="{{ route('notif.baca', $notif->id) }}"
                        method="POST">
                        @csrf
                    </form>

                </div>

                @empty
                <p class="text-muted text-center">Belum ada notifikasi.</p>
                @endforelse

                <div class="mt-3">
                    {{ $notifications->links() }}
                </div>

            </div>
        </div>
    </div>
</div>
@endsection