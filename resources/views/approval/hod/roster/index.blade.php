@extends('layouts.app')

@section('content')
<div class="container-fluid">
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
                            <th>{{ __('tables.nik') }}</th>
                            <th>{{ __('tables.name') }}</th>
                            <th>{{ __('tables.submission') }}</th>
                            <th>{{ __('tables.start') }}</th>
                            <th>{{ __('tables.end') }}</th>
                            <th>{{ __('tables.category') }}</th>
                            <th>{{ __('tables.delegation') }}</th>
                            <th>{{ __('tables.status') }}</th>
                            <th>{{ __('tables.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cutis as $cuti)
                        @php
                            $hodStatus = (int) $cuti->status_pengajuan;
                            $hrdStatus = (int) $cuti->status_pengajuan_hrd;
                        @endphp
                        <tr>
                            <td>{{ $cuti->employee->nik }}</td>
                            <td>{{ $cuti->employee->nama_karyawan }}</td>
                            <td>{{ formatDateIndonesia($cuti->tanggal_pengajuan) }}</td>
                            <td>{{ formatDateIndonesia($cuti->tgl_mulai_cuti) }}</td>
                            <td>{{ formatDateIndonesia($cuti->tgl_mulai_cuti_berakhir) }}</td>
                            <td>{!! $cuti->status_rencana_label !!}</td>
                            <td>{!! $cuti->status_delegate_label !!}</td>
                            <td>{!! $cuti->status_hod_label !!}</td>
                            <td>
                                <div class="d-flex gap-2 justify-content-center">

                                    <a href="{{ route('approval.roster.hod.show', $cuti->id) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i> Detail
                                    </a>

                                    @if($hodStatus === 0)
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
                                        <button type="button" class="btn btn-danger btn-sm js-approval-reject" data-bs-toggle="modal" data-bs-target="#approvalRejectReasonModal">
                                            <i class="fas fa-times me-1"></i> Reject
                                        </button>
                                    </form>
                                    @elseif($hodStatus === 1 && $hrdStatus === 0)
                                        <span class="badge bg-info align-self-center">Menunggu HR</span>
                                    @elseif($hodStatus === 1 && $hrdStatus === 1)
                                        <span class="badge bg-success align-self-center">Disetujui HR</span>
                                    @elseif($hodStatus === 1 && $hrdStatus === 2)
                                        <span class="badge bg-danger align-self-center">Ditolak HR</span>
                                    @else
                                        <span class="badge bg-danger align-self-center">Ditolak HOD</span>
                                    @endif

                                </div>
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
        $("#table-approval-roster").DataTable({
            order: [
                [1, 'desc']
            ] // kolom index 1, urut terbaru dulu
        });
    });
</script>
@endpush


@endsection
