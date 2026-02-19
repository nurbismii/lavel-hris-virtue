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
                        <span class="badge badge-success">4</span>
                    </a>
                </li>

                @if(Auth::user()->role->permission_role == 'Administrator')

                <li class="nav-item {{ request()->routeIs('home') ? 'active' : '' }}">
                    <a href="{{ route('home') }}">
                        <i class="fas fa-home"></i>
                        <p>Dashboard</p>
                        <span class="badge badge-success">4</span>
                    </a>
                </li>

                <li class="nav-section">
                    <span class="sidebar-mini-icon">
                        <i class="fa fa-ellipsis-h"></i>
                    </span>
                    <h4 class="text-section">Karyawan</h4>
                </li>

                <li class="nav-item {{ request()->routeIs('karyawan.index') ? 'active' : '' }}">
                    <a href="{{ route('karyawan.index') }}">
                        <i class="fas fa-users"></i>
                        <p>Data Karyawan</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('user.index') ? 'active' : '' }}">
                    <a href="{{ route('user.index') }}">
                        <i class="fas fa-user-friends"></i>
                        <p>Data User</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('slip-gaji.index') ? 'active' : '' }}">
                    <a href="{{ route('slip-gaji.index') }}">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <p>Slip Gaji</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('resign.index') ? 'active' : '' }}">
                    <a href="{{ route('resign.index') }}">
                        <i class="fas fa-user-minus"></i>
                        <p>Resign</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('surat-peringatan.index') ? 'active' : '' }}">
                    <a href="{{ route('surat-peringatan.index') }}">
                        <i class="fas fa-file-alt"></i>
                        <p>Surat Peringatan</p>
                    </a>
                </li>

                @endif

                <li class="nav-item {{ request()->routeIs('cuti.index') ? 'active' : '' }}">
                    <a href="{{ route('cuti.index') }}">
                        <i class="fas fa-sign-out-alt"></i>
                        <p>Cuti Tahunan</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('roster.index') ? 'active' : '' }}">
                    <a href="{{ route('roster.index') }}">
                        <i class="fas fa-plane-departure"></i>
                        <p>Cuti Roster</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('izin.index') ? 'active' : '' }}">
                    <a href="{{ route('izin.index') }}">
                        <i class="fas fa-file-signature"></i>
                        <p>Izin (Paid & Unpaid)</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('presensi.index') }}">
                        <i class="fas fa-map-pin"></i>
                        <p>Presensi Karyawan</p>
                    </a>
                </li>

                @if(Auth::user()->role->permission_role == 'Administrator' || Auth::user()->role->permission_role == 'HOD')

                <li class="nav-section">
                    <span class="sidebar-mini-icon">
                        <i class="fa fa-ellipsis-h"></i>
                    </span>
                    <h4 class="text-section">Approval HOD</h4>
                </li>

                <li class="nav-item {{ request()->routeIs('approval.cuti.hod') ? 'active' : '' }}">
                    <a href="{{ route('approval.cuti.hod') }}">
                        <i class="fas fa-pen"></i>
                        <p>Cuti Tahunan</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('approval.izin.hod') ? 'active' : '' }}">
                    <a href="{{ route('approval.izin.hod') }}">
                        <i class="fas fa-pencil-alt"></i>
                        <p>Izin (Paid & Unpaid)</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('approval.roster.hod') ? 'active' : '' }}">
                    <a href="{{ route('approval.roster.hod') }}">
                        <i class="fas fa-pen-fancy"></i>
                        <p>Roster</p>
                    </a>
                </li>

                @endif

                @if(Auth::user()->role->permission_role == 'Administrator' || Auth::user()->role->permission_role == 'HRD')

                <li class="nav-section">
                    <span class="sidebar-mini-icon">
                        <i class="fa fa-ellipsis-h"></i>
                    </span>
                    <h4 class="text-section">Approval HR</h4>
                </li>

                <li class="nav-item {{ request()->routeIs('approval.cuti.hrd') ? 'active' : '' }}">
                    <a href="{{ route('approval.cuti.hrd') }}">
                        <i class="fas fa-pen"></i>
                        <p>Cuti Tahunan</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('approval.izin.hrd') ? 'active' : '' }}">
                    <a href="{{ route('approval.izin.hrd') }}">
                        <i class="fas fa-pencil-alt"></i>
                        <p>Izin (Paid & Unpaid)</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('approval.roster.hrd') ? 'active' : '' }}">
                    <a href="{{ route('approval.roster.hrd') }}">
                        <i class="fas fa-pen-fancy"></i>
                        <p>Roster</p>
                    </a>
                </li>

                @endif

                @if(Auth::user()->role->permission_role == 'Administrator')

                <li class="nav-section">
                    <span class="sidebar-mini-icon">
                        <i class="fa fa-ellipsis-h"></i>
                    </span>
                    <h4 class="text-section">Manage Organisasi</h4>
                </li>

                <li class="nav-item {{ request()->routeIs('perusahaan.index') ? 'active' : '' }}">
                    <a href="{{ route('perusahaan.index') }}">
                        <i class="fas fa-hotel"></i>
                        <p>Perusahaan</p>
                    </a>
                </li>

                <li class="nav-section">
                    <span class="sidebar-mini-icon">
                        <i class="fa fa-ellipsis-h"></i>
                    </span>
                    <h4 class="text-section">Admin Panel</h4>
                </li>

                <li class="nav-item {{ request()->routeIs('setting-lokasi-presensi.index') ? 'active' : '' }}">
                    <a href="{{ route('setting-lokasi-presensi.index') }}">
                        <i class="fas fa-map-marked-alt"></i>
                        <p>Lokasi Presensi</p>
                    </a>
                </li>

                <li class="nav-item {{ request()->routeIs('setting-role.index') ? 'active' : '' }}">
                    <a href="{{ route('setting-role.index') }}">
                        <i class="fas fa-user-shield"></i>
                        <p>Peran dan Akses</p>
                    </a>
                </li>

                @endif

            </ul>
        </div>
    </div>
</div>