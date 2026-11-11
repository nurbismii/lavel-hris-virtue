@php
$user = auth()->user();
$homeUrl = $user ? route($user->preferredHomeRouteName()) : '#';
$can = fn(string $menu) => $user && $user->hasMenuAccess($menu);
$canManageOvertimeOrders = $user && $user->hasRole(['Super Admin', 'HR', 'HOD', 'Admin Divisi']);
$canManageOvertimeMaster = $user && $user->hasRole(['Super Admin', 'HR']);
$canManageFirstPartySignature = $user && $user->hasRole(['Super Admin', 'HR']) && $can('electronic_contract_first_party_signature');
$canManageApprovalDelegations = $user && $user->hasRole(['Super Admin', 'HOD']) && $can('approval_hod');
$canUseDelegateApproval = $user && ($approvalDelegateAccess ?? false);
$canUseEmployeeMovement = $user && ($can('employee_movement') || ($employeeMovementDelegateAccess ?? false));
$contractRenewalActive = request()->routeIs('contract-renewals.*');
$contractRenewalDashboardActive = request()->routeIs('contract-renewals.dashboard');
$contractRenewalWorkflowActive = $contractRenewalActive && !$contractRenewalDashboardActive;
$attendanceAnomalyActive = request()->routeIs('attendance-anomalies.*');
$attendancePeriodLockActive = request()->routeIs('attendance-period-locks.*');
$approvalSlaActive = request()->routeIs('approval-sla.*');
$approvalHodDashboardActive = request()->routeIs('approval.hod.dashboard');
$centralMonitorActive = request()->routeIs('central-monitor.*');
$employeeMovementActive = request()->routeIs('employee-movements.*');
$cvMakerCompareActive = request()->routeIs('cv-maker-compare.*');
$approvalPendingCount = ($can('approval_hod') ? ($approvalHodCounts['total'] ?? 0) : 0)
    + ($can('approval_hr') ? ($approvalHrCounts['total'] ?? 0) : 0)
    + ($canUseDelegateApproval ? ($approvalDelegateCounts['total'] ?? 0) : 0);
@endphp

@if($serviceDirectory ?? false)
<nav class="native-service-list" aria-label="{{ __('navigation.services') }}">
@else
<div class="sidebar" id="appSidebar" role="navigation" aria-label="{{ __('Menu utama') }}" data-background-color="white">
    <div class="sidebar-logo">
        <div class="logo-header" data-background-color="white">
            <a href="{{ $homeUrl }}" class="logo app-brand app-brand--sidebar text-decoration-none" aria-label="V-People">
                <img src="{{ asset('assets/img/kaiadmin/icon-2.png')}}" alt="" class="navbar-brand app-brand__icon" height="80" />
                <span class="app-brand__text">V-People</span>
            </a>
            <div class="nav-toggle">
                <button type="button" class="btn btn-toggle toggle-sidebar" aria-label="{{ __('Ringkas menu navigasi') }}">
                    <i class="gg-menu-right" aria-hidden="true"></i>
                </button>
                <button type="button" class="btn btn-toggle app-menu-toggle" data-app-menu-toggle aria-controls="appSidebar" aria-expanded="false" aria-label="{{ __('Menu navigasi') }}">
                    <i class="gg-menu-left" aria-hidden="true"></i>
                </button>
            </div>
            <button class="topbar-toggler more">
                <i class="gg-more-vertical-alt" aria-hidden="true"></i>
            </button>
        </div>
    </div>
    <div class="sidebar-wrapper scrollbar scrollbar-inner">
        <div class="sidebar-content">
@endif
            <ul class="nav nav-secondary app-navigation" id="appNavigation">

                @if($can('dashboard_admin'))
                <li class="nav-item {{ request()->routeIs('home') ? 'active' : '' }}">
                    <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif aria-label="{{ __('navigation.home_admin') }}" title="{{ __('navigation.home_admin') }}">
                        <i class="fas fa-home" aria-hidden="true"></i>
                        <p>{{ __('navigation.home_admin') }}</p>
                    </a>
                </li>
                @endif

                @if($can('dashboard_karyawan'))
                <li class="nav-item {{ request()->routeIs('dashboard.karyawan') ? 'active' : '' }}">
                    <a href="{{ route('dashboard.karyawan') }}" @if(request()->routeIs('dashboard.karyawan')) aria-current="page" @endif aria-label="{{ __('navigation.home_employee') }}" title="{{ __('navigation.home_employee') }}">
                        <i class="fas fa-home" aria-hidden="true"></i>
                        <p>{{ __('navigation.home_employee') }}</p>
                    </a>
                </li>
                @endif

                @if($can('presensi'))
                <li class="nav-item {{ request()->routeIs('presensi.*') ? 'active' : '' }}">
                    <a href="{{ route('presensi.index') }}" @if(request()->routeIs('presensi.*')) aria-current="page" @endif aria-label="{{ __('navigation.my_attendance') }}" title="{{ __('navigation.my_attendance') }}">
                        <i class="fas fa-map-pin" aria-hidden="true"></i>
                        <p>{{ __('navigation.my_attendance') }}</p>
                    </a>
                </li>
                @endif

                @component('partials.sidebar-group', [
                    'id' => 'sidebar-approvals', 'label' => __('navigation.my_approvals'), 'icon' => 'fas fa-clipboard-check',
                    'active' => request()->routeIs('approval.*'), 'badge' => $approvalPendingCount
                ])
                    @if($can('approval_hod'))
                        <li class="app-nav-subheading">{{ __('navigation.approval_hod') }}</li>

                        <li class="{{ $approvalHodDashboardActive ? 'active' : '' }}">
                            <a href="{{ route('approval.hod.dashboard') }}" @if($approvalHodDashboardActive) aria-current="page" @endif class="{{ ($approvalHodCounts['total'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                                <span class="app-nav-label">{{ __('navigation.approval_hod_dashboard') }}</span>
                                @if(($approvalHodCounts['total'] ?? 0) > 0)
                                    <span class="badge badge-primary">{{ $approvalHodCounts['total'] }}</span>
                                @endif
                            </a>
                        </li>

                        @if($canManageApprovalDelegations)
                            <li class="{{ request()->routeIs('approval.delegations.*') ? 'active' : '' }}">
                                <a href="{{ route('approval.delegations.index') }}" @if(request()->routeIs('approval.delegations.*')) aria-current="page" @endif>
                                    <span class="app-nav-label">{{ __('navigation.approval_delegation') }}</span>
                                </a>
                            </li>
                        @endif

                        <li class="{{ request()->routeIs('approval.cuti.hod') ? 'active' : '' }}">
                            <a href="{{ route('approval.cuti.hod') }}" @if(request()->routeIs('approval.cuti.hod')) aria-current="page" @endif class="{{ ($approvalHodCounts['cuti'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                                <span class="app-nav-label">{{ __('navigation.annual_leave') }}</span>
                                @if(($approvalHodCounts['cuti'] ?? 0) > 0)
                                    <span class="badge badge-success">{{ $approvalHodCounts['cuti'] }}</span>
                                @endif
                            </a>
                        </li>

                        <li class="{{ request()->routeIs('approval.izin.hod') ? 'active' : '' }}">
                            <a href="{{ route('approval.izin.hod') }}" @if(request()->routeIs('approval.izin.hod')) aria-current="page" @endif class="{{ ($approvalHodCounts['izin'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                                <span class="app-nav-label">{{ __('navigation.permission_paid_unpaid') }}</span>
                                @if(($approvalHodCounts['izin'] ?? 0) > 0)
                                    <span class="badge badge-secondary">{{ $approvalHodCounts['izin'] }}</span>
                                @endif
                            </a>
                        </li>

                        <li class="{{ request()->routeIs('approval.roster.hod') ? 'active' : '' }}">
                            <a href="{{ route('approval.roster.hod') }}" @if(request()->routeIs('approval.roster.hod')) aria-current="page" @endif class="{{ ($approvalHodCounts['roster'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                                <span class="app-nav-label">{{ __('navigation.roster_leave') }}</span>
                                @if(($approvalHodCounts['roster'] ?? 0) > 0)
                                    <span class="badge badge-warning">{{ $approvalHodCounts['roster'] }}</span>
                                @endif
                            </a>
                        </li>

                        <li class="{{ request()->routeIs('approval.roster-off.hod') ? 'active' : '' }}">
                            <a href="{{ route('approval.roster-off.hod') }}" @if(request()->routeIs('approval.roster-off.hod')) aria-current="page" @endif class="{{ ($approvalHodCounts['roster_off'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                                <span class="app-nav-label">{{ __('navigation.roster_off') }}</span>
                                @if(($approvalHodCounts['roster_off'] ?? 0) > 0)
                                    <span class="badge badge-info">{{ $approvalHodCounts['roster_off'] }}</span>
                                @endif
                            </a>
                        </li>

                        <li class="{{ request()->routeIs('approval.attendance-corrections.hod') ? 'active' : '' }}">
                            <a href="{{ route('approval.attendance-corrections.hod') }}" @if(request()->routeIs('approval.attendance-corrections.hod')) aria-current="page" @endif class="{{ ($approvalHodCounts['attendance_correction'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                                <span class="app-nav-label">{{ __('navigation.attendance_correction') }}</span>
                                @if(($approvalHodCounts['attendance_correction'] ?? 0) > 0)
                                    <span class="badge badge-danger">{{ $approvalHodCounts['attendance_correction'] }}</span>
                                @endif
                            </a>
                        </li>
                    @endif

                    @if($can('approval_hr'))
                        <li class="app-nav-subheading">{{ __('navigation.approval_hr') }}</li>

                        <li class="{{ request()->routeIs('approval.cuti.hrd') ? 'active' : '' }}">
                            <a href="{{ route('approval.cuti.hrd') }}" @if(request()->routeIs('approval.cuti.hrd')) aria-current="page" @endif class="{{ ($approvalHrCounts['cuti'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                                <span class="app-nav-label">{{ __('navigation.annual_leave') }}</span>
                                @if(($approvalHrCounts['cuti'] ?? 0) > 0)
                                    <span class="badge badge-primary">{{ $approvalHrCounts['cuti'] }}</span>
                                @endif
                            </a>
                        </li>

                        <li class="{{ request()->routeIs('approval.izin.hrd') ? 'active' : '' }}">
                            <a href="{{ route('approval.izin.hrd') }}" @if(request()->routeIs('approval.izin.hrd')) aria-current="page" @endif class="{{ ($approvalHrCounts['izin'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                                <span class="app-nav-label">{{ __('navigation.permission_paid_unpaid') }}</span>
                                @if(($approvalHrCounts['izin'] ?? 0) > 0)
                                    <span class="badge badge-secondary">{{ $approvalHrCounts['izin'] }}</span>
                                @endif
                            </a>
                        </li>

                        <li class="{{ request()->routeIs('approval.roster.hrd') ? 'active' : '' }}">
                            <a href="{{ route('approval.roster.hrd') }}" @if(request()->routeIs('approval.roster.hrd')) aria-current="page" @endif class="{{ ($approvalHrCounts['roster'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                                <span class="app-nav-label">{{ __('navigation.roster_leave') }}</span>
                                @if(($approvalHrCounts['roster'] ?? 0) > 0)
                                    <span class="badge badge-warning">{{ $approvalHrCounts['roster'] }}</span>
                                @endif
                            </a>
                        </li>

                        <li class="{{ request()->routeIs('approval.roster-off.hrd') ? 'active' : '' }}">
                            <a href="{{ route('approval.roster-off.hrd') }}" @if(request()->routeIs('approval.roster-off.hrd')) aria-current="page" @endif class="{{ ($approvalHrCounts['roster_off'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                                <span class="app-nav-label">{{ __('navigation.roster_off') }}</span>
                                @if(($approvalHrCounts['roster_off'] ?? 0) > 0)
                                    <span class="badge badge-info">{{ $approvalHrCounts['roster_off'] }}</span>
                                @endif
                            </a>
                        </li>

                        <li class="{{ request()->routeIs('approval.attendance-corrections.hrd') ? 'active' : '' }}">
                            <a href="{{ route('approval.attendance-corrections.hrd') }}" @if(request()->routeIs('approval.attendance-corrections.hrd')) aria-current="page" @endif class="{{ ($approvalHrCounts['attendance_correction'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                                <span class="app-nav-label">{{ __('navigation.attendance_correction') }}</span>
                                @if(($approvalHrCounts['attendance_correction'] ?? 0) > 0)
                                    <span class="badge badge-danger">{{ $approvalHrCounts['attendance_correction'] }}</span>
                                @endif
                            </a>
                        </li>
                    @endif

                    @if($canUseDelegateApproval)

                        <li class="{{ request()->routeIs('approval.delegate.*') ? 'active' : '' }}">
                            <a href="{{ route('approval.delegate.index', ['module' => 'cuti']) }}" @if(request()->routeIs('approval.delegate.*')) aria-current="page" @endif class="{{ ($approvalDelegateCounts['total'] ?? 0) > 0 ? 'has-sidebar-badge' : '' }}">
                                <span class="app-nav-label">{{ __('navigation.delegated_approval') }}</span>
                                @if(($approvalDelegateCounts['total'] ?? 0) > 0)
                                    <span class="badge badge-primary">{{ $approvalDelegateCounts['total'] }}</span>
                                @endif
                            </a>
                        </li>
                    @endif
                @endcomponent

                @component('partials.sidebar-group', [
                    'id' => 'sidebar-requests', 'label' => __('navigation.my_requests'), 'icon' => 'fas fa-paper-plane',
                    'active' => request()->routeIs('cuti.*', 'roster.*', 'roster-off.*', 'izin.*', 'attendance-corrections.*', 'lembur.*')
                ])
                    @if($can('cuti'))
                        <li class="{{ request()->routeIs('cuti.*') ? 'active' : '' }}">
                            <a href="{{ route('cuti.index') }}" @if(request()->routeIs('cuti.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.annual_leave') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('roster'))
                        <li class="{{ request()->routeIs('roster.*') ? 'active' : '' }}">
                            <a href="{{ route('roster.index') }}" @if(request()->routeIs('roster.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.roster_leave') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('off_roster') && $user->hasRole(['Staff Roster', 'Super Admin']))
                        <li class="{{ request()->routeIs('roster-off.*') ? 'active' : '' }}">
                            <a href="{{ route('roster-off.index') }}" @if(request()->routeIs('roster-off.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.roster_off_submission') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('izin'))
                        <li class="{{ request()->routeIs('izin.*') ? 'active' : '' }}">
                            <a href="{{ route('izin.index') }}" @if(request()->routeIs('izin.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.permission_paid_unpaid') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('attendance_correction'))
                        <li class="{{ request()->routeIs('attendance-corrections.*') ? 'active' : '' }}">
                            <a href="{{ route('attendance-corrections.index') }}" @if(request()->routeIs('attendance-corrections.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.attendance_correction') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('lembur') && !$canManageOvertimeOrders)
                        <li class="{{ request()->routeIs('lembur.*') ? 'active' : '' }}">
                            <a href="{{ route('lembur.index') }}" @if(request()->routeIs('lembur.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.my_overtime') }}</span>
                            </a>
                        </li>
                    @endif
                @endcomponent

                @if($can('slip_gaji_user'))
                <li class="nav-item {{ request()->routeIs('slipgaji.*') ? 'active' : '' }}">
                    <a href="{{ route('slipgaji.index') }}" @if(request()->routeIs('slipgaji.*')) aria-current="page" @endif aria-label="{{ __('navigation.my_salary_slip') }}" title="{{ __('navigation.my_salary_slip') }}">
                        <i class="fas fa-file-invoice-dollar" aria-hidden="true"></i>
                        <p>{{ __('navigation.my_salary_slip') }}</p>
                    </a>
                </li>
                @endif

                @if($can('electronic_contract_user'))
                <li class="nav-item {{ request()->routeIs('user-electronic-contracts.*') ? 'active' : '' }}">
                    <a href="{{ route('user-electronic-contracts.index') }}" @if(request()->routeIs('user-electronic-contracts.*')) aria-current="page" @endif aria-label="{{ __('navigation.my_contract') }}" title="{{ __('navigation.my_contract') }}">
                        <i class="fas fa-file-signature" aria-hidden="true"></i>
                        <p>{{ __('navigation.my_contract') }}</p>
                    </a>
                </li>
                @endif

                @component('partials.sidebar-group', [
                    'id' => 'sidebar-employees', 'label' => __('navigation.employees'), 'icon' => 'fas fa-users',
                    'active' => request()->routeIs('karyawan.*', 'cv-maker-compare.*', 'employee-movements.*', 'resign.*', 'surat-peringatan.*', 'organization-structure.*'), 'badge' => $canUseEmployeeMovement ? (($approvalHodCounts['employee_movement'] ?? 0) + ($approvalHrCounts['employee_movement'] ?? 0)) : 0
                ])
                    @if($can('data_karyawan'))
                        <li class="{{ request()->routeIs('karyawan.*') ? 'active' : '' }}">
                            <a href="{{ route('karyawan.index') }}" @if(request()->routeIs('karyawan.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.employee_data') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('cv_maker_compare'))
                        <li class="{{ $cvMakerCompareActive ? 'active' : '' }}">
                            <a href="{{ route('cv-maker-compare.index') }}" @if($cvMakerCompareActive) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.cv_maker_compare') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($canUseEmployeeMovement)
                        <li class="{{ $employeeMovementActive ? 'active' : '' }}">
                            <a href="{{ route('employee-movements.index') }}" @if($employeeMovementActive) aria-current="page" @endif class="{{ (($approvalHodCounts['employee_movement'] ?? 0) + ($approvalHrCounts['employee_movement'] ?? 0)) > 0 ? 'has-sidebar-badge' : '' }}">
                                <span class="app-nav-label">{{ __('navigation.employee_movement') }}</span>
                                @php($employeeMovementBadge = ($approvalHodCounts['employee_movement'] ?? 0) + ($approvalHrCounts['employee_movement'] ?? 0))
                                @if($employeeMovementBadge > 0)
                                    <span class="badge badge-primary">{{ $employeeMovementBadge }}</span>
                                @endif
                            </a>
                        </li>
                    @endif

                    @if($can('organization_structure'))
                        <li class="{{ request()->routeIs('organization-structure.chart') ? 'active' : '' }}">
                            <a href="{{ route('organization-structure.chart') }}" @if(request()->routeIs('organization-structure.chart')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.organization_chart') }}</span>
                            </a>
                        </li>
                        @if($user->hasRole(['Super Admin', 'HR']))
                            <li class="{{ request()->routeIs('organization-structure.index') ? 'active' : '' }}">
                                <a href="{{ route('organization-structure.index') }}" @if(request()->routeIs('organization-structure.index')) aria-current="page" @endif>
                                    <span class="app-nav-label">{{ __('navigation.organization_master') }}</span>
                                </a>
                            </li>
                        @endif
                    @endif

                    @if($can('surat_peringatan'))
                        <li class="{{ request()->routeIs('surat-peringatan.*') ? 'active' : '' }}">
                            <a href="{{ route('surat-peringatan.index') }}" @if(request()->routeIs('surat-peringatan.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.violation') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('resign'))
                        <li class="{{ request()->routeIs('resign.*') ? 'active' : '' }}">
                            <a href="{{ route('resign.index') }}" @if(request()->routeIs('resign.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.resignation') }}</span>
                            </a>
                        </li>
                    @endif
                @endcomponent

                @component('partials.sidebar-group', [
                    'id' => 'sidebar-attendance', 'label' => __('navigation.attendance_schedule'), 'icon' => 'fas fa-calendar-alt',
                    'active' => request()->routeIs('data-presensi.*', 'attendance-anomalies.*', 'set-kehadiran.*', 'national-holidays.*', 'work-patterns.*', 'roster-schedules.*', 'shifts.*', 'shift-settings.*', 'overtime-orders.*', 'overtime-masters.*', 'leave-balances.*', 'attendance-period-locks.*')
                ])
                    @if($can('data_presensi'))
                        <li class="{{ request()->routeIs('data-presensi.*') ? 'active' : '' }}">
                            <a href="{{ route('data-presensi.index') }}" @if(request()->routeIs('data-presensi.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.attendance_data') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('attendance_anomaly'))
                        <li class="{{ $attendanceAnomalyActive ? 'active' : '' }}">
                            <a href="{{ route('attendance-anomalies.index') }}" @if($attendanceAnomalyActive) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.attendance_anomaly') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('roster_schedule'))
                        <li class="{{ request()->routeIs('roster-schedules.*') ? 'active' : '' }}">
                            <a href="{{ route('roster-schedules.index') }}" @if(request()->routeIs('roster-schedules.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.roster_schedule') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('master_shift') || $can('pengaturan_shift'))
                        @if($can('master_shift'))
                            <li class="{{ request()->routeIs('shifts.*') ? 'active' : '' }}">
                                <a href="{{ route('shifts.index') }}" @if(request()->routeIs('shifts.*')) aria-current="page" @endif>
                                    <span class="app-nav-label">{{ __('navigation.shift_master') }}</span>
                                </a>
                            </li>
                        @endif

                        @if($can('pengaturan_shift'))
                            <li class="{{ request()->routeIs('shift-settings.*') ? 'active' : '' }}">
                                <a href="{{ route('shift-settings.index') }}" @if(request()->routeIs('shift-settings.*')) aria-current="page" @endif>
                                    <span class="app-nav-label">{{ __('navigation.shift_setting') }}</span>
                                </a>
                            </li>
                        @endif
                    @endif

                    @if($can('jadwal_kerja'))
                        <li class="{{ request()->routeIs('work-patterns.*') ? 'active' : '' }}">
                            <a href="{{ route('work-patterns.index') }}" @if(request()->routeIs('work-patterns.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.work_pattern_master') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('setting_hari_off'))
                        <li class="{{ request()->routeIs('set-kehadiran.*') ? 'active' : '' }}">
                            <a href="{{ route('set-kehadiran.index') }}" @if(request()->routeIs('set-kehadiran.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.day_off_setting') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('master_tanggal_merah'))
                        <li class="{{ request()->routeIs('national-holidays.*') ? 'active' : '' }}">
                            <a href="{{ route('national-holidays.index') }}" @if(request()->routeIs('national-holidays.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.holiday_master') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('lembur') && ($canManageOvertimeOrders || $canManageOvertimeMaster))
                        @if($canManageOvertimeOrders)
                            <li class="{{ request()->routeIs('overtime-orders.*') ? 'active' : '' }}">
                                <a href="{{ route('overtime-orders.index') }}" @if(request()->routeIs('overtime-orders.*')) aria-current="page" @endif>
                                    <span class="app-nav-label">{{ __('navigation.overtime_order') }}</span>
                                </a>
                            </li>
                        @endif

                        @if($canManageOvertimeMaster)
                            <li class="{{ request()->routeIs('overtime-masters.*') ? 'active' : '' }}">
                                <a href="{{ route('overtime-masters.index') }}" @if(request()->routeIs('overtime-masters.*')) aria-current="page" @endif>
                                    <span class="app-nav-label">{{ __('navigation.overtime_master') }}</span>
                                </a>
                            </li>
                        @endif
                    @endif

                    @if($can('leave_balance'))
                        <li class="{{ request()->routeIs('leave-balances.*') ? 'active' : '' }}">
                            <a href="{{ route('leave-balances.index') }}" @if(request()->routeIs('leave-balances.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.leave_balance') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('attendance_period_lock'))
                        <li class="{{ $attendancePeriodLockActive ? 'active' : '' }}">
                            <a href="{{ route('attendance-period-locks.index') }}" @if($attendancePeriodLockActive) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.attendance_period_lock') }}</span>
                            </a>
                        </li>
                    @endif
                @endcomponent

                @component('partials.sidebar-group', [
                    'id' => 'sidebar-contracts', 'label' => __('navigation.contracts_payroll'), 'icon' => 'fas fa-file-contract',
                    'active' => request()->routeIs('electronic-contracts.*', 'contract-renewals.*', 'slip-gaji.*', 'employee-salaries.*', 'payroll.*', 'salary-components.*')
                ])
                    @if($can('electronic_contract_admin'))
                        <li class="{{ request()->routeIs('electronic-contracts.*') && !request()->routeIs('electronic-contracts.templates.*', 'electronic-contracts.clauses.*', 'electronic-contracts.first-party-signature.*') ? 'active' : '' }}">
                            <a href="{{ route('electronic-contracts.index') }}" @if(request()->routeIs('electronic-contracts.*') && !request()->routeIs('electronic-contracts.templates.*', 'electronic-contracts.clauses.*', 'electronic-contracts.first-party-signature.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.electronic_contract') }}</span>
                            </a>
                        </li>
                        <li class="{{ request()->routeIs('electronic-contracts.templates.*') ? 'active' : '' }}">
                            <a href="{{ route('electronic-contracts.templates.index') }}" @if(request()->routeIs('electronic-contracts.templates.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.electronic_contract_templates') }}</span>
                            </a>
                        </li>
                        <li class="{{ request()->routeIs('electronic-contracts.clauses.*') ? 'active' : '' }}">
                            <a href="{{ route('electronic-contracts.clauses.index') }}" @if(request()->routeIs('electronic-contracts.clauses.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.electronic_contract_clauses') }}</span>
                            </a>
                        </li>
                        @if($canManageFirstPartySignature)
                            <li class="{{ request()->routeIs('electronic-contracts.first-party-signature.*') ? 'active' : '' }}">
                                <a href="{{ route('electronic-contracts.first-party-signature.edit') }}" @if(request()->routeIs('electronic-contracts.first-party-signature.*')) aria-current="page" @endif>
                                    <span class="app-nav-label">{{ __('navigation.electronic_contract_first_party_signature') }}</span>
                                </a>
                            </li>
                        @endif
                    @endif

                    @if($can('contract_renewal'))
                        <li class="{{ $contractRenewalDashboardActive ? 'active' : '' }}">
                            <a href="{{ route('contract-renewals.dashboard') }}" @if($contractRenewalDashboardActive) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.contract_monitoring') }}</span>
                            </a>
                        </li>
                        <li class="{{ $contractRenewalWorkflowActive ? 'active' : '' }}">
                            <a href="{{ route('contract-renewals.index') }}" @if($contractRenewalWorkflowActive) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.contract_renewal_workflow') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('slip_gaji_admin'))
                        <li class="{{ request()->routeIs('slip-gaji.*') ? 'active' : '' }}">
                            <a href="{{ route('slip-gaji.index') }}" @if(request()->routeIs('slip-gaji.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.salary_slip') }}</span>
                            </a>
                        </li>
                        @if($user->hasRole(['Super Admin', 'HR']))
                            <li class="{{ request()->routeIs('payroll.*') ? 'active' : '' }}">
                                <a href="{{ route('payroll.index') }}" @if(request()->routeIs('payroll.*')) aria-current="page" @endif><span class="app-nav-label">Proses Payroll</span></a>
                            </li>
                            <li class="{{ request()->routeIs('salary-components.*') ? 'active' : '' }}">
                                <a href="{{ route('salary-components.index') }}"><span class="app-nav-label">Master Komponen Gaji</span></a>
                            </li>
                            <li class="{{ request()->routeIs('employee-salaries.*') ? 'active' : '' }}">
                                <a href="{{ route('employee-salaries.index') }}" @if(request()->routeIs('employee-salaries.*')) aria-current="page" @endif><span class="app-nav-label">{{ __('navigation.salary_update') }}</span></a>
                            </li>
                        @endif
                    @endif
                @endcomponent

                @component('partials.sidebar-group', [
                    'id' => 'sidebar-monitoring', 'label' => __('navigation.reports_monitoring'), 'icon' => 'fas fa-chart-line',
                    'active' => request()->routeIs('central-monitor.*', 'cv-maker-dashboard.*', 'approval-sla.*', 'distribusi.*')
                ])
                    @if($can('central_monitor'))
                        <li class="{{ $centralMonitorActive ? 'active' : '' }}">
                            <a href="{{ route('central-monitor.index') }}" @if($centralMonitorActive) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.central_monitor') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('cv_maker_dashboard'))
                        <li class="{{ request()->routeIs('cv-maker-dashboard.*') ? 'active' : '' }}">
                            <a href="{{ route('cv-maker-dashboard.index') }}" @if(request()->routeIs('cv-maker-dashboard.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.cv_maker_dashboard') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('approval_sla'))

                        <li class="{{ $approvalSlaActive ? 'active' : '' }}">
                            <a href="{{ route('approval-sla.index') }}" @if($approvalSlaActive) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.approval_sla') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('distribusi_wilayah'))
                        <li class="{{ request()->routeIs('distribusi.*') ? 'active' : '' }}">
                            <a href="{{ route('distribusi.index') }}" @if(request()->routeIs('distribusi.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.area_distribution') }}</span>
                            </a>
                        </li>
                    @endif
                @endcomponent

                @component('partials.sidebar-group', [
                    'id' => 'sidebar-settings', 'label' => __('navigation.settings'), 'icon' => 'fas fa-cog',
                    'active' => request()->routeIs('user.*', 'perusahaan.*', 'setting-lokasi-presensi.*', 'setting-role.*', 'audit-trails.*', 'import-histories.*', 'search-by-security.*', 'search-logs.*', 'deleted-data.*')
                ])
                    @if($can('data_user'))
                        <li class="{{ request()->routeIs('user.*') ? 'active' : '' }}">
                            <a href="{{ route('user.index') }}" @if(request()->routeIs('user.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.user_data') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('perusahaan'))
                        <li class="{{ request()->routeIs('perusahaan.*') ? 'active' : '' }}">
                            <a href="{{ route('perusahaan.index') }}" @if(request()->routeIs('perusahaan.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.company') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('setting_lokasi_presensi'))
                        <li class="{{ request()->routeIs('setting-lokasi-presensi.*') ? 'active' : '' }}">
                            <a href="{{ route('setting-lokasi-presensi.index') }}" @if(request()->routeIs('setting-lokasi-presensi.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.attendance_location') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('setting_role'))
                        <li class="{{ request()->routeIs('setting-role.*') ? 'active' : '' }}">
                            <a href="{{ route('setting-role.index') }}" @if(request()->routeIs('setting-role.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.role_access') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($user->hasRole(['Super Admin', 'HR']) && ($can('data_karyawan') || $can('perusahaan') || $can('slip_gaji_admin')))
                        <li class="{{ request()->routeIs('deleted-data.*') ? 'active' : '' }}"><a href="{{ route('deleted-data.index') }}"><span class="app-nav-label">Tempat Sampah</span></a></li>
                    @endif
                    @if($can('audit_trail'))
                        <li class="{{ request()->routeIs('audit-trails.*') ? 'active' : '' }}">
                            <a href="{{ route('audit-trails.index') }}" @if(request()->routeIs('audit-trails.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.audit_trail') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('import_history'))
                        <li class="{{ request()->routeIs('import-histories.*') ? 'active' : '' }}">
                            <a href="{{ route('import-histories.index') }}" @if(request()->routeIs('import-histories.*')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.import_history') }}</span>
                            </a>
                        </li>
                    @endif

                    @if($can('exit_portal'))
                        <li class="{{ request()->routeIs('search-by-security.index') ? 'active' : '' }}">
                            <a href="{{ route('search-by-security.index') }}" @if(request()->routeIs('search-by-security.index')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.exit_portal_users') }}</span>
                            </a>
                        </li>

                        <li class="{{ request()->routeIs('search-logs.index') ? 'active' : '' }}">
                            <a href="{{ route('search-logs.index') }}" @if(request()->routeIs('search-logs.index')) aria-current="page" @endif>
                                <span class="app-nav-label">{{ __('navigation.exit_portal_history') }}</span>
                            </a>
                        </li>
                    @endif
                @endcomponent

            </ul>
@if($serviceDirectory ?? false)
</nav>
@else
        </div>
    </div>
</div>
@endif
