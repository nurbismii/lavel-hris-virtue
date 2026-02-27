@extends('layouts.app')

@section('content')
<div class="container">

    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-users text-primary me-2"></i>
                    Data Pengguna
                </h4>
                <small class="text-muted">
                    Tambah pengguna kepada aplikasi search by security
                </small>
            </div>

            <div class="ms-md-auto py-2 py-md-0">
                <a class="btn btn-sm btn-primary" href="{{ route('search-by-security.create') }}">
                    Tambah Pengguna
                </a>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="multi-filter-select" class="table table-bordered table-striped mb-0 table-sm small text-sm nowrap">
                            <thead>
                                <tr>
                                    <th>NIK</th>
                                    <th>Email</th>
                                    <th>Tanggal Lahir</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $user)
                                <tr>
                                    <td>{{ $user->nik }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>{{ $user->tgl_lahir }}</td>
                                    <td>
                                        <a href="{{ route('search-by-security.edit', $user->id) }}" class="btn btn-sm btn-primary btn-sm btn-icon-split">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-edit"></i>
                                            </span>
                                            <span class="text">Edit</span>
                                        </a>
                                        <a href="{{ route('search-by-security.destroy', $user->id) }}" class="btn btn-danger btn-sm btn-icon-split" data-confirm-delete="true">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-trash"></i>
                                            </span>
                                            <span class="text">Hapus</span>
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
</div>

@endsection