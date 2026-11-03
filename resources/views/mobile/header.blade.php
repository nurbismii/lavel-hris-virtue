<header class="native-header">
    <div class="native-brand">
        <img src="{{ versioned_asset('assets/img/kaiadmin/icon-2.png') }}" alt="" width="32" height="32">
        <span>V-People</span>
    </div>
    <div class="native-header-actions">
        @include('partials.language-switcher', ['id' => 'nativeLocaleToggle'])
        <div class="dropdown native-notifications" data-native-notifications>
        <button type="button" class="native-inbox" id="notifDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
            aria-label="{{ __('notifications.title') }}" aria-expanded="false" aria-controls="nativeNotificationMenu">
            <i class="far fa-bell" aria-hidden="true"></i>
            @php
                $unread = auth()->user()->unreadNotifications()->count();
            @endphp
            <span id="notifBadge" class="native-badge{{ $unread ? '' : ' d-none' }}" data-notification-badge>{{ $unread }}</span>
        </button>
        <section class="dropdown-menu dropdown-menu-end native-notification-menu" id="nativeNotificationMenu" aria-labelledby="notifDropdown">
            <div class="native-notification-heading">
                <h2>{{ __('notifications.title') }}</h2>
                <button type="button" class="native-notification-button native-notification-refresh" data-notification-refresh>{{ __('notifications.refresh') }}</button>
            </div>
            <div id="notifList" class="native-notification-list" aria-live="polite" aria-busy="true">
                <div class="text-center text-muted py-4">{{ __('notifications.loading') }}</div>
            </div>
            <p class="native-notification-status" data-notification-status role="status" hidden></p>
            <div class="native-notification-actions">
                <button type="button" class="native-notification-button native-notification-clear" data-notification-clear
                    data-url="{{ route('notif.destroyAll') }}" data-confirm-title="{{ __('self_service.inbox.delete_all_confirm_title') }}"
                    data-confirm-text="{{ __('self_service.inbox.delete_all_confirm_text') }}"
                    data-loading-text="{{ __('self_service.inbox.deleting') }}" disabled>{{ __('notifications.clear') }}</button>
                <a href="{{ route('kotak-masuk.index') }}">{{ __('notifications.view_all') }} &rarr;</a>
            </div>
        </section>
        </div>
    </div>
</header>
