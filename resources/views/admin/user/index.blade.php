@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">

        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold">
                    <i class="fas fa-user-friends text-primary me-2"></i>
                    {{ __('access.user_management.index_title') }}
                </h4>

                <small class="text-muted">
                    {{ __('access.user_management.index_subtitle') }}
                </small>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="table-user" class="table table-bordered table-striped mb-0 table-sm small text-sm nowrap" width="100%">
                            <thead>
                                <tr>
                                    <th>{{ __('tables.nik') }}</th>
                                    <th>{{ __('tables.name') }}</th>
                                    <th>{{ __('tables.email') }}</th>
                                    <th>{{ __('tables.status') }}</th>
                                    <th>{{ __('tables.role') }}</th>
                                    <th>{{ __('tables.last_login') }}</th>
                                    <th>{{ __('tables.action') }}</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        $('#table-user').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: '{{route('user.datatable')}}',
                type: 'GET'
            },
            columns: [{
                    data: 'nik_karyawan',
                    name: 'nik_karyawan'
                },
                {
                    data: 'nama_karyawan',
                    name: 'nama_karyawan'
                },
                {
                    data: 'email',
                    name: 'email'
                },
                {
                    data: 'status',
                    name: 'status'
                },
                {
                    data: 'role',
                    name: 'role'
                },
                {
                    data: 'terakhir_login',
                    name: 'terakhir_login'
                },
                {
                    data: 'action',
                    name: 'action',
                    orderable: false,
                    searchable: false
                }
            ],
            order: [
                [1, 'asc']
            ]
        });
    });
</script>
@endpush

@endsection