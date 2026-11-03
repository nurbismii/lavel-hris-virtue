@php
    $user = auth()->user();
    $homeRoute = $user->preferredHomeRouteName();
    $tabs = [];
    if ($homeRoute !== 'update.akun') {
        $tabs[] = [$homeRoute, 'navigation.dashboard', 'fa-home', request()->routeIs($homeRoute)];
    }
    if ($user->hasMenuAccess('presensi')) {
        $tabs[] = ['presensi.index', 'navigation.attendance', 'fa-map-marker-alt', request()->routeIs('presensi.*')];
    }
    if ($user->hasMenuAccess('slip_gaji_user')) {
        $tabs[] = ['slipgaji.index', 'navigation.salary_slip', 'fa-file-invoice-dollar', request()->routeIs('slipgaji.*')];
    }
    $tabs[] = ['mobile.services', 'navigation.services', 'fa-th-large', request()->routeIs('mobile.services')];
    $tabs[] = ['pengaturan-akun.index', 'navigation.profile', 'fa-user-circle', request()->routeIs('pengaturan-akun.*', 'update.akun')];
@endphp
<nav class="native-tabs" aria-label="{{ __('navigation.mobile_navigation') }}">
    @foreach($tabs as [$route, $label, $icon, $active])
        <a href="{{ route($route) }}" @if($active) aria-current="page" @endif>
            <i class="fas {{ $icon }}" aria-hidden="true"></i>
            <span>{{ __($label) }}</span>
        </a>
    @endforeach
</nav>
