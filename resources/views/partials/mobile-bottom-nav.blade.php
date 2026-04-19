@if(auth()->check())
    @php
        $mobileNavItems = [];

        if (auth()->user()->hasMenuAccess('dashboard_karyawan')) {
            $mobileNavItems[] = [
                'type' => 'link',
                'label' => 'Dashboard',
                'route' => route('dashboard.karyawan'),
                'icon' => 'fas fa-home',
                'active' => request()->routeIs('dashboard.karyawan'),
            ];
        }

        if (auth()->user()->hasMenuAccess('slip_gaji_user')) {
            $mobileNavItems[] = [
                'type' => 'link',
                'label' => 'Slip Gaji',
                'route' => route('slipgaji.index'),
                'icon' => 'fas fa-file-invoice-dollar',
                'active' => request()->routeIs('slipgaji.*'),
            ];
        }

        if (auth()->user()->hasMenuAccess('presensi')) {
            $mobileNavItems[] = [
                'type' => 'link',
                'label' => 'Presensi',
                'route' => route('presensi.index'),
                'icon' => 'fas fa-map-pin',
                'active' => request()->routeIs('presensi.*'),
                'featured' => true,
            ];
        }

        if (auth()->user()->hasMenuAccess('izin')) {
            $mobileNavItems[] = [
                'type' => 'menu',
                'label' => 'Izin',
                'icon' => 'fas fa-file-signature',
                'active' => request()->routeIs('izin.*'),
                'menu_id' => 'mobile-menu-izin',
                'popup_class' => 'mobile-bottom-nav__popup--end',
                'children' => [
                    [
                        'label' => 'Izin Berbayar',
                        'route' => route('izin.create', ['type' => 'PAID']),
                        'icon' => 'fas fa-wallet',
                    ],
                    [
                        'label' => 'Izin Tidak Berbayar',
                        'route' => route('izin.create', ['type' => 'UNPAID']),
                        'icon' => 'fas fa-file-medical',
                    ],
                ],
            ];
        }

        $mobileNavItems[] = [
            'type' => 'menu',
            'label' => 'Profile',
            'icon' => 'fas fa-user-circle',
            'active' => request()->routeIs('pengaturan-akun.*') || request()->routeIs('update.akun'),
            'menu_id' => 'mobile-menu-profile',
            'popup_class' => 'mobile-bottom-nav__popup--end',
            'children' => [
                [
                    'label' => 'Pengaturan Akun',
                    'route' => route('update.akun'),
                    'icon' => 'fas fa-cog',
                ],
                [
                    'label' => 'Keluar',
                    'action' => 'logout',
                    'icon' => 'fas fa-sign-out-alt',
                    'danger' => true,
                ],
            ],
        ];
    @endphp
    @if(count($mobileNavItems) > 0)
    <nav class="mobile-bottom-nav" aria-label="Mobile navigation">
        @foreach($mobileNavItems as $item)
            @if($item['type'] === 'menu')
                <div class="mobile-bottom-nav__group">
                    <button
                        type="button"
                        class="mobile-bottom-nav__item {{ $item['active'] ? 'is-active' : '' }}"
                        data-mobile-menu-toggle
                        data-mobile-menu-target="{{ $item['menu_id'] }}"
                        aria-expanded="false"
                        aria-controls="{{ $item['menu_id'] }}">
                        <i class="{{ $item['icon'] }}"></i>
                        <span class="mobile-bottom-nav__label">{{ $item['label'] }}</span>
                    </button>

                    <div
                        id="{{ $item['menu_id'] }}"
                        class="mobile-bottom-nav__popup {{ $item['popup_class'] ?? '' }}">
                        @foreach($item['children'] as $child)
                            @if(($child['action'] ?? null) === 'logout')
                                <button
                                    type="button"
                                    class="mobile-bottom-nav__popup-item {{ !empty($child['danger']) ? 'mobile-bottom-nav__popup-item--danger' : '' }}"
                                    data-mobile-logout>
                                    <i class="{{ $child['icon'] }}"></i>
                                    <span>{{ $child['label'] }}</span>
                                </button>
                            @else
                                <a
                                    href="{{ $child['route'] }}"
                                    class="mobile-bottom-nav__popup-item {{ request()->fullUrlIs($child['route']) ? 'is-active' : '' }}">
                                    <i class="{{ $child['icon'] }}"></i>
                                    <span>{{ $child['label'] }}</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @else
                <div class="mobile-bottom-nav__group {{ !empty($item['featured']) ? 'mobile-bottom-nav__group--featured' : '' }}">
                    <a href="{{ $item['route'] }}" class="mobile-bottom-nav__item {{ $item['active'] ? 'is-active' : '' }} {{ !empty($item['featured']) ? 'mobile-bottom-nav__item--featured' : '' }}">
                        <i class="{{ $item['icon'] }}"></i>
                        <span class="mobile-bottom-nav__label">{{ $item['label'] }}</span>
                    </a>
                </div>
            @endif
        @endforeach
    </nav>

    <form id="mobile-bottom-nav-logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
        @csrf
    </form>
    @endif
@endif

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileNav = document.querySelector('.mobile-bottom-nav');

            if (!mobileNav) {
                return;
            }

            const menuGroups = Array.from(mobileNav.querySelectorAll('.mobile-bottom-nav__group'));
            const toggles = Array.from(mobileNav.querySelectorAll('[data-mobile-menu-toggle]'));
            const logoutButton = mobileNav.querySelector('[data-mobile-logout]');
            const logoutForm = document.getElementById('mobile-bottom-nav-logout-form');

            const closeAllMenus = function() {
                menuGroups.forEach(function(group) {
                    group.classList.remove('is-open');

                    const popup = group.querySelector('.mobile-bottom-nav__popup');
                    const toggle = group.querySelector('[data-mobile-menu-toggle]');

                    if (popup) {
                        popup.classList.remove('is-open');
                    }

                    if (toggle) {
                        toggle.setAttribute('aria-expanded', 'false');
                    }
                });
            };

            toggles.forEach(function(toggle) {
                toggle.addEventListener('click', function(event) {
                    event.preventDefault();

                    const group = toggle.closest('.mobile-bottom-nav__group');
                    const popup = group ? group.querySelector('.mobile-bottom-nav__popup') : null;
                    const willOpen = popup && !popup.classList.contains('is-open');

                    closeAllMenus();

                    if (group && popup && willOpen) {
                        group.classList.add('is-open');
                        popup.classList.add('is-open');
                        toggle.setAttribute('aria-expanded', 'true');
                    }
                });
            });

            document.addEventListener('click', function(event) {
                if (!mobileNav.contains(event.target)) {
                    closeAllMenus();
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeAllMenus();
                }
            });

            if (logoutButton && logoutForm) {
                logoutButton.addEventListener('click', function() {
                    logoutForm.submit();
                });
            }
        });
    </script>
@endpush
