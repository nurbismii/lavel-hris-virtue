@extends('layouts.app')

@section('content')
<div class="container">
    <div class="page-inner">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-file-signature text-primary me-2"></i>
                    Data Izin (Paid & Unpaid)
                </h4>
                <small class="text-muted">
                    Daftar izin berbayar dan tidak berbayar
                </small>
            </div>

            <a href="{{ route('izin.create') }}" class="btn btn-sm btn-primary">
                <i class="fas fa-plus me-1"></i> Ajukan Izin
            </a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">

                <div class="table-responsive">
                    <table id="table-izin" class="table table-bordered table-striped mb-0 table-sm small text-sm nowrap">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Tipe</th>
                                <th>Tanggal Pengajuan</th>
                                <th>Periode</th>
                                <th>Jumlah Hari</th>
                                <th>Status HOD</th>
                                <th>Status HR</th>
                                <th>Bukti</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{!! $row->status_tipe_label !!}</td>
                                <td>{{ formatDateIndonesia($row->tanggal) }}</td>
                                <td>
                                    {{ formatDateIndonesia($row->tanggal_mulai) }}
                                    <br>
                                    <small class="text-muted">
                                        s/d
                                        {{ formatDateIndonesia($row->tanggal_berakhir) }}
                                    </small>
                                </td>
                                <td>
                                    <span class="fw-bold">
                                        {{ $row->jumlah }} Hari
                                    </span>
                                </td>
                                <td>{!! $row->status_hod_label !!}</td>
                                <td>{!! $row->status_hrd_label !!}</td>
                                <td>
                                    @if($row->foto)
                                    <a href="{{ asset($row->foto) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('izin.edit', $row->id) }}" class="btn btn-sm btn-primary btn-sm btn-icon-split">
                                        <span class="icon text-white-50">
                                            <i class="fas fa-edit"></i>
                                        </span>
                                        <span class="text">Edit</span>
                                    </a>
                                    <a href="{{ route('izin.destroy', $row->id) }}" class="btn btn-danger btn-sm btn-icon-split" data-confirm-delete="true">
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

@push('scripts')
<script>
    $(document).ready(function() {
        $("#table-izin").DataTable({
            responsive: true,
        });
    });
</script>
@endpush

@endsection