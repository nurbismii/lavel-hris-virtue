@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold">
                    <i class="fas fa-user-shield text-primary me-2"></i>
                    {{ __('access.setting_role.index_title') }}
                    <small class="text-muted">
                        {{ __('access.setting_role.index_subtitle') }}
                    </small>
                </h4>
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

        <div class="card shadow-sm border-0">
            <div class="card-body">

                <div class="table-responsive">
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
                            @foreach($users as $index => $user)
                            <tr>
                                <td>{{ ++$index }}</td>
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
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        $("#table-setting-role").DataTable({});
    });
</script>
@endpush

@endsection
