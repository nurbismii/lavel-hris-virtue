@if(auth()->check())
    @php
        $mobileNavItems = [];
        $canManageOvertimeOrders = auth()->user()->hasRole(['Super Admin', 'HR', 'HOD', 'Admin Divisi']);

        if (auth()->user()->hasMenuAccess('dashboard_karyawan')) {
            $mobileNavItems[] = [
                'type' => 'link',
                'label' => __('navigation.dashboard'),
                'route' => route('dashboard.karyawan'),
                'icon' => 'fas fa-home',
                'active' => request()->routeIs('dashboard.karyawan'),
            ];
        }

        if (auth()->user()->hasMenuAccess('slip_gaji_user')) {
            $mobileNavItems[] = [
                'type' => 'link',
                'label' => __('navigation.salary_slip'),
                'route' => route('slipgaji.index'),
                'icon' => 'fas fa-file-invoice-dollar',
                'active' => request()->routeIs('slipgaji.*'),
            ];
        }

        if (auth()->user()->hasMenuAccess('presensi')) {
            $mobileNavItems[] = [
                'type' => 'link',
                'label' => __('navigation.attendance'),
                'route' => route('presensi.index'),
                'icon' => 'fas fa-map-pin',
                'active' => request()->routeIs('presensi.*'),
                'featured' => true,
            ];
        }

        if (auth()->user()->hasMenuAccess('izin')) {
            $mobileNavItems[] = [
                'type' => 'menu',
                'label' => __('navigation.permission'),
                'icon' => 'fas fa-file-signature',
                'active' => request()->routeIs('izin.*'),
                'menu_id' => 'mobile-menu-izin',
                'popup_class' => 'mobile-bottom-nav__popup--end',
                'children' => [
                    [
                        'label' => __('navigation.paid_permission'),
                        'route' => route('izin.create', ['type' => 'PAID']),
                        'icon' => 'fas fa-wallet',
                    ],
                    [
                        'label' => __('navigation.unpaid_permission'),
                        'route' => route('izin.create', ['type' => 'UNPAID']),
                        'icon' => 'fas fa-file-medical',
                    ],
                ],
            ];
        }

        $mobileNavItems[] = [
            'type' => 'menu',
            'label' => __('navigation.profile'),
            'icon' => 'fas fa-user-circle',
            'active' => request()->routeIs('pengaturan-akun.*') || request()->routeIs('update.akun'),
            'menu_id' => 'mobile-menu-profile',
            'popup_class' => 'mobile-bottom-nav__popup--end',
            'children' => [
                [
                    'label' => __('navigation.account_settings'),
                    'route' => route('update.akun'),
                    'icon' => 'fas fa-cog',
                ],
                [
                    'label' => __('navigation.logout'),
                    'action' => 'logout',
                    'icon' => 'fas fa-sign-out-alt',
                    'danger' => true,
                ],
            ],
        ];
    @endphp
    @if(count($mobileNavItems) > 0)
    <nav class="mobile-bottom-nav" aria-label="{{ __('navigation.mobile_navigation') }}">
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
    <script src="{{ versioned_asset('assets/js/mobile-bottom-nav.js') }}"></script>
@endpush
