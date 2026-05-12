@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas text-primary"></i>
                    Approval OFF Roster HOD
                </h4>
                <small class="text-muted">Persetujuan HOD untuk pengajuan hari OFF karyawan roster.</small>
            </div>
        </div>

        <div class="card border-0" style="border-radius:16px; box-shadow:0 12px 30px rgba(15,23,42,.06);">
            <div class="card-body table-responsive">
                <table id="table-approval-roster-off" class="table table-bordered table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>NIK</th>
                            <th>Nama</th>
                            <th>Departemen</th>
                            <th>Tanggal OFF</th>
                            <th>Alasan</th>
                            <th>Delegasi</th>
                            <th>Status HOD</th>
                            <th>Status HR</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($offRequests as $offRequest)
                        @php
                            $employee = $offRequest->employee;
                            $department = optional(optional(optional($employee)->divisi)->departemen)->departemen;
                            $isPending = (int) $offRequest->status_hod === \App\Models\RosterOffRequest::STATUS_PENDING;
                        @endphp
                        <tr>
                            <td>{{ $offRequest->nik_karyawan }}</td>
                            <td>{{ optional($employee)->nama_karyawan ?? '-' }}</td>
                            <td>{{ $department ?? '-' }}</td>
                            <td>{{ formatDateIndonesia($offRequest->tanggal_off) }}</td>
                            <td>{{ $offRequest->alasan ?: '-' }}</td>
                            <td>{!! $offRequest->status_delegate_label !!}</td>
                            <td>{!! $offRequest->status_hod_label !!}</td>
                            <td>{!! $offRequest->status_hrd_label !!}</td>
                            <td>
                                @if($isPending)
                                <div class="d-flex gap-2 justify-content-center">
                                    <form action="{{ route('approval.roster-off.hod.process', $offRequest->id) }}" method="POST" data-approval-confirm-message="Setujui pengajuan OFF roster ini?">
                                        @csrf
                                        <input type="hidden" name="action" value="1">
                                        <button class="btn btn-success btn-sm">
                                            <i class="fas fa-check me-1"></i>
                                            Approve
                                        </button>
                                    </form>
                                    <form action="{{ route('approval.roster-off.hod.process', $offRequest->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="action" value="2">
                                        <button type="button" class="btn btn-danger btn-sm js-approval-reject" data-bs-toggle="modal" data-bs-target="#approvalRejectReasonModal">
                                            <i class="fas fa-times me-1"></i>
                                            Reject
                                        </button>
                                    </form>
                                </div>
                                @elseif((int) $offRequest->status_hod === \App\Models\RosterOffRequest::STATUS_APPROVED && (int) $offRequest->status_hrd === \App\Models\RosterOffRequest::STATUS_PENDING)
                                    <span class="badge bg-info">Menunggu HR</span>
                                    <small class="d-block text-muted mt-1">Disetujui HOD</small>
                                @elseif((int) $offRequest->status_hod === \App\Models\RosterOffRequest::STATUS_APPROVED && (int) $offRequest->status_hrd === \App\Models\RosterOffRequest::STATUS_APPROVED)
                                    <span class="badge bg-success">Disetujui HR</span>
                                    <small class="d-block text-muted mt-1">Proses selesai</small>
                                @elseif((int) $offRequest->status_hod === \App\Models\RosterOffRequest::STATUS_APPROVED && (int) $offRequest->status_hrd === \App\Models\RosterOffRequest::STATUS_REJECTED)
                                    <span class="badge bg-danger">Ditolak HR</span>
                                @else
                                    <span class="badge bg-danger">Ditolak HOD</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @if(method_exists($offRequests, 'links'))
                <div class="mt-3">
                    {{ $offRequests->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        $("#table-approval-roster-off").DataTable({
            responsive: true,
            order: [[3, 'desc']]
        });
    });
</script>
@endpush
@endsection
