<nav
    class="navbar navbar-header navbar-header-transparent navbar-expand-lg border-bottom">
    <div class="container-fluid">

        <ul class="navbar-nav topbar-nav ms-md-auto align-items-center">

            <li class="nav-item topbar-icon dropdown hidden-caret">
                @php
                $user = auth()->user();
                $unreadCount = $user->unreadNotifications->count();
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
                    @if($unreadCount > 0)
                    <span class="notification"> {{ $unreadCount }} </span>
                    @endif
                </a>


                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 notif-box animated fadeIn"
                    aria-labelledby="notifDropdown"
                    style="width: 380px; border-radius: 12px;">

                    <!-- Header -->
                    <li class="px-3 py-3 border-bottom bg-light rounded-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold">Notifikasi</h6>
                            @if($unreadCount > 0)
                            <span class="badge bg-danger rounded-pill">
                                {{ $unreadCount }} New
                            </span>
                            @endif
                        </div>
                    </li>

                    <!-- Notification List -->
                    <li style="max-height: 350px; overflow-y: auto;">
                        @forelse($notifications as $notif)
                        <a href="{{ route('notif.baca', $notif->id) }}"
                            class="dropdown-item d-flex align-items-start py-3 border-bottom {{ is_null($notif->read_at) ? 'bg-light' : '' }}">

                            <div class="flex-grow-1">
                                <div class="fw-semibold small">
                                    {{ $notif->data['judul'] ?? 'Notifikasi' }}
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
                        <div class="text-center text-muted py-4">
                            Tidak ada notifikasi
                        </div>
                        @endforelse
                    </li>

                    <!-- Footer -->
                    <li class="border-top bg-white rounded-bottom">
                        <div class="d-flex justify-content-between align-items-center px-3 py-2">

                            @if($unreadCount > 0)
                            <form action="{{ route('notif.readAll') }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-light">
                                    Baca semua
                                </button>
                            </form>
                            @endif

                            <a href="{{ route('kotak-masuk.index') }}"
                                class="text-primary fw-semibold small">
                                Lihat semua →
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
                        <img
                            src="{{ asset('/assets/img/profile.jpg') }}"
                            alt="..."
                            class="avatar-img rounded-circle" />
                    </div>
                    <span class="profile-username">
                        <span class="op-7">Hi,</span>
                        <span class="fw-bold">{{ Auth::user()->employee->nama_karyawan }}</span>
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-user animated fadeIn">
                    <div class="dropdown-user-scroll scrollbar-outer">
                        <li>
                            <div class="user-box">
                                <div class="avatar-lg">
                                    <img
                                        src="{{ asset('/assets/img/profile.jpg') }}"
                                        alt="image profile"
                                        class="avatar-img rounded" />
                                </div>
                                <div class="u-text">
                                    <h4>{{ Auth::user()->employee->nama_karyawan }}</h4>
                                    <p class="text-muted">{{ Auth::user()->email }}</p>
                                    <a href="{{ route('pengaturan-akun.index') }}" class="btn btn-xs btn-secondary btn-sm">Profil Saya</a>
                                </div>
                            </div>
                        </li>
                        <li>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="{{ route('kotak-masuk.index') }}">Kotak Masuk</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="{{ route('update.akun') }}">Pengaturan Akun</a>
                            <div class="dropdown-divider"></div>

                            <a class="dropdown-item" href="{{ route('logout') }}"
                                onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                Keluar
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