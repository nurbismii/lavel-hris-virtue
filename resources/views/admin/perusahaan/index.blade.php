@extends('layouts.app')

@section('content')
<div class="container">

    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-hotel text-primary me-2"></i>
                    Data Perusahaan
                </h4>
                <small class="text-muted">
                    Daftar departemen/divisi
                </small>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="multi-filter-select" class="table table-bordered table-striped mb-0 table-sm small text-sm nowrap">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Kode</th>
                                    <th>Perusahaan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($perusahaan as $p)
                                <tr>
                                    <td>{{ ++$no }}</td>
                                    <td>{{ $p->kode_perusahaan }}</td>
                                    <td>{{ $p->nama_perusahaan }}</td>
                                    <td>
                                        <a href="{{ route('perusahaan.show', $p->id) }}" class="btn btn-sm btn-primary btn-sm btn-icon-split">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-eye"></i>
                                            </span>
                                            <span class="text">Detail</span>
                                        </a>
                                        <a href="{{ route('perusahaan.edit', $p->id) }}" class="btn btn-sm btn-warning btn-sm btn-icon-split">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-edit"></i>
                                            </span>
                                            <span class="text">Edit</span>
                                        </a>
                                        <a href="{{ route('perusahaan.destroy', $p->id) }}" class="btn btn-danger btn-sm btn-icon-split" data-confirm-delete="true">
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