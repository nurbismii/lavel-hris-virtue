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

    .employee-dashboard .hero-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: wrap;
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

    .employee-dashboard .hero-mobile-notif {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        position: relative;
        gap: 0.5rem;
        min-width: 42px;
        height: 42px;
        padding: 0 0.9rem;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.22);
        color: #ffffff;
        text-decoration: none;
        flex-shrink: 0;
        box-shadow: 0 14px 28px rgba(15, 23, 42, 0.14);
        cursor: pointer;
        transition: transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .employee-dashboard .hero-mobile-notif:hover,
    .employee-dashboard .hero-mobile-notif:focus {
        color: #ffffff;
        text-decoration: none;
        background: rgba(255, 255, 255, 0.28);
        border-color: rgba(255, 255, 255, 0.32);
        transform: translateY(-2px);
        box-shadow: 0 18px 32px rgba(15, 23, 42, 0.18);
    }

    .employee-dashboard .hero-mobile-notif i {
        font-size: 1rem;
        line-height: 1;
    }

    .employee-dashboard .hero-mobile-notif__label {
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        line-height: 1;
    }

    .employee-dashboard .hero-mobile-notif__badge {
        position: absolute;
        top: -5px;
        right: -5px;
        min-width: 18px;
        height: 18px;
        padding: 0 0.3rem;
        border-radius: 999px;
        background: #ef4444;
        color: #fff;
        font-size: 0.65rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 16px rgba(239, 68, 68, 0.26);
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
        gap: 0.75rem;
        min-height: 44px;
        padding: 0.82rem 1.05rem;
        border-radius: 18px;
        text-decoration: none;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease, border-color 0.2s ease;
    }

    .employee-dashboard .hero-button:hover,
    .employee-dashboard .hero-button:focus {
        transform: translateY(-2px);
        text-decoration: none;
    }

    .employee-dashboard .hero-button--light {
        background: #fff;
        color: var(--dash-ink);
        border: 1px solid rgba(255, 255, 255, 0.65);
        box-shadow: 0 16px 30px rgba(15, 23, 42, 0.14);
    }

    .employee-dashboard .hero-button--ghost {
        background: rgba(255, 255, 255, 0.18);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.24);
        box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12);
    }

    .employee-dashboard .hero-button__icon {
        width: 34px;
        height: 34px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 0.92rem;
    }

    .employee-dashboard .hero-button--light .hero-button__icon {
        background: #eaf2ff;
        color: #2563eb;
    }

    .employee-dashboard .hero-button--ghost .hero-button__icon {
        background: rgba(255, 255, 255, 0.16);
        color: #ffffff;
    }

    .employee-dashboard .hero-button__label {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        line-height: 1.2;
    }

    .employee-dashboard .hero-button__arrow {
        opacity: 0.72;
        font-size: 0.82rem;
        transition: transform 0.2s ease, opacity 0.2s ease;
    }

    .employee-dashboard .hero-button:hover .hero-button__arrow,
    .employee-dashboard .hero-button:focus .hero-button__arrow {
        opacity: 1;
        transform: translateX(2px);
    }

    .employee-dashboard .hero-stats {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.85rem;
    }

    .employee-dashboard .hero-stat {
        min-height: 65px;
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

    .employee-dashboard .section-actions {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        flex-wrap: wrap;
    }

    .employee-dashboard .btn-view-all {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        min-height: 40px;
        padding: 0.65rem 0.95rem;
        border: 1px solid #c7d2fe;
        border-radius: 14px;
        background: #fff;
        color: #1d4ed8;
        font-size: 0.84rem;
        font-weight: 700;
        box-shadow: 0 10px 20px rgba(37, 99, 235, 0.08);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .employee-dashboard .btn-view-all:hover,
    .employee-dashboard .btn-view-all:focus {
        color: #1d4ed8;
        border-color: #93c5fd;
        box-shadow: 0 14px 24px rgba(37, 99, 235, 0.12);
        transform: translateY(-1px);
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

    .employee-dashboard .menu-access-modal .modal-content {
        border: 0;
        border-radius: 26px;
        overflow: hidden;
        box-shadow: 0 28px 60px rgba(15, 23, 42, 0.18);
    }

    .employee-dashboard .menu-access-modal .modal-header {
        padding: 1.1rem 1.25rem;
        background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%);
        border-bottom: 1px solid #e2e8f0;
    }

    .employee-dashboard .menu-access-modal .modal-title {
        color: var(--dash-ink);
        font-size: 1rem;
        font-weight: 700;
    }

    .employee-dashboard .menu-access-modal .modal-body {
        padding: 1rem 1.15rem 1.2rem;
        background: #f8fafc;
    }

    .employee-dashboard .menu-access-groups {
        display: grid;
        gap: 0.95rem;
    }

    .employee-dashboard .menu-access-group {
        padding: 1rem;
        border-radius: 22px;
        border: 1px solid #e2e8f0;
        background: #fff;
    }

    .employee-dashboard .menu-access-group__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.9rem;
        flex-wrap: wrap;
    }

    .employee-dashboard .menu-access-group__title {
        margin: 0;
        color: var(--dash-ink);
        font-size: 0.92rem;
        font-weight: 700;
    }

    .employee-dashboard .menu-access-group__count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 28px;
        min-height: 28px;
        padding: 0.25rem 0.55rem;
        border-radius: 999px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .employee-dashboard .menu-access-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.8rem;
    }

    .employee-dashboard .menu-access-link {
        display: flex;
        align-items: flex-start;
        gap: 0.8rem;
        padding: 0.95rem;
        border-radius: 18px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: var(--dash-ink);
        text-decoration: none;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .employee-dashboard .menu-access-link:hover,
    .employee-dashboard .menu-access-link:focus {
        color: var(--dash-ink);
        text-decoration: none;
        border-color: #c7d2fe;
        box-shadow: 0 14px 24px rgba(15, 23, 42, 0.08);
        transform: translateY(-1px);
    }

    .employee-dashboard .menu-access-link__icon {
        width: 42px;
        height: 42px;
        flex-shrink: 0;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 0.95rem;
        box-shadow: 0 12px 22px rgba(15, 23, 42, 0.12);
    }

    .employee-dashboard .menu-access-link__title {
        display: block;
        color: var(--dash-ink);
        font-size: 0.88rem;
        font-weight: 700;
        line-height: 1.4;
    }

    .employee-dashboard .menu-access-link__description {
        display: block;
        margin-top: 0.22rem;
        color: var(--dash-muted);
        font-size: 0.76rem;
        line-height: 1.5;
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

        .employee-dashboard .hero-mobile-notif {
            width: auto;
        }
    }

    @media (max-width: 575.98px) {
        .employee-dashboard .dashboard-shell {
            padding-top: 10px;
        }

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

        .employee-dashboard .section-actions,
        .employee-dashboard .menu-access-grid {
            width: 100%;
            grid-template-columns: 1fr;
        }

        .employee-dashboard .btn-view-all {
            width: 100%;
            justify-content: center;
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
$dashboardUnreadNotifications = $currentUser->unreadNotifications()->count();
$verifiedAtText = $currentUser->email_verified_at
? $currentUser->email_verified_at->translatedFormat('d M Y, H:i') . ' WITA'
: 'Menunggu verifikasi email';
$sisaCuti = filled(optional($employee)->sisa_cuti) ? optional($employee)->sisa_cuti . ' hari' : '0 hari';
$accessibleMenus = $currentUser->hasRole('Super Admin')
? array_keys(config('access.menus', []))
: $currentUser->resolveMenuPermissions();
$totalAccessibleMenuCount = count(array_unique(array_filter($accessibleMenus)));
$menuCatalog = [
    'dashboard_admin' => [
        'route_name' => 'home',
        'icon' => 'fas fa-chart-line',
        'tone' => 'primary',
        'description' => 'Pantau ringkasan utama dan statistik operasional.',
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
    ->map(function ($menuKey) use ($menuCatalog) {
        $menuConfig = config('access.menus.' . $menuKey);
        $menuMeta = $menuCatalog[$menuKey] ?? null;

        if (!$menuConfig || !$menuMeta) {
            return null;
        }

        if (empty($menuMeta['route_name']) || !\Illuminate\Support\Facades\Route::has($menuMeta['route_name'])) {
            return null;
        }

        return [
            'key' => $menuKey,
            'group' => $menuConfig['group'] ?? 'Lainnya',
            'label' => $menuConfig['label'] ?? $menuKey,
            'route' => route($menuMeta['route_name']),
            'icon' => $menuMeta['icon'] ?? 'fas fa-link',
            'tone' => $menuMeta['tone'] ?? 'primary',
            'description' => $menuMeta['description'] ?? 'Akses menu sesuai permission akun Anda.',
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
    @endphp

    <div class="container-fluid employee-dashboard px-3">
        <div class="page-inner dashboard-shell">
            <div class="dashboard-hero mb-3">
                <div class="hero-body">
                    <div class="hero-top">
                        <div class="hero-user">
                            <div class="hero-avatar">{{ $displayInitial }}</div>

                            <div>
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
                                <span class="hero-button__icon">
                                    <i class="fas fa-user-circle"></i>
                                </span>
                                <span class="hero-button__label">
                                    Profil Saya
                                    <i class="fas fa-arrow-right hero-button__arrow"></i>
                                </span>
                            </a>
                            <a href="{{ route('update.akun') }}" class="hero-button hero-button--ghost">
                                <span class="hero-button__icon">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <span class="hero-button__label">
                                    Pengaturan Akun
                                    <i class="fas fa-arrow-right hero-button__arrow"></i>
                                </span>
                            </a>
                        </div>
                    </div>

                    <div class="hero-stats">
                        <div class="hero-stat">
                            <small>Status Akun</small>
                            <strong>{{ $statusText }}</strong>
                        </div>
                        <div class="hero-stat">
                            <small>Sisa Cuti</small>
                            <strong>{{ $sisaCuti }}</strong>
                        </div>
                        <div class="hero-stat">
                            <small>Verifikasi Email</small>
                            <strong>{{ $verificationText }}</strong>
                        </div>
                        <div class="hero-stat">
                            <small>Total Akses</small>
                            <strong>{{ $totalAccessibleMenuCount }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-card mb-3">
                <div class="section-header">
                    <div>
                        <h2 class="section-title">Menu</h2>
                        <p class="section-subtitle">
                            Dashboard hanya menampilkan shortcut yang paling sering dipakai.
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
                            Lihat semua
                        </button>
                        @endif
                        <span class="section-badge">
                            <i class="fas fa-bolt"></i>
                            {{ $visibleQuickActions->count() }} shortcut utama
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

            @if($allAccessibleMenuItems->flatten(1)->isNotEmpty())
            <div class="modal fade menu-access-modal" id="allAccessMenuModal" tabindex="-1" aria-labelledby="allAccessMenuModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title" id="allAccessMenuModalLabel">Semua Akses Menu</h5>
                                <small class="text-muted">Daftar menu yang aktif sesuai permission akun Anda.</small>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="menu-access-groups">
                                @foreach($allAccessibleMenuItems as $group => $menus)
                                <div class="menu-access-group">
                                    <div class="menu-access-group__head">
                                        <h6 class="menu-access-group__title">{{ $group }}</h6>
                                        <span class="menu-access-group__count">{{ $menus->count() }}</span>
                                    </div>
                                    <div class="menu-access-grid">
                                        @foreach($menus as $menu)
                                        <a href="{{ $menu['route'] }}" class="menu-access-link">
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
                        </div>
                    </div>
                </div>
            </div>
            @endif

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
