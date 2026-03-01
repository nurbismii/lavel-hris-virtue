@extends('layouts.app')

@section('content')
<div class="container">

    <div class="page-inner">

        {{-- HEADER --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="text-primary">Approval Cuti Tahunan</h3>
                <small class="text-muted">
                    Persetujuan HOD untuk karyawan pengajuan cuti tahunan
                </small>
            </div>
        </div>

        <div class="card">
            <div class="card-body">

                <table id="table-approval-cuti" class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Pengajuan</th>
                            <th>Mulai</th>
                            <th>Berakhir</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cutis as $cuti)
                        <tr>
                            <td>{{ $cuti->employee->nama_karyawan }}</td>
                            <td>{{ formatDateIndonesia($cuti->tanggal) }}</td>
                            <td>{{ formatDateIndonesia($cuti->tanggal_mulai) }}</td>
                            <td>{{ formatDateIndonesia($cuti->tanggal_berakhir)}}</td>
                            <td>{{ $cuti->jumlah }} Hari</td>
                            <td>{!! $cuti->status_hod_label !!}</td>
                            <td>
                                <form action="{{ route('approval.cuti.hod.process', $cuti->id) }}" method="POST">
                                    @csrf
                                    <button name="action" value="1" class="btn btn-success btn-sm">
                                        Approve
                                    </button>
                                    <button name="action" value="2" class="btn btn-danger btn-sm">
                                        Reject
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        $("#table-approval-cuti").DataTable({
            order: [
                [1, 'desc']
            ] // kolom index 1, urut terbaru dulu
        });
    });
</script>
@endpush
@endsection