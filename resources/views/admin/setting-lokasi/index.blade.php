@extends('layouts.app')

@section('content')
<div class="container">

    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h3 class="fw-bold mb-3">Data Lokasi Presensi</h3>
            </div>

            <div class="ms-md-auto py-2 py-md-0">
                <a href="{{ route('setting-lokasi-presensi.create') }}" class="btn btn-sm btn-secondary">
                    <span class="btn-label">
                        <i class="fa fa-plus"></i>
                    </span>
                    Lokasi presensi
                </a>
            </div>

        </div>

        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="table-lokasi-presensi" class="table table-bordered table-striped mb-0 table-sm small text-sm nowrap">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Departemen</th>
                                    <th>Divisi</th>
                                    <th>Latitude</th>
                                    <th>Longitude</th>
                                    <th>Radius <sup>m</sup></th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lokasi as $key => $lok)
                                <tr>
                                    <td>{{ ++$key }}</td>
                                    <td>{{ $lok->divisi->departemen->departemen }}</td>
                                    <td>{{ $lok->divisi->nama_divisi }}</td>
                                    <td>{{ $lok->lat }}</td>
                                    <td>{{ $lok->long }}</td>
                                    <td>{{ $lok->radius }}</td>
                                    <td>
                                        <a href="{{ route('setting-lokasi-presensi.edit', $lok->id) }}" class="btn btn-sm btn-primary btn-sm btn-icon-split">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-edit"></i>
                                            </span>
                                            <span class="text">Edit</span>
                                        </a>
                                        <a href="{{ route('setting-lokasi-presensi.destroy', $lok->id) }}" class="btn btn-danger btn-sm btn-icon-split" data-confirm-delete="true">
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
        $("#table-lokasi-presensi").DataTable({
            responsive: true,
        });
    });
</script>
@endpush

@endsection