@once
    @push('styles')
        <style>
            .sidebar-section-title {
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .sidebar-notification-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 2px 8px;
                border-radius: 999px;
                background: #dc3545;
                color: #ffffff;
                font-size: 11px;
                font-weight: 700;
                line-height: 1;
            }

            .sidebar-notification-badge i {
                font-size: 10px;
            }

            .sidebar .nav-item a .sidebar-link-badge {
                margin-left: auto;
            }
        </style>
    @endpush
@endonce

<div class="sidebar" data-background-color="white">
    <div class="sidebar-logo">
        <!-- Logo Header -->
        <div class="logo-header" data-background-color="white">
            <a href="{{ route('home') }}" class="logo">
                <img src="{{ asset('assets/img/kaiadmin/favicon-1.png')}}" alt="navbar brand" class="navbar-brand" height="80" />
                <div class="text-decoration-none logo-industrial">V-People</div>
            </a>
            <div class="nav-toggle">
                <button class="btn btn-toggle toggle-sidebar">
                    <i class="gg-menu-right"></i>
                </button>
                <button class="btn btn-toggle sidenav-toggler">
                    <i class="gg-menu-left"></i>
                </button>
            </div>
            <button class="topbar-toggler more">
                <i class="gg-more-vertical-alt"></i>
            </button>
        </div>
        <!-- End Logo Header -->
    </div>
    <div class="sidebar-wrapper scrollbar scrollbar-inner">
        <div class="sidebar-content">
            <ul class="nav nav-secondary">

                <li class="nav-item {{ request()->routeIs('dashboard.karyawan') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.karyawan') }}">
                        <i class="fas fa-home"></i>
                        <p>Dashboard Karyawan</p>
                    </a>
                </li>

                @if(auth()->user()->hasRole(['Administrator', 'HR']))

                <li class="nav-item {{ request()->routeIs('home') ? 'active' : '' }}">
                    <a href="{{ route('home') }}">
                        <i class="fas fa-home"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <li class="nav-section">
                    <span class="sidebar-mini-icon">
                        <i class="fa fa-ellipsis-h"></i>
                    </span>
                    <h4 class="text-section">Karyawan</h4>
                </li>

                <li class="nav-item {{ request()->routeIs('karyawan.*') ? 'active' : '' }}">
                    <a href="{{ route('karyawan.index') }}">
                        <i class="fas fa-users"></i>
                        <p>Data Karyawan</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('user.*') ? 'active' : '' }}">
                    <a href="{{ route('user.index') }}">
                        <i class="fas fa-user-friends"></i>
                        <p>Data User</p>
                    </a>
                </li>

                @endif

                @if(auth()->user()->hasRole(['Administrator']))

                <li class="nav-item {{ request()->routeIs('slip-gaji.*') ? 'active' : '' }}">
                    <a href="{{ route('slip-gaji.index') }}">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <p>Slip Gaji</p>
                    </a>
                </li>

                @endif

                @if(auth()->user()->hasRole(['Administrator', 'HR']))

                <li class="nav-item {{ request()->routeIs('resign.*') ? 'active' : '' }}">
                    <a href="{{ route('resign.index') }}">
                        <i class="fas fa-user-minus"></i>
                        <p>Resign</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('surat-peringatan.*') ? 'active' : '' }}">
                    <a href="{{ route('surat-peringatan.index') }}">
                        <i class="fas fa-file-alt"></i>
                        <p>Surat Peringatan</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('data-presensi.*') ? 'active' : '' }}">
                    <a href="{{ route('data-presensi.index') }}">
                        <i class="fas fa-check"></i>
                        <p>Data Presensi</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('distribusi.*') ? 'active' : '' }}">
                    <a href="{{ route('distribusi.index') }}">
                        <i class="fas fa-map"></i>
                        <p>Distribusi Wilayah</p>
                    </a>
                </li>

                @endif

                @if(auth()->user()->hasRole(['Administrator', 'User', 'HR']))

                {{-- SLIP GAJI USER --}}
                <li class="nav-item {{ request()->routeIs('slipgaji.*') ? 'active' : '' }}">
                    <a href="{{ route('slipgaji.index') }}">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <p>Slip Gaji</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('cuti.*') ? 'active' : '' }}">
                    <a href="{{ route('cuti.index') }}">
                        <i class="fas fa-sign-out-alt"></i>
                        <p>Cuti Tahunan</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('roster.*') ? 'active' : '' }}">
                    <a href="{{ route('roster.index') }}">
                        <i class="fas fa-plane-departure"></i>
                        <p>Cuti Roster</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('izin.*') ? 'active' : '' }}">
                    <a href="{{ route('izin.index') }}">
                        <i class="fas fa-file-signature"></i>
                        <p>Izin (Paid & Unpaid)</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('presensi.*') ? 'active' : '' }}">
                    <a href="{{ route('presensi.index') }}">
                        <i class="fas fa-map-pin"></i>
                        <p>Presensi Karyawan</p>
                    </a>
                </li>

                @endif

                @if(auth()->user()->hasRole(['Administrator', 'HOD', 'HR']))

                <li class="nav-section">
                    <span class="sidebar-mini-icon">
                        <i class="fa fa-ellipsis-h"></i>
                    </span>
                    <div class="sidebar-section-title">
                        <h4 class="text-section mb-0">Approval HOD</h4>
                        @if(($approvalHodCounts['total'] ?? 0) > 0)
                            <span class="sidebar-notification-badge">
                                <i class="fas fa-bell"></i>
                                {{ $approvalHodCounts['total'] }}
                            </span>
                        @endif
                    </div>
                </li>

                <li class="nav-item {{ request()->routeIs('approval.cuti.hod') ? 'active' : '' }}">
                    <a href="{{ route('approval.cuti.hod') }}">
                        <i class="fas fa-pen"></i>
                        <p>Cuti Tahunan</p>
                        @if(($approvalHodCounts['cuti'] ?? 0) > 0)
                            <span class="sidebar-notification-badge sidebar-link-badge">
                                <i class="fas fa-bell"></i>
                                {{ $approvalHodCounts['cuti'] }}
                            </span>
                        @endif
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('approval.izin.hod') ? 'active' : '' }}">
                    <a href="{{ route('approval.izin.hod') }}">
                        <i class="fas fa-pencil-alt"></i>
                        <p>Izin (Paid & Unpaid)</p>
                        @if(($approvalHodCounts['izin'] ?? 0) > 0)
                            <span class="sidebar-notification-badge sidebar-link-badge">
                                <i class="fas fa-bell"></i>
                                {{ $approvalHodCounts['izin'] }}
                            </span>
                        @endif
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('approval.roster.hod') ? 'active' : '' }}">
                    <a href="{{ route('approval.roster.hod') }}">
                        <i class="fas fa-pen-fancy"></i>
                        <p>Roster</p>
                        @if(($approvalHodCounts['roster'] ?? 0) > 0)
                            <span class="sidebar-notification-badge sidebar-link-badge">
                                <i class="fas fa-bell"></i>
                                {{ $approvalHodCounts['roster'] }}
                            </span>
                        @endif
                    </a>
                </li>

                {{-- ADMIN DIVISI --}}
                <li class="nav-section">
                    <span class="sidebar-mini-icon">
                        <i class="fa fa-ellipsis-h"></i>
                    </span>
                    <h4 class="text-section">Admin Divisi</h4>
                </li>

                <li class="nav-item {{ request()->routeIs('set-kehadiran.*') ? 'active' : '' }}">
                    <a href="{{ route('set-kehadiran.index') }}">
                        <i class="fas fa-cog"></i>
                        <p>Setting Hari Off</p>
                    </a>
                </li>

                @endif

                @if(auth()->user()->hasRole(['Administrator', 'HR']))

                <li class="nav-section">
                    <span class="sidebar-mini-icon">
                        <i class="fa fa-ellipsis-h"></i>
                    </span>
                    <div class="sidebar-section-title">
                        <h4 class="text-section mb-0">Approval HR</h4>
                        @if(($approvalHrCounts['total'] ?? 0) > 0)
                            <span class="sidebar-notification-badge">
                                <i class="fas fa-bell"></i>
                                {{ $approvalHrCounts['total'] }}
                            </span>
                        @endif
                    </div>
                </li>

                <li class="nav-item {{ request()->routeIs('approval.cuti.hrd') ? 'active' : '' }}">
                    <a href="{{ route('approval.cuti.hrd') }}">
                        <i class="fas fa-pen"></i>
                        <p>Cuti Tahunan</p>
                        @if(($approvalHrCounts['cuti'] ?? 0) > 0)
                            <span class="sidebar-notification-badge sidebar-link-badge">
                                <i class="fas fa-bell"></i>
                                {{ $approvalHrCounts['cuti'] }}
                            </span>
                        @endif
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('approval.izin.hrd') ? 'active' : '' }}">
                    <a href="{{ route('approval.izin.hrd') }}">
                        <i class="fas fa-pencil-alt"></i>
                        <p>Izin (Paid & Unpaid)</p>
                        @if(($approvalHrCounts['izin'] ?? 0) > 0)
                            <span class="sidebar-notification-badge sidebar-link-badge">
                                <i class="fas fa-bell"></i>
                                {{ $approvalHrCounts['izin'] }}
                            </span>
                        @endif
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('approval.roster.hrd') ? 'active' : '' }}">
                    <a href="{{ route('approval.roster.hrd') }}">
                        <i class="fas fa-pen-fancy"></i>
                        <p>Roster</p>
                        @if(($approvalHrCounts['roster'] ?? 0) > 0)
                            <span class="sidebar-notification-badge sidebar-link-badge">
                                <i class="fas fa-bell"></i>
                                {{ $approvalHrCounts['roster'] }}
                            </span>
                        @endif
                    </a>
                </li>

                @endif

                @if(auth()->user()->hasRole(['Administrator', 'HR']))

                <li class="nav-section">
                    <span class="sidebar-mini-icon">
                        <i class="fa fa-ellipsis-h"></i>
                    </span>
                    <h4 class="text-section">Manage Organisasi</h4>
                </li>

                <li class="nav-item {{ request()->routeIs('perusahaan.*') ? 'active' : '' }}">
                    <a href="{{ route('perusahaan.index') }}">
                        <i class="fas fa-hotel"></i>
                        <p>Perusahaan</p>
                    </a>
                </li>

                @endif

                @if(auth()->user()->hasRole(['Administrator']))

                <li class="nav-section">
                    <span class="sidebar-mini-icon">
                        <i class="fa fa-ellipsis-h"></i>
                    </span>
                    <h4 class="text-section">Admin Panel</h4>
                </li>

                <li class="nav-item {{ request()->routeIs('setting-lokasi-presensi.*') ? 'active' : '' }}">
                    <a href="{{ route('setting-lokasi-presensi.index') }}">
                        <i class="fas fa-map-marked-alt"></i>
                        <p>Lokasi Presensi</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('setting-role.*') ? 'active' : '' }}">
                    <a href="{{ route('setting-role.index') }}">
                        <i class="fas fa-user-shield"></i>
                        <p>Peran dan Akses</p>
                    </a>
                </li>

                <li class="nav-section">
                    <span class="sidebar-mini-icon">
                        <i class="fa fa-ellipsis-h"></i>
                    </span>
                    <h4 class="text-section">Third Party</h4>
                </li>

                <li class="nav-item {{ request()->routeIs('search-by-security.*') || request()->routeIs('search-logs.*') ? 'active' : '' }}">
                    <a data-bs-toggle="collapse" href="#security"
                        class="{{ request()->routeIs('search-by-security.*') || request()->routeIs('search-logs.*') ? '' : 'collapsed' }}"
                        aria-expanded="{{ request()->routeIs('search-by-security.*') || request()->routeIs('search-logs.*') ? 'true' : 'false' }}">

                        <i class="fas fa-laptop"></i>
                        <p>Exit Portal</p>
                        <span class="caret"></span>
                    </a>

                    <div class="collapse {{ request()->routeIs('search-by-security.*') || request()->routeIs('search-logs.*') ? 'show' : '' }}" id="security">
                        <ul class="nav nav-collapse">
                            <li class="{{ request()->routeIs('search-by-security.index') ? 'active' : '' }}">
                                <a href="{{ route('search-by-security.index') }}">
                                    <span class="sub-item">User</span>
                                </a>
                            </li>

                            <li class="{{ request()->routeIs('search-logs.index') ? 'active' : '' }}">
                                <a href="{{ route('search-logs.index') }}">
                                    <span class="sub-item">Logs</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                @endif

            </ul>
        </div>
    </div>
</div>
