@extends('layouts.app')

@section('content')
<div class="container">

    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-sign-out-alt text-primary me-2"></i>
                    Data pengajuan cuti
                </h4>
                <small class="text-muted">
                    Pengajuan cuti dan status pengajuan kamu
                </small>
            </div>

            <div class="ms-md-auto py-2 py-md-0">
                <a href="{{ route('cuti.create') }}" class="btn btn-sm btn-secondary">
                    <span class="btn-label">
                        <i class="fa fa-plus"></i>
                    </span>
                    Pengajuan Cuti
                </a>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="table-cuti" class="table table-bordered table-striped mb-0 table-sm small text-sm nowrap">
                            <thead class="table-light">
                                <tr>
                                    <th>Pengajuan cuti</th>
                                    <th>NIK</th>
                                    <th>Nama</th>
                                    <th>Mulai cuti</th>
                                    <th>Berakhir cuti</th>
                                    <th>Jumlah Cuti</th>
                                    <th>Status HOD</th>
                                    <th>Status HR</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cuti as $c)
                                <tr>
                                    <td>{{ formatDateIndonesia($c->tanggal) }}</td>
                                    <td>{{ $c->nik_karyawan }}</td>
                                    <td>{{ $c->employee->nama_karyawan }}</td>
                                    <td>{{ formatDateIndonesia($c->tanggal_mulai) }}</td>
                                    <td>{{ formatDateIndonesia($c->tanggal_berakhir) }}</td>
                                    <td>{{ $c->jumlah }} Hari</td>
                                    <td>{!! $c->status_hod_label !!}</td>
                                    <td>{!! $c->status_hrd_label !!}</td>
                                    <td>
                                        <a href="{{ route('cuti.edit', $c->id) }}" class="btn btn-sm btn-primary btn-sm btn-icon-split">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-edit"></i>
                                            </span>
                                            <span class="text">Edit</span>
                                        </a>
                                        <a href="{{ route('cuti.destroy', $c->id) }}" class="btn btn-danger btn-sm btn-icon-split" data-confirm-delete="true">
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

@push('scripts')
<script>
    $(document).ready(function() {
        $("#table-cuti").DataTable({
            responsive: true,
        });
    });
</script>
@endpush

@endsection