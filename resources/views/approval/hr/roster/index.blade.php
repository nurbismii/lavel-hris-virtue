@extends('layouts.app')

@section('content')
<div class="container">
    <div class="page-inner">

        {{-- HEADER --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="text-primary">Approval HR</h3>
                <small class="text-muted">
                    Persetujuan HR untuk karyawan pengajuan cuti/insentif
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
                            <td>{{ formatDateIndonesia($cuti->tgl_mulai_cuti) }}</td>
                            <td>{{ formatDateIndonesia($cuti->tgl_mulai_cuti_berakhir) }}</td>
                            <td>{!! $cuti->status_rencana_label !!}</td>
                            <td>{!! $cuti->status_hrd_label !!}</td>
                            <td>
                                <form action="{{ route('approval.roster.hrd.process', $cuti->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="action" value="1">
                                    <button class="btn btn-success btn-sm">Approve</button>
                                </form>

                                <form action="{{ route('approval.roster.hrd.process', $cuti->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="action" value="2">
                                    <button class="btn btn-danger btn-sm">Reject</button>
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
        $("#table-approval-roster").DataTable({
            order: [
                [1, 'desc']
            ] // kolom index 1, urut terbaru dulu
        });
    });
</script>
@endpush


@endsection