@extends('layouts.app')

@push('styles')
<style>
    .access-group {
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        background: #ffffff;
        padding: 12px 14px;
    }

    .access-group__header {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: 0.76rem;
        font-weight: 700;
        letter-spacing: 0.01em;
        margin-bottom: 10px;
    }

    .access-group__count {
        opacity: 0.8;
        font-weight: 600;
    }

    .access-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.38rem 0.72rem;
        border-radius: 999px;
        font-size: 0.74rem;
        font-weight: 600;
        border: 1px solid transparent;
    }

    .access-group--dashboard .access-group__header {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .access-group--dashboard .access-badge {
        background: #eff6ff;
        color: #1e40af;
        border-color: #bfdbfe;
    }

    .access-group--data-master .access-group__header {
        background: #dcfce7;
        color: #15803d;
    }

    .access-group--data-master .access-badge {
        background: #f0fdf4;
        color: #166534;
        border-color: #bbf7d0;
    }

    .access-group--self-service .access-group__header {
        background: #fef3c7;
        color: #b45309;
    }

    .access-group--self-service .access-badge {
        background: #fffbeb;
        color: #b45309;
        border-color: #fde68a;
    }

    .access-group--approval .access-group__header {
        background: #ffe4e6;
        color: #be123c;
    }

    .access-group--approval .access-badge {
        background: #fff1f2;
        color: #be123c;
        border-color: #fecdd3;
    }

    .access-group--operasional .access-group__header {
        background: #ccfbf1;
        color: #0f766e;
    }

    .access-group--operasional .access-badge {
        background: #f0fdfa;
        color: #0f766e;
        border-color: #99f6e4;
    }

    .access-group--admin-panel .access-group__header {
        background: #e0f2fe;
        color: #0369a1;
    }

    .access-group--admin-panel .access-badge {
        background: #f0f9ff;
        color: #075985;
        border-color: #bae6fd;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        @php
            $currentRole = $user->role;
            $currentMenuKeys = $user->resolveMenuPermissions();
            $currentScopeLabel = $currentRole ? $currentRole->scope_label : __('access.setting_role.not_selected');
            $selectedRoleId = (string) old('role_id', $user->role_id);
            $selectedAdditionalRoleIds = collect(old('additional_role_ids', $selectedAdditionalRoleIds ?? []))
                ->map(fn($id) => (string) $id)
                ->all();
        @endphp

        <h4 class="fw-bold mb-4">
            <i class="fas fa-user-cog text-primary me-2"></i>
            {{ __('access.setting_role.edit_title') }}
        </h4>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">

                <form action="{{ route('setting-role.update', $user->id) }}"
                    method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label">NIK</label>
                        <input type="text"
                            class="form-control"
                            value="{{ $user->nik_karyawan }}"
                            readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="text"
                            class="form-control"
                            value="{{ $user->email }}"
                            readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Role Saat Ini</label>
                        <input type="text"
                            class="form-control"
                            value="{{ $user->display_role_name }}"
                            readonly>
                    </div>

                    <div class="card border-0 bg-light mb-3">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                <div>
                                    <h6 class="fw-semibold mb-1">{{ __('access.setting_role.current_access') }}</h6>
                                    <small class="text-muted">{{ __('access.setting_role.current_access_help') }}</small>
                                </div>
                                <span class="badge bg-primary">{{ $user->display_role_name }}</span>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label text-muted small mb-1">{{ __('access.setting_role.scope_data') }}</label>
                                    <div class="fw-semibold">{{ $currentScopeLabel }}</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-muted small mb-1">{{ __('access.setting_role.active_menu_count') }}</label>
                                    <div class="fw-semibold">{{ __('access.setting_role.menu_count', ['count' => count($currentMenuKeys)]) }}</div>
                                </div>
                            </div>

                            @if($user->isHodRole())
                                <div class="mb-3">
                                    <label class="form-label text-muted small mb-2">{{ __('access.setting_role.accessible_departments') }}</label>
                                    <div>
                                        @forelse($assignedDepartemens as $departemen)
                                            <span class="badge bg-primary me-1 mb-1">{{ $departemen->departemen }}</span>
                                        @empty
                                            <span class="text-muted small">{{ __('access.setting_role.no_departments') }}</span>
                                        @endforelse
                                    </div>
                                </div>
                            @endif

                            @if($user->isHodRole() || $user->isAdminDivisiRole())
                                <div class="mb-3">
                                    <label class="form-label text-muted small mb-2">{{ __('access.setting_role.accessible_divisions') }}</label>
                                    <div>
                                        @forelse($assignedDivisis as $divisi)
                                            <span class="badge bg-secondary me-1 mb-1">{{ $divisi->nama_divisi }}</span>
                                        @empty
                                            <span class="text-muted small">{{ __('access.setting_role.no_divisions') }}</span>
                                        @endforelse
                                    </div>
                                </div>
                            @endif

                            <div>
                                <label class="form-label text-muted small mb-2">{{ __('access.setting_role.active_menu') }}</label>
                                <div id="current-access-summary">
                                    @forelse($menuGroups as $group => $menus)
                                        @php
                                            $activeMenus = collect($menus)->filter(fn($menu) => in_array($menu['key'], $currentMenuKeys, true));
                                            $groupStyle = $activeMenus->first()['group_style'] ?? 'dashboard';
                                            $groupLabel = $activeMenus->first()['group_label'] ?? $group;
                                        @endphp
                                        @if($activeMenus->isNotEmpty())
                                            <div class="access-group access-group--{{ $groupStyle }} mb-2">
                                                <div class="access-group__header">
                                                    <span>{{ $groupLabel }}</span>
                                                    <span class="access-group__count">{{ $activeMenus->count() }}</span>
                                                </div>
                                                <div>
                                                    @foreach($activeMenus as $menu)
                                                        <span class="access-badge me-1 mb-1">{{ $menu['label'] }}</span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    @empty
                                        <div class="text-muted small">{{ __('access.setting_role.empty_active_menu') }}</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            {{ __('access.setting_role.role_permission') }}
                        </label>

                        <select name="role_id"
                            id="role_id"
                            class="form-select @error('role_id') is-invalid @enderror"
                            required>
                            <option value="">{{ __('access.setting_role.select_role') }}</option>
                            @foreach($roles as $role)
                            <option value="{{ $role->id }}"
                                data-normalized-role="{{ $role->normalized_name }}"
                                {{ (string) old('role_id', $user->role_id) === (string) $role->id ? 'selected' : '' }}>
                                {{ $role->permission_role }}
                            </option>
                            @endforeach
                        </select>

                        @error('role_id')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            {{ __('access.setting_role.additional_role') }}
                        </label>

                        <select name="additional_role_ids[]"
                            id="additional_role_ids"
                            class="form-select @error('additional_role_ids') is-invalid @enderror"
                            multiple
                            size="7">
                            @foreach($roles as $role)
                            <option value="{{ $role->id }}"
                                data-normalized-role="{{ $role->normalized_name }}"
                                {{ in_array((string) $role->id, $selectedAdditionalRoleIds, true) ? 'selected' : '' }}>
                                {{ $role->permission_role }}
                            </option>
                            @endforeach
                        </select>

                        @error('additional_role_ids')
                        <div class="invalid-feedback d-block">
                            {{ $message }}
                        </div>
                        @enderror
                        @error('additional_role_ids.*')
                        <div class="invalid-feedback d-block">
                            {{ $message }}
                        </div>
                        @enderror
                        <small class="text-muted d-block mt-2">
                            {{ __('access.setting_role.additional_role_help') }}
                        </small>
                    </div>

                    <div class="card border-0 bg-light mb-3">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                <div>
                                    <h6 class="fw-semibold mb-1">{{ __('access.setting_role.selected_role_preview') }}</h6>
                                    <small class="text-muted">{{ __('access.setting_role.selected_role_preview_help') }}</small>
                                </div>
                                <span id="selected-role-badge" class="badge bg-info text-dark">{{ optional($roles->firstWhere('id', $selectedRoleId))->permission_role ?? $user->display_role_name }}</span>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-muted small mb-1">{{ __('access.setting_role.scope_data') }}</label>
                                    <div id="selected-role-scope" class="fw-semibold">
                                        {{ optional($roles->firstWhere('id', $selectedRoleId))->scope_label ?? $currentScopeLabel }}
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-muted small mb-1">{{ __('access.setting_role.menu_total') }}</label>
                                    <div id="selected-role-count" class="fw-semibold">
                                        {{ __('access.setting_role.menu_count', ['count' => count(optional($roles->firstWhere('id', $selectedRoleId))->resolved_menu_permissions ?? $currentMenuKeys)]) }}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3" id="selected-role-menu-groups"></div>
                        </div>
                    </div>

                    <div id="role-scope-card" class="card border-0 bg-light mb-3 d-none">
                        <div class="card-body">
                            <h6 class="fw-semibold mb-3">{{ __('access.setting_role.additional_scope_access') }}</h6>

                            <div class="mb-3">
                                <label class="form-label">{{ __('access.setting_role.employee_default_division') }}</label>
                                <input type="text"
                                    class="form-control"
                                    value="{{ optional(optional($user->employee)->divisi)->nama_divisi ?? '-' }}"
                                    readonly>
                                <small class="text-muted">{{ __('access.setting_role.employee_default_division_help') }}</small>
                            </div>

                            <div id="hod-departemen-scope-section" class="mb-3 d-none">
                                <label class="form-label">{{ __('access.setting_role.hod_extra_departments') }}</label>
                                @php
                                    $selectedAuthorizedDepartemens = collect(old('authorized_departemen_ids', $user->authorized_departemen_ids ?? []))
                                        ->map(fn($id) => (string) $id)
                                        ->all();
                                @endphp
                                <select
                                    name="authorized_departemen_ids[]"
                                    id="authorized_departemen_ids"
                                    class="form-select @error('authorized_departemen_ids') is-invalid @enderror"
                                    multiple
                                    size="8">
                                    @foreach($departemens->groupBy(fn($departemen) => optional($departemen->perusahaan)->kode_perusahaan ?: optional($departemen->perusahaan)->nama_perusahaan ?: 'Perusahaan') as $companyName => $companyDepartemens)
                                        <optgroup label="{{ $companyName }}">
                                            @foreach($companyDepartemens as $departemen)
                                                <option value="{{ $departemen->id }}"
                                                    {{ in_array((string) $departemen->id, $selectedAuthorizedDepartemens, true) ? 'selected' : '' }}>
                                                    {{ $departemen->departemen }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                @error('authorized_departemen_ids')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                @error('authorized_departemen_ids.*')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <small class="text-muted d-block mt-2">
                                    {{ __('access.setting_role.hod_extra_departments_help') }}
                                </small>
                            </div>

                            <div id="divisi-scope-section">
                                <label class="form-label">{{ __('access.setting_role.extra_divisions') }}</label>
                                @php
                                    $selectedAuthorizedDivisis = collect(old('authorized_divisi_ids', $user->authorized_divisi_ids ?? []))
                                        ->map(fn($id) => (string) $id)
                                        ->all();
                                @endphp
                                <select
                                    name="authorized_divisi_ids[]"
                                    id="authorized_divisi_ids"
                                    class="form-select @error('authorized_divisi_ids') is-invalid @enderror"
                                    multiple
                                    size="12">
                                    @foreach($departemens as $departemen)
                                        <optgroup label="{{ $departemen->departemen }}">
                                            @foreach($departemen->divisi as $divisi)
                                                <option value="{{ $divisi->id }}"
                                                    {{ in_array((string) $divisi->id, $selectedAuthorizedDivisis, true) ? 'selected' : '' }}>
                                                    {{ $divisi->nama_divisi }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                @error('authorized_divisi_ids')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                @error('authorized_divisi_ids.*')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <small class="text-muted d-block mt-2">
                                    {{ __('access.setting_role.extra_divisions_help') }}
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-light border small">
                        {{ __('access.setting_role.scope_rule_help') }}
                    </div>

                    <div class="d-flex justify-content-left">
                        <button type="submit"
                            class="btn btn-primary me-2">
                            {{ __('access.setting_role.update_role') }}
                        </button>

                        <a href="{{ route('setting-role.index') }}"
                            class="btn btn-secondary">
                            {{ __('access.setting_role.back') }}
                        </a>
                    </div>

                </form>

            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        const roleSelect = $('#role_id');
        const additionalRoleSelect = $('#additional_role_ids');
        const roleScopeCard = $('#role-scope-card');
        const hodDepartemenScopeSection = $('#hod-departemen-scope-section');
        const authorizedDepartemenSelect = $('#authorized_departemen_ids');
        const authorizedDivisiSelect = $('#authorized_divisi_ids');
        const selectedRoleBadge = $('#selected-role-badge');
        const selectedRoleScope = $('#selected-role-scope');
        const selectedRoleCount = $('#selected-role-count');
        const selectedRoleMenuGroups = $('#selected-role-menu-groups');
        const roleAccessMap = @json($roleAccessMap);
        const menuGroups = @json($menuGroups);
        const menuCountTemplate = @json(__('access.setting_role.menu_count', ['count' => '__COUNT__']));
        const notSelectedText = @json(__('access.setting_role.not_selected'));
        const selectRoleForPreviewText = @json(__('access.setting_role.select_role_for_preview'));
        const roleWithoutMenuText = @json(__('access.setting_role.role_without_menu'));
        const emptyActiveMenuText = @json(__('access.setting_role.empty_active_menu'));

        function renderRolePreview() {
            const selectedRoleId = roleSelect.val();
            const selectedRoleIds = [selectedRoleId]
                .concat(additionalRoleSelect.val() || [])
                .filter((roleId, index, roleIds) => roleId && roleIds.indexOf(roleId) === index);
            const selectedRoles = selectedRoleIds
                .map(roleId => roleAccessMap[roleId])
                .filter(Boolean);

            if (selectedRoles.length === 0) {
                selectedRoleBadge.text(notSelectedText);
                selectedRoleScope.text('-');
                selectedRoleCount.text(menuCountTemplate.replace('__COUNT__', '0'));
                selectedRoleMenuGroups.html(`<div class="text-muted small">${selectRoleForPreviewText}</div>`);
                return;
            }

            const mergedMenus = [...new Set(selectedRoles.flatMap(role => role.menus || []))];

            selectedRoleBadge.text(selectedRoles.map(role => role.name).join(' + '));
            selectedRoleScope.text(selectedRoles.map(role => role.scope_label || '-').join(' + '));
            selectedRoleCount.text(menuCountTemplate.replace('__COUNT__', mergedMenus.length));

            let html = '';

            Object.entries(menuGroups).forEach(([groupName, menus]) => {
                const activeMenus = menus.filter(menu => mergedMenus.includes(menu.key));
                const firstMenu = menus[0] || {};
                const styleKey = firstMenu.group_style || 'dashboard';
                const groupLabel = firstMenu.group_label || groupName;

                if (activeMenus.length === 0) {
                    return;
                }

                html += `<div class="access-group access-group--${styleKey} mb-2">`;
                html += `<div class="access-group__header">`;
                html += `<span>${groupLabel}</span>`;
                html += `<span class="access-group__count">${activeMenus.length}</span>`;
                html += `</div>`;
                html += `<div>`;
                activeMenus.forEach(menu => {
                    html += `<span class="access-badge me-1 mb-1">${menu.label}</span>`;
                });
                html += `</div></div>`;
            });

            selectedRoleMenuGroups.html(html || `<div class="text-muted small">${roleWithoutMenuText}</div>`);
        }

        function toggleRoleScope() {
            const selectedOption = roleSelect.find('option:selected');
            const normalizedRole = selectedOption.data('normalized-role');
            const isHod = normalizedRole === 'HOD';
            const isAdminDivisi = normalizedRole === 'Admin Divisi';
            const hasScopeFields = isHod || isAdminDivisi;

            roleScopeCard.toggleClass('d-none', !hasScopeFields);
            hodDepartemenScopeSection.toggleClass('d-none', !isHod);
            authorizedDepartemenSelect.prop('disabled', !isHod);
            authorizedDivisiSelect.prop('disabled', !hasScopeFields);
        }

        roleSelect.on('change', function() {
            toggleRoleScope();
            renderRolePreview();
        });

        additionalRoleSelect.on('change', function() {
            renderRolePreview();
        });

        toggleRoleScope();
        renderRolePreview();
    });
</script>
@endpush
