@extends('layouts.app')

@section('content')
<div class="container">
    <div class="page-inner">

        {{-- HEADER --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="text-primary">{{ __('Approval Roster') }}</h3>
                <small class="text-muted">
                    {{ __('Persetujuan HR untuk karyawan pengajuan cuti/insentif') }}
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
                            <th>{{ __('tables.start') }}</th>
                            <th>{{ __('tables.end') }}</th>
                            <th>{{ __('tables.category') }}</th>
                            <th>{{ __('tables.status') }}</th>
                            <th>{{ __('tables.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cutis as $cuti)
                        @php
                            $hrdStatus = (int) $cuti->status_pengajuan_hrd;
                        @endphp
                        <tr>
                            <td>{{ $cuti->employee->nik }}</td>
                            <td>{{ $cuti->employee->nama_karyawan }}</td>
                            <td>{{ formatDateIndonesia($cuti->tgl_mulai_cuti) }}</td>
                            <td>{{ formatDateIndonesia($cuti->tgl_mulai_cuti_berakhir) }}</td>
                            <td>{!! $cuti->status_rencana_label !!}</td>
                            <td>{!! $cuti->status_hrd_label !!}</td>
                            <td>
                                <a href="{{ route('approval.roster.hrd.show', $cuti->id) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i> {{ __('Detail') }}
                                </a>

                                @if($hrdStatus === 0)
                                <form action="{{ route('approval.roster.hrd.process', $cuti->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="action" value="1">
                                    <button class="btn btn-success btn-sm">{{ __('Approve') }}</button>
                                </form>

                                <form action="{{ route('approval.roster.hrd.process', $cuti->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="action" value="2">
                                    <button type="button" class="btn btn-danger btn-sm js-approval-reject" data-bs-toggle="modal" data-bs-target="#approvalRejectReasonModal">{{ __('Reject') }}</button>
                                </form>
                                @elseif($hrdStatus === 1)
                                    <span class="badge bg-success ms-1">{{ __('Disetujui HR') }}</span>
                                @else
                                    <span class="badge bg-danger ms-1">{{ __('Ditolak HR') }}</span>
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
        $("#table-approval-roster").DataTable({
            order: [
                [1, 'desc']
            ] // kolom index 1, urut terbaru dulu
        });
    });
</script>
@endpush


@endsection
