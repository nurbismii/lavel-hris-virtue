@extends('layouts.app')

@section('title', 'Kotak Masuk')

@push('styles')
<style>
    .inbox-page {
        --inbox-ink: #0f172a;
        --inbox-muted: #64748b;
        --inbox-line: #e2e8f0;
        --inbox-soft: #f8fafc;
        --inbox-card: #ffffff;
        --inbox-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
    }

    .inbox-page .inbox-shell {
        max-width: 1100px;
        margin: 0 auto;
    }

    .inbox-page .inbox-hero,
    .inbox-page .inbox-card {
        border: 0;
        border-radius: 26px;
        overflow: hidden;
        box-shadow: var(--inbox-shadow);
    }

    .inbox-page .inbox-hero {
        position: relative;
        margin-bottom: 1rem;
        background:
            radial-gradient(circle at top right, rgba(255, 255, 255, 0.2), transparent 34%),
            linear-gradient(135deg, #0f3d68 0%, #15616d 55%, #1f7a4d 100%);
        color: #fff;
    }

    .inbox-page .inbox-hero::before {
        content: "";
        position: absolute;
        width: 180px;
        height: 180px;
        top: -90px;
        right: -48px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.08);
        pointer-events: none;
    }

    .inbox-page .inbox-hero__body {
        position: relative;
        z-index: 1;
        padding: 1.25rem;
        display: grid;
        gap: 1rem;
    }

    .inbox-page .inbox-hero__top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .inbox-page .inbox-hero__title {
        margin: 0;
        font-size: clamp(1.5rem, 4vw, 2rem);
        font-weight: 700;
        line-height: 1.1;
    }

    .inbox-page .inbox-hero__title i {
        margin-right: 0.65rem;
    }

    .inbox-page .inbox-hero__subtitle {
        margin: 0.45rem 0 0;
        max-width: 44rem;
        color: rgba(255, 255, 255, 0.82);
        font-size: 0.93rem;
        line-height: 1.6;
    }

    .inbox-page .inbox-hero__actions {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        flex-wrap: wrap;
    }

    .inbox-page .inbox-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        min-height: 44px;
        padding: 0.78rem 1rem;
        border: 0;
        border-radius: 16px;
        font-weight: 600;
        text-decoration: none;
        transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }

    .inbox-page .inbox-button:hover,
    .inbox-page .inbox-button:focus {
        text-decoration: none;
        transform: translateY(-1px);
    }

    .inbox-page .inbox-button--light {
        background: #fff;
        color: var(--inbox-ink);
        box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12);
    }

    .inbox-page .inbox-button--ghost {
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.16);
    }

    .inbox-page .inbox-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.8rem;
    }

    .inbox-page .inbox-stat {
        padding: 0.95rem 1rem;
        border-radius: 20px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.14);
        backdrop-filter: blur(10px);
    }

    .inbox-page .inbox-stat small {
        display: block;
        margin-bottom: 0.35rem;
        color: rgba(255, 255, 255, 0.75);
        font-size: 0.78rem;
    }

    .inbox-page .inbox-stat strong {
        display: block;
        color: #fff;
        font-size: 1.15rem;
        line-height: 1.2;
        font-weight: 700;
    }

    .inbox-page .inbox-stat span {
        display: block;
        margin-top: 0.22rem;
        color: rgba(255, 255, 255, 0.72);
        font-size: 0.78rem;
        line-height: 1.5;
    }

    .inbox-page .inbox-card {
        background: var(--inbox-card);
    }

    .inbox-page .inbox-card__head {
        padding: 1.2rem 1.25rem 0;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .inbox-page .inbox-card__title {
        margin: 0;
        color: var(--inbox-ink);
        font-size: 1.02rem;
        font-weight: 700;
    }

    .inbox-page .inbox-card__subtitle {
        margin: 0.28rem 0 0;
        color: var(--inbox-muted);
        font-size: 0.88rem;
        line-height: 1.6;
    }

    .inbox-page .inbox-card__pill {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.45rem 0.78rem;
        border-radius: 999px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 0.78rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .inbox-page .inbox-list {
        padding: 1rem 1.1rem 1.2rem;
        display: grid;
        gap: 0.85rem;
    }

    .inbox-page .inbox-item {
        position: relative;
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: 0.9rem;
        align-items: flex-start;
        padding: 1rem;
        border-radius: 22px;
        border: 1px solid var(--inbox-line);
        background: #fff;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background 0.2s ease;
    }

    .inbox-page .inbox-item:hover,
    .inbox-page .inbox-item:focus-within {
        transform: translateY(-1px);
        border-color: rgba(37, 99, 235, 0.18);
        box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
        background: #fcfdff;
    }

    .inbox-page .inbox-item.is-unread {
        border-color: rgba(37, 99, 235, 0.14);
        background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
    }

    .inbox-page .inbox-item__icon {
        width: 46px;
        height: 46px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #dbeafe 0%, #eff6ff 100%);
        color: #2563eb;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .inbox-page .inbox-item__body {
        min-width: 0;
    }

    .inbox-page .inbox-item__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.7rem;
        margin-bottom: 0.3rem;
        flex-wrap: wrap;
    }

    .inbox-page .inbox-item__title {
        margin: 0;
        color: var(--inbox-ink);
        font-size: 0.96rem;
        font-weight: 700;
        line-height: 1.45;
    }

    .inbox-page .inbox-item__badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.33rem 0.68rem;
        border-radius: 999px;
        background: rgba(239, 68, 68, 0.12);
        color: #b91c1c;
        font-size: 0.72rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .inbox-page .inbox-item__message {
        margin: 0;
        color: var(--inbox-muted);
        font-size: 0.84rem;
        line-height: 1.7;
    }

    .inbox-page .inbox-item__footer {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-top: 0.7rem;
        flex-wrap: wrap;
    }

    .inbox-page .inbox-item__time,
    .inbox-page .inbox-item__hint {
        display: inline-flex;
        align-items: center;
        gap: 0.38rem;
        color: #94a3b8;
        font-size: 0.76rem;
        font-weight: 600;
    }

    .inbox-page .inbox-item__chevron {
        color: #cbd5e1;
        font-size: 0.95rem;
        padding-top: 0.2rem;
    }

    .inbox-page .inbox-empty {
        padding: 2rem 1.4rem;
        text-align: center;
        border-radius: 22px;
        border: 1px dashed #dbe4f0;
        background: linear-gradient(180deg, #fcfdff 0%, #f8fafc 100%);
    }

    .inbox-page .inbox-empty__icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 1rem;
        border-radius: 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #eff6ff;
        color: #2563eb;
        font-size: 1.3rem;
    }

    .inbox-page .inbox-empty h5 {
        margin: 0 0 0.45rem;
        color: var(--inbox-ink);
        font-size: 1rem;
        font-weight: 700;
    }

    .inbox-page .inbox-empty p {
        margin: 0;
        color: var(--inbox-muted);
        font-size: 0.88rem;
        line-height: 1.65;
    }

    .inbox-page .inbox-pagination {
        padding: 0 1.1rem 1.2rem;
    }

    .inbox-page .inbox-pagination .pagination {
        margin-bottom: 0;
        justify-content: center;
        flex-wrap: wrap;
        gap: 0.4rem;
    }

    .inbox-page .inbox-pagination .page-link {
        border: 1px solid #dbe4f0;
        border-radius: 12px !important;
        color: var(--inbox-ink);
        min-width: 40px;
        min-height: 40px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: none;
    }

    .inbox-page .inbox-pagination .page-item.active .page-link {
        background: #0d6efd;
        border-color: #0d6efd;
        color: #fff;
    }

    .inbox-page .inbox-pagination .page-item.disabled .page-link {
        color: #94a3b8;
        background: #f8fafc;
        border-color: #e2e8f0;
    }

    @media (min-width: 768px) {
        .inbox-page .inbox-hero__body {
            padding: 1.6rem;
        }
    }

    @media (max-width: 767.98px) {
        .inbox-page .inbox-stats {
            grid-template-columns: 1fr;
        }

        .inbox-page .inbox-hero__actions {
            width: 100%;
        }

        .inbox-page .inbox-button {
            flex: 1 1 calc(50% - 0.35rem);
        }

        .inbox-page .inbox-item {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .inbox-page .inbox-item__chevron {
            display: none;
        }
    }

    @media (max-width: 575.98px) {
        .inbox-page {
            padding-left: 0.2rem;
            padding-right: 0.2rem;
        }

        .inbox-page .page-inner {
            padding-top: 12px;
        }

        .inbox-page .inbox-hero,
        .inbox-page .inbox-card {
            border-radius: 22px;
        }

        .inbox-page .inbox-hero__body,
        .inbox-page .inbox-list,
        .inbox-page .inbox-pagination {
            padding-left: 0.95rem;
            padding-right: 0.95rem;
        }

        .inbox-page .inbox-card__head {
            padding: 1rem 0.95rem 0;
        }

        .inbox-page .inbox-button {
            width: 100%;
        }

        .inbox-page .inbox-item {
            gap: 0.8rem;
            padding: 0.92rem;
            border-radius: 18px;
        }

        .inbox-page .inbox-item__icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
        }
    }
</style>
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
                                <button type="submit" class="inbox-button inbox-button--ghost">
                                    <i class="fas fa-check-double"></i>
                                    Baca semua
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
                    <p class="inbox-card__subtitle">
                        Ketuk salah satu kartu untuk membuka detail atau langsung menuju halaman terkait.
                    </p>
                </div>

                <span class="inbox-card__pill">
                    <i class="fas fa-list-ul"></i>
                    {{ $notifications->count() }} item di halaman ini
                </span>
            </div>

            <div class="inbox-list">
                @forelse($notifications as $notif)
                    @php
                        $title = $notif->data['judul'] ?? 'Notifikasi';
                        $message = $notif->data['pesan'] ?? 'Belum ada detail pesan.';
                        $targetUrl = $notif->data['url'] ?? route('kotak-masuk.index');
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

                        <div class="inbox-item__chevron">
                            <i class="fas fa-chevron-right"></i>
                        </div>

                        <a
                            href="{{ $targetUrl }}"
                            class="stretched-link"
                            aria-label="Buka notifikasi {{ $title }}"
                            onclick="event.preventDefault(); document.getElementById('mark-{{ $notif->id }}').submit();">
                        </a>

                        <form id="mark-{{ $notif->id }}" action="{{ route('notif.baca', $notif->id) }}" method="GET" class="d-none"></form>
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
                    {{ $notifications->links() }}
                </div>
            @endif
        </section>
    </div>
</div>
@endsection
