@php
    $user = auth()->user();
    $homeUrl = $user ? route($user->preferredHomeRouteName()) : '#';
    $can = fn(string $menu) => $user && $user->hasMenuAccess($menu);
    $canManageOvertimeOrders = $user && $user->hasRole(['Super Admin', 'HR', 'HOD', 'Admin Divisi']);
    $canManageOvertimeMaster = $user && $user->hasRole(['Super Admin', 'HR']);
    $shiftMenuActive = request()->routeIs('shifts.*') || request()->routeIs('shift-settings.*');
    $overtimeMenuActive = request()->routeIs('overtime-orders.*') || request()->routeIs('overtime-masters.*');
@endphp

<div class="sidebar" data-background-color="white">
    <div class="sidebar-logo">
        <div class="logo-header" data-background-color="white">
            <a href="{{ $homeUrl }}" class="logo app-brand app-brand--sidebar text-decoration-none" aria-label="V-People">
                <img src="{{ asset('assets/img/kaiadmin/favicon-1.png')}}" alt="" class="navbar-brand app-brand__icon" height="80" />
                <span class="app-brand__text">V-People</span>
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
    </div>
    <div class="sidebar-wrapper scrollbar scrollbar-inner">
        <div class="sidebar-content">
            <ul class="nav nav-secondary">

                @if($can('dashboard_karyawan'))
                    <li class="nav-item {{ request()->routeIs('dashboard.karyawan') ? 'active' : '' }}">
                        <a href="{{ route('dashboard.karyawan') }}">
                            <i class="fas fa-home"></i>
                            <p>{{ __('navigation.dashboard_employee') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('dashboard_admin'))
                    <li class="nav-item {{ request()->routeIs('home') ? 'active' : '' }}">
                        <a href="{{ route('home') }}">
                            <i class="fas fa-home"></i>
                            <p>{{ __('navigation.dashboard') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('data_karyawan') || $can('data_user') || $can('slip_gaji_admin') || $can('resign') || $can('surat_peringatan') || $can('data_presensi') || $can('distribusi_wilayah'))
                    <li class="nav-section">
                        <span class="sidebar-mini-icon">
                            <i class="fa fa-ellipsis-h"></i>
                        </span>
                        <h4 class="text-section">{{ __('navigation.master_data') }}</h4>
                    </li>
                @endif

                @if($can('data_karyawan'))
                    <li class="nav-item {{ request()->routeIs('karyawan.*') ? 'active' : '' }}">
                        <a href="{{ route('karyawan.index') }}">
                            <i class="fas fa-users"></i>
                            <p>{{ __('navigation.employee_data') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('data_user'))
                    <li class="nav-item {{ request()->routeIs('user.*') ? 'active' : '' }}">
                        <a href="{{ route('user.index') }}">
                            <i class="fas fa-user-friends"></i>
                            <p>{{ __('navigation.user_data') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('slip_gaji_admin'))
                    <li class="nav-item {{ request()->routeIs('slip-gaji.*') ? 'active' : '' }}">
                        <a href="{{ route('slip-gaji.index') }}">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <p>{{ __('navigation.salary_slip') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('resign'))
                    <li class="nav-item {{ request()->routeIs('resign.*') ? 'active' : '' }}">
                        <a href="{{ route('resign.index') }}">
                            <i class="fas fa-user-minus"></i>
                            <p>{{ __('navigation.resignation') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('surat_peringatan'))
                    <li class="nav-item {{ request()->routeIs('surat-peringatan.*') ? 'active' : '' }}">
                        <a href="{{ route('surat-peringatan.index') }}">
                            <i class="fas fa-file-alt"></i>
                            <p>{{ __('navigation.violation') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('data_presensi'))
                    <li class="nav-item {{ request()->routeIs('data-presensi.*') ? 'active' : '' }}">
                        <a href="{{ route('data-presensi.index') }}">
                            <i class="fas fa-check"></i>
                            <p>{{ __('navigation.attendance_data') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('distribusi_wilayah'))
                    <li class="nav-item {{ request()->routeIs('distribusi.*') ? 'active' : '' }}">
                        <a href="{{ route('distribusi.index') }}">
                            <i class="fas fa-map"></i>
                            <p>{{ __('navigation.area_distribution') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('slip_gaji_user') || $can('cuti') || $can('roster') || $can('off_roster') || $can('izin') || $can('presensi') || $can('attendance_correction') || ($can('lembur') && !$canManageOvertimeOrders))
                    <li class="nav-section">
                        <span class="sidebar-mini-icon">
                            <i class="fa fa-ellipsis-h"></i>
                        </span>
                        <h4 class="text-section">{{ __('navigation.self_service') }}</h4>
                    </li>
                @endif

                @if($can('slip_gaji_user'))
                    <li class="nav-item {{ request()->routeIs('slipgaji.*') ? 'active' : '' }}">
                        <a href="{{ route('slipgaji.index') }}">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <p>{{ __('navigation.salary_slip') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('cuti'))
                    <li class="nav-item {{ request()->routeIs('cuti.*') ? 'active' : '' }}">
                        <a href="{{ route('cuti.index') }}">
                            <i class="fas fa-sign-out-alt"></i>
                            <p>{{ __('navigation.annual_leave') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('roster'))
                    <li class="nav-item {{ request()->routeIs('roster.*') ? 'active' : '' }}">
                        <a href="{{ route('roster.index') }}">
                            <i class="fas fa-plane-departure"></i>
                            <p>{{ __('navigation.roster_leave') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('off_roster') && $user->hasRole(['Staff Roster', 'Super Admin']))
                    <li class="nav-item {{ request()->routeIs('roster-off.*') ? 'active' : '' }}">
                        <a href="{{ route('roster-off.index') }}">
                            <i class="fas fa-times"></i>
                            <p>{{ __('navigation.roster_off_submission') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('izin'))
                    <li class="nav-item {{ request()->routeIs('izin.*') ? 'active' : '' }}">
                        <a href="{{ route('izin.index') }}">
                            <i class="fas fa-file-signature"></i>
                            <p>{{ __('navigation.permission_paid_unpaid') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('presensi'))
                    <li class="nav-item {{ request()->routeIs('presensi.*') ? 'active' : '' }}">
                        <a href="{{ route('presensi.index') }}">
                            <i class="fas fa-map-pin"></i>
                            <p>{{ __('navigation.employee_attendance') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('attendance_correction'))
                    <li class="nav-item {{ request()->routeIs('attendance-corrections.*') ? 'active' : '' }}">
                        <a href="{{ route('attendance-corrections.index') }}">
                            <i class="fas fa-user-clock"></i>
                            <p>{{ __('navigation.attendance_correction') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('lembur') && !$canManageOvertimeOrders)
                    <li class="nav-item {{ request()->routeIs('lembur.*') ? 'active' : '' }}">
                        <a href="{{ route('lembur.index') }}">
                            <i class="fas fa-clock"></i>
                            <p>{{ __('navigation.overtime_order') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('approval_hod'))
                    <li class="nav-section">
                        <span class="sidebar-mini-icon">
                            <i class="fa fa-ellipsis-h"></i>
                        </span>
                        <div class="sidebar-section-title">
                            <h4 class="text-section mb-0">{{ __('navigation.approval_hod') }}</h4>
                        </div>
                    </li>

                    <li class="nav-item {{ request()->routeIs('approval.cuti.hod') ? 'active' : '' }}">
                        <a href="{{ route('approval.cuti.hod') }}" class="{{ ($approvalHodCounts['cuti'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                            <i class="fas fa-pen"></i>
                            <p>{{ __('navigation.annual_leave') }}</p>
                            @if(($approvalHodCounts['cuti'] ?? 0) > 0)
                                <span class="badge badge-success">{{ $approvalHodCounts['cuti'] }}</span>
                            @endif
                        </a>
                    </li>

                    <li class="nav-item {{ request()->routeIs('approval.izin.hod') ? 'active' : '' }}">
                        <a href="{{ route('approval.izin.hod') }}" class="{{ ($approvalHodCounts['izin'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                            <i class="fas fa-pencil-alt"></i>
                            <p>{{ __('navigation.permission_paid_unpaid') }}</p>
                            @if(($approvalHodCounts['izin'] ?? 0) > 0)
                                <span class="badge badge-secondary">{{ $approvalHodCounts['izin'] }}</span>
                            @endif
                        </a>
                    </li>

                    <li class="nav-item {{ request()->routeIs('approval.roster.hod') ? 'active' : '' }}">
                        <a href="{{ route('approval.roster.hod') }}" class="{{ ($approvalHodCounts['roster'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                            <i class="fas fa-pen-fancy"></i>
                            <p>{{ __('navigation.roster') }}</p>
                            @if(($approvalHodCounts['roster'] ?? 0) > 0)
                                <span class="badge badge-warning">{{ $approvalHodCounts['roster'] }}</span>
                            @endif
                        </a>
                    </li>

                    <li class="nav-item {{ request()->routeIs('approval.roster-off.hod') ? 'active' : '' }}">
                        <a href="{{ route('approval.roster-off.hod') }}" class="{{ ($approvalHodCounts['roster_off'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                            <i class="fas fa-times"></i>
                            <p>{{ __('navigation.roster_off') }}</p>
                            @if(($approvalHodCounts['roster_off'] ?? 0) > 0)
                                <span class="badge badge-info">{{ $approvalHodCounts['roster_off'] }}</span>
                            @endif
                        </a>
                    </li>

                    <li class="nav-item {{ request()->routeIs('approval.attendance-corrections.hod') ? 'active' : '' }}">
                        <a href="{{ route('approval.attendance-corrections.hod') }}" class="{{ ($approvalHodCounts['attendance_correction'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                            <i class="fas fa-user-clock"></i>
                            <p>{{ __('navigation.attendance_correction') }}</p>
                            @if(($approvalHodCounts['attendance_correction'] ?? 0) > 0)
                                <span class="badge badge-danger">{{ $approvalHodCounts['attendance_correction'] }}</span>
                            @endif
                        </a>
                    </li>
                @endif

                @if($can('approval_hr'))
                    <li class="nav-section">
                        <span class="sidebar-mini-icon">
                            <i class="fa fa-ellipsis-h"></i>
                        </span>
                        <div class="sidebar-section-title">
                            <h4 class="text-section mb-0">{{ __('navigation.approval_hr') }}</h4>
                        </div>
                    </li>

                    <li class="nav-item {{ request()->routeIs('approval.cuti.hrd') ? 'active' : '' }}">
                        <a href="{{ route('approval.cuti.hrd') }}" class="{{ ($approvalHrCounts['cuti'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                            <i class="fas fa-pen"></i>
                            <p>{{ __('navigation.annual_leave') }}</p>
                            @if(($approvalHrCounts['cuti'] ?? 0) > 0)
                                <span class="badge badge-primary">{{ $approvalHrCounts['cuti'] }}</span>
                            @endif
                        </a>
                    </li>

                    <li class="nav-item {{ request()->routeIs('approval.izin.hrd') ? 'active' : '' }}">
                        <a href="{{ route('approval.izin.hrd') }}" class="{{ ($approvalHrCounts['izin'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                            <i class="fas fa-pencil-alt"></i>
                            <p>{{ __('navigation.permission_paid_unpaid') }}</p>
                            @if(($approvalHrCounts['izin'] ?? 0) > 0)
                                <span class="badge badge-secondary">{{ $approvalHrCounts['izin'] }}</span>
                            @endif
                        </a>
                    </li>

                    <li class="nav-item {{ request()->routeIs('approval.roster.hrd') ? 'active' : '' }}">
                        <a href="{{ route('approval.roster.hrd') }}" class="{{ ($approvalHrCounts['roster'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                            <i class="fas fa-pen-fancy"></i>
                            <p>{{ __('navigation.roster') }}</p>
                            @if(($approvalHrCounts['roster'] ?? 0) > 0)
                                <span class="badge badge-warning">{{ $approvalHrCounts['roster'] }}</span>
                            @endif
                        </a>
                    </li>

                    <li class="nav-item {{ request()->routeIs('approval.roster-off.hrd') ? 'active' : '' }}">
                        <a href="{{ route('approval.roster-off.hrd') }}" class="{{ ($approvalHrCounts['roster_off'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                            <i class="fas fa-times"></i>
                            <p>{{ __('navigation.roster_off') }}</p>
                            @if(($approvalHrCounts['roster_off'] ?? 0) > 0)
                                <span class="badge badge-info">{{ $approvalHrCounts['roster_off'] }}</span>
                            @endif
                        </a>
                    </li>

                    <li class="nav-item {{ request()->routeIs('approval.attendance-corrections.hrd') ? 'active' : '' }}">
                        <a href="{{ route('approval.attendance-corrections.hrd') }}" class="{{ ($approvalHrCounts['attendance_correction'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                            <i class="fas fa-user-clock"></i>
                            <p>{{ __('navigation.attendance_correction') }}</p>
                            @if(($approvalHrCounts['attendance_correction'] ?? 0) > 0)
                                <span class="badge badge-danger">{{ $approvalHrCounts['attendance_correction'] }}</span>
                            @endif
                        </a>
                    </li>
                @endif

                @if($can('setting_hari_off') || $can('master_tanggal_merah') || $can('jadwal_kerja') || $can('master_shift') || $can('pengaturan_shift') || ($can('lembur') && $canManageOvertimeOrders) || $can('perusahaan') || $can('leave_balance'))
                    <li class="nav-section">
                        <span class="sidebar-mini-icon">
                            <i class="fa fa-ellipsis-h"></i>
                        </span>
                        <h4 class="text-section">{{ __('navigation.operations') }}</h4>
                    </li>
                @endif

                @if($can('setting_hari_off'))
                    <li class="nav-item {{ request()->routeIs('set-kehadiran.*') ? 'active' : '' }}">
                        <a href="{{ route('set-kehadiran.index') }}">
                            <i class="fas fa-cogs"></i>
                            <p>{{ __('navigation.day_off_setting') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('master_tanggal_merah'))
                    <li class="nav-item {{ request()->routeIs('national-holidays.*') ? 'active' : '' }}">
                        <a href="{{ route('national-holidays.index') }}">
                            <i class="fas fa-calendar"></i>
                            <p>{{ __('navigation.holiday_master') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('jadwal_kerja'))
                    <li class="nav-item {{ request()->routeIs('work-patterns.*') ? 'active' : '' }}">
                        <a href="{{ route('work-patterns.index') }}">
                            <i class="fas fa-spinner"></i>
                            <p>{{ __('navigation.work_pattern_master') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('master_shift') || $can('pengaturan_shift'))
                    <li class="nav-item {{ $shiftMenuActive ? 'active' : '' }}">
                        <a data-bs-toggle="collapse" href="#operationShift"
                            class="{{ $shiftMenuActive ? '' : 'collapsed' }}"
                            aria-expanded="{{ $shiftMenuActive ? 'true' : 'false' }}">
                            <i class="fas fa-user-clock"></i>
                            <p>{{ __('navigation.shift') }}</p>
                            <span class="caret"></span>
                        </a>

                        <div class="collapse {{ $shiftMenuActive ? 'show' : '' }}" id="operationShift">
                            <ul class="nav nav-collapse">
                                @if($can('master_shift'))
                                    <li class="{{ request()->routeIs('shifts.*') ? 'active' : '' }}">
                                        <a href="{{ route('shifts.index') }}">
                                            <span class="sub-item">{{ __('navigation.shift_master') }}</span>
                                        </a>
                                    </li>
                                @endif

                                @if($can('pengaturan_shift'))
                                    <li class="{{ request()->routeIs('shift-settings.*') ? 'active' : '' }}">
                                        <a href="{{ route('shift-settings.index') }}">
                                            <span class="sub-item">{{ __('navigation.shift_setting') }}</span>
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif

                @if($can('lembur') && ($canManageOvertimeOrders || $canManageOvertimeMaster))
                    <li class="nav-item {{ $overtimeMenuActive ? 'active' : '' }}">
                        <a data-bs-toggle="collapse" href="#operationOvertime"
                            class="{{ $overtimeMenuActive ? '' : 'collapsed' }}"
                            aria-expanded="{{ $overtimeMenuActive ? 'true' : 'false' }}">
                            <i class="fas fa-clock"></i>
                            <p>{{ __('navigation.overtime') }}</p>
                            <span class="caret"></span>
                        </a>

                        <div class="collapse {{ $overtimeMenuActive ? 'show' : '' }}" id="operationOvertime">
                            <ul class="nav nav-collapse">
                                @if($canManageOvertimeOrders)
                                    <li class="{{ request()->routeIs('overtime-orders.*') ? 'active' : '' }}">
                                        <a href="{{ route('overtime-orders.index') }}">
                                            <span class="sub-item">{{ __('navigation.overtime_order') }}</span>
                                        </a>
                                    </li>
                                @endif

                                @if($canManageOvertimeMaster)
                                    <li class="{{ request()->routeIs('overtime-masters.*') ? 'active' : '' }}">
                                        <a href="{{ route('overtime-masters.index') }}">
                                            <span class="sub-item">{{ __('navigation.overtime_master') }}</span>
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif

                @if($can('perusahaan'))
                    <li class="nav-item {{ request()->routeIs('perusahaan.*') ? 'active' : '' }}">
                        <a href="{{ route('perusahaan.index') }}">
                            <i class="fas fa-hotel"></i>
                            <p>{{ __('navigation.company') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('leave_balance'))
                    <li class="nav-item {{ request()->routeIs('leave-balances.*') ? 'active' : '' }}">
                        <a href="{{ route('leave-balances.index') }}">
                            <i class="fas fa-calendar-check"></i>
                            <p>{{ __('navigation.leave_balance') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('setting_lokasi_presensi') || $can('setting_role') || $can('audit_trail') || $can('import_history') || $can('exit_portal'))
                    <li class="nav-section">
                        <span class="sidebar-mini-icon">
                            <i class="fa fa-ellipsis-h"></i>
                        </span>
                        <h4 class="text-section">{{ __('navigation.admin_panel') }}</h4>
                    </li>
                @endif

                @if($can('setting_lokasi_presensi'))
                    <li class="nav-item {{ request()->routeIs('setting-lokasi-presensi.*') ? 'active' : '' }}">
                        <a href="{{ route('setting-lokasi-presensi.index') }}">
                            <i class="fas fa-map-marked-alt"></i>
                            <p>{{ __('navigation.attendance_location') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('setting_role'))
                    <li class="nav-item {{ request()->routeIs('setting-role.*') ? 'active' : '' }}">
                        <a href="{{ route('setting-role.index') }}">
                            <i class="fas fa-user-shield"></i>
                            <p>{{ __('navigation.role_access') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('audit_trail'))
                    <li class="nav-item {{ request()->routeIs('audit-trails.*') ? 'active' : '' }}">
                        <a href="{{ route('audit-trails.index') }}">
                            <i class="fas fa-clipboard-list"></i>
                            <p>{{ __('navigation.audit_trail') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('import_history'))
                    <li class="nav-item {{ request()->routeIs('import-histories.*') ? 'active' : '' }}">
                        <a href="{{ route('import-histories.index') }}">
                            <i class="fas fa-file-import"></i>
                            <p>{{ __('navigation.import_history') }}</p>
                        </a>
                    </li>
                @endif

                @if($can('exit_portal'))
                    <li class="nav-item {{ request()->routeIs('search-by-security.*') || request()->routeIs('search-logs.*') ? 'active' : '' }}">
                        <a data-bs-toggle="collapse" href="#security"
                            class="{{ request()->routeIs('search-by-security.*') || request()->routeIs('search-logs.*') ? '' : 'collapsed' }}"
                            aria-expanded="{{ request()->routeIs('search-by-security.*') || request()->routeIs('search-logs.*') ? 'true' : 'false' }}">

                            <i class="fas fa-laptop"></i>
                            <p>{{ __('navigation.exit_portal') }}</p>
                            <span class="caret"></span>
                        </a>

                        <div class="collapse {{ request()->routeIs('search-by-security.*') || request()->routeIs('search-logs.*') ? 'show' : '' }}" id="security">
                            <ul class="nav nav-collapse">
                                <li class="{{ request()->routeIs('search-by-security.index') ? 'active' : '' }}">
                                    <a href="{{ route('search-by-security.index') }}">
                                        <span class="sub-item">{{ __('navigation.user') }}</span>
                                    </a>
                                </li>

                                <li class="{{ request()->routeIs('search-logs.index') ? 'active' : '' }}">
                                    <a href="{{ route('search-logs.index') }}">
                                        <span class="sub-item">{{ __('navigation.logs') }}</span>
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
