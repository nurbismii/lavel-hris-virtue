@extends('layouts.app')

@push('styles')
<style>
    .role-menu-preview {
        max-width: 320px;
    }

    .role-menu-preview__text {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.45;
        white-space: normal;
    }

    .role-menu-modal__badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .role-menu-modal__badge {
        display: inline-flex;
        align-items: center;
        padding: 0.4rem 0.7rem;
        border-radius: 999px;
        font-size: 0.76rem;
        font-weight: 600;
        color: #0f172a;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-user-shield text-primary me-2"></i>
                    Role dan Akses Menu
                </h4>
                <small class="text-muted">Super Admin dapat menentukan menu apa saja yang boleh diakses oleh tiap role.</small>
            </div>

            <a href="{{ route('setting-role.index') }}" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <form id="roleForm" action="{{ route('setting-role.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="id" id="role_id">

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Nama Role</label>
                            <input
                                type="text"
                                name="permission_role"
                                id="permission_role"
                                class="form-control"
                                list="role-presets"
                                placeholder="Contoh: HOD / Manager / Staff"
                                required>
                            <datalist id="role-presets">
                                @foreach($rolePresets as $roleName => $meta)
                                    <option value="{{ $roleName }}">{{ $meta['scope_label'] }}</option>
                                @endforeach
                            </datalist>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status Role</label>
                            <select name="status" id="status" class="form-select">
                                <option value="1">Aktif</option>
                                <option value="0">Nonaktif</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Role Bawaan</label>
                            <div class="border rounded-3 px-3 py-2 bg-light small text-muted h-100 d-flex align-items-center">
                                Super Admin, HR, HOD, Manager, Supervisor, Staff, Admin Divisi
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Deskripsi</label>
                            <textarea
                                name="description"
                                id="description"
                                class="form-control"
                                rows="3"
                                placeholder="Deskripsi singkat role ini..."></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-semibold mb-1">Hak Akses Menu</h5>
                            <small class="text-muted">Checklist menu yang boleh dibuka oleh role ini. Jika role tidak dicentang pada menu tertentu, akses URL langsung juga akan ditolak.</small>
                        </div>
                        <button type="button" id="toggleAllMenus" class="btn btn-sm btn-outline-primary">Pilih Semua</button>
                    </div>

                    <div class="row g-3 mb-4">
                        @foreach($menuGroups as $group => $menus)
                            <div class="col-lg-6">
                                <div class="border rounded-3 h-100 p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="fw-semibold mb-0">{{ $group }}</h6>
                                        <span class="badge bg-light text-dark">{{ count($menus) }} menu</span>
                                    </div>

                                    @foreach($menus as $menu)
                                        <div class="form-check mb-2">
                                            <input
                                                class="form-check-input role-menu-checkbox"
                                                type="checkbox"
                                                name="menu_permissions[]"
                                                value="{{ $menu['key'] }}"
                                                id="menu_{{ $menu['key'] }}">
                                            <label class="form-check-label" for="menu_{{ $menu['key'] }}">
                                                {{ $menu['label'] }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" id="resetForm" class="btn btn-secondary">
                            Reset
                        </button>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="table-setting-role" class="table table-bordered table-striped mb-0 table-sm small text-sm nowrap">
                        <thead class="table-light">
                            <tr>
                                <th width="50">No</th>
                                <th>Role</th>
                                <th>Scope</th>
                                <th>Deskripsi</th>
                                <th>Menu</th>
                                <th width="100">Status</th>
                                <th width="120">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($roles as $index => $role)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td class="fw-semibold">{{ $role->permission_role }}</td>
                                    <td>{{ $role->scope_label }}</td>
                                    <td>{{ $role->description ?? '-' }}</td>
                                    <td>
                                        @php
                                            $menuLabels = collect($role->resolved_menu_permissions)
                                                ->map(fn($menuKey) => config('access.menus.' . $menuKey . '.label'))
                                                ->filter()
                                                ->values();
                                            $previewLabels = $menuLabels->take(3);
                                            $remainingMenuCount = $menuLabels->count() - $previewLabels->count();
                                        @endphp

                                        <div class="role-menu-preview">
                                            <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                                                <span class="badge bg-info text-dark">{{ $menuLabels->count() }} menu</span>
                                                @if($menuLabels->isNotEmpty())
                                                    <button
                                                        type="button"
                                                        class="btn btn-link btn-sm p-0 text-decoration-none btn-role-menu-detail"
                                                        data-role-name="{{ $role->permission_role }}"
                                                        data-menu-labels='@json($menuLabels->all())'
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#roleMenuDetailModal">
                                                        Lihat detail
                                                    </button>
                                                @endif
                                            </div>

                                            @if($menuLabels->isNotEmpty())
                                                <div class="text-muted small role-menu-preview__text">
                                                    {{ $previewLabels->implode(', ') }}@if($remainingMenuCount > 0), +{{ $remainingMenuCount }} lagi @endif
                                                </div>
                                            @else
                                                <div class="text-muted small">Belum ada menu aktif.</div>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $role->status == '1' ? 'success' : 'secondary' }}">
                                            {{ $role->status == '1' ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary btn-edit"
                                            data-id="{{ $role->id }}"
                                            data-role="{{ $role->permission_role }}"
                                            data-description="{{ $role->description }}"
                                            data-status="{{ $role->status }}"
                                            data-menus='@json($role->resolved_menu_permissions)'>
                                            <i class="fas fa-edit me-1"></i> Edit
                                        </button>

                                        <a href="{{ route('setting-role.destroy', $role->id) }}" class="btn btn-outline-danger btn-sm btn-icon-split" data-confirm-delete="true">
                                            <i class="fas fa-trash me-1"></i> Hapus
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Data role belum tersedia</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="roleMenuDetailModal" tabindex="-1" aria-labelledby="roleMenuDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="roleMenuDetailModalLabel">Detail Menu Role</h5>
                    <small id="roleMenuDetailRoleName" class="text-muted d-block"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="roleMenuDetailContent" class="role-menu-modal__badges"></div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        $("#table-setting-role").DataTable({});

        const rolePresets = @json($rolePresets);
        const menuCheckboxes = $('.role-menu-checkbox');
        const roleForm = $('#roleForm');
        const permissionRoleInput = $('#permission_role');
        const descriptionInput = $('#description');
        let allSelected = false;

        function setCheckedMenus(selectedMenus) {
            menuCheckboxes.each(function() {
                $(this).prop('checked', selectedMenus.includes($(this).val()));
            });
        }

        function resetRoleForm() {
            roleForm.attr('action', "{{ route('setting-role.store') }}");
            $('#role_id').val('');
            permissionRoleInput.val('');
            descriptionInput.val('');
            $('#status').val('1');
            setCheckedMenus([]);
            $('input[name="_method"]').remove();
        }

        permissionRoleInput.on('change blur', function() {
            const roleName = $(this).val();
            const preset = rolePresets[roleName];

            if (!preset) {
                return;
            }

            if (!descriptionInput.val().trim()) {
                descriptionInput.val(preset.description || '');
            }
        });

        $('#toggleAllMenus').on('click', function() {
            allSelected = !allSelected;
            menuCheckboxes.prop('checked', allSelected);
            $(this).text(allSelected ? 'Bersihkan Semua' : 'Pilih Semua');
        });

        $('.btn-edit').click(function() {
            const id = $(this).data('id');
            const role = $(this).data('role');
            const description = $(this).data('description');
            const status = $(this).data('status');
            const menus = JSON.parse($(this).attr('data-menus') || '[]');
            let updateUrl = "{{ route('role.update', ':id') }}";
            updateUrl = updateUrl.replace(':id', id);

            $('#role_id').val(id);
            permissionRoleInput.val(role);
            descriptionInput.val(description);
            $('#status').val(status);
            setCheckedMenus(menus);

            roleForm.attr('action', updateUrl);

            if (roleForm.find('input[name="_method"]').length === 0) {
                roleForm.append('<input type="hidden" name="_method" value="PATCH">');
            }

            $('html, body').animate({
                scrollTop: roleForm.offset().top - 100
            }, 500);
        });

        $('#resetForm').click(function() {
            resetRoleForm();
        });

        $('.btn-role-menu-detail').on('click', function() {
            const roleName = $(this).data('role-name');
            const menuLabels = $(this).data('menu-labels') || [];
            const modalRoleName = $('#roleMenuDetailRoleName');
            const modalContent = $('#roleMenuDetailContent');

            modalRoleName.text(`Role: ${roleName}`);

            if (!menuLabels.length) {
                modalContent.html('<span class="text-muted small">Belum ada menu aktif.</span>');
                return;
            }

            const badges = menuLabels.map(label => `<span class="role-menu-modal__badge">${label}</span>`);
            modalContent.html(badges.join(''));
        });
    });
</script>
@endpush

@endsection
