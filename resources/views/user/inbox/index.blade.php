@extends('layouts.app')

@section('title', 'Kotak Masuk')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/user-inbox.css') }}">
@endpush

@section('content')
@php
$currentUser = auth()->user();
$homeUrl = $currentUser ? route($currentUser->preferredHomeRouteName()) : url('/');
$totalNotifications = $currentUser ? $currentUser->notifications()->count() : 0;
$unreadNotifications = $currentUser ? $currentUser->unreadNotifications()->count() : 0;
$readNotifications = max($totalNotifications - $unreadNotifications, 0);
@endphp

<div class="container-fluid inbox-page px-3">
    <div class="page-inner inbox-shell">
        <section class="inbox-hero">
            <div class="inbox-hero__body">
                <div class="inbox-hero__top">
                    <div>
                        <h1 class="inbox-hero__title">
                            <i class="fas fa-inbox"></i>
                            Kotak Masuk
                        </h1>
                        <p class="inbox-hero__subtitle">
                            Semua update terkait pengajuan, approval, dan informasi penting dari perusahaan akan muncul di sini.
                        </p>
                    </div>

                    <div class="inbox-hero__actions">
                        @if($unreadNotifications > 0)
                        <form action="{{ route('notif.readAll') }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="inbox-button inbox-button--ghost" data-loading-text="Menandai...">
                                <i class="fas fa-check-double"></i>
                                Baca semua
                            </button>
                        </form>
                        @endif

                        @if($readNotifications > 0)
                        <form
                            action="{{ route('notif.destroyRead') }}"
                            method="POST"
                            class="m-0"
                            data-confirm-title="Hapus notifikasi dibaca?"
                            data-confirm-text="Semua notifikasi yang sudah dibaca pada akun Anda akan dihapus.">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inbox-button inbox-button--ghost inbox-button--danger" data-loading-text="Menghapus...">
                                <i class="fas fa-trash-alt"></i>
                                Hapus dibaca
                            </button>
                        </form>
                        @endif

                        @if($totalNotifications > 0)
                        <form
                            action="{{ route('notif.destroyAll') }}"
                            method="POST"
                            class="m-0"
                            data-confirm-title="Hapus semua notifikasi?"
                            data-confirm-text="Semua notifikasi pada akun Anda akan dihapus permanen, termasuk yang belum dibaca.">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inbox-button inbox-button--ghost inbox-button--danger" data-loading-text="Menghapus...">
                                <i class="fas fa-trash"></i>
                                Hapus semua
                            </button>
                        </form>
                        @endif

                        <a href="{{ $homeUrl }}" class="inbox-button inbox-button--light">
                            <i class="fas fa-arrow-left"></i>
                            Kembali
                        </a>
                    </div>
                </div>

                <div class="inbox-stats">
                    <div class="inbox-stat">
                        <small>Total Notifikasi</small>
                        <strong>{{ $totalNotifications }}</strong>
                        <span>Seluruh pesan yang tercatat pada akun Anda.</span>
                    </div>
                    <div class="inbox-stat">
                        <small>Belum Dibaca</small>
                        <strong>{{ $unreadNotifications }}</strong>
                        <span>Perlu Anda cek agar tidak ada informasi yang terlewat.</span>
                    </div>
                    <div class="inbox-stat">
                        <small>Sudah Dibaca</small>
                        <strong>{{ $readNotifications }}</strong>
                        <span>Update yang sudah Anda buka sebelumnya.</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="inbox-card">
            <div class="inbox-card__head">
                <div>
                    <h2 class="inbox-card__title">Daftar Notifikasi</h2>
                </div>

                <span class="inbox-card__pill">
                    <i class="fas fa-list-ul"></i>

                    @if($notifications->total() > 0)
                    {{ $notifications->firstItem() }} - {{ $notifications->lastItem() }}
                    dari {{ $notifications->total() }} item
                    @else
                    0 item
                    @endif
                </span>
            </div>

            <div class="inbox-list">
                @forelse($notifications as $notif)
                @php
                $title = $notif->data['judul'] ?? 'Notifikasi';
                $message = $notif->data['pesan'] ?? 'Belum ada detail pesan.';
                $isUnread = is_null($notif->read_at);
                @endphp

                <article class="inbox-item {{ $isUnread ? 'is-unread' : '' }}">
                    <div class="inbox-item__icon">
                        <i class="fas fa-bell"></i>
                    </div>

                    <div class="inbox-item__body">
                        <div class="inbox-item__head">
                            <h3 class="inbox-item__title">{{ $title }}</h3>

                            @if($isUnread)
                            <span class="inbox-item__badge">
                                <i class="fas fa-circle"></i>
                                Baru
                            </span>
                            @endif
                        </div>

                        <p class="inbox-item__message">{{ $message }}</p>

                        <div class="inbox-item__footer">
                            <span class="inbox-item__time">
                                <i class="far fa-clock"></i>
                                {{ $notif->created_at->diffForHumans() }}
                            </span>
                            <span class="inbox-item__hint">
                                <i class="fas fa-arrow-up-right-from-square"></i>
                                Buka detail
                            </span>
                        </div>
                    </div>

                    <div class="inbox-item__actions">
                        <a
                            href="{{ route('notif.baca', $notif->id) }}"
                            class="inbox-icon-button"
                            title="Buka notifikasi"
                            aria-label="Buka notifikasi {{ $title }}"
                            data-loading-text="Membuka...">
                            <i class="fas fa-chevron-right"></i>
                        </a>

                        <form
                            action="{{ route('notif.destroy', $notif->id) }}"
                            method="POST"
                            class="m-0"
                            data-confirm-title="Hapus notifikasi?"
                            data-confirm-text="Notifikasi ini akan dihapus dari akun Anda.">
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="inbox-icon-button inbox-icon-button--danger"
                                title="Hapus notifikasi"
                                aria-label="Hapus notifikasi {{ $title }}"
                                data-loading-text="Menghapus...">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    </div>
                </article>
                @empty
                <div class="inbox-empty">
                    <div class="inbox-empty__icon">
                        <i class="fas fa-bell-slash"></i>
                    </div>
                    <h5>Belum ada notifikasi</h5>
                    <p>
                        Saat ada update baru dari approval, pengajuan, atau informasi perusahaan,
                        notifikasinya akan muncul di halaman ini.
                    </p>
                </div>
                @endforelse
            </div>

            @if($notifications->hasPages())
            <div class="inbox-pagination">
                {{ $notifications->onEachSide(1)->links('pagination::bootstrap-4') }}
            </div>
            @endif
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!form || !form.matches('form[data-confirm-title]') || form.dataset.confirmed === '1') {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const submitConfirmed = function () {
            form.dataset.confirmed = '1';
            form.submit();
        };

        if (window.AppDialog && typeof window.AppDialog.confirm === 'function') {
            window.AppDialog.confirm({
                title: form.dataset.confirmTitle || 'Hapus notifikasi?',
                text: form.dataset.confirmText || 'Notifikasi akan dihapus dari akun Anda.',
                icon: 'warning',
                confirmButtonText: 'Ya, hapus',
                cancelButtonText: 'Batal'
            }).then(function (confirmed) {
                if (confirmed) {
                    submitConfirmed();
                }
            });

            return;
        }

        if (window.confirm(form.dataset.confirmText || 'Hapus notifikasi ini?')) {
            submitConfirmed();
        }
    }, true);
</script>
@endpush
