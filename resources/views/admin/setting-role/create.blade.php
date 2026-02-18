@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">

        {{-- HEADER --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold">
                <i class="fas fa-user-shield text-primary me-2"></i>
                Tambah Role Permission
            </h4>

            <a href="{{ route('setting-role.index') }}" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>

        {{-- ALERT --}}
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        {{-- CARD FORM --}}
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">

                <form id="roleForm" action="{{ route('setting-role.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="id" id="role_id">

                    {{-- Nama Role --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Nama Permission Role
                        </label>
                        <input type="text"
                            name="permission_role"
                            id="permission_role"
                            class="form-control"
                            placeholder="Contoh: Super Admin / HRD / Manager">
                    </div>

                    {{-- Description --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Deskripsi
                        </label>
                        <textarea name="description"
                            id="description"
                            class="form-control"
                            rows="3"
                            placeholder="Deskripsi singkat role ini..."></textarea>
                    </div>

                    {{-- Status --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            Status Role
                        </label>

                        <select name="status" id="status" class="form-select">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
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
                                <th>Permission Role</th>
                                <th>Deskripsi</th>
                                <th width="100">Status</th>
                                <th width="120">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($roles as $index => $role)
                            <tr>
                                <td>{{ $index + 1 }}</td>

                                <td class="fw-semibold">
                                    {{ $role->permission_role }}
                                </td>

                                <td>
                                    {{ $role->description ?? '-' }}
                                </td>

                                <td>
                                    <span class="badge bg-{{ $role->status == '1' ? 'success' : 'secondary' }}">
                                        {{ $role->status == '1' ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>

                                <td>
                                    <button type="button"
                                        class="btn btn-sm btn-outline-primary btn-edit"
                                        data-id="{{ $role->id }}"
                                        data-role="{{ $role->permission_role }}"
                                        data-description="{{ $role->description }}"
                                        data-status="{{ $role->status }}">
                                        <i class="fas fa-edit me-1"></i> Edit
                                    </button>

                                    <a href="{{ route('setting-role.destroy', $role->id) }}" class="btn btn-outline-danger btn-sm btn-icon-split" data-confirm-delete="true">
                                        <i class="fas fa-trash me-1"></i> Hapus
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    Data role belum tersedia
                                </td>
                            </tr>
                            @endforelse
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

<script>
    $(document).ready(function() {

        $('.btn-edit').click(function() {

            let id = $(this).data('id');
            let role = $(this).data('role');
            let description = $(this).data('description');
            let status = $(this).data('status');
            let updateUrl = "{{ route('role.update', ':id') }}";
            updateUrl = updateUrl.replace(':id', id);

            $('#role_id').val(id);
            $('#permission_role').val(role);
            $('#description').val(description);
            $('#status').val(status);

            // Ubah action jadi update
            $('#roleForm').attr('action', updateUrl);

            if ($('#roleForm input[name="_method"]').length == 0) {
                $('#roleForm').append('<input type="hidden" name="_method" value="PATCH">');
            }
            
            $('html, body').animate({
                scrollTop: $("#roleForm").offset().top - 100
            }, 500);
        });

        $('#resetForm').click(function() {

            $('#roleForm').attr('action', "{{ route('setting-role.store') }}");
            $('#role_id').val('');
            $('#permission_role').val('');
            $('#description').val('');
            $('#status').val('1');

            $('input[name="_method"]').remove();
        });

    });
</script>
@endpush



@endsection