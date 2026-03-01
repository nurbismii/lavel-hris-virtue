@extends('layouts.app')

@section('content')
<div class="container">
    <div class="page-inner">

        {{-- HEADER --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="text-primary">Approval Roster</h3>
                <small class="text-muted">
                    Persetujuan HOD untuk karyawan pengajuan cuti/insentif
                </small>
            </div>
        </div>


        <div class="card">
            <div class="card-body table-responsive">
                <table id="table-approval-roster" class="table table-bordered">
                    <thead>
                        <tr>
                            <th>NIK</th>
                            <th>Nama</th>
                            <th>Pengajuan</th>
                            <th>Mulai</th>
                            <th>Berakhir</th>
                            <th>Kategori</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cutis as $cuti)
                        <tr>
                            <td>{{ $cuti->employee->nik }}</td>
                            <td>{{ $cuti->employee->nama_karyawan }}</td>
                            <td>{{ formatDateIndonesia($cuti->tanggal_pengajuan) }}</td>
                            <td>{{ formatDateIndonesia($cuti->tgl_mulai_cuti) }}</td>
                            <td>{{ formatDateIndonesia($cuti->tgl_mulai_cuti_berakhir) }}</td>
                            <td>{!! $cuti->status_rencana_label !!}</td>
                            <td>{!! $cuti->status_hod_label !!}</td>
                            <td>
                                <div class="d-flex gap-2 justify-content-center">

                                    <a href="{{ route('approval.roster.hod.show', $cuti->id) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i> Detail
                                    </a>

                                    <form action="{{ route('approval.roster.hod.process', $cuti->id) }}"
                                        method="POST">
                                        @csrf
                                        <input type="hidden" name="action" value="1">
                                        <button class="btn btn-success btn-sm">
                                            <i class="fas fa-check me-1"></i> Approve
                                        </button>
                                    </form>

                                    <form action="{{ route('approval.roster.hod.process', $cuti->id) }}"
                                        method="POST">
                                        @csrf
                                        <input type="hidden" name="action" value="2">
                                        <button class="btn btn-danger btn-sm">
                                            <i class="fas fa-times me-1"></i> Reject
                                        </button>
                                    </form>

                                </div>
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
        $("#table-approval-roster").DataTable({
            order: [
                [1, 'desc']
            ] // kolom index 1, urut terbaru dulu
        });
    });
</script>
@endpush


@endsection