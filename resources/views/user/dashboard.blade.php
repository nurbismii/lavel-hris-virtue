@extends('layouts.app')

@section('title', 'Dashboard Karyawan')

@push('styles')
<style>
    .employee-dashboard {
        --dash-ink: #0f172a;
        --dash-muted: #64748b;
        --dash-line: #dbe4f0;
        --dash-soft: #f8fafc;
        --dash-surface: #ffffff;
        --dash-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
    }

    .employee-dashboard .dashboard-shell {
        max-width: 1240px;
        margin: 0 auto;
    }

    .employee-dashboard .dashboard-hero,
    .employee-dashboard .dashboard-card {
        border: 0;
        border-radius: 28px;
        overflow: hidden;
        box-shadow: var(--dash-shadow);
    }

    .employee-dashboard .dashboard-hero {
        position: relative;
        background:
            radial-gradient(circle at top right, rgba(255, 255, 255, 0.22), transparent 35%),
            linear-gradient(135deg, #0f3d68 0%, #15616d 52%, #1f7a4d 100%);
        color: #fff;
    }

    .employee-dashboard .dashboard-hero::before,
    .employee-dashboard .dashboard-hero::after {
        content: "";
        position: absolute;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.08);
        pointer-events: none;
    }

    .employee-dashboard .dashboard-hero::before {
        width: 220px;
        height: 220px;
        top: -110px;
        right: -70px;
    }

    .employee-dashboard .dashboard-hero::after {
        width: 150px;
        height: 150px;
        bottom: -70px;
        right: 18%;
    }

    .employee-dashboard .hero-body {
        position: relative;
        z-index: 1;
        padding: 1.35rem;
        display: grid;
        gap: 1rem;
    }

    .employee-dashboard .hero-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .employee-dashboard .hero-user {
        display: flex;
        gap: 1rem;
        align-items: flex-start;
    }

    .employee-dashboard .hero-avatar {
        width: 64px;
        height: 64px;
        border-radius: 22px;
        background: rgba(255, 255, 255, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.18);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.45rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        flex-shrink: 0;
    }

    .employee-dashboard .hero-date {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.45rem 0.8rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.16);
        font-size: 0.78rem;
        font-weight: 600;
        letter-spacing: 0.02em;
    }

    .employee-dashboard .hero-eyebrow {
        margin-bottom: 0.3rem;
        font-size: 0.83rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.78);
    }

    .employee-dashboard .hero-name {
        margin: 0;
        font-size: clamp(1.55rem, 5vw, 2.25rem);
        font-weight: 700;
        line-height: 1.1;
    }

    .employee-dashboard .hero-subtitle {
        margin: 0.45rem 0 0;
        max-width: 44rem;
        color: rgba(255, 255, 255, 0.82);
        font-size: 0.95rem;
        line-height: 1.6;
    }

    .employee-dashboard .chip-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.55rem;
        margin-top: 0.9rem;
    }

    .employee-dashboard .dashboard-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.48rem 0.82rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.16);
        font-size: 0.82rem;
        font-weight: 600;
        color: #fff;
    }

    .employee-dashboard .dashboard-chip i {
        font-size: 0.78rem;
    }

    .employee-dashboard .hero-actions {
        display: flex;
        gap: 0.7rem;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .employee-dashboard .hero-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        min-height: 44px;
        padding: 0.78rem 1rem;
        border-radius: 16px;
        text-decoration: none;
        font-weight: 600;
        transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }

    .employee-dashboard .hero-button:hover,
    .employee-dashboard .hero-button:focus {
        transform: translateY(-1px);
        text-decoration: none;
    }

    .employee-dashboard .hero-button--light {
        background: #fff;
        color: var(--dash-ink);
        box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12);
    }

    .employee-dashboard .hero-button--ghost {
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.16);
    }

    .employee-dashboard .hero-stats {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.85rem;
    }

    .employee-dashboard .hero-stat {
        min-height: 88px;
        padding: 0.9rem 1rem;
        border-radius: 20px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.14);
        backdrop-filter: blur(10px);
    }

    .employee-dashboard .hero-stat small {
        display: block;
        color: rgba(255, 255, 255, 0.74);
        font-size: 0.78rem;
        margin-bottom: 0.35rem;
    }

    .employee-dashboard .hero-stat strong {
        display: block;
        font-size: 1.15rem;
        line-height: 1.2;
        font-weight: 700;
        color: #fff;
    }

    .employee-dashboard .hero-stat span {
        display: block;
        margin-top: 0.22rem;
        color: rgba(255, 255, 255, 0.72);
        font-size: 0.78rem;
    }

    .employee-dashboard .dashboard-card {
        background: var(--dash-surface);
    }

    .employee-dashboard .section-header {
        padding: 1.2rem 1.25rem 0;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .employee-dashboard .section-title {
        margin: 0;
        color: var(--dash-ink);
        font-size: 1.02rem;
        font-weight: 700;
    }

    .employee-dashboard .section-subtitle {
        margin: 0.3rem 0 0;
        color: var(--dash-muted);
        font-size: 0.88rem;
        line-height: 1.6;
    }

    .employee-dashboard .section-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.44rem 0.75rem;
        border-radius: 999px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .employee-dashboard .quick-grid {
        padding: 1rem 1.25rem 1.25rem;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.85rem;
    }

    .employee-dashboard .quick-link {
        display: flex;
        flex-direction: column;
        gap: 0.8rem;
        min-height: 144px;
        padding: 1rem;
        border-radius: 22px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: var(--dash-ink);
        text-decoration: none;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .employee-dashboard .quick-link:hover,
    .employee-dashboard .quick-link:focus {
        transform: translateY(-2px);
        box-shadow: 0 18px 30px rgba(15, 23, 42, 0.08);
        border-color: #c7d2fe;
        text-decoration: none;
        color: var(--dash-ink);
    }

    .employee-dashboard .quick-link__icon {
        width: 48px;
        height: 48px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.1rem;
        box-shadow: 0 16px 25px rgba(15, 23, 42, 0.12);
    }

    .employee-dashboard .quick-link__title {
        display: block;
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--dash-ink);
    }

    .employee-dashboard .quick-link__description {
        display: block;
        margin-top: 0.28rem;
        color: var(--dash-muted);
        font-size: 0.82rem;
        line-height: 1.55;
    }

    .employee-dashboard .quick-link__arrow {
        margin-top: auto;
        color: #94a3b8;
        font-size: 0.84rem;
        font-weight: 600;
    }

    .employee-dashboard .quick-tone--primary {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    }

    .employee-dashboard .quick-tone--emerald {
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
    }

    .employee-dashboard .quick-tone--amber {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    .employee-dashboard .quick-tone--rose {
        background: linear-gradient(135deg, #ef4444 0%, #e11d48 100%);
    }

    .employee-dashboard .quick-tone--teal {
        background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%);
    }

    .employee-dashboard .quick-tone--violet {
        background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    }

    .employee-dashboard .quick-tone--slate {
        background: linear-gradient(135deg, #334155 0%, #0f172a 100%);
    }

    .employee-dashboard .quick-tone--sky {
        background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
    }

    .employee-dashboard .dashboard-info-grid {
        display: grid;
        gap: 1rem;
    }

    .employee-dashboard .detail-grid {
        padding: 1rem 1.25rem 1.25rem;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.85rem;
    }

    .employee-dashboard .detail-item {
        padding: 0.95rem 1rem;
        border-radius: 18px;
        border: 1px solid var(--dash-line);
        background: var(--dash-soft);
    }

    .employee-dashboard .detail-item small {
        display: block;
        margin-bottom: 0.38rem;
        color: var(--dash-muted);
        font-size: 0.78rem;
    }

    .employee-dashboard .detail-item strong {
        display: block;
        color: var(--dash-ink);
        font-size: 0.95rem;
        line-height: 1.5;
    }

    .employee-dashboard .activity-list {
        padding: 0.35rem 1.25rem 1.25rem;
    }

    .employee-dashboard .activity-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.85rem;
        padding: 0.95rem 0;
        border-bottom: 1px dashed var(--dash-line);
    }

    .employee-dashboard .activity-row:last-child {
        padding-bottom: 0;
        border-bottom: 0;
    }

    .employee-dashboard .activity-row small {
        display: block;
        margin-bottom: 0.28rem;
        color: var(--dash-muted);
        font-size: 0.77rem;
    }

    .employee-dashboard .activity-row strong {
        display: block;
        color: var(--dash-ink);
        font-size: 0.95rem;
        line-height: 1.45;
        text-align: right;
    }

    .employee-dashboard .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.42rem;
        padding: 0.42rem 0.76rem;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .employee-dashboard .status-pill--success {
        background: rgba(34, 197, 94, 0.12);
        color: #15803d;
    }

    .employee-dashboard .status-pill--warning {
        background: rgba(245, 158, 11, 0.16);
        color: #b45309;
    }

    .employee-dashboard .status-pill--secondary {
        background: rgba(148, 163, 184, 0.15);
        color: #475569;
    }

    .employee-dashboard .empty-quick-actions {
        padding: 1.1rem 1.25rem 1.25rem;
    }

    .employee-dashboard .empty-quick-actions .alert {
        border: 1px dashed #cbd5e1;
        background: #f8fafc;
        color: #475569;
        border-radius: 18px;
        margin: 0;
    }

    .employee-dashboard .quick-summary {
        padding: 0 1.25rem 1.25rem;
    }

    .employee-dashboard .quick-summary__card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.95rem 1rem;
        border-radius: 18px;
        background: #f8fafc;
        border: 1px solid #dbe4f0;
        color: var(--dash-ink);
    }

    .employee-dashboard .quick-summary__card strong {
        display: block;
        margin-bottom: 0.2rem;
        font-size: 0.92rem;
    }

    .employee-dashboard .quick-summary__card span {
        display: block;
        color: var(--dash-muted);
        font-size: 0.82rem;
        line-height: 1.55;
    }

    .employee-dashboard .quick-summary__badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 42px;
        min-height: 42px;
        padding: 0.4rem 0.75rem;
        border-radius: 999px;
        background: #e2e8f0;
        color: #0f172a;
        font-size: 0.86rem;
        font-weight: 700;
        flex-shrink: 0;
    }

    @media (min-width: 768px) {
        .employee-dashboard .hero-body {
            padding: 1.75rem;
        }

        .employee-dashboard .hero-stats {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .employee-dashboard .quick-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }

    @media (min-width: 992px) {
        .employee-dashboard .dashboard-info-grid {
            grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.85fr);
        }
    }

    @media (max-width: 767.98px) {
        .employee-dashboard .hero-actions {
            width: 100%;
            justify-content: stretch;
        }

        .employee-dashboard .hero-button {
            flex: 1 1 calc(50% - 0.35rem);
        }
    }

    @media (max-width: 575.98px) {

        .employee-dashboard .dashboard-card,
        .employee-dashboard .dashboard-hero {
            border-radius: 22px;
        }

        .employee-dashboard .hero-user {
            flex-direction: column;
        }

        .employee-dashboard .hero-avatar {
            width: 58px;
            height: 58px;
            border-radius: 18px;
        }

        .employee-dashboard .quick-grid,
        .employee-dashboard .detail-grid {
            grid-template-columns: 1fr;
        }

        .employee-dashboard .activity-row {
            flex-direction: column;
        }

        .employee-dashboard .activity-row strong {
            text-align: left;
        }

        .employee-dashboard .quick-summary__card {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>
@endpush

@section('content')
@php
$currentUser = $user;
$employee = $currentUser->employee;
$normalizedRole = $currentUser->normalized_role_name;
$roleConfig = config('access.roles.' . $normalizedRole, []);
$displayName = $employee->nama_karyawan ?? $currentUser->name ?? 'Pengguna';
$displayInitial = strtoupper(substr($displayName, 0, 1));
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
$lastLogin = filled($currentUser->terakhir_login) ? \Illuminate\Support\Carbon::parse($currentUser->terakhir_login) : null;
$lastLoginText = $lastLogin ? $lastLogin->translatedFormat('d M Y, H:i') . ' WITA' : '-';
$lastLoginHuman = $lastLogin ? $lastLogin->diffForHumans() : 'Belum ada catatan login';
$verifiedAtText = $currentUser->email_verified_at
? $currentUser->email_verified_at->translatedFormat('d M Y, H:i') . ' WITA'
: 'Menunggu verifikasi email';
$sisaCuti = filled(optional($employee)->sisa_cuti) ? optional($employee)->sisa_cuti . ' hari' : '0 hari';
$accessibleMenus = $currentUser->hasRole('Super Admin')
? array_keys(config('access.menus', []))
: $currentUser->resolveMenuPermissions();
$totalAccessibleMenuCount = count(array_unique(array_filter($accessibleMenus)));
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
    'menu' => 'slip_gaji_user',
    'label' => 'Slip Gaji',
    'description' => 'Lihat slip gaji terbaru dan riwayat penghasilan.',
    'route' => route('slipgaji.index'),
    'icon' => 'fas fa-wallet',
    'tone' => 'emerald',
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
    'menu' => null,
    'label' => 'Profil Saya',
    'description' => 'Perbarui informasi akun dan kata sandi Anda.',
    'route' => route('pengaturan-akun.index'),
    'icon' => 'fas fa-user-cog',
    'tone' => 'primary',
    ],
    ])->filter(function ($action) use ($currentUser) {
    return blank($action['menu']) || $currentUser->hasMenuAccess($action['menu']);
    })->values();
$quickActionLimit = 6;
$visibleQuickActions = $quickActions->take($quickActionLimit);
$remainingMenuCount = max($totalAccessibleMenuCount - $visibleQuickActions->count(), 0);
    @endphp

    <div class="container-fluid employee-dashboard px-3">
        <div class="page-inner dashboard-shell">
            <div class="dashboard-hero mb-3">
                <div class="hero-body">
                    <div class="hero-top">
                        <div class="hero-user">
                            <div class="hero-avatar">{{ $displayInitial }}</div>

                            <div>
                                <div class="hero-date mb-3">
                                    <i class="fas fa-calendar-day"></i>
                                    {{ now()->translatedFormat('l, d F Y') }}
                                </div>

                                <div class="hero-eyebrow">{{ $greeting }}</div>
                                <h1 class="hero-name">{{ $displayName }}</h1>
                                <p class="hero-subtitle">
                                    {{ $roleDescription }}
                                    Saat ini Anda berada di unit {{ $divisionName }} dengan cakupan akses {{ strtolower($scopeLabel) }}.
                                </p>

                                <div class="chip-row">
                                    <span class="dashboard-chip">
                                        <i class="fas fa-user-shield"></i>
                                        {{ $currentUser->display_role_name }}
                                    </span>
                                    <span class="dashboard-chip">
                                        <i class="fas fa-sitemap"></i>
                                        {{ $divisionName }}
                                    </span>
                                    <span class="dashboard-chip">
                                        <i class="fas fa-building"></i>
                                        {{ $departmentName }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="hero-actions">
                            <a href="{{ route('pengaturan-akun.index') }}" class="hero-button hero-button--light">
                                <i class="fas fa-user-circle"></i>
                                Profil Saya
                            </a>
                            <a href="{{ route('update.akun') }}" class="hero-button hero-button--ghost">
                                <i class="fas fa-lock"></i>
                                Pengaturan Akun
                            </a>
                        </div>
                    </div>

                    <div class="hero-stats">
                        <div class="hero-stat">
                            <small>Status Akun</small>
                            <strong>{{ $statusText }}</strong>
                            <span>{{ ($currentUser->status ?? null) === 'aktif' ? 'Akun siap digunakan' : 'Perlu pengecekan status akun' }}</span>
                        </div>
                        <div class="hero-stat">
                            <small>Sisa Cuti</small>
                            <strong>{{ $sisaCuti }}</strong>
                            <span>Hak cuti yang tercatat pada profil karyawan Anda</span>
                        </div>
                        <div class="hero-stat">
                            <small>Verifikasi Email</small>
                            <strong>{{ $verificationText }}</strong>
                            <span>{{ $currentUser->email_verified_at ? 'Akses akun terlindungi dengan email aktif' : 'Segera verifikasi agar akun tetap aman' }}</span>
                        </div>
                        <div class="hero-stat">
                            <small>Total Akses</small>
                            <strong>{{ $totalAccessibleMenuCount }}</strong>
                            <span>Hak menu aktif sesuai permission akun Anda</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-card mb-3">
                <div class="section-header">
                    <div>
                        <h2 class="section-title">Menu Prioritas</h2>
                        <p class="section-subtitle">
                            Dashboard hanya menampilkan shortcut yang paling sering dipakai. Menu lainnya tetap bisa Anda buka dari sidebar atau navigasi bawah.
                        </p>
                    </div>
                    <span class="section-badge">
                        <i class="fas fa-bolt"></i>
                        {{ $visibleQuickActions->count() }} shortcut utama
                    </span>
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
                            Buka menu
                            <i class="fas fa-arrow-right ms-1"></i>
                        </span>
                    </a>
                    @endforeach
                </div>
                @else
                <div class="empty-quick-actions">
                    <div class="alert">
                        Belum ada shortcut yang aktif untuk akun ini. Silakan hubungi administrator jika Anda membutuhkan akses tambahan.
                    </div>
                </div>
                @endif
            </div>

            <div class="dashboard-info-grid">
                <div class="dashboard-card mb-3 mb-lg-0">
                    <div class="section-header">
                        <div>
                            <h2 class="section-title">Profil Kerja</h2>
                            <p class="section-subtitle">
                                Ringkasan identitas kerja yang membantu memastikan akun ini terhubung ke unit dan data yang tepat.
                            </p>
                        </div>
                    </div>

                    <div class="detail-grid">
                        <div class="detail-item">
                            <small>NIK Karyawan</small>
                            <strong>{{ $employee->nik ?? ($currentUser->nik_karyawan ?? '-') }}</strong>
                        </div>
                        <div class="detail-item">
                            <small>Posisi</small>
                            <strong>{{ $positionName }}</strong>
                        </div>
                        <div class="detail-item">
                            <small>Divisi</small>
                            <strong>{{ $divisionName }}</strong>
                        </div>
                        <div class="detail-item">
                            <small>Departemen</small>
                            <strong>{{ $departmentName }}</strong>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="section-header">
                        <div>
                            <h2 class="section-title">Aktivitas Akun</h2>
                            <p class="section-subtitle">
                                Status akses dan aktivitas login terbaru untuk membantu Anda memantau keamanan akun.
                            </p>
                        </div>
                    </div>

                    <div class="activity-list">
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
