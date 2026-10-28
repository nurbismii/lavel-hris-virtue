@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner ui-page">

        <div class="ui-page-header">
            <div>
                <h4 class="ui-page-title">
                    <i class="fas fa-user-shield text-primary me-2"></i>
                    {{ __('access.setting_role.index_title') }}
                </h4>
                    <p class="ui-page-subtitle">
                        {{ __('access.setting_role.index_subtitle') }}
                    </p>
            </div>

            <div class="ms-md-auto py-2 py-md-0">
                <a href="{{ route('setting-role.create') }}" class="btn btn-sm btn-secondary">
                    <span class="btn-label">
                        <i class="fa fa-plus"></i>
                    </span>
                    {{ __('access.setting_role.add_role') }}
                </a>
            </div>
        </div>

        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        <form method="GET" class="ui-panel mb-3">
            <div class="ui-panel__body ui-actions ui-actions--sm-stack">
                <div class="ui-field flex-grow-1">
                    <label for="role-search" class="form-label">{{ __('Cari nama, NIK, email, atau peran') }}</label>
                    <input type="search" id="role-search" name="search" value="{{ request('search') }}" maxlength="100" class="form-control @error('search') is-invalid @enderror">
                    @error('search')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button class="btn btn-primary" type="submit" data-loading-text="{{ __('Mencari...') }}">{{ __('Cari') }}</button>
                <a class="btn btn-outline-secondary" href="{{ route('setting-role.index') }}">{{ __('Reset') }}</a>
            </div>
        </form>
        <div class="card shadow-sm border-0">
            <div class="card-body">

                <div class="table-responsive {{ $users->isEmpty() ? 'd-none' : '' }}">
                    <table id="table-setting-role" class="table table-bordered table-striped mb-0 table-sm small text-sm nowrap">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('tables.id') }}</th>
                                <th>{{ __('tables.nik') }}</th>
                                <th>{{ __('tables.name') }}</th>
                                <th>{{ __('tables.email') }}</th>
                                <th>{{ __('tables.status') }}</th>
                                <th>{{ __('tables.role') }}</th>
                                <th>{{ __('tables.scope') }}</th>
                                <th width="120">{{ __('tables.action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $index => $user)
                            <tr>
                                <td>{{ $users->firstItem() + $index }}</td>
                                <td>{{ $user->nik_karyawan }}</td>
                                <td>{{ optional($user->employee)->nama_karyawan ?? $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    <span class="badge bg-{{ $user->status == 'aktif' ? 'success' : 'secondary' }}">
                                        {{ ucfirst($user->status) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark">
                                        {{ $user->display_role_name }}
                                    </span>
                                </td>
                                <td>
                                    {{ optional($user->role)->scope_label ?? __('access.roles.staff.scope_label') }}
                                    @if($user->isHodRole())
                                        <div class="small text-muted mt-1">
                                             {{ __('access.setting_role.department_count', ['count' => count($user->scopedDepartmentIds())]) }},
                                             {{ __('access.setting_role.active_division_count', ['count' => count($user->scopedDivisionIds())]) }}
                                        </div>
                                    @endif
                                    @if($user->isAdminDivisiRole())
                                        <div class="small text-muted mt-1">
                                            {{ __('access.setting_role.active_division_count', ['count' => count($user->scopedDivisionIds())]) }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('setting-role.edit', $user->id) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit me-1"></i> {{ __('access.setting_role.edit') }}
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="8"><div class="ui-empty-state">{{ __('Tidak ada akun yang sesuai dengan pencarian.') }}</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="approval-list-footer">
                    @if($users->isEmpty())<div class="ui-empty-state" role="status">{{ __('Tidak ada akun yang sesuai dengan pencarian.') }}</div>@endif
                    <p>{{ __(':count akun ditemukan.', ['count' => number_format($users->total())]) }}</p>
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
