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
                    <form id="user-filters" class="row g-3 mb-3 align-items-end">
                        <div class="col-12 col-md-4">
                            <label for="filter-status" class="form-label">{{ __('Status akun') }}</label>
                            <select id="filter-status" class="form-select">
                                <option value="">{{ __('Semua status') }}</option>
                                <option value="aktif">{{ __('Aktif') }}</option>
                                <option value="tidak aktif">{{ __('Tidak aktif') }}</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="filter-role" class="form-label">{{ __('Role') }}</label>
                            <select id="filter-role" class="form-select">
                                <option value="">{{ __('Semua role') }}</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}">{{ $role->permission_role }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <button type="button" id="reset-user-filters" class="btn btn-outline-secondary">{{ __('Reset filter & pencarian') }}</button>
                        </div>
                    </form>
                    <div id="user-table-feedback" class="small text-muted mb-2" role="status" aria-live="polite"></div>
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
        var table = $('#table-user').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            searchDelay: 400,
            ajax: {
                url: '{{route('user.datatable')}}',
                type: 'GET',
                data: function (data) {
                    data.status = $('#filter-status').val();
                    data.role_id = $('#filter-role').val();
                },
                beforeSend: function () {
                    $('#user-filters :input').prop('disabled', true);
                    $('#user-table-feedback').text(@json(__('Memuat data user...')));
                },
                complete: function () {
                    $('#user-filters :input').prop('disabled', false);
                },
                error: function (xhr) {
                    var message = @json(__('Data user gagal dimuat. Silakan coba lagi.'));
                    if (xhr.status === 401 || xhr.status === 419) {
                        message = @json(__('Sesi login berakhir. Silakan login ulang.'));
                    } else if (xhr.status === 403) {
                        message = @json(__('Anda tidak memiliki akses ke data user.'));
                    } else if (xhr.status === 0) {
                        message = @json(__('Koneksi bermasalah. Silakan cek jaringan Anda.'));
                    } else if (xhr.status === 422) {
                        message = @json(__('Filter atau pencarian tidak valid. Pencarian maksimal 200 karakter.'));
                    }
                    $('#table-user_processing').hide();
                    $('#user-table-feedback').text(message);
                    Swal.fire({ icon: 'error', title: @json(__('Gagal')), text: message });
                }
            },
            columns: [{
                    data: 'nik_karyawan',
                    name: 'nik_karyawan'
                },
                {
                    data: 'nama_karyawan',
                    name: 'employee.nama_karyawan'
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
                    name: 'role',
                    orderable: false
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
            language: {
                search: @json(__('Cari:')),
                searchPlaceholder: @js(__('NIK, nama, email, role')),
                emptyTable: @json(__('Belum ada data user.')),
                zeroRecords: @json(__('Tidak ada user yang sesuai pencarian atau filter.'))
            },
            drawCallback: function () {
                $('#user-table-feedback').text(this.api().page.info().recordsDisplay + ' user ditemukan.');
            },
            order: [
                [1, 'asc']
            ]
        });
        $('#user-filters').on('submit', function (event) { event.preventDefault(); });
        $('#filter-status, #filter-role').on('change', function () { table.ajax.reload(); });
        $('#reset-user-filters').on('click', function () {
            $('#filter-status, #filter-role').val('');
            table.search('').columns().search('').draw();
        });
    });
</script>
@endpush

@endsection
