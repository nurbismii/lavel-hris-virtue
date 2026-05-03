<nav
    class="navbar navbar-header navbar-header-transparent navbar-expand-lg border-bottom">
    <div class="container-fluid">

        <ul class="navbar-nav topbar-nav ms-md-auto align-items-center">

            <li class="nav-item me-2">
                @include('partials.language-switcher')
            </li>

            <li class="nav-item topbar-icon dropdown hidden-caret">
                @php
                $user = auth()->user();
                $employee = optional($user)->employee;
                $employeeName = $employee->nama_karyawan ?? optional($user)->name ?? __('common.user');
                $employeePhotoUrl = $employee->document_photo_url;
                $employeeInitials = $user->avatar_initials;
                $unreadCount = $user->unreadNotifications()->count();
                $notifications = $user->notifications()->latest()->limit(5)->get();
                @endphp

                <a
                    class="nav-link dropdown-toggle"
                    href="#"
                    id="notifDropdown"
                    role="button"
                    data-bs-toggle="dropdown"
                    aria-haspopup="true"
                    aria-expanded="false">
                    <i class="fa fa-bell"></i>
                    <span
                        id="notifBadge"
                        class="notification {{ $unreadCount > 0 ? '' : 'd-none' }}"
                        data-notification-badge>
                        {{ $unreadCount }}
                    </span>
                </a>


                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 notif-box animated fadeIn"
                    aria-labelledby="notifDropdown"
                    style="width: 380px; border-radius: 12px;">

                    <!-- Header -->
                    <li class="px-3 py-3 border-bottom bg-light rounded-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold">{{ __('notifications.title') }}</h6>
                            <span
                                id="notifHeaderBadge"
                                class="badge bg-danger rounded-pill {{ $unreadCount > 0 ? '' : 'd-none' }}">
                                {{ $unreadCount }} {{ __('common.new') }}
                            </span>
                        </div>
                    </li>

                    <li id="desktopNotifPermissionPanel" class="px-3 py-2 border-bottom d-none">
                        <button
                            type="button"
                            id="desktopNotifPermissionButton"
                            class="btn btn-sm btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2">
                            <i class="fa fa-desktop"></i>
                            <span id="desktopNotifPermissionText">Aktifkan Notifikasi Desktop</span>
                        </button>
                        <div id="desktopNotifPermissionHint" class="small text-muted mt-2 d-none"></div>
                    </li>

                    <!-- Notification List -->
                    <li id="notifList" class="realtime-notif-list" style="max-height: 350px; overflow-y: auto;">
                        @forelse($notifications as $notif)
                        <a href="{{ route('notif.baca', $notif->id) }}"
                            class="dropdown-item d-flex align-items-start py-3 border-bottom realtime-notif-item {{ is_null($notif->read_at) ? 'bg-light' : '' }}">

                            <div class="flex-grow-1">
                                <div class="fw-semibold small">
                                    {{ $notif->data['judul'] ?? __('notifications.title') }}
                                </div>

                                <div class="text-muted small">
                                    {{ $notif->data['pesan'] ?? '-' }}
                                </div>

                                <div class="text-secondary small mt-1">
                                    {{ $notif->created_at->diffForHumans() }}
                                </div>
                            </div>

                            @if(is_null($notif->read_at))
                            <span class="badge bg-primary ms-2"
                                style="width:8px;height:8px;border-radius:50%;padding:0;">
                            </span>
                            @endif

                        </a>
                        @empty
                        <div class="text-center text-muted py-4 realtime-notif-empty">
                            {{ __('notifications.empty') }}
                        </div>
                        @endforelse
                    </li>

                    <!-- Footer -->
                    <li class="border-top bg-white rounded-bottom">
                        <div class="d-flex justify-content-between align-items-center px-3 py-2">

                            <div id="notifReadAllContainer" class="{{ $unreadCount > 0 ? '' : 'd-none' }}">
                            <form action="{{ route('notif.readAll') }}" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-light">
                                    {{ __('notifications.mark_all_read') }}
                                </button>
                            </form>
                            </div>

                            <a href="{{ route('kotak-masuk.index') }}"
                                class="text-primary fw-semibold small">
                                {{ __('notifications.view_all') }} &rarr;
                            </a>
                        </div>
                    </li>

                </ul>
            </li>
            <li class="nav-item topbar-user dropdown hidden-caret">
                <a
                    class="dropdown-toggle profile-pic"
                    data-bs-toggle="dropdown"
                    href="#"
                    aria-expanded="false">
                    <div class="avatar-sm">
                        @if($employeePhotoUrl)
                            <img
                                src="{{ $employeePhotoUrl }}"
                                alt="{{ $employeeName }}"
                                class="avatar-img rounded-circle" />
                        @else
                            <span class="avatar-title rounded-circle bg-primary">{{ $employeeInitials }}</span>
                        @endif
                    </div>
                    <span class="profile-username">
                        <span class="op-7">{{ __('common.hi') }},</span>
                        <span class="fw-bold">{{ $employeeName }}</span>
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-user animated fadeIn">
                    <div class="dropdown-user-scroll scrollbar-outer">
                        <li>
                            <div class="user-box">
                                <div class="avatar-lg">
                                    @if($employeePhotoUrl)
                                        <img
                                            src="{{ $employeePhotoUrl }}"
                                            alt="{{ $employeeName }}"
                                            class="avatar-img rounded-circle" />
                                    @else
                                        <span class="avatar-title rounded-circle bg-primary">{{ $employeeInitials }}</span>
                                    @endif
                                </div>
                                <div class="u-text">
                                    <h4>{{ $employeeName }}</h4>
                                    <p class="text-muted">{{ $user->email }}</p>
                                    <a href="{{ route('pengaturan-akun.index') }}" class="btn btn-xs btn-secondary btn-sm">{{ __('navigation.my_profile') }}</a>
                                </div>
                            </div>
                        </li>
                        <li>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="{{ route('kotak-masuk.index') }}">{{ __('navigation.inbox') }}</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="{{ route('update.akun') }}">{{ __('navigation.account_settings') }}</a>
                            <div class="dropdown-divider"></div>

                            <a class="dropdown-item" href="{{ route('logout') }}"
                                onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                {{ __('navigation.logout') }}
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                @csrf
                            </form>
                        </li>
                    </div>
                </ul>
            </li>
        </ul>
    </div>
</nav>
