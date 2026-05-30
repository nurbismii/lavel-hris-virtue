@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        {{-- HEADER --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="text-primary">Approval Izin (Paid/Unpaid)</h3>
                <small class="text-muted">
                    Persetujuan HR untuk pengajuan izin paid/unpaid
                </small>
            </div>
        </div>

        @include('approval.partials.feedback-alerts')

        <div class="card">
            <div class="card-body">

                <table id="table-approval-izin" class="table table-bordered">
                    <thead>
                        <tr>
                            <th>{{ __('tables.name') }}</th>
                            <th>{{ __('tables.submission') }}</th>
                            <th>{{ __('tables.start') }}</th>
                            <th>{{ __('tables.end') }}</th>
                            <th>{{ __('tables.amount') }}</th>
                            <th>{{ __('tables.status') }}</th>
                            <th>{{ __('tables.action') }}</th>
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
                                <form action="{{ route('approval.izin.hrd.process', $cuti->id) }}" method="POST" data-approval-confirm-message="Setujui pengajuan izin ini?" data-loading-text="Memproses approval...">
                                    @csrf
                                    <button type="submit" name="action" value="1" class="btn btn-success btn-sm" data-loading-text="Menyetujui...">
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
        $("#table-approval-izin").DataTable({
            order: [
                [1, 'desc']
            ] // kolom index 1, urut terbaru dulu
        });
    });
</script>
@endpush
@endsection
