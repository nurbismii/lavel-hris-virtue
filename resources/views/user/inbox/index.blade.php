@extends('layouts.app')

@section('title', __('self_service.inbox.title'))

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
                            {{ __('self_service.inbox.title') }}
                        </h1>
                        <p class="inbox-hero__subtitle">
                            {{ __('self_service.inbox.subtitle') }}
                        </p>
                    </div>

                    <div class="inbox-hero__actions">
                        @if($unreadNotifications > 0)
                        <form action="{{ route('notif.readAll') }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="inbox-button inbox-button--ghost" data-loading-text="{{ __('self_service.inbox.marking') }}">
                                <i class="fas fa-check-double"></i>
                                {{ __('self_service.actions.read_all') }}
                            </button>
                        </form>
                        @endif

                        @if($readNotifications > 0)
                        <form
                            action="{{ route('notif.destroyRead') }}"
                            method="POST"
                            class="m-0"
                            data-confirm-title="{{ __('self_service.inbox.delete_read_confirm_title') }}"
                            data-confirm-text="{{ __('self_service.inbox.delete_read_confirm_text') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inbox-button inbox-button--ghost inbox-button--danger" data-loading-text="{{ __('self_service.inbox.deleting') }}">
                                <i class="fas fa-trash-alt"></i>
                                {{ __('self_service.actions.delete_read') }}
                            </button>
                        </form>
                        @endif

                        @if($totalNotifications > 0)
                        <form
                            action="{{ route('notif.destroyAll') }}"
                            method="POST"
                            class="m-0"
                            data-confirm-title="{{ __('self_service.inbox.delete_all_confirm_title') }}"
                            data-confirm-text="{{ __('self_service.inbox.delete_all_confirm_text') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inbox-button inbox-button--ghost inbox-button--danger" data-loading-text="{{ __('self_service.inbox.deleting') }}">
                                <i class="fas fa-trash"></i>
                                {{ __('self_service.actions.delete_all') }}
                            </button>
                        </form>
                        @endif

                        <a href="{{ $homeUrl }}" class="inbox-button inbox-button--light">
                            <i class="fas fa-arrow-left"></i>
                            {{ __('self_service.actions.back') }}
                        </a>
                    </div>
                </div>

                <div class="inbox-stats">
                    <div class="inbox-stat">
                        <small>{{ __('self_service.inbox.total_notifications') }}</small>
                        <strong>{{ $totalNotifications }}</strong>
                        <span>{{ __('self_service.inbox.total_notifications_help') }}</span>
                    </div>
                    <div class="inbox-stat">
                        <small>{{ __('self_service.inbox.unread') }}</small>
                        <strong>{{ $unreadNotifications }}</strong>
                        <span>{{ __('self_service.inbox.unread_help') }}</span>
                    </div>
                    <div class="inbox-stat">
                        <small>{{ __('self_service.inbox.read') }}</small>
                        <strong>{{ $readNotifications }}</strong>
                        <span>{{ __('self_service.inbox.read_help') }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="inbox-card">
            <div class="inbox-card__head">
                <div>
                    <h2 class="inbox-card__title">{{ __('self_service.inbox.list_title') }}</h2>
                </div>

                <span class="inbox-card__pill">
                    <i class="fas fa-list-ul"></i>

                    @if($notifications->total() > 0)
                    {{ $notifications->firstItem() }} - {{ $notifications->lastItem() }}
                    {{ __('self_service.inbox.from_total', ['total' => $notifications->total()]) }}
                    @else
                    0 {{ __('self_service.common.item') }}
                    @endif
                </span>
            </div>

            <div class="inbox-list">
                @forelse($notifications as $notif)
                @php
                $title = $notif->data['judul'] ?? __('self_service.inbox.notification_fallback');
                $message = $notif->data['pesan'] ?? __('self_service.inbox.message_fallback');
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
                                {{ __('self_service.inbox.new_badge') }}
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
                                {{ __('self_service.actions.open_detail') }}
                            </span>
                        </div>
                    </div>

                    <div class="inbox-item__actions">
                        <a
                            href="{{ route('notif.baca', $notif->id) }}"
                            class="inbox-icon-button"
                            title="{{ __('self_service.actions.open') }}"
                            aria-label="{{ __('self_service.inbox.open_notification', ['title' => $title]) }}"
                            data-loading-text="{{ __('self_service.inbox.opening') }}">
                            <i class="fas fa-chevron-right"></i>
                        </a>

                    </div>
                </article>
                @empty
                <div class="inbox-empty">
                    <div class="inbox-empty__icon">
                        <i class="fas fa-bell-slash"></i>
                    </div>
                    <h5>{{ __('self_service.inbox.empty_title') }}</h5>
                    <p>
                        {{ __('self_service.inbox.empty_text') }}
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
