@extends('layouts.app')

@section('title', __('navigation.dashboard_employee'))

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/user-dashboard.css') }}">
@endpush

@section('content')
@php
$currentUser = $user;
$employee = $currentUser->employee;
$employeePhotoUrl = optional($employee)->document_photo_url;
$normalizedRole = $currentUser->normalized_role_name;
$roleConfig = config('access.roles.' . $normalizedRole, []);
$displayName = $employee->nama_karyawan ?? $currentUser->name ?? 'Pengguna';
$displayInitials = $currentUser->avatar_initials;
$departmentName = optional(optional(optional($employee)->divisi)->departemen)->departemen
?? optional(optional($employee)->departemen)->departemen
?? 'Belum diatur';
$divisionName = optional(optional($employee)->divisi)->nama_divisi ?? 'Belum diatur';
$positionName = $employee->posisi ?? 'Posisi belum diatur';
$scopeLabel = $roleConfig['scope_label'] ?? 'Akses mengikuti pengaturan role';
$roleDescription = $roleConfig['description'] ?? 'Hak akses mengikuti pengaturan yang diberikan ke akun Anda.';
$statusText = ucfirst($currentUser->status ?? 'nonaktif');
$statusTone = ($currentUser->status ?? null) === 'aktif' ? 'success' : 'secondary';
$verificationText = $currentUser->email_verified_at ? 'Terverifikasi' : 'Belum verifikasi';
$verificationTone = $currentUser->email_verified_at ? 'success' : 'warning';
$canManageOvertimeOrders = $currentUser->hasRole(['Super Admin', 'HR', 'HOD', 'Admin Divisi']);
$overtimeRoute = $canManageOvertimeOrders ? route('overtime-orders.index') : route('lembur.index');
$lastLogin = filled($currentUser->terakhir_login) ? \Illuminate\Support\Carbon::parse($currentUser->terakhir_login) : null;
$lastLoginText = $lastLogin ? $lastLogin->translatedFormat('d M Y, H:i') . ' WITA' : '-';
$lastLoginHuman = $lastLogin ? $lastLogin->diffForHumans() : 'Belum ada catatan login';
$dashboardUnreadNotifications = $currentUser->unreadNotifications()->count();
$verifiedAtText = $currentUser->email_verified_at
? $currentUser->email_verified_at->translatedFormat('d M Y, H:i') . ' WITA'
: 'Menunggu verifikasi email';
$sisaCuti = filled(optional($employee)->sisa_cuti) ? optional($employee)->sisa_cuti . ' hari' : '0 hari';
$accessibleMenus = $currentUser->hasRole('Super Admin')
? array_keys(config('access.menus', []))
: $currentUser->resolveMenuPermissions();
$totalAccessibleMenuCount = count(array_unique(array_filter($accessibleMenus)));
$translateWithFallback = static function (string $key, ?string $fallback = null): string {
return \Illuminate\Support\Facades\Lang::has($key) ? __($key) : ($fallback ?? $key);
};
$menuGroupTranslationKeys = [
'Dashboard' => 'dashboard',
'Data Master' => 'master_data',
'Self Service' => 'self_service',
'Approval' => 'approval',
'Operasional' => 'operations',
'Admin Panel' => 'admin_panel',
'Lainnya' => 'other',
];
$menuGroupLabel = static function (?string $group) use ($menuGroupTranslationKeys, $translateWithFallback): string {
$groupName = filled($group) ? $group : 'Lainnya';
$groupKey = $menuGroupTranslationKeys[$groupName] ?? 'other';

return $translateWithFallback('access.menu_groups.' . $groupKey, $groupName);
};
$menuDescription = static function (string $menuKey, ?string $fallback = null) use ($translateWithFallback, $canManageOvertimeOrders): string {
if ($menuKey === 'lembur') {
return $translateWithFallback(
$canManageOvertimeOrders
? 'access.dashboard_menu.overtime_manager_description'
: 'access.dashboard_menu.overtime_staff_description',
$fallback
);
}

return $translateWithFallback('access.dashboard_menu.menu_descriptions.' . $menuKey, $fallback);
};
$menuCatalog = [
'dashboard_admin' => [
'route_name' => 'home',
'icon' => 'fas fa-chart-line',
'tone' => 'primary',
'description' => 'Pantau ringkasan utama dan statistik operasional.',
],
'central_monitor' => [
'route_name' => 'central-monitor.index',
'icon' => 'fas fa-desktop',
'tone' => 'slate',
'description' => 'Pantau approval, presensi, import, queue, dan audit dari satu halaman.',
'roles' => ['Super Admin', 'HR'],
],
'dashboard_karyawan' => [
'route_name' => 'dashboard.karyawan',
'icon' => 'fas fa-home',
'tone' => 'primary',
'description' => 'Kembali ke dashboard utama karyawan.',
],
'data_karyawan' => [
'route_name' => 'karyawan.index',
'icon' => 'fas fa-users',
'tone' => 'emerald',
'description' => 'Kelola dan lihat data karyawan sesuai akses Anda.',
],
'data_user' => [
'route_name' => 'user.index',
'icon' => 'fas fa-user-friends',
'tone' => 'slate',
'description' => 'Atur akun pengguna dan hak akses login.',
],
'slip_gaji_admin' => [
'route_name' => 'slip-gaji.index',
'icon' => 'fas fa-file-invoice-dollar',
'tone' => 'emerald',
'description' => 'Lihat dan kelola slip gaji karyawan.',
],
'electronic_contract_admin' => [
'route_name' => 'electronic-contracts.index',
'icon' => 'fas fa-file-contract',
'tone' => 'amber',
'description' => 'Kelola template, klausul, dan kontrak elektronik karyawan.',
'roles' => ['Super Admin', 'HR'],
],
'electronic_contract_first_party_signature' => [
'route_name' => 'electronic-contracts.first-party-signature.edit',
'icon' => 'fas fa-signature',
'tone' => 'slate',
'description' => 'Kelola tanda tangan master Pihak Pertama untuk kontrak elektronik.',
'roles' => ['Super Admin', 'HR'],
],
'contract_renewal' => [
'route_name' => 'contract-renewals.index',
'icon' => 'fas fa-file-contract',
'tone' => 'violet',
'description' => 'Kelola reminder, penilaian, approval, dan penerbitan adendum perpanjangan kontrak.',
'roles' => ['Super Admin', 'HR', 'HOD', 'Admin Divisi'],
],
'kontrak_elektronik' => [
'route_name' => 'kontrak-elektronik.index',
'icon' => 'fas fa-file-contract',
'tone' => 'amber',
'description' => 'Atur kontrak elektronik dan dokumen terkait karyawan.',
],
'resign' => [
'route_name' => 'resign.index',
'icon' => 'fas fa-user-minus',
'tone' => 'rose',
'description' => 'Pantau proses pengajuan resign dan statusnya.',
],
'surat_peringatan' => [
'route_name' => 'surat-peringatan.index',
'icon' => 'fas fa-file-alt',
'tone' => 'amber',
'description' => 'Kelola surat peringatan yang tercatat di sistem.',
],
'data_presensi' => [
'route_name' => 'data-presensi.index',
'icon' => 'fas fa-clipboard-list',
'tone' => 'sky',
'description' => 'Review rekap dan histori presensi.',
],
'distribusi_wilayah' => [
'route_name' => 'distribusi.index',
'icon' => 'fas fa-map',
'tone' => 'violet',
'description' => 'Atur data distribusi wilayah perusahaan.',
],
'slip_gaji_user' => [
'route_name' => 'slipgaji.index',
'icon' => 'fas fa-wallet',
'tone' => 'emerald',
'description' => 'Lihat slip gaji dan riwayat penghasilan Anda.',
],
'electronic_contract_user' => [
'route_name' => 'user-electronic-contracts.index',
'icon' => 'fas fa-file-signature',
'tone' => 'amber',
'description' => 'Lihat dan tanda tangani kontrak elektronik Anda.',
],
'cuti' => [
'route_name' => 'cuti.index',
'icon' => 'fas fa-umbrella-beach',
'tone' => 'amber',
'description' => 'Ajukan cuti dan cek histori cuti tahunan.',
],
'roster' => [
'route_name' => 'roster.index',
'icon' => 'fas fa-plane-departure',
'tone' => 'teal',
'description' => 'Kelola pengajuan roster dan status persetujuan.',
],
'off_roster' => [
'route_name' => 'roster-off.index',
'icon' => 'fas fa-times',
'tone' => 'sky',
'description' => 'Ajukan hari OFF roster dan lihat riwayat persetujuannya.',
'roles' => ['Staff Roster', 'Super Admin'],
],
'izin' => [
'route_name' => 'izin.index',
'icon' => 'fas fa-file-signature',
'tone' => 'rose',
'description' => 'Kelola izin berbayar maupun tidak berbayar.',
],
'presensi' => [
'route_name' => 'presensi.index',
'icon' => 'fas fa-map-marker-alt',
'tone' => 'primary',
'description' => 'Lakukan presensi dan lihat catatan kehadiran.',
],
'attendance_correction' => [
'route_name' => 'attendance-corrections.index',
'icon' => 'fas fa-user-clock',
'tone' => 'sky',
'description' => 'Ajukan koreksi jam atau status presensi yang perlu direview.',
],
'approval_hod' => [
'route_name' => 'approval.cuti.hod',
'icon' => 'fas fa-user-check',
'tone' => 'violet',
'description' => 'Buka antrean approval pada level HOD.',
],
'approval_hr' => [
'route_name' => 'approval.cuti.hrd',
'icon' => 'fas fa-clipboard-check',
'tone' => 'slate',
'description' => 'Buka antrean approval pada level HR.',
],
'setting_hari_off' => [
'route_name' => 'set-kehadiran.index',
'icon' => 'fas fa-calendar-alt',
'tone' => 'sky',
'description' => 'Atur hari off sesuai unit yang ditangani.',
],
'jadwal_kerja' => [
'route_name' => 'work-patterns.index',
'icon' => 'fas fa-calendar',
'tone' => 'sky',
'description' => 'Kelola master pola kerja dan jadwal kerja.',
],
'lembur' => [
'route' => $overtimeRoute,
'icon' => 'fas fa-clock',
'tone' => 'violet',
'description' => $canManageOvertimeOrders
? 'Buat dan pantau perintah lembur karyawan.'
: 'Lihat dan respons perintah lembur yang ditujukan ke Anda.',
],
'perusahaan' => [
'route_name' => 'perusahaan.index',
'icon' => 'fas fa-building',
'tone' => 'sky',
'description' => 'Kelola struktur perusahaan, departemen, dan divisi.',
],
'setting_lokasi_presensi' => [
'route_name' => 'setting-lokasi-presensi.index',
'icon' => 'fas fa-map-marked-alt',
'tone' => 'primary',
'description' => 'Atur titik lokasi presensi untuk divisi terkait.',
],
'setting_role' => [
'route_name' => 'setting-role.index',
'icon' => 'fas fa-user-shield',
'tone' => 'slate',
'description' => 'Kelola role dan permission menu pengguna.',
],
'import_history' => [
'route_name' => 'import-histories.index',
'icon' => 'fas fa-file-import',
'tone' => 'amber',
'description' => 'Pantau hasil import Excel, CSV, ZIP dokumen, foto, dan referensi presensi.',
],
'exit_portal' => [
'route_name' => 'search-by-security.index',
'icon' => 'fas fa-door-open',
'tone' => 'rose',
'description' => 'Akses portal pencarian keamanan dan log terkait.',
],
];
$allAccessibleMenuItems = collect($accessibleMenus)
->filter()
->unique()
->map(function ($menuKey) use ($menuCatalog, $currentUser, $translateWithFallback, $menuGroupLabel, $menuDescription) {
$menuConfig = config('access.menus.' . $menuKey);
$menuMeta = $menuCatalog[$menuKey] ?? null;

if (!$menuConfig || !$menuMeta) {
return null;
}

if (!empty($menuMeta['roles'] ?? null) && !$currentUser->hasRole($menuMeta['roles'])) {
return null;
}

if (!empty($menuMeta['route'] ?? null)) {
$route = $menuMeta['route'];
} else {
if (empty($menuMeta['route_name']) || !\Illuminate\Support\Facades\Route::has($menuMeta['route_name'])) {
return null;
}

$route = route($menuMeta['route_name']);
}

return [
'key' => $menuKey,
'group' => $menuConfig['group'] ?? 'Lainnya',
'group_label' => $menuGroupLabel($menuConfig['group'] ?? 'Lainnya'),
'label' => $translateWithFallback('access.menus.' . $menuKey . '.label', $menuConfig['label'] ?? $menuKey),
'route' => $route,
'icon' => $menuMeta['icon'] ?? 'fas fa-link',
'tone' => $menuMeta['tone'] ?? 'primary',
'description' => $menuDescription($menuKey, $menuMeta['description'] ?? __('access.dashboard_menu.default_description')),
];
})
->filter()
->groupBy('group');
$hour = now()->hour;
if ($hour < 11) {
    $greeting='Selamat pagi' ;
    } elseif ($hour < 15) {
    $greeting='Selamat siang' ;
    } elseif ($hour < 18) {
    $greeting='Selamat sore' ;
    } else {
    $greeting='Selamat malam' ;
    }

    $quickActions=collect([
    [ 'menu'=> 'presensi',
    'label' => 'Presensi',
    'description' => 'Absen dengan lokasi kerja dan verifikasi wajah.',
    'route' => route('presensi.index'),
    'icon' => 'fas fa-map-marker-alt',
    'tone' => 'primary',
    ],
    [
    'menu' => 'attendance_correction',
    'label' => 'Koreksi Presensi',
    'description' => 'Ajukan koreksi jam atau status presensi yang perlu direview.',
    'route' => route('attendance-corrections.index'),
    'icon' => 'fas fa-user-clock',
    'tone' => 'sky',
    ],
    [
    'menu' => 'slip_gaji_user',
    'label' => 'Slip Gaji',
    'description' => 'Lihat slip gaji terbaru dan riwayat penghasilan.',
    'route' => route('slipgaji.index'),
    'icon' => 'fas fa-wallet',
    'tone' => 'emerald',
    ],
    [
    'menu' => 'electronic_contract_first_party_signature',
    'label' => 'Tanda Tangan Pihak Pertama',
    'description' => 'Kelola tanda tangan master Pihak Pertama untuk kontrak elektronik.',
    'route' => route('electronic-contracts.first-party-signature.edit'),
    'icon' => 'fas fa-signature',
    'tone' => 'slate',
    'roles' => ['Super Admin', 'HR'],
    ],
    [
    'menu' => 'cuti',
    'label' => 'Cuti Tahunan',
    'description' => 'Ajukan cuti dan cek sisa cuti yang tersedia.',
    'route' => route('cuti.index'),
    'icon' => 'fas fa-umbrella-beach',
    'tone' => 'amber',
    ],
    [
    'menu' => 'izin',
    'label' => 'Izin',
    'description' => 'Kelola izin berbayar maupun tidak berbayar.',
    'route' => route('izin.index'),
    'icon' => 'fas fa-file-signature',
    'tone' => 'rose',
    ],
    [
    'menu' => 'roster',
    'label' => 'Roster',
    'description' => 'Ajukan cuti roster dan pantau proses persetujuan.',
    'route' => route('roster.index'),
    'icon' => 'fas fa-plane-departure',
    'tone' => 'teal',
    ],
    [
    'menu' => 'off_roster',
    'label' => 'Pengajuan OFF',
    'description' => 'Ajukan hari OFF roster dan lihat riwayatnya.',
    'route' => route('roster-off.index'),
    'icon' => 'fas fa-times',
    'tone' => 'sky',
    'roles' => ['Staff Roster', 'Super Admin'],
    ],
    [
    'menu' => 'approval_hod',
    'label' => 'Approval HOD',
    'description' => 'Tinjau pengajuan yang membutuhkan keputusan HOD.',
    'route' => route('approval.cuti.hod'),
    'icon' => 'fas fa-user-check',
    'tone' => 'violet',
    ],
    [
    'menu' => 'approval_hr',
    'label' => 'Approval HR',
    'description' => 'Review pengajuan lanjutan pada level HR.',
    'route' => route('approval.cuti.hrd'),
    'icon' => 'fas fa-clipboard-check',
    'tone' => 'slate',
    ],
    [
    'menu' => 'setting_hari_off',
    'label' => 'Hari Off',
    'description' => 'Atur jadwal hari off sesuai divisi yang ditangani.',
    'route' => route('set-kehadiran.index'),
    'icon' => 'fas fa-calendar-alt',
    'tone' => 'sky',
    ],
    [
    'menu' => 'lembur',
    'label' => 'Perintah Lembur',
    'description' => $canManageOvertimeOrders
    ? 'Buat dan monitor perintah lembur dari HOD/Admin Divisi.'
    : 'Buka dan respons perintah lembur yang ditujukan kepada Anda.',
    'route' => $overtimeRoute,
    'icon' => 'fas fa-clock',
    'tone' => 'violet',
    ],
    [
    'menu' => null,
    'label' => 'Profil Saya',
    'description' => 'Perbarui informasi akun dan kata sandi Anda.',
    'route' => route('pengaturan-akun.index'),
    'icon' => 'fas fa-user-cog',
    'tone' => 'primary',
    ],
    ])->filter(function ($action) use ($currentUser) {
    if (!blank($action['menu']) && !$currentUser->hasMenuAccess($action['menu'])) {
    return false;
    }

    if (!empty($action['roles'] ?? null) && !$currentUser->hasRole($action['roles'])) {
    return false;
    }

    return true;
    })->map(function ($action) use ($translateWithFallback, $menuDescription) {
    if (blank($action['menu'])) {
    $action['label'] = __('access.dashboard_menu.profile_label');
    $action['description'] = __('access.dashboard_menu.profile_description');

    return $action;
    }

    $action['label'] = $translateWithFallback('access.menus.' . $action['menu'] . '.label', $action['label']);
    $action['description'] = $menuDescription($action['menu'], $action['description']);

    return $action;
    })->values();
    $quickActionLimit = 8;
    $visibleQuickActions = $quickActions->take($quickActionLimit);
    $canAccessPresensi = $currentUser->hasMenuAccess('presensi');
    $presensiRoute = $canAccessPresensi ? route('presensi.index') : null;
    $attendanceTimes = collect($attendanceSummary['times'] ?? []);
    $attendanceDoneCount = $attendanceTimes->where('filled', true)->count();
    $attendanceTotalCount = $attendanceTimes->count();
    $attendanceProgressPercent = $attendanceTotalCount > 0
    ? (int) round(($attendanceDoneCount / $attendanceTotalCount) * 100)
    : 0;
    $nextAttendanceAction = $attendanceSummary['next_action'] ?? [
    'key' => null,
    'label' => 'Buka Presensi',
    'description' => 'Lihat status presensi Anda hari ini.',
    'tone' => 'primary',
    ];
    $attendanceActionIcons = [
    'masuk' => 'fas fa-sign-in-alt',
    'istirahat' => 'fas fa-coffee',
    'kembali' => 'fas fa-undo-alt',
    'pulang' => 'fas fa-sign-out-alt',
    'done' => 'fas fa-check-circle',
    'status' => 'fas fa-info-circle',
    ];
    $nextAttendanceIcon = $attendanceActionIcons[$nextAttendanceAction['key'] ?? ''] ?? 'fas fa-fingerprint';
    $heroShiftLabel = $attendanceSummary['shift_label'] ?? 'Shift belum tersedia';
    $heroWorkTimeRange = $attendanceSummary['work_time_range'] ?? 'Jam kerja belum diatur';
    @endphp

    <div class="container-fluid employee-dashboard px-3">
        <div class="page-inner dashboard-shell">
            <div class="dashboard-hero mb-3">
                <div class="hero-body">
                    <div class="hero-top">
                        <div class="hero-user">
                            <div class="hero-avatar{{ $employeePhotoUrl ? ' hero-avatar--photo' : '' }}">
                                @if($employeePhotoUrl)
                                <img src="{{ $employeePhotoUrl }}" alt="{{ $displayName }}">
                                @else
                                {{ $displayInitials }}
                                @endif
                            </div>

                            <div class="hero-copy">
                                <div class="hero-meta mb-3">
                                    <div class="hero-date mb-0">
                                        <i class="fas fa-calendar-day"></i>
                                        {{ now()->translatedFormat('l, d F Y') }}
                                    </div>

                                    <a href="{{ route('kotak-masuk.index') }}" class="hero-mobile-notif d-lg-none" aria-label="Buka notifikasi">
                                        <i class="fas fa-bell"></i>
                                        <span class="hero-mobile-notif__label">Notif</span>
                                        @if($dashboardUnreadNotifications > 0)
                                        <span class="hero-mobile-notif__badge">{{ $dashboardUnreadNotifications }}</span>
                                        @endif
                                    </a>
                                </div>

                                <div class="hero-eyebrow">{{ $greeting }}</div>
                                <h1 class="hero-name">{{ $displayName }}</h1>
                                <div class="hero-role">
                                    <i class="fas fa-briefcase"></i>
                                    <span>{{ $positionName }}</span>
                                    <span class="hero-role__divider"></span>
                                    <span>{{ $divisionName }}</span>
                                </div>

                                <div class="chip-row">
                                    <span class="dashboard-chip">
                                        <i class="fas fa-user-clock"></i>
                                        {{ $heroShiftLabel }}
                                    </span>
                                    <span class="dashboard-chip">
                                        <i class="fas fa-clock"></i>
                                        {{ $heroWorkTimeRange }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mobile-command-strip d-lg-none" aria-label="Aksi cepat dashboard">
                @if($attendanceSummary && $canAccessPresensi && $presensiRoute)
                <a href="{{ $presensiRoute }}" class="mobile-command mobile-command--primary">
                    <span class="mobile-command__icon">
                        <i class="{{ $nextAttendanceIcon }}"></i>
                    </span>
                    <span>
                        <strong>{{ $nextAttendanceAction['label'] }}</strong>
                        <small>{{ $nextAttendanceAction['description'] }}</small>
                    </span>
                </a>
                @endif

                <button type="button" class="mobile-command" data-dashboard-scroll="#quickActionsSection">
                    <span class="mobile-command__icon quick-tone--sky">
                        <i class="fas fa-th-large"></i>
                    </span>
                    <span>
                        <strong>{{ $totalAccessibleMenuCount }}</strong>
                        <small>Menu aktif</small>
                    </span>
                </button>

                <a href="{{ route('kotak-masuk.index') }}" class="mobile-command">
                    <span class="mobile-command__icon quick-tone--rose">
                        <i class="fas fa-bell"></i>
                    </span>
                    <span>
                        <strong>{{ $dashboardUnreadNotifications }}</strong>
                        <small>Notifikasi</small>
                    </span>
                </a>

                <a href="{{ route('pengaturan-akun.index') }}" class="mobile-command">
                    <span class="mobile-command__icon quick-tone--emerald">
                        <i class="fas fa-user-check"></i>
                    </span>
                    <span>
                        <strong>{{ $sisaCuti }}</strong>
                        <small>Sisa cuti</small>
                    </span>
                </a>
            </div>

            @if($attendanceSummary)
            <div
                class="dashboard-card attendance-overview mb-3"
                data-dashboard-clock
                data-server-time="{{ $attendanceSummary['server_now_iso'] }}">
                <div class="attendance-overview__main">
                    <div class="attendance-live-panel">
                        <span class="attendance-live-panel__label">
                            <i class="fas fa-clock"></i>
                            Waktu Realtime
                        </span>
                        <strong id="dashboardRealtimeClock" class="attendance-live-panel__clock">
                            {{ now()->format('H:i:s') }}
                        </strong>
                        <small id="dashboardRealtimeDate" class="attendance-live-panel__date">
                            {{ now()->translatedFormat('l, d M Y') }} WITA
                        </small>

                        <div class="attendance-progress" aria-label="Progress presensi hari ini">
                            <div class="attendance-progress__meta">
                                <span>Progress presensi hari ini</span>
                                <strong>{{ $attendanceDoneCount }}/{{ $attendanceTotalCount }}</strong>
                            </div>
                            <div class="attendance-progress__track">
                                <span style="width: {{ $attendanceProgressPercent }}%"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="attendance-times-grid">
                    @foreach($attendanceSummary['times'] as $timeItem)
                    <div class="attendance-time-item {{ $timeItem['filled'] ? 'is-done' : '' }} {{ $timeItem['active'] ? 'is-active' : '' }}">
                        <span class="attendance-time-item__icon">
                            <i class="{{ $timeItem['icon'] }}"></i>
                        </span>
                        <span>
                            <small>{{ $timeItem['label'] }}</small>
                            <strong>{{ $timeItem['time'] }}</strong>
                        </span>
                    </div>
                    @endforeach
                </div>

                @if($canAccessPresensi && $presensiRoute)
                <div class="attendance-overview__footer">
                    <a href="{{ $presensiRoute }}" class="attendance-action-button attendance-action-button--{{ $nextAttendanceAction['tone'] ?? 'primary' }}">
                        <span class="attendance-action-button__icon">
                            <i class="{{ $nextAttendanceIcon }}"></i>
                        </span>
                        <span>
                            <strong>{{ $nextAttendanceAction['label'] }}</strong>
                            <small>{{ $nextAttendanceAction['description'] }}</small>
                        </span>
                    </a>
                </div>
                @endif
            </div>
            @endif

            <div class="dashboard-card mb-3" id="quickActionsSection">
                <div class="section-header">
                    <div>
                            <h2 class="section-title">{{ __('access.dashboard_menu.section_title') }}</h2>
                            <p class="section-subtitle">
                            {{ __('access.dashboard_menu.section_subtitle') }}
                        </p>
                    </div>
                    <div class="section-actions">
                        @if($allAccessibleMenuItems->flatten(1)->isNotEmpty())
                        <button
                            type="button"
                            class="btn btn-view-all"
                            data-bs-toggle="modal"
                            data-bs-target="#allAccessMenuModal">
                            <i class="fas fa-th-large"></i>
                            {{ __('access.dashboard_menu.view_all') }}
                        </button>
                        @endif
                        <span class="section-badge">
                            <i class="fas fa-bolt"></i>
                            {{ __('access.dashboard_menu.main_shortcut_count', ['count' => $visibleQuickActions->count()]) }}
                        </span>
                    </div>
                </div>

                @if($visibleQuickActions->isNotEmpty())
                <div class="quick-grid">
                    @foreach($visibleQuickActions as $action)
                    <a href="{{ $action['route'] }}" class="quick-link">
                        <span class="quick-link__icon quick-tone--{{ $action['tone'] }}">
                            <i class="{{ $action['icon'] }}"></i>
                        </span>
                        <div>
                            <span class="quick-link__title">{{ $action['label'] }}</span>
                            <span class="quick-link__description">{{ $action['description'] }}</span>
                        </div>
                        <span class="quick-link__arrow">
                            {{ __('access.dashboard_menu.open_menu') }}
                            <i class="fas fa-arrow-right ms-1"></i>
                        </span>
                    </a>
                    @endforeach
                </div>
                @else
                <div class="empty-quick-actions">
                    <div class="alert">
                        {{ __('access.dashboard_menu.empty_shortcuts') }}
                    </div>
                </div>
                @endif
            </div>

            @if($allAccessibleMenuItems->flatten(1)->isNotEmpty())
            <div class="modal fade menu-access-modal" id="allAccessMenuModal" tabindex="-1" aria-labelledby="allAccessMenuModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title" id="allAccessMenuModalLabel">{{ __('access.dashboard_menu.all_access_title') }}</h5>
                                <small class="text-muted">{{ __('access.dashboard_menu.all_access_subtitle') }}</small>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="menu-access-tools">
                                <label class="menu-access-search" for="dashboardMenuSearch">
                                    <i class="fas fa-search"></i>
                                    <input
                                        type="search"
                                        id="dashboardMenuSearch"
                                        placeholder="Cari menu..."
                                        autocomplete="off"
                                        data-dashboard-menu-search>
                                </label>

                                <div class="menu-access-filter" aria-label="Filter grup menu">
                                    <button type="button" class="is-active" data-dashboard-menu-filter="all">
                                        Semua
                                    </button>
                                    @foreach($allAccessibleMenuItems as $group => $menus)
                                    <button type="button" data-dashboard-menu-filter="{{ \Illuminate\Support\Str::slug((string) $group) }}">
                                        {{ $menus->first()['group_label'] ?? $group }}
                                    </button>
                                    @endforeach
                                </div>
                            </div>

                            <div class="menu-access-groups" data-dashboard-menu-list>
                                @foreach($allAccessibleMenuItems as $group => $menus)
                                <div class="menu-access-group" data-dashboard-menu-group="{{ \Illuminate\Support\Str::slug((string) $group) }}">
                                    <div class="menu-access-group__head">
                                        <h6 class="menu-access-group__title">{{ $menus->first()['group_label'] ?? $group }}</h6>
                                        <span class="menu-access-group__count">{{ $menus->count() }}</span>
                                    </div>
                                    <div class="menu-access-grid">
                                        @foreach($menus as $menu)
                                        <a href="{{ $menu['route'] }}" class="menu-access-link" data-dashboard-menu-item>
                                            <span class="menu-access-link__icon quick-tone--{{ $menu['tone'] }}">
                                                <i class="{{ $menu['icon'] }}"></i>
                                            </span>
                                            <span>
                                                <span class="menu-access-link__title">{{ $menu['label'] }}</span>
                                                <span class="menu-access-link__description">{{ $menu['description'] }}</span>
                                            </span>
                                        </a>
                                        @endforeach
                                    </div>
                                </div>
                                @endforeach
                            </div>

                            <div class="menu-access-empty d-none" data-dashboard-menu-empty>
                                <i class="fas fa-search"></i>
                                <strong>Menu tidak ditemukan</strong>
                                <span>Coba kata kunci lain atau pilih filter Semua.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <div class="dashboard-info-grid">
                <div class="dashboard-card account-activity-card" data-account-card>
                    <div class="section-header">
                        <div>
                            <h2 class="section-title">Aktivitas Akun</h2>
                            <p class="section-subtitle">
                                Status akses dan aktivitas login terbaru untuk membantu Anda memantau keamanan akun.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="dashboard-detail-toggle d-lg-none"
                            data-account-toggle
                            aria-expanded="false">
                            <span>Lihat detail</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>

                    <div class="activity-list" data-account-details>
                        <div class="activity-row">
                            <div>
                                <small>Status</small>
                                <span class="status-pill status-pill--{{ $statusTone }}">
                                    <i class="fas fa-circle"></i>
                                    {{ $statusText }}
                                </span>
                            </div>
                            <strong>{{ $scopeLabel }}</strong>
                        </div>

                        <div class="activity-row">
                            <div>
                                <small>Email Login</small>
                                <strong>{{ $currentUser->email ?? '-' }}</strong>
                            </div>
                            <strong>{{ $verificationText }}</strong>
                        </div>

                        <div class="activity-row">
                            <div>
                                <small>Verifikasi Email</small>
                                <strong>{{ $verifiedAtText }}</strong>
                            </div>
                            <span class="status-pill status-pill--{{ $verificationTone }}">
                                <i class="fas fa-shield-alt"></i>
                                {{ $verificationText }}
                            </span>
                        </div>

                        <div class="activity-row">
                            <div>
                                <small>Login Terakhir</small>
                                <strong>{{ $lastLoginText }}</strong>
                            </div>
                            <strong>{{ $lastLoginHuman }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endsection

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const clockRoot = document.querySelector('[data-dashboard-clock]');

            if (clockRoot) {
                const clockElement = document.getElementById('dashboardRealtimeClock');
                const dateElement = document.getElementById('dashboardRealtimeDate');
                const serverTime = Date.parse(clockRoot.dataset.serverTime || '');
                const baseTime = Number.isNaN(serverTime) ? Date.now() : serverTime;
                const startedAt = Date.now();
                const clockFormatter = new Intl.DateTimeFormat('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false,
                    timeZone: 'Asia/Makassar'
                });
                const dateFormatter = new Intl.DateTimeFormat('id-ID', {
                    weekday: 'long',
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    timeZone: 'Asia/Makassar'
                });

                const renderClock = function() {
                    const current = new Date(baseTime + (Date.now() - startedAt));

                    if (clockElement) {
                        clockElement.textContent = clockFormatter.format(current).replace(/\./g, ':');
                    }

                    if (dateElement) {
                        dateElement.textContent = dateFormatter.format(current) + ' WITA';
                    }
                };

                renderClock();
                window.setInterval(renderClock, 1000);
            }

            document.querySelectorAll('[data-dashboard-scroll]').forEach(function(trigger) {
                trigger.addEventListener('click', function() {
                    const target = document.querySelector(trigger.dataset.dashboardScroll);

                    if (!target) {
                        return;
                    }

                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                });
            });

            const menuModal = document.getElementById('allAccessMenuModal');

            if (menuModal) {
                const searchInput = menuModal.querySelector('[data-dashboard-menu-search]');
                const filterButtons = Array.from(menuModal.querySelectorAll('[data-dashboard-menu-filter]'));
                const menuGroups = Array.from(menuModal.querySelectorAll('[data-dashboard-menu-group]'));
                const emptyState = menuModal.querySelector('[data-dashboard-menu-empty]');
                let activeFilter = 'all';

                const renderMenuFilter = function() {
                    const keyword = (searchInput ? searchInput.value : '').trim().toLowerCase();
                    let visibleItemCount = 0;

                    menuGroups.forEach(function(group) {
                        const groupKey = group.dataset.dashboardMenuGroup;
                        const groupIsActive = activeFilter === 'all' || activeFilter === groupKey;
                        let groupVisibleCount = 0;

                        group.querySelectorAll('[data-dashboard-menu-item]').forEach(function(item) {
                            const text = item.textContent.trim().toLowerCase();
                            const isVisible = groupIsActive && (!keyword || text.indexOf(keyword) !== -1);

                            item.classList.toggle('d-none', !isVisible);

                            if (isVisible) {
                                groupVisibleCount += 1;
                                visibleItemCount += 1;
                            }
                        });

                        group.classList.toggle('d-none', groupVisibleCount === 0);
                    });

                    if (emptyState) {
                        emptyState.classList.toggle('d-none', visibleItemCount > 0);
                    }
                };

                filterButtons.forEach(function(button) {
                    button.addEventListener('click', function() {
                        activeFilter = button.dataset.dashboardMenuFilter || 'all';
                        filterButtons.forEach(function(item) {
                            item.classList.toggle('is-active', item === button);
                        });
                        renderMenuFilter();
                    });
                });

                if (searchInput) {
                    searchInput.addEventListener('input', renderMenuFilter);
                }

                menuModal.addEventListener('shown.bs.modal', function() {
                    if (searchInput) {
                        searchInput.focus();
                    }
                });

                menuModal.addEventListener('hidden.bs.modal', function() {
                    activeFilter = 'all';

                    if (searchInput) {
                        searchInput.value = '';
                    }

                    filterButtons.forEach(function(button) {
                        button.classList.toggle('is-active', button.dataset.dashboardMenuFilter === 'all');
                    });
                    renderMenuFilter();
                });

                renderMenuFilter();
            }

            const accountCard = document.querySelector('[data-account-card]');

            if (accountCard) {
                const accountToggle = accountCard.querySelector('[data-account-toggle]');
                const mobileQuery = window.matchMedia('(max-width: 575.98px)');

                const setAccountExpanded = function(isExpanded) {
                    accountCard.classList.toggle('is-collapsed', !isExpanded);

                    if (accountToggle) {
                        accountToggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
                        accountToggle.querySelector('span').textContent = isExpanded ? 'Sembunyikan' : 'Lihat detail';
                    }
                };

                const syncAccountState = function() {
                    setAccountExpanded(!mobileQuery.matches);
                };

                if (accountToggle) {
                    accountToggle.addEventListener('click', function() {
                        setAccountExpanded(accountCard.classList.contains('is-collapsed'));
                    });
                }

                if (typeof mobileQuery.addEventListener === 'function') {
                    mobileQuery.addEventListener('change', syncAccountState);
                } else if (typeof mobileQuery.addListener === 'function') {
                    mobileQuery.addListener(syncAccountState);
                }

                syncAccountState();
            }
        });
    </script>
    @endpush
