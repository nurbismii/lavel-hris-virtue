@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner ui-page">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
            <div>
                <h4 class="ui-page-title fw-bold mb-1">
                    <i class="fas text-primary"></i>
                    {{ __('Approval OFF Roster HR') }}
                </h4>
                <small class="text-muted">{{ __('Review akhir HR untuk pengajuan hari OFF karyawan roster yang sudah disetujui HOD.') }}</small>
            </div>
        </div>

        @include('approval.partials.list-filter')

        <div class="card border-0" style="border-radius:16px; box-shadow:0 12px 30px rgba(15,23,42,.06);">
            <div class="card-body">
                <div class="ui-table-wrap {{ $offRequests->isEmpty() ? 'd-none' : '' }}" role="region" tabindex="0" aria-label="{{ __('Daftar pengajuan') }}">
                <table id="table-approval-roster-off" class="approval-table table table-bordered table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('tables.nik') }}</th>
                            <th>{{ __('tables.name') }}</th>
                            <th>{{ __('tables.department') }}</th>
                            <th>{{ __('tables.off_date') }}</th>
                            <th>{{ __('tables.reason') }}</th>
                            <th>{{ __('tables.hod_status') }}</th>
                            <th>{{ __('tables.hr_status') }}</th>
                            <th>{{ __('tables.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($offRequests as $offRequest)
                        @php
                            $employee = $offRequest->employee;
                            $department = optional(optional(optional($employee)->divisi)->departemen)->departemen;
                            $isPending = (int) $offRequest->status_hod === \App\Models\RosterOffRequest::STATUS_APPROVED
                                && (int) $offRequest->status_hrd === \App\Models\RosterOffRequest::STATUS_PENDING;
                        @endphp
                        <tr>
                            <td>{{ $offRequest->nik_karyawan }}</td>
                            <td>{{ optional($employee)->nama_karyawan ?? '-' }}</td>
                            <td>{{ $department ?? '-' }}</td>
                            <td>{{ formatDateIndonesia($offRequest->tanggal_off) }}</td>
                            <td>{{ $offRequest->alasan ?: '-' }}</td>
                            <td>{!! $offRequest->status_hod_label !!}</td>
                            <td>{!! $offRequest->status_hrd_label !!}</td>
                            <td>
                                @if($isPending)
                                <div class="d-flex gap-2 justify-content-center">
                                    <form action="{{ route('approval.roster-off.hrd.process', $offRequest->id) }}" method="POST" data-approval-confirm-message="Setujui pengajuan OFF roster ini di level HR?">
                                        @csrf
                                        <input type="hidden" name="action" value="1">
                                        <button class="btn btn-success btn-sm">
                                            <i class="fas fa-check me-1"></i>
                                            {{ __('Approve') }}
                                        </button>
                                    </form>
                                    <form action="{{ route('approval.roster-off.hrd.process', $offRequest->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="action" value="2">
                                        <button type="button" class="btn btn-danger btn-sm js-approval-reject" data-bs-toggle="modal" data-bs-target="#approvalRejectReasonModal">
                                            <i class="fas fa-times me-1"></i>
                                            {{ __('Reject') }}
                                        </button>
                                    </form>
                                </div>
                                @elseif((int) $offRequest->status_hrd === \App\Models\RosterOffRequest::STATUS_APPROVED)
                                    <span class="badge bg-success">{{ __('Disetujui HR') }}</span>
                                    <small class="d-block text-muted mt-1">{{ __('Proses selesai') }}</small>
                                @elseif((int) $offRequest->status_hrd === \App\Models\RosterOffRequest::STATUS_REJECTED)
                                    <span class="badge bg-danger">{{ __('Ditolak HR') }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ __('Tidak tersedia') }}</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8"><div class="ui-empty-state"><i class="fas fa-inbox" aria-hidden="true"></i><span>{{ __('Tidak ada pengajuan yang sesuai dengan pencarian.') }}</span></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                @include('approval.partials.list-footer', ['items' => $offRequests])
            </div>
        </div>
    </div>
</div>


@endsection
