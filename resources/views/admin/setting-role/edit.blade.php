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
            $currentMenuKeys = $currentRole ? $currentRole->resolved_menu_permissions : [];
            $currentScopeLabel = $currentRole ? $currentRole->scope_label : 'Belum ada role';
            $selectedRoleId = (string) old('role_id', $user->role_id);
            $groupStyleMap = [
                'Dashboard' => 'dashboard',
                'Data Master' => 'data-master',
                'Self Service' => 'self-service',
                'Approval' => 'approval',
                'Operasional' => 'operasional',
                'Admin Panel' => 'admin-panel',
            ];
        @endphp

        <h4 class="fw-bold mb-4">
            <i class="fas fa-user-cog text-primary me-2"></i>
            Edit Permission Role
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
                                    <h6 class="fw-semibold mb-1">Akses User Saat Ini</h6>
                                    <small class="text-muted">Ringkasan hak akses yang aktif berdasarkan role user saat ini.</small>
                                </div>
                                <span class="badge bg-primary">{{ $user->display_role_name }}</span>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label text-muted small mb-1">Scope Data</label>
                                    <div class="fw-semibold">{{ $currentScopeLabel }}</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-muted small mb-1">Jumlah Menu Aktif</label>
                                    <div class="fw-semibold">{{ count($currentMenuKeys) }} menu</div>
                                </div>
                            </div>

                            @if($user->isAdminDivisiRole())
                                <div class="mb-3">
                                    <label class="form-label text-muted small mb-2">Divisi yang Bisa Diakses</label>
                                    <div>
                                        @forelse($assignedDivisis as $divisi)
                                            <span class="badge bg-secondary me-1 mb-1">{{ $divisi->nama_divisi }}</span>
                                        @empty
                                            <span class="text-muted small">Belum ada divisi yang ditugaskan.</span>
                                        @endforelse
                                    </div>
                                </div>
                            @endif

                            <div>
                                <label class="form-label text-muted small mb-2">Menu Aktif</label>
                                <div id="current-access-summary">
                                    @forelse($menuGroups as $group => $menus)
                                        @php
                                            $activeMenus = collect($menus)->filter(fn($menu) => in_array($menu['key'], $currentMenuKeys, true));
                                            $groupStyle = $groupStyleMap[$group] ?? 'dashboard';
                                        @endphp
                                        @if($activeMenus->isNotEmpty())
                                            <div class="access-group access-group--{{ $groupStyle }} mb-2">
                                                <div class="access-group__header">
                                                    <span>{{ $group }}</span>
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
                                        <div class="text-muted small">Belum ada menu aktif.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Role Permission
                        </label>

                        <select name="role_id"
                            id="role_id"
                            class="form-select @error('role_id') is-invalid @enderror"
                            required>
                            <option value="">-- Pilih Role --</option>
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

                    <div class="card border-0 bg-light mb-3">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                <div>
                                    <h6 class="fw-semibold mb-1">Preview Akses Role Terpilih</h6>
                                    <small class="text-muted">Preview ini membantu melihat akses yang akan dimiliki user setelah role diperbarui.</small>
                                </div>
                                <span id="selected-role-badge" class="badge bg-info text-dark">{{ optional($roles->firstWhere('id', $selectedRoleId))->permission_role ?? $user->display_role_name }}</span>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-muted small mb-1">Scope Data</label>
                                    <div id="selected-role-scope" class="fw-semibold">
                                        {{ optional($roles->firstWhere('id', $selectedRoleId))->scope_label ?? $currentScopeLabel }}
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-muted small mb-1">Jumlah Menu</label>
                                    <div id="selected-role-count" class="fw-semibold">
                                        {{ count(optional($roles->firstWhere('id', $selectedRoleId))->resolved_menu_permissions ?? $currentMenuKeys) }} menu
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3" id="selected-role-menu-groups"></div>
                        </div>
                    </div>

                    <div id="admin-divisi-scope-card" class="card border-0 bg-light mb-3 d-none">
                        <div class="card-body">
                            <h6 class="fw-semibold mb-3">Akses Tambahan Divisi</h6>

                            <div class="mb-3">
                                <label class="form-label">Divisi Bawaan Karyawan</label>
                                <input type="text"
                                    class="form-control"
                                    value="{{ optional(optional($user->employee)->divisi)->nama_divisi ?? '-' }}"
                                    readonly>
                                <small class="text-muted">Divisi bawaan ini tetap otomatis ikut terakses meskipun tidak dicentang di bawah.</small>
                            </div>

                            <div>
                                <label class="form-label">Divisi Tambahan yang Boleh Diakses</label>
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
                                    Gunakan `Ctrl`/`Cmd` atau tap beberapa pilihan untuk memilih lebih dari satu divisi.
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-light border small">
                        Pilih role baru sesuai kewenangan user. Scope data mengikuti role:
                        Super Admin/HR = semua data, HOD/Manager = departemen yang sama, Supervisor = divisi yang sama, Admin Divisi = satu atau beberapa divisi yang ditugaskan, Staff = akun sendiri.
                    </div>

                    <div class="d-flex justify-content-left">
                        <button type="submit"
                            class="btn btn-primary me-2">
                            Update Role
                        </button>

                        <a href="{{ route('setting-role.index') }}"
                            class="btn btn-secondary">
                            Kembali
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
        const adminDivisiScopeCard = $('#admin-divisi-scope-card');
        const authorizedDivisiSelect = $('#authorized_divisi_ids');
        const selectedRoleBadge = $('#selected-role-badge');
        const selectedRoleScope = $('#selected-role-scope');
        const selectedRoleCount = $('#selected-role-count');
        const selectedRoleMenuGroups = $('#selected-role-menu-groups');
        const roleAccessMap = @json($roleAccessMap);
        const menuGroups = @json($menuGroups);
        const groupStyleMap = @json($groupStyleMap);

        function renderRolePreview() {
            const selectedRoleId = roleSelect.val();
            const selectedRole = roleAccessMap[selectedRoleId];

            if (!selectedRole) {
                selectedRoleBadge.text('Belum dipilih');
                selectedRoleScope.text('-');
                selectedRoleCount.text('0 menu');
                selectedRoleMenuGroups.html('<div class="text-muted small">Pilih role terlebih dahulu untuk melihat preview akses.</div>');
                return;
            }

            selectedRoleBadge.text(selectedRole.name);
            selectedRoleScope.text(selectedRole.scope_label || '-');
            selectedRoleCount.text(`${selectedRole.menus.length} menu`);

            let html = '';

            Object.entries(menuGroups).forEach(([groupName, menus]) => {
                const activeMenus = menus.filter(menu => selectedRole.menus.includes(menu.key));
                const styleKey = groupStyleMap[groupName] || 'dashboard';

                if (activeMenus.length === 0) {
                    return;
                }

                html += `<div class="access-group access-group--${styleKey} mb-2">`;
                html += `<div class="access-group__header">`;
                html += `<span>${groupName}</span>`;
                html += `<span class="access-group__count">${activeMenus.length}</span>`;
                html += `</div>`;
                html += `<div>`;
                activeMenus.forEach(menu => {
                    html += `<span class="access-badge me-1 mb-1">${menu.label}</span>`;
                });
                html += `</div></div>`;
            });

            selectedRoleMenuGroups.html(html || '<div class="text-muted small">Role ini belum memiliki menu aktif.</div>');
        }

        function toggleAdminDivisiScope() {
            const selectedOption = roleSelect.find('option:selected');
            const normalizedRole = selectedOption.data('normalized-role');
            const isAdminDivisi = normalizedRole === 'Admin Divisi';

            adminDivisiScopeCard.toggleClass('d-none', !isAdminDivisi);
            authorizedDivisiSelect.prop('disabled', !isAdminDivisi);
        }

        roleSelect.on('change', function() {
            toggleAdminDivisiScope();
            renderRolePreview();
        });

        toggleAdminDivisiScope();
        renderRolePreview();
    });
</script>
@endpush
