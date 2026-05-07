@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">

        {{-- HEADER --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="text-primary">Approval Cuti Tahunan</h3>
                <small class="text-muted">
                    Persetujuan HR untuk karyawan pengajuan cuti tahunan
                </small>
            </div>
        </div>

        <div class="card">
            <div class="card-body">

                <table id="table-approval-cuti" class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Tanggal Pengajuan</th>
                            <th>Mulai</th>
                            <th>Berakhir</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cutis as $cuti)
                        @php
                            $hrdStatus = (int) $cuti->status_hrd;
                        @endphp
                        <tr>
                            <td>{{ $cuti->employee->nama_karyawan }}</td>
                            <td>{{ formatDateIndonesia($cuti->tanggal) }}</td>
                            <td>{{ formatDateIndonesia($cuti->tanggal_mulai) }}</td>
                            <td>{{ formatDateIndonesia($cuti->tanggal_berakhir)}}</td>
                            <td>{{ $cuti->jumlah }} Hari</td>
                            <td>{!! $cuti->status_hrd_label !!}</td>
                            <td>
                                @if($hrdStatus === 0)
                                <form action="{{ route('approval.cuti.hrd.process', $cuti->id) }}" method="POST">
                                    @csrf
                                    <button name="action" value="1" class="btn btn-success btn-sm">
                                        Approve
                                    </button>
                                    <button type="button" name="action" value="2" class="btn btn-danger btn-sm js-approval-reject" data-bs-toggle="modal" data-bs-target="#approvalRejectReasonModal">
                                        Reject
                                    </button>
                                </form>
                                @elseif($hrdStatus === 1)
                                    <span class="badge bg-success">Disetujui HR</span>
                                    <small class="d-block text-muted mt-1">Proses selesai</small>
                                @else
                                    <span class="badge bg-danger">Ditolak HR</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @if(method_exists($cutis, 'links'))
                <div class="mt-3">
                    {{ $cutis->links() }}
                </div>
                @endif
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
